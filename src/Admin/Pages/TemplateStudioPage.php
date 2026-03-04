<?php

namespace CargoDocsStudio\Admin\Pages;

class TemplateStudioPage
{
    public function render(): void
    {
        if (!current_user_can('cds_manage_templates') && !current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'cargo-docs-studio'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Template Studio', 'cargo-docs-studio') . '</h1>';
        echo '<p>' . esc_html__('Configure template fields, theme tokens, and layout for invoice, receipt, and SKR documents.', 'cargo-docs-studio') . '</p>';

        echo '<div id="cds-template-studio">';
        echo '<div class="cds-grid">';
        echo '<section class="cds-card">';
        echo '<h2>' . esc_html__('Create Template', 'cargo-docs-studio') . '</h2>';
        echo '<label>' . esc_html__('Document Type', 'cargo-docs-studio') . '</label>';
        echo '<select id="cds-doc-type">';
        echo '<option value="invoice">Invoice</option>';
        echo '<option value="receipt">Receipt</option>';
        echo '<option value="skr">SKR</option>';
        echo '</select>';
        echo '<label>' . esc_html__('Template Name', 'cargo-docs-studio') . '</label>';
        echo '<input id="cds-template-name" type="text" class="regular-text" placeholder="Default Invoice Theme" />';
        echo '<p><button id="cds-create-template" class="button button-primary">' . esc_html__('Create', 'cargo-docs-studio') . '</button></p>';
        echo '</section>';

        echo '<section class="cds-card">';
        echo '<h2>' . esc_html__('Templates', 'cargo-docs-studio') . '</h2>';
        echo '<div id="cds-template-list"></div>';
        echo '</section>';
        echo '</div>';

        echo '<section class="cds-card cds-editor">';
        echo '<h2>' . esc_html__('Revision Editor', 'cargo-docs-studio') . '</h2>';
        echo '<p class="description">' . esc_html__('Use the visual builder for fields/groups, or edit raw JSON for advanced control.', 'cargo-docs-studio') . '</p>';
        echo '<div class="cds-revision-toolbar">';
        echo '<div>';
        echo '<label for="cds-revision-select">' . esc_html__('Revision History', 'cargo-docs-studio') . '</label>';
        echo '<select id="cds-revision-select"><option value="">' . esc_html__('Select a loaded template first', 'cargo-docs-studio') . '</option></select>';
        echo '</div>';
        echo '<div class="cds-revision-actions">';
        echo '<select id="cds-compare-revision"><option value="">' . esc_html__('Compare against...', 'cargo-docs-studio') . '</option></select> ';
        echo '<button id="cds-compare-run" class="button" type="button">' . esc_html__('Compare', 'cargo-docs-studio') . '</button> ';
        echo '<button id="cds-duplicate-revision" class="button" type="button">' . esc_html__('Duplicate Selected Revision', 'cargo-docs-studio') . '</button> ';
        echo '<button id="cds-rollback-revision" class="button" type="button">' . esc_html__('Rollback (Publish Selected)', 'cargo-docs-studio') . '</button>';
        echo '</div>';
        echo '</div>';
        echo '<div id="cds-revision-compare-result" class="cds-compare-result"><p class="description">' . esc_html__('Choose two revisions and click Compare to view differences.', 'cargo-docs-studio') . '</p></div>';

        echo '<div class="cds-builder-grid">';
        echo '<div>';
        echo '<h3>' . esc_html__('Fields Builder', 'cargo-docs-studio') . '</h3>';
        echo '<div id="cds-fields-builder"></div>';
        echo '<p><button id="cds-add-field" class="button">' . esc_html__('Add Field', 'cargo-docs-studio') . '</button></p>';
        echo '</div>';
        echo '<div>';
        echo '<h3>' . esc_html__('Groups Builder', 'cargo-docs-studio') . '</h3>';
        echo '<div id="cds-groups-builder"></div>';
        echo '<p><button id="cds-add-group" class="button">' . esc_html__('Add Group', 'cargo-docs-studio') . '</button></p>';
        echo '</div>';
        echo '</div>';

        echo '<label for="cds-schema-json">' . esc_html__('Schema JSON', 'cargo-docs-studio') . '</label>';
        echo '<textarea id="cds-schema-json" rows="12"></textarea>';

        echo '<h3>' . esc_html__('Theme Builder', 'cargo-docs-studio') . '</h3>';
        echo '<div id="cds-theme-builder" class="cds-theme-builder"></div>';

        echo '<label for="cds-theme-json">' . esc_html__('Theme JSON', 'cargo-docs-studio') . '</label>';
        echo '<textarea id="cds-theme-json" rows="12"></textarea>';

        echo '<h3>' . esc_html__('Layout Builder', 'cargo-docs-studio') . '</h3>';
        echo '<div id="cds-layout-builder" class="cds-layout-builder"></div>';

        echo '<label for="cds-layout-json">' . esc_html__('Layout JSON', 'cargo-docs-studio') . '</label>';
        echo '<textarea id="cds-layout-json" rows="8"></textarea>';

        echo '<label for="cds-sample-payload-json">' . esc_html__('Sample Payload JSON (Preview)', 'cargo-docs-studio') . '</label>';
        echo '<textarea id="cds-sample-payload-json" rows="8"></textarea>';

        echo '<p>';
        echo '<button id="cds-save-revision" class="button button-primary">' . esc_html__('Save Draft Revision', 'cargo-docs-studio') . '</button> ';
        echo '<button id="cds-publish-revision" class="button">' . esc_html__('Publish Revision', 'cargo-docs-studio') . '</button> ';
        echo '<button id="cds-preview-pdf" class="button">' . esc_html__('Preview PDF', 'cargo-docs-studio') . '</button> ';
        echo '<button id="cds-preview-inline" class="button">' . esc_html__('Preview Inline', 'cargo-docs-studio') . '</button> ';
        echo '<label><input id="cds-set-default" type="checkbox" /> ' . esc_html__('Set as default for this document type', 'cargo-docs-studio') . '</label>';
        echo '</p>';
        echo '<div id="cds-template-status" class="notice inline" style="display:none;"><p></p></div>';
        echo '<div class="cds-preview-wrap"><iframe id="cds-inline-preview" title="Template Preview"></iframe></div>';
        echo '</section>';
        echo '</div>';
        echo '</div>';
    }
}
