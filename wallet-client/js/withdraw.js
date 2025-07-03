document.getElementById('withdrawForm').addEventListener('submit', function(e) {
    e.preventDefault();

    // Check authentication: redirect if no JWT is found
    const token = localStorage.getItem('jwt');
    if (!token) {
        window.location.href = 'login.html';
        return;
    }

    // Validate withdrawal amount
    const withdrawAmount = parseFloat(document.getElementById('withdrawAmount').value);
    if (isNaN(withdrawAmount) || withdrawAmount <= 0) {
        return;
    }

    // Initiate withdrawal API call with JWT authorization
    axios.post(
        'http://localhost/digital-wallet-plateform/wallet-server/user/v1/withdraw.php',
        { amount: withdrawAmount },
        { headers: { 'Authorization': `Bearer ${token}` } }
    )
    .then(function(response) {
        if (!response.data.error) {
            window.location.href = "dashboard.html";
        }
    })
    .catch(function(error) {
        console.error("Error during withdrawal:", error);
    });
});