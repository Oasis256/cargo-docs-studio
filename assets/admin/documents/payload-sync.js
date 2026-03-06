(function (window) {
  "use strict";

  function createPayloadSync(deps) {
    const { els, state } = deps;
    const nodeCache = {
      payloadFields: null,
      checkboxGroups: null,
      lineItemsRoots: null,
      rootKey: "",
    };

    function invalidateNodeCache() {
      nodeCache.payloadFields = null;
      nodeCache.checkboxGroups = null;
      nodeCache.lineItemsRoots = null;
      nodeCache.rootKey = "";
    }

    function getFormCacheKey() {
      if (!els.formBuilder) {
        return "";
      }
      const schemaId = els.formBuilder.getAttribute("data-schema-id") || "";
      const fieldCount = els.formBuilder.querySelectorAll("[data-payload-key]").length;
      const groupCount = els.formBuilder.querySelectorAll("[data-checkbox-group]").length;
      const lineItemRootCount = els.formBuilder.querySelectorAll("[data-line-items-root]").length;
      return `${schemaId}:${fieldCount}:${groupCount}:${lineItemRootCount}`;
    }

    function ensureNodeCache() {
      if (!els.formBuilder) {
        return { payloadFields: [], checkboxGroups: [], lineItemsRoots: [] };
      }
      const key = getFormCacheKey();
      if (nodeCache.rootKey !== key || !nodeCache.payloadFields || !nodeCache.checkboxGroups || !nodeCache.lineItemsRoots) {
        nodeCache.rootKey = key;
        nodeCache.payloadFields = Array.from(els.formBuilder.querySelectorAll("[data-payload-key]"));
        nodeCache.checkboxGroups = Array.from(els.formBuilder.querySelectorAll("[data-checkbox-group]"));
        nodeCache.lineItemsRoots = Array.from(els.formBuilder.querySelectorAll("[data-line-items-root]"));
      }
      return {
        payloadFields: nodeCache.payloadFields,
        checkboxGroups: nodeCache.checkboxGroups,
        lineItemsRoots: nodeCache.lineItemsRoots,
      };
    }

    function coerceLineItemNumber(raw) {
      if (raw === "" || raw === null || raw === undefined) {
        return "";
      }
      const num = Number(raw);
      return Number.isFinite(num) ? num : "";
    }

    function createLineItemRow(item) {
      const row = document.createElement("div");
      row.className = "cds-line-item-row";
      row.setAttribute("data-line-item-row", "1");

      const descriptionWrap = document.createElement("div");
      descriptionWrap.className = "cds-line-item-cell cds-line-item-cell-wide";
      const descriptionLabel = document.createElement("label");
      descriptionLabel.className = "cds-line-item-label";
      descriptionLabel.textContent = "Commodity";
      const description = document.createElement("input");
      description.type = "text";
      description.className = "cds-line-item-input";
      description.placeholder = "Commodity";
      description.setAttribute("data-line-item-field", "description");
      description.value = item && item.description != null ? String(item.description) : "";
      descriptionWrap.appendChild(descriptionLabel);
      descriptionWrap.appendChild(description);

      const quantityWrap = document.createElement("div");
      quantityWrap.className = "cds-line-item-cell";
      const quantityLabel = document.createElement("label");
      quantityLabel.className = "cds-line-item-label";
      quantityLabel.textContent = "Qty";
      const quantity = document.createElement("input");
      quantity.type = "number";
      quantity.step = "any";
      quantity.className = "cds-line-item-input";
      quantity.placeholder = "Qty";
      quantity.setAttribute("data-line-item-field", "quantity");
      quantity.value = item && item.quantity != null && item.quantity !== "" ? String(item.quantity) : "";
      quantityWrap.appendChild(quantityLabel);
      quantityWrap.appendChild(quantity);

      const amountWrap = document.createElement("div");
      amountWrap.className = "cds-line-item-cell";
      const amountLabel = document.createElement("label");
      amountLabel.className = "cds-line-item-label";
      amountLabel.textContent = "Amount";
      const amount = document.createElement("input");
      amount.type = "number";
      amount.step = "any";
      amount.className = "cds-line-item-input";
      amount.placeholder = "Amount";
      amount.setAttribute("data-line-item-field", "total");
      let amountValue = "";
      if (item && item.unit_price != null && item.unit_price !== "") {
        amountValue = String(item.unit_price);
      } else if (item && item.amount != null && item.amount !== "") {
        amountValue = String(item.amount);
      } else if (item && item.total != null && item.total !== "") {
        const qty = Number(item.quantity || 0);
        const totalRaw = Number(item.total);
        amountValue = (Number.isFinite(qty) && qty > 0 && Number.isFinite(totalRaw))
          ? String(totalRaw / qty)
          : String(item.total);
      }
      amount.value = amountValue;
      amountWrap.appendChild(amountLabel);
      amountWrap.appendChild(amount);

      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "button button-link-delete";
      remove.textContent = "Remove";
      remove.setAttribute("data-line-items-remove", "1");
      const actionWrap = document.createElement("div");
      actionWrap.className = "cds-line-item-actions-row";
      actionWrap.appendChild(remove);

      if (item && (item.unit_price != null || item.total != null)) {
        row.dataset.lineItemUnitPrice = item.unit_price != null ? String(item.unit_price) : "";
        row.dataset.lineItemTotal = item.total != null ? String(item.total) : "";
      }

      row.appendChild(descriptionWrap);
      row.appendChild(quantityWrap);
      row.appendChild(amountWrap);
      row.appendChild(actionWrap);

      return row;
    }

    function ensureLineItemsRowsContainer(root) {
      if (!root) {
        return null;
      }
      let rows = root.querySelector(".cds-line-items-rows");
      if (!rows) {
        rows = document.createElement("div");
        rows.className = "cds-line-items-rows";
        root.prepend(rows);
      }
      return rows;
    }

    function renderLineItemsRoot(root, items) {
      const rows = ensureLineItemsRowsContainer(root);
      if (!rows) {
        return;
      }
      rows.innerHTML = "";
      const normalized = Array.isArray(items) && items.length > 0 ? items : [{}];
      normalized.forEach((item) => rows.appendChild(createLineItemRow(item)));
    }

    function readLineItems(root) {
      if (!root) {
        return [];
      }
      return Array.from(root.querySelectorAll("[data-line-item-row]"))
        .map((row) => {
          const descriptionNode = row.querySelector('[data-line-item-field="description"]');
          const quantityNode = row.querySelector('[data-line-item-field="quantity"]');
          const amountNode = row.querySelector('[data-line-item-field="total"]');

          const description = descriptionNode ? String(descriptionNode.value || "").trim() : "";
          const quantity = quantityNode ? coerceLineItemNumber(quantityNode.value) : "";
          const amount = amountNode ? coerceLineItemNumber(amountNode.value) : "";
          const unitPrice = amount === "" ? coerceLineItemNumber(row.dataset.lineItemUnitPrice || "") : amount;
          const legacyTotal = coerceLineItemNumber(row.dataset.lineItemTotal || "");
          const total = (unitPrice !== "" && quantity !== "")
            ? Number(unitPrice) * Number(quantity)
            : legacyTotal;

          if (description === "" && quantity === "" && total === "") {
            return null;
          }

          let computedUnitPrice = unitPrice === "" ? 0 : unitPrice;
          if ((computedUnitPrice <= 0) && quantity !== "" && Number(quantity) > 0 && total !== "") {
            computedUnitPrice = Number(total) / Number(quantity);
          }

          return {
            description,
            quantity: quantity === "" ? 0 : quantity,
            unit_price: computedUnitPrice,
            total: total === "" ? 0 : total,
          };
        })
        .filter(Boolean);
    }

    function deriveLegacyReceiptFields(payload, items) {
      if (!Array.isArray(items) || items.length === 0) {
        payload.cargo_type = "";
        payload.quantity = "";
        payload.taxable_value = "";
        payload.amount_paid = "";
        return;
      }
      const descriptions = items.map((item) => String(item.description || "").trim()).filter(Boolean);
      const quantity = items.reduce((sum, item) => {
        const value = Number(item.quantity || 0);
        return Number.isFinite(value) ? sum + value : sum;
      }, 0);
      const total = items.reduce((sum, item) => {
        const value = Number(item.total || item.amount || 0);
        return Number.isFinite(value) ? sum + value : sum;
      }, 0);

      payload.cargo_type = descriptions.join(", ");
      payload.quantity = quantity;
      payload.amount_paid = total;
    }

    function getLineItemsFromPayload(payload, key) {
      const items = payload && Array.isArray(payload[key]) ? payload[key] : [];
      if (items.length > 0) {
        return items;
      }

      const description = payload && Object.prototype.hasOwnProperty.call(payload, "cargo_type") ? String(payload.cargo_type || "").trim() : "";
      const quantity = payload && Object.prototype.hasOwnProperty.call(payload, "quantity") ? coerceLineItemNumber(payload.quantity) : "";
      const total = payload && Object.prototype.hasOwnProperty.call(payload, "taxable_value") ? coerceLineItemNumber(payload.taxable_value) : "";
      if (description === "" && quantity === "" && total === "") {
        return [];
      }

      return [{
        description,
        quantity: quantity === "" ? 0 : quantity,
        unit_price: total === "" ? 0 : total,
        total: (quantity === "" || total === "") ? 0 : Number(quantity) * Number(total),
      }];
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
      const { payloadFields, checkboxGroups, lineItemsRoots } = ensureNodeCache();
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
      lineItemsRoots.forEach((root) => {
        const key = root.getAttribute("data-payload-key") || "line_items";
        const items = readLineItems(root);
        payload[key] = items;
        if (key === "line_items") {
          deriveLegacyReceiptFields(payload, items);
        }
      });
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
      const { payloadFields, checkboxGroups, lineItemsRoots } = ensureNodeCache();
      lineItemsRoots.forEach((root) => {
        const key = root.getAttribute("data-payload-key") || "line_items";
        renderLineItemsRoot(root, getLineItemsFromPayload(payload, key));
      });
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

    function addLineItemRow(triggerNode) {
      const root = triggerNode ? triggerNode.closest("[data-line-items-root]") : null;
      const rows = ensureLineItemsRowsContainer(root);
      if (!rows) {
        return;
      }
      rows.appendChild(createLineItemRow({}));
    }

    function removeLineItemRow(triggerNode) {
      const row = triggerNode ? triggerNode.closest("[data-line-item-row]") : null;
      const root = triggerNode ? triggerNode.closest("[data-line-items-root]") : null;
      if (!row || !root) {
        return;
      }
      const rows = ensureLineItemsRowsContainer(root);
      if (!rows) {
        return;
      }
      row.remove();
      if (rows.querySelectorAll("[data-line-item-row]").length === 0) {
        rows.appendChild(createLineItemRow({}));
      }
    }

    return {
      tryParsePayload,
      syncPayloadFromForm,
      syncFormFromPayload,
      invalidateNodeCache,
      addLineItemRow,
      removeLineItemRow,
    };
  }

  window.CDS_DOCS_PAYLOAD_SYNC = {
    create: createPayloadSync,
  };
})(window);
