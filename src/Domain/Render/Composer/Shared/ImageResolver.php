<?php

namespace CargoDocsStudio\Domain\Render\Composer\Shared;

class ImageResolver
{
    public function resolveImageSource(string $source): string
    {
        $source = trim($source);
        if ($source === '') {
            return '';
        }

        if (str_starts_with($source, 'data:image/')) {
            return $source;
        }

        if (file_exists($source)) {
            return $source;
        }

        if (preg_match('#^https?://#i', $source)) {
            $upload = wp_upload_dir();
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

            // Avoid broken-image placeholders in PDF when remote URL is not resolvable by engine.
            return '';
        }

        return '';
    }

    public function resolveQrImageSource(string $dataUri, string $payload, int $sizePx = 200): string
    {
        $dataUri = trim($dataUri);
        if ($dataUri !== '') {
            return $dataUri;
        }

        $payload = trim($payload);
        if ($payload === '') {
            return '';
        }

        $sizePx = max(80, min(600, $sizePx));

        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $sizePx . 'x' . $sizePx . '&data=' . rawurlencode($payload);
    }
}
