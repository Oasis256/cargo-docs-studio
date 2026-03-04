(function (window) {
  "use strict";

  const { escapeHtml } = window.CDS_DOCS_UTILS;

  function createListModule(deps) {
    const { els, state, api, showStatus, formatApiError } = deps;

    function getListFilterValue() {
      if (!els.listFilter) {
        return "";
      }
      return String(els.listFilter.value || "").toLowerCase();
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
      const search = state.listSearch ? `, search: \"${state.listSearch}\"` : "";
      els.listMeta.textContent = `Total: ${state.listTotal} documents (filter: ${filter}${search}).`;
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
            const docId = Number(d.id || 0);
            const link = d.pdf_url
              ? `<a href="${escapeHtml(d.pdf_url)}" target="_blank" rel="noopener">Open PDF</a>
               <button class="button-link-delete" type="button" data-action="delete-pdf" data-document-id="${docId}">Delete PDF</button>`
              : "No file";
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

    function refreshListFromControls(page) {
      const filter = getListFilterValue() || "all";
      const search = els.listSearch ? String(els.listSearch.value || "").trim() : state.listSearch;
      const targetPage = Number(page || 1);
      return loadRecentDocuments(filter, targetPage, search);
    }

    function bindListDelete() {
      if (!els.documentsList) {
        return;
      }

      els.documentsList.addEventListener("click", (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }
        if (target.getAttribute("data-action") !== "delete-pdf") {
          return;
        }

        const documentId = Number(target.getAttribute("data-document-id") || 0);
        if (!Number.isFinite(documentId) || documentId <= 0) {
          showStatus("Invalid document id.", "error");
          return;
        }

        if (!window.confirm("Delete PDF for this document?")) {
          return;
        }

        target.setAttribute("disabled", "disabled");
        api(`/documents/${documentId}/pdf`, { method: "DELETE" })
          .then(() => {
            showStatus("PDF deleted successfully.", "success");
            return refreshListFromControls(state.listPage || 1);
          })
          .catch((err) => {
            showStatus(formatApiError(err), "error");
          })
          .finally(() => {
            target.removeAttribute("disabled");
          });
      });
    }

    return {
      getListFilterValue,
      loadRecentDocuments,
      renderPagination,
      renderListMeta,
      refreshListFromControls,
      bindListDelete,
    };
  }

  window.CDS_DOCS_LIST = {
    create: createListModule,
  };
})(window);
