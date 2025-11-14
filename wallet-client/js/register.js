// register.js - Fixed to use config.js
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const confirmPasswordInput = document.getElementById('confirm_password');

document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    // ✅ FIX: Use API_BASE_URL from config.js
    const API_BASE_URL = window.APP_CONFIG?.API_BASE_URL || 
        'http://localhost/digital-wallet-plateform/wallet-server/user/v1';
    
    console.log('[register.js] Using API_BASE_URL:', API_BASE_URL);

    const formData = new FormData(this);

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
        console.log('[register.js] Response:', data);
        
        document.getElementById('result').innerText = data.message;

        if (data.status === 'success') {
            // ✅ Store JWT token
            if (data.token) {
                localStorage.setItem('jwt', data.token);
                localStorage.setItem('userId', data.user.id);
            }
            
            // ✅ Show success message and redirect
            document.getElementById('result').style.color = 'green';
            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 1500);
        } else {
            // Show error in red
            document.getElementById('result').style.color = 'red';
        }
    } catch (error) {
        console.error("Error processing registration:", error);
        document.getElementById('result').style.color = 'red';
        document.getElementById('result').innerText = 'Network error. Please try again.';
    }
});