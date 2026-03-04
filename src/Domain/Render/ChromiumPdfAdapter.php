<?php

namespace CargoDocsStudio\Domain\Render;

class ChromiumPdfAdapter implements PdfAdapterInterface
{
    public function render(string $html, string $filename, array $options = []): array
    {
        $binary = $this->findChromiumBinary();
        if ($binary === null) {
            return [
                'success' => false,
                'error' => 'Chromium/Chrome binary not found. Configure CDS_CHROMIUM_BIN.',
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

        $pdfPath = $dir . $safeFilename;
        $pdfUrl = $urlBase . $safeFilename;

        $tmpHtml = wp_tempnam('cds_invoice_html');
        if ($tmpHtml === false) {
            return [
                'success' => false,
                'error' => 'Unable to create temporary HTML file.',
            ];
        }

        file_put_contents($tmpHtml, $html);

        $tmpPdf = wp_tempnam('cds_invoice_pdf');
        if ($tmpPdf === false) {
            @unlink($tmpHtml);
            return [
                'success' => false,
                'error' => 'Unable to create temporary PDF file.',
            ];
        }

        @unlink($tmpPdf);
        $tmpPdf .= '.pdf';
        $paper = $this->normalizePaperSize((string) ($options['page_format'] ?? 'A4'));

        $args = [
            escapeshellarg($binary),
            '--headless=new',
            '--disable-gpu',
            '--no-sandbox',
            '--print-to-pdf-no-header',
            '--print-to-pdf-page-size=' . escapeshellarg($paper),
            '--print-to-pdf=' . escapeshellarg($tmpPdf),
            escapeshellarg('file:///' . str_replace('\\', '/', $tmpHtml)),
        ];

        $command = implode(' ', $args) . ' 2>&1';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        @unlink($tmpHtml);

        if ($exitCode !== 0 || !file_exists($tmpPdf)) {
            @unlink($tmpPdf);
            return [
                'success' => false,
                'error' => 'Chromium PDF generation failed: ' . implode("\n", $output),
            ];
        }

        if (!@rename($tmpPdf, $pdfPath)) {
            if (!@copy($tmpPdf, $pdfPath)) {
                @unlink($tmpPdf);
                return [
                    'success' => false,
                    'error' => 'Could not move generated PDF to uploads directory.',
                ];
            }
            @unlink($tmpPdf);
        }

        return [
            'success' => true,
            'file_path' => $pdfPath,
            'file_url' => $pdfUrl,
        ];
    }

    private function findChromiumBinary(): ?string
    {
        $candidates = [
            getenv('CDS_CHROMIUM_BIN') ?: null,
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Chromium\\Application\\chrome.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
        ];

        foreach ($candidates as $candidate) {
            if (!empty($candidate) && file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizePaperSize(string $value): string
    {
        $value = strtoupper(trim($value));
        return $value === 'LETTER' ? 'Letter' : 'A4';
    }
}
