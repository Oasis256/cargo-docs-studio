# CargoDocs Studio

CargoDocs Studio is a WordPress plugin for secure cargo document operations: Invoice, Receipt, and SKR generation, template lifecycle management, shipment tracking, and QR-enabled workflows.

## Key Features

- Multi-document PDF generation (`invoice`, `receipt`, `skr`)
- Template Studio with draft/publish revisions and compare/rollback
- Form mode and JSON mode generation workflows
- Tracking timeline administration and public tracking endpoint
- Payment QR and tracking QR support
- PDF engine selection (`tcpdf` / `mpdf`) with runtime fallback
- Audit trail and retention cleanup automation
- GitHub release-driven in-dashboard plugin updates

## Requirements

- WordPress with REST API enabled
- PHP with `ZipArchive` support for packaging
- Write access to `wp-content/uploads`
- WP-Cron enabled for scheduled retention cleanup
- At least one PDF engine available (`tcpdf` default, optional `mpdf`)

## Installation

1. Download a release asset (`cargo-docs-studio.zip`) from GitHub Releases.
2. In WordPress Admin, go to `Plugins > Add New > Upload Plugin`.
3. Upload the ZIP, install, and activate.
4. Open `CargoDocs Studio > Settings` and configure engine + retention.

## Quick Start

1. Create or publish a template in `CargoDocs Studio > Template Studio`.
2. Open `CargoDocs Studio > Documents`.
3. Select document type and template revision.
4. Fill payload (Form Mode or JSON Mode) and generate.
5. Validate generated PDF, tracking link, and QR outputs.

## Release and Packaging

If WP-CLI is available:

- `wp cds release-report`
- `wp cds preflight`
- `wp cds smoke`
- `wp cds test-permissions`
- `wp cds package --output=path/to/cargo-docs-studio.zip`
- `wp cds release-run --output=path/to/cargo-docs-studio.zip`

Versioning must stay aligned across:

- `cargo-docs-studio.php` plugin header `Version`
- `CDS_VERSION` constant
- Git tag (`vX.Y.Z`)

## Documentation Map

- Technical reference: [TECHNICAL.md](TECHNICAL.md)
- Changelog: [CHANGELOG.md](CHANGELOG.md)
- Architecture notes: [ARCHITECTURE.md](ARCHITECTURE.md)
- Migrations: [MIGRATIONS.md](MIGRATIONS.md)
- QA checklist: [QA_CHECKLIST.md](QA_CHECKLIST.md)
- Runbook: [RUNBOOK.md](RUNBOOK.md)
- Screenshot checklist: [SCREENSHOTS.md](SCREENSHOTS.md)
- Active backlog: [TODO.md](TODO.md)

## Repository

- GitHub: `https://github.com/Oasis256/cargo-docs-studio`
