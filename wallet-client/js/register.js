document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    // Build FormData from the form
    const formData = new FormData(this);

    try {
        const response = await axios.post("http://ec2-13-38-91-228.eu-west-3.compute.amazonaws.com/user/v1/auth/register.php", formData);
        console.log("Register response:", response.data);
        if (response.data && response.data.status === 'success') {
            // Store JWT if provided and redirect to verification page
            if (response.data.token) {
                localStorage.setItem('jwt', response.data.token);
            }
            window.location.href = '/digital-wallet-platform/wallet-client/verification.html';
        }
    } catch (error) {
        console.error("Error processing registration:", error);
    }
});