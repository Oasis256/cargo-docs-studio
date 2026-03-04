(function (window) {
  "use strict";

  function bindListEvents(deps) {
    const { els, state, listModule, showStatus, formatApiError } = deps;

    if (els.listFilter) {
      els.listFilter.addEventListener("change", () => {
        listModule.refreshListFromControls(1).catch((err) => showStatus(formatApiError(err), "error"));
      });
    }

    if (els.listSearchBtn) {
      els.listSearchBtn.addEventListener("click", () => {
        listModule.refreshListFromControls(1).catch((err) => showStatus(formatApiError(err), "error"));
      });
    }

    if (els.listSearch) {
      els.listSearch.addEventListener("keydown", (event) => {
        if (event.key !== "Enter") {
          return;
        }
        event.preventDefault();
        listModule.refreshListFromControls(1).catch((err) => showStatus(formatApiError(err), "error"));
      });
    }

    if (els.prevPageBtn) {
      els.prevPageBtn.addEventListener("click", () => {
        if (state.listPage <= 1) {
          return;
        }
        listModule.refreshListFromControls(state.listPage - 1).catch((err) => showStatus(formatApiError(err), "error"));
      });
    }

    if (els.nextPageBtn) {
      els.nextPageBtn.addEventListener("click", () => {
        if (state.listPage >= state.listPages) {
          return;
        }
        listModule.refreshListFromControls(state.listPage + 1).catch((err) => showStatus(formatApiError(err), "error"));
      });
    }

    listModule.bindListDelete();
  }

  window.CDS_DOCS_EVENTS_LIST = {
    bindListEvents,
  };
})(window);
