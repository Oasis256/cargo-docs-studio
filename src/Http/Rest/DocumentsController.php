<?php

namespace CargoDocsStudio\Http\Rest;

use CargoDocsStudio\Database\Repository\DocumentRepository;
use CargoDocsStudio\Database\Repository\AuditRepository;
use CargoDocsStudio\Database\Repository\TemplateRepository;
use CargoDocsStudio\Domain\Render\RenderPipeline;
use CargoDocsStudio\Domain\Tracking\ShipmentService;

class DocumentsController
{
    private const ALLOWED_DOC_TYPES = ['invoice', 'receipt', 'skr'];
    private const MAX_PAYLOAD_BYTES = 200000;
    private const MAX_PAYLOAD_DEPTH = 10;
    private const MAX_PAYLOAD_NODES = 2000;

    public function registerRoutes(): void
    {
        register_rest_route('cds/v1', '/documents', [
            'methods' => 'GET',
            'callback' => [$this, 'index'],
            'permission_callback' => [$this, 'canViewDocuments'],
        ]);

        register_rest_route('cds/v1', '/documents/(?P<id>\d+)/pdf', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deletePdf'],
            'permission_callback' => [$this, 'canDeleteDocuments'],
        ]);

        register_rest_route('cds/v1', '/documents/invoice/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generateInvoice'],
            'permission_callback' => [$this, 'canGenerateDocuments'],
        ]);

        register_rest_route('cds/v1', '/documents/receipt/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generateReceipt'],
            'permission_callback' => [$this, 'canGenerateDocuments'],
        ]);

        register_rest_route('cds/v1', '/documents/skr/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generateSkr'],
            'permission_callback' => [$this, 'canGenerateDocuments'],
        ]);
    }

    public function canViewDocuments(): bool
    {
        return current_user_can('cds_view_documents') || current_user_can('manage_options');
    }

    public function canGenerateDocuments(): bool
    {
        return current_user_can('cds_generate_documents') || current_user_can('manage_options');
    }

    public function canDeleteDocuments(): bool
    {
        return current_user_can('cds_delete_documents') || current_user_can('manage_options');
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo = new DocumentRepository();
        $docType = $request->get_param('doc_type');
        $limit = (int) ($request->get_param('limit') ?: 20);
        $page = (int) ($request->get_param('page') ?: 1);
        $search = sanitize_text_field((string) ($request->get_param('search') ?: ''));
        $result = $repo->listRecentPaged($limit, $page, is_string($docType) ? $docType : null, $search ?: null);

        return new \WP_REST_Response([
            'ok' => true,
            'documents' => $result['items'],
            'pagination' => [
                'page' => (int) $result['page'],
                'pages' => (int) $result['pages'],
                'limit' => (int) $result['limit'],
                'total' => (int) $result['total'],
            ],
        ]);
    }

    public function generateInvoice(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->generateByType($request, 'invoice');
    }

    public function generateReceipt(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->generateByType($request, 'receipt');
    }

    public function generateSkr(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->generateByType($request, 'skr');
    }

    public function deletePdf(\WP_REST_Request $request): \WP_REST_Response
    {
        $documentId = (int) $request->get_param('id');
        if ($documentId <= 0) {
            return $this->errorResponse(400, 'invalid_document_id', 'Invalid document id.');
        }

        $repo = new DocumentRepository();
        $document = $repo->getById($documentId);
        if (!$document) {
            return $this->errorResponse(404, 'document_not_found', 'Document not found.');
        }

        $pdfPath = (string) ($document['pdf_path'] ?? '');
        $fileDeleted = false;
        if ($pdfPath !== '' && file_exists($pdfPath)) {
            $fileDeleted = @unlink($pdfPath);
        }

        $cleared = $repo->clearPdfData($documentId);
        if (!$cleared) {
            return $this->errorResponse(500, 'pdf_clear_failed', 'Failed to clear PDF data for this document.');
        }

        $this->logAudit('document_pdf_deleted', 'document', $documentId, [
            'file_deleted' => $fileDeleted,
            'had_file_path' => $pdfPath !== '',
        ]);

        return new \WP_REST_Response([
            'ok' => true,
            'document_id' => $documentId,
            'file_deleted' => $fileDeleted,
            'message' => 'PDF deleted successfully.',
        ]);
    }

    private function generateByType(\WP_REST_Request $request, string $docTypeKey): \WP_REST_Response
    {
        $payload = (array) $request->get_json_params();
        $docTypeKey = sanitize_key($docTypeKey);
        if (!in_array($docTypeKey, self::ALLOWED_DOC_TYPES, true)) {
            $docTypeKey = 'invoice';
        }

        $constraintErrors = $this->validatePayloadConstraints($payload);
        if (!empty($constraintErrors)) {
            return $this->errorResponse(400, 'payload_limits_exceeded', 'Payload exceeds allowed limits.', $constraintErrors);
        }

        $clientName = sanitize_text_field((string) ($payload['client_name'] ?? ''));
        $clientEmail = sanitize_email((string) ($payload['client_email'] ?? ''));
        $cargoType = sanitize_text_field((string) ($payload['cargo_type'] ?? ''));
        $depositorName = sanitize_text_field((string) ($payload['depositor_name'] ?? ''));
        $contentDescription = sanitize_text_field((string) ($payload['content_description'] ?? ''));
        $lineItems = is_array($payload['line_items'] ?? null) ? $payload['line_items'] : [];
        $validationErrors = [];

        if ($docTypeKey === 'skr') {
            // SKR uses depositor/content fields in the form, while pipeline still expects normalized client/cargo keys.
            if ($depositorName !== '' && $clientName === '') {
                $clientName = $depositorName;
            }
            if ($contentDescription !== '' && $cargoType === '') {
                $cargoType = $contentDescription;
            }
            if ($depositorName === '') {
                $validationErrors[] = ['field' => 'depositor_name', 'message' => 'depositor_name is required.'];
            }
            if ($contentDescription === '') {
                $validationErrors[] = ['field' => 'content_description', 'message' => 'content_description is required.'];
            }
        } else {
            if ($clientName === '') {
                $validationErrors[] = ['field' => 'client_name', 'message' => 'client_name is required.'];
            }
            if ($docTypeKey === 'receipt' && $cargoType === '' && !empty($lineItems)) {
                foreach ($lineItems as $lineItem) {
                    if (!is_array($lineItem)) {
                        continue;
                    }
                    $lineItemLabel = sanitize_text_field((string) ($lineItem['description'] ?? $lineItem['cargo_type'] ?? $lineItem['name'] ?? ''));
                    if ($lineItemLabel !== '') {
                        $cargoType = $lineItemLabel;
                        break;
                    }
                }
            }
            if ($cargoType === '') {
                $validationErrors[] = ['field' => 'cargo_type', 'message' => 'cargo_type is required.'];
            }
        }

        if ($clientEmail === '') {
            $validationErrors[] = ['field' => 'client_email', 'message' => 'client_email is required.'];
        } elseif (!is_email($clientEmail)) {
            $validationErrors[] = ['field' => 'client_email', 'message' => 'client_email must be a valid email address.'];
        }

        if (!empty($validationErrors)) {
            return $this->errorResponse(400, 'validation_error', 'Required payload fields are invalid.', $validationErrors);
        }

        $templateRevisionId = isset($payload['template_revision_id']) ? (int) $payload['template_revision_id'] : 0;
        $templateRepo = new TemplateRepository();
        if ($templateRevisionId > 0) {
            $revision = $templateRepo->getRevisionById($templateRevisionId);
            if (!$revision) {
                return $this->errorResponse(
                    400,
                    'invalid_template_revision',
                    'template_revision_id not found',
                    [['field' => 'template_revision_id', 'message' => 'Provided template revision was not found.']]
                );
            }

            $revisionDocType = sanitize_key((string) ($revision['doc_type_key'] ?? ''));
            if ($revisionDocType !== $docTypeKey) {
                return $this->errorResponse(
                    400,
                    'template_revision_doc_type_mismatch',
                    'template_revision_id does not match selected document type',
                    [['field' => 'template_revision_id', 'message' => 'Template revision type does not match selected document type.']]
                );
            }

            $canManageTemplates = current_user_can('cds_manage_templates') || current_user_can('manage_options');
            $isPublished = !empty($revision['is_published']);
            if (!$canManageTemplates && !$isPublished) {
                return $this->errorResponse(403, 'draft_revision_not_allowed', 'Draft revisions are not allowed for this account');
            }
        } else {
            $published = $templateRepo->getPublishedRevisionForDocType($docTypeKey);
            $templateRevisionId = $published ? (int) $published['id'] : 0;
        }

        if ($templateRevisionId <= 0) {
            return $this->errorResponse(
                400,
                'missing_template_revision',
                'No published template revision found for this document type. Provide template_revision_id or publish a template.',
                [['field' => 'template_revision_id', 'message' => 'No accessible published revision exists for selected document type.']]
            );
        }
        $payload['doc_type_key'] = $docTypeKey;
        $payload['template_revision_id'] = $templateRevisionId;
        $payload['client_name'] = $clientName;
        $payload['client_email'] = $clientEmail;
        $payload['cargo_type'] = $cargoType;

        $shipmentService = new ShipmentService();
        $result = $shipmentService->createDocumentTracking($docTypeKey, $payload, $templateRevisionId);

        if ($result instanceof \WP_Error) {
            $this->logAudit('document_generate_failed', 'document', 0, [
                'doc_type_key' => $docTypeKey,
                'template_revision_id' => $templateRevisionId,
                'stage' => 'tracking_create',
                'error' => $result->get_error_message(),
            ]);
            return $this->errorResponse(500, 'tracking_create_failed', $result->get_error_message());
        }

        $pipeline = new RenderPipeline();
        $payload = $this->applyGeneratedIdentifiers($payload, $docTypeKey, (string) ($result['tracking_code'] ?? ''));
        $pdf = $pipeline->generateDocumentPdf($docTypeKey, $payload, $result);

        if (!$pdf['success']) {
            $this->logAudit('document_generate_failed', 'document', (int) ($result['document_id'] ?? 0), [
                'doc_type_key' => $docTypeKey,
                'template_revision_id' => $templateRevisionId,
                'stage' => 'pdf_render',
                'error' => $pdf['error'] ?? 'Failed to render PDF',
            ]);
            return $this->errorResponse(500, 'pdf_render_failed', $pdf['error'] ?? 'Failed to render PDF');
        }

        $checksum = !empty($pdf['file_path']) && file_exists($pdf['file_path'])
            ? hash_file('sha256', $pdf['file_path'])
            : null;

        $repo = new DocumentRepository();
        $repo->setPdfData((int) $result['document_id'], (string) $pdf['file_path'], (string) $pdf['file_url'], $checksum);
        $this->logAudit('document_generate_success', 'document', (int) $result['document_id'], [
            'doc_type_key' => $docTypeKey,
            'template_revision_id' => $templateRevisionId,
            'engine_used' => (string) ($pdf['engine_used'] ?? ''),
        ]);

        return new \WP_REST_Response([
            'ok' => true,
            'doc_type_key' => $docTypeKey,
            'document_id' => $result['document_id'],
            'shipment_id' => $result['shipment_id'],
            'tracking_code' => $result['tracking_code'],
            'tracking_token' => $result['tracking_token'],
            'tracking_url' => $result['tracking_url'],
            'tracking_qr_data_uri' => $pdf['tracking_qr_data_uri'] ?? '',
            'payment_qr_data_uri' => $pdf['payment_qr_data_uri'] ?? '',
            'payment_uri' => $pdf['payment_uri'] ?? '',
            'pdf_url' => $pdf['file_url'] ?? '',
            'selected_engine' => $pdf['selected_engine'] ?? 'tcpdf',
            'engine_used' => $pdf['engine_used'] ?? ($pdf['selected_engine'] ?? 'tcpdf'),
            'engine_fallback' => $pdf['engine_fallback'] ?? null,
            'engine_fallback_reason' => $pdf['engine_fallback_reason'] ?? null,
            'identifiers' => [
                'document_number' => (string) ($payload['document_number'] ?? ''),
                'invoice_number' => (string) ($payload['invoice_number'] ?? ''),
                'receipt_number' => (string) ($payload['receipt_number'] ?? ''),
                'deposit_number' => (string) ($payload['deposit_number'] ?? ''),
                'reg_number' => (string) ($payload['reg_number'] ?? ''),
            ],
            'message' => ucfirst($docTypeKey) . ' PDF generated successfully.',
        ], 201);
    }

    private function applyGeneratedIdentifiers(array $payload, string $docTypeKey, string $trackingCode): array
    {
        $trackingCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', $trackingCode) ?: '');
        if ($trackingCode === '') {
            $trackingCode = strtoupper(wp_generate_password(8, false, false));
        }

        $datePart = current_time('Ymd');
        $suffix = substr($trackingCode, -8);
        if ($suffix === '') {
            $suffix = strtoupper(wp_generate_password(8, false, false));
        }

        $payload['document_number'] = 'DOC-' . $datePart . '-' . $suffix;

        if ($docTypeKey === 'invoice') {
            $payload['invoice_number'] = 'INV-' . $datePart . '-' . $suffix;
        } elseif ($docTypeKey === 'receipt') {
            $payload['receipt_number'] = 'RCP-' . $datePart . '-' . $suffix;
        } elseif ($docTypeKey === 'skr') {
            $payload['deposit_number'] = 'ESL' . $datePart . substr($suffix, 0, 4);
            $payload['reg_number'] = 'ESL-A-' . substr($suffix, -3);
        }

        return $payload;
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
     * @return array<int, array{field:string,message:string}>
     */
    private function validatePayloadConstraints(array $payload): array
    {
        $errors = [];
        $encoded = wp_json_encode($payload);
        if (is_string($encoded) && strlen($encoded) > self::MAX_PAYLOAD_BYTES) {
            $errors[] = [
                'field' => 'payload',
                'message' => 'Payload size exceeds limit of ' . self::MAX_PAYLOAD_BYTES . ' bytes.',
            ];
        }

        $depth = $this->maxDepth($payload);
        if ($depth > self::MAX_PAYLOAD_DEPTH) {
            $errors[] = [
                'field' => 'payload',
                'message' => 'Payload nesting exceeds max depth of ' . self::MAX_PAYLOAD_DEPTH . '.',
            ];
        }

        $nodes = $this->countNodes($payload);
        if ($nodes > self::MAX_PAYLOAD_NODES) {
            $errors[] = [
                'field' => 'payload',
                'message' => 'Payload structure exceeds max node count of ' . self::MAX_PAYLOAD_NODES . '.',
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
