<?php

namespace CargoDocsStudio\Database\Schema;

class SettingsTable extends AbstractTable
{
    public function create(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cds_settings';
        $collate = $wpdb->get_charset_collate();

        $this->runSql(
            "CREATE TABLE $table (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                `key` varchar(128) NOT NULL,
                value_json longtext NULL,
                autoload tinyint(1) NOT NULL DEFAULT 0,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY key_u (`key`)
            ) $collate;"
        );
    }
}
