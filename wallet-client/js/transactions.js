document.addEventListener('DOMContentLoaded', function () {
  // --- auth guard ---
  const token = localStorage.getItem('jwt');
  if (!token) {
    window.location.href = 'login.html';
    return;
  }

  // --- axios config ---
  const axiosConfig = { headers: { Authorization: `Bearer ${token}` } };

  // --- DOM refs ---
  const filterBtn = document.getElementById('filterBtn');
  const filterDate = document.getElementById('filterDate');
  const typeSelect = document.getElementById('typeSelect');
  const transactionsList = document.getElementById('transactionsList');

  // --- endpoints ---
  const ORIGIN = location.origin;
  const ROOT = '/digital-wallet-plateform'; // adjust if your project root differs
  const PHP_BASE = `${ORIGIN}${ROOT}/wallet-server/user/v1`;

  let loggedInUserId = null;

  // init
  fetchTransactions();
  filterBtn.addEventListener('click', fetchTransactions);

  function fetchTransactions() {
    const params = new URLSearchParams();
    if (filterDate.value) params.append('date', filterDate.value);
    if (typeSelect.value) params.append('type', typeSelect.value);

    axios
      .get(`${PHP_BASE}/get_transactions.php?` + params.toString(), axiosConfig)
      .then((response) => {
        if (response.data?.error) {
          transactionsList.innerHTML = `<p>Error: ${response.data.error}</p>`;
          return;
        }
        loggedInUserId = response.data?.userId ?? null;
        renderTransactions(response.data?.transactions || []);
      })
      .catch((error) => {
        console.error('Error fetching transactions:', error);
        transactionsList.innerHTML = '<p>Failed to load transactions.</p>';
      });
  }

  function renderTransactions(transactions) {
    if (!Array.isArray(transactions) || transactions.length === 0) {
      transactionsList.innerHTML = '<p>No transactions found.</p>';
      return;
    }

    let html = `
      <table class="transactions-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
    `;

    transactions.forEach((tx) => {
      const createdAt = tx.created_at ? new Date(tx.created_at) : null;
      const dateStr = createdAt ? createdAt.toLocaleString() : '-';

      // robust type label (some old rows might miss the column)
      const type = (tx.transaction_type || tx.type || '').toString().toLowerCase() || '-';

      // Default amount display (numeric fallback)
      let amountDisplay = (tx.amount != null) ? String(tx.amount) : '-';

      // Build "Details" smartly per type (and use meta_json for exchange)
      let details = '-';

      if (type === 'transfer') {
        const me = parseInt(loggedInUserId || 0, 10);
        const senderId = parseInt(tx.sender_id || 0, 10);
        const recipientId = parseInt(tx.recipient_id || 0, 10);
        if (me && senderId === me) {
          details = `To: ${tx.recipient_email || 'N/A'}`;
        } else if (me && recipientId === me) {
          details = `From: ${tx.sender_email || 'N/A'}`;
        } else {
          details = `Transfer`;
        }
      } else if (type === 'exchange') {
        // meta_json may contain: from_sym, to_sym, from_amount, to_amount, rate, usdt_value
        let meta = null;
        if (typeof tx.meta_json === 'string' && tx.meta_json.trim().length) {
          try { meta = JSON.parse(tx.meta_json); } catch {}
        }

        if (meta && meta.from_sym && meta.to_sym) {
          // amount column should show what the user **spent** (source amount)
          if (meta.from_amount != null) {
            amountDisplay = `${meta.from_amount} ${meta.from_sym}`;
          }
          const ratePart = (meta.rate != null) ? ` @ ${Number(meta.rate).toFixed(8)}` : '';
          const usdtPart = (meta.usdt_value != null) ? ` (≈ $${Number(meta.usdt_value).toFixed(2)})` : '';
          details = `${meta.from_sym} → ${meta.to_sym}${ratePart}${usdtPart}`;
        } else {
          // no meta_json (older rows) – keep it minimal
          details = 'Currency exchange';
        }
      } else if (type === 'deposit') {
        details = 'Deposit';
      } else if (type === 'withdrawal') {
        details = 'Withdrawal';
      }

      html += `
        <tr>
          <td>${escapeHtml(dateStr)}</td>
          <td>${escapeHtml(capitalize(type))}</td>
          <td>${escapeHtml(amountDisplay)}</td>
          <td>${escapeHtml(details)}</td>
        </tr>
      `;
    });

    html += `</tbody></table>`;
    transactionsList.innerHTML = html;
  }

  // --- helpers ---
  function capitalize(s) {
    if (!s || typeof s !== 'string') return '-';
    return s.charAt(0).toUpperCase() + s.slice(1);
  }
  function escapeHtml(s) {
    return String(s)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#39;');
  }
});
