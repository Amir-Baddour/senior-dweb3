// Admin config.js - API Configuration
(function () {
  "use strict";

  const isLocal =
    location.hostname === "localhost" || location.hostname === "127.0.0.1";
  
  const isVercel = location.hostname.includes("vercel.app");
  const isCloudflare = location.hostname.includes("trycloudflare.com");

  const basePath = "/digital-wallet-plateform/wallet-server/admin/v1";

  let API_BASE_URL;

  if (isLocal) {
    // Local development
    API_BASE_URL = `http://localhost${basePath}`;
  } else if (isVercel) {
    // ✅ UPDATED: New tunnel URL
    API_BASE_URL = `https://accepted-chemical-nissan-amino.trycloudflare.com${basePath}`;
  } else if (isCloudflare) {
    // ✅ When on Cloudflare tunnel, use same origin
    API_BASE_URL = `${location.origin}${basePath}`;
  } else {
    // Fallback
    API_BASE_URL = `${location.origin}${basePath}`;
  }

  window.ADMIN_CONFIG = { API_BASE_URL };
  console.log("[admin-config] API_BASE_URL =", API_BASE_URL);
})();