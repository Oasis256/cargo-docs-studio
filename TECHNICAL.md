# CargoDocs Studio Technical Reference

This document is the implementation-focused reference for CargoDocs Studio, covering architecture behavior, module boundaries, operational mechanics, endpoints, and release/update internals.

## PDF engine toggle

The renderer is toggleable from WordPress admin:

- `CargoDocs Studio > Settings > PDF Engine`
- `CargoDocs Studio > Settings > Engine Health` shows whether each engine is available on the server.
- Unavailable engines are disabled in the selector and cannot be saved.

- `tcpdf` (default, shared-host friendly)
- `mpdf` (better modern CSS/emoji behavior if mPDF is installed in this plugin)

If `mpdf` is selected but unavailable at runtime, generation falls back to `tcpdf`.
Both adapters now use aligned page margins and UTF-8 friendly default fonts for improved cross-engine output consistency.
If `tcpdf` is selected but unavailable at runtime, generation now attempts fallback to `mpdf`.
Engine health now also detects `mPDF` from the legacy `cargo-tracking-pdf-generator/vendor` path when present on the same site.

## SKR Watermark Configuration

SKR watermark URL is now configured globally in:

- `CargoDocs Studio > Settings > SKR Watermark`

Document generation now exposes watermark toggles in payload/form mode for all document types:

- `watermark_enabled` (checkbox, common)
- `skr_watermark_enabled` remains accepted for backward compatibility with older SKR payloads.

When enabled, renderer uses the global Settings watermark URL. Legacy payload URL keys are kept as fallback for older data.
SKR Form Mode now includes an expanded field set (company, custody grid, goods/value grid, signature/footer fields), and those values are printed directly into the generated SKR PDF.
SKR Form Mode is now rendered as a guided section-based generator UI (Depositor, Custody, Contents, Deposit, Supporting Documents, Instructions, Additional Information) instead of a flat key/value list.

## Maintenance and Retention

Settings now include retention controls at:

- `CargoDocs Studio > Settings > Retention & Cleanup`

Available controls:

- PDF retention days (old generated files are deleted and document file pointers are cleared)
- Max draft revisions per template (published revisions are preserved)
- Manual `Run Cleanup Now` action

Automation:

- Daily cleanup is scheduled via WP-Cron (`cds_daily_cleanup_event`).

## Audit Events

Audit events are now persisted and visible in:

- `CargoDocs Studio > Audit`
- `GET /wp-json/cds/v1/audit` (requires audit capability)

Current logged event families include:

- Template publishing and revision duplication
- Document generation success/failure (including render failures)
- Tracking stop update success/failure

## Template Studio (Phase 2 baseline)

Template Studio is now interactive at:

- `CargoDocs Studio > Template Studio`

Current capabilities:

