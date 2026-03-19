<?php

namespace CargoDocsStudio\Http\Rest;

use CargoDocsStudio\Database\Repository\TemplateRepository;
use CargoDocsStudio\Database\Repository\AuditRepository;
use CargoDocsStudio\Domain\Render\RenderPipeline;

class TemplatesController
{
    private const ALLOWED_DOC_TYPES = ['invoice', 'receipt', 'skr', 'spa'];
    private const MAX_JSON_BYTES = 250000;
    private const MAX_JSON_DEPTH = 12;
    private const MAX_JSON_NODES = 3000;

    public function registerRoutes(): void
    {
        register_rest_route('cds/v1', '/templates', [
            'methods' => 'GET',
            'callback' => [$this, 'index'],
            'permission_callback' => [$this, 'canManageTemplates'],
        ]);

        register_rest_route('cds/v1', '/templates', [
            'methods' => 'POST',
            'callback' => [$this, 'store'],
            'permission_callback' => [$this, 'canManageTemplates'],
        ]);

        register_rest_route('cds/v1', '/templates/revisions', [
            'methods' => 'GET',
            'callback' => [$this, 'revisions'],
            'permission_callback' => [$this, 'canAccessRevisions'],
        ]);

        register_rest_route('cds/v1', '/templates/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'show'],
            'permission_callback' => [$this, 'canManageTemplates'],
        ]);

        register_rest_route('cds/v1', '/templates/(?P<id>\d+)/revisions', [
            'methods' => 'POST',
            'callback' => [$this, 'storeRevision'],
            'permission_callback' => [$this, 'canManageTemplates'],
        ]);

        register_rest_route('cds/v1', '/templates/(?P<id>\d+)/revisions/duplicate', [
            'methods' => 'POST',
            'callback' => [$this, 'duplicateRevision'],
            'permission_callback' => [$this, 'canManageTemplates'],
        ]);

        register_rest_route('cds/v1', '/templates/(?P<id>\d+)/publish', [
            'methods' => 'POST',
            'callback' => [$this, 'publish'],
            'permission_callback' => [$this, 'canPublishTemplates'],
        ]);

        register_rest_route('cds/v1', '/templates/preview', [
            'methods' => 'POST',
            'callback' => [$this, 'preview'],
            'permission_callback' => [$this, 'canManageTemplates'],
        ]);

