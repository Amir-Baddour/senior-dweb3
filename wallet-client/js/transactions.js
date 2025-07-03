document.addEventListener('DOMContentLoaded', function() {
    // Ensure user is authenticated; redirect to login if no JWT is found
    const token = localStorage.getItem('jwt');
    if (!token) {
        window.location.href = 'login.html';
        return;
    }

    // Configure Axios with the JWT for API calls
    const axiosConfig = {
        headers: {
            'Authorization': `Bearer ${token}`
        }
    };

    // DOM elements for filters and transaction display
    const filterBtn = document.getElementById('filterBtn');
    const filterDate = document.getElementById('filterDate');
    const typeSelect = document.getElementById('typeSelect');
    const transactionsList = document.getElementById('transactionsList');
    let loggedInUserId = null; // Will be set after fetching transactions

    // Load transactions on page load and when the filter button is clicked
    fetchTransactions();
    filterBtn.addEventListener('click', fetchTransactions);

    // Fetch transactions with optional filtering parameters
    function fetchTransactions() {
        const params = new URLSearchParams();
        if (filterDate.value) params.append('date', filterDate.value);
        if (typeSelect.value) params.append('type', typeSelect.value);

        axios.get('http://localhost/digital-wallet-plateform/wallet-server/user/v1/get_transactions.php?' + params.toString(), axiosConfig)
            .then(response => {
                if (response.data.error) {
                    transactionsList.innerHTML = `<p>Error: ${response.data.error}</p>`;
                } else {
                    loggedInUserId = response.data.userId || null;
                    renderTransactions(response.data.transactions);
                }
            })
            .catch(error => {
                console.error("Error fetching transactions:", error);
                transactionsList.innerHTML = "<p>Failed to load transactions.</p>";
            });
    }

    // Render the transactions in a table format
    function renderTransactions(transactions) {
        if (!transactions || transactions.length === 0) {
            transactionsList.innerHTML = "<p>No transactions found.</p>";
            return;
        }

        let html = `<table class="transactions-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>`;

        transactions.forEach(tx => {
            const dateStr = new Date(tx.created_at).toLocaleString();
            let details = '';
            // For transfers, indicate if the user is the sender or recipient
            if (tx.transaction_type === 'transfer') {
                if (parseInt(tx.sender_id) === parseInt(loggedInUserId)) {
                    details = `To: ${tx.recipient_email || 'N/A'}`;
                } else if (parseInt(tx.recipient_id) === parseInt(loggedInUserId)) {
                    details = `From: ${tx.sender_email || 'N/A'}`;
                }
            }

            html += `
              <tr>
                <td>${dateStr}</td>
                <td>${tx.transaction_type}</td>
                <td>${tx.amount}</td>
                <td>${details}</td>
              </tr>`;
        });

        html += `</tbody></table>`;
        transactionsList.innerHTML = html;
    }
});