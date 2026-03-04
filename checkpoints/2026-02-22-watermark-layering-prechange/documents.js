(function () {
  "use strict";

  if (!window.CDS_ADMIN || window.CDS_ADMIN.page !== "cargo-docs-studio-documents") {
    return;
  }
  if (!window.CDS_API || typeof window.CDS_API.request !== "function") {
    return;
  }

  const els = {
    docType: document.getElementById("cds-doc-gen-type"),
    listFilter: document.getElementById("cds-doc-list-filter"),
    templateRevision: document.getElementById("cds-doc-template-revision"),
    reloadRevisionsBtn: document.getElementById("cds-reload-revisions"),
    revisionNote: document.getElementById("cds-doc-revision-note"),
    payload: document.getElementById("cds-doc-payload-json"),
    modeFormBtn: document.getElementById("cds-doc-mode-form"),
    modeJsonBtn: document.getElementById("cds-doc-mode-json"),
    formBuilder: document.getElementById("cds-doc-form-builder"),
    autoFixBtn: document.getElementById("cds-autofix-payload"),
    generateBtn: document.getElementById("cds-generate-document"),
    checklist: document.getElementById("cds-doc-required-checklist"),
    hints: document.getElementById("cds-doc-validation-hints"),
    status: document.getElementById("cds-doc-status"),
    result: document.getElementById("cds-doc-result"),
    documentsList: document.getElementById("cds-documents-list"),
    listSearch: document.getElementById("cds-doc-list-search"),
    listSearchBtn: document.getElementById("cds-doc-list-search-btn"),
    listMeta: document.getElementById("cds-doc-list-meta"),
    prevPageBtn: document.getElementById("cds-doc-prev-page"),
    nextPageBtn: document.getElementById("cds-doc-next-page"),
    pageInfo: document.getElementById("cds-doc-page-info"),
  };
  const state = {
    isGenerating: false,
    isLoadingRevisions: false,
    hasAccessibleRevision: true,
    listPage: 1,
    listPages: 1,
    listTotal: 0,
    listSearch: "",
    editorMode: "form",
    isSyncingPayload: false,
    isSyncingForm: false,
    activeFormSchema: [],
  };

  const payloadDefaults = {
    invoice: {
      client_name: "Preview Client",
      client_email: "preview@example.com",
      client_address: "123 Preview Street, Demo City",
      cargo_type: "Electronics",
      quantity: 2,
      taxable_value: 900.5,
      current_location: "Dubai Hub",
      bitcoin_enabled: true,
    },
    receipt: {
      client_name: "Preview Client",
      client_email: "preview@example.com",
      client_address: "123 Preview Street, Demo City",
      cargo_type: "Paid Cargo",
      quantity: 1,
      taxable_value: 500,
      current_location: "Collection Desk",
      bitcoin_enabled: true,
    },
    skr: {
      company_logo_url: "https://inds.soothingspotspa.care/wp-content/uploads/2026/02/WakalaNew.png",
      skr_company_name: "WAKALA MINERALS LIMITED",
      skr_company_rc: "1234567",
      skr_license_number: "77-7477",
      skr_company_phone: "+256 778 223 344",
      skr_company_email: "info@wakalaminerals.com",
      skr_company_address: "PLOT 32A KAMPALA ROAD KAMPALA UGANDA",
      custody_type: "SAFE CUSTODY",
      depositor_name: "Preview Client",
      client_email: "preview@example.com",
      client_address: "123 Preview Street, Demo City",
      deposit_number: "ESL20260205176",
      projected_days: 30,
      documented_custom_value: "T.B.A",
      represented_date: "",
      represented_by: "N/A",
      receiving_officer: "MR.KIMBUGWE FAISAL",
      reg_number: "ESL-A-205",
      content_description: "Raw Gold",
      quantity: 250,
      unit: "KGS",
      packages_number: 2,
      declared_value: 120000,
      origin_of_goods: "Uganda",
      deposit_type: "Bonded Warehouse",
      total_value: "TBA",
      insurance_rate: "1.5%",
      supporting_documents: "PRELIMINARY DOCUMENTATION\nCERTIFICATE OF ORIGIN\nCERTIFICATE OF OWNERSHIP\nEXPORT PERMIT",
      storage_fees_label: "PER DAY = $25.00",
      deposit_instructions: "Release only to authorized signatory.",
      date_label: "",
      skr_watermark_enabled: true,
      depositor_signature: "",
      additional_notes: "Goods held under bonded terms.",
      affidavit_text: "",
      issuer_name: "Kimbugwe Faisal",
      issuer_title: "ISSUING OFFICER LIMITED",
      stamp_label: "Official Stamp / Signature",
      bitcoin_enabled: false,
    },
  };

  const fallbackSchemas = {
    invoice: [
      { key: "client_name", label: "Client Name", type: "text", required: true },
      { key: "client_email", label: "Client Email", type: "email", required: true },
      { key: "client_address", label: "Client Address", type: "textarea", required: false },
      { key: "cargo_type", label: "Cargo Type", type: "text", required: true },
      { key: "quantity", label: "Quantity", type: "number", required: false },
      { key: "taxable_value", label: "Taxable Value", type: "number", required: false },
      { key: "destination", label: "Destination", type: "text", required: false },
      { key: "bitcoin_enabled", label: "Enable Bitcoin QR", type: "checkbox", required: false },
    ],
    receipt: [
      { key: "client_name", label: "Client Name", type: "text", required: true },
      { key: "client_email", label: "Client Email", type: "email", required: true },
      { key: "client_address", label: "Client Address", type: "textarea", required: false },
      { key: "receipt_number", label: "Receipt Number", type: "text", required: false },
      { key: "payment_method", label: "Payment Method", type: "text", required: false },
      { key: "payment_reference", label: "Payment Reference", type: "text", required: false },
      { key: "notes", label: "Notes", type: "textarea", required: false },
      { key: "bitcoin_enabled", label: "Enable Bitcoin QR", type: "checkbox", required: false },
    ],
    skr: [
      { key: "company_logo_url", label: "Company Logo URL", type: "url", required: false },
      { key: "skr_company_name", label: "Company Name", type: "text", required: false },
      { key: "skr_company_rc", label: "RC Number", type: "text", required: false },
      { key: "skr_license_number", label: "License Number", type: "text", required: false },
      { key: "skr_company_phone", label: "Company Phone", type: "text", required: false },
      { key: "skr_company_email", label: "Company Email", type: "email", required: false },
      { key: "skr_company_address", label: "Company Address", type: "textarea", required: false },
      { key: "depositor_name", label: "Depositor Name", type: "text", required: true },
      { key: "client_email", label: "Email Address", type: "email", required: true },
      { key: "client_address", label: "Depositor Address", type: "textarea", required: false },
      { key: "deposit_number", label: "Deposit Number", type: "text", required: false },
      { key: "custody_type", label: "Custody Type", type: "text", required: true },
      { key: "projected_days", label: "Projected Days of Custody", type: "number", required: false },
      { key: "documented_custom_value", label: "Documented Custom Value (US$)", type: "text", required: false },
      { key: "represented_date", label: "Represented Date", type: "date", required: false },
      { key: "represented_by", label: "Represented By", type: "text", required: false },
      { key: "receiving_officer", label: "Receiving Officer", type: "text", required: false },
      { key: "reg_number", label: "Reg Number", type: "text", required: false },
      { key: "skr_watermark_enabled", label: "Enable SKR Watermark", type: "checkbox", required: false },
      { key: "content_description", label: "Description of Contents", type: "text", required: true },
      { key: "quantity", label: "Quantity", type: "number", required: true },
      { key: "unit", label: "Unit", type: "text", required: true },
      { key: "packages_number", label: "Number of Packages", type: "number", required: false },
      { key: "origin_of_goods", label: "Origin of Goods", type: "text", required: false },
      { key: "declared_value", label: "Declared Value (USD)", type: "number", required: true },
      { key: "deposit_type", label: "Type of Deposit", type: "text", required: true },
      { key: "total_value", label: "Total Value", type: "text", required: false },
      { key: "insurance_rate", label: "Insurance Value/Rate", type: "text", required: false },
      { key: "supporting_documents", label: "Supporting Documents", type: "textarea", required: false },
      { key: "storage_fees_label", label: "CD Storage Fees", type: "text", required: false },
      { key: "depositor_signature", label: "Depositor's Signature", type: "textarea", required: false },
      { key: "date_label", label: "Date", type: "date", required: false },
      { key: "additional_notes", label: "Additional Information", type: "textarea", required: false },
      { key: "affidavit_text", label: "Affidavit Paragraph", type: "textarea", required: false },
      { key: "issuer_name", label: "Issuing Officer Name", type: "text", required: false },
      { key: "issuer_title", label: "Issuing Officer Title", type: "text", required: false },
      { key: "stamp_label", label: "Stamp Label", type: "text", required: false },
    ],
  };

  const api = window.CDS_API.request;
  const formatApiError = window.CDS_API.formatError;
  const getApiErrorFields = window.CDS_API.getErrorFields;
  const skrFormSections = [
    { key: "branding", title: "Branding", fields: ["company_logo_url"] },
    { key: "depositor", title: "Depositor Information", fields: ["depositor_name", "client_email", "client_address"] },
    { key: "custody", title: "Custody Details", fields: ["custody_type", "projected_days", "deposit_number"] },
    {
      key: "contents",
      title: "Contents Details",
      fields: ["content_description", "quantity", "unit", "packages_number", "declared_value", "origin_of_goods"],
    },
    { key: "deposit", title: "Deposit Details", fields: ["deposit_type", "storage_fees_label", "insurance_rate"] },
    { key: "docs", title: "Supporting Documents", fields: ["supporting_documents"] },
    { key: "instructions", title: "Deposit Instructions", fields: ["deposit_instructions"] },
    { key: "additional", title: "Additional Information", fields: ["additional_notes", "depositor_signature", "skr_watermark_enabled"] },
  ];
  const skrFieldUi = {
    custody_type: { control: "select", options: ["SAFE CUSTODY", "SINGLE CUSTODY", "JOINT CUSTODY"] },
    content_description: { control: "select", options: ["Precious Metal", "Raw Gold", "Mineral", "General Cargo"] },
    unit: { control: "select", options: ["KGS", "Grams", "Boxes", "Units"] },
    origin_of_goods: { control: "select", options: ["Uganda", "D.R.Congo", "Kenya", "Tanzania", "Rwanda"] },
    deposit_type: { control: "select", options: ["Bonded Warehouse", "Mineral", "General Deposit"] },
    supporting_documents: { control: "checkboxes", options: ["PRELIMINARY DOCUMENTATION", "CERTIFICATE OF ORIGIN", "CERTIFICATE OF OWNERSHIP", "EXPORT PERMIT"] },
  };

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
    return ["invoice", "receipt", "skr"].includes(value) ? value : "invoice";
  }

  function setPayloadDefaults(docType) {
    if (!els.payload) {
      return;
    }
    const payload = Object.assign({}, payloadDefaults[docType] || payloadDefaults.invoice);
    state.isSyncingPayload = true;
    els.payload.value = JSON.stringify(payload, null, 2);
    state.isSyncingPayload = false;
    syncFormFromPayload(payload);
  }

  function setEditorMode(mode) {
    state.editorMode = mode === "json" ? "json" : "form";
    const formMode = state.editorMode === "form";
    const isSkr = getDocType() === "skr";

    if (els.formBuilder) {
      if (!formMode) {
        els.formBuilder.style.display = "none";
      } else {
        els.formBuilder.style.display = isSkr ? "block" : "grid";
      }
    }
    if (els.payload) {
      els.payload.style.display = formMode ? "none" : "block";
    }
    if (els.checklist) {
      els.checklist.style.display = formMode && isSkr ? "none" : "block";
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

  function normalizeFormFields(fields, docType) {
    const fallback = fallbackSchemas[docType] || fallbackSchemas.invoice;
    const blockedKeysByDocType = {
      skr: new Set(["skr_watermark_url", "watermark_url"]),
    };
    const blockedKeys = blockedKeysByDocType[docType] || new Set();
    if (!Array.isArray(fields) || fields.length === 0) {
      return fallback;
    }

    const normalized = fields
      .map((f) => ({
        key: String((f && f.key) || "").trim(),
        label: String((f && f.label) || "").trim(),
        type: String((f && f.type) || "text").trim().toLowerCase(),
        required: !!(f && f.required),
      }))
      .filter((f) => f.key !== "" && !blockedKeys.has(f.key))
      .map((f) => ({
        key: f.key,
        label: f.label || f.key,
        type: ["text", "email", "number", "textarea", "date", "checkbox", "url"].includes(f.type) ? f.type : "text",
        required: f.required,
      }));

    const byKey = new Map();
    normalized.forEach((f) => byKey.set(f.key, f));
    fallback.forEach((f) => {
      if (!byKey.has(f.key)) {
        byKey.set(f.key, Object.assign({}, f));
      }
    });

    let merged = Array.from(byKey.values());
    if (docType === "skr") {
      merged = merged.filter((f) => f.key !== "skr_watermark_url" && f.key !== "watermark_url");
      const watermarkToggle = fallback.find((f) => f.key === "skr_watermark_enabled") || {
        key: "skr_watermark_enabled",
        label: "Enable SKR Watermark",
        type: "checkbox",
        required: false,
      };
      merged = merged.filter((f) => f.key !== "skr_watermark_enabled");
      merged.unshift(Object.assign({}, watermarkToggle));
    }

    return merged.length > 0 ? merged : fallback;
  }

  function renderFormBuilder(fields) {
    if (!els.formBuilder) {
      return;
    }
    if (getDocType() === "skr") {
      els.formBuilder.classList.add("cds-doc-form-builder-skr");
      renderSkrFormBuilder(fields);
      return;
    }
    els.formBuilder.classList.remove("cds-doc-form-builder-skr");
    const rows = fields
      .map((f) => {
        const req = f.required ? " <span class=\"required\">*</span>" : "";
        const requiredAttr = f.required ? "required" : "";
        if (f.type === "textarea") {
          return `<label for="cds-form-${escapeHtmlAttr(f.key)}">${escapeHtml(f.label)}${req}</label><textarea id="cds-form-${escapeHtmlAttr(f.key)}" data-payload-key="${escapeHtmlAttr(f.key)}" data-payload-type="${escapeHtmlAttr(f.type)}" ${requiredAttr}></textarea>`;
        }
        if (f.type === "checkbox") {
          return `<label class="cds-form-checkbox"><input id="cds-form-${escapeHtmlAttr(f.key)}" type="checkbox" data-payload-key="${escapeHtmlAttr(f.key)}" data-payload-type="${escapeHtmlAttr(f.type)}" /> ${escapeHtml(f.label)}${req}</label><div></div>`;
        }
        return `<label for="cds-form-${escapeHtmlAttr(f.key)}">${escapeHtml(f.label)}${req}</label><input id="cds-form-${escapeHtmlAttr(f.key)}" type="${escapeHtmlAttr(f.type)}" data-payload-key="${escapeHtmlAttr(f.key)}" data-payload-type="${escapeHtmlAttr(f.type)}" ${requiredAttr} />`;
      })
      .join("");
    els.formBuilder.innerHTML = rows || "<p class='description'>No form fields defined in schema.</p>";
  }

  function renderSkrFormBuilder(fields) {
    els.formBuilder.classList.add("cds-doc-form-builder-skr");
    const byKey = new Map((fields || []).map((f) => [f.key, f]));
    const sectionHtml = skrFormSections
      .map((section) => {
        const items = section.fields
          .map((key) => byKey.get(key) || getForcedSkrField(key))
          .filter(Boolean)
          .map((f) => renderSkrField(f))
          .join("");
        if (!items) {
          return "";
        }
        return `<section class="cds-skr-section"><h4>${escapeHtml(section.title)}</h4><div class="cds-skr-grid">${items}</div></section>`;
      })
      .join("");

    els.formBuilder.innerHTML = `<div class="cds-skr-form"><h3 class="cds-skr-title">Safe Keeping Receipt Generator</h3>${sectionHtml}</div>`;
  }

  function getForcedSkrField(key) {
    const k = String(key || "").trim();
    if (k === "company_logo_url") {
      return { key: "company_logo_url", label: "Company Logo URL", type: "url", required: false };
    }
    return null;
  }

  function renderSkrField(f) {
    const ui = skrFieldUi[f.key] || {};
    const req = f.required ? " <span class=\"required\">*</span>" : "";
    const requiredAttr = f.required ? "required" : "";
    const key = escapeHtmlAttr(f.key);
    const type = escapeHtmlAttr(f.type || "text");
    const label = escapeHtml(f.label || f.key);

    if (ui.control === "checkboxes") {
      const opts = Array.isArray(ui.options) ? ui.options : [];
      const checks = opts
        .map(
          (opt, idx) =>
            `<label class="cds-skr-check"><input type="checkbox" data-checkbox-group="${key}" data-opt="${escapeHtmlAttr(opt)}" ${idx === 0 ? "checked" : ""} /> ${escapeHtml(opt)}</label>`
        )
        .join("");
      return `<div class="cds-skr-item cds-skr-item-wide"><label for="cds-form-${key}">${label}${req}</label><input id="cds-form-${key}" type="hidden" data-payload-key="${key}" data-payload-type="${type}" ${requiredAttr} /><div class="cds-skr-check-row">${checks}</div></div>`;
    }
    if (f.type === "checkbox") {
      return `<div class="cds-skr-item cds-skr-item-wide"><label class="cds-form-checkbox"><input id="cds-form-${key}" type="checkbox" data-payload-key="${key}" data-payload-type="checkbox" /> ${label}${req}</label></div>`;
    }
    if (ui.control === "select") {
      const opts = (ui.options || []).map((opt) => `<option value="${escapeHtmlAttr(opt)}">${escapeHtml(opt)}</option>`).join("");
      return `<div class="cds-skr-item"><label for="cds-form-${key}">${label}${req}</label><select id="cds-form-${key}" data-payload-key="${key}" data-payload-type="${type}" ${requiredAttr}>${opts}</select></div>`;
    }
    if (f.type === "textarea") {
      return `<div class="cds-skr-item cds-skr-item-wide"><label for="cds-form-${key}">${label}${req}</label><textarea id="cds-form-${key}" data-payload-key="${key}" data-payload-type="${type}" ${requiredAttr}></textarea></div>`;
    }
    return `<div class="cds-skr-item"><label for="cds-form-${key}">${label}${req}</label><input id="cds-form-${key}" type="${escapeHtmlAttr(f.type || "text")}" data-payload-key="${key}" data-payload-type="${type}" ${requiredAttr} /></div>`;
  }

  function coerceValueByType(raw, type) {
    if (type === "checkbox") {
      return !!raw;
    }
    if (type === "number") {
      const num = Number(raw);
      return Number.isFinite(num) ? num : 0;
    }
    return String(raw == null ? "" : raw);
  }

  function syncPayloadFromForm() {
    if (!els.formBuilder || !els.payload || state.isSyncingForm) {
      return;
    }

    let payload;
    try {
      payload = JSON.parse(els.payload.value || "{}");
    } catch (err) {
      payload = {};
    }

    state.isSyncingPayload = true;
    const nodes = Array.from(els.formBuilder.querySelectorAll("[data-payload-key]"));
    nodes.forEach((node) => {
      const key = node.getAttribute("data-payload-key");
      const type = node.getAttribute("data-payload-type") || "text";
      if (!key) {
        return;
      }

      if (type === "checkbox") {
        payload[key] = !!node.checked;
        return;
      }
      payload[key] = coerceValueByType(node.value, type);
    });
    const checkboxGroups = Array.from(els.formBuilder.querySelectorAll("[data-checkbox-group]"));
    if (checkboxGroups.length > 0) {
      const grouped = {};
      checkboxGroups.forEach((node) => {
        const g = node.getAttribute("data-checkbox-group");
        if (!g) {
          return;
        }
        grouped[g] = grouped[g] || [];
        if (node.checked) {
          grouped[g].push(String(node.getAttribute("data-opt") || "").trim());
        }
      });
      Object.keys(grouped).forEach((groupKey) => {
        payload[groupKey] = grouped[groupKey].filter(Boolean).join("\n");
      });
    }
    els.payload.value = JSON.stringify(payload, null, 2);
    state.isSyncingPayload = false;

    renderPayloadHints(payload);
    setGenerateButtonState();
  }

  function syncFormFromPayload(payloadInput) {
    if (!els.formBuilder || state.isSyncingPayload) {
      return;
    }
    const payload = payloadInput && typeof payloadInput === "object" ? payloadInput : tryParsePayload();
    if (!payload || typeof payload !== "object") {
      return;
    }

    state.isSyncingForm = true;
    const nodes = Array.from(els.formBuilder.querySelectorAll("[data-payload-key]"));
    nodes.forEach((node) => {
      const key = node.getAttribute("data-payload-key");
      const type = node.getAttribute("data-payload-type") || "text";
      if (!key) {
        return;
      }
      const value = Object.prototype.hasOwnProperty.call(payload, key) ? payload[key] : "";
      if (type === "checkbox") {
        node.checked = !!value;
      } else {
        node.value = value == null ? "" : String(value);
      }
    });
    const checkboxGroups = Array.from(els.formBuilder.querySelectorAll("[data-checkbox-group]"));
    checkboxGroups.forEach((node) => {
      const g = node.getAttribute("data-checkbox-group");
      const opt = String(node.getAttribute("data-opt") || "");
      if (!g) {
        return;
      }
      const raw = Object.prototype.hasOwnProperty.call(payload, g) ? String(payload[g] || "") : "";
      const selected = raw
        .split("\n")
        .map((x) => String(x || "").trim())
        .filter(Boolean);
      node.checked = selected.includes(opt);
    });
    state.isSyncingForm = false;
  }

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
    setGenerateButtonState();
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
        setGenerateButtonState();
        loadFormSchemaForCurrentSelection().catch(() => {});
      });
  }

  function loadFormSchemaForCurrentSelection() {
    const docType = getDocType();
    const fallback = normalizeFormFields(fallbackSchemas[docType], docType);

    if (!els.templateRevision) {
      state.activeFormSchema = fallback;
      renderFormBuilder(state.activeFormSchema);
      syncFormFromPayload();
      return Promise.resolve();
    }

    const selected = els.templateRevision.selectedOptions && els.templateRevision.selectedOptions[0] ? els.templateRevision.selectedOptions[0] : null;
    const revisionId = Number((selected && selected.value) || els.templateRevision.value || 0);
    const templateId = Number((selected && selected.dataset && selected.dataset.templateId) || 0);

    if (revisionId <= 0 || templateId <= 0) {
      state.activeFormSchema = fallback;
      renderFormBuilder(state.activeFormSchema);
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
        syncFormFromPayload();
      })
      .catch(() => {
        state.activeFormSchema = fallback;
        renderFormBuilder(state.activeFormSchema);
        syncFormFromPayload();
      });
  }

  function getListFilterValue() {
    if (!els.listFilter) {
      return "";
    }
    return String(els.listFilter.value || "").toLowerCase();
  }

  function loadRecentDocuments(docType, page, searchTerm) {
    if (!els.documentsList) {
      return Promise.resolve();
    }
    els.documentsList.innerHTML = "<p>Loading...</p>";
    const requestedPage = Number(page || state.listPage || 1);
    const safePage = requestedPage > 0 ? requestedPage : 1;
    const search = typeof searchTerm === "string" ? searchTerm.trim() : state.listSearch;
    state.listSearch = search;
    const key = String(docType || "").toLowerCase();
    const params = [`limit=20`, `page=${encodeURIComponent(String(safePage))}`];
    if (key && key !== "all") {
      params.push(`doc_type=${encodeURIComponent(docType)}`);
    }
    if (search) {
      params.push(`search=${encodeURIComponent(search)}`);
    }
    const query = `/documents?${params.join("&")}`;
    return api(query).then((data) => {
      const docs = Array.isArray(data.documents) ? data.documents : [];
      const pagination = data.pagination || {};
      state.listPage = Number(pagination.page || safePage || 1);
      state.listPages = Number(pagination.pages || 1);
      state.listTotal = Number(pagination.total || docs.length || 0);
      renderPagination();
      renderListMeta();
      if (docs.length === 0) {
        els.documentsList.innerHTML = "<p>No documents yet.</p>";
        return;
      }
      const rows = docs
        .map((d) => {
          const link = d.pdf_url ? `<a href="${escapeHtml(d.pdf_url)}" target="_blank" rel="noopener">Open PDF</a>` : "No file";
          const trackingCode = String(d.tracking_code || "");
          return `<tr>
            <td>${escapeHtml(String(d.id || ""))}</td>
            <td>${escapeHtml(String(d.doc_type_key || ""))}</td>
            <td>${trackingCode ? escapeHtml(trackingCode) : "-"}</td>
            <td>${escapeHtml(String(d.status || ""))}</td>
            <td>${escapeHtml(String(d.created_at || ""))}</td>
            <td>${link}</td>
          </tr>`;
        })
        .join("");
      els.documentsList.innerHTML = `<table class="widefat striped">
        <thead><tr><th>ID</th><th>Type</th><th>Tracking</th><th>Status</th><th>Created</th><th>PDF</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>`;
    });
  }

  function renderPagination() {
    if (els.pageInfo) {
      els.pageInfo.textContent = `Page ${state.listPage} of ${state.listPages}`;
    }
    if (els.prevPageBtn) {
      els.prevPageBtn.disabled = state.listPage <= 1;
    }
    if (els.nextPageBtn) {
      els.nextPageBtn.disabled = state.listPage >= state.listPages;
    }
  }

  function renderListMeta() {
    if (!els.listMeta) {
      return;
    }
    const filter = getListFilterValue() || "all";
    const search = state.listSearch ? `, search: "${state.listSearch}"` : "";
    els.listMeta.textContent = `Total: ${state.listTotal} documents (filter: ${filter}${search}).`;
  }

  function refreshListFromControls(page) {
    const filter = getListFilterValue() || "all";
    const search = els.listSearch ? String(els.listSearch.value || "").trim() : state.listSearch;
    const targetPage = Number(page || 1);
    return loadRecentDocuments(filter, targetPage, search);
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

    const validationError = validatePayload(payload);
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
    setGenerateButtonState();

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
            ${pdfUrl ? `<a class="button" href="${escapeHtml(pdfUrl)}" target="_blank" rel="noopener">Download PDF</a>` : ""}
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
        return refreshListFromControls(1);
      })
      .catch((err) => {
        handleGenerationError(err);
      })
      .finally(() => {
        state.isGenerating = false;
        if (els.generateBtn) {
          els.generateBtn.textContent = "Generate Document";
        }
        setGenerateButtonState();
      });
  }

  function handleGenerationError(err) {
    showStatus(formatApiError(err), "error");

    const fields = getApiErrorFields(err);
    if (fields.length > 0) {
      renderApiFieldErrors(fields);
      focusPayloadField(fields);
      return;
    }

    const parsed = tryParsePayload();
    if (parsed !== null) {
      renderPayloadHints(parsed);
    }
  }

  function renderApiFieldErrors(fields) {
    if (!els.hints) {
      return;
    }

    const items = fields
      .map((f) => {
        const field = escapeHtml(String((f && f.field) || "payload"));
        const msg = escapeHtml(String((f && f.message) || "Invalid value."));
        return `<li><code>${field}</code>: ${msg}</li>`;
      })
      .join("");

    els.hints.innerHTML = `<ul><li><strong>Server validation failed:</strong></li>${items}</ul>`;
  }

  function focusPayloadField(fields) {
    if (!els.payload || !Array.isArray(fields) || fields.length === 0) {
      return;
    }

    const firstField = String((fields[0] && fields[0].field) || "").trim();
    if (!firstField) {
      return;
    }

    const raw = String(els.payload.value || "");
    const pattern = new RegExp(`"${escapeRegExp(firstField)}"\\s*:`);
    const match = pattern.exec(raw);
    if (!match) {
      return;
    }

    const start = match.index + 1;
    const end = start + firstField.length;
    els.payload.focus();
    if (typeof els.payload.setSelectionRange === "function") {
      els.payload.setSelectionRange(start, end);
    }
  }

  function escapeRegExp(value) {
    return String(value).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  }

  function validatePayload(payload) {
    const errors = getPayloadValidationErrors(payload);
    return errors.length > 0 ? errors[0] : "";
  }

  function getActiveValidationFields() {
    const docType = getDocType();
    const source =
      Array.isArray(state.activeFormSchema) && state.activeFormSchema.length > 0
        ? state.activeFormSchema
        : normalizeFormFields(fallbackSchemas[docType], docType);
    let required = source.filter((f) => !!(f && f.required));
    if (docType === "skr") {
      const visible = new Set(
        skrFormSections
          .flatMap((section) => (Array.isArray(section.fields) ? section.fields : []))
          .map((k) => String(k || "").trim())
          .filter(Boolean)
      );
      required = required.filter((f) => visible.has(String((f && f.key) || "").trim()));
    }
    return required;
  }

  function isMissingRequiredValue(value, type) {
    if (type === "checkbox") {
      return !value;
    }
    if (type === "number") {
      if (value === null || value === undefined) {
        return true;
      }
      const text = String(value).trim();
      if (text === "") {
        return true;
      }
      return !Number.isFinite(Number(value));
    }
    return String(value == null ? "" : value).trim() === "";
  }

  function getPayloadValidationErrors(payload) {
    const errors = [];
    const required = getActiveValidationFields();
    required.forEach((field) => {
      const key = String(field.key || "");
      const type = String(field.type || "text").toLowerCase();
      const value = payload && Object.prototype.hasOwnProperty.call(payload, key) ? payload[key] : "";
      if (isMissingRequiredValue(value, type)) {
        errors.push(`${key} is required.`);
        return;
      }
      if (type === "email") {
        const email = String(value || "").trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
          errors.push(`${key} must be a valid email address.`);
        }
      }
    });

    return errors;
  }

  function renderPayloadHints(payload) {
    if (!els.hints) {
      return;
    }
    const errors = getPayloadValidationErrors(payload);
    if (errors.length === 0) {
      els.hints.innerHTML = '<p class="description">Payload looks valid.</p>';
      return;
    }
    const items = errors.map((e) => `<li>${escapeHtml(e)}</li>`).join("");
    els.hints.innerHTML = `<ul><li><strong>Payload issues:</strong></li>${items}</ul>`;
  }

  function renderRequiredChecklist(payload) {
    if (!els.checklist) {
      return;
    }
    const items = getActiveValidationFields().map((field) => {
      const key = String(field.key || "");
      const type = String(field.type || "text").toLowerCase();
      const value = payload && Object.prototype.hasOwnProperty.call(payload, key) ? payload[key] : "";
      let ok = !isMissingRequiredValue(value, type);
      if (ok && type === "email") {
        ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || "").trim());
      }
      return { label: key, ok };
    });

    const html = items
      .map((i) => `<li class="${i.ok ? "ok" : "bad"}"><span class="dot"></span>${escapeHtml(i.label)}</li>`)
      .join("");

    els.checklist.innerHTML = `<p><strong>Required fields</strong></p><ul>${html}</ul>`;
  }

  function setGenerateButtonState() {
    if (!els.generateBtn || !els.payload) {
      return;
    }

    if (state.isGenerating) {
      els.generateBtn.disabled = true;
      els.generateBtn.title = "Generation in progress...";
      return;
    }

    const parsed = tryParsePayload();
    if (parsed === null) {
      els.generateBtn.disabled = true;
      els.generateBtn.title = "Payload JSON must be valid before generating.";
      renderRequiredChecklist({});
      return;
    }

    const errors = getPayloadValidationErrors(parsed);
    if (errors.length > 0) {
      els.generateBtn.disabled = true;
      els.generateBtn.title = errors[0];
      return;
    }

    els.generateBtn.disabled = false;
    els.generateBtn.title = "";
    renderRequiredChecklist(parsed);
  }

  function tryParsePayload() {
    try {
      return JSON.parse((els.payload && els.payload.value) || "{}");
    } catch (err) {
      return null;
    }
  }

  function autoFixPayload() {
    if (!els.payload) {
      return;
    }
    const docType = getDocType();
    const defaults = Object.assign({}, payloadDefaults[docType] || payloadDefaults.invoice);
    let payload = tryParsePayload();

    if (payload === null || typeof payload !== "object" || Array.isArray(payload)) {
      payload = defaults;
      state.isSyncingPayload = true;
      els.payload.value = JSON.stringify(payload, null, 2);
      state.isSyncingPayload = false;
      syncFormFromPayload(payload);
      renderPayloadHints(payload);
      setGenerateButtonState();
      showStatus("Payload was invalid JSON and has been reset to defaults.", "success");
      return;
    }

    const required = getActiveValidationFields();
    required.forEach((field) => {
      const key = String(field.key || "");
      const type = String(field.type || "text").toLowerCase();
      const current = Object.prototype.hasOwnProperty.call(payload, key) ? payload[key] : "";
      if (isMissingRequiredValue(current, type)) {
        payload[key] = Object.prototype.hasOwnProperty.call(defaults, key) ? defaults[key] : current;
      }
      if (type === "email") {
        const email = String(payload[key] || "").trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
          payload[key] = Object.prototype.hasOwnProperty.call(defaults, key) ? defaults[key] : email;
        }
      }
    });

    state.isSyncingPayload = true;
    els.payload.value = JSON.stringify(payload, null, 2);
    state.isSyncingPayload = false;
    syncFormFromPayload(payload);
    renderPayloadHints(payload);
    setGenerateButtonState();
    showStatus("Payload auto-fix applied.", "success");
  }

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function escapeHtmlAttr(text) {
    return escapeHtml(text).replace(/`/g, "&#096;");
  }

  function copyTextToClipboard(value) {
    const text = String(value || "");
    if (!text) {
      return Promise.reject(new Error("Nothing to copy."));
    }

    if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
      return navigator.clipboard.writeText(text);
    }

    return new Promise((resolve, reject) => {
      const input = document.createElement("textarea");
      input.value = text;
      input.setAttribute("readonly", "readonly");
      input.style.position = "absolute";
      input.style.left = "-9999px";
      document.body.appendChild(input);
      input.select();
      try {
        const ok = document.execCommand("copy");
        document.body.removeChild(input);
        if (!ok) {
          reject(new Error("Copy failed."));
          return;
        }
        resolve();
      } catch (err) {
        document.body.removeChild(input);
        reject(err);
      }
    });
  }

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
      setGenerateButtonState();
      Promise.all([loadTemplateRevisions(docType), refreshListFromControls(1)]).catch((err) => showStatus(formatApiError(err), "error"));
    });
  }

  if (els.templateRevision) {
    els.templateRevision.addEventListener("change", () => {
      loadFormSchemaForCurrentSelection().catch((err) => showStatus(formatApiError(err), "error"));
    });
  }

  if (els.listFilter) {
    els.listFilter.addEventListener("change", () => {
      refreshListFromControls(1).catch((err) => showStatus(formatApiError(err), "error"));
    });
  }

  if (els.listSearchBtn) {
    els.listSearchBtn.addEventListener("click", () => {
      refreshListFromControls(1).catch((err) => showStatus(formatApiError(err), "error"));
    });
  }

  if (els.listSearch) {
    els.listSearch.addEventListener("keydown", (event) => {
      if (event.key !== "Enter") {
        return;
      }
      event.preventDefault();
      refreshListFromControls(1).catch((err) => showStatus(formatApiError(err), "error"));
    });
  }

  if (els.prevPageBtn) {
    els.prevPageBtn.addEventListener("click", () => {
      if (state.listPage <= 1) {
        return;
      }
      refreshListFromControls(state.listPage - 1).catch((err) => showStatus(formatApiError(err), "error"));
    });
  }

  if (els.nextPageBtn) {
    els.nextPageBtn.addEventListener("click", () => {
      if (state.listPage >= state.listPages) {
        return;
      }
      refreshListFromControls(state.listPage + 1).catch((err) => showStatus(formatApiError(err), "error"));
    });
  }

  if (els.generateBtn) {
    els.generateBtn.addEventListener("click", generateDocument);
  }

  if (els.reloadRevisionsBtn) {
    els.reloadRevisionsBtn.addEventListener("click", () => {
      const docType = getDocType();
      loadTemplateRevisions(docType).catch((err) => showStatus(formatApiError(err), "error"));
    });
  }

  if (els.autoFixBtn) {
    els.autoFixBtn.addEventListener("click", autoFixPayload);
  }

  if (els.modeFormBtn) {
    els.modeFormBtn.addEventListener("click", () => setEditorMode("form"));
  }
  if (els.modeJsonBtn) {
    els.modeJsonBtn.addEventListener("click", () => setEditorMode("json"));
  }

  if (els.formBuilder) {
    els.formBuilder.addEventListener("input", syncPayloadFromForm);
    els.formBuilder.addEventListener("change", syncPayloadFromForm);
  }

  if (els.result) {
    els.result.addEventListener("click", (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }
      const copyValue = target.getAttribute("data-copy-value");
      if (!copyValue) {
        return;
      }

      copyTextToClipboard(copyValue)
        .then(() => showStatus("Copied to clipboard.", "success"))
        .catch(() => showStatus("Failed to copy value.", "error"));
    });
  }

  if (els.payload) {
    els.payload.addEventListener("input", () => {
      if (state.isSyncingPayload) {
        return;
      }
      const parsed = tryParsePayload();
      if (parsed === null) {
        if (els.hints) {
          els.hints.innerHTML = "<ul><li><strong>Payload issues:</strong></li><li>Payload JSON must be valid.</li></ul>";
        }
        setGenerateButtonState();
        return;
      }
      renderPayloadHints(parsed);
      syncFormFromPayload(parsed);
      setGenerateButtonState();
    });
  }

  if (els.docType) {
    const initialType = getDocType();
    if (els.listFilter) {
      els.listFilter.value = initialType;
    }
    setPayloadDefaults(initialType);
    setEditorMode("form");
    state.activeFormSchema = normalizeFormFields(fallbackSchemas[initialType], initialType);
    renderFormBuilder(state.activeFormSchema);
    syncFormFromPayload(payloadDefaults[initialType] || payloadDefaults.invoice);
    renderPayloadHints(payloadDefaults[initialType] || payloadDefaults.invoice);
    renderRequiredChecklist(payloadDefaults[initialType] || payloadDefaults.invoice);
    state.hasAccessibleRevision = true;
    setGenerateButtonState();
    Promise.all([loadTemplateRevisions(initialType), refreshListFromControls(1)]).catch((err) => showStatus(formatApiError(err), "error"));
  } else {
    if (els.listFilter) {
      els.listFilter.value = "all";
    }
    refreshListFromControls(1).catch((err) => showStatus(formatApiError(err), "error"));
  }
})();
