(function () {
  const isLocal =
    location.hostname === "localhost" || location.hostname === "127.0.0.1";
  
  const isVercel = location.hostname.includes("vercel.app");
  const basePath = "/digital-wallet-plateform/wallet-server/user/v1";
  
  let API_BASE_URL;
  
  if (isLocal) {
    API_BASE_URL = `http://localhost${basePath}`;
  } else if (isVercel) {
    // ✅ Updated to new tunnel URL
    API_BASE_URL = `https://coming-chapter-vintage-handles.trycloudflare.com${basePath}`;
  } else {
    API_BASE_URL = `${location.origin}${basePath}`;
  }

  window.APP_CONFIG = { API_BASE_URL };
  console.log("[config] API_BASE_URL =", API_BASE_URL);
})();