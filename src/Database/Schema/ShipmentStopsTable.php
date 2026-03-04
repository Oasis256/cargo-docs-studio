<?php

namespace CargoDocsStudio\Database\Schema;

class ShipmentStopsTable extends AbstractTable
{
    public function create(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cds_shipment_stops';
        $collate = $wpdb->get_charset_collate();

        $this->runSql(
            "CREATE TABLE $table (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                shipment_id bigint unsigned NOT NULL,
                stop_name varchar(255) NOT NULL,
                status varchar(64) NOT NULL,
                notes text NULL,
                lat decimal(10,8) NULL,
                lng decimal(11,8) NULL,
                updated_by bigint unsigned NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY shipment_id (shipment_id),
                KEY status (status),
                KEY created_at (created_at)
            ) $collate;"
        );
    }
}
