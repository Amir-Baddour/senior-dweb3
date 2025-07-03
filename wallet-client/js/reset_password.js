document.addEventListener("DOMContentLoaded", function () {
  const resetPasswordForm = document.getElementById("resetPasswordForm");

  resetPasswordForm.addEventListener("submit", function (e) {
    e.preventDefault();

    // Retrieve token and password inputs
    const token = document.getElementById("token").value.trim();
    const newPassword = document.getElementById("new_password").value;
    const confirmPassword = document.getElementById("confirm_password").value;

    // Validate password inputs
    if (!newPassword || !confirmPassword) {
      return;
    }
    if (newPassword !== confirmPassword) {
      return;
    }
    if (newPassword.length < 6) {
      return;
    }

    // Build form data and perform the reset password API call
    const data = new FormData();
    data.append("token", token);
    data.append("new_password", newPassword);
    data.append("confirm_password", confirmPassword);
    axios.post("http://localhost/digital-wallet-plateform/wallet-server/user/v1/reset_password.php", data)
      .then(response => {
        if (!response.data.error) {
          window.location.href = "login.html";
        }
      })
      .catch(error => {
        console.error("Error resetting password:", error);
      });
  });
});