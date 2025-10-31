(function () {
  const isLocal = location.hostname === 'localhost' || location.hostname === '127.0.0.1';
  const LOCAL_API = 'http://localhost/digital-wallet-plateform/wallet-server/user/v1';

  // ⬇️ Replace with YOUR current ngrok https URL
  const PROD_API  = 'https://cagelike-georgina-unrustically.ngrok-free.dev/digital-wallet-plateform/wallet-server/user/v1';

  window.APP_CONFIG = { API_BASE_URL: isLocal ? LOCAL_API : PROD_API };
  console.log('[config] API_BASE_URL =', window.APP_CONFIG.API_BASE_URL);
})();
