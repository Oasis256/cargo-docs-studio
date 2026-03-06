# Changelog

All notable changes to CargoDocs Studio are documented here.

## 0.1.9

### Changed

- Updated receipt commodity form behavior so the per-row `Amount` input is treated as a per-quantity value (line total = `qty * amount`).
- Removed legacy `Amount Paid` from receipt form sections and default payload schema.
- Kept compatibility by deriving `amount_paid` automatically from commodity totals during payload sync.

## 0.1.8

### Changed

- Refined receipt commodity editing UX by restructuring the repeater form layout and aligning fields with the standardized admin form aesthetics.
- Added explicit per-commodity `Amount` capture in receipt form mode and improved payload sync behavior between form and JSON representations.
- Updated receipt rendering to alternate commodity row backgrounds for clearer visual separation.
- Right-aligned totals-in-words rows in both invoice and receipt outputs for consistent monetary presentation.
- Improved receipt total computation precedence so generated line-item totals are authoritative when `line_items` are provided, avoiding legacy `amount_paid` override mismatches.
- Updated invoice purity/carat presentation logic and related rendering pathways for improved output consistency.

## 0.1.7

### Changed

- Reworked the project documentation structure so `README.md` now serves as a true user-facing plugin overview focused on installation, quick start, release commands, and document navigation.
- Moved the former implementation-heavy README content into a dedicated technical reference document (`TECHNICAL.md`) to separate operator guidance from architecture and internal behavior notes.

## 0.1.6

### Changed

- Expanded SKR form payload defaults and section mapping to include a dedicated `purity` field, so operators can capture purity percentage directly in form mode without custom JSON edits.
- Improved invoice cargo detail rendering to compose purity/carat qualifiers consistently:
- auto-appends `%` when purity is entered as a bare number,
- appends `Pure` when purity exists and no carat percentage is provided,
- includes carat percentage only when `carats_enabled` is active.
- Updated invoice table presentation for freight rows to display quantity/unit context in the quantity column instead of a placeholder dash, improving invoice readability during reconciliation.

### Fixed

- Corrected freight charge math in invoice totals and shared financial calculations so freight is now computed per unit (`freight_cost * quantity`) rather than as a flat one-time value.

## 0.1.5

### Changed

- Capitalized numeric words in totals-in-words output for generated documents.
- Adjusted invoice footer/banking block spacing for improved fixed-page positioning.

## 0.1.4

### Changed

- Added totals-in-words rendering for invoice and receipt outputs.
- Moved total words into dedicated table rows matching total-row styling.
- Updated receipt layout so footer block stays pinned to the page footer area.
- Improved money formatting with smart decimal trimming and number-to-words helpers.

## 0.1.3

### Changed

- Enforced server-side auto-generation of document identifiers (`invoice_number`, `receipt_number`, `document_number`, `deposit_number`, `reg_number`).
- Removed manual identifier fields from admin document forms/default schemas.

## 0.1.2

### Fixed

- Packaging now writes ZIP entries with forward slashes for cloud-host compatibility.
- Updater now always registers plugin update state (`response`/`no_update`) so auto-update UI can appear consistently.
- Database schema SQL definitions normalized for safer `dbDelta` parsing during activation/migration.

## 0.1.1

### Changed

- Version bump for GitHub-tagged release flow.

## 0.1.0

### Added

- Configurable template studio for `invoice`, `receipt`, and `skr`.
- PDF engine toggle with runtime diagnostics and fallback metadata.
- Documents generator admin page with validation, auto-fix, and template revision selection.
- Tracking and payment QR support in generated documents.
- Structured REST error responses with field-level metadata.
- Shared admin REST client (`assets/admin/api.js`) for consistent error handling.
- Recent documents pagination and search.
- Template revision safety tooling: duplicate, rollback publish, and compare report.
- Public tracking endpoint rate limiting.
- Payload/structure guardrails for generation and preview requests.
- Retention policy with daily cleanup cron and manual cleanup trigger.
- Audit repository, audit REST endpoint, and audit admin view.
- QA checklist, migration notes, and production runbook documentation.
