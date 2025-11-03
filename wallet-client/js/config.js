(function () {
  const isLocal =
    location.hostname === "localhost" || location.hostname === "127.0.0.1";
  const LOCAL_API =
    "http://localhost/digital-wallet-plateform/wallet-server/user/v1";

  // ✅ Cloudflare Tunnel URL
  const PROD_API =
    "https://celebs-gained-park-leader.trycloudflare.com/digital-wallet-plateform/wallet-server/user/v1";

  window.APP_CONFIG = { API_BASE_URL: isLocal ? LOCAL_API : PROD_API };
  console.log("[config] API_BASE_URL =", window.APP_CONFIG.API_BASE_URL);
})();
