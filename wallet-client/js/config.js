// config.js - API Configuration
(function () {
  "use strict";

  // Detect environment
  const isLocal =
    window.location.hostname === "localhost" ||
    window.location.hostname === "127.0.0.1";

  // Set API base URL based on environment
  let API_BASE_URL;

  if (isLocal) {
    // Local development
    API_BASE_URL =
      "http://localhost/digital-wallet-plateform/wallet-server/user/v1";
  } else {
    // Production - Using NEW Cloudflare Tunnel
    API_BASE_URL =
      "https://templates-bridge-michelle-ranked.trycloudflare.com/digital-wallet-plateform/wallet-server/user/v1";
  }

  // Make config globally available
  window.APP_CONFIG = {
    API_BASE_URL: API_BASE_URL,
  };

  console.log("[config] API_BASE_URL =", API_BASE_URL);
})();
