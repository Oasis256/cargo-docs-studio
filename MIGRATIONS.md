# CargoDocs Studio Migration Notes

Track schema and data changes here for each plugin version.

## Version 0.1.0

Initial schema created via `CargoDocsStudio\Database\Migrator`:

- `cds_documents`
- `cds_shipments`
- `cds_shipment_stops`
- `cds_templates`
- `cds_template_revisions`
- `cds_settings`
- `cds_document_types`
- `cds_fields`
- `cds_field_groups`
- `cds_audit_events`

## Upgrade Guidance

1. Activate/upgrade plugin (runs migrator on activation).
2. Verify table presence in DB.
3. Verify settings exist:
- `pdf_engine`
- `bitcoin_payment`
- `retention_policy` (added during settings save; defaults applied in code if missing)
4. Verify cron event:
- `cds_daily_cleanup_event` should be scheduled.

## Backward Compatibility Rules

- New settings must always have safe defaults in code.
- New nullable columns should default to `NULL` to avoid breaking existing rows.
- Never remove existing tables/columns without explicit migration and backup plan.
