/* login.js - FIXED VERSION WITH CONFIG.JS INTEGRATION */

// Wait for config.js to load
(function waitForConfig() {
  if (!window.APP_CONFIG || !window.APP_CONFIG.API_BASE_URL) {
    console.warn("[login.js] Waiting for APP_CONFIG...");
  }
})();

const API_BASE = (() => {
  // Always use config.js if available
  if (window.APP_CONFIG?.API_BASE_URL) {
    console.log("[login.js] Using API_BASE from config.js");
    return window.APP_CONFIG.API_BASE_URL;
  }
  
  // Fallback (should rarely be used)
  console.warn("[login.js] APP_CONFIG not found, using fallback");
  const isLocal = location.hostname === "localhost" || location.hostname === "127.0.0.1";
  const basePath = "/digital-wallet-plateform/wallet-server/user/v1";
  
  if (isLocal) {
    return `http://localhost${basePath}`;
  }
  
  // Use current origin for Cloudflare tunnel
  return `${location.origin}${basePath}`;
})();

console.log("[login.js] Using API_BASE:", API_BASE);

const ROUTES = {
  passwordLogin: "/auth/login.php",
  googleLogin: "/auth/oauth_google.php",
};

const http = axios.create({
  baseURL: API_BASE,
  withCredentials: false,
  headers: { Accept: "application/json" },
});

function saveSession(token, user) {
  if (!token || !user) return;
  localStorage.setItem("jwt", token);
  localStorage.setItem("userId", user.id);
  localStorage.setItem("userEmail", user.email);
  if (user.role !== undefined) localStorage.setItem("userRole", user.role);
}

function redirectToDashboard() {
  const baseUrl = window.location.origin;
  const dashboardUrl = `${baseUrl}/dashboard.html`;
  console.log("[login.js] Redirecting to dashboard:", dashboardUrl);
  window.location.href = dashboardUrl;
}

function showError(msg) {
  alert(msg || "An error occurred. Please try again.");
}

function showInfo(msg) {
  alert(msg);
}

function extractErr(err, fallback = "Request failed") {
  return err?.response?.data?.message || err?.response?.data?.error || err?.message || fallback;
}

function openVerificationLink(verificationUrl) {
  console.log("[login.js] Opening verification link:", verificationUrl);
  
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
    console.warn("[login.js] Popup blocked, opening in current tab");
    window.location.href = verificationUrl;
  } else {
    console.log("[login.js] Verification popup opened successfully");
  }
}

// Listen for verification completion
window.addEventListener("message", function (event) {
  if (event.data && event.data.type === "login_verified") {
    console.log("[login.js] ✅ Login verified via popup!");
    
    const { token, user } = event.data;
    if (token && user) {
      saveSession(token, user);
      showInfo("Login verified successfully! Redirecting...");
      setTimeout(() => {
        redirectToDashboard();
      }, 1000);
    }
  }
});

// Email/Password login
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
      
      // Handle potential string response
      if (typeof data === 'string') {
        try {
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

      // ✅ Successful immediate login (shouldn't happen with new flow)
      if (data.status === "success" && data.token && data.user) {
        console.log("[login.js] Immediate login (legacy)");
        saveSession(data.token, data.user);
        redirectToDashboard();
        return;
      }

      // ✅ Pending verification (MAIN FLOW)
      if (data.status === "pending_verification") {
        console.log("[login.js] 📧 Email verification required");
        
        const verificationUrl = data.verification_url;
        
        if (!verificationUrl) {
          showError("Verification URL not available. Please contact support.");
          return;
        }
        
        // Show different message based on whether email was sent
        let message;
        if (data.email_sent) {
          message = `✅ SECURITY CHECK\n\nWe've sent a verification email to:\n${data.email}\n\n📧 Please check your inbox and click the verification link to complete your login.\n\n⏱️ The link expires in 15 minutes.\n\nClick OK to open the verification page now, or check your email.`;
        } else {
          message = `⚠️ EMAIL NOT CONFIGURED\n\nEmail service is not set up yet.\n\nClick OK to open the verification page and complete your login manually.\n\n⏱️ Link expires in 15 minutes.`;
        }
        
        if (confirm(message)) {
          openVerificationLink(verificationUrl);
        }
        
        // Update button
        if (submitBtn) {
          submitBtn.textContent = "Open Verification Link";
          submitBtn.style.backgroundColor = "#ff9800";
          submitBtn.disabled = false;
          
          submitBtn.onclick = (e) => {
            e.preventDefault();
            openVerificationLink(verificationUrl);
          };
        }
        
        return;
      }

      // ❌ Error
      showError(data.message || "Login failed. Please check your credentials.");
      
    } catch (err) {
      console.error("[login.js] Login error:", err);
      showError(extractErr(err, "Login error"));
    } finally {
      if (submitBtn && submitBtn.textContent === "Logging in...") {
        submitBtn.disabled = false;
        submitBtn.textContent = "Login";
      }
    }
  });
})();

// Google Login
window.handleCredentialResponse = async function (googleResponse) {
  console.log("[login.js] Google credential received");
  
  try {
    const credential = googleResponse?.credential;
    if (!credential) {
      showError("Missing Google credential.");
      return;
    }

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

    showError(data.message || "Google login failed.");
  } catch (err) {
    console.error("[login.js] Google login error:", err);
    showError(extractErr(err, "Google login error"));
  }
};

window.realHandleCredentialResponse = window.handleCredentialResponse;