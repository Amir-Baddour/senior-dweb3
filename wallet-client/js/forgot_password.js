document.addEventListener("DOMContentLoaded", function () {
  // Initialize forgot password functionality
  const forgotPasswordForm = document.getElementById("forgotPasswordForm");

  forgotPasswordForm.addEventListener("submit", function (e) {
    e.preventDefault();

    // Get and validate the email input
    const email = document.getElementById("email").value.trim();
    if (!email) {
      // Exit if email is missing
      return;
    }

    // Prepare form data for the password reset request
    const data = new FormData();
    data.append("email", email);

    // Make API call to request a password reset
    axios.post("http://ec2-13-38-91-228.eu-west-3.compute.amazonaws.com/user/v1/request_password_reset.php", data)
      .then(response => {
        if (!response.data.error) {
          // On success, redirect to login page
          window.location.href = "login.html";
        }
        // Error responses can be handled here (e.g., log error or update UI)
      })
      .catch(error => {
        console.error("Error requesting password reset:", error);
        // Handle network or unexpected errors
      });
  });
});