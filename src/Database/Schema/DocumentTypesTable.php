<?php
namespace CargoDocsStudio\Database\Schema;
class DocumentTypesTable extends AbstractTable { public function create(): void { global $wpdb; $table=$wpdb->prefix.'cds_document_types'; $collate=$wpdb->get_charset_collate(); $this->runSql("CREATE TABLE $table (id bigint unsigned NOT NULL AUTO_INCREMENT, `key` varchar(64) NOT NULL, label varchar(191) NOT NULL, status varchar(32) NOT NULL DEFAULT 'active', created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY key_u (`key`)) $collate;"); } }
