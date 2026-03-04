<?php

namespace CargoDocsStudio\Database\Schema;

abstract class AbstractTable
{
    abstract public function create(): void;

    protected function runSql(string $sql): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
