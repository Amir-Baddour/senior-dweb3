document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    try {
        const response = await axios.post(
            "http://localhost/digital-wallet-plateform/wallet-server/user/v1/auth/login.php",
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }
        );

        if (response.data && response.data.status === 'success') {
            const token = response.data.token;
            const user = response.data.user;
            if (token && user) {
                localStorage.setItem('jwt', token);
                localStorage.setItem('userId', user.id);
                localStorage.setItem('userEmail', user.email);
                localStorage.setItem('userRole', user.role);
                window.location.href = '/digital-wallet-plateform/wallet-client/dashboard.html';
            }
        } else {
            alert(response.data.message || 'Login failed');
        }
    } catch (error) {
        console.error("Error processing login:", error);
        alert("Login error: " + error.message);
    }
});