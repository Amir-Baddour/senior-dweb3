const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const confirmPasswordInput = document.getElementById('confirm_password');

document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    try {
        const response = await fetch('http://localhost/digital-wallet-platform/wallet-server/user/v1/auth/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                email: emailInput.value,
                password: passwordInput.value,
                confirm_password: confirmPasswordInput.value
            })
        });

        const data = await response.json();
        document.getElementById('result').innerText = data.message;

        if (data.status === 'success') {
            // Optionally redirect or clear the form
        }
    } catch (error) {
        console.error("Error processing registration:", error);
    }
});