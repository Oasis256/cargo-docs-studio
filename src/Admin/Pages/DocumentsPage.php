<?php

namespace CargoDocsStudio\Admin\Pages;

class DocumentsPage
{
    public function render(): void
    {
        if (!current_user_can('cds_view_documents') && !current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'cargo-docs-studio'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Documents', 'cargo-docs-studio') . '</h1>';
        echo '<p>' . esc_html__('Generate invoice, receipt, or SKR documents from selected templates.', 'cargo-docs-studio') . '</p>';

        echo '<div id="cds-documents-page" class="cds-grid">';
        if (current_user_can('cds_generate_documents') || current_user_can('manage_options')) {
            echo '<section class="cds-card">';
            echo '<h2>' . esc_html__('Generate Document', 'cargo-docs-studio') . '</h2>';

            echo '<label for="cds-doc-gen-type">' . esc_html__('Document Type', 'cargo-docs-studio') . '</label>';
            echo '<select id="cds-doc-gen-type">';
            echo '<option value="invoice">Invoice</option>';
            echo '<option value="receipt">Receipt</option>';
            echo '<option value="skr">SKR</option>';
            echo '</select>';

            echo '<label for="cds-doc-template-revision">' . esc_html__('Template Revision', 'cargo-docs-studio') . '</label>';
            echo '<select id="cds-doc-template-revision"><option value="">' . esc_html__('Auto (published/default)', 'cargo-docs-studio') . '</option></select>';
            echo '<p><button id="cds-reload-revisions" class="button" type="button">' . esc_html__('Reload Revisions', 'cargo-docs-studio') . '</button></p>';
            echo '<p id="cds-doc-revision-note" class="description"></p>';

            echo '<label for="cds-doc-payload-json">' . esc_html__('Payload JSON', 'cargo-docs-studio') . '</label>';
            echo '<div class="cds-doc-mode-switch" role="tablist" aria-label="' . esc_attr__('Payload Editor Mode', 'cargo-docs-studio') . '">';
            echo '<button id="cds-doc-mode-form" class="button button-primary" type="button">' . esc_html__('Form Mode', 'cargo-docs-studio') . '</button> ';
            echo '<button id="cds-doc-mode-json" class="button" type="button">' . esc_html__('JSON Mode', 'cargo-docs-studio') . '</button>';
            echo '</div>';
            echo '<div id="cds-doc-form-builder" class="cds-doc-form-builder"></div>';
            echo '<textarea id="cds-doc-payload-json" rows="14"></textarea>';
            echo '<div id="cds-doc-required-checklist" class="cds-required-checklist"></div>';
            echo '<div id="cds-doc-validation-hints" class="cds-validation-hints"></div>';

            echo '<p><button id="cds-generate-document" class="button button-primary">' . esc_html__('Generate Document', 'cargo-docs-studio') . '</button> ';
            echo '<button id="cds-autofix-payload" class="button" type="button">' . esc_html__('Auto-fix Payload', 'cargo-docs-studio') . '</button></p>';
            echo '<div id="cds-doc-status" class="notice inline" style="display:none;"><p></p></div>';
            echo '<div id="cds-doc-result"></div>';
            echo '</section>';
        } else {
            echo '<section class="cds-card">';
            echo '<h2>' . esc_html__('Generate Document', 'cargo-docs-studio') . '</h2>';
            echo '<p>' . esc_html__('You do not have permission to generate documents. You can still view recent documents.', 'cargo-docs-studio') . '</p>';
            echo '</section>';
        }

        echo '<section class="cds-card">';
        echo '<h2>' . esc_html__('Recent Documents', 'cargo-docs-studio') . '</h2>';
        echo '<div class="cds-doc-list-toolbar">';
        echo '<div>';
        echo '<label for="cds-doc-list-filter">' . esc_html__('Filter by Type', 'cargo-docs-studio') . '</label>';
        echo '<select id="cds-doc-list-filter">';
        echo '<option value="all">' . esc_html__('All', 'cargo-docs-studio') . '</option>';
        echo '<option value="invoice">Invoice</option>';
        echo '<option value="receipt">Receipt</option>';
        echo '<option value="skr">SKR</option>';
        echo '</select>';
        echo '</div>';
        echo '<div>';
        echo '<label for="cds-doc-list-search">' . esc_html__('Search', 'cargo-docs-studio') . '</label>';
        echo '<div class="cds-doc-search-row">';
        echo '<input id="cds-doc-list-search" type="text" placeholder="' . esc_attr__('Tracking code or client email', 'cargo-docs-studio') . '" />';
        echo '<button id="cds-doc-list-search-btn" class="button" type="button">' . esc_html__('Search', 'cargo-docs-studio') . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<p class="description" id="cds-doc-list-meta"></p>';
        echo '<div id="cds-documents-list"></div>';
        echo '<div class="cds-doc-list-pager">';
        echo '<button id="cds-doc-prev-page" class="button" type="button">' . esc_html__('Previous', 'cargo-docs-studio') . '</button>';
        echo '<span id="cds-doc-page-info"></span>';
        echo '<button id="cds-doc-next-page" class="button" type="button">' . esc_html__('Next', 'cargo-docs-studio') . '</button>';
        echo '</div>';
        echo '</section>';
        echo '</div>';

        echo '</div>';
    }
}
