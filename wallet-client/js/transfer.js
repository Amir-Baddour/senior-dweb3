document.addEventListener("DOMContentLoaded", function() {
   
    const transferBtn = document.getElementById("transferBtn");

    if (!transferBtn) {
    console.error("Transfer button not found in DOM!");
    return;
}

    transferBtn.addEventListener("click", function(e) {
         
        e.preventDefault();
 
        // Verify user authentication by checking the JWT; redirect if missing.
        const token = localStorage.getItem('jwt');
        if (!token) {
            window.location.href = 'login.html';
            return;
        }

        // Retrieve and validate transfer inputs.
        const recipientEmail = document.getElementById("recipientQuery").value.trim();
        const transferAmount = parseFloat(document.getElementById("transferAmount").value);
        if (!recipientEmail) {
            return;
        }
        if (isNaN(transferAmount) || transferAmount <= 0) {
            return;
        }

        // Build request data and initiate transfer API call with the JWT.
        const data = {
            recipient_email: recipientEmail,
            amount: transferAmount
        };

        axios.post('http://localhost/digital-wallet-plateform/wallet-server/user/v1/transfer.php', data, {
            headers: { 'Authorization': `Bearer ${token}` }
        })
        .then(response => {
            if (!response.data.error) {
                window.location.href = 'dashboard.html';
            }
        })
        .catch(error => {
            console.error("Transfer error:", error);
        });
    });
});