// Admin login.js
document.addEventListener('DOMContentLoaded', function() {
  // ✅ Check if config loaded
  const API_BASE_URL = window.ADMIN_CONFIG?.API_BASE_URL;
  
  if (!API_BASE_URL) {
    console.error('[admin-login] ADMIN_CONFIG not found! Config.js may not have loaded.');
    showError('Configuration error. Please refresh the page.');
    return;
  }
  
  console.log('[admin-login] Using API_BASE_URL:', API_BASE_URL);

  const loginForm = document.getElementById("adminLoginForm");
  const errorElement = document.getElementById("loginError");

  function showError(message) {
    if (errorElement) {
      errorElement.textContent = message;
      errorElement.style.display = 'block';
    }
  }

  function hideError() {
    if (errorElement) {
      errorElement.style.display = 'none';
    }
  }

  loginForm.addEventListener("submit", function (e) {
    e.preventDefault();
    e.stopPropagation();
    
    hideError();

    const formData = new FormData(this);

    console.log('[admin-login] Attempting login to:', `${API_BASE_URL}/auth/login.php`);

    axios
      .post(`${API_BASE_URL}/auth/login.php`, formData)
      .then(function (response) {
        console.log('[admin-login] Response:', response.data);
        
        if (response.data && response.data.status === "success") {
          if (response.data.token) {
            localStorage.setItem("admin_jwt", response.data.token);
            localStorage.setItem("admin_user", JSON.stringify(response.data.user || {}));
          }

          // ✅ Redirect to admin dashboard
          window.location.replace("dashboard.html");
        } else {
          showError(response.data?.message || "Login failed. Please try again.");
        }
      })
      .catch(function (error) {
        console.error("[admin-login] Error:", error);
        
        if (error.response) {
          // Server responded with error
          showError(error.response.data?.message || "Invalid credentials");
        } else if (error.request) {
          // Request made but no response
          showError("Cannot connect to server. Please check your connection.");
        } else {
          // Something else happened
          showError("An error occurred. Please try again.");
        }
      });

    return false;
  });
});