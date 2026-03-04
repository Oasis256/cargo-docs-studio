<?php

namespace CargoDocsStudio\Database\Schema;

class AuditEventsTable extends AbstractTable
{
    public function create(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cds_audit_events';
        $collate = $wpdb->get_charset_collate();

        $this->runSql(
            "CREATE TABLE $table (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                event_type varchar(128) NOT NULL,
                actor_id bigint unsigned NULL,
                ref_type varchar(64) NULL,
                ref_id bigint unsigned NULL,
                meta_json longtext NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY event_type (event_type),
                KEY created_at (created_at)
            ) $collate;"
        );
    }
}
