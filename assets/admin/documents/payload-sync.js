(function (window) {
  "use strict";

  function createPayloadSync(deps) {
    const { els, state } = deps;
    const nodeCache = {
      payloadFields: null,
      checkboxGroups: null,
      rootKey: "",
    };

    function invalidateNodeCache() {
      nodeCache.payloadFields = null;
      nodeCache.checkboxGroups = null;
      nodeCache.rootKey = "";
    }

    function getFormCacheKey() {
      if (!els.formBuilder) {
        return "";
      }
      const schemaId = els.formBuilder.getAttribute("data-schema-id") || "";
      const fieldCount = els.formBuilder.querySelectorAll("[data-payload-key]").length;
      const groupCount = els.formBuilder.querySelectorAll("[data-checkbox-group]").length;
      return `${schemaId}:${fieldCount}:${groupCount}`;
    }

    function ensureNodeCache() {
      if (!els.formBuilder) {
        return { payloadFields: [], checkboxGroups: [] };
      }
      const key = getFormCacheKey();
      if (nodeCache.rootKey !== key || !nodeCache.payloadFields || !nodeCache.checkboxGroups) {
        nodeCache.rootKey = key;
        nodeCache.payloadFields = Array.from(els.formBuilder.querySelectorAll("[data-payload-key]"));
        nodeCache.checkboxGroups = Array.from(els.formBuilder.querySelectorAll("[data-checkbox-group]"));
      }
      return {
        payloadFields: nodeCache.payloadFields,
        checkboxGroups: nodeCache.checkboxGroups,
      };
    }

    function coerceValueByType(raw, type) {
      if (type === "checkbox") {
        return !!raw;
      }
      if (type === "number") {
        if (raw === "" || raw === null || raw === undefined) {
          return "";
        }
        const num = Number(raw);
        return Number.isFinite(num) ? num : "";
      }
      return String(raw == null ? "" : raw);
    }

    function tryParsePayload() {
      try {
        return JSON.parse((els.payload && els.payload.value) || "{}");
      } catch (err) {
        return null;
      }
    }

    function syncPayloadFromForm({ renderPayloadHints, setGenerateButtonState }) {
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
      const { payloadFields, checkboxGroups } = ensureNodeCache();
      payloadFields.forEach((node) => {
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
      const { payloadFields, checkboxGroups } = ensureNodeCache();
      payloadFields.forEach((node) => {
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

    return {
      tryParsePayload,
      syncPayloadFromForm,
      syncFormFromPayload,
      invalidateNodeCache,
    };
  }

  window.CDS_DOCS_PAYLOAD_SYNC = {
    create: createPayloadSync,
  };
})(window);
