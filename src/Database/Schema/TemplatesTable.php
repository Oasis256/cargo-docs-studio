<?php

namespace CargoDocsStudio\Database\Schema;

class TemplatesTable extends AbstractTable
{
    public function create(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cds_templates';
        $collate = $wpdb->get_charset_collate();

        $this->runSql(
            "CREATE TABLE $table (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                doc_type_key varchar(64) NOT NULL,
                name varchar(191) NOT NULL,
                status varchar(32) NOT NULL DEFAULT 'draft',
                is_default tinyint(1) NOT NULL DEFAULT 0,
                created_by bigint unsigned NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY doc_type_key (doc_type_key),
                KEY is_default (is_default)
            ) $collate;"
        );
    }
}
