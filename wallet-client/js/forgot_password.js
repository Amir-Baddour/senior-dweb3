document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("forgotPasswordForm");
  const emailInput = document.getElementById("email");

  let status = document.getElementById("forgotStatus");
  if (!status) {
    status = document.createElement("div");
    status.id = "forgotStatus";
    status.style.marginTop = "10px";
    form.appendChild(status);
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const email = emailInput.value.trim();
    if (!email) {
      status.textContent = "Please enter your email.";
      status.style.color = "crimson";
      return;
    }

    const btn = form.querySelector("button[type=submit]");
    btn.disabled = true; btn.textContent = "Sending...";

    try {
      const data = new FormData();
      data.append("email", email);

      console.groupCollapsed("[ForgotPassword] Request");
      console.log("POST → /wallet-server/user/v1/request_password_reset.php", { email });

      const res = await axios.post(
        "http://localhost/digital-wallet-plateform/wallet-server/user/v1/request_password_reset.php",
        data
      );

      console.log("Response:", res.data);
      console.groupEnd();

      if (res.data.error) {
        status.textContent = res.data.error;
        status.style.color = "crimson";
        return;
      }

      // Always show success text
      status.innerHTML = "If this email exists, a reset link has been sent.";
      status.style.color = "seagreen";

      // 🔊 Extra console info about email sending
      if (typeof res.data.email_sent !== "undefined") {
        console.info("[ForgotPassword] email_sent:", res.data.email_sent);
      }
      if (res.data.email_error) {
        console.warn("[ForgotPassword] email_error:", res.data.email_error);
      }

      // DEV: show link even if email failed to send
      if (res.data.dev_reset_link) {
        const a = document.createElement("a");
        a.href = res.data.dev_reset_link;
        a.textContent = "Open reset link";
        a.style.display = "block";
        a.style.marginTop = "6px";
        status.appendChild(a);

        if (res.data.email_sent === false) {
          const warn = document.createElement("div");
          warn.textContent = "Email could not be sent (see console). Use the link above during development.";
          warn.style.color = "orange";
          warn.style.marginTop = "6px";
          status.appendChild(warn);
        }
      }

    } catch (err) {
      console.error("[ForgotPassword] Network/JS error:", err);
      status.textContent = "Network error. Check the PHP endpoint path.";
      status.style.color = "crimson";
    } finally {
      btn.disabled = false; btn.textContent = "Reset Password";
    }
  });
});
