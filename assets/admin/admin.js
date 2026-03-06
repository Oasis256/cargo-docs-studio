(function () {
  "use strict";

  if (!window.CDS_ADMIN || window.CDS_ADMIN.page !== "cargo-docs-studio-templates") {
    return;
  }
  if (!window.CDS_API || typeof window.CDS_API.request !== "function") {
    return;
  }

  const state = {
    selectedTemplateId: null,
    selectedRevisionId: null,
    loadedRevisions: [],
  };

  const els = {
    docType: document.getElementById("cds-doc-type"),
    templateName: document.getElementById("cds-template-name"),
    createBtn: document.getElementById("cds-create-template"),
    list: document.getElementById("cds-template-list"),
    schemaJson: document.getElementById("cds-schema-json"),
    themeJson: document.getElementById("cds-theme-json"),
    themeBuilder: document.getElementById("cds-theme-builder"),
    layoutJson: document.getElementById("cds-layout-json"),
    layoutBuilder: document.getElementById("cds-layout-builder"),
    samplePayloadJson: document.getElementById("cds-sample-payload-json"),
    fieldsBuilder: document.getElementById("cds-fields-builder"),
    groupsBuilder: document.getElementById("cds-groups-builder"),
    addFieldBtn: document.getElementById("cds-add-field"),
    addGroupBtn: document.getElementById("cds-add-group"),
    saveBtn: document.getElementById("cds-save-revision"),
    publishBtn: document.getElementById("cds-publish-revision"),
    previewBtn: document.getElementById("cds-preview-pdf"),
    previewInlineBtn: document.getElementById("cds-preview-inline"),
    inlinePreview: document.getElementById("cds-inline-preview"),
    revisionSelect: document.getElementById("cds-revision-select"),
    compareRevision: document.getElementById("cds-compare-revision"),
    compareRunBtn: document.getElementById("cds-compare-run"),
    compareResult: document.getElementById("cds-revision-compare-result"),
    duplicateRevisionBtn: document.getElementById("cds-duplicate-revision"),
    rollbackRevisionBtn: document.getElementById("cds-rollback-revision"),
    setDefault: document.getElementById("cds-set-default"),
    status: document.getElementById("cds-template-status"),
  };

  const jsonEditors = [
    { el: els.schemaJson, label: "Schema JSON" },
    { el: els.themeJson, label: "Theme JSON" },
    { el: els.layoutJson, label: "Layout JSON" },
    { el: els.samplePayloadJson, label: "Sample Payload JSON" },
  ].filter((item) => !!item.el);

  const defaultSchema = {
    version: 1,
    groups: [
      { key: "billing", label: "Billed to", fields: ["client_name", "client_email", "client_address"] },
      { key: "shipment", label: "Destination", fields: ["origin", "destination", "cargo_type", "cargo_weight"] },
    ],
    fields: [
      { key: "client_name", label: "Client Name", type: "text", required: true },
      { key: "client_email", label: "Client Email", type: "email", required: true },
      { key: "client_address", label: "Client Address", type: "textarea", required: false },
      { key: "cargo_type", label: "Cargo Type", type: "text", required: true },
      { key: "cargo_weight", label: "Weight", type: "text", required: false },
      { key: "origin", label: "Origin", type: "text", required: false },
      { key: "destination", label: "Destination", type: "text", required: false },
      { key: "taxable_value", label: "Declared Taxable Value (USD)", type: "number", required: false },
      { key: "quantity", label: "Quantity", type: "number", required: false },
    ],
  };

  const defaultTheme = {
    primary_color: "#0b5fff",
    accent_color: "#101828",
    text_color: "#111827",
    table_header_bg: "#f5f5f5",
    font_family: "DejaVu Sans",
    heading_weight: 700,
    table_cell_padding: 8,
    space_sm: 10,
    space_md: 14,
  };

  const defaultSamplePayload = {
    client_name: "Preview Client",
    client_email: "preview@example.com",
    client_address: "123 Preview Street, Demo City",
    cargo_type: "Electronics",
    quantity: 2,
    taxable_value: 900.5,
    current_location: "Dubai Hub",
    bitcoin_enabled: true,
  };

  function getDefaultSchemaByDocType(docType) {
    const key = String(docType || "invoice");
    if (key === "receipt") {
      return {
        version: 1,
        groups: [
          { key: "billing", label: "Billed to", fields: ["client_name", "client_email", "client_address"] },
          { key: "payment", label: "Receipt Meta", fields: ["receipt_number", "payment_method", "payment_reference", "currency"] },
          { key: "notes", label: "Special Notes and Instructions", fields: ["notes"] },
        ],
        fields: [
          { key: "client_name", label: "Client Name", type: "text", required: true },
          { key: "client_email", label: "Client Email", type: "email", required: true },
          { key: "client_address", label: "Client Address", type: "textarea", required: false },
          { key: "receipt_number", label: "Receipt Number", type: "text", required: false },
          { key: "payment_method", label: "Payment Method", type: "text", required: false },
          { key: "payment_reference", label: "Payment Reference", type: "text", required: false },
          { key: "currency", label: "Currency", type: "text", required: false },
          { key: "notes", label: "Notes", type: "textarea", required: false },
        ],
      };
    }
    if (key === "skr") {
      return {
        version: 1,
        groups: [
          { key: "custody", label: "Custody and Depositor", fields: ["custody_type", "depositor_name", "deposit_number", "projected_days"] },
          { key: "contents", label: "Contents and Value", fields: ["content_description", "quantity", "unit", "packages_number", "declared_value", "origin_of_goods"] },
          { key: "deposit_meta", label: "Deposit Details", fields: ["deposit_type", "insurance_rate", "storage_fees"] },
          { key: "docs", label: "Supporting Documents", fields: ["supporting_documents", "deposit_instructions", "additional_notes"] },
        ],
        fields: [
          { key: "custody_type", label: "Custody Type", type: "text", required: false },
          { key: "depositor_name", label: "Depositor Name", type: "text", required: false },
          { key: "deposit_number", label: "Depositors Booking Number", type: "text", required: false },
          { key: "projected_days", label: "Projected Days of Custody", type: "number", required: false },
          { key: "content_description", label: "Details Description of Contents", type: "textarea", required: false },
          { key: "quantity", label: "Quantity", type: "number", required: false },
          { key: "unit", label: "Unit", type: "text", required: false },
          { key: "packages_number", label: "Number of Packages", type: "number", required: false },
          { key: "declared_value", label: "Declared Value (USD)", type: "number", required: false },
          { key: "origin_of_goods", label: "Origin of Goods", type: "text", required: false },
          { key: "deposit_type", label: "Type of Deposit", type: "text", required: false },
          { key: "insurance_rate", label: "Insurance Value / Rate", type: "text", required: false },
          { key: "storage_fees", label: "Storage Fees (Per Day)", type: "number", required: false },
          { key: "supporting_documents", label: "Supporting Documents of Goods", type: "textarea", required: false },
          { key: "deposit_instructions", label: "Deposition Instructions", type: "textarea", required: false },
          { key: "additional_notes", label: "Additional Information", type: "textarea", required: false },
        ],
      };
    }
    return defaultSchema;
  }

  function getDefaultThemeByDocType(docType) {
    const key = String(docType || "invoice");
    if (key === "receipt") {
      return Object.assign({}, defaultTheme, {
        primary_color: "#e74c3c",
        accent_color: "#2c3e50",
        table_header_bg: "#d4a574",
      });
    }
    if (key === "skr") {
      return Object.assign({}, defaultTheme, {
        primary_color: "#1e4d72",
        accent_color: "#1e4d72",
        table_header_bg: "#e6e6e6",
      });
    }
    return Object.assign({}, defaultTheme, {
      primary_color: "#d4a574",
      accent_color: "#111827",
      table_header_bg: "#f5f5f5",
    });
  }

  function getDefaultLayoutByDocType(docType) {
    const key = String(docType || "invoice");
    if (key === "receipt") {
      return {
        page: "A4",
        title: "Payment Receipt",
        sections: ["header", "summary", "line_items", "footer"],
        qr: { tracking_position: "right", payment_position: "right", size: 96 },
      };
    }
    if (key === "skr") {
      return {
        page: "A4",
        title: "Safe Keeping Receipt",
        sections: ["header", "summary", "tracking_qr", "footer"],
        qr: { tracking_position: "right", payment_position: "right", size: 96 },
      };
    }
    return {
      page: "A4",
      title: "Cargo Invoice",
      sections: ["header", "summary", "line_items", "tracking_qr", "payment_qr", "footer"],
      qr: { tracking_position: "right", payment_position: "right", size: 96 },
    };
  }

  function getDefaultSamplePayloadByDocType(docType) {
    const key = String(docType || "invoice");
    if (key === "receipt") {
      return {
        client_name: "Preview Client",
        client_email: "preview@example.com",
        client_address: "Plot 429 Sseguku, Kampala",
        payment_method: "Bank Transfer",
        payment_reference: "TRX-REF-001",
        receipt_number: "RCP-20260205-38C659",
        notes: "Payment confirmed. Cargo processing continues.",
        currency: "USD",
        line_items: [
          { description: "Gold Bars", quantity: 2, unit_price: 250, total: 500 },
          { description: "Export Handling", quantity: 1, unit_price: 75, total: 75 },
        ],
      };
    }
    if (key === "skr") {
      return {
        client_name: "Preview Client",
        client_email: "preview@example.com",
        cargo_type: "Raw Gold",
        quantity: 250,
        custody_type: "SAFE CUSTODY",
        depositor_name: "Oasis Innocent",
        deposit_number: "ESL20260205176",
        projected_days: 30,
        content_description: "Raw Gold",
        unit: "KGS",
        packages_number: 2,
        declared_value: 120000,
        origin_of_goods: "Uganda",
        deposit_type: "Bonded Warehouse",
        insurance_rate: "1.5%",
        storage_fees: 25,
        supporting_documents: "PRELIMINARY DOCUMENTATION\nCERTIFICATE OF ORIGIN\nCERTIFICATE OF OWNERSHIP\nEXPORT PERMIT",
        deposit_instructions: "Release only to authorized signatory.",
        additional_notes: "Goods held under bonded terms.",
        bitcoin_enabled: false,
      };
    }
    return {
      client_name: "Oasis Innocent",
      client_email: "oasis.joy8@gmail.com",
      client_address: "Kampala, Uganda",
      cargo_type: "Raw Gold",
      quantity: 250,
      taxable_value: 8000,
      destination: "Tororo",
      invoice_number: "WML-20260205-02001b25",
      invoice_date: "2026-02-05",
      currency: "USD",
      tax_rate: 5,
      insurance_rate: 1.5,
      smelting_cost: 35,
      cert_origin: 2500,
      cert_ownership: 2500,
      export_permit: 3000,
      freight_cost: 9.5,
      agent_fees: 12,
      payment_network: "TRON (TRC20)",
      payment_wallet_address: "TUzrKeQqBkcWlPtzqmGbYAsWUqueYTNyKx",
      company_name: "WAKALA Minerals Limited",
      company_phone: "+256-751896060",
      company_email: "info@wakalaminerals.com",
      company_address: "TANK HILL ROAD, MUYENGA\nP.O.BOX 124439 KAMPALA-CPO",
      bitcoin_enabled: true,
    };
  }

  const api = window.CDS_API.request;
  const formatApiError = window.CDS_API.formatError;

  function showStatus(message, type) {
    if (!els.status) {
      return;
    }
    els.status.className = "notice inline";
    els.status.classList.add(type === "error" ? "notice-error" : "notice-success");
    els.status.style.display = "block";
    els.status.querySelector("p").textContent = message;
  }

  function safeParseJson(raw, label) {
    try {
      return JSON.parse(raw || "{}");
    } catch (err) {
      const parseError = new Error(label + " must be valid JSON");
      const keyMap = {
        "Schema JSON": "schema",
        "Theme JSON": "theme",
        "Layout JSON": "layout",
        "Sample Payload JSON": "payload",
      };
      const fieldKey = keyMap[label] || "";
      if (fieldKey) {
        parseError.cds = {
          fields: [{ field: fieldKey, message: label + " must be valid JSON" }],
        };
      }
      throw parseError;
    }
  }

  function focusTemplateJsonField(field) {
    const key = String(field || "").trim().toLowerCase();
    const map = {
      schema: els.schemaJson,
      theme: els.themeJson,
      layout: els.layoutJson,
      payload: els.samplePayloadJson,
      sample_payload: els.samplePayloadJson,
    };
    const input = map[key] || null;
    if (!input || typeof input.focus !== "function") {
      return;
    }
    input.focus();
    if (typeof input.setSelectionRange === "function") {
      const pos = String(input.value || "").length;
      input.setSelectionRange(pos, pos);
    }
  }

  function setJsonFoldState(textarea, toggle, folded) {
    if (!textarea || !toggle) {
      return;
    }
    const previewId = textarea.dataset.previewId || "";
    const preview = previewId ? document.getElementById(previewId) : null;
    textarea.dataset.folded = folded ? "1" : "0";
    textarea.classList.toggle("cds-json-is-folded", folded);
    textarea.style.display = folded ? "none" : "block";
    textarea.style.height = folded ? "84px" : "360px";
    textarea.style.minHeight = folded ? "84px" : "160px";
    textarea.style.maxHeight = folded ? "84px" : "480px";
    textarea.style.overflow = "auto";
    if (preview) {
      preview.style.display = folded ? "block" : "none";
      preview.textContent = buildJsonPreviewText(textarea.value || "");
    }
    toggle.textContent = folded ? "Unfold JSON" : "Fold JSON";
    toggle.setAttribute("aria-expanded", folded ? "false" : "true");
  }

  function buildJsonPreviewText(raw) {
    const text = String(raw || "").trim();
    if (!text) {
      return "{ }";
    }
    const lines = text.split(/\r?\n/).map((x) => x.trim()).filter(Boolean);
    const first = lines[0] || "{ }";
    const keys = (text.match(/\"[^\"]+\"\s*:/g) || []).length;
    return `${first}${lines.length > 1 ? " ..." : ""}  (${lines.length} lines, ${keys} keys)`;
  }

  function initJsonFolding() {
    jsonEditors.forEach((item) => {
      const textarea = item.el;
      if (!textarea || textarea.dataset.foldInit === "1") {
        return;
      }
      textarea.dataset.foldInit = "1";
      textarea.classList.add("cds-json-folded-editor");
      const toggle = document.createElement("button");
      toggle.type = "button";
      toggle.className = "button button-small cds-json-fold-toggle";
      toggle.textContent = "Unfold JSON";
      toggle.setAttribute("aria-label", `${item.label} toggle`);
      textarea.parentNode.insertBefore(toggle, textarea);
      const preview = document.createElement("div");
      preview.id = `cds-json-preview-${Math.random().toString(36).slice(2, 10)}`;
      preview.className = "cds-json-fold-preview";
      preview.setAttribute("role", "note");
      textarea.dataset.previewId = preview.id;
      textarea.parentNode.insertBefore(preview, textarea.nextSibling);
      setJsonFoldState(textarea, toggle, true);
      textarea.addEventListener("input", () => {
        if (textarea.dataset.folded === "1") {
          preview.textContent = buildJsonPreviewText(textarea.value || "");
        }
      });
      toggle.addEventListener("click", () => {
        const folded = textarea.dataset.folded !== "1";
        setJsonFoldState(textarea, toggle, folded);
      });
    });
  }

  function focusFromApiOrParseError(err) {
    const fields = window.CDS_API.getErrorFields(err);
    if (!Array.isArray(fields) || fields.length === 0) {
      return;
    }
    const first = fields[0];
    focusTemplateJsonField(first && first.field ? first.field : "");
  }

  function normalizeSchema(schema) {
    const s = schema && typeof schema === "object" ? schema : {};
    const fields = Array.isArray(s.fields) ? s.fields : [];
    const groups = Array.isArray(s.groups) ? s.groups : [];

    return {
      version: Number.isFinite(Number(s.version)) ? Number(s.version) : 1,
      fields: fields.map((f) => ({
        key: String((f && f.key) || ""),
        label: String((f && f.label) || ""),
        type: String((f && f.type) || "text"),
        required: !!(f && f.required),
      })),
      groups: groups.map((g) => ({
        key: String((g && g.key) || ""),
        label: String((g && g.label) || ""),
        fields: Array.isArray(g && g.fields) ? g.fields.map((x) => String(x)) : [],
      })),
    };
  }

  function normalizeTheme(theme) {
    const t = theme && typeof theme === "object" ? theme : {};
    return {
      primary_color: String(t.primary_color || defaultTheme.primary_color),
      accent_color: String(t.accent_color || defaultTheme.accent_color),
      text_color: String(t.text_color || defaultTheme.text_color),
      table_header_bg: String(t.table_header_bg || defaultTheme.table_header_bg),
      font_family: String(t.font_family || defaultTheme.font_family),
      heading_weight: Number.isFinite(Number(t.heading_weight)) ? Number(t.heading_weight) : defaultTheme.heading_weight,
      table_cell_padding: Number.isFinite(Number(t.table_cell_padding)) ? Number(t.table_cell_padding) : defaultTheme.table_cell_padding,
      space_sm: Number.isFinite(Number(t.space_sm)) ? Number(t.space_sm) : defaultTheme.space_sm,
      space_md: Number.isFinite(Number(t.space_md)) ? Number(t.space_md) : defaultTheme.space_md,
    };
  }

  function normalizeLayout(layout, docType) {
    const l = layout && typeof layout === "object" ? layout : {};
    const defaults = getDefaultLayoutByDocType(docType || (els.docType ? els.docType.value : "invoice"));
    const rawSections = Array.isArray(l.sections) ? l.sections : defaults.sections;
    const qr = l.qr && typeof l.qr === "object" ? l.qr : {};

    return {
      page: String(l.page || defaults.page),
      title: String(l.title || defaults.title),
      sections: rawSections.map((s) => String(s)).filter(Boolean),
      qr: {
        tracking_position: String(qr.tracking_position || defaults.qr.tracking_position),
        payment_position: String(qr.payment_position || defaults.qr.payment_position),
        size: Number.isFinite(Number(qr.size)) ? Number(qr.size) : defaults.qr.size,
      },
    };
  }

  function getSchemaFromTextarea() {
    try {
      return normalizeSchema(safeParseJson(els.schemaJson.value, "Schema JSON"));
    } catch (err) {
      const activeDocType = els.docType ? els.docType.value : "invoice";
      return normalizeSchema(getDefaultSchemaByDocType(activeDocType));
    }
  }

  function setSchemaInTextarea(schema) {
    els.schemaJson.value = JSON.stringify(normalizeSchema(schema), null, 2);
  }

  function getThemeFromTextarea() {
    try {
      return normalizeTheme(safeParseJson(els.themeJson.value, "Theme JSON"));
    } catch (err) {
      const activeDocType = els.docType ? els.docType.value : "invoice";
      return normalizeTheme(getDefaultThemeByDocType(activeDocType));
    }
  }

  function setThemeInTextarea(theme) {
    els.themeJson.value = JSON.stringify(normalizeTheme(theme), null, 2);
  }

  function getLayoutFromTextarea() {
    try {
      const activeDocType = els.docType ? els.docType.value : "invoice";
      return normalizeLayout(safeParseJson(els.layoutJson.value, "Layout JSON"), activeDocType);
    } catch (err) {
      const activeDocType = els.docType ? els.docType.value : "invoice";
      return normalizeLayout(getDefaultLayoutByDocType(activeDocType), activeDocType);
    }
  }

  function setLayoutInTextarea(layout) {
    const activeDocType = els.docType ? els.docType.value : "invoice";
    els.layoutJson.value = JSON.stringify(normalizeLayout(layout, activeDocType), null, 2);
  }

  function renderSchemaBuilder(schemaObj) {
    const schema = normalizeSchema(schemaObj);
    const fieldRows = schema.fields
      .map(
        (f, i) =>
          `<div class="cds-builder-row cds-field-row" data-index="${i}">
            <input type="text" data-name="key" placeholder="field_key" value="${escapeHtml(f.key)}" />
            <input type="text" data-name="label" placeholder="Label" value="${escapeHtml(f.label)}" />
            <select data-name="type">
              ${["text", "email", "number", "textarea", "date"].map((t) => `<option value="${t}" ${f.type === t ? "selected" : ""}>${t}</option>`).join("")}
            </select>
            <label><input type="checkbox" data-name="required" ${f.required ? "checked" : ""} />Req</label>
            <button class="button-link-delete" data-action="remove-field" data-index="${i}" type="button">Remove</button>
          </div>`
      )
      .join("");

    const groupRows = schema.groups
      .map(
        (g, i) =>
          `<div class="cds-builder-row cds-group-row" data-index="${i}">
            <input type="text" data-name="key" placeholder="group_key" value="${escapeHtml(g.key)}" />
            <input type="text" data-name="label" placeholder="Group Label" value="${escapeHtml(g.label)}" />
            <input type="text" data-name="fields" placeholder="field_a, field_b" value="${escapeHtml(g.fields.join(", "))}" />
            <button class="button-link-delete" data-action="remove-group" data-index="${i}" type="button">Remove</button>
          </div>`
      )
      .join("");

    if (els.fieldsBuilder) {
      els.fieldsBuilder.innerHTML = fieldRows || "<p class='description'>No fields yet.</p>";
    }
    if (els.groupsBuilder) {
      els.groupsBuilder.innerHTML = groupRows || "<p class='description'>No groups yet.</p>";
    }
  }

  function schemaFromBuilderRows() {
    const schema = getSchemaFromTextarea();

    if (els.fieldsBuilder) {
      const rows = Array.from(els.fieldsBuilder.querySelectorAll(".cds-field-row"));
      schema.fields = rows.map((row) => ({
        key: (row.querySelector('[data-name="key"]') || {}).value || "",
        label: (row.querySelector('[data-name="label"]') || {}).value || "",
        type: (row.querySelector('[data-name="type"]') || {}).value || "text",
        required: !!((row.querySelector('[data-name="required"]') || {}).checked),
      }));
    }

    if (els.groupsBuilder) {
      const rows = Array.from(els.groupsBuilder.querySelectorAll(".cds-group-row"));
      schema.groups = rows.map((row) => ({
        key: (row.querySelector('[data-name="key"]') || {}).value || "",
        label: (row.querySelector('[data-name="label"]') || {}).value || "",
        fields: String((row.querySelector('[data-name="fields"]') || {}).value || "")
          .split(",")
          .map((x) => x.trim())
          .filter(Boolean),
      }));
    }

    return normalizeSchema(schema);
  }

  function syncSchemaFromBuilder() {
    setSchemaInTextarea(schemaFromBuilderRows());
  }

  function renderThemeBuilder(themeObj) {
    const t = normalizeTheme(themeObj);
    if (!els.themeBuilder) {
      return;
    }

    els.themeBuilder.innerHTML = `
      <div class="cds-theme-row">
        <label>Primary Color</label><input type="color" data-theme="primary_color" value="${escapeHtml(t.primary_color)}" />
        <label>Accent Color</label><input type="color" data-theme="accent_color" value="${escapeHtml(t.accent_color)}" />
        <label>Text Color</label><input type="color" data-theme="text_color" value="${escapeHtml(t.text_color)}" />
        <label>Table Header</label><input type="color" data-theme="table_header_bg" value="${escapeHtml(t.table_header_bg)}" />
      </div>
      <div class="cds-theme-row">
        <label>Font Family</label><input type="text" data-theme="font_family" value="${escapeHtml(t.font_family)}" />
        <label>Heading Weight</label><input type="number" min="300" max="900" step="100" data-theme="heading_weight" value="${escapeHtml(t.heading_weight)}" />
        <label>Cell Padding</label><input type="number" min="4" max="16" data-theme="table_cell_padding" value="${escapeHtml(t.table_cell_padding)}" />
        <label>Space SM</label><input type="number" min="6" max="20" data-theme="space_sm" value="${escapeHtml(t.space_sm)}" />
        <label>Space MD</label><input type="number" min="8" max="30" data-theme="space_md" value="${escapeHtml(t.space_md)}" />
      </div>
    `;
  }

  function syncThemeFromBuilder() {
    if (!els.themeBuilder) {
      return;
    }

    const theme = getThemeFromTextarea();
    const nodes = Array.from(els.themeBuilder.querySelectorAll("[data-theme]"));
    nodes.forEach((node) => {
      const key = node.getAttribute("data-theme");
      if (!key) {
        return;
      }
      const value = node.value;
      if (["heading_weight", "table_cell_padding", "space_sm", "space_md"].includes(key)) {
        theme[key] = Number(value || 0);
      } else {
        theme[key] = value;
      }
    });
    setThemeInTextarea(theme);
  }

  function renderLayoutBuilder(layoutObj) {
    if (!els.layoutBuilder) {
      return;
    }
    const activeDocType = els.docType ? els.docType.value : "invoice";
    const l = normalizeLayout(layoutObj, activeDocType);
    const allSections = ["header", "summary", "line_items", "tracking_qr", "payment_qr", "footer"];

    const sectionRows = allSections
      .map((key) => {
        const idx = l.sections.indexOf(key);
        const enabled = idx !== -1;
        const order = enabled ? idx + 1 : 99;
        return `<div class="cds-layout-row">
          <label><input type="checkbox" data-layout-section="${key}" ${enabled ? "checked" : ""} /> ${key}</label>
          <input type="number" min="1" max="99" data-layout-order="${key}" value="${order}" ${enabled ? "" : "disabled"} />
        </div>`;
      })
      .join("");

    els.layoutBuilder.innerHTML = `
      <div class="cds-layout-row">
        <label>Title</label>
        <input type="text" data-layout="title" value="${escapeHtml(l.title)}" />
        <label>Page</label>
        <select data-layout="page">
          <option value="A4" ${l.page === "A4" ? "selected" : ""}>A4</option>
          <option value="LETTER" ${l.page === "LETTER" ? "selected" : ""}>LETTER</option>
        </select>
      </div>
      <div class="cds-layout-row">
        <label>Tracking QR Position</label>
        <select data-layout="qr_tracking_position">
          <option value="left" ${l.qr.tracking_position === "left" ? "selected" : ""}>left</option>
          <option value="right" ${l.qr.tracking_position === "right" ? "selected" : ""}>right</option>
        </select>
        <label>Payment QR Position</label>
        <select data-layout="qr_payment_position">
          <option value="left" ${l.qr.payment_position === "left" ? "selected" : ""}>left</option>
          <option value="right" ${l.qr.payment_position === "right" ? "selected" : ""}>right</option>
        </select>
        <label>QR Size</label>
        <input type="number" min="64" max="220" data-layout="qr_size" value="${l.qr.size}" />
      </div>
      <div class="cds-layout-sections">
        <strong>Sections</strong>
        ${sectionRows}
      </div>
    `;
  }

  function syncLayoutFromBuilder() {
    if (!els.layoutBuilder) {
      return;
    }
    const l = getLayoutFromTextarea();
    const sections = [];

    const title = els.layoutBuilder.querySelector('[data-layout="title"]');
    const page = els.layoutBuilder.querySelector('[data-layout="page"]');
    const qrTrack = els.layoutBuilder.querySelector('[data-layout="qr_tracking_position"]');
    const qrPay = els.layoutBuilder.querySelector('[data-layout="qr_payment_position"]');
    const qrSize = els.layoutBuilder.querySelector('[data-layout="qr_size"]');

    l.title = title ? title.value : l.title;
    l.page = page ? page.value : l.page;
    l.qr.tracking_position = qrTrack ? qrTrack.value : l.qr.tracking_position;
    l.qr.payment_position = qrPay ? qrPay.value : l.qr.payment_position;
    l.qr.size = qrSize ? Number(qrSize.value || l.qr.size) : l.qr.size;

    const checks = Array.from(els.layoutBuilder.querySelectorAll("[data-layout-section]"));
    checks.forEach((check) => {
      const key = check.getAttribute("data-layout-section");
      if (!key) {
        return;
      }
      const orderInput = els.layoutBuilder.querySelector(`[data-layout-order="${key}"]`);
      if (check.checked) {
        const order = orderInput ? Number(orderInput.value || 99) : 99;
        sections.push({ key, order });
        if (orderInput) {
          orderInput.disabled = false;
        }
      } else if (orderInput) {
        orderInput.disabled = true;
      }
    });

    sections.sort((a, b) => a.order - b.order);
    l.sections = sections.map((x) => x.key);
    setLayoutInTextarea(l);
  }

  function renderTemplates(items) {
    if (!Array.isArray(items) || items.length === 0) {
      els.list.innerHTML = "<p>No templates yet.</p>";
      return;
    }

    const html = items
      .map((t) => {
        const badge = Number(t.is_default) === 1 ? " <strong>(default)</strong>" : "";
        const latest = t.latest_revision_no ? `Rev ${t.latest_revision_no}` : "No revisions";
        return `<button class="button cds-template-item" data-id="${t.id}">${t.name}${badge}<br><small>${t.doc_type_key} | ${latest}</small></button>`;
      })
      .join("");
    els.list.innerHTML = `<div class="cds-template-items">${html}</div>`;

    els.list.querySelectorAll(".cds-template-item").forEach((btn) => {
      btn.addEventListener("click", () => {
        loadTemplate(Number(btn.dataset.id));
      });
    });
  }

  function applyRevisionToEditor(revision) {
    const activeDocType = els.docType ? els.docType.value : "invoice";
    const schema = revision && revision.schema_json ? revision.schema_json : getDefaultSchemaByDocType(activeDocType);
    const theme = revision && revision.theme_json ? revision.theme_json : getDefaultThemeByDocType(activeDocType);
    const layout = revision && revision.layout_json ? revision.layout_json : getDefaultLayoutByDocType(activeDocType);

    setSchemaInTextarea(schema);
    renderSchemaBuilder(schema);
    setThemeInTextarea(theme);
    renderThemeBuilder(theme);
    setLayoutInTextarea(layout);
    renderLayoutBuilder(layout);
    if (els.samplePayloadJson && !els.samplePayloadJson.value) {
      els.samplePayloadJson.value = JSON.stringify(getDefaultSamplePayloadByDocType(activeDocType), null, 2);
    }
    state.selectedRevisionId = revision ? Number(revision.id) : null;
  }

  function renderRevisionHistory(revisions) {
    if (!els.revisionSelect) {
      return;
    }
    const list = Array.isArray(revisions) ? revisions : [];
    state.loadedRevisions = list;
    if (list.length === 0) {
      els.revisionSelect.innerHTML = '<option value="">No revisions</option>';
      els.revisionSelect.disabled = true;
      if (els.compareRevision) {
        els.compareRevision.innerHTML = '<option value="">No revisions</option>';
        els.compareRevision.disabled = true;
      }
      return;
    }

    const options = list
      .map((rev) => {
        const revId = Number(rev.id || 0);
        if (revId <= 0) {
          return "";
        }
        const status = Number(rev.is_published || 0) === 1 ? "Published" : "Draft";
        const label = `Rev ${rev.revision_no || "?"} - ${status} - ${rev.created_at || ""}`;
        return `<option value="${revId}">${escapeHtml(label)}</option>`;
      })
      .filter(Boolean)
      .join("");
    els.revisionSelect.innerHTML = options || '<option value="">No revisions</option>';
    els.revisionSelect.disabled = options === "";
    if (els.compareRevision) {
      els.compareRevision.innerHTML = `<option value="">Compare against...</option>${options}`;
      els.compareRevision.disabled = options === "";
    }
    if (state.selectedRevisionId) {
      els.revisionSelect.value = String(state.selectedRevisionId);
    }
  }

  function getLoadedRevisionById(revisionId) {
    const id = Number(revisionId || 0);
    if (id <= 0) {
      return null;
    }
    return state.loadedRevisions.find((rev) => Number(rev.id || 0) === id) || null;
  }

  function flattenForDiff(value, path, map) {
    if (Array.isArray(value)) {
      if (value.length === 0) {
        map[path] = [];
        return;
      }
      value.forEach((item, index) => {
        const nextPath = `${path}[${index}]`;
        flattenForDiff(item, nextPath, map);
      });
      return;
    }

    if (value && typeof value === "object") {
      const keys = Object.keys(value);
      if (keys.length === 0) {
        map[path] = {};
        return;
      }
      keys.forEach((key) => {
        const nextPath = path ? `${path}.${key}` : key;
        flattenForDiff(value[key], nextPath, map);
      });
      return;
    }

    map[path] = value;
  }

  function formatValueForDiff(value) {
    if (typeof value === "string") {
      return value;
    }
    try {
      return JSON.stringify(value);
    } catch (err) {
      return String(value);
    }
  }

  function collectDiffRows(section, sourceValue, targetValue) {
    const sourceMap = {};
    const targetMap = {};
    flattenForDiff(sourceValue, section, sourceMap);
    flattenForDiff(targetValue, section, targetMap);

    const paths = new Set([...Object.keys(sourceMap), ...Object.keys(targetMap)]);
    const rows = [];
    paths.forEach((path) => {
      const hasSource = Object.prototype.hasOwnProperty.call(sourceMap, path);
      const hasTarget = Object.prototype.hasOwnProperty.call(targetMap, path);
      if (hasSource && !hasTarget) {
        rows.push({ type: "removed", path, before: formatValueForDiff(sourceMap[path]), after: "" });
        return;
      }
      if (!hasSource && hasTarget) {
        rows.push({ type: "added", path, before: "", after: formatValueForDiff(targetMap[path]) });
        return;
      }
      const before = formatValueForDiff(sourceMap[path]);
      const after = formatValueForDiff(targetMap[path]);
      if (before !== after) {
        rows.push({ type: "changed", path, before, after });
      }
    });

    rows.sort((a, b) => a.path.localeCompare(b.path));
    return rows;
  }

  function renderRevisionDiffReport(sourceRev, targetRev, rows) {
    if (!els.compareResult) {
      return;
    }
    if (!Array.isArray(rows) || rows.length === 0) {
      els.compareResult.innerHTML = `<p><strong>No differences found.</strong> Revision ${escapeHtml(String(sourceRev.revision_no || sourceRev.id))} and revision ${escapeHtml(String(targetRev.revision_no || targetRev.id))} are identical for schema/theme/layout.</p>`;
      return;
    }

    const added = rows.filter((r) => r.type === "added").length;
    const removed = rows.filter((r) => r.type === "removed").length;
    const changed = rows.filter((r) => r.type === "changed").length;
    const maxRows = 150;
    const visible = rows.slice(0, maxRows);
    const truncated = rows.length > maxRows;

    const body = visible
      .map((row) => `<tr>
        <td><span class="cds-diff-badge ${escapeHtml(row.type)}">${escapeHtml(row.type)}</span></td>
        <td><code>${escapeHtml(row.path)}</code></td>
        <td>${escapeHtml(row.before)}</td>
        <td>${escapeHtml(row.after)}</td>
      </tr>`)
      .join("");

    els.compareResult.innerHTML = `
      <p><strong>Comparing</strong> Rev ${escapeHtml(String(sourceRev.revision_no || sourceRev.id))} -> Rev ${escapeHtml(String(targetRev.revision_no || targetRev.id))}</p>
      <p>Added: <strong>${added}</strong> | Removed: <strong>${removed}</strong> | Changed: <strong>${changed}</strong></p>
      ${truncated ? `<p class="description">Showing first ${maxRows} differences of ${rows.length} total.</p>` : ""}
      <div class="cds-diff-table-wrap">
        <table class="widefat striped cds-diff-table">
          <thead><tr><th>Type</th><th>Path</th><th>Before</th><th>After</th></tr></thead>
          <tbody>${body}</tbody>
        </table>
      </div>
    `;
  }

  function compareSelectedRevisions() {
    if (!state.selectedTemplateId || !state.selectedRevisionId) {
      showStatus("Load a template and choose a base revision first.", "error");
      return;
    }
    if (!els.compareRevision) {
      return;
    }

    const compareId = Number(els.compareRevision.value || 0);
    if (compareId <= 0) {
      showStatus("Choose a revision to compare against.", "error");
      return;
    }
    if (compareId === state.selectedRevisionId) {
      showStatus("Choose a different revision to compare.", "error");
      return;
    }

    const source = getLoadedRevisionById(state.selectedRevisionId);
    const target = getLoadedRevisionById(compareId);
    if (!source || !target) {
      showStatus("Could not resolve selected revisions for compare.", "error");
      return;
    }

    const rows = [
      ...collectDiffRows("schema", source.schema_json || {}, target.schema_json || {}),
      ...collectDiffRows("theme", source.theme_json || {}, target.theme_json || {}),
      ...collectDiffRows("layout", source.layout_json || {}, target.layout_json || {}),
    ];

    renderRevisionDiffReport(source, target, rows);
    showStatus(`Compared revision ${source.revision_no || source.id} with ${target.revision_no || target.id}.`, "success");
  }

  function refreshTemplates() {
    const docType = encodeURIComponent(els.docType.value || "");
    return api(`/templates?doc_type=${docType}`).then((data) => {
      renderTemplates(data.templates || []);
    });
  }

  function loadTemplate(templateId) {
    state.selectedTemplateId = templateId;
    api(`/templates/${templateId}`)
      .then((data) => {
        const template = data.template || {};
        if (els.docType && template.doc_type_key) {
          els.docType.value = String(template.doc_type_key);
        }
        const revisions = Array.isArray(template.revisions) ? template.revisions : [];
        const revision = revisions.length > 0 ? revisions[0] : null;
        applyRevisionToEditor(revision);
        renderRevisionHistory(revisions);
        showStatus(`Loaded template #${templateId}`, "success");
      })
      .catch((err) => {
        showStatus(formatApiError(err), "error");
      });
  }

  function duplicateSelectedRevision() {
    if (!state.selectedTemplateId || !state.selectedRevisionId) {
      showStatus("Load a template and choose a revision first.", "error");
      return;
    }

    api(`/templates/${state.selectedTemplateId}/revisions/duplicate`, {
      method: "POST",
      body: JSON.stringify({
        revision_id: state.selectedRevisionId,
      }),
    })
      .then((data) => {
        const revisionId = Number(data.revision_id || 0);
        showStatus(revisionId > 0 ? `Revision duplicated (new id: ${revisionId}).` : "Revision duplicated.", "success");
        return Promise.all([refreshTemplates(), loadTemplate(state.selectedTemplateId)]);
      })
      .catch((err) => showStatus(formatApiError(err), "error"));
  }

  function rollbackToSelectedRevision() {
    if (!state.selectedTemplateId || !state.selectedRevisionId) {
      showStatus("Load a template and choose a revision first.", "error");
      return;
    }

    api(`/templates/${state.selectedTemplateId}/publish`, {
      method: "POST",
      body: JSON.stringify({
        revision_id: state.selectedRevisionId,
        is_default: !!els.setDefault.checked,
      }),
    })
      .then(() => {
        showStatus(`Rollback complete. Published revision #${state.selectedRevisionId}.`, "success");
        return Promise.all([refreshTemplates(), loadTemplate(state.selectedTemplateId)]);
      })
      .catch((err) => showStatus(formatApiError(err), "error"));
  }

  function createTemplate() {
    const docType = els.docType ? els.docType.value : "invoice";
    const payload = {
      doc_type_key: docType,
      name: (els.templateName.value || "").trim(),
      schema: getDefaultSchemaByDocType(docType),
      theme: getDefaultThemeByDocType(docType),
      layout: getDefaultLayoutByDocType(docType),
      is_default: false,
      publish: false,
    };

    if (!payload.name) {
      showStatus("Template name is required.", "error");
      return;
    }

    api("/templates", {
      method: "POST",
      body: JSON.stringify(payload),
    })
      .then((data) => {
        state.selectedTemplateId = Number(data.template_id);
        applyRevisionToEditor(null);
        showStatus("Template created.", "success");
        return refreshTemplates().then(() => loadTemplate(state.selectedTemplateId));
      })
      .catch((err) => showStatus(formatApiError(err), "error"));
  }

  function parseEditorJson() {
    const schema = safeParseJson(els.schemaJson.value, "Schema JSON");
    const theme = safeParseJson(els.themeJson.value, "Theme JSON");
    const layout = safeParseJson(els.layoutJson.value, "Layout JSON");
    const samplePayload = safeParseJson(els.samplePayloadJson.value, "Sample Payload JSON");

    return { schema, theme, layout, samplePayload };
  }

  function saveRevision(publishNow) {
    if (!state.selectedTemplateId) {
      showStatus("Select or create a template first.", "error");
      return;
    }

    let parsed;
    try {
      parsed = parseEditorJson();
    } catch (err) {
      showStatus(err.message, "error");
      focusFromApiOrParseError(err);
      return;
    }

    api(`/templates/${state.selectedTemplateId}/revisions`, {
      method: "POST",
      body: JSON.stringify({
        schema: parsed.schema,
        theme: parsed.theme,
        layout: parsed.layout,
        publish: publishNow,
        is_default: !!els.setDefault.checked,
      }),
    })
      .then(() => {
        showStatus(publishNow ? "Revision published." : "Draft revision saved.", "success");
        return Promise.all([refreshTemplates(), loadTemplate(state.selectedTemplateId)]);
      })
      .catch((err) => {
        showStatus(formatApiError(err), "error");
        focusFromApiOrParseError(err);
      });
  }

  function previewPdf() {
    let parsed;
    try {
      parsed = parseEditorJson();
    } catch (err) {
      showStatus(err.message, "error");
      focusFromApiOrParseError(err);
      return;
    }

    api("/templates/preview", {
      method: "POST",
      body: JSON.stringify({
        revision_id: state.selectedRevisionId || 0,
        doc_type_key: els.docType ? els.docType.value : "invoice",
        schema: parsed.schema,
        theme: parsed.theme,
        layout: parsed.layout,
        payload: parsed.samplePayload,
      }),
    })
      .then((data) => {
        if (data.pdf_url) {
          window.open(data.pdf_url, "_blank", "noopener");
        }
        const fallback = data.engine_fallback ? ` (fallback: ${data.engine_fallback})` : "";
        showStatus("Preview generated." + fallback, "success");
      })
      .catch((err) => {
        showStatus(formatApiError(err), "error");
        focusFromApiOrParseError(err);
      });
  }

  function previewInline() {
    let parsed;
    try {
      parsed = parseEditorJson();
    } catch (err) {
      showStatus(err.message, "error");
      focusFromApiOrParseError(err);
      return;
    }

    api("/templates/preview-html", {
      method: "POST",
      body: JSON.stringify({
        revision_id: state.selectedRevisionId || 0,
        doc_type_key: els.docType ? els.docType.value : "invoice",
        schema: parsed.schema,
        theme: parsed.theme,
        layout: parsed.layout,
        payload: parsed.samplePayload,
      }),
    })
      .then((data) => {
        if (els.inlinePreview) {
          els.inlinePreview.srcdoc = data.html || "<p>Empty preview.</p>";
        }
        showStatus("Inline preview updated.", "success");
      })
      .catch((err) => {
        showStatus(formatApiError(err), "error");
        focusFromApiOrParseError(err);
      });
  }

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  if (els.createBtn) {
    els.createBtn.addEventListener("click", createTemplate);
  }
  if (els.saveBtn) {
    els.saveBtn.addEventListener("click", () => saveRevision(false));
  }
  if (els.publishBtn) {
    els.publishBtn.addEventListener("click", () => saveRevision(true));
  }
  if (els.previewBtn) {
    els.previewBtn.addEventListener("click", previewPdf);
  }
  if (els.previewInlineBtn) {
    els.previewInlineBtn.addEventListener("click", previewInline);
  }
  if (els.docType) {
    els.docType.addEventListener("change", () => {
      state.selectedTemplateId = null;
      state.selectedRevisionId = null;
      state.loadedRevisions = [];
      if (els.samplePayloadJson) {
        els.samplePayloadJson.value = JSON.stringify(getDefaultSamplePayloadByDocType(els.docType.value), null, 2);
      }
      applyRevisionToEditor(null);
      renderRevisionHistory([]);
      if (els.compareResult) {
        els.compareResult.innerHTML = '<p class="description">Choose two revisions and click Compare to view differences.</p>';
      }
      refreshTemplates().catch((err) => showStatus(formatApiError(err), "error"));
    });
  }

  if (els.revisionSelect) {
    els.revisionSelect.addEventListener("change", () => {
      const revId = Number(els.revisionSelect.value || 0);
      if (revId <= 0) {
        return;
      }
      const revision = state.loadedRevisions.find((r) => Number(r.id || 0) === revId) || null;
      if (!revision) {
        showStatus("Selected revision could not be loaded from current template.", "error");
        return;
      }
      applyRevisionToEditor(revision);
      showStatus(`Loaded revision #${revId} into editor.`, "success");
    });
  }

  if (els.duplicateRevisionBtn) {
    els.duplicateRevisionBtn.addEventListener("click", duplicateSelectedRevision);
  }

  if (els.rollbackRevisionBtn) {
    els.rollbackRevisionBtn.addEventListener("click", rollbackToSelectedRevision);
  }

  if (els.compareRunBtn) {
    els.compareRunBtn.addEventListener("click", compareSelectedRevisions);
  }

  if (els.addFieldBtn) {
    els.addFieldBtn.addEventListener("click", () => {
      const schema = getSchemaFromTextarea();
      schema.fields.push({ key: "", label: "", type: "text", required: false });
      setSchemaInTextarea(schema);
      renderSchemaBuilder(schema);
    });
  }

  if (els.addGroupBtn) {
    els.addGroupBtn.addEventListener("click", () => {
      const schema = getSchemaFromTextarea();
      schema.groups.push({ key: "", label: "", fields: [] });
      setSchemaInTextarea(schema);
      renderSchemaBuilder(schema);
    });
  }

  if (els.fieldsBuilder) {
    els.fieldsBuilder.addEventListener("input", syncSchemaFromBuilder);
    els.fieldsBuilder.addEventListener("change", syncSchemaFromBuilder);
    els.fieldsBuilder.addEventListener("click", (e) => {
      const target = e.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }
      if (target.dataset.action === "remove-field") {
        const idx = Number(target.dataset.index);
        const schema = getSchemaFromTextarea();
        schema.fields.splice(idx, 1);
        setSchemaInTextarea(schema);
        renderSchemaBuilder(schema);
      }
    });
  }

  if (els.groupsBuilder) {
    els.groupsBuilder.addEventListener("input", syncSchemaFromBuilder);
    els.groupsBuilder.addEventListener("change", syncSchemaFromBuilder);
    els.groupsBuilder.addEventListener("click", (e) => {
      const target = e.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }
      if (target.dataset.action === "remove-group") {
        const idx = Number(target.dataset.index);
        const schema = getSchemaFromTextarea();
        schema.groups.splice(idx, 1);
        setSchemaInTextarea(schema);
        renderSchemaBuilder(schema);
      }
    });
  }

  if (els.schemaJson) {
    els.schemaJson.addEventListener("blur", () => {
      try {
        const schema = normalizeSchema(safeParseJson(els.schemaJson.value, "Schema JSON"));
        setSchemaInTextarea(schema);
        renderSchemaBuilder(schema);
      } catch (err) {
        showStatus(err.message, "error");
        focusFromApiOrParseError(err);
      }
    });
  }

  if (els.themeBuilder) {
    els.themeBuilder.addEventListener("input", syncThemeFromBuilder);
    els.themeBuilder.addEventListener("change", syncThemeFromBuilder);
  }

  if (els.themeJson) {
    els.themeJson.addEventListener("blur", () => {
      try {
        const theme = normalizeTheme(safeParseJson(els.themeJson.value, "Theme JSON"));
        setThemeInTextarea(theme);
        renderThemeBuilder(theme);
      } catch (err) {
        showStatus(err.message, "error");
        focusFromApiOrParseError(err);
      }
    });
  }

  if (els.layoutBuilder) {
    els.layoutBuilder.addEventListener("input", syncLayoutFromBuilder);
    els.layoutBuilder.addEventListener("change", syncLayoutFromBuilder);
  }

  if (els.layoutJson) {
    els.layoutJson.addEventListener("blur", () => {
      try {
        const activeDocType = els.docType ? els.docType.value : "invoice";
        const layout = normalizeLayout(safeParseJson(els.layoutJson.value, "Layout JSON"), activeDocType);
        setLayoutInTextarea(layout);
        renderLayoutBuilder(layout);
      } catch (err) {
        showStatus(err.message, "error");
        focusFromApiOrParseError(err);
      }
    });
  }

  initJsonFolding();

  applyRevisionToEditor(null);
  renderRevisionHistory([]);
  if (els.compareResult) {
    els.compareResult.innerHTML = '<p class="description">Choose two revisions and click Compare to view differences.</p>';
  }
  refreshTemplates().catch((err) => showStatus(formatApiError(err), "error"));
})();
