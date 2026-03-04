<?php

namespace CargoDocsStudio\Domain\Render;

class TcpdfAdapter implements PdfAdapterInterface
{
    public function render(string $html, string $filename, array $options = []): array
    {
        if (!$this->loadTcpdf()) {
            return [
                'success' => false,
                'error' => 'TCPDF is not available.',
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
            $pdf = new \TCPDF('P', 'mm', $format, true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(12, 12, 12);
            $pdf->SetAutoPageBreak(true, 12);
            $pdf->setImageScale(1.25);
            $pdf->AddPage();
            $this->applyCenteredWatermark($pdf, $watermarkSource);
            // DejaVu improves UTF-8/symbol rendering compared to default core fonts.
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Output($path, 'F');
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'PDF generation failed: ' . $e->getMessage(),
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

    private function loadTcpdf(): bool
    {
        if (class_exists('TCPDF')) {
            return true;
        }

        $paths = [
            CDS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php',
            CDS_PLUGIN_DIR . 'lib/tcpdf/tcpdf.php',
                    ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                if (class_exists('TCPDF')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizePageFormat(string $value): string
    {
        $value = strtoupper(trim($value));
        return in_array($value, ['A4', 'LETTER'], true) ? $value : 'A4';
    }

    private function applyCenteredWatermark(\TCPDF $pdf, string $watermarkUrl): void
    {
        if ($watermarkUrl === '') {
            return;
        }

        try {
            $pageW = (float) $pdf->getPageWidth();
            $pageH = (float) $pdf->getPageHeight();
            $maxW = 120.0;
            $maxH = 120.0;
            $w = $maxW;
            $h = $maxH;

            $size = @getimagesize($watermarkUrl);
            if (is_array($size) && !empty($size[0]) && !empty($size[1])) {
                $imgW = (float) $size[0];
                $imgH = (float) $size[1];
                if ($imgW > 0 && $imgH > 0) {
                    $scale = min($maxW / $imgW, $maxH / $imgH);
                    $w = max(1.0, $imgW * $scale);
                    $h = max(1.0, $imgH * $scale);
                }
            }
            $x = ($pageW - $w) / 2.0;
            $y = ($pageH - $h) / 2.0;

            if (method_exists($pdf, 'SetAlpha')) {
                $pdf->SetAlpha(0.5);
            }
            $pdf->Image($watermarkUrl, $x, $y, $w, $h, '', '', '', false, 300, '', false, false, 0, false, false, false);
            if (method_exists($pdf, 'SetAlpha')) {
                $pdf->SetAlpha(1);
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


