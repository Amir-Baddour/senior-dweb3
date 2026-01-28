const form = document.getElementById('registerForm');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const confirmPasswordInput = document.getElementById('confirm_password');
const result = document.getElementById('result');

// ---------- Live Password Check ----------
passwordInput.addEventListener('input', () => {
    const password = passwordInput.value;

    if (password.length < 8) {
        result.style.color = "orange";
        result.innerText = "Min 8 characters";
        return;
    }

    if (!/[a-z]/.test(password)) {
        result.style.color = "orange";
        result.innerText = "Add lowercase letter";
        return;
    }

    if (!/[A-Z]/.test(password)) {
        result.style.color = "red";
        result.innerText = "Add uppercase letter";
        return;
    }

    if (!/[0-9]/.test(password)) {
        result.style.color = "red";
        result.innerText = "Add number";
        return;
    }

    if (!/[!@#$%^&]/.test(password)) {
        result.style.color = "red";
        result.innerText = "Add symbol (!@#$%^&)";
        return;
    }

    result.style.color = "green";
    result.innerText = "Strong password ✔";
});

// ---------- Submit ----------
form.addEventListener('submit', async function (e) {
    e.preventDefault();

    const password = passwordInput.value;

    // ---------- Block Weak Password ----------
    if (
        password.length < 8 ||
        !/[a-z]/.test(password) ||
        !/[A-Z]/.test(password) ||
        !/[0-9]/.test(password) ||
        !/[!@#$%^&]/.test(password)
    ) {
        result.style.color = "red";
        result.innerText = "Password does not meet requirements";
        return;
    }

    const API_BASE_URL =
        window.APP_CONFIG?.API_BASE_URL ||
        'http://localhost/digital-wallet-plateform/wallet-server/user/v1';

    try {
        const response = await fetch(`${API_BASE_URL}/auth/register.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                email: emailInput.value,
                password: passwordInput.value,
                confirm_password: confirmPasswordInput.value
            })
        });

        const data = await response.json();
        result.innerText = data.message;
        result.style.color = data.status === "success" ? "green" : "red";

        if (data.status === "success") {
            setTimeout(() => {
                window.location.href = "login.html";
            }, 2000);
        }

    } catch (error) {
        result.style.color = "red";
        result.innerText = "Network error. Please try again.";
    }
});
