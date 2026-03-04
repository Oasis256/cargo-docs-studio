(function (window) {
  "use strict";

  function bindResultEvents(deps) {
    const { els, state, api, listModule, showStatus, formatApiError, copyTextToClipboard } = deps;

    if (!els.result) {
      return;
    }

    els.result.addEventListener("click", (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }
      if (target.getAttribute("data-action") === "delete-result-pdf") {
        const documentId = Number(target.getAttribute("data-document-id") || 0);
        if (!Number.isFinite(documentId) || documentId <= 0) {
          showStatus("Invalid document id.", "error");
          return;
        }
        if (!window.confirm("Delete PDF for this generated document?")) {
          return;
        }
        target.setAttribute("disabled", "disabled");
        api(`/documents/${documentId}/pdf`, { method: "DELETE" })
          .then(() => {
            showStatus("PDF deleted successfully.", "success");
            return listModule.refreshListFromControls(state.listPage || 1);
          })
          .then(() => {
            const actionsWrap = target.closest(".cds-result-actions");
            if (actionsWrap) {
              const pdfOpenAction = actionsWrap.querySelector('[data-action="open-result-pdf"]');
              if (pdfOpenAction) {
                pdfOpenAction.remove();
              }
            }
            target.remove();
          })
          .catch((err) => showStatus(formatApiError(err), "error"))
          .finally(() => target.removeAttribute("disabled"));
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

  window.CDS_DOCS_EVENTS_RESULT = {
    bindResultEvents,
  };
})(window);
