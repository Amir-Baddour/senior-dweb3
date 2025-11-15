// Admin config.js - API Configuration
(function () {
  "use strict";

  // Detect environment
  const isLocal =
    location.hostname === "localhost" || location.hostname === "127.0.0.1";

  const basePath = "/digital-wallet-plateform/wallet-server/admin/v1";

  // ✅ Auto-detect: Always use current origin (works with any tunnel URL)
  const API_BASE_URL = isLocal 
    ? `http://localhost${basePath}`
    : `${location.origin}${basePath}`;

  // Make config globally available
  window.ADMIN_CONFIG = { API_BASE_URL };
  
  console.log("[admin-config] API_BASE_URL =", API_BASE_URL);
})();