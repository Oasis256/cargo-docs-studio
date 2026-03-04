# CargoDocs Studio Architecture

This document is the current separation-of-concerns map for the plugin.

## 1. Stability Contracts

The following contracts are intentionally stable:

- `RenderPipeline` public behavior and call flow
- `HtmlComposer::composeInvoice(array $payload, array $trackingBlock, ?array $paymentBlock, array $templateConfig): string`
- REST route paths and response envelopes for templates/documents/tracking
- Capability model (`cds_generate_documents`, `cds_view_documents`, `cds_delete_documents`, etc.)

## 2. Render Layer

Main entry:

- `src/Domain/Render/RenderPipeline.php`

Compatibility adapter:

- `src/Domain/Render/HtmlComposer.php`
  - Thin adapter only, delegates to `HtmlComposerFacade`

Facade + per-document renderers:

- `src/Domain/Render/Composer/HtmlComposerFacade.php`
- `src/Domain/Render/Composer/InvoiceRenderer.php`
- `src/Domain/Render/Composer/ReceiptRenderer.php`
- `src/Domain/Render/Composer/SkrRenderer.php`
- `src/Domain/Render/Composer/SkrStableViewBuilder.php` (stable SKR view-data preparation)

Shared render services:

- `src/Domain/Render/Composer/RenderContextFactory.php`
- `src/Domain/Render/Composer/Shared/FinancialCalculator.php`
- `src/Domain/Render/Composer/Shared/NumberFormatter.php`
- `src/Domain/Render/Composer/Shared/ImageResolver.php`

### Render responsibility boundaries

- `HtmlComposerFacade`: orchestration and renderer selection by `doc_type_key`
- `RenderContextFactory`: normalized context materialization
- `FinancialCalculator`: invoice-derived arithmetic and totals strategy
- `NumberFormatter`: display formatting (`20`, `20.5`, `20.05` style)
- `ImageResolver`: image/QR source resolution rules
- `InvoiceRenderer`/`ReceiptRenderer`/`SkrRenderer`: HTML output only

Current hardening decisions:

- Invoice totals are renderer-driven (no form-level totals override section).
- Invoice pipeline skips tracking QR generation work; response contract remains unchanged.
- SKR renderer uses stable path only (legacy SKR render method removed).

## 3. Admin Documents UI Layer

Page entry shim:

- `assets/admin/documents.js` (stable enqueue handle)

Modular runtime files:

- `assets/admin/documents/index.js` (page bootstrapping + module composition)
- `assets/admin/documents/state.js` (DOM references + mutable state)
- `assets/admin/documents/defaults.js` (default payloads + fallback schemas)
- `assets/admin/documents/sections.js` (document form section definitions/UI maps)
- `assets/admin/documents/form-renderer.js` (form generation from schema config)
- `assets/admin/documents/payload-sync.js` (form <-> JSON payload sync + node-cache optimized updates)
- `assets/admin/documents/validation.js` (required checks, hints, checklist, server mapping)
- `assets/admin/documents/documents-list.js` (recent documents list/search/pagination/delete)
- `assets/admin/documents/generation.js` (revision loading + generation flow + result panel)
- `assets/admin/documents/events-form.js` (form/doc-type/revision/generation/payload listeners)
- `assets/admin/documents/events-list.js` (list filter/search/pagination listeners)
- `assets/admin/documents/events-result.js` (result panel copy/delete listeners)
- `assets/admin/documents/events.js` (thin event-orchestration facade)
- `assets/admin/documents/utils.js` (shared small helpers)

### UI behavior ownership

- Form/JSON mode switching: `events-form.js`
- Generation submit + result panel actions: `generation.js` + `events-form.js`
- Delete PDF from list: `documents-list.js`
- Delete PDF from generation result panel: `events-result.js` action handler

## 4. Asset Loading Graph

`src/Core/Assets.php` enqueues documents modules in dependency order:

1. `state.js`
2. `defaults.js`
3. `sections.js`
4. `utils.js`
5. `form-renderer.js`
6. `payload-sync.js`
7. `validation.js`
8. `documents-list.js`
9. `generation.js`
10. `events-form.js`
11. `events-list.js`
12. `events-result.js`
13. `events.js`
14. `index.js`
15. `documents.js` (stable shim)

## 4.1 Update System (GitHub Releases)

Updater components:

- `src/Core/Updater.php`
- `src/Domain/Update/GitHubReleaseClient.php`
- `src/Domain/Update/VersionComparator.php`

WordPress hooks used:

- `pre_set_site_transient_update_plugins`
- `plugins_api`
- `upgrader_process_complete`

Release feed contract:

- Repository: `Oasis256/cargo-docs-studio`
- Stable-only releases (draft/prerelease ignored)
- Required asset: `cargo-docs-studio.zip`
- Cache key: site transient with TTL from `CDS_UPDATER_CACHE_TTL`

## 5. Current Size Check (SoC Gate)

Composer PHP files (largest first):

- `SkrRenderer.php` ~442 lines
- `SkrStableViewBuilder.php` ~205 lines
- `HtmlComposerFacade.php` ~272 lines
- `InvoiceRenderer.php` ~268 lines
- `ReceiptRenderer.php` ~234 lines

Admin documents JS files (largest first):

- `validation.js` ~281 lines
- `form-renderer.js` ~272 lines
- `generation.js` ~229 lines
- `payload-sync.js` ~223 lines
- `events-form.js` ~140 lines
- `index.js` ~164 lines

Status:

- All render classes currently satisfy the Phase-1 target (<500 lines).
- `HtmlComposer.php` remains orchestration-only adapter.

## 6. Verification Performed

Static verification completed:

- `php -l` on all `src/**/*.php` in plugin: pass
- `node --check` on admin/public JS in plugin: pass

Runtime note:

- End-to-end browser/UI checks were validated in prior interactive passes.
- WP-CLI runtime smoke commands depend on local WP-CLI availability.

## 7. Next Refactor Candidates (Optional, Non-blocking)

If further reduction is needed, split:

- `SkrRenderer.php` into section partial builders (header/table/footer/watermark blocks)
- `events-form.js` into smaller event domains if needed (`events-form-input`, `events-form-actions`)

These are optional and not required for current contract stability.
