document.addEventListener('DOMContentLoaded', function() {
  const { API_BASE_URL } = window.APP_CONFIG; // ✅ add this line
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

      // ✅ Use dynamic base URL instead of localhost
      axios.post(
        `${API_BASE_URL}/deposit.php`,
        { amount: depositAmount },
        {
          headers: { 'Authorization': `Bearer ${token}` }
        }
      )
      .then(function(response) {
        if (response.data.error) {
          alert("Deposit error: " + response.data.error);
        } else {
          const message = `✅ Deposit of ${depositAmount} USDT successful.`;

          if (response.data.emailSent) {
            alert(`${message}\n📧 A confirmation email was sent to your inbox.`);
          } else {
            alert(`${message}\n⚠️ However, we couldn't send a confirmation email.`);
          }

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
