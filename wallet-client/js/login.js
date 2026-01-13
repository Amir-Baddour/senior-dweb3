/* login.js - FIXED VERSION WITH IMPROVED POPUP FLOW */

// Wait for config.js to load
(function waitForConfig() {
  if (!window.APP_CONFIG || !window.APP_CONFIG.API_BASE_URL) {
    console.warn("[login.js] Waiting for APP_CONFIG...");
  }
})();

const API_BASE = (() => {
  if (window.APP_CONFIG?.API_BASE_URL) {
    console.log("[login.js] Using API_BASE from config.js");
    return window.APP_CONFIG.API_BASE_URL;
  }
  
  console.warn("[login.js] APP_CONFIG not found, using fallback");
  const isLocal = location.hostname === "localhost" || location.hostname === "127.0.0.1";
  const basePath = "/digital-wallet-plateform/wallet-server/user/v1";
  
  if (isLocal) {
    return `http://localhost${basePath}`;
  }
  
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
    console.warn("[login.js] ⚠️ Popup blocked, redirecting to verification page...");
    // If popup is blocked, just redirect the main window
    window.location.href = verificationUrl;
    return;
  }
  
  console.log("[login.js] ✓ Verification popup opened successfully");
  console.log("[login.js] ⏳ Waiting for verification message...");
  
  let messageReceived = false;
  
  // Listen for message from popup
  const messageHandler = (event) => {
    if (event.data && event.data.type === "login_verified") {
      messageReceived = true;
      console.log("[login.js] 🎉 Message received from popup!");
    }
  };
  
  window.addEventListener("message", messageHandler);
  
  // Monitor popup
  const checkPopup = setInterval(() => {
    if (popup.closed) {
      console.log("[login.js] Popup was closed");
      clearInterval(checkPopup);
      window.removeEventListener("message", messageHandler);
      
      // If popup closed but we didn't receive the message
      if (!messageReceived) {
        console.warn("[login.js] ⚠️ Popup closed but no message received!");
        console.log("[login.js] 🔄 Redirecting to verification page instead...");
        
        // Wait a bit in case message is delayed
        setTimeout(() => {
          if (!messageReceived) {
            window.location.href = verificationUrl;
          }
        }, 1000);
      }
    }
  }, 500);
}

// Custom modal for verification message
function showVerificationModal(email, emailSent, verificationUrl) {
  const modal = document.createElement('div');
  modal.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
  `;
  
  const content = document.createElement('div');
  content.style.cssText = `
    background: white;
    padding: 30px;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
  `;
  
  const icon = emailSent ? '✅' : '⚠️';
  const title = emailSent ? 'SECURITY CHECK' : 'EMAIL NOT CONFIGURED';
  
  content.innerHTML = `
    <div style="font-size: 48px; margin-bottom: 20px;">${icon}</div>
    <h2 style="color: #333; margin-bottom: 16px; font-size: 24px;">${title}</h2>
    ${emailSent ? `
      <p style="color: #666; margin-bottom: 12px; line-height: 1.6;">
        We've sent a verification email to:<br>
        <strong style="color: #333;">${email}</strong>
      </p>
      <p style="color: #666; margin-bottom: 12px;">
        📧 Please check your inbox and click the verification link to complete your login.
      </p>
    ` : `
      <p style="color: #e67e22; margin-bottom: 12px; line-height: 1.6;">
        Email service is not set up yet.
      </p>
      <p style="color: #666; margin-bottom: 12px;">
        Click below to open the verification page and complete your login manually.
      </p>
    `}
    <p style="color: #999; font-size: 14px; margin-bottom: 20px;">
      ⏱️ The link expires in 15 minutes.
    </p>
    <div style="display: flex; gap: 12px; justify-content: center;">
      <button id="verifyBtn" style="
        padding: 12px 30px;
        background-color: #4CAF50;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: background-color 0.3s;
      ">Open Verification Page</button>
      <button id="cancelBtn" style="
        padding: 12px 30px;
        background-color: #999;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
        transition: background-color 0.3s;
      ">Cancel</button>
    </div>
  `;
  
  modal.appendChild(content);
  document.body.appendChild(modal);
  
  const verifyBtn = content.querySelector('#verifyBtn');
  const cancelBtn = content.querySelector('#cancelBtn');
  
  verifyBtn.onmouseover = () => verifyBtn.style.backgroundColor = '#45a049';
  verifyBtn.onmouseout = () => verifyBtn.style.backgroundColor = '#4CAF50';
  cancelBtn.onmouseover = () => cancelBtn.style.backgroundColor = '#777';
  cancelBtn.onmouseout = () => cancelBtn.style.backgroundColor = '#999';
  
  verifyBtn.onclick = () => {
    openVerificationLink(verificationUrl);
    document.body.removeChild(modal);
  };
  
  cancelBtn.onclick = () => {
    document.body.removeChild(modal);
  };
}

// Listen for verification completion from popup
window.addEventListener("message", function (event) {
  // Filter out browser extension noise (MetaMask, etc.)
  if (event.data && (event.data.target || event.data.iframeId)) {
    return; // Ignore extension messages
  }
  
  console.log("[login.js] 📨 Received message:", event.data);
  
  // Security: Only accept messages from expected origins
  const allowedOrigins = [
    window.location.origin,
    'http://localhost',
    'https://hawaiian-privileges-levy-bases.trycloudflare.com'
  ];
  
  const isAllowedOrigin = allowedOrigins.some(origin => 
    event.origin.startsWith(origin)
  );
  
  if (!isAllowedOrigin) {
    console.warn("[login.js] ⚠️ Message from untrusted origin:", event.origin);
    return;
  }
  
  if (event.data && event.data.type === "login_verified") {
    console.log("[login.js] ✅ LOGIN VERIFIED VIA POPUP!");
    console.log("[login.js] 📦 Token received:", event.data.token ? "YES" : "NO");
    console.log("[login.js] 👤 User data:", event.data.user);
    
    const { token, user } = event.data;
    if (token && user) {
      saveSession(token, user);
      
      // Close modal if it exists
      const modal = document.querySelector('[style*="position: fixed"]');
      if (modal) {
        modal.remove();
      }
      
      showInfo("Login verified successfully! Redirecting...");
      setTimeout(() => {
        redirectToDashboard();
      }, 1000);
    } else {
      console.error("[login.js] ❌ Missing token or user data!");
    }
  }
}, false);

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

      // ✅ Successful immediate login (legacy)
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
        
        // Show custom modal instead of confirm()
        showVerificationModal(data.email, data.email_sent, verificationUrl);
        
        // Update button to allow re-opening verification
        if (submitBtn) {
          submitBtn.textContent = "Resend Verification";
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