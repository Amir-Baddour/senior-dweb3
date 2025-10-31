// js/dashboard.js — final safe build v2.4
document.addEventListener("DOMContentLoaded", function () {
  // ---- Auth ----
  const token = localStorage.getItem("jwt") || sessionStorage.getItem("jwt");
  if (!token) {
    window.location.href = "login.html";
    return;
  }
  const axiosConfig = { headers: { Authorization: `Bearer ${token}` } };

  // ===== PROFILE (only if elements exist) =====
  const userNameElem = document.querySelector(".dashboard-user-name");
  const userMetaElem = document.querySelector(".dashboard-user-meta");
  if (userNameElem && userMetaElem) {
    axios
      .get(
        "http://localhost/digital-wallet-plateform/wallet-server/user/v1/get_profile.php",
        axiosConfig
      )
      .then((response) => {
        if (response.data?.success) {
          const fullName = (response.data.user?.full_name || "").trim();
          const userTier = (response.data.user?.tier || "").trim();
          userNameElem.innerHTML = fullName
            ? fullName
            : 'No name set. <a href="profile.html">Update your profile</a>';
          userMetaElem.textContent = userTier
            ? `User Level: ${userTier}`
            : "User Level: Regular";
        } else {
          userNameElem.textContent = "Unknown User";
          userMetaElem.textContent = "User Level: Unknown";
        }
      })
      .catch(() => {
        userNameElem.textContent = "Error Loading Name";
        userMetaElem.textContent = "User Level: Error";
      });
  }

  // ===== VERIFICATION (only if elements exist) =====
  const verificationWidget = document.getElementById("verificationWidget");
  const verificationTitle = document.getElementById("verificationTitle");
  const verificationMessage = document.getElementById("verificationMessage");
  const verificationButton = document.getElementById("verificationButton");

  if (
    verificationWidget &&
    verificationTitle &&
    verificationMessage &&
    verificationButton
  ) {
    axios
      .get(
        "http://localhost/digital-wallet-plateform/wallet-server/user/v1/get_verification_status.php",
        axiosConfig
      )
      .then((response) => {
        if (response.data?.error) {
          verificationTitle.textContent = "Error";
          verificationMessage.textContent = response.data.error;
          verificationButton.style.display = "none";
          return;
        }
        const status = parseInt(response.data?.is_validated ?? -999, 10);
        verificationWidget.classList.remove(
          "verification-pending",
          "verification-approved",
          "verification-rejected"
        );

        switch (status) {
          case 0:
            verificationWidget.classList.add("verification-pending");
            verificationTitle.textContent = "Verification Pending";
            verificationMessage.textContent =
              "Your documents are under review. Please wait for approval.";
            verificationButton.style.display = "none";
            break;
          case 1:
            verificationWidget.classList.add("verification-approved");
            verificationTitle.textContent = "Account Verified";
            verificationMessage.textContent =
              "Your account is verified. Enjoy full access to our services!";
            verificationButton.style.display = "none";
            break;
          case -1:
            verificationWidget.classList.add("verification-rejected");
            verificationTitle.textContent = "Verification Rejected";
            verificationMessage.textContent =
              "Unfortunately, your verification was rejected. Please resubmit.";
            verificationButton.style.display = "inline-block";
            verificationButton.textContent = "Resubmit";
            verificationButton.onclick = () =>
              (window.location.href = "verification.html");
            break;
          default:
            verificationTitle.textContent = "Not Verified";
            verificationMessage.textContent =
              "No verification record found. Please verify to unlock features.";
            verificationButton.style.display = "inline-block";
            verificationButton.textContent = "Verify Now";
            verificationButton.onclick = () =>
              (window.location.href = "verification.html");
            break;
        }
      })
      .catch(() => {
        verificationTitle.textContent = "Error";
        verificationMessage.textContent = "Unable to load verification status.";
        verificationButton.style.display = "none";
      });
  }

  // ===== BALANCE (robust; shows even if other widgets are absent) =====
  const balanceAmountElem = document.getElementById("balanceAmount");
  if (balanceAmountElem) {
    async function fetchBalance() {
      // Try all-balances first
      try {
        const rAll = await axios.get(
          "http://localhost/digital-wallet-plateform/wallet-server/user/v1/get_balance.php",
          axiosConfig
        );
        const map = rAll.data?.balances || {};
        if (map && Object.keys(map).length) {
          if (map.USDT !== undefined)
            return { amount: Number(map.USDT), symbol: "USDT" };
          const [sym, amt] = Object.entries(map).sort(
            (a, b) => Number(b[1]) - Number(a[1])
          )[0] || ["USDT", 0];
          return { amount: Number(amt || 0), symbol: sym };
        }
      } catch (_) {
        /* fall back */
      }

      // Legacy USDT-only endpoint
      a= "http://localhost/digital-wallet-plateform/wallet-server/user/v1"
      const r = await axios.get(
        "a/get_balances.php",
        axiosConfig
      );
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
  //QR
  const qrImg = document.getElementById("sidebarQr");
  function setQr(userId) {
    if (!qrImg || !userId) return;
    // Adjust amount or other params as you like
    qrImg.src =
      "http://localhost/digital-wallet-plateform/wallet-server/utils/generate_qr.php" +
      "?recipient_id=" +
      encodeURIComponent(userId) +
      "&amount=10";
  }
  // ===== LIMITS (only if elements exist) =====
  const dailyInfo = document.getElementById("dailyInfo");
  const weeklyInfo = document.getElementById("weeklyInfo");
  const monthlyInfo = document.getElementById("monthlyInfo");
  const dailyBar = document.getElementById("dailyBar");
  const weeklyBar = document.getElementById("weeklyBar");
  const monthlyBar = document.getElementById("monthlyBar");

  function updateProgressBar(used, limit, barElem, infoElem) {
    const percent = Math.min(limit > 0 ? (used / limit) * 100 : 0, 100);
    if (barElem) barElem.style.width = percent.toFixed(2) + "%";
    if (infoElem)
      infoElem.textContent = `${Number(used).toFixed(2)} / ${Number(
        limit
      ).toFixed(2)}`;
  }

  if (
    dailyInfo &&
    weeklyInfo &&
    monthlyInfo &&
    dailyBar &&
    weeklyBar &&
    monthlyBar
  ) {
    axios
      .get(
        "http://localhost/digital-wallet-plateform/wallet-server/user/v1/get_limits_usage.php",
        axiosConfig
      )
      .then((response) => {
        if (response.data?.error) {
          dailyInfo.textContent =
            weeklyInfo.textContent =
            monthlyInfo.textContent =
              "Error";
        } else {
          updateProgressBar(
            response.data.dailyUsed,
            response.data.dailyLimit,
            dailyBar,
            dailyInfo
          );
          updateProgressBar(
            response.data.weeklyUsed,
            response.data.weeklyLimit,
            weeklyBar,
            weeklyInfo
          );
          updateProgressBar(
            response.data.monthlyUsed,
            response.data.monthlyLimit,
            monthlyBar,
            monthlyInfo
          );
        }
      })
      .catch(() => {
        dailyInfo.textContent =
          weeklyInfo.textContent =
          monthlyInfo.textContent =
            "Error";
      });
  }
});
