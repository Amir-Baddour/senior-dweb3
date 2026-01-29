const form = document.getElementById("registerForm");
const emailInput = document.getElementById("email");
const passwordInput = document.getElementById("password");
const confirmPasswordInput = document.getElementById("confirm_password");
const result = document.getElementById("result");

// ---------- Live Password Check ----------
passwordInput.addEventListener("input", () => {
  const p = passwordInput.value;

  if (p.length < 8) return show("Min 8 characters", "orange");
  if (!/[a-z]/.test(p)) return show("Add lowercase letter", "orange");
  if (!/[A-Z]/.test(p)) return show("Add uppercase letter", "red");
  if (!/[0-9]/.test(p)) return show("Add number", "red");
  if (!/[!@#$%^&]/.test(p)) return show("Add symbol (!@#$%^&)", "red");

  show("Strong password ✔", "green");
});

function show(msg, color) {
  result.innerText = msg;
  result.style.color = color;
}

// ---------- Submit ----------
form.addEventListener("submit", async (e) => {
  e.preventDefault();

  const API_BASE_URL =
    window.APP_CONFIG?.API_BASE_URL ||
    "http://localhost/digital-wallet-plateform/wallet-server/user/v1";

  try {
    const res = await fetch(`${API_BASE_URL}/auth/register.php`, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        email: emailInput.value,
        password: passwordInput.value,
        confirm_password: confirmPasswordInput.value,
      }),
    });

    const data = await res.json();
    show(data.message, data.status === "success" ? "green" : "red");

    /*if (data.status === "success") {
            setTimeout(() => {
                window.location.href = "login.html";
            }, 2000);
        }*/
    if (data.status === "success") {
      alert("Registration successful! Check your email and login.");
      window.location.href = "check-email.html";
    }
  } catch (err) {
    show("Network error. Please try again.", "red");
  }
});
