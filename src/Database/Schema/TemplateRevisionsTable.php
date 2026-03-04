<?php

namespace CargoDocsStudio\Database\Schema;

class TemplateRevisionsTable extends AbstractTable
{
    public function create(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cds_template_revisions';
        $collate = $wpdb->get_charset_collate();

        $this->runSql(
            "CREATE TABLE $table (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                template_id bigint unsigned NOT NULL,
                revision_no int NOT NULL,
                schema_json longtext NULL,
                theme_json longtext NULL,
                layout_json longtext NULL,
                is_published tinyint(1) NOT NULL DEFAULT 0,
                created_by bigint unsigned NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY template_id (template_id),
                UNIQUE KEY template_revision (template_id,revision_no)
            ) $collate;"
        );
    }
}
