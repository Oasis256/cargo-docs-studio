# CargoDocs Studio QA Checklist

Use this checklist before each release.

## 1. Setup

- [ ] Plugin activates cleanly.
- [ ] CargoDocs capabilities exist for admin and non-admin test users.
- [ ] Template Studio, Documents, Tracking, Settings, and Audit pages are accessible by expected roles.

## 2. Template Studio

- [ ] Create one template each for `invoice`, `receipt`, and `skr`.
- [ ] Save draft revision and publish revision successfully.
- [ ] Duplicate a revision and verify a new revision appears.
- [ ] Rollback by publishing an older revision.
- [ ] Compare two revisions and verify diff rows are shown.
- [ ] Invalid JSON in schema/theme/layout/sample payload focuses correct editor field.

## 3. Document Generation

### Invoice sample payload

```json
{
  "client_name": "QA Invoice Client",
  "client_email": "invoice.qa@example.com",
  "client_address": "100 Invoice St, QA City",
  "cargo_type": "Electronics",
  "quantity": 2,
  "taxable_value": 950.75,
  "current_location": "Origin Hub",
  "bitcoin_enabled": true
}
```

### Receipt sample payload

```json
{
  "client_name": "QA Receipt Client",
  "client_email": "receipt.qa@example.com",
  "client_address": "200 Receipt Ave, QA City",
  "cargo_type": "Paid Cargo",
  "quantity": 1,
  "taxable_value": 500,
  "current_location": "Billing Desk",
  "bitcoin_enabled": true
}
```

### SKR sample payload

```json
{
  "client_name": "QA SKR Client",
  "client_email": "skr.qa@example.com",
  "client_address": "300 SKR Rd, QA City",
  "cargo_type": "Shipment Item",
  "quantity": 1,
  "taxable_value": 0,
  "current_location": "Dispatch Point",
  "bitcoin_enabled": false
}
```

### Generation checks

- [ ] Generate invoice/receipt/skr documents successfully.
- [ ] Generated PDF opens and QR blocks render.
- [ ] Success panel shows engine diagnostics.
- [ ] Success panel copy buttons work for tracking/payment links.
- [ ] Structured validation errors appear for invalid payloads.

## 4. Tracking

- [ ] Public tracking URL loads latest location and stop list.
- [ ] Stop update API works for authorized staff user.
- [ ] Public tracking rate limit returns `429` and `retry_after` when exceeded.

## 5. Documents List

- [ ] Filter by `all/invoice/receipt/skr` works.
- [ ] Search by tracking code works.
- [ ] Search by client email works.
- [ ] Pagination previous/next works with page metadata.

## 6. Settings and Maintenance

- [ ] PDF engine toggle saves and applies.
- [ ] Retention policy values save.
- [ ] Manual cleanup runs and returns stats notice.
- [ ] Audit page shows recent events.

## 7. Security and Permissions

- [ ] Template manager can access template actions.
- [ ] Document generator cannot publish templates.
- [ ] Viewer can view documents list but cannot generate documents.
- [ ] Audit endpoint/page inaccessible without audit capability.

## 8. Final Signoff

- [ ] README and TODO are updated.
- [ ] No PHP syntax errors in changed files.
- [ ] No JavaScript syntax errors in changed files.
