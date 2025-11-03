(function () {
  const isLocal =
    location.hostname === "localhost" || location.hostname === "127.0.0.1";
  
  const LOCAL_API =
    "http://localhost/digital-wallet-plateform/wallet-server/admin/v1";

  // ✅ Cloudflare Tunnel URL for admin endpoints
  const PROD_API =
    "https://boxed-reserve-relief-desktop.trycloudflare.com/digital-wallet-plateform/wallet-server/admin/v1";

  window.ADMIN_CONFIG = { API_BASE_URL: isLocal ? LOCAL_API : PROD_API };
  console.log("[admin-config] API_BASE_URL =", window.ADMIN_CONFIG.API_BASE_URL);
})();
