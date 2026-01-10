// js/dashboard.js — WITH BURGER MENU TOGGLE

// ===== BURGER MENU TOGGLE (Global function) =====
function toggleActionMenu() {
  const dropdown = document.getElementById("actionDropdown");
  if (dropdown) {
    dropdown.classList.toggle("show");
  }
}

// Close dropdown when clicking outside
document.addEventListener("click", function (event) {
  const dropdown = document.getElementById("actionDropdown");
  const burgerBtn = document.querySelector(".burger-menu-btn");
  
  if (dropdown && burgerBtn) {
    if (!dropdown.contains(event.target) && !burgerBtn.contains(event.target)) {
      dropdown.classList.remove("show");
    }
  }
});

document.addEventListener("DOMContentLoaded", function () {
  // ✅ Use config with fallback
  const API_BASE_URL =
    window.APP_CONFIG?.API_BASE_URL ||
    "https://sixth-audit-valuable-until.trycloudflare.com/digital-wallet-plateform/wallet-server/user/v1";

  console.log("[dashboard.js] Using API_BASE_URL:", API_BASE_URL);

  const token = localStorage.getItem("jwt") || sessionStorage.getItem("jwt");
  if (!token) {
    window.location.href = "login.html";
    return;
  }
  const axiosConfig = { headers: { Authorization: `Bearer ${token}` } };

  // ===== PROFILE =====
  if (
    document.querySelector(".dashboard-user-name") &&
    document.querySelector(".dashboard-user-meta")
  ) {
    axios
      .get(`${API_BASE_URL}/get_profile.php`, axiosConfig)
      .then((response) => {
        console.log("[dashboard.js] Profile response:", response.data);

        if (response.data?.error) {
          console.error("Profile error:", response.data.error);
          return;
        }

        const user = response.data?.user || response.data;

        if (user && !user.error) {
          const nameElem = document.querySelector(".dashboard-user-name");
          const metaElem = document.querySelector(".dashboard-user-meta");

          let displayName = "User";

          if (user.full_name && user.full_name.trim()) {
            displayName = user.full_name;
          } else if (user.first_name && user.last_name) {
            displayName = `${user.first_name} ${user.last_name}`;
          } else if (user.first_name) {
            displayName = user.first_name;
          } else if (user.username) {
            displayName = user.username;
          } else if (user.email) {
            displayName = user.email.split("@")[0];
          }

          if (nameElem) nameElem.textContent = displayName;

          const tierLabel =
            user.tier === "premium"
              ? "Premium User"
              : user.tier === "vip"
              ? "VIP User"
              : "Regular User";
          if (metaElem) metaElem.textContent = `VIP Level: ${tierLabel}`;
        }
      })
      .catch((error) => {
        console.error("Profile fetch error:", error);
        const nameElem = document.querySelector(".dashboard-user-name");
        if (nameElem) nameElem.textContent = "Error Loading Profile";
      });
  }

  // ===== VERIFICATION STATUS CHECK =====
  if (document.getElementById("verificationWidget")) {
    axios
      .get(`${API_BASE_URL}/get_verification_status.php`, axiosConfig)
      .then((response) => {
        console.log("[dashboard.js] Verification response:", response.data);

        const data = response.data || {};
        const titleElem = document.getElementById("verificationTitle");
        const msgElem = document.getElementById("verificationMessage");
        const btnElem = document.getElementById("verificationButton");

        const validationStatus = parseInt(data.is_validated);

        if (validationStatus === 1) {
          if (titleElem) {
            titleElem.textContent = "✓ Verified";
            titleElem.style.color = "#10b981";
          }
          if (msgElem) {
            msgElem.textContent = "Your account is verified.";
            msgElem.style.color = "#059669";
          }
          if (btnElem) btnElem.style.display = "none";
        } else if (validationStatus === -1) {
          if (titleElem) {
            titleElem.textContent = "❌ Verification Rejected";
            titleElem.style.color = "#ef4444";
          }
          if (msgElem) {
            const reason =
              data.validation_note || "Your verification was rejected.";
            msgElem.textContent = `${reason} Please submit again with correct documents.`;
            msgElem.style.color = "#dc2626";
          }
          if (btnElem) {
            btnElem.style.display = "inline-block";
            btnElem.textContent = "Resubmit Verification";
            btnElem.style.backgroundColor = "#ef4444";
            btnElem.onclick = () =>
              (window.location.href = "verifications.html");
          }
        } else {
          if (titleElem) {
            titleElem.textContent = "⚠ Not Verified";
            titleElem.style.color = "#f59e0b";
          }
          if (msgElem) {
            msgElem.textContent =
              "Please verify your account to unlock all features.";
            msgElem.style.color = "#d97706";
          }
          if (btnElem) {
            btnElem.style.display = "inline-block";
            btnElem.textContent = "Verify Now";
            btnElem.style.backgroundColor = "#3b82f6";
            btnElem.onclick = () =>
              (window.location.href = "verifications.html");
          }
        }
      })
      .catch((error) => {
        console.error("Verification fetch error:", error);

        const titleElem = document.getElementById("verificationTitle");
        const msgElem = document.getElementById("verificationMessage");

        if (titleElem) {
          titleElem.textContent = "⚠ Error Loading Status";
          titleElem.style.color = "#ef4444";
        }
        if (msgElem) {
          msgElem.textContent =
            "Could not load verification status. Please refresh the page.";
          msgElem.style.color = "#dc2626";
        }
      });
  }

  // ===== BALANCE =====
  const balanceAmountElem = document.getElementById("balanceAmount");
  if (balanceAmountElem) {
    async function fetchBalance() {
      try {
        const response = await axios.get(
          `${API_BASE_URL}/get_balances.php`,
          axiosConfig
        );
        console.log("[dashboard.js] Balance response:", response.data);

        const data = response.data;

        if (data.success && data.balances) {
          const balances = data.balances;

          if (balances.USDT !== undefined) {
            return { amount: Number(balances.USDT), symbol: "USDT" };
          }

          const entries = Object.entries(balances);
          if (entries.length > 0) {
            const [symbol, amount] = entries[0];
            return { amount: Number(amount), symbol };
          }
        }

        return { amount: 0, symbol: "USDT" };
      } catch (err) {
        console.error("Balance fetch failed:", err);
        throw err;
      }
    }

    (async () => {
      try {
        balanceAmountElem.textContent = "Loading...";
        const { amount, symbol } = await fetchBalance();
        balanceAmountElem.textContent = `${amount.toFixed(2)} ${symbol}`;
      } catch {
        balanceAmountElem.textContent = "Error Loading Balance";
      }
    })();
  }

  // ===== QR =====
  const qrImg = document.getElementById("sidebarQr");
  function setQr(userId) {
    if (!qrImg || !userId) return;
    qrImg.src = `${API_BASE_URL}/../../utils/generate_qr.php?recipient_id=${encodeURIComponent(
      userId
    )}&amount=10`;
  }

  const userId = localStorage.getItem("userId");
  if (userId) setQr(userId);

  // ===== LIMITS =====
  if (document.getElementById("dailyInfo")) {
    axios
      .get(`${API_BASE_URL}/get_limits_usage.php`, axiosConfig)
      .then((response) => {
        console.log("[dashboard.js] Limits response:", response.data);

        const data = response.data || {};

        const dailyBar = document.getElementById("dailyBar");
        const dailyInfo = document.getElementById("dailyInfo");
        const weeklyBar = document.getElementById("weeklyBar");
        const weeklyInfo = document.getElementById("weeklyInfo");
        const monthlyBar = document.getElementById("monthlyBar");
        const monthlyInfo = document.getElementById("monthlyInfo");

        function setBarColor(bar, percentage) {
          if (!bar) return;
          if (percentage >= 90) {
            bar.style.backgroundColor = "#ef4444";
          } else if (percentage >= 70) {
            bar.style.backgroundColor = "#f59e0b";
          } else if (percentage >= 50) {
            bar.style.backgroundColor = "#eab308";
          } else {
            bar.style.backgroundColor = "#10b981";
          }
        }

        const dailyUsed = data.dailyUsed || 0;
        const dailyLimit = data.dailyLimit || 500;
        const dailyPct = dailyLimit > 0 ? (dailyUsed / dailyLimit) * 100 : 0;
        if (dailyBar) {
          dailyBar.style.width = `${Math.min(dailyPct, 100)}%`;
          setBarColor(dailyBar, dailyPct);
        }
        if (dailyInfo)
          dailyInfo.textContent = `${dailyUsed.toFixed(2)} / ${dailyLimit}`;

        const weeklyUsed = data.weeklyUsed || 0;
        const weeklyLimit = data.weeklyLimit || 2000;
        const weeklyPct =
          weeklyLimit > 0 ? (weeklyUsed / weeklyLimit) * 100 : 0;
        if (weeklyBar) {
          weeklyBar.style.width = `${Math.min(weeklyPct, 100)}%`;
          setBarColor(weeklyBar, weeklyPct);
        }
        if (weeklyInfo)
          weeklyInfo.textContent = `${weeklyUsed.toFixed(2)} / ${weeklyLimit}`;

        const monthlyUsed = data.monthlyUsed || 0;
        const monthlyLimit = data.monthlyLimit || 5000;
        const monthlyPct =
          monthlyLimit > 0 ? (monthlyUsed / monthlyLimit) * 100 : 0;
        if (monthlyBar) {
          monthlyBar.style.width = `${Math.min(monthlyPct, 100)}%`;
          setBarColor(monthlyBar, monthlyPct);
        }
        if (monthlyInfo)
          monthlyInfo.textContent = `${monthlyUsed.toFixed(
            2
          )} / ${monthlyLimit}`;
      })
      .catch((error) => {
        console.error("Limits fetch error:", error);

        const dailyInfo = document.getElementById("dailyInfo");
        const weeklyInfo = document.getElementById("weeklyInfo");
        const monthlyInfo = document.getElementById("monthlyInfo");

        if (dailyInfo) dailyInfo.textContent = "0 / 500";
        if (weeklyInfo) weeklyInfo.textContent = "0 / 2000";
        if (monthlyInfo) monthlyInfo.textContent = "0 / 5000";
      });
  }
});