document.getElementById('adminLoginForm').addEventListener('submit', function(e) {
  e.preventDefault();

  // Build form data from the form fields
  const formData = new FormData(this);

  axios.post(`${window.ADMIN_CONFIG.API_BASE_URL}/auth/login.php`, formData)
      .then(function(response) {
          // Check if the server returned a valid response with a message and success status
          if (response.data && response.data.status === 'success') {
              // Store the admin JWT token in localStorage if provided
              if (response.data.token) {
                  localStorage.setItem('admin_jwt', response.data.token);
              }
              
              // ✅ Create a temporary anchor element to force GET navigation
              const link = document.createElement('a');
              link.href = 'dashboard.html';
              link.click();
          } else {
              // Log unexpected responses for debugging purposes
              console.error("Unexpected response from server.", response.data);
          }
      })
      .catch(function(error) {
          console.error("Error processing login:", error);
      });
});