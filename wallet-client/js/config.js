(function () {
  const isLocal =
    location.hostname === "localhost" || location.hostname === "127.0.0.1";
  
  const basePath = "/digital-wallet-plateform/wallet-server/user/v1";
  
  // ✅ Simple: Always use current origin + path
  const API_BASE_URL = isLocal 
    ? `http://localhost${basePath}`
    : `${location.origin}${basePath}`;

  window.APP_CONFIG = { API_BASE_URL };
  console.log("[config] API_BASE_URL =", API_BASE_URL);
})();