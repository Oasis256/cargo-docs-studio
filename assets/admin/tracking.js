(function () {
  if (!window.CDS_ADMIN || window.CDS_ADMIN.page !== "cargo-docs-studio-tracking") {
    return;
  }

  const btn = document.getElementById("cds-track-use-gps");
  const latInput = document.getElementById("cds-stop-lat");
  const lngInput = document.getElementById("cds-stop-lng");
  const statusEl = document.getElementById("cds-track-geo-status");
  const sourceEl = document.getElementById("cds-track-geo-source");
  const accuracyEl = document.getElementById("cds-track-geo-accuracy");
  const capturedAtEl = document.getElementById("cds-track-geo-captured-at");

  if (!btn || !latInput || !lngInput || !statusEl || !sourceEl || !accuracyEl || !capturedAtEl) {
    return;
  }

  const setStatus = (message, mode) => {
    statusEl.textContent = message;
    statusEl.classList.remove("cds-geo-ok", "cds-geo-warn", "cds-geo-error");
    if (mode) {
      statusEl.classList.add(mode);
    }
  };

  const setManualSource = () => {
    if (sourceEl.value !== "gps") {
      sourceEl.value = "manual";
    }
  };

  latInput.addEventListener("input", setManualSource);
  lngInput.addEventListener("input", setManualSource);

  btn.addEventListener("click", () => {
    if (!navigator.geolocation) {
      setStatus("Geolocation is not supported in this browser.", "cds-geo-error");
      return;
    }

    btn.disabled = true;
    setStatus("Getting GPS fix...", "cds-geo-warn");

    navigator.geolocation.getCurrentPosition(
      (position) => {
        const coords = position.coords || {};
        const lat = Number(coords.latitude);
        const lng = Number(coords.longitude);
        const accuracy = Number(coords.accuracy);

        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
          setStatus("GPS returned invalid coordinates.", "cds-geo-error");
          btn.disabled = false;
          return;
        }

        latInput.value = lat.toFixed(6);
        lngInput.value = lng.toFixed(6);
        sourceEl.value = "gps";
        accuracyEl.value = Number.isFinite(accuracy) ? accuracy.toFixed(1) : "";
        capturedAtEl.value = new Date().toISOString();
        setStatus(
          Number.isFinite(accuracy)
            ? `GPS captured: ${latInput.value}, ${lngInput.value} (±${accuracyEl.value}m)`
            : `GPS captured: ${latInput.value}, ${lngInput.value}`,
          "cds-geo-ok"
        );
        btn.disabled = false;
      },
      (error) => {
        let msg = "Unable to capture GPS coordinates.";
        if (error && typeof error.code === "number") {
          if (error.code === 1) {
            msg = "GPS permission denied.";
          } else if (error.code === 2) {
            msg = "GPS position unavailable.";
          } else if (error.code === 3) {
            msg = "GPS request timed out.";
          }
        }
        setStatus(msg, "cds-geo-error");
        btn.disabled = false;
      },
      {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 30000,
      }
    );
  });
})();
