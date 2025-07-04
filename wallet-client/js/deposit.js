document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('depositForm');
  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();

      const token = localStorage.getItem('jwt');
      if (!token) {
        window.location.href = 'login.html';
        return;
      }

      const depositAmount = parseFloat(document.getElementById('depositAmount').value);
      if (isNaN(depositAmount) || depositAmount <= 0) {
        alert("Please enter a valid deposit amount.");
        return;
      }

      axios.post(
        'http://localhost/digital-wallet-plateform/wallet-server/user/v1/deposit.php',
        { amount: depositAmount },
        {
          headers: { 'Authorization': `Bearer ${token}` }
        }
      )
      .then(function(response) {
        if (response.data.error) {
          alert("Deposit error: " + response.data.error);
        } else {
          window.location.href = "dashboard.html";
        }
      })
      .catch(function(error) {
        console.error("Error during deposit:", error);
        alert("Unexpected error during deposit.");
      });
    });
  }
});
