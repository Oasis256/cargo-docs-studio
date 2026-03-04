<?php

namespace CargoDocsStudio\Domain\Tracking;

use CargoDocsStudio\Database\Repository\ShipmentRepository;

class TrackingCodeService
{
    private ShipmentRepository $shipments;

    public function __construct()
    {
        $this->shipments = new ShipmentRepository();
    }

    public function generateUniqueCode(): string
    {
        do {
            $candidate = 'CDS-' . gmdate('Ymd') . '-' . strtoupper(wp_generate_password(8, false, false));
            $exists = $this->shipments->getByTrackingCode($candidate);
        } while (!empty($exists));

        return $candidate;
    }
}
