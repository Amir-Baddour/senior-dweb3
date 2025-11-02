// js/dashboard.js — fixed version
document.addEventListener("DOMContentLoaded", function () {
  const { API_BASE_URL } = window.APP_CONFIG; // ✅ add this line
  const token = localStorage.getItem("jwt") || sessionStorage.getItem("jwt");
  if (!token) {
    window.location.href = "login.html";
    return;
  }
  const axiosConfig = { headers: { Authorization: `Bearer ${token}` } };

  // ===== PROFILE =====
  if (document.querySelector(".dashboard-user-name") && document.querySelector(".dashboard-user-meta")) {
    axios
      .get(`${API_BASE_URL}/get_profile.php`, axiosConfig) // ✅ use API_BASE_URL
      .then(/* same as before */)
      .catch(/* same as before */);
  }

  // ===== VERIFICATION =====
  if (document.getElementById("verificationWidget")) {
    axios
      .get(`${API_BASE_URL}/get_verification_status.php`, axiosConfig) // ✅
      .then(/* same as before */)
      .catch(/* same as before */);
  }

  // ===== BALANCE =====
  const balanceAmountElem = document.getElementById("balanceAmount");
  if (balanceAmountElem) {
    async function fetchBalance() {
      try {
        const rAll = await axios.get(`${API_BASE_URL}/get_balance.php`, axiosConfig); // ✅
        const map = rAll.data?.balances || {};
        if (map && Object.keys(map).length) {
          if (map.USDT !== undefined)
            return { amount: Number(map.USDT), symbol: "USDT" };
          const [sym, amt] = Object.entries(map).sort((a, b) => Number(b[1]) - Number(a[1]))[0] || ["USDT", 0];
          return { amount: Number(amt || 0), symbol: sym };
        }
      } catch (_) {}
      // Fallback
      const r = await axios.get(`${API_BASE_URL}/get_balances.php`, axiosConfig); // ✅
      return { amount: Number(r.data?.balance ?? 0), symbol: "USDT" };
    }

    (async () => {
      try {
        balanceAmountElem.textContent = "Loading...";
        const { amount, symbol } = await fetchBalance();
        balanceAmountElem.textContent = `${amount} ${symbol}`;
      } catch {
        balanceAmountElem.textContent = "Error Loading Balance";
      }
    })();
  }

  // ===== QR =====
  const qrImg = document.getElementById("sidebarQr");
  function setQr(userId) {
    if (!qrImg || !userId) return;
    qrImg.src = `${API_BASE_URL}/../../utils/generate_qr.php?recipient_id=${encodeURIComponent(userId)}&amount=10`; // ✅
  }

  // ===== LIMITS =====
  if (document.getElementById("dailyInfo")) {
    axios
      .get(`${API_BASE_URL}/get_limits_usage.php`, axiosConfig) // ✅
      .then(/* same as before */)
      .catch(/* same as before */);
  }
});
