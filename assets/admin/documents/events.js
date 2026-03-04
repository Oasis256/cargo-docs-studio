(function (window) {
  "use strict";

  function createEventsModule(deps) {
    function bindAll() {
      window.CDS_DOCS_EVENTS_FORM.bindFormEvents(deps);
      window.CDS_DOCS_EVENTS_LIST.bindListEvents(deps);
      window.CDS_DOCS_EVENTS_RESULT.bindResultEvents(deps);
    }

    function bootstrapInitialState() {
      window.CDS_DOCS_EVENTS_FORM.bootstrapInitialState(deps);
    }

    return {
      bindAll,
      bootstrapInitialState,
    };
  }

  window.CDS_DOCS_EVENTS = {
    create: createEventsModule,
  };
})(window);
