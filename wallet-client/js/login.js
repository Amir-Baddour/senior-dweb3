document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    // Prepare form data and perform login API call
    const formData = new FormData(this);
    try {
        const response = await axios.post("http://ec2-13-38-91-228.eu-west-3.compute.amazonaws.com/user/v1/auth/login.php", formData);
        if (response.data && response.data.status === 'success') {
            // Store the JWT and user info, then redirect to dashboard
            const token = response.data.token;
            const user = response.data.user;
            if (token && user) {
                localStorage.setItem('jwt', token);
                localStorage.setItem('userId', user.id);
                localStorage.setItem('userEmail', user.email);
                localStorage.setItem('userRole', user.role);
                window.location.href = '/digital-wallet-platform/wallet-client/dashboard.html';
            }
        }
    } catch (error) {
        console.error("Error processing login:", error);
    }
});