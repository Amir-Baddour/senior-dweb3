<?php
declare(strict_types=1);
$r = isset($_GET['r']) ? (int)$_GET['r'] : 0;
$a = isset($_GET['a']) ? (int)$_GET['a'] : 0; // cents
$e = isset($_GET['e']) ? (int)$_GET['e'] : 0;
$s = $_GET['s'] ?? '';
if ($r<=0 || $e<=0 || !$s) { http_response_code(400); header('Content-Type: text/plain'); echo "Invalid QR"; exit; }
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Confirm payment</title></head>
<body style="font-family:system-ui,Arial;max-width:480px;margin:40px auto">
  <h2>Confirm payment</h2>
  <p>Recipient ID: <b><?= htmlspecialchars((string)$r) ?></b></p>
  <p>QR expires: <b><?= date('Y-m-d H:i:s', $e) ?></b></p>

  <label>Amount (USD):</label>
  <input id="amount" type="number" step="0.01" min="0.01"
         value="<?= $a ? number_format($a/100, 2, '.', '') : '' ?>" <?= $a?'readonly':'' ?> />
  <br><br>
  <button id="pay">Pay now</button>
  <pre id="out" style="margin-top:12px;white-space:pre-wrap"></pre>

<script>
document.getElementById('pay').addEventListener('click', async () => {
  const token = localStorage.getItem('access_token');
  if (!token) { alert('Please log in first.'); return; }

  const v = document.getElementById('amount').value;
  if (!v || parseFloat(v) <= 0) { alert('Enter a valid amount'); return; }
  const cents = Math.round(parseFloat(v) * 100);

  const fd = new FormData();
  fd.append('r', '<?= $r ?>');
  fd.append('a', String(cents));
  fd.append('e', '<?= $e ?>');
  fd.append('s', '<?= htmlspecialchars($s, ENT_QUOTES) ?>');

  const res = await fetch('/digital-wallet-plateform/wallet-server/user/v1/transfer_via_qr.php', {
    method: 'POST',
    body: fd,
    headers: { 'Authorization': 'Bearer ' + token }
  });

  let data = {};
  try { data = await res.json(); } catch(e) {}
  document.getElementById('out').textContent = JSON.stringify(data, null, 2);
  if (data.success) { alert('Payment completed'); /* window.location.href = '/digital-wallet-plateform/wallet-client/dashboard.html'; */ }
});
</script>
</body>
</html>