- Create templates per document type (`invoice`, `receipt`, `skr`)
- Visual fields/groups builder for non-technical editing (syncs with `schema` JSON)
- Visual theme builder (colors, font, spacing, table tokens) synced with `theme` JSON
- Visual layout builder (title, page, section enable/order, QR placement) synced with `layout` JSON
- Edit `schema`, `theme`, and `layout` JSON
- Save draft revisions
- Publish revisions and optionally set template default
- Revision history selector to load older revisions into the editor
- Duplicate selected revision from history
- Rollback support by publishing a selected historical revision
- Revision compare report between two selected revisions (added/removed/changed paths across schema/theme/layout)
- List templates filtered by selected document type
- Invoice rendering now consumes published template `schema/theme/layout` (or explicit `template_revision_id` when provided)
- Line-items renderer accepts repeatable `line_items` payload rows (`description`, `quantity`, `unit_price`, `total`)
- Financial totals are computed when not provided (`subtotal`, `tax_amount`, `grand_total`; optional `tax_rate`)
- `layout.page` is enforced in PDF adapters (`A4`/`LETTER`), and QR `size`/`position` are applied in rendered output
- QR `left/right` placement now affects card ordering/column placement in the QR section
- Document-type default section packs are now applied when layout sections are not explicitly set:
- `invoice`: `header`, `summary`, `line_items`, `tracking_qr`, `payment_qr`, `footer`
- `receipt`: `header`, `summary`, `payment_qr`, `footer`
- `skr`: `header`, `summary`, `tracking_qr`, `footer`
- Template Studio now pre-fills layout title/sections and sample payload by selected document type.
- Template Studio now pre-fills document-type specific schema/theme/layout/sample payload presets aligned to Invoice, Receipt, and SKR structures from your attached reference documents.
- Receipt default layout now includes commodity line items and uses `Payment Receipt` title by default.
- SKR default layout title is now `Safe Keeping Receipt` with custody/depositor/value-oriented default field groups.
- Invoice default renderer now uses a reference-matching layout (logo + date/invoice + tracking QR header, commodity block, billed-to/destination, multi-row cost table, signature block, and branded contact footer) to mirror the attached invoice document.
- SKR default renderer now uses a reference-matching layout (security header block, custody/depositor grid, goods/value details table, legal declaration, tracking-number section, signature area) to mirror the attached SKR document.
- SKR print layout has been scaled to use near full printable page area (reduced page padding, larger typography, expanded table proportions) for closer parity with full-page scanned originals.
- SKR is engine-independent and now follows the same HTML-render pipeline as inline preview and other document types, honoring selected engine (`mpdf`/`tcpdf`) with fallback.
- SKR template was rebuilt from a simple, proven table pattern used by working document outputs (no rowspan/complex grid dependencies) for stronger PDF parity.
- SKR was reintroduced from the working invoice-render path, then refactored into SKR-specific sections (custody/depositor grid, goods details, legal block, tracking line, signature block).
- Generate draft preview PDF from Template Studio using the current JSON editor values
- Generate inline HTML preview in Template Studio for faster iteration
- Template Studio now auto-focuses the relevant JSON editor (`schema`, `theme`, `layout`, `sample payload`) when parse/validation errors occur

REST routes:

- `GET /wp-json/cds/v1/templates`
- `POST /wp-json/cds/v1/templates`
- `GET /wp-json/cds/v1/templates/revisions?doc_type={invoice|receipt|skr}`
- `GET /wp-json/cds/v1/templates/{id}`
- `POST /wp-json/cds/v1/templates/{id}/revisions`
- `POST /wp-json/cds/v1/templates/{id}/revisions/duplicate`
- `POST /wp-json/cds/v1/templates/{id}/publish`
- `POST /wp-json/cds/v1/templates/preview`
- `POST /wp-json/cds/v1/templates/preview-html`
- `POST /wp-json/cds/v1/documents/invoice/generate`
- `POST /wp-json/cds/v1/documents/receipt/generate`
- `POST /wp-json/cds/v1/documents/skr/generate`

`GET /wp-json/cds/v1/documents` supports `doc_type`, `search`, `page`, and `limit` query params.

Preview endpoints now accept `doc_type_key` (`invoice`, `receipt`, `skr`) so previews match document-type defaults.
Document generation validates that `template_revision_id` matches the requested document type.
Document generation requires a valid revision: either explicit `template_revision_id` or a published default for that document type.
Non-template-managers are blocked from generating documents with draft revisions.
Template and preview payloads are protected by structure limits (size, depth, and node-count guardrails).
Public tracking lookup endpoint is rate-limited and returns `429` with `retry_after` when throttled.
Public tracking page map now shows current marker, stop markers, route polyline (when coordinates exist), and clear fallback text when no GPS data is available.

## Documents Page

`CargoDocs Studio > Documents` now includes an admin generator panel that can:

- Select document type (`invoice`, `receipt`, `skr`)
- Select template revision (or auto published/default)
- Select any template revision with `Published`/`Draft` status labels
- Inline revision availability note (loaded count / no-accessible-revisions guidance)
- Manual `Reload Revisions` control for permission/network recovery without page refresh
- Submit payload JSON and generate document instantly
- Switch between `Form Mode` (schema-driven inputs, default) and `JSON Mode` (advanced)
- Auto-build input fields from selected template revision schema when available
- Keep form inputs and payload JSON synchronized both ways
- Inline payload validation hints for required fields/email format
- Required-fields checklist with live pass/fail indicators (`client_name`, `client_email`, `cargo_type`)
- `Auto-fix Payload` action to fill/repair required payload keys
- `Generate Document` button is disabled until payload JSON is valid and required fields pass validation
- Generator is pre-disabled when no accessible template revisions exist for the selected document type
- Generator remains disabled while revision options are loading or if revision fetch fails
- Generation uses a busy/locked submit state to prevent duplicate requests
- Show generated PDF/tracking links
- Filter recent generated documents by `all/invoice/receipt/skr`
- Search recent documents by tracking code or client email
- Paginate recent documents with previous/next navigation and total count
- Delete PDF directly from the generation result panel (post-generate cleanup)
- Delete PDF from the recent documents list
- Server-side structured validation errors are surfaced in UI with field-level detail
- On server validation failure, the payload editor auto-focuses the first invalid key (when present)
- Generation result panel now shows renderer diagnostics (`selected engine`, `engine used`, fallback reason when applicable)
- Generation result panel includes tracking/payment QR previews and quick-copy actions for tracking/payment links
- Document generation payload is protected by size/depth/node-count limits with structured field errors when exceeded

