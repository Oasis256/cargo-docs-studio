(function (window) {
  "use strict";

  function bindFormEvents(deps) {
    const {
      els,
      state,
      getDocType,
      setEditorMode,
      setPayloadDefaults,
      generation,
      listModule,
      validation,
      payloadSync,
      showStatus,
      formatApiError,
    } = deps;

    if (els.docType) {
      els.docType.addEventListener("change", () => {
        const docType = getDocType();
        if (els.listFilter) {
          els.listFilter.value = docType;
        }
        setEditorMode(state.editorMode);
        setPayloadDefaults(docType);
        if (els.result) {
          els.result.innerHTML = "";
        }
        validation.setGenerateButtonState();
        Promise.all([generation.loadTemplateRevisions(docType), listModule.refreshListFromControls(1)]).catch((err) => showStatus(formatApiError(err), "error"));
      });
    }

    if (els.templateRevision) {
      els.templateRevision.addEventListener("change", () => {
        generation.loadFormSchemaForCurrentSelection().catch((err) => showStatus(formatApiError(err), "error"));
      });
    }

    if (els.generateBtn) {
      els.generateBtn.addEventListener("click", generation.generateDocument);
    }

    if (els.reloadRevisionsBtn) {
      els.reloadRevisionsBtn.addEventListener("click", () => {
        const docType = getDocType();
        generation.loadTemplateRevisions(docType).catch((err) => showStatus(formatApiError(err), "error"));
      });
    }

    if (els.autoFixBtn) {
      els.autoFixBtn.addEventListener("click", () => validation.autoFixPayload());
    }

    if (els.modeFormBtn) {
      els.modeFormBtn.addEventListener("click", () => setEditorMode("form"));
    }
    if (els.modeJsonBtn) {
      els.modeJsonBtn.addEventListener("click", () => setEditorMode("json"));
    }

    if (els.formBuilder) {
      const syncFromForm = () =>
        payloadSync.syncPayloadFromForm({
          renderPayloadHints: validation.renderPayloadHints,
          setGenerateButtonState: validation.setGenerateButtonState,
        });
      els.formBuilder.addEventListener("input", syncFromForm);
      els.formBuilder.addEventListener("change", syncFromForm);
    }

    if (els.payload) {
      els.payload.addEventListener("input", () => {
        if (state.isSyncingPayload) {
          return;
        }
        const parsed = payloadSync.tryParsePayload();
        if (parsed === null) {
          if (els.hints) {
            els.hints.innerHTML = "<ul><li><strong>Payload issues:</strong></li><li>Payload JSON must be valid.</li></ul>";
          }
          validation.setGenerateButtonState();
          return;
        }
        validation.renderPayloadHints(parsed);
        payloadSync.syncFormFromPayload(parsed);
        validation.setGenerateButtonState();
      });
    }
  }

  function bootstrapInitialState(deps) {
    const {
      els,
      state,
      getDocType,
      setEditorMode,
      setPayloadDefaults,
      generation,
      listModule,
      validation,
      payloadSync,
      formRenderer,
      payloadDefaults,
      fallbackSchemas,
      showStatus,
      formatApiError,
    } = deps;

    if (els.docType) {
      const initialType = getDocType();
      if (els.listFilter) {
        els.listFilter.value = initialType;
      }
      setPayloadDefaults(initialType);
      setEditorMode("form");
      state.activeFormSchema = formRenderer.normalizeFormFields(fallbackSchemas[initialType], initialType);
      formRenderer.renderFormBuilder(state.activeFormSchema);
      payloadSync.invalidateNodeCache();
      payloadSync.syncFormFromPayload(payloadDefaults[initialType] || payloadDefaults.invoice);
      validation.renderPayloadHints(payloadDefaults[initialType] || payloadDefaults.invoice);
      validation.renderRequiredChecklist(payloadDefaults[initialType] || payloadDefaults.invoice);
      state.hasAccessibleRevision = true;
      validation.setGenerateButtonState();
      Promise.all([generation.loadTemplateRevisions(initialType), listModule.refreshListFromControls(1)]).catch((err) => showStatus(formatApiError(err), "error"));
      return;
    }

    if (els.listFilter) {
      els.listFilter.value = "all";
    }
    listModule.refreshListFromControls(1).catch((err) => showStatus(formatApiError(err), "error"));
  }

  window.CDS_DOCS_EVENTS_FORM = {
    bindFormEvents,
    bootstrapInitialState,
  };
})(window);
