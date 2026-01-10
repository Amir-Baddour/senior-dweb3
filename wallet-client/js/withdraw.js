document.addEventListener('DOMContentLoait', function() {
  const { API_BASE_URL } = window.APP_CONFIG; // ✅ Get dynamic base URL
  const form = document.getElementById('withdrawForm');

  if (form) {
    form.addEventListener('submit', function(e) {
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
        alert("Please enter a valid withdrawal amount.");
        return;
      }

      // ✅ Use dynamic base URL instead of localhost
      axios.post(
        `${API_BASE_URL}/withdraw.php`,
        { amount: withdrawAmount },
        { headers: { 'Authorization': `Bearer ${token}` } }
      )
      .then(function(response) {
        if (response.data.error) {
          alert("Withdrawal error: " + response.data.error);
        } else {
          const message = `✅ Withdrawal of ${withdrawAmount} USDT successful.`;
          
          if (response.data.emailSent) {
            alert(`${message}\n📧 A confirmation email was sent to your inbox.`);
          } else {
            alert(`${message}\n⚠️ However, we couldn't send a confirmation email.`);
          }
          
          window.location.href = "dashboard.html";
        }
      })
      .catch(function(error) {
        console.error("Error during withdrawal:", error);
        alert("Unexpected error during withdrawal.");
      });
    });
  }
});