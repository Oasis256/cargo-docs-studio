<?php

namespace CargoDocsStudio\Domain\Maintenance;

use CargoDocsStudio\Database\Repository\SettingsRepository;

class RetentionCleanupService
{
    private const DEFAULT_PDF_DAYS = 90;
    private const DEFAULT_MAX_DRAFT_REVISIONS = 20;

    private SettingsRepository $settings;

    public function __construct()
    {
        $this->settings = new SettingsRepository();
    }

    public function getPolicy(): array
    {
        $stored = $this->settings->get('retention_policy', []);
        $pdfDays = isset($stored['pdf_days']) ? (int) $stored['pdf_days'] : self::DEFAULT_PDF_DAYS;
        $maxDraftRevisions = isset($stored['max_draft_revisions']) ? (int) $stored['max_draft_revisions'] : self::DEFAULT_MAX_DRAFT_REVISIONS;

        $pdfDays = max(1, min($pdfDays, 3650));
        $maxDraftRevisions = max(1, min($maxDraftRevisions, 200));

        return [
            'pdf_days' => $pdfDays,
            'max_draft_revisions' => $maxDraftRevisions,
        ];
    }

    public function savePolicy(int $pdfDays, int $maxDraftRevisions): bool
    {
        $pdfDays = max(1, min($pdfDays, 3650));
        $maxDraftRevisions = max(1, min($maxDraftRevisions, 200));

        return $this->settings->set('retention_policy', [
            'pdf_days' => $pdfDays,
            'max_draft_revisions' => $maxDraftRevisions,
        ]);
    }

    public function run(): array
    {
        $policy = $this->getPolicy();
        $pdf = $this->cleanupOldPdfFiles((int) $policy['pdf_days']);
        $revisionsDeleted = $this->cleanupOldDraftRevisions((int) $policy['max_draft_revisions']);

        return [
            'policy' => $policy,
            'pdf_candidates' => $pdf['candidates'],
            'pdf_files_deleted' => $pdf['files_deleted'],
            'documents_cleared' => $pdf['documents_cleared'],
            'revisions_deleted' => $revisionsDeleted,
        ];
    }

    private function cleanupOldPdfFiles(int $pdfDays): array
    {
        global $wpdb;

        $documentsTable = $wpdb->prefix . 'cds_documents';
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($pdfDays * DAY_IN_SECONDS));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, pdf_path
                 FROM {$documentsTable}
                 WHERE created_at < %s
                   AND pdf_path IS NOT NULL
                   AND pdf_path != ''",
                $cutoff
            ),
            ARRAY_A
        ) ?: [];

        if (empty($rows)) {
            return [
                'candidates' => 0,
                'files_deleted' => 0,
                'documents_cleared' => 0,
            ];
        }

        $filesDeleted = 0;
        $documentIds = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $path = (string) ($row['pdf_path'] ?? '');
            if ($id <= 0) {
                continue;
            }
            $documentIds[] = $id;
            if ($path !== '' && file_exists($path) && is_file($path)) {
                if (@unlink($path)) {
                    $filesDeleted++;
                }
            }
        }

        if (empty($documentIds)) {
            return [
                'candidates' => count($rows),
                'files_deleted' => $filesDeleted,
                'documents_cleared' => 0,
            ];
        }

        $ids = implode(',', array_map('intval', $documentIds));
        $updated = $wpdb->query(
            "UPDATE {$documentsTable}
             SET pdf_path = NULL, pdf_url = NULL, checksum = NULL
             WHERE id IN ({$ids})"
        );

        return [
            'candidates' => count($rows),
            'files_deleted' => $filesDeleted,
            'documents_cleared' => $updated === false ? 0 : (int) $updated,
        ];
    }

    private function cleanupOldDraftRevisions(int $maxDraftRevisions): int
    {
        global $wpdb;

        $revisionsTable = $wpdb->prefix . 'cds_template_revisions';
        $templateIds = $wpdb->get_col("SELECT DISTINCT template_id FROM {$revisionsTable}") ?: [];
        if (empty($templateIds)) {
            return 0;
        }

        $toDelete = [];
        foreach ($templateIds as $templateId) {
            $templateId = (int) $templateId;
            if ($templateId <= 0) {
                continue;
            }

            $draftIds = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id
                     FROM {$revisionsTable}
                     WHERE template_id = %d AND is_published = 0
                     ORDER BY revision_no DESC",
                    $templateId
                )
            ) ?: [];

            $draftIds = array_map('intval', $draftIds);
            if (count($draftIds) <= $maxDraftRevisions) {
                continue;
            }

            $toDelete = array_merge($toDelete, array_slice($draftIds, $maxDraftRevisions));
        }

        if (empty($toDelete)) {
            return 0;
        }

        $ids = implode(',', array_map('intval', $toDelete));
        $deleted = $wpdb->query("DELETE FROM {$revisionsTable} WHERE id IN ({$ids})");

        return $deleted === false ? 0 : (int) $deleted;
    }
}
