document
  .getElementById("adminLoginForm")
  .addEventListener("submit", function (e) {
    e.preventDefault();
    e.stopPropagation(); // ✅ Stop event bubbling

    const formData = new FormData(this);

    axios
      .post(`${window.ADMIN_CONFIG.API_BASE_URL}/auth/login.php`, formData)
      .then(function (response) {
        if (response.data && response.data.status === "success") {
          if (response.data.token) {
            localStorage.setItem("admin_jwt", response.data.token);
          }

          // ✅ Use replace() to break the POST context completely
          window.location.replace("/wallet-admin/dashboard.html");
        } else {
          console.error("Unexpected response from server.", response.data);
        }
      })
      .catch(function (error) {
        console.error("Error processing login:", error);
      });

    return false; // ✅ Extra safety to prevent form submission
  });
