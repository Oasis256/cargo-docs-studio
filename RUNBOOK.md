# CargoDocs Studio Production Runbook

## 1. Server Requirements

- WordPress with REST API enabled.
- Write access to uploads directory (`wp-content/uploads`).
- WP-Cron enabled for scheduled cleanup.
- At least one PDF engine available:
- TCPDF (default fallback)
- mPDF (optional)

## 2. Post-Deploy Checklist

1. Activate plugin and confirm no fatal errors.
2. Open `CargoDocs Studio > Settings`:
- Verify engine health.
- Configure Bitcoin defaults (if used).
- Configure retention policy.
3. Confirm cron schedule:
- `cds_daily_cleanup_event` exists.
4. Create/publish at least one template per document type.
5. Generate one test document for each type.

## 3. Operational Monitoring

- Review `CargoDocs Studio > Audit` regularly for:
- `template_publish_failed`
- `document_generate_failed`
- `tracking_stop_update_failed`
- Watch uploads space usage under:
- `wp-content/uploads/cargo-docs-studio/invoices/`

## 4. Incident Response

## PDF generation failures

1. Check audit events for `document_generate_failed`.
2. Verify selected engine availability in settings.
3. If mPDF unavailable, switch to TCPDF.
4. Re-run generation and confirm success.

## Tracking endpoint abuse

1. Verify `429` responses on public tracking endpoint.
2. Increase protection at edge/CDN if needed.
3. Rotate tracking tokens for affected shipments if compromise suspected.

## Cleanup not running

1. Confirm WP-Cron is enabled.
2. Trigger cleanup manually from settings (`Run Cleanup Now`).
3. Check timestamp and counts in admin notice.

## 5. Backup & Recovery

- Back up DB and uploads daily.
- Before major template changes, export current template/revision rows.
- Keep at least one published revision per active template.
