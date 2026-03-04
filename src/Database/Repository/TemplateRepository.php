<?php

namespace CargoDocsStudio\Database\Repository;

class TemplateRepository
{
    private string $templatesTable;
    private string $revisionsTable;

    public function __construct()
    {
        global $wpdb;
        $this->templatesTable = $wpdb->prefix . 'cds_templates';
        $this->revisionsTable = $wpdb->prefix . 'cds_template_revisions';
    }

    public function listTemplates(?string $docTypeKey = null): array
    {
        global $wpdb;

        $where = '';
        $params = [];
        if (!empty($docTypeKey)) {
            $where = 'WHERE t.doc_type_key = %s';
            $params[] = sanitize_key($docTypeKey);
        }

        $sql = "SELECT t.id, t.doc_type_key, t.name, t.status, t.is_default, t.created_by, t.created_at, t.updated_at,
                    r.id AS latest_revision_id, r.revision_no AS latest_revision_no, r.is_published AS latest_is_published
                FROM {$this->templatesTable} t
                LEFT JOIN {$this->revisionsTable} r
                    ON r.id = (
                        SELECT rr.id
                        FROM {$this->revisionsTable} rr
                        WHERE rr.template_id = t.id
                        ORDER BY rr.revision_no DESC
                        LIMIT 1
                    )
                {$where}
                ORDER BY t.id DESC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public function getTemplateById(int $templateId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->templatesTable} WHERE id = %d", $templateId),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        $row['revisions'] = $this->listRevisions($templateId);
        return $row;
    }

