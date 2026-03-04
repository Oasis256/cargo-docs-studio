<?php

namespace CargoDocsStudio\Domain\Tracking;

use CargoDocsStudio\Database\Repository\ShipmentRepository;
use CargoDocsStudio\Database\Repository\StopRepository;

class PublicTrackingQuery
{
    private ShipmentRepository $shipments;
    private StopRepository $stops;
    private TrackingTokenService $tokens;

    public function __construct()
    {
        $this->shipments = new ShipmentRepository();
        $this->stops = new StopRepository();
        $this->tokens = new TrackingTokenService();
    }

    public function getPublicTracking(string $trackingCode, string $token): array|\WP_Error
    {
        $shipment = $this->shipments->getByTrackingCode($trackingCode);
        if (!$shipment) {
            return new \WP_Error('cds_tracking_not_found', 'Tracking record not found');
        }

        if (empty($shipment['token_hash']) || !$this->tokens->verifyToken($token, (string) $shipment['token_hash'])) {
            return new \WP_Error('cds_tracking_unauthorized', 'Invalid tracking token');
        }

        $stops = $this->stops->listByShipment((int) $shipment['id']);

        return [
            'tracking_code' => $shipment['tracking_code'],
            'status' => $shipment['current_status'],
            'current_location' => $shipment['current_location_text'],
            'lat' => $shipment['current_lat'],
            'lng' => $shipment['current_lng'],
            'last_update_at' => $shipment['last_update_at'],
            'stops' => $stops,
        ];
    }
}
