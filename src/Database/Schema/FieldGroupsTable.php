<?php

namespace CargoDocsStudio\Database\Schema;

class FieldGroupsTable extends AbstractTable
{
    public function create(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cds_field_groups';
        $collate = $wpdb->get_charset_collate();

        $this->runSql(
            "CREATE TABLE $table (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                group_key varchar(128) NOT NULL,
                doc_type_key varchar(64) NOT NULL,
                label varchar(191) NOT NULL,
                sort_order int NOT NULL DEFAULT 0,
                ui_json longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY doc_type_key (doc_type_key),
                UNIQUE KEY group_doc (group_key,doc_type_key)
            ) $collate;"
        );
    }
}
