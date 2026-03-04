(function (window) {
  "use strict";

  function getElements(doc) {
    return {
      docType: doc.getElementById("cds-doc-gen-type"),
      listFilter: doc.getElementById("cds-doc-list-filter"),
      templateRevision: doc.getElementById("cds-doc-template-revision"),
      reloadRevisionsBtn: doc.getElementById("cds-reload-revisions"),
      revisionNote: doc.getElementById("cds-doc-revision-note"),
      payload: doc.getElementById("cds-doc-payload-json"),
      modeFormBtn: doc.getElementById("cds-doc-mode-form"),
      modeJsonBtn: doc.getElementById("cds-doc-mode-json"),
      formBuilder: doc.getElementById("cds-doc-form-builder"),
      autoFixBtn: doc.getElementById("cds-autofix-payload"),
      generateBtn: doc.getElementById("cds-generate-document"),
      checklist: doc.getElementById("cds-doc-required-checklist"),
      hints: doc.getElementById("cds-doc-validation-hints"),
      status: doc.getElementById("cds-doc-status"),
      result: doc.getElementById("cds-doc-result"),
      documentsList: doc.getElementById("cds-documents-list"),
      listSearch: doc.getElementById("cds-doc-list-search"),
      listSearchBtn: doc.getElementById("cds-doc-list-search-btn"),
      listMeta: doc.getElementById("cds-doc-list-meta"),
      prevPageBtn: doc.getElementById("cds-doc-prev-page"),
      nextPageBtn: doc.getElementById("cds-doc-next-page"),
      pageInfo: doc.getElementById("cds-doc-page-info"),
    };
  }

  function createState() {
    return {
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
  }

  window.CDS_DOCS_STATE = {
    getElements: getElements,
    createState: createState,
  };
})(window);
