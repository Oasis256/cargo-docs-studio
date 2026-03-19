(function () {
  "use strict";

  if (!window.CDS_ADMIN || window.CDS_ADMIN.page !== "cargo-docs-studio-documents") {
    return;
  }
  if (!window.CDS_API || typeof window.CDS_API.request !== "function") {
    return;
  }

  const els = window.CDS_DOCS_STATE.getElements(document);
  const state = window.CDS_DOCS_STATE.createState();
  const payloadDefaults = window.CDS_DOCS_DEFAULTS.payloadDefaults;
  const fallbackSchemas = window.CDS_DOCS_DEFAULTS.fallbackSchemas;
  const {
    skrFormSections,
    invoiceFormSections,
    receiptFormSections,
    spaFormSections,
    invoiceFieldUi,
    receiptFieldUi,
    skrFieldUi,
  } = window.CDS_DOCS_SECTIONS;

  const api = window.CDS_API.request;
  const formatApiError = window.CDS_API.formatError;
  const getApiErrorFields = window.CDS_API.getErrorFields;
  function showStatus(message, type) {
    if (!els.status) {
      return;
    }
    els.status.className = "notice inline";
    els.status.classList.add(type === "error" ? "notice-error" : "notice-success");
    els.status.style.display = "block";
    els.status.querySelector("p").textContent = message;
  }

  function getDocType() {
    const value = (els.docType && els.docType.value) || "invoice";
    return ["invoice", "receipt", "skr", "spa"].includes(value) ? value : "invoice";
  }

  function setPayloadDefaults(docType) {
    if (!els.payload) {
      return;
    }
    const payload = Object.assign({}, payloadDefaults[docType] || payloadDefaults.invoice);
    state.isSyncingPayload = true;
    els.payload.value = JSON.stringify(payload, null, 2);
    state.isSyncingPayload = false;
    payloadSync.syncFormFromPayload(payload);
  }

  function setEditorMode(mode) {
    state.editorMode = mode === "json" ? "json" : "form";
    const formMode = state.editorMode === "form";
    const docType = getDocType();
    const isStructured = docType === "skr" || docType === "invoice" || docType === "receipt";

    if (els.formBuilder) {
      if (!formMode) {
        els.formBuilder.style.display = "none";
      } else {
        els.formBuilder.style.display = isStructured ? "block" : "grid";
      }
    }
    if (els.payload) {
      els.payload.style.display = formMode ? "none" : "block";
    }
    if (els.checklist) {
      els.checklist.style.display = formMode && isStructured ? "none" : "block";
    }
    if (els.hints) {
      els.hints.style.display = "block";
    }
    if (els.modeFormBtn) {
      els.modeFormBtn.classList.toggle("button-primary", formMode);
      els.modeFormBtn.classList.toggle("button", !formMode);
    }
    if (els.modeJsonBtn) {
      els.modeJsonBtn.classList.toggle("button-primary", !formMode);
      els.modeJsonBtn.classList.toggle("button", formMode);
    }
  }

  const formRenderer = window.CDS_DOCS_FORM_RENDERER.create({
    els,
    fallbackSchemas,
    getDocType,
    skrFormSections,
    invoiceFormSections,
    receiptFormSections,
    spaFormSections,
    skrFieldUi,
    invoiceFieldUi,
    receiptFieldUi,
  });

  const payloadSync = window.CDS_DOCS_PAYLOAD_SYNC.create({
    els,
    state,
    getDocType,
  });

  const validation = window.CDS_DOCS_VALIDATION.create({
    els,
    state,
    payloadDefaults,
    fallbackSchemas,
    skrFormSections,
    receiptFormSections,
    getDocType,
    normalizeFormFields: formRenderer.normalizeFormFields,
    tryParsePayload: payloadSync.tryParsePayload,
    syncFormFromPayload: payloadSync.syncFormFromPayload,
    showStatus,
    formatApiError,
    getApiErrorFields,
  });

  const listModule = window.CDS_DOCS_LIST.create({
    els,
    state,
    api,
    showStatus,
    formatApiError,
  });

  const generation = window.CDS_DOCS_GENERATION.create({
    els,
    state,
    api,
    getDocType,
    formatApiError,
    fallbackSchemas,
    normalizeFormFields: formRenderer.normalizeFormFields,
    renderFormBuilder: formRenderer.renderFormBuilder,
    syncFormFromPayload: payloadSync.syncFormFromPayload,
    invalidateNodeCache: payloadSync.invalidateNodeCache,
    validation,
    listModule,
    showStatus,
  });

  const events = window.CDS_DOCS_EVENTS.create({
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
    api,
    copyTextToClipboard: window.CDS_DOCS_UTILS.copyTextToClipboard,
  });

  events.bindAll();
  events.bootstrapInitialState();
})();
