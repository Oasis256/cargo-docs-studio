<?php

namespace CargoDocsStudio\Database\Repository;

class DocumentRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'cds_documents';
    }

    public function create(string $docTypeKey, int $templateRevisionId, array $payload, array $computed = [], ?int $createdBy = null): int|\WP_Error
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table,
            [
                'doc_type_key' => sanitize_key($docTypeKey),
                'template_revision_id' => $templateRevisionId,
                'status' => 'generated',
                'payload_json' => wp_json_encode($payload),
                'computed_json' => wp_json_encode($computed),
                'created_by' => $createdBy ?: get_current_user_id(),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%d', '%s', '%s', '%s', '%d', '%s']
        );

        if ($result === false) {
            return new \WP_Error('cds_document_create_failed', $wpdb->last_error ?: 'Failed to create document');
        }

        return (int) $wpdb->insert_id;
    }

    public function setPdfData(int $documentId, string $pdfPath, string $pdfUrl, ?string $checksum = null): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            [
                'pdf_path' => $pdfPath,
                'pdf_url' => $pdfUrl,
                'checksum' => $checksum,
            ],
            ['id' => $documentId],
            ['%s', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function clearPdfData(int $documentId): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            [
                'pdf_path' => null,
                'pdf_url' => null,
                'checksum' => null,
            ],
            ['id' => $documentId],
            ['%s', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function getById(int $documentId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $documentId),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        $row['payload_json'] = !empty($row['payload_json']) ? json_decode($row['payload_json'], true) : [];
        $row['computed_json'] = !empty($row['computed_json']) ? json_decode($row['computed_json'], true) : [];

        return $row;
    }

    public function listRecent(int $limit = 20, ?string $docType = null): array
    {
        $result = $this->listRecentPaged($limit, 1, $docType, null);
        return $result['items'];
    }

    public function listRecentPaged(int $limit = 20, int $page = 1, ?string $docType = null, ?string $search = null): array
    {
        global $wpdb;

        $limit = max(1, min($limit, 100));
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];

        if (!empty($docType) && $docType !== 'all') {
            $where[] = 'd.doc_type_key = %s';
            $params[] = sanitize_key($docType);
        }

        $searchTerm = trim((string) $search);
        if ($searchTerm !== '') {
            $like = '%' . $wpdb->esc_like($searchTerm) . '%';
            $where[] = '(s.tracking_code LIKE %s OR d.payload_json LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM {$this->table} d LEFT JOIN {$wpdb->prefix}cds_shipments s ON s.document_id = d.id {$whereSql}";
        $total = (int) $wpdb->get_var($params ? $wpdb->prepare($countSql, ...$params) : $countSql);

        $sql = "SELECT d.id, d.doc_type_key, d.status, d.pdf_url, d.created_by, d.created_at, s.tracking_code
            FROM {$this->table} d
            LEFT JOIN {$wpdb->prefix}cds_shipments s ON s.document_id = d.id
            {$whereSql}
            ORDER BY d.id DESC
            LIMIT %d OFFSET %d";

        $rowsParams = array_merge($params, [$limit, $offset]);
        $items = $wpdb->get_results($wpdb->prepare($sql, ...$rowsParams), ARRAY_A) ?: [];
        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;

        return [
            'items' => $items,
            'total' => $total,
            'page' => min($page, $totalPages),
            'limit' => $limit,
            'pages' => $totalPages,
        ];
    }
}
