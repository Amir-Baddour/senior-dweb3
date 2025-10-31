document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("resetPasswordForm");
  const tokenInput = document.getElementById("token");
  const status = document.getElementById("resetStatus");

  // Pull token from URL
  const params = new URLSearchParams(window.location.search);
  const token = (params.get("token") || "").trim();
  tokenInput.value = token;

  if (!token) {
    status.textContent = "Invalid or missing token. Please use the link from your email.";
    status.style.color = "crimson";
    form.querySelector("button[type=submit]").disabled = true;
    return;
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const newPassword = document.getElementById("new_password").value;
    const confirmPassword = document.getElementById("confirm_password").value;

    if (!newPassword || !confirmPassword) {
      status.textContent = "Please fill in both password fields.";
      status.style.color = "crimson";
      return;
    }
    if (newPassword !== confirmPassword) {
      status.textContent = "Passwords do not match.";
      status.style.color = "crimson";
      return;
    }
    if (newPassword.length < 6) {
      status.textContent = "Password must be at least 6 characters.";
      status.style.color = "crimson";
      return;
    }

    const btn = form.querySelector("button[type=submit]");
    btn.disabled = true; btn.textContent = "Updating...";

    try {
      const data = new FormData();
      data.append("token", token);
      data.append("new_password", newPassword);
      data.append("confirm_password", confirmPassword);

      const res = await axios.post(
        "http://localhost/digital-wallet-plateform/wallet-server/user/v1/reset_password.php",
        data
      );

      if (res.data && !res.data.error) {
        status.textContent = "Password updated. Redirecting to login…";
        status.style.color = "seagreen";
        window.location.href = "login.html";
      } else {
        status.textContent = res.data.error || "Failed to update password.";
        status.style.color = "crimson";
      }
    } catch (err) {
      console.error(err);
      status.textContent = "Network error. Check the PHP endpoint path.";
      status.style.color = "crimson";
    } finally {
      btn.disabled = false; btn.textContent = "Update Password";
    }
  });
});
