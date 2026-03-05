# Changelog

All notable changes to CargoDocs Studio are documented here.

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