        register_rest_route('cds/v1', '/templates/preview-html', [
            'methods' => 'POST',
            'callback' => [$this, 'previewHtml'],
            'permission_callback' => [$this, 'canManageTemplates'],
        ]);
    }

    public function canManageTemplates(): bool
    {
        return current_user_can('cds_manage_templates') || current_user_can('manage_options');
    }

    public function canPublishTemplates(): bool
    {
        return current_user_can('cds_publish_templates') || current_user_can('manage_options');
    }

    public function canGenerateDocuments(): bool
    {
        return current_user_can('cds_generate_documents') || current_user_can('manage_options');
    }

    public function canAccessRevisions(): bool
    {
        return $this->canManageTemplates() || $this->canGenerateDocuments();
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo = new TemplateRepository();
        $docType = sanitize_key((string) $request->get_param('doc_type'));

        return new \WP_REST_Response([
            'ok' => true,
            'templates' => $repo->listTemplates($docType ?: null),
        ]);
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $repo = new TemplateRepository();
        $template = $repo->getTemplateById($id);

        if (!$template) {
            return $this->errorResponse(404, 'template_not_found', 'Template not found.');
        }

        return new \WP_REST_Response([
            'ok' => true,
            'template' => $template,
        ]);
    }

    public function revisions(\WP_REST_Request $request): \WP_REST_Response
    {
        $docType = sanitize_key((string) $request->get_param('doc_type'));
        if ($docType === '') {
            return $this->errorResponse(
                400,
                'validation_error',
                'doc_type is required',
                [['field' => 'doc_type', 'message' => 'doc_type is required.']]
            );
        }

        if (!in_array($docType, self::ALLOWED_DOC_TYPES, true)) {
            return $this->errorResponse(
                400,
                'validation_error',
                'Invalid doc_type value',
                [['field' => 'doc_type', 'message' => 'doc_type must be one of: invoice, receipt, skr, spa.']]
            );
        }

        $repo = new TemplateRepository();
        $revisions = $this->canManageTemplates()
            ? $repo->listRevisionsByDocType($docType)
            : $repo->listPublishedRevisionsByDocType($docType);

        return new \WP_REST_Response([
            'ok' => true,
            'revisions' => $revisions,
        ]);
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = (array) $request->get_json_params();
        $docTypeKey = sanitize_key((string) ($payload['doc_type_key'] ?? ''));
        $name = sanitize_text_field((string) ($payload['name'] ?? ''));
        $schema = is_array($payload['schema'] ?? null) ? $payload['schema'] : [];
        $theme = is_array($payload['theme'] ?? null) ? $payload['theme'] : [];
        $layout = is_array($payload['layout'] ?? null) ? $payload['layout'] : [];
        $isDefault = !empty($payload['is_default']);
        $publish = !empty($payload['publish']);

        $validationErrors = [];
        if ($docTypeKey === '') {
            $validationErrors[] = ['field' => 'doc_type_key', 'message' => 'doc_type_key is required.'];
        } elseif (!in_array($docTypeKey, self::ALLOWED_DOC_TYPES, true)) {
            $validationErrors[] = ['field' => 'doc_type_key', 'message' => 'doc_type_key must be one of: invoice, receipt, skr, spa.'];
        }
        if ($name === '') {
            $validationErrors[] = ['field' => 'name', 'message' => 'name is required.'];
        }

        if (!empty($validationErrors)) {
            return $this->errorResponse(400, 'validation_error', 'Template payload is invalid.', $validationErrors);
        }

        $constraintErrors = array_merge(
            $this->validateStructureLimits('schema', $schema),
            $this->validateStructureLimits('theme', $theme),
            $this->validateStructureLimits('layout', $layout)
        );
        if (!empty($constraintErrors)) {
            return $this->errorResponse(400, 'payload_limits_exceeded', 'Template payload exceeds allowed limits.', $constraintErrors);
        }

        $repo = new TemplateRepository();
        $templateId = $repo->createTemplate($docTypeKey, $name, $schema, $theme, $layout, $isDefault);
        if ($templateId instanceof \WP_Error) {
            return $this->errorResponse(500, 'template_create_failed', $templateId->get_error_message());
        }

        if ($publish) {
            $template = $repo->getTemplateById($templateId);
            $latest = $template['revisions'][0] ?? null;
            if ($latest) {
                $repo->publishRevision($templateId, (int) $latest['id'], $isDefault);
            }
        }

        return new \WP_REST_Response([
            'ok' => true,
            'template_id' => $templateId,
        ], 201);
    }

    public function storeRevision(\WP_REST_Request $request): \WP_REST_Response
    {
        $templateId = (int) $request->get_param('id');
        $payload = (array) $request->get_json_params();

        if ($templateId <= 0) {
            return $this->errorResponse(
                400,
                'validation_error',
                'Template id is required',
                [['field' => 'id', 'message' => 'Template id is required.']]
            );
        }

        $schema = is_array($payload['schema'] ?? null) ? $payload['schema'] : [];
        $theme = is_array($payload['theme'] ?? null) ? $payload['theme'] : [];
        $layout = is_array($payload['layout'] ?? null) ? $payload['layout'] : [];
        $publish = !empty($payload['publish']);

        $constraintErrors = array_merge(
            $this->validateStructureLimits('schema', $schema),
            $this->validateStructureLimits('theme', $theme),
            $this->validateStructureLimits('layout', $layout)
        );
        if (!empty($constraintErrors)) {
            return $this->errorResponse(400, 'payload_limits_exceeded', 'Template payload exceeds allowed limits.', $constraintErrors);
        }

        $repo = new TemplateRepository();
        $template = $repo->getTemplateById($templateId);
        if (!$template) {
            return $this->errorResponse(404, 'template_not_found', 'Template not found.');
        }

        $revisionId = $repo->createRevision($templateId, $schema, $theme, $layout, false);
        if ($revisionId instanceof \WP_Error) {
            return $this->errorResponse(500, 'revision_create_failed', $revisionId->get_error_message());
        }

        if ($publish) {
            $repo->publishRevision($templateId, $revisionId, !empty($payload['is_default']));
        }

        return new \WP_REST_Response([
            'ok' => true,
            'revision_id' => $revisionId,
        ], 201);
    }

    public function duplicateRevision(\WP_REST_Request $request): \WP_REST_Response
    {
        $templateId = (int) $request->get_param('id');
        $payload = (array) $request->get_json_params();
        $sourceRevisionId = (int) ($payload['revision_id'] ?? 0);
        $publish = !empty($payload['publish']);

        if ($templateId <= 0 || $sourceRevisionId <= 0) {
            $fields = [];
            if ($templateId <= 0) {
                $fields[] = ['field' => 'id', 'message' => 'Template id is required.'];
            }
            if ($sourceRevisionId <= 0) {
                $fields[] = ['field' => 'revision_id', 'message' => 'revision_id is required.'];
            }

            return $this->errorResponse(400, 'validation_error', 'template id and revision_id are required', $fields);
        }

        $repo = new TemplateRepository();
        $template = $repo->getTemplateById($templateId);
        if (!$template) {
            return $this->errorResponse(404, 'template_not_found', 'Template not found.');
        }

        $newRevisionId = $repo->duplicateRevision($templateId, $sourceRevisionId, $publish);
        if ($newRevisionId instanceof \WP_Error) {
            $code = $newRevisionId->get_error_code();
            if ($code === 'cds_revision_not_found') {
                return $this->errorResponse(
                    404,
                    'revision_not_found',
                    $newRevisionId->get_error_message(),
                    [['field' => 'revision_id', 'message' => 'Provided revision does not exist.']]
                );
            }
            if ($code === 'cds_revision_template_mismatch') {
                return $this->errorResponse(
                    400,
                    'revision_template_mismatch',
                    $newRevisionId->get_error_message(),
                    [['field' => 'revision_id', 'message' => 'Provided revision does not belong to this template.']]
                );
            }

            return $this->errorResponse(500, 'revision_duplicate_failed', $newRevisionId->get_error_message());
        }

        $this->logAudit('template_revision_duplicated', 'template', $templateId, [
            'source_revision_id' => $sourceRevisionId,
            'new_revision_id' => (int) $newRevisionId,
            'published' => $publish ? 1 : 0,
        ]);

        return new \WP_REST_Response([
            'ok' => true,
            'revision_id' => (int) $newRevisionId,
        ], 201);
    }

    public function publish(\WP_REST_Request $request): \WP_REST_Response
    {
        $templateId = (int) $request->get_param('id');
        $payload = (array) $request->get_json_params();
        $revisionId = (int) ($payload['revision_id'] ?? 0);
        $isDefault = !empty($payload['is_default']);

        if ($templateId <= 0 || $revisionId <= 0) {
            $fields = [];
            if ($templateId <= 0) {
                $fields[] = ['field' => 'id', 'message' => 'template id is required.'];
            }
            if ($revisionId <= 0) {
                $fields[] = ['field' => 'revision_id', 'message' => 'revision_id is required.'];
            }

            return $this->errorResponse(400, 'validation_error', 'template id and revision_id are required', $fields);
        }

        $repo = new TemplateRepository();
        $template = $repo->getTemplateById($templateId);
        if (!$template) {
            return $this->errorResponse(404, 'template_not_found', 'Template not found.');
        }

        $ok = $repo->publishRevision($templateId, $revisionId, $isDefault);

        if (!$ok) {
            $this->logAudit('template_publish_failed', 'template', $templateId, [
                'revision_id' => $revisionId,
                'is_default' => $isDefault ? 1 : 0,
            ]);
            return $this->errorResponse(
                500,
                'revision_publish_failed',
                'Failed to publish revision',
                [['field' => 'revision_id', 'message' => 'Failed to publish the selected revision.']]
            );
        }

        $this->logAudit('template_published', 'template', $templateId, [
            'revision_id' => $revisionId,
            'is_default' => $isDefault ? 1 : 0,
        ]);

        return new \WP_REST_Response([
            'ok' => true,
        ]);
    }

    public function preview(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = (array) $request->get_json_params();
        $constraintErrors = array_merge(
            $this->validateStructureLimits('schema', is_array($payload['schema'] ?? null) ? $payload['schema'] : []),
            $this->validateStructureLimits('theme', is_array($payload['theme'] ?? null) ? $payload['theme'] : []),
            $this->validateStructureLimits('layout', is_array($payload['layout'] ?? null) ? $payload['layout'] : []),
            $this->validateStructureLimits('payload', is_array($payload['payload'] ?? null) ? $payload['payload'] : [])
        );
        if (!empty($constraintErrors)) {
            return $this->errorResponse(400, 'payload_limits_exceeded', 'Preview payload exceeds allowed limits.', $constraintErrors);
        }

        $resolved = $this->resolveTemplatePayload($payload);
        if ($resolved instanceof \WP_REST_Response) {
            return $resolved;
        }

        $templateConfig = $resolved['template'];
        $samplePayload = $resolved['payload'];
        $renderer = new RenderPipeline();
        $result = $renderer->generateInvoicePreviewPdf($templateConfig, $samplePayload);

        if (empty($result['success'])) {
            return $this->errorResponse(500, 'preview_pdf_failed', $result['error'] ?? 'Failed to generate preview PDF');
        }

        return new \WP_REST_Response([
            'ok' => true,
            'pdf_url' => $result['file_url'] ?? '',
            'tracking_qr_data_uri' => $result['tracking_qr_data_uri'] ?? '',
            'payment_qr_data_uri' => $result['payment_qr_data_uri'] ?? '',
            'payment_uri' => $result['payment_uri'] ?? '',
            'selected_engine' => $result['selected_engine'] ?? 'tcpdf',
            'engine_used' => $result['engine_used'] ?? ($result['selected_engine'] ?? 'tcpdf'),
            'engine_fallback' => $result['engine_fallback'] ?? null,
            'engine_fallback_reason' => $result['engine_fallback_reason'] ?? null,
        ]);
    }

    public function previewHtml(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = (array) $request->get_json_params();
        $constraintErrors = array_merge(
            $this->validateStructureLimits('schema', is_array($payload['schema'] ?? null) ? $payload['schema'] : []),
            $this->validateStructureLimits('theme', is_array($payload['theme'] ?? null) ? $payload['theme'] : []),
            $this->validateStructureLimits('layout', is_array($payload['layout'] ?? null) ? $payload['layout'] : []),
            $this->validateStructureLimits('payload', is_array($payload['payload'] ?? null) ? $payload['payload'] : [])
        );
        if (!empty($constraintErrors)) {
            return $this->errorResponse(400, 'payload_limits_exceeded', 'Preview payload exceeds allowed limits.', $constraintErrors);
        }

        $resolved = $this->resolveTemplatePayload($payload);
        if ($resolved instanceof \WP_REST_Response) {
            return $resolved;
        }

        $templateConfig = $resolved['template'];
        $samplePayload = $resolved['payload'];
        $renderer = new RenderPipeline();
        $result = $renderer->generateInvoicePreviewHtml($templateConfig, $samplePayload);

        return new \WP_REST_Response([
            'ok' => true,
            'html' => (string) ($result['html'] ?? ''),
            'tracking_qr_data_uri' => $result['tracking_qr_data_uri'] ?? '',
            'payment_qr_data_uri' => $result['payment_qr_data_uri'] ?? '',
            'payment_uri' => $result['payment_uri'] ?? '',
        ]);
    }

    private function resolveTemplatePayload(array $payload): array|\WP_REST_Response
    {
        $revisionId = isset($payload['revision_id']) ? (int) $payload['revision_id'] : 0;
        $docTypeKey = sanitize_key((string) ($payload['doc_type_key'] ?? 'invoice'));
        if (!in_array($docTypeKey, self::ALLOWED_DOC_TYPES, true)) {
            $docTypeKey = 'invoice';
        }

        $repo = new TemplateRepository();
        $templateConfig = [
            'doc_type_key' => $docTypeKey,
            'schema' => is_array($payload['schema'] ?? null) ? $payload['schema'] : [],
            'theme' => is_array($payload['theme'] ?? null) ? $payload['theme'] : [],
            'layout' => is_array($payload['layout'] ?? null) ? $payload['layout'] : [],
        ];

        if ($revisionId > 0) {
            $revision = $repo->getRevisionById($revisionId);
            if (!$revision) {
                return $this->errorResponse(
                    404,
                    'revision_not_found',
                    'Revision not found',
                    [['field' => 'revision_id', 'message' => 'Provided revision does not exist.']]
                );
            }

            $templateConfig = [
                'doc_type_key' => sanitize_key((string) ($revision['doc_type_key'] ?? $docTypeKey)),
                'schema' => is_array($revision['schema_json'] ?? null) ? $revision['schema_json'] : [],
                'theme' => is_array($revision['theme_json'] ?? null) ? $revision['theme_json'] : [],
                'layout' => is_array($revision['layout_json'] ?? null) ? $revision['layout_json'] : [],
            ];
        }

        $samplePayload = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];
        $samplePayload['doc_type_key'] = $templateConfig['doc_type_key'];

        return [
            'template' => $templateConfig,
            'payload' => $samplePayload,
        ];
    }

    /**
     * @param array<int, array{field:string,message:string}> $fields
     */
    private function errorResponse(int $status, string $code, string $message, array $fields = []): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'ok' => false,
            'code' => $code,
            'error' => $message,
            'message' => $message,
            'fields' => $fields,
        ], $status);
    }

    /**
     * @param mixed $value
     * @return array<int, array{field:string,message:string}>
     */
    private function validateStructureLimits(string $field, $value): array
    {
        $errors = [];
        $encoded = wp_json_encode($value);
        if (is_string($encoded) && strlen($encoded) > self::MAX_JSON_BYTES) {
            $errors[] = [
                'field' => $field,
                'message' => $field . ' exceeds size limit of ' . self::MAX_JSON_BYTES . ' bytes.',
            ];
        }

        $depth = $this->maxDepth($value);
        if ($depth > self::MAX_JSON_DEPTH) {
            $errors[] = [
                'field' => $field,
                'message' => $field . ' exceeds max depth of ' . self::MAX_JSON_DEPTH . '.',
            ];
        }

        $nodes = $this->countNodes($value);
        if ($nodes > self::MAX_JSON_NODES) {
            $errors[] = [
                'field' => $field,
                'message' => $field . ' exceeds max node count of ' . self::MAX_JSON_NODES . '.',
            ];
        }

        return $errors;
    }

    /**
     * @param mixed $value
     */
    private function maxDepth($value, int $current = 0): int
    {
        if (!is_array($value)) {
            return $current;
        }

        $max = $current;
        foreach ($value as $child) {
            $childDepth = $this->maxDepth($child, $current + 1);
            if ($childDepth > $max) {
                $max = $childDepth;
            }
        }

        return $max;
    }

    /**
     * @param mixed $value
     */
    private function countNodes($value): int
    {
        if (!is_array($value)) {
            return 1;
        }

        $count = 1;
        foreach ($value as $child) {
            $count += $this->countNodes($child);
        }

        return $count;
    }

    private function logAudit(string $eventType, string $refType, int $refId, array $meta = []): void
    {
        try {
            (new AuditRepository())->log($eventType, get_current_user_id(), $refType, $refId > 0 ? $refId : null, $meta);
        } catch (\Throwable $e) {
            // Audit logging must not break primary workflow.
        }
    }
}
