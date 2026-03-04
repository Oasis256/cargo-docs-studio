<?php

namespace CargoDocsStudio\Database\Schema;

class DocumentsTable extends AbstractTable
{
    public function create(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cds_documents';
        $collate = $wpdb->get_charset_collate();

        $this->runSql(
            "CREATE TABLE $table (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                doc_type_key varchar(64) NOT NULL,
                template_revision_id bigint unsigned NOT NULL,
                status varchar(32) NOT NULL DEFAULT 'generated',
                payload_json longtext NULL,
                computed_json longtext NULL,
                pdf_path varchar(500) NULL,
                pdf_url varchar(500) NULL,
                checksum char(64) NULL,
                created_by bigint unsigned NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY doc_type_key (doc_type_key),
                KEY template_revision_id (template_revision_id),
                KEY checksum (checksum)
            ) $collate;"
        );
    }
}
