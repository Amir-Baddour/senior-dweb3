document.getElementById('depositForm').addEventListener('submit', function(e) {
    e.preventDefault();

    // Retrieve JWT from localStorage and redirect if missing
    const token = localStorage.getItem('jwt');
    if (!token) {
        window.location.href = 'login.html';
        return;
    }

    // Validate deposit amount input
    const depositAmount = parseFloat(document.getElementById('depositAmount').value);
    if (isNaN(depositAmount) || depositAmount <= 0) {
        // Invalid deposit amount; exit submission
        return;
    }

    // Make deposit API call with JWT in the header
    axios.post(
        'http://localhost/digital-wallet-plateform/wallet-server/user/v1/deposit.php',
        { amount: depositAmount },
        {
            headers: { 'Authorization': `Bearer ${token}` }
        }
    )
    .then(function(response) {
        if (response.data.error) {
            // Handle deposit error (e.g., invalid token or other errors)
        } else {
            // On successful deposit, redirect to dashboard
            window.location.href = "dashboard.html";
        }
    })
    .catch(function(error) {
        console.error("Error during deposit:", error);
        // Handle network or unexpected errors
    });
});