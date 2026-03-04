<?php

namespace CargoDocsStudio\Domain\Tracking;

use CargoDocsStudio\Database\Repository\DocumentRepository;
use CargoDocsStudio\Database\Repository\ShipmentRepository;
use CargoDocsStudio\Database\Repository\StopRepository;

class ShipmentService
{
    private DocumentRepository $documents;
    private ShipmentRepository $shipments;
    private StopRepository $stops;
    private TrackingCodeService $trackingCodes;
    private TrackingTokenService $tokens;

    public function __construct()
    {
        $this->documents = new DocumentRepository();
        $this->shipments = new ShipmentRepository();
        $this->stops = new StopRepository();
        $this->trackingCodes = new TrackingCodeService();
        $this->tokens = new TrackingTokenService();
    }

    public function createInvoiceTracking(array $payload, int $templateRevisionId = 1): array|\WP_Error
    {
        return $this->createDocumentTracking('invoice', $payload, $templateRevisionId);
    }

    public function createDocumentTracking(string $docTypeKey, array $payload, int $templateRevisionId = 1): array|\WP_Error
    {
        $docTypeKey = sanitize_key($docTypeKey);
        if ($docTypeKey === '') {
            $docTypeKey = 'invoice';
        }

        $trackingCode = $this->trackingCodes->generateUniqueCode();
        $token = $this->tokens->generateToken();
        $tokenHash = $this->tokens->hashToken($token);

        $documentId = $this->documents->create($docTypeKey, $templateRevisionId, $payload, []);
        if ($documentId instanceof \WP_Error) {
            return $documentId;
        }

        $status = sanitize_text_field($payload['status'] ?? 'Processing');
        $location = sanitize_text_field($payload['current_location'] ?? 'Origin');
        $lat = isset($payload['lat']) && $payload['lat'] !== '' ? (float) $payload['lat'] : null;
        $lng = isset($payload['lng']) && $payload['lng'] !== '' ? (float) $payload['lng'] : null;

        $shipmentId = $this->shipments->create($trackingCode, $tokenHash, $documentId, $status, $location, $lat, $lng);
        if ($shipmentId instanceof \WP_Error) {
            return $shipmentId;
        }

        $stop = $this->stops->create(
            $shipmentId,
            $location,
            $status,
            'Initial shipment registration',
            $lat,
            $lng
        );

        if ($stop instanceof \WP_Error) {
            return $stop;
        }

        return [
            'document_id' => $documentId,
            'shipment_id' => $shipmentId,
            'tracking_code' => $trackingCode,
            'tracking_token' => $token,
            'tracking_url' => $this->tokens->buildTrackingUrl($trackingCode, $token),
        ];
    }
}
