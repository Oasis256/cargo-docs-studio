<?php

namespace CargoDocsStudio\Domain\Tracking;

class TrackingTokenService
{
    public function generateToken(): string
    {
        return wp_generate_password(40, false, false);
    }

    public function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, wp_salt('auth'));
    }

    public function verifyToken(string $token, string $hash): bool
    {
        return hash_equals($hash, $this->hashToken($token));
    }

    public function buildTrackingUrl(string $trackingCode, string $token): string
    {
        return add_query_arg(
            [
                'cds_track' => rawurlencode($trackingCode),
                't' => rawurlencode($token),
            ],
            home_url('/')
        );
    }
}
