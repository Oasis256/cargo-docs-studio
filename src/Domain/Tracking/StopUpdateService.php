<?php

namespace CargoDocsStudio\Domain\Tracking;

use CargoDocsStudio\Database\Repository\ShipmentRepository;
use CargoDocsStudio\Database\Repository\StopRepository;

class StopUpdateService
{
    private ShipmentRepository $shipments;
    private StopRepository $stops;

    public function __construct()
    {
        $this->shipments = new ShipmentRepository();
        $this->stops = new StopRepository();
    }

    public function addStopByTrackingCode(string $trackingCode, string $stopName, string $status, string $notes = '', ?float $lat = null, ?float $lng = null): array|\WP_Error
    {
        $shipment = $this->shipments->getByTrackingCode($trackingCode);
        if (!$shipment) {
            return new \WP_Error('cds_tracking_not_found', 'Tracking code not found');
        }

        $created = $this->stops->create((int) $shipment['id'], $stopName, $status, $notes, $lat, $lng);
        if ($created instanceof \WP_Error) {
            return $created;
        }

        $this->shipments->updateLatest((int) $shipment['id'], $status, $stopName, $lat, $lng);

        $updatedShipment = $this->shipments->getByTrackingCode($trackingCode);

        return [
            'shipment' => $updatedShipment,
            'stop_id' => $created,
        ];
    }
}