    public function listRevisions(int $templateId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->revisionsTable} WHERE template_id = %d ORDER BY revision_no DESC", $templateId),
            ARRAY_A
        ) ?: [];

        foreach ($rows as &$row) {
            $row['schema_json'] = !empty($row['schema_json']) ? json_decode((string) $row['schema_json'], true) : [];
            $row['theme_json'] = !empty($row['theme_json']) ? json_decode((string) $row['theme_json'], true) : [];
            $row['layout_json'] = !empty($row['layout_json']) ? json_decode((string) $row['layout_json'], true) : [];
        }
        unset($row);

        return $rows;
    }

    public function listRevisionsByDocType(string $docTypeKey): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.id, r.template_id, r.revision_no, r.is_published, r.created_at, t.name AS template_name, t.doc_type_key
                 FROM {$this->revisionsTable} r
                 INNER JOIN {$this->templatesTable} t ON t.id = r.template_id
                 WHERE t.doc_type_key = %s
                 ORDER BY t.name ASC, r.revision_no DESC",
                sanitize_key($docTypeKey)
            ),
            ARRAY_A
        ) ?: [];

        return $rows;
    }

    public function listPublishedRevisionsByDocType(string $docTypeKey): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.id, r.template_id, r.revision_no, r.is_published, r.created_at, t.name AS template_name, t.doc_type_key
                 FROM {$this->revisionsTable} r
                 INNER JOIN {$this->templatesTable} t ON t.id = r.template_id
                 WHERE t.doc_type_key = %s AND r.is_published = 1
                 ORDER BY t.name ASC, r.revision_no DESC",
                sanitize_key($docTypeKey)
            ),
            ARRAY_A
        ) ?: [];

        return $rows;
    }

    public function getRevisionById(int $revisionId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT r.*, t.doc_type_key, t.name AS template_name
                 FROM {$this->revisionsTable} r
                 INNER JOIN {$this->templatesTable} t ON t.id = r.template_id
                 WHERE r.id = %d",
                $revisionId
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        $row['schema_json'] = !empty($row['schema_json']) ? json_decode((string) $row['schema_json'], true) : [];
        $row['theme_json'] = !empty($row['theme_json']) ? json_decode((string) $row['theme_json'], true) : [];
        $row['layout_json'] = !empty($row['layout_json']) ? json_decode((string) $row['layout_json'], true) : [];

        return $row;
    }

    public function createTemplate(
        string $docTypeKey,
        string $name,
        array $schema = [],
        array $theme = [],
        array $layout = [],
        bool $isDefault = false,
        ?int $createdBy = null
    ): int|\WP_Error {
        global $wpdb;

        $createdBy = $createdBy ?: get_current_user_id();
        $docTypeKey = sanitize_key($docTypeKey);
        $name = sanitize_text_field($name);

        if ($docTypeKey === '' || $name === '') {
            return new \WP_Error('cds_template_invalid', 'doc_type_key and name are required');
        }

        $now = current_time('mysql');
        $ok = $wpdb->insert(
            $this->templatesTable,
            [
                'doc_type_key' => $docTypeKey,
                'name' => $name,
                'status' => 'draft',
                'is_default' => $isDefault ? 1 : 0,
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );

        if ($ok === false) {
            return new \WP_Error('cds_template_create_failed', $wpdb->last_error ?: 'Failed to create template');
        }

        $templateId = (int) $wpdb->insert_id;
        if ($isDefault) {
            $this->unsetDefaultForDocType($docTypeKey, $templateId);
        }

        $revisionId = $this->createRevision($templateId, $schema, $theme, $layout, false, $createdBy);
        if ($revisionId instanceof \WP_Error) {
            return $revisionId;
        }

        return $templateId;
    }

    public function createRevision(
        int $templateId,
        array $schema,
        array $theme,
        array $layout,
        bool $publish = false,
        ?int $createdBy = null
    ): int|\WP_Error {
        global $wpdb;

        $template = $this->getTemplateById($templateId);
        if (!$template) {
            return new \WP_Error('cds_template_not_found', 'Template not found');
        }

        $nextRevision = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COALESCE(MAX(revision_no), 0) + 1 FROM {$this->revisionsTable} WHERE template_id = %d", $templateId)
        );

        $createdBy = $createdBy ?: get_current_user_id();
        $ok = $wpdb->insert(
            $this->revisionsTable,
            [
                'template_id' => $templateId,
                'revision_no' => $nextRevision,
                'schema_json' => wp_json_encode($schema),
                'theme_json' => wp_json_encode($theme),
                'layout_json' => wp_json_encode($layout),
                'is_published' => $publish ? 1 : 0,
                'created_by' => $createdBy,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s']
        );

        if ($ok === false) {
            return new \WP_Error('cds_template_revision_failed', $wpdb->last_error ?: 'Failed to create template revision');
        }

        $revisionId = (int) $wpdb->insert_id;

        if ($publish) {
            $this->publishRevision($templateId, $revisionId);
        } else {
            $wpdb->update(
                $this->templatesTable,
                ['updated_at' => current_time('mysql')],
                ['id' => $templateId],
                ['%s'],
                ['%d']
            );
        }

        return $revisionId;
    }

    public function duplicateRevision(int $templateId, int $sourceRevisionId, bool $publish = false, ?int $createdBy = null): int|\WP_Error
    {
        $source = $this->getRevisionById($sourceRevisionId);
        if (!$source) {
            return new \WP_Error('cds_revision_not_found', 'Source revision not found');
        }

        if ((int) ($source['template_id'] ?? 0) !== $templateId) {
            return new \WP_Error('cds_revision_template_mismatch', 'Source revision does not belong to selected template');
        }

        $schema = is_array($source['schema_json'] ?? null) ? $source['schema_json'] : [];
        $theme = is_array($source['theme_json'] ?? null) ? $source['theme_json'] : [];
        $layout = is_array($source['layout_json'] ?? null) ? $source['layout_json'] : [];

        return $this->createRevision($templateId, $schema, $theme, $layout, $publish, $createdBy);
    }

    public function publishRevision(int $templateId, int $revisionId, bool $setDefault = false): bool
    {
        global $wpdb;

        $template = $this->getTemplateById($templateId);
        if (!$template) {
            return false;
        }

        $wpdb->query('START TRANSACTION');
        try {
            $wpdb->update(
                $this->revisionsTable,
                ['is_published' => 0],
                ['template_id' => $templateId],
                ['%d'],
                ['%d']
            );

            $updated = $wpdb->update(
                $this->revisionsTable,
                ['is_published' => 1],
                ['id' => $revisionId, 'template_id' => $templateId],
                ['%d'],
                ['%d', '%d']
            );

            if ($updated === false) {
                throw new \RuntimeException('Failed to mark revision published');
            }

            $templateUpdate = [
                'status' => 'published',
                'updated_at' => current_time('mysql'),
            ];
            $templateUpdateFormats = ['%s', '%s'];
            if ($setDefault) {
                $templateUpdate['is_default'] = 1;
                $templateUpdateFormats[] = '%d';
            }

            $wpdb->update(
                $this->templatesTable,
                $templateUpdate,
                ['id' => $templateId],
                $templateUpdateFormats,
                ['%d']
            );

            if ($setDefault) {
                $this->unsetDefaultForDocType((string) $template['doc_type_key'], $templateId);
            }

            $wpdb->query('COMMIT');
            return true;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }

    public function getPublishedRevisionForDocType(string $docTypeKey): ?array
    {
        global $wpdb;

        $docTypeKey = sanitize_key($docTypeKey);
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT r.*, t.id AS template_id, t.name AS template_name, t.doc_type_key
                 FROM {$this->templatesTable} t
                 INNER JOIN {$this->revisionsTable} r ON r.template_id = t.id AND r.is_published = 1
                 WHERE t.doc_type_key = %s
                 ORDER BY t.is_default DESC, r.revision_no DESC
                 LIMIT 1",
                $docTypeKey
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        $row['schema_json'] = !empty($row['schema_json']) ? json_decode((string) $row['schema_json'], true) : [];
        $row['theme_json'] = !empty($row['theme_json']) ? json_decode((string) $row['theme_json'], true) : [];
        $row['layout_json'] = !empty($row['layout_json']) ? json_decode((string) $row['layout_json'], true) : [];

        return $row;
    }

    private function unsetDefaultForDocType(string $docTypeKey, int $exceptTemplateId): void
    {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->templatesTable}
                 SET is_default = 0
                 WHERE doc_type_key = %s AND id != %d",
                sanitize_key($docTypeKey),
                $exceptTemplateId
            )
        );
    }
}
