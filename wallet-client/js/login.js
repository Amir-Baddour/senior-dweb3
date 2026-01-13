/* =========================
   login.js  (frontend)
   Requires:
   - window.APP_CONFIG.API_BASE_URL set BEFORE this file
   - axios loaded BEFORE this file
========================= */

// --- HARD GUARD: pick API base even if config is missing ---
const API_BASE = (() => {
  // if inline config ran, use it
  if (window.APP_CONFIG?.API_BASE_URL) return window.APP_CONFIG.API_BASE_URL;

  const isLocal =
    location.hostname === "localhost" || location.hostname === "127.0.0.1";
  if (isLocal)
    return "http://localhost/digital-wallet-plateform/wallet-server/user/v1";

  // ✅ PRODUCTION FALLBACK: Current Cloudflare Tunnel
  return "https://templates-bridge-michelle-ranked.trycloudflare.com/digital-wallet-plateform/wallet-server/user/v1";
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
    console.warn("APP_CONFIG.API_BASE_URL is missing - using fallback");
  }
  if (!window.axios) {
    throw new Error("Axios is not loaded. Include it before login.js");
  }
})();

// --- config & HTTP client ---
const ROUTES = {
  passwordLogin: "/auth/login.php",
  googleLogin: "/auth/oauth_google.php",
};

console.log("[login.js] Using API_BASE:", API_BASE);
console.log("[login.js] ROUTES:", ROUTES);

const http = axios.create({
  baseURL: API_BASE,
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

function redirectToDashboard() {
  const baseUrl = window.location.origin;
  window.location.href = `${baseUrl}/dashboard.html`;
}

function showError(msg) {
  alert(msg || "An error occurred. Please try again.");
}

function showInfo(msg) {
  alert(msg);
}

function extractErr(err, fallback = "Request failed") {
  return (
    err?.response?.data?.message ||
    err?.response?.data?.error ||
    err?.message ||
    fallback
  );
}

/**
 * Open verification link in a new window/tab
 */
function openVerificationLink(verificationUrl) {
  console.log("[login.js] Opening verification link:", verificationUrl);
  
  // Try to open in a popup window
  const width = 600;
  const height = 700;
  const left = (screen.width - width) / 2;
  const top = (screen.height - height) / 2;
  
  const popup = window.open(
    verificationUrl,
    'LoginVerification',
    `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
  );
  
  if (!popup || popup.closed || typeof popup.closed === 'undefined') {
    // Popup was blocked, fallback to opening in current tab
    console.warn("[login.js] Popup blocked, opening in current tab");
    window.location.href = verificationUrl;
  } else {
    console.log("[login.js] Verification popup opened successfully");
  }
}

/* =========================
   Listen for verification completion from popup
========================= */
window.addEventListener("message", function (event) {
  // Accept messages from any origin for development
  // In production, verify event.origin matches your domain
  
  if (event.data && event.data.type === "login_verified") {
    console.log("[login.js] Received login verification from popup");
    
    const { token, user } = event.data;
    if (token && user) {
      saveSession(token, user);
      
      // Show success message
      showInfo("Login verified successfully! Redirecting to dashboard...");
      
      // Redirect after a short delay
      setTimeout(() => {
        redirectToDashboard();
      }, 1000);
    }
  }
});

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
      const resp = await http.post(ROUTES.passwordLogin, formData);
      let data = resp?.data;
      
      // Handle case where response might be a string with JSON
      if (typeof data === 'string') {
        try {
          // Try to find JSON in the response
          const jsonMatch = data.match(/\{[\s\S]*\}/);
          if (jsonMatch) {
            data = JSON.parse(jsonMatch[0]);
          }
        } catch (parseErr) {
          console.error("[login.js] Failed to parse response:", parseErr);
          showError("Invalid server response");
          return;
        }
      }
      
      console.log("[login.js] Backend response:", data);
      console.log("[login.js] Response status:", data.status);

      // ✅ Handle successful login (immediate)
      if (data.status === "success" && data.token && data.user) {
        console.log("[login.js] Login successful, saving session");
        saveSession(data.token, data.user);
        redirectToDashboard();
        return;
      }

      // ✅ Handle pending email verification
      if (data.status === "pending_verification") {
        console.log("[login.js] Email verification pending");
        
        // Build verification URL
        let verificationUrl;
        if (data.debug_token) {
          // Use debug token from response
          verificationUrl = `${API_BASE.replace('/user/v1', '')}/user/v1/auth/verify_login.php?token=${data.debug_token}`;
          console.log("[login.js] Debug verification link:", verificationUrl);
        } else if (data.verification_url) {
          // Use provided URL
          verificationUrl = data.verification_url;
        }
        
        // Determine message based on email status
        let message;
        if (data.email_sent) {
          message = "A verification email has been sent to your inbox. Please check your email and click the verification link.\n\nAlternatively, click OK to open the verification page now.";
        } else {
          message = "Email service is not configured yet. Click OK to open the verification page and complete your login.";
        }
        
        // Show message and ask user to proceed
        if (confirm(message)) {
          if (verificationUrl) {
            openVerificationLink(verificationUrl);
          } else {
            showError("Verification URL not available. Please contact support.");
          }
        }
        
        // Update button to show verification pending
        if (submitBtn) {
          submitBtn.textContent = "Open Verification Link";
          submitBtn.style.backgroundColor = "#ff9800";
          submitBtn.disabled = false;
          
          // Allow user to click button to open verification link again
          submitBtn.onclick = (e) => {
            e.preventDefault();
            if (verificationUrl) {
              openVerificationLink(verificationUrl);
            }
          };
        }
        
        return;
      }

      // Handle other responses
      showError(
        data.message || "Login failed. Check your credentials and try again."
      );
    } catch (err) {
      console.error("[login.js] Login error:", err);
      showError(extractErr(err, "Login error"));
    } finally {
      // Only reset if not pending verification
      if (submitBtn && submitBtn.textContent === "Logging in...") {
        submitBtn.disabled = false;
        submitBtn.textContent = "Login";
      }
    }
  });
})();

/* =========================
   Google One Tap / Button
========================= */
window.handleCredentialResponse = async function (googleResponse) {
  console.log("[login.js] Google credential received");
  
  try {
    const credential = googleResponse?.credential;
    if (!credential) {
      showError("Missing Google credential.");
      return;
    }

    console.log("[login.js] Sending credential to backend...");
    
    const resp = await http.post(
      ROUTES.googleLogin,
      { credential },
      { headers: { "Content-Type": "application/json" } }
    );

    console.log("[login.js] Backend response:", resp.data);
    
    const data = resp?.data || {};
    if (data.status === "success" && data.token && data.user) {
      saveSession(data.token, data.user);
      redirectToDashboard();
      return;
    }

    showError(data.message || "Google login failed. Please try again.");
  } catch (err) {
    console.error("[login.js] Google login error:", err);
    console.error("[login.js] Error details:", err.response?.data);
    showError(extractErr(err, "Google login error"));
  }
};

// ✅ Add alias for compatibility
window.realHandleCredentialResponse = window.handleCredentialResponse;