Access behavior:

- Generator panel requires `cds_generate_documents` (or admin)
- Recent documents list remains viewable with `cds_view_documents`
- Template revisions endpoint is accessible to users who can manage templates or generate documents
- Revision visibility is capability-aware:
- Template managers can see `Published` + `Draft` revisions
- Document generators without template-management can only see `Published` revisions

Performance note:

- Admin scripts are page-scoped (`Template Studio` and `Documents`) instead of loading all plugin scripts on every CargoDocs admin screen.
- Shared admin REST helper (`assets/admin/api.js`) centralizes request/error handling for both Template Studio and Documents pages.

## Tracking Admin

`CargoDocs Studio > Tracking` now supports:

- Search recent shipments by tracking code
- Open a shipment and review current timeline
- Post stop updates (status, notes, optional latitude/longitude)
- Auto-grab GPS coordinates from browser geolocation (`Use Current GPS`) to prefill latitude/longitude
- GPS metadata tagging in notes when auto-captured (accuracy + capture timestamp)
- Capability-aware access:
- `cds_view_tracking_admin` can view
- `cds_update_tracking` can submit stop updates

## Remaining Work

- The active remaining-work checklist is tracked in `TODO.md`.
- `TODO.md` is maintained as a progress bar (overall + per-section completion).
- Manual verification checklist is available at `QA_CHECKLIST.md`.
- Migration/version notes are tracked in `MIGRATIONS.md`.
- Production operations guide is available in `RUNBOOK.md`.
- Release history is tracked in `CHANGELOG.md`.
- Screenshot capture checklist is available in `SCREENSHOTS.md`.
- Architecture map is documented in `ARCHITECTURE.md`.

## CLI Smoke Test

If WP-CLI is available, run:

- `wp cds release-report`
- `wp cds release-run --output=path/to/cargo-docs-studio.zip`
- `wp cds smoke`
- `wp cds test-permissions`
- `wp cds preflight`
- `wp cds package --output=path/to/cargo-docs-studio.zip`

`wp cds release-report` summarizes release readiness and missing docs/screenshots.

`wp cds release-run` executes preflight, smoke, permission regression, and package steps in sequence.

`wp cds smoke` checks core table presence, key REST routes, critical settings sanity, administrator capability baseline, REST validation/permission behavior, renderer generation smoke, and `mpdf` selection fallback-path behavior.

`wp cds test-permissions` validates core permission boundaries (guest vs administrator) for sensitive controllers.

`wp cds preflight` checks required docs + expected screenshot files before packaging.

`wp cds package` builds a distributable plugin zip and warns if key release docs are missing.

## GitHub Release Automation and Plugin Updates

This plugin is now prepared for GitHub-managed releases and in-dashboard updates.

- Target repository: `https://github.com/Oasis256/cargo-docs-studio`
- Update channel: stable releases only
- Expected release asset: `cargo-docs-studio.zip`
- Plugin metadata includes `Update URI` to avoid WP.org collisions.

### Automated release workflow

- GitHub Action: `.github/workflows/release.yml`
- Trigger: push tag matching `v*.*.*`
- Actions:
  - validate Composer metadata
  - run PHP and JS syntax checks
  - build runtime ZIP (excludes `checkpoints/`, `dist/`, local artifacts)
  - create GitHub release and upload `cargo-docs-studio.zip`

### Versioning policy

Version must stay in sync across:

- `cargo-docs-studio.php` plugin header `Version`
- `CDS_VERSION` constant
- Git tag name (`vX.Y.Z`)

### WordPress updater behavior

The updater checks GitHub public releases and injects updates into standard WordPress plugin update UI.
It caches release metadata and clears cache after successful plugin upgrades.
Pre-releases and drafts are ignored.

