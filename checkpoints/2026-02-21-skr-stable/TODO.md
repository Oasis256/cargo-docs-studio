# CargoDocs Studio TODO / Progress

This file is the implementation progress bar. I will keep percentages and checkboxes updated as work lands.

Overall progress: `[##############################] 100%`

## 1. Core Product Completion - 100%
Progress: `[##############################]`

- [x] Finalize public tracking map UX
- [x] Complete staff stop-update workflow
- [x] Finish configurable template data binding

## 2. Renderer and Output Quality - 100%
Progress: `[##############################]`

- [x] Ensure output parity between `tcpdf` and `mpdf`
- [x] Improve font strategy for modern symbols
- [x] Add engine diagnostics (selected engine, runtime engine used, fallback reason)
- [x] Add renderer smoke checks in CLI (`wp cds smoke`) for TCPDF/mPDF generation sanity

## 3. Security and Reliability Hardening - 100%
Progress: `[##############################]`

- [x] Add rate limiting/throttling for public tracking endpoints
- [x] Add stricter payload/schema limits (size, depth, field count)
- [x] Add retention/cleanup policy for generated PDFs and old revisions
- [x] Expand audit events for template publish, generation failures, and stop updates

## 4. Admin UX Improvements - 100%
Progress: `[##############################]`

- [x] Inline field-to-editor focus for template validation errors (schema/theme/layout JSON)
- [x] Add safer revision operations (duplicate/compare/rollback)
- [x] Duplicate selected revision from Template Studio
- [x] Rollback by publishing selected historical revision
- [x] Compare revisions (human-readable diff view)
- [x] Improve documents list (pagination + search)
- [x] Add richer success panel (QR previews + quick-copy links)
- [x] Inline field focus for document payload validation errors
- [x] Unified admin REST/error handler shared by Template Studio and Documents pages

## 5. Testing and QA - 100%
Progress: `[##############################]`

- [x] Add automated REST tests for document/template validation and permissions
- [x] Add integration tests for renderer selection and fallback (`mpdf` -> `tcpdf`)
- [x] Add regression tests for role capabilities
- [x] Add manual QA checklist with sample payloads for `invoice`, `receipt`, `skr`
- [x] Add automated smoke test command (`wp cds smoke`) for tables/routes/settings sanity checks
- [x] Add automated REST validation smoke checks inside `wp cds smoke`
- [x] Add capability regression checks inside `wp cds smoke`
- [x] Add dedicated permission regression command (`wp cds test-permissions`)
- [x] Add renderer fallback smoke verification inside `wp cds smoke`

## 6. Release and Operations - 80%
Progress: `[########################------]`

- [x] Prepare migration/versioning notes for schema updates
- [x] Add production runbook (shared-host requirements, cleanup, troubleshooting)
- [ ] Final pre-release pass (README cleanup, screenshots, changelog)
- [x] Pre-release docs cleanup (README/links/changelog)
- [ ] Admin screenshots capture and final packaging pass
- [x] Screenshot capture checklist prepared (`SCREENSHOTS.md`)
- [x] CLI packaging command added (`wp cds package`)
- [x] CLI preflight command added (`wp cds preflight`) for docs/screenshots readiness checks
- [x] CLI release report command added (`wp cds release-report`) for one-shot readiness summary
- [x] CLI release run command added (`wp cds release-run`) for one-command release pipeline

## Recently Completed

- [x] Structured REST error contract (`code`, `message`, `fields`) for documents/templates endpoints
- [x] Shared admin API layer (`assets/admin/api.js`) and page-scoped script loading
- [x] Engine diagnostics exposed in generation response and documents admin result panel
- [x] Documents success panel now includes QR previews and quick-copy actions for tracking/payment links
- [x] Template Studio now auto-focuses the relevant JSON editor when parse/field validation errors occur
- [x] Recent Documents now supports server-backed pagination and search (tracking code/client email)
- [x] Template Studio revision history selector with duplicate + rollback publish actions
- [x] Template Studio revision compare report (added/removed/changed paths across schema/theme/layout)
- [x] REST payload guardrails added (size/depth/node limits for document generation and template/preview payloads)
- [x] Public tracking endpoint now enforces request throttling with `429` and `retry_after`
- [x] Daily retention cleanup job with settings-driven PDF/file pruning and draft-revision pruning
- [x] Audit repository + admin/REST listing with event logs for publish/generation/tracking stop workflows
- [x] Manual QA checklist added with ready-to-use sample payloads for all 3 document types
- [x] Migration notes and production runbook added (`MIGRATIONS.md`, `RUNBOOK.md`)
- [x] Changelog initialized (`CHANGELOG.md`) as part of pre-release documentation
- [x] Public tracking map now renders stop markers + route trail and clear no-GPS fallback message
- [x] Admin Tracking page now supports shipment search, stop updates, and timeline review
- [x] Line-items binding now supports repeatable payload rows and computed totals (`subtotal`, `tax_amount`, `grand_total`)
- [x] Template Studio defaults are now doc-type aligned for `invoice`, `receipt`, and `skr` (schema/theme/layout/sample payload parity baseline)
- [x] Invoice default renderer aligned to attached reference structure for inline preview/PDF parity baseline
- [x] Engine health/runtime now auto-detects legacy mPDF install and supports fallback in both directions (`mpdf` <-> `tcpdf`)
- [x] SKR default renderer aligned to attached reference structure for inline preview/PDF parity baseline
- [x] SKR layout scaled for full-page occupancy (print-optimized spacing/typography/table proportions)
- [x] Documents generator now supports schema-driven Form Mode with JSON advanced mode fallback
- [x] Added native TCPDF SKR renderer path (non-HTML) to eliminate parser-driven pagination corruption
- [x] Rebuilt SKR HTML template from stable working-document table pattern (removed fragile complex grid constructs)
- [x] Reintroduced SKR from the invoice working path, then refactored content/layout to SKR document structure
