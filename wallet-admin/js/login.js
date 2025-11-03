document.getElementById('adminLoginForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const formData = new FormData(this);

  axios.post(`${window.ADMIN_CONFIG.API_BASE_URL}/auth/login.php`, formData)
      .then(function(response) {
          if (response.data && response.data.status === 'success') {
              if (response.data.token) {
                  localStorage.setItem('admin_jwt', response.data.token);
              }
              // ✅ Correct path to dashboard
              window.location.href = 'wallet-admin/dashboard.html';
          } else {
              console.error("Unexpected response from server.", response.data);
          }
      })
      .catch(function(error) {
          console.error("Error processing login:", error);
      });
});