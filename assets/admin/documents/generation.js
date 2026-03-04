(function (window) {
  "use strict";

  const { escapeHtml, escapeHtmlAttr } = window.CDS_DOCS_UTILS;

  function createGenerationModule(deps) {
    const {
      els,
      state,
      api,
      getDocType,
      formatApiError,
      fallbackSchemas,
      normalizeFormFields,
      renderFormBuilder,
      syncFormFromPayload,
      invalidateNodeCache,
      validation,
      listModule,
      showStatus,
    } = deps;

    function loadTemplateRevisions(docType) {
      if (!els.templateRevision) {
        return Promise.resolve();
      }
      state.isLoadingRevisions = true;
      if (els.reloadRevisionsBtn) {
        els.reloadRevisionsBtn.disabled = true;
        els.reloadRevisionsBtn.textContent = "Reloading...";
      }
      els.templateRevision.innerHTML = '<option value="">Auto (published/default)</option>';
      if (els.revisionNote) {
        els.revisionNote.textContent = "Loading revisions...";
      }
      validation.setGenerateButtonState();
      return api(`/templates/revisions?doc_type=${encodeURIComponent(docType)}`)
        .then((data) => {
          const revisions = Array.isArray(data.revisions) ? data.revisions : [];
          state.hasAccessibleRevision = revisions.length > 0;
          if (revisions.length === 0) {
            const option = document.createElement("option");
            option.value = "";
            option.textContent = "No accessible revisions available";
            option.disabled = true;
            els.templateRevision.appendChild(option);
            if (els.revisionNote) {
              els.revisionNote.textContent = "No accessible revisions found. Publish a template revision or request access.";
            }
            return;
          }

          revisions.forEach((rev) => {
            const revId = Number(rev.id || 0);
            if (revId <= 0) {
              return;
            }
            const option = document.createElement("option");
            option.value = String(revId);
            option.dataset.templateId = String(Number(rev.template_id || 0));
            const status = Number(rev.is_published || 0) === 1 ? "Published" : "Draft";
            option.textContent = `${rev.template_name || "Template"} - Rev ${rev.revision_no || "?"} - ${status}`;
            els.templateRevision.appendChild(option);
          });
          if (els.revisionNote) {
            els.revisionNote.textContent = `Loaded ${revisions.length} revision option(s).`;
          }
        })
        .catch((err) => {
          state.hasAccessibleRevision = false;
          if (els.revisionNote) {
            els.revisionNote.textContent = "Failed to load revisions. Check permissions or try again.";
          }
          throw err;
        })
        .finally(() => {
          state.isLoadingRevisions = false;
          if (els.reloadRevisionsBtn) {
            els.reloadRevisionsBtn.disabled = false;
            els.reloadRevisionsBtn.textContent = "Reload Revisions";
          }
          validation.setGenerateButtonState();
          loadFormSchemaForCurrentSelection().catch(() => {});
        });
    }

    function loadFormSchemaForCurrentSelection() {
      const docType = getDocType();
      const fallback = normalizeFormFields(fallbackSchemas[docType], docType);

      if (!els.templateRevision) {
        state.activeFormSchema = fallback;
        renderFormBuilder(state.activeFormSchema);
        invalidateNodeCache();
        syncFormFromPayload();
        return Promise.resolve();
      }

      const selected = els.templateRevision.selectedOptions && els.templateRevision.selectedOptions[0] ? els.templateRevision.selectedOptions[0] : null;
      const revisionId = Number((selected && selected.value) || els.templateRevision.value || 0);
      const templateId = Number((selected && selected.dataset && selected.dataset.templateId) || 0);

      if (revisionId <= 0 || templateId <= 0) {
        state.activeFormSchema = fallback;
        renderFormBuilder(state.activeFormSchema);
        invalidateNodeCache();
        syncFormFromPayload();
        return Promise.resolve();
      }

      return api(`/templates/${templateId}`)
        .then((data) => {
          const template = data && data.template ? data.template : {};
          const revisions = Array.isArray(template.revisions) ? template.revisions : [];
          const match = revisions.find((rev) => Number(rev && rev.id ? rev.id : 0) === revisionId) || null;
          const schema = match && match.schema_json ? match.schema_json : {};
          const fields = Array.isArray(schema.fields) ? schema.fields : [];
          state.activeFormSchema = normalizeFormFields(fields, docType);
          renderFormBuilder(state.activeFormSchema);
          invalidateNodeCache();
          syncFormFromPayload();
        })
        .catch(() => {
          state.activeFormSchema = fallback;
          renderFormBuilder(state.activeFormSchema);
          invalidateNodeCache();
          syncFormFromPayload();
        });
    }

    function generateDocument() {
      if (!els.docType || !els.templateRevision || !els.payload) {
        showStatus("Document generation controls are unavailable for this account.", "error");
        return;
      }
      if (state.isGenerating) {
        return;
      }

      const docType = getDocType();
      let payload;
      try {
        payload = JSON.parse((els.payload && els.payload.value) || "{}");
      } catch (err) {
        showStatus("Payload JSON must be valid.", "error");
        return;
      }

      const validationError = validation.validatePayload(payload);
      if (validationError) {
        showStatus(validationError, "error");
        return;
      }

      const revisionId = Number((els.templateRevision && els.templateRevision.value) || 0);
      if (revisionId > 0) {
        payload.template_revision_id = revisionId;
      }

      state.isGenerating = true;
      if (els.generateBtn) {
        els.generateBtn.textContent = "Generating...";
      }
      validation.setGenerateButtonState();

      api(`/documents/${docType}/generate`, {
        method: "POST",
        body: JSON.stringify(payload),
      })
        .then((data) => {
          showStatus(`${docType.toUpperCase()} generated successfully.`, "success");
          const pdfUrl = data.pdf_url || "";
          const trackingUrl = data.tracking_url || "";
          const selectedEngine = String(data.selected_engine || "tcpdf");
          const usedEngine = String(data.engine_used || selectedEngine);
          const fallbackReason = String(data.engine_fallback_reason || "");
          const trackingQrDataUri = String(data.tracking_qr_data_uri || "");
          const paymentQrDataUri = String(data.payment_qr_data_uri || "");
          const paymentUri = String(data.payment_uri || "");
          const engineNote = usedEngine !== selectedEngine
            ? `${usedEngine} (selected: ${selectedEngine}${fallbackReason ? `, reason: ${fallbackReason}` : ""})`
            : usedEngine;
          els.result.innerHTML = `
            <p><strong>Document ID:</strong> ${escapeHtml(String(data.document_id || ""))}</p>
            <p><strong>Tracking Code:</strong> ${escapeHtml(String(data.tracking_code || ""))}</p>
            <p><strong>Engine:</strong> ${escapeHtml(engineNote)}</p>
            <div class="cds-result-actions">
              ${pdfUrl ? `<a class="button" data-action="open-result-pdf" href="${escapeHtml(pdfUrl)}" target="_blank" rel="noopener">Download PDF</a>` : ""}
              ${data.document_id ? `<button type="button" class="button" data-action="delete-result-pdf" data-document-id="${escapeHtmlAttr(String(data.document_id))}">Delete PDF</button>` : ""}
              ${trackingUrl ? `<a class="button" href="${escapeHtml(trackingUrl)}" target="_blank" rel="noopener">Open Tracking Page</a>` : ""}
              ${trackingUrl ? `<button type="button" class="button" data-copy-value="${escapeHtmlAttr(trackingUrl)}">Copy Tracking URL</button>` : ""}
              ${paymentUri ? `<button type="button" class="button" data-copy-value="${escapeHtmlAttr(paymentUri)}">Copy Payment URI</button>` : ""}
            </div>
            <div class="cds-result-qr-wrap">
              ${trackingQrDataUri ? `
                <div class="cds-result-qr-card">
                  <p><strong>Tracking QR</strong></p>
                  <img src="${escapeHtmlAttr(trackingQrDataUri)}" alt="Tracking QR Code" />
                </div>` : ""}
              ${paymentQrDataUri ? `
                <div class="cds-result-qr-card">
                  <p><strong>Payment QR</strong></p>
                  <img src="${escapeHtmlAttr(paymentQrDataUri)}" alt="Payment QR Code" />
                </div>` : ""}
            </div>
          `;
          return listModule.refreshListFromControls(1);
        })
        .catch((err) => {
          validation.handleGenerationError(err);
        })
        .finally(() => {
          state.isGenerating = false;
          if (els.generateBtn) {
            els.generateBtn.textContent = "Generate Document";
          }
          validation.setGenerateButtonState();
        });
    }

    return {
      loadTemplateRevisions,
      loadFormSchemaForCurrentSelection,
      generateDocument,
    };
  }

  window.CDS_DOCS_GENERATION = {
    create: createGenerationModule,
  };
})(window);
