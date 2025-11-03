/* =========================
   login.js  (frontend)
   Requires:
   - window.APP_CONFIG.API_BASE_URL set BEFORE this file
   - axios loaded BEFORE this file
========================= */

// --- HARD GUARD: pick API base even if config is missing ---
// HARD GUARD: pick API base even if config didn't load
const API_BASE = (() => {
  // if inline config ran, use it
  if (window.APP_CONFIG?.API_BASE_URL) return window.APP_CONFIG.API_BASE_URL;

  const isLocal =
    location.hostname === "localhost" || location.hostname === "127.0.0.1";
  if (isLocal)
    return "http://localhost/digital-wallet-plateform/wallet-server/user/v1";

  // PRODUCTION FALLBACK: your CURRENT HTTPS ngrok base (replace below)
  // PRODUCTION FALLBACK: use Cloudflare Tunnel
// Line 21 in login.js - CHANGE THIS:
return "https://boxed-reserve-relief-desktop.trycloudflare.com/digital-wallet-plateform/wallet-server/user/v1";
})();
console.log("[login.js] EFFECTIVE API_BASE =", API_BASE);

// safety: don't allow prod to hit localhost API
if (
  location.hostname !== "localhost" &&
  API_BASE.startsWith("http://localhost")
) {
  throw new Error("Misconfig: Production is pointing to localhost API.");
}

(function () {
  if (!window.APP_CONFIG || !window.APP_CONFIG.API_BASE_URL) {
    throw new Error(
      "APP_CONFIG.API_BASE_URL is missing. Load the config <script> before login.js"
    );
  }
  if (!window.axios) {
    throw new Error("Axios is not loaded. Include it before login.js");
  }
})();

// --- config & HTTP client ---
//const API_BASE = window.APP_CONFIG.API_BASE_URL; // set by your inline config script
const ROUTES = {
  passwordLogin: "/auth/login.php",
  googleLogin: "/auth/oauth_google.php",
};

console.log("[login.js] Using API_BASE:", API_BASE);
console.log("[login.js] ROUTES:", ROUTES);

const http = axios.create({
  baseURL: API_BASE,
  // Keep false if you’re returning JWT in JSON (not cookies)
  withCredentials: false,
  headers: { Accept: "application/json" },
});

// --- helpers ---
function saveSession(token, user) {
  if (!token || !user) return;
  localStorage.setItem("jwt", token);
  localStorage.setItem("userId", user.id);
  localStorage.setItem("userEmail", user.email);
  if (user.role !== undefined) localStorage.setItem("userRole", user.role);
}

/*function redirectToDashboard() {
  // adjust path if your dashboard lives elsewhere
  window.location.href = "/dashboard.html";
}*/
function redirectToDashboard() {
  // Get the base URL (works for both local and production)
  const baseUrl = window.location.origin;
  window.location.href = `${baseUrl}/dashboard.html`;
}

function showError(msg) {
  alert(msg || "An error occurred. Please try again.");
}

function extractErr(err, fallback = "Request failed") {
  return (
    err?.response?.data?.message ||
    err?.response?.data?.error ||
    err?.message ||
    fallback
  );
}

/* =========================
   Email/Password login
========================= */
(function wirePasswordLogin() {
  const form = document.getElementById("loginForm");
  if (!form) return;

  const submitBtn = form.querySelector('button[type="submit"]');

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = new FormData(form);
    const email = (formData.get("email") || "").toString().trim();
    const password = (formData.get("password") || "").toString();

    if (!email || !password) {
      showError("Please enter your email and password.");
      return;
    }

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = "Logging in...";
    }

    try {
      // login.php expects POST form fields -> FormData is correct
      const resp = await http.post(ROUTES.passwordLogin, formData);
      const data = resp?.data || {};

      if (data.status === "success" && data.token && data.user) {
        saveSession(data.token, data.user);
        redirectToDashboard();
        return;
      }

      showError(
        data.message || "Login failed. Check your credentials and try again."
      );
    } catch (err) {
      console.error("[login.js] Login error:", err);
      showError(extractErr(err, "Login error"));
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = "Login";
      }
    }
  });
})();

/* =========================
   Google One Tap / Button
========================= */
// Google’s GSI calls this globally
window.realHandleCredentialResponse = async function (googleResponse) {
  try {
    const credential = googleResponse?.credential;
    if (!credential) {
      showError("Missing Google credential.");
      return;
    }

    // oauth_google.php expects JSON { credential }
    const resp = await http.post(
      ROUTES.googleLogin,
      { credential },
      { headers: { "Content-Type": "application/json" } }
    );

    const data = resp?.data || {};
    if (data.status === "success" && data.token && data.user) {
      saveSession(data.token, data.user);
      redirectToDashboard();
      return;
    }

    showError(data.message || "Google login failed. Please try again.");
  } catch (err) {
    console.error("[login.js] Google login error:", err);
    showError(extractErr(err, "Google login error"));
  }
};
(function () {
  if (!window.APP_CONFIG || !window.APP_CONFIG.API_BASE_URL) {
    throw new Error(
      "APP_CONFIG.API_BASE_URL is missing — config script did not load."
    );
  }
})();

/* =========================
   Optional: clear old session on page load
========================= */
// localStorage.removeItem('jwt');
// localStorage.removeItem('userId');
// localStorage.removeItem('userEmail');
// localStorage.removeItem('userRole');
