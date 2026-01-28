const form = document.getElementById('registerForm');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const confirmPasswordInput = document.getElementById('confirm_password');
const result = document.getElementById('result');

// ---------- Password Live Check ----------
passwordInput.addEventListener('input', () => {
    const password = passwordInput.value;

    if (password.length < 8) {
        result.style.color = "orange";
        result.innerText = "Password must be at least 8 characters";
        return;
    }

    if (!/[A-Za-z]/.test(password)) {
        result.style.color = "orange";
        result.innerText = "Password must contain a letter";
        return;
    }

    if (!/[0-9]/.test(password)) {
        result.style.color = "orange";
        result.innerText = "Password must contain a number";
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
        !/[A-Za-z]/.test(password) ||
        !/[0-9]/.test(password)
    ) {
        result.style.color = "red";
        result.innerText = "Please enter a strong password";
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
