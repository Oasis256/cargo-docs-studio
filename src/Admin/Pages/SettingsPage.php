<?php

namespace CargoDocsStudio\Admin\Pages;

use CargoDocsStudio\Database\Repository\SettingsRepository;
use CargoDocsStudio\Domain\Maintenance\RetentionCleanupService;

class SettingsPage
{
    public function render(): void
    {
        if (!current_user_can('cds_manage_settings') && !current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'cargo-docs-studio'));
        }

        $repo = new SettingsRepository();
        $cleanup = new RetentionCleanupService();
        $notice = null;
        $noticeType = 'updated';

        if (isset($_POST['cds_settings_submit'])) {
            check_admin_referer('cds_save_settings');

            $pdfEngine = isset($_POST['pdf_engine']) ? sanitize_key((string) $_POST['pdf_engine']) : 'tcpdf';
            if (!in_array($pdfEngine, ['tcpdf', 'mpdf'], true)) {
                $pdfEngine = 'tcpdf';
            }
            if ($pdfEngine === 'mpdf' && !$this->isMpdfAvailable()) {
                $pdfEngine = 'tcpdf';
                $notice = esc_html__('mPDF is not available on this server. Engine has been set to TCPDF.', 'cargo-docs-studio');
                $noticeType = 'warning';
            }
            if ($pdfEngine === 'tcpdf' && !$this->isTcpdfAvailable()) {
                $notice = esc_html__('TCPDF is not available on this server.', 'cargo-docs-studio');
                $noticeType = 'error';
            }

            $walletEnabled = !empty($_POST['bitcoin_payment_enabled']);
            $walletAddress = sanitize_text_field((string) ($_POST['bitcoin_wallet_address'] ?? ''));
            $walletLabel = sanitize_text_field((string) ($_POST['bitcoin_wallet_label'] ?? 'Cargo Payment'));
            $walletAmountMode = sanitize_key((string) ($_POST['bitcoin_amount_mode'] ?? 'none'));
            if (!in_array($walletAmountMode, ['none', 'fixed'], true)) {
                $walletAmountMode = 'none';
            }
            $walletFixedAmount = sanitize_text_field((string) ($_POST['bitcoin_fixed_amount_btc'] ?? ''));
            if ($walletAmountMode === 'fixed' && $walletFixedAmount !== '' && !preg_match('/^\d+(\.\d+)?$/', $walletFixedAmount)) {
                $walletFixedAmount = '';
            }
            $skrWatermarkUrl = esc_url_raw((string) ($_POST['skr_watermark_url'] ?? ''));

            $pdfRetentionDays = isset($_POST['retention_pdf_days']) ? (int) $_POST['retention_pdf_days'] : 90;
            $maxDraftRevisions = isset($_POST['retention_max_draft_revisions']) ? (int) $_POST['retention_max_draft_revisions'] : 20;
            $pdfRetentionDays = max(1, min($pdfRetentionDays, 3650));
            $maxDraftRevisions = max(1, min($maxDraftRevisions, 200));

            $okEngine = $repo->set('pdf_engine', $pdfEngine);
            $okWallet = $repo->set('bitcoin_payment', [
                'enabled' => $walletEnabled,
                'address' => $walletAddress,
                'label' => $walletLabel,
                'amount_mode' => $walletAmountMode,
                'fixed_amount_btc' => $walletFixedAmount,
            ]);
            $okSkrWatermark = $repo->set('skr_watermark', [
                'url' => $skrWatermarkUrl,
            ]);
            $okRetention = $cleanup->savePolicy($pdfRetentionDays, $maxDraftRevisions);

            if ($okEngine && $okWallet && $okSkrWatermark && $okRetention) {
                $notice = esc_html__('Settings saved.', 'cargo-docs-studio');
                $noticeType = 'updated';
            } else {
                $notice = esc_html__('Failed to save one or more settings.', 'cargo-docs-studio');
                $noticeType = 'error';
            }
        }

        if (isset($_POST['cds_cleanup_now'])) {
            check_admin_referer('cds_save_settings');
            $report = $cleanup->run();
            $notice = sprintf(
                /* translators: 1: files deleted, 2: documents cleared, 3: revisions deleted */
                esc_html__('Cleanup complete. Files deleted: %1$d, documents cleared: %2$d, revisions deleted: %3$d.', 'cargo-docs-studio'),
                (int) ($report['pdf_files_deleted'] ?? 0),
                (int) ($report['documents_cleared'] ?? 0),
                (int) ($report['revisions_deleted'] ?? 0)
            );
            $noticeType = 'updated';
        }

        $pdfEngine = (string) $repo->get('pdf_engine', 'tcpdf');
        if (!in_array($pdfEngine, ['tcpdf', 'mpdf'], true)) {
            $pdfEngine = 'tcpdf';
        }
        $mpdfAvailable = $this->isMpdfAvailable();
        $tcpdfAvailable = $this->isTcpdfAvailable();
        $wallet = $repo->get('bitcoin_payment', [
            'enabled' => true,
            'address' => '',
            'label' => 'Cargo Payment',
            'amount_mode' => 'none',
            'fixed_amount_btc' => '',
        ]);

        $walletEnabled = !empty($wallet['enabled']);
        $walletAddress = (string) ($wallet['address'] ?? '');
        $walletLabel = (string) ($wallet['label'] ?? 'Cargo Payment');
        $walletAmountMode = (string) ($wallet['amount_mode'] ?? 'none');
        $walletFixedAmount = (string) ($wallet['fixed_amount_btc'] ?? '');
        $skrWatermark = $repo->get('skr_watermark', [
            'url' => '',
        ]);
        $skrWatermarkUrl = esc_url_raw((string) ($skrWatermark['url'] ?? ''));
        $policy = $cleanup->getPolicy();
        $pdfRetentionDays = (int) ($policy['pdf_days'] ?? 90);
        $maxDraftRevisions = (int) ($policy['max_draft_revisions'] ?? 20);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('CargoDocs Studio Settings', 'cargo-docs-studio') . '</h1>';

        if ($notice !== null) {
            echo '<div class="' . esc_attr($noticeType) . ' notice is-dismissible"><p>' . esc_html($notice) . '</p></div>';
        }

        echo '<form method="post" action="">';
        wp_nonce_field('cds_save_settings');

        echo '<h2>' . esc_html__('PDF Engine', 'cargo-docs-studio') . '</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row"><label for="pdf_engine">' . esc_html__('Renderer', 'cargo-docs-studio') . '</label></th><td>';
        echo '<select id="pdf_engine" name="pdf_engine">';
        echo '<option value="tcpdf" ' . selected($pdfEngine, 'tcpdf', false) . ' ' . disabled(!$tcpdfAvailable, true, false) . '>';
        echo esc_html__('TCPDF (shared-host friendly)', 'cargo-docs-studio');
        if (!$tcpdfAvailable) {
            echo ' - ' . esc_html__('missing', 'cargo-docs-studio');
        }
        echo '</option>';
        echo '<option value="mpdf" ' . selected($pdfEngine, 'mpdf', false) . ' ' . disabled(!$mpdfAvailable, true, false) . '>';
        echo esc_html__('mPDF (richer HTML/CSS)', 'cargo-docs-studio');
        if (!$mpdfAvailable) {
            echo ' - ' . esc_html__('missing', 'cargo-docs-studio');
        }
        echo '</option>';
        echo '</select>';
        echo '<p class="description">' . esc_html__('Choose PDF engine used for generated documents.', 'cargo-docs-studio') . '</p>';
        echo '</td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Engine Health', 'cargo-docs-studio') . '</th><td>';
        echo '<p><strong>TCPDF:</strong> ' . ($tcpdfAvailable ? esc_html__('Available', 'cargo-docs-studio') : esc_html__('Missing', 'cargo-docs-studio')) . '</p>';
        echo '<p><strong>mPDF:</strong> ' . ($mpdfAvailable ? esc_html__('Available', 'cargo-docs-studio') : esc_html__('Missing', 'cargo-docs-studio')) . '</p>';
        echo '</td></tr>';
        echo '</table>';

        if ($pdfEngine === 'mpdf' && !$mpdfAvailable) {
            echo '<div class="notice notice-warning inline"><p>' .
                esc_html__('mPDF is selected but not available. Generation will fall back to TCPDF.', 'cargo-docs-studio') .
                '</p></div>';
        }

        echo '<h2>' . esc_html__('Bitcoin Wallet QR Defaults', 'cargo-docs-studio') . '</h2>';
        echo '<table class="form-table" role="presentation">';

        echo '<tr><th scope="row">' . esc_html__('Enable Bitcoin QR', 'cargo-docs-studio') . '</th><td>';
        echo '<label><input type="checkbox" name="bitcoin_payment_enabled" value="1" ' . checked($walletEnabled, true, false) . '> ' . esc_html__('Enable payment QR block by default', 'cargo-docs-studio') . '</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="bitcoin_wallet_address">' . esc_html__('Wallet Address', 'cargo-docs-studio') . '</label></th><td>';
        echo '<input type="text" class="regular-text code" id="bitcoin_wallet_address" name="bitcoin_wallet_address" value="' . esc_attr($walletAddress) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="bitcoin_wallet_label">' . esc_html__('Label', 'cargo-docs-studio') . '</label></th><td>';
        echo '<input type="text" class="regular-text" id="bitcoin_wallet_label" name="bitcoin_wallet_label" value="' . esc_attr($walletLabel) . '" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="bitcoin_amount_mode">' . esc_html__('Amount Mode', 'cargo-docs-studio') . '</label></th><td>';
        echo '<select id="bitcoin_amount_mode" name="bitcoin_amount_mode">';
        echo '<option value="none" ' . selected($walletAmountMode, 'none', false) . '>' . esc_html__('No amount in URI', 'cargo-docs-studio') . '</option>';
        echo '<option value="fixed" ' . selected($walletAmountMode, 'fixed', false) . '>' . esc_html__('Fixed BTC amount', 'cargo-docs-studio') . '</option>';
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="bitcoin_fixed_amount_btc">' . esc_html__('Fixed BTC Amount', 'cargo-docs-studio') . '</label></th><td>';
        echo '<input type="text" class="small-text" id="bitcoin_fixed_amount_btc" name="bitcoin_fixed_amount_btc" value="' . esc_attr($walletFixedAmount) . '" />';
        echo '<p class="description">' . esc_html__('Example: 0.0005', 'cargo-docs-studio') . '</p>';
        echo '</td></tr>';

        echo '</table>';

        echo '<h2>' . esc_html__('Document Watermark', 'cargo-docs-studio') . '</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row"><label for="skr_watermark_url">' . esc_html__('Watermark Image URL', 'cargo-docs-studio') . '</label></th><td>';
        echo '<input type="url" class="regular-text code" id="skr_watermark_url" name="skr_watermark_url" value="' . esc_attr($skrWatermarkUrl) . '" />';
        echo '<p class="description">' . esc_html__('Global watermark image used by any document when its watermark toggle is enabled.', 'cargo-docs-studio') . '</p>';
        echo '</td></tr>';
        echo '</table>';

        echo '<h2>' . esc_html__('Retention & Cleanup', 'cargo-docs-studio') . '</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row"><label for="retention_pdf_days">' . esc_html__('PDF Retention (days)', 'cargo-docs-studio') . '</label></th><td>';
        echo '<input type="number" min="1" max="3650" id="retention_pdf_days" name="retention_pdf_days" value="' . esc_attr((string) $pdfRetentionDays) . '" class="small-text" />';
        echo '<p class="description">' . esc_html__('Generated PDF files older than this are deleted and document file paths are cleared.', 'cargo-docs-studio') . '</p>';
        echo '</td></tr>';
        echo '<tr><th scope="row"><label for="retention_max_draft_revisions">' . esc_html__('Max Draft Revisions per Template', 'cargo-docs-studio') . '</label></th><td>';
        echo '<input type="number" min="1" max="200" id="retention_max_draft_revisions" name="retention_max_draft_revisions" value="' . esc_attr((string) $maxDraftRevisions) . '" class="small-text" />';
        echo '<p class="description">' . esc_html__('Only draft revisions above this count are pruned. Published revisions are kept.', 'cargo-docs-studio') . '</p>';
        echo '</td></tr>';
        echo '</table>';

        submit_button(__('Save Settings', 'cargo-docs-studio'), 'primary', 'cds_settings_submit');
        submit_button(__('Run Cleanup Now', 'cargo-docs-studio'), 'secondary', 'cds_cleanup_now', false);
        echo '</form>';
        echo '</div>';
    }

    private function isMpdfAvailable(): bool
    {
        if (class_exists('\Mpdf\Mpdf')) {
            return true;
        }

        foreach ($this->mpdfSourcePaths() as $path) {
            if (file_exists($path)) {
                return true;
            }
        }

        return false;
    }

    private function isTcpdfAvailable(): bool
    {
        if (class_exists('TCPDF')) {
            return true;
        }

        return file_exists(CDS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php')
            || file_exists(CDS_PLUGIN_DIR . 'lib/tcpdf/tcpdf.php');
    }

    private function mpdfSourcePaths(): array
    {
        return array_values(array_unique([
            CDS_PLUGIN_DIR . 'vendor/mpdf/mpdf/src/Mpdf.php',
            trailingslashit(WP_PLUGIN_DIR) . 'cargo-tracking-pdf-generator/vendor/mpdf/mpdf/src/Mpdf.php',
        ]));
    }
}
