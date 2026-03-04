(function (window) {
  "use strict";

  const { escapeHtml, escapeHtmlAttr } = window.CDS_DOCS_UTILS;

  function createFormRenderer(deps) {
    const {
      els,
      fallbackSchemas,
      getDocType,
      skrFormSections,
      invoiceFormSections,
      receiptFormSections,
      skrFieldUi,
      invoiceFieldUi,
      receiptFieldUi,
    } = deps;

    function normalizeFormFields(fields, docType) {
      const fallback = fallbackSchemas[docType] || fallbackSchemas.invoice;
      const blockedKeysByDocType = {
        invoice: new Set(["skr_watermark_url", "watermark_url"]),
        receipt: new Set(["skr_watermark_url", "watermark_url"]),
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
        const watermarkToggle = fallback.find((f) => f.key === "watermark_enabled") || {
          key: "watermark_enabled",
          label: "Enable Watermark",
          type: "checkbox",
          required: false,
        };
        merged = merged.filter((f) => f.key !== "watermark_enabled" && f.key !== "skr_watermark_enabled");
        merged.unshift(Object.assign({}, watermarkToggle));
      }

      return merged.length > 0 ? merged : fallback;
    }

    function renderFormBuilder(fields) {
      if (!els.formBuilder) {
        return;
      }
      if (getDocType() === "invoice") {
        els.formBuilder.classList.add("cds-doc-form-builder-skr");
        renderInvoiceFormBuilder(fields);
        return;
      }
      if (getDocType() === "receipt") {
        els.formBuilder.classList.add("cds-doc-form-builder-skr");
        renderReceiptFormBuilder(fields);
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
            return `<label for=\"cds-form-${escapeHtmlAttr(f.key)}\">${escapeHtml(f.label)}${req}</label><textarea id=\"cds-form-${escapeHtmlAttr(f.key)}\" data-payload-key=\"${escapeHtmlAttr(f.key)}\" data-payload-type=\"${escapeHtmlAttr(f.type)}\" ${requiredAttr}></textarea>`;
          }
          if (f.type === "checkbox") {
            return `<label class=\"cds-form-checkbox\"><input id=\"cds-form-${escapeHtmlAttr(f.key)}\" type=\"checkbox\" data-payload-key=\"${escapeHtmlAttr(f.key)}\" data-payload-type=\"${escapeHtmlAttr(f.type)}\" /> ${escapeHtml(f.label)}${req}</label><div></div>`;
          }
          return `<label for=\"cds-form-${escapeHtmlAttr(f.key)}\">${escapeHtml(f.label)}${req}</label><input id=\"cds-form-${escapeHtmlAttr(f.key)}\" type=\"${escapeHtmlAttr(f.type)}\" data-payload-key=\"${escapeHtmlAttr(f.key)}\" data-payload-type=\"${escapeHtmlAttr(f.type)}\" ${requiredAttr} />`;
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
          return `<section class=\"cds-skr-section\"><h4>${escapeHtml(section.title)}</h4><div class=\"cds-skr-grid\">${items}</div></section>`;
        })
        .join("");

      els.formBuilder.innerHTML = `<div class=\"cds-skr-form\"><h3 class=\"cds-skr-title\">Safe Keeping Receipt Generator</h3>${sectionHtml}</div>`;
    }

    function renderInvoiceFormBuilder(fields) {
      els.formBuilder.classList.add("cds-doc-form-builder-skr");
      const byKey = new Map((fields || []).map((f) => [f.key, f]));
      const sectionHtml = invoiceFormSections
        .map((section) => {
          const items = section.fields
            .map((key) => byKey.get(key) || getForcedInvoiceField(key))
            .filter(Boolean)
            .map((f) => renderInvoiceField(f))
            .join("");
          if (!items) {
            return "";
          }
          return `<section class=\"cds-skr-section\"><h4>${escapeHtml(section.title)}</h4><div class=\"cds-skr-grid\">${items}</div></section>`;
        })
        .join("");

      els.formBuilder.innerHTML = `<div class=\"cds-skr-form\"><h3 class=\"cds-skr-title\">Invoice Generator</h3>${sectionHtml}</div>`;
    }

    function renderReceiptFormBuilder(fields) {
      els.formBuilder.classList.add("cds-doc-form-builder-skr");
      const byKey = new Map((fields || []).map((f) => [f.key, f]));
      const sectionHtml = receiptFormSections
        .map((section) => {
          const items = section.fields
            .map((key) => byKey.get(key) || getForcedReceiptField(key))
            .filter(Boolean)
            .map((f) => renderReceiptField(f))
            .join("");
          if (!items) {
            return "";
          }
          return `<section class=\"cds-skr-section\"><h4>${escapeHtml(section.title)}</h4><div class=\"cds-skr-grid\">${items}</div></section>`;
        })
        .join("");

      els.formBuilder.innerHTML = `<div class=\"cds-skr-form\"><h3 class=\"cds-skr-title\">Receipt Generator</h3>${sectionHtml}</div>`;
    }

    function getForcedSkrField(key) {
      const k = String(key || "").trim();
      if (k === "company_logo_url") {
        return { key: "company_logo_url", label: "Company Logo URL", type: "url", required: false };
      }
      return null;
    }

    function getForcedInvoiceField(key) {
      const k = String(key || "").trim();
      if (k === "company_logo_url") {
        return { key: "company_logo_url", label: "Company Logo URL", type: "url", required: false };
      }
      return null;
    }

    function getForcedReceiptField(key) {
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
              `<label class=\"cds-skr-check\"><input type=\"checkbox\" data-checkbox-group=\"${key}\" data-opt=\"${escapeHtmlAttr(opt)}\" ${idx === 0 ? "checked" : ""} /> ${escapeHtml(opt)}</label>`
          )
          .join("");
        return `<div class=\"cds-skr-item cds-skr-item-wide\"><label for=\"cds-form-${key}\">${label}${req}</label><input id=\"cds-form-${key}\" type=\"hidden\" data-payload-key=\"${key}\" data-payload-type=\"${type}\" ${requiredAttr} /><div class=\"cds-skr-check-row\">${checks}</div></div>`;
      }
      if (f.type === "checkbox") {
        return `<div class=\"cds-skr-item cds-skr-item-wide\"><label class=\"cds-form-checkbox\"><input id=\"cds-form-${key}\" type=\"checkbox\" data-payload-key=\"${key}\" data-payload-type=\"checkbox\" /> ${label}${req}</label></div>`;
      }
      if (ui.control === "select") {
        const opts = (ui.options || []).map((opt) => `<option value=\"${escapeHtmlAttr(opt)}\">${escapeHtml(opt)}</option>`).join("");
        return `<div class=\"cds-skr-item\"><label for=\"cds-form-${key}\">${label}${req}</label><select id=\"cds-form-${key}\" data-payload-key=\"${key}\" data-payload-type=\"${type}\" ${requiredAttr}>${opts}</select></div>`;
      }
      if (f.type === "textarea") {
        return `<div class=\"cds-skr-item cds-skr-item-wide\"><label for=\"cds-form-${key}\">${label}${req}</label><textarea id=\"cds-form-${key}\" data-payload-key=\"${key}\" data-payload-type=\"${type}\" ${requiredAttr}></textarea></div>`;
      }
      return `<div class=\"cds-skr-item\"><label for=\"cds-form-${key}\">${label}${req}</label><input id=\"cds-form-${key}\" type=\"${escapeHtmlAttr(f.type || "text")}\" data-payload-key=\"${key}\" data-payload-type=\"${type}\" ${requiredAttr} /></div>`;
    }

    function renderInvoiceField(f) {
      const ui = invoiceFieldUi[f.key] || {};
      const req = f.required ? " <span class=\"required\">*</span>" : "";
      const requiredAttr = f.required ? "required" : "";
      const key = escapeHtmlAttr(f.key);
      const type = escapeHtmlAttr(f.type || "text");
      const label = escapeHtml(f.label || f.key);

      if (f.type === "checkbox") {
        return `<div class=\"cds-skr-item cds-skr-item-wide\"><label class=\"cds-form-checkbox\"><input id=\"cds-form-${key}\" type=\"checkbox\" data-payload-key=\"${key}\" data-payload-type=\"checkbox\" /> ${label}${req}</label></div>`;
      }
      if (ui.control === "select") {
        const opts = (ui.options || []).map((opt) => `<option value=\"${escapeHtmlAttr(opt)}\">${escapeHtml(opt)}</option>`).join("");
        return `<div class=\"cds-skr-item\"><label for=\"cds-form-${key}\">${label}${req}</label><select id=\"cds-form-${key}\" data-payload-key=\"${key}\" data-payload-type=\"${type}\" ${requiredAttr}>${opts}</select></div>`;
      }
      if (f.type === "textarea") {
        return `<div class=\"cds-skr-item cds-skr-item-wide\"><label for=\"cds-form-${key}\">${label}${req}</label><textarea id=\"cds-form-${key}\" data-payload-key=\"${key}\" data-payload-type=\"${type}\" ${requiredAttr}></textarea></div>`;
      }
      return `<div class=\"cds-skr-item\"><label for=\"cds-form-${key}\">${label}${req}</label><input id=\"cds-form-${key}\" type=\"${escapeHtmlAttr(f.type || "text")}\" data-payload-key=\"${key}\" data-payload-type=\"${type}\" ${requiredAttr} /></div>`;
    }

    function renderReceiptField(f) {
      const ui = receiptFieldUi[f.key] || {};
      const req = f.required ? " <span class=\"required\">*</span>" : "";
      const requiredAttr = f.required ? "required" : "";
      const key = escapeHtmlAttr(f.key);
      const type = escapeHtmlAttr(f.type || "text");
      const label = escapeHtml(f.label || f.key);

      if (f.type === "checkbox") {
        return `<div class=\"cds-skr-item cds-skr-item-wide\"><label class=\"cds-form-checkbox\"><input id=\"cds-form-${key}\" type=\"checkbox\" data-payload-key=\"${key}\" data-payload-type=\"checkbox\" /> ${label}${req}</label></div>`;
      }
      if (ui.control === "select") {
        const opts = (ui.options || []).map((opt) => `<option value=\"${escapeHtmlAttr(opt)}\">${escapeHtml(opt)}</option>`).join("");
        return `<div class=\"cds-skr-item\"><label for=\"cds-form-${key}\">${label}${req}</label><select id=\"cds-form-${key}\" data-payload-key=\"${key}\" data-payload-type=\"${type}\" ${requiredAttr}>${opts}</select></div>`;
      }
      if (f.type === "textarea") {
        return `<div class=\"cds-skr-item cds-skr-item-wide\"><label for=\"cds-form-${key}\">${label}${req}</label><textarea id=\"cds-form-${key}\" data-payload-key=\"${key}\" data-payload-type=\"${type}\" ${requiredAttr}></textarea></div>`;
      }
      return `<div class=\"cds-skr-item\"><label for=\"cds-form-${key}\">${label}${req}</label><input id=\"cds-form-${key}\" type=\"${escapeHtmlAttr(f.type || "text")}\" data-payload-key=\"${key}\" data-payload-type=\"${type}\" ${requiredAttr} /></div>`;
    }

    return {
      normalizeFormFields,
      renderFormBuilder,
    };
  }

  window.CDS_DOCS_FORM_RENDERER = {
    create: createFormRenderer,
  };
})(window);
