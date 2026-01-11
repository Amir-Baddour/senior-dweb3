document.addEventListener('DOMContentLoaded', function() {
  const { API_BASE_URL } = window.APP_CONFIG;
  const form = document.getElementById('withdrawForm');

  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();

      const token = localStorage.getItem('jwt');
      if (!token) {
        window.location.href = 'login.html';
        return;
      }

      const withdrawAmount = parseFloat(document.getElementById('withdrawAmount').value);
      if (isNaN(withdrawAmount) || withdrawAmount <= 0) {
        alert("Please enter a valid withdrawal amount.");
        return;
      }

      // ✅ FIXED: Correct endpoint path
      axios.post(
        `${API_BASE_URL}/user/v1/withdraw.php`,
        { amount: withdrawAmount },
        { headers: { 'Authorization': `Bearer ${token}` } }
      )
      .then(function(response) {
        console.log('Response:', response.data);
        
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
        console.error("Error:", error);
        
        if (error.response) {
          alert("Withdrawal failed: " + (error.response.data.error || error.response.statusText));
          console.error('Server response:', error.response.data);
        } else if (error.request) {
          alert("Network error: Unable to connect to server.");
        } else {
          alert("Unexpected error during withdrawal.");
        }
      });
    });
  }
});