## Separation of Concerns Refactor (Phase 1)

Phase 1 backend split is now implemented with compatibility-first wiring.

Render entrypoint stability:

- `RenderPipeline` remains unchanged.
- `HtmlComposer::composeInvoice(...)` remains unchanged.

New render architecture:

- `src/Domain/Render/Composer/HtmlComposerFacade.php` (orchestration entrypoint)
- `src/Domain/Render/Composer/RenderContextFactory.php` (normalized doc context)
- `src/Domain/Render/Composer/Shared/NumberFormatter.php`
- `src/Domain/Render/Composer/Shared/FinancialCalculator.php`
- `src/Domain/Render/Composer/Shared/ImageResolver.php`
- `src/Domain/Render/Composer/InvoiceRenderer.php`
- `src/Domain/Render/Composer/ReceiptRenderer.php`
- `src/Domain/Render/Composer/SkrRenderer.php`

Compatibility wrapper:

- `src/Domain/Render/HtmlComposer.php` is now a thin adapter delegating to `HtmlComposerFacade`.

Notes:

- Existing route contracts and payload keys were preserved.
- SKR legacy renderer path was removed; `SkrRenderer` now uses the stable path only.

## Documents Admin Modularization (Phase 2 Completed)

Documents admin JavaScript has been split into a staged modular structure:

- `assets/admin/documents/state.js`
- `assets/admin/documents/defaults.js`
- `assets/admin/documents/sections.js`
- `assets/admin/documents/events-form.js`
- `assets/admin/documents/events-list.js`
- `assets/admin/documents/events-result.js`
- `assets/admin/documents/events.js`
- `assets/admin/documents/index.js`
- `assets/admin/documents.js` (stable enqueue entry shim)

Loading order is now managed in `src/Core/Assets.php` so the legacy enqueue handle remains stable while internals are modularized.
- Additional Phase 2 extraction now active:
  - `assets/admin/documents/validation.js`
  - `assets/admin/documents/documents-list.js`
  - `assets/admin/documents/generation.js`
- `assets/admin/documents/events-form.js`, `events-list.js`, and `events-result.js` now own domain-specific event wiring.
- `assets/admin/documents/events.js` is now a thin event-orchestration facade.
- Additional Phase 2 extraction now active:
  - `assets/admin/documents/form-renderer.js`
  - `assets/admin/documents/payload-sync.js`
- `assets/admin/documents/index.js` is now orchestration-focused (module wiring + bootstrap delegation), with event-heavy logic moved out.
### Documents Admin Module Map

Current `assets/admin/documents/` responsibility split:

- `state.js`: DOM lookup + shared state creation
- `defaults.js`: default payloads + fallback schemas
- `sections.js`: section layout and field UI maps by doc type
- `utils.js`: shared escape/copy utility helpers
- `form-renderer.js`: form schema normalization + structured form rendering
- `payload-sync.js`: payload/form sync + cached node lookups for efficient updates
- `validation.js`: required checks, hints, checklist, server error mapping, autofix
- `documents-list.js`: recent list fetch/render, pagination/meta, delete action
- `generation.js`: revision loading, schema switching, document generation flow
- `events-form.js`: form mode/doc type/revision/generation/payload editor listeners
- `events-list.js`: list filter/search/pagination listeners + list delete binding
- `events-result.js`: generation result panel actions (copy + delete PDF)
- `events.js`: thin orchestrator delegating to event-domain modules
- `index.js`: page bootstrap + module composition
- `../documents.js`: stable enqueue shim

Verification status:

- Phase 1 render split verified with compatibility wrapper intact (`RenderPipeline` unchanged).
- Phase 2 admin modularization verified for form/json mode, generation, list pagination/search, and PDF delete actions (recent list + post-generate result panel).
- Phase 3 cleanup complete for active paths; thin composer adapter intentionally retained for backward compatibility.

## Cleanup Hardening (Current)

- Receipt renderer runtime bug fixed (`payment_network` is now initialized before use).
- Invoice totals override fields (`tax_amount`, `insurance_amount`, `grand_total`) are no longer exposed in form schema sections.
- Invoice tracking QR generation is skipped in render pipeline paths for efficiency; response envelope remains stable with empty `tracking_qr_data_uri` for invoices.
- Documents payload sync now caches form nodes and avoids full form re-sync on every keystroke.
