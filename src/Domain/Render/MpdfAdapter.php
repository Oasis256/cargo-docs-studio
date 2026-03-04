<?php

namespace CargoDocsStudio\Domain\Render;

class MpdfAdapter implements PdfAdapterInterface
{
    public function render(string $html, string $filename, array $options = []): array
    {
        if (!$this->loadMpdf()) {
            return [
                'success' => false,
                'error' => 'mPDF is not available in this plugin.',
            ];
        }

        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'cargo-docs-studio/invoices/';
        $urlBase = trailingslashit($upload['baseurl']) . 'cargo-docs-studio/invoices/';

        if (!wp_mkdir_p($dir)) {
            return [
                'success' => false,
                'error' => 'Could not create invoice output directory.',
            ];
        }

        $safeFilename = sanitize_file_name($filename);
        if (!str_ends_with(strtolower($safeFilename), '.pdf')) {
            $safeFilename .= '.pdf';
        }

        $path = $dir . $safeFilename;
        $url = $urlBase . $safeFilename;
        $format = $this->normalizePageFormat((string) ($options['page_format'] ?? 'A4'));
        $watermarkUrl = esc_url_raw((string) ($options['watermark_url'] ?? ''));
        $watermarkSource = $this->prepareWatermarkSource($watermarkUrl, $upload);

        try {
            $mpdf = new \Mpdf\Mpdf([
                'tempDir' => trailingslashit($upload['basedir']) . 'cargo-docs-studio/tmp',
                'mode' => 'utf-8',
                'format' => $format,
                'default_font' => 'dejavusans',
                'margin_left' => 12,
                'margin_right' => 12,
                'margin_top' => 12,
                'margin_bottom' => 12,
            ]);
            $mpdf->showImageErrors = false;
            $this->applyCenteredWatermark($mpdf, $watermarkSource);
            $mpdf->WriteHTML($html);
            $mpdf->Output($path, \Mpdf\Output\Destination::FILE);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'mPDF generation failed: ' . $e->getMessage(),
            ];
        }

        if (!file_exists($path)) {
            return [
                'success' => false,
                'error' => 'PDF file was not created.',
            ];
        }

        return [
            'success' => true,
            'file_path' => $path,
            'file_url' => $url,
        ];
    }

    private function loadMpdf(): bool
    {
        if (class_exists('\Mpdf\Mpdf')) {
            return true;
        }

        foreach ($this->autoloadPaths() as $autoload) {
            if (file_exists($autoload)) {
                require_once $autoload;
            }
            if (class_exists('\Mpdf\Mpdf')) {
                return true;
            }
        }

        return false;
    }

    private function normalizePageFormat(string $value): string
    {
        $value = strtoupper(trim($value));
        return in_array($value, ['A4', 'LETTER'], true) ? $value : 'A4';
    }

    private function autoloadPaths(): array
    {
        return array_values(array_unique([
            CDS_PLUGIN_DIR . 'vendor/autoload.php',
            trailingslashit(WP_PLUGIN_DIR) . 'cargo-tracking-pdf-generator/vendor/autoload.php',
        ]));
    }

    private function applyCenteredWatermark(\Mpdf\Mpdf $mpdf, string $watermarkUrl): void
    {
        if ($watermarkUrl === '') {
            return;
        }

        try {
            // Do not force width/height box; let mPDF keep native aspect ratio.
            $mpdf->SetWatermarkImage($watermarkUrl, 0.5);
            $mpdf->showWatermarkImage = true;
            if (property_exists($mpdf, 'watermarkImgBehind')) {
                $mpdf->watermarkImgBehind = true;
            }
        } catch (\Throwable $e) {
            // Non-fatal: document should still render when watermark fails.
        }
    }

    private function prepareWatermarkSource(string $source, array $upload): string
    {
        if ($source === '') {
            return '';
        }

        $baseUrl = trailingslashit((string) ($upload['baseurl'] ?? ''));
        $baseDir = trailingslashit((string) ($upload['basedir'] ?? ''));
        if ($baseUrl !== '' && $baseDir !== '' && str_starts_with($source, $baseUrl)) {
            $relative = ltrim(substr($source, strlen($baseUrl)), '/');
            $relative = str_replace('\\', '/', $relative);
            $relative = implode('/', array_map('rawurldecode', explode('/', $relative)));
            $localPath = $baseDir . $relative;
            if (file_exists($localPath)) {
                return $localPath;
            }
        }

        $tmpDir = trailingslashit($upload['basedir']) . 'cargo-docs-studio/tmp/';
        if (!wp_mkdir_p($tmpDir)) {
            return $source;
        }

        if (str_starts_with($source, 'data:image/')) {
            $parts = explode(',', $source, 2);
            if (count($parts) !== 2) {
                return $source;
            }
            $meta = $parts[0];
            $data = base64_decode($parts[1], true);
            if (!is_string($data) || $data === '') {
                return $source;
            }
            $ext = 'png';
            if (preg_match('#^data:image/([a-zA-Z0-9]+);base64$#', $meta, $m)) {
                $ext = strtolower($m[1]);
            }
            $file = $tmpDir . 'wm-' . md5($source) . '.' . $ext;
            if (@file_put_contents($file, $data) !== false) {
                return $file;
            }
            return $source;
        }

        if (preg_match('#^https?://#i', $source)) {
            $resp = wp_safe_remote_get($source, ['timeout' => 15, 'redirection' => 3]);
            if (!is_wp_error($resp) && (int) wp_remote_retrieve_response_code($resp) === 200) {
                $body = (string) wp_remote_retrieve_body($resp);
                if ($body !== '') {
                    $ext = 'png';
                    $contentType = (string) wp_remote_retrieve_header($resp, 'content-type');
                    if (str_contains($contentType, 'jpeg')) {
                        $ext = 'jpg';
                    } elseif (str_contains($contentType, 'webp')) {
                        $ext = 'webp';
                    } elseif (str_contains($contentType, 'gif')) {
                        $ext = 'gif';
                    } elseif (str_contains($contentType, 'png')) {
                        $ext = 'png';
                    }
                    $file = $tmpDir . 'wm-' . md5($source) . '.' . $ext;
                    if (@file_put_contents($file, $body) !== false) {
                        return $file;
                    }
                }
            }
        }

        if (file_exists($source)) {
            return $source;
        }

        return $source;
    }
}
