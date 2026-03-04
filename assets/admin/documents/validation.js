(function (window) {
  "use strict";

  const { escapeHtml, escapeRegExp } = window.CDS_DOCS_UTILS;

  function createValidationModule(deps) {
    const {
      els,
      state,
      payloadDefaults,
      fallbackSchemas,
      skrFormSections,
      receiptFormSections,
      getDocType,
      normalizeFormFields,
      tryParsePayload,
      syncFormFromPayload,
      showStatus,
      formatApiError,
      getApiErrorFields,
    } = deps;

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
      } else if (docType === "receipt") {
        const visible = new Set(
          receiptFormSections
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

    function validatePayload(payload) {
      const errors = getPayloadValidationErrors(payload);
      return errors.length > 0 ? errors[0] : "";
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
      const pattern = new RegExp(`\"${escapeRegExp(firstField)}\"\\s*:`);
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

    function autoFixPayload(syncAndRefresh) {
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
      if (typeof syncAndRefresh === "function") {
        syncAndRefresh(payload);
      }
      showStatus("Payload auto-fix applied.", "success");
    }

    return {
      validatePayload,
      getPayloadValidationErrors,
      renderPayloadHints,
      renderRequiredChecklist,
      setGenerateButtonState,
      renderApiFieldErrors,
      focusPayloadField,
      handleGenerationError,
      autoFixPayload,
    };
  }

  window.CDS_DOCS_VALIDATION = {
    create: createValidationModule,
  };
})(window);
