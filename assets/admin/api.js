(function () {
  "use strict";

  function getConfig() {
    const cfg = window.CDS_ADMIN || {};
    const restBase = String(cfg.rest_base || "");
    const nonce = String(cfg.nonce || "");
    return { restBase, nonce };
  }

  function request(path, options) {
    const cfg = getConfig();
    const req = Object.assign(
      {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": cfg.nonce,
        },
      },
      options || {}
    );

    return fetch(cfg.restBase + path, req).then(async (res) => {
      const data = await res.json().catch(() => ({}));
      if (!res.ok || data.ok === false) {
        const err = new Error((data && (data.message || data.error)) || `Request failed (${res.status})`);
        err.cds = {
          code: (data && data.code) || "request_failed",
          fields: Array.isArray(data && data.fields) ? data.fields : [],
          status: res.status,
        };
        throw err;
      }
      return data;
    });
  }

  function getErrorFields(err) {
    return err && err.cds && Array.isArray(err.cds.fields) ? err.cds.fields : [];
  }

  function formatError(err) {
    const base = String((err && err.message) || "Request failed.");
    const fields = getErrorFields(err);
    if (fields.length === 0) {
      return base;
    }
    const details = fields
      .map((f) => {
        const field = String((f && f.field) || "").trim();
        const msg = String((f && f.message) || "").trim();
        if (!field && !msg) {
          return "";
        }
        return field ? `${field}: ${msg || "Invalid value."}` : msg;
      })
      .filter(Boolean)
      .join(" | ");

    return details ? `${base} ${details}` : base;
  }

  window.CDS_API = {
    request,
    formatError,
    getErrorFields,
  };
})();
