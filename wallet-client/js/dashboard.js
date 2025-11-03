// js/dashboard.js — fixed version
document.addEventListener("DOMContentLoaded", function () {
  // ✅ SAFE: Use optional chaining with fallback
  const API_BASE_URL = window.APP_CONFIG?.API_BASE_URL || 
    'https://boxed-reserve-relief-desktop.trycloudflare.com/digital-wallet-plateform/wallet-server/user/v1';
  
  console.log('[dashboard.js] Using API_BASE_URL:', API_BASE_URL);

  const token = localStorage.getItem("jwt") || sessionStorage.getItem("jwt");
  if (!token) {
    window.location.href = "login.html";
    return;
  }
  const axiosConfig = { headers: { Authorization: `Bearer ${token}` } };

  // ===== PROFILE =====
  if (document.querySelector(".dashboard-user-name") && document.querySelector(".dashboard-user-meta")) {
    axios
      .get(`${API_BASE_URL}/get_profile.php`, axiosConfig)
      .then((response) => {
        if (response.data?.error) {
          console.error("Profile error:", response.data.error);
          return;
        }
        const user = response.data?.user;
        if (user) {
          const nameElem = document.querySelector(".dashboard-user-name");
          const metaElem = document.querySelector(".dashboard-user-meta");
          if (nameElem) nameElem.textContent = user.email || "User";
          if (metaElem) metaElem.textContent = `VIP Level: ${user.role === 1 ? "Admin" : "Regular User"}`;
        }
      })
      .catch((error) => console.error("Profile fetch error:", error));
  }

  // ===== VERIFICATION =====
  if (document.getElementById("verificationWidget")) {
    axios
      .get(`${API_BASE_URL}/get_verification_status.php`, axiosConfig)
      .then((response) => {
        const data = response.data || {};
        const titleElem = document.getElementById("verificationTitle");
        const msgElem = document.getElementById("verificationMessage");
        const btnElem = document.getElementById("verificationButton");

        if (data.is_validated) {
          if (titleElem) titleElem.textContent = "✓ Verified";
          if (msgElem) msgElem.textContent = "Your account is verified.";
          if (btnElem) btnElem.style.display = "none";
        } else {
          if (titleElem) titleElem.textContent = "⚠ Not Verified";
          if (msgElem) msgElem.textContent = "Please verify your account to unlock all features.";
          if (btnElem) {
            btnElem.style.display = "inline-block";
            btnElem.textContent = "Verify Now";
            btnElem.onclick = () => window.location.href = "verification.html";
          }
        }
      })
      .catch((error) => console.error("Verification fetch error:", error));
  }

  // ===== BALANCE =====
  const balanceAmountElem = document.getElementById("balanceAmount");
  if (balanceAmountElem) {
    async function fetchBalance() {
      try {
        const rAll = await axios.get(`${API_BASE_URL}/get_balance.php`, axiosConfig);
        const map = rAll.data?.balances || {};
        if (map && Object.keys(map).length) {
          if (map.USDT !== undefined)
            return { amount: Number(map.USDT), symbol: "USDT" };
          const [sym, amt] = Object.entries(map).sort((a, b) => Number(b[1]) - Number(a[1]))[0] || ["USDT", 0];
          return { amount: Number(amt || 0), symbol: sym };
        }
      } catch (err) {
        console.error("get_balance.php failed:", err);
      }
      
      // Fallback
      try {
        const r = await axios.get(`${API_BASE_URL}/get_balances.php`, axiosConfig);
        return { amount: Number(r.data?.balance ?? 0), symbol: "USDT" };
      } catch (err) {
        console.error("get_balances.php failed:", err);
        throw err;
      }
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
    qrImg.src = `${API_BASE_URL}/../../utils/generate_qr.php?recipient_id=${encodeURIComponent(userId)}&amount=10`;
  }

  // Call setQr if you have userId available
  const userId = localStorage.getItem("userId");
  if (userId) setQr(userId);

  // ===== LIMITS =====
  if (document.getElementById("dailyInfo")) {
    axios
      .get(`${API_BASE_URL}/get_limits_usage.php`, axiosConfig)
      .then((response) => {
        const data = response.data || {};
        
        const dailyBar = document.getElementById("dailyBar");
        const dailyInfo = document.getElementById("dailyInfo");
        const weeklyBar = document.getElementById("weeklyBar");
        const weeklyInfo = document.getElementById("weeklyInfo");
        const monthlyBar = document.getElementById("monthlyBar");
        const monthlyInfo = document.getElementById("monthlyInfo");

        if (data.daily) {
          const dailyPct = data.daily.limit > 0 ? (data.daily.used / data.daily.limit) * 100 : 0;
          if (dailyBar) dailyBar.style.width = `${dailyPct}%`;
          if (dailyInfo) dailyInfo.textContent = `${data.daily.used} / ${data.daily.limit}`;
        }

        if (data.weekly) {
          const weeklyPct = data.weekly.limit > 0 ? (data.weekly.used / data.weekly.limit) * 100 : 0;
          if (weeklyBar) weeklyBar.style.width = `${weeklyPct}%`;
          if (weeklyInfo) weeklyInfo.textContent = `${data.weekly.used} / ${data.weekly.limit}`;
        }

        if (data.monthly) {
          const monthlyPct = data.monthly.limit > 0 ? (data.monthly.used / data.monthly.limit) * 100 : 0;
          if (monthlyBar) monthlyBar.style.width = `${monthlyPct}%`;
          if (monthlyInfo) monthlyInfo.textContent = `${data.monthly.used} / ${data.monthly.limit}`;
        }
      })
      .catch((error) => console.error("Limits fetch error:", error));
  }
});