(function (window) {
  "use strict";

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function escapeHtmlAttr(text) {
    return escapeHtml(text).replace(/`/g, "&#096;");
  }

  function escapeRegExp(value) {
    return String(value).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  }

  function copyTextToClipboard(value) {
    const text = String(value || "");
    if (!text) {
      return Promise.reject(new Error("Nothing to copy."));
    }

    if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
      return navigator.clipboard.writeText(text);
    }

    return new Promise((resolve, reject) => {
      const input = document.createElement("textarea");
      input.value = text;
      input.setAttribute("readonly", "readonly");
      input.style.position = "absolute";
      input.style.left = "-9999px";
      document.body.appendChild(input);
      input.select();
      try {
        const ok = document.execCommand("copy");
        document.body.removeChild(input);
        if (!ok) {
          reject(new Error("Copy failed."));
          return;
        }
        resolve();
      } catch (err) {
        document.body.removeChild(input);
        reject(err);
      }
    });
  }

  window.CDS_DOCS_UTILS = {
    escapeHtml,
    escapeHtmlAttr,
    escapeRegExp,
    copyTextToClipboard,
  };
})(window);
