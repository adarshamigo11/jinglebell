<?php
require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
$db   = getDB();

// Fetch saved bank details
$bankDetails = $db->prepare("SELECT * FROM user_payment_details WHERE user_id = ?");
$bankDetails->execute([$user['id']]);
$bankDetails = $bankDetails->fetch();

// Fetch withdrawal history
$history = $db->prepare("SELECT id, amount, bank_name, upi_id, status, admin_remark, created_at FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$history->execute([$user['id']]);
$history = $history->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Withdraw — TradeZenfy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../public/assets/css/groww-ui.css">
<link rel="stylesheet" href="../public/assets/css/layout-new.css">
<style>
.balance-bar{background:var(--groww-card);border-radius:12px;padding:20px 24px;margin-bottom:24px;display:flex;align-items:center;gap:16px;box-shadow:0 1px 3px rgba(0,0,0,0.08)}
.balance-bar .bal-label{font-size:13px;color:var(--groww-text-secondary)}
.balance-bar .bal-value{font-size:24px;font-weight:700;color:var(--groww-text)}
.two-col{display:grid;grid-template-columns:420px 1fr;gap:24px}
@media(max-width:960px){.two-col{grid-template-columns:1fr}}
.tab-row{display:flex;gap:0;margin-bottom:20px;border:1px solid var(--groww-border);border-radius:8px;overflow:hidden}
.tab-btn{flex:1;padding:10px;background:var(--groww-card);color:var(--groww-text-secondary);font-size:14px;font-weight:500;cursor:pointer;border:none;transition:all .15s}
.tab-btn.active{background:var(--groww-green);color:#fff}
.info-box{background:#E0F2FE;border:1px solid #BAE6FD;border-radius:8px;padding:14px 16px;font-size:13px;color:#0369A1;margin-bottom:18px}
.saved-tag{display:inline-block;background:rgba(0,208,156,0.1);color:var(--groww-green);font-size:11px;padding:2px 8px;border-radius:20px;margin-left:6px}
@media (max-width: 768px) {
  .main { overflow-x: hidden; }
  .top-header, .page-header { padding: 12px 16px; position: static; }
  .content { padding: 0; }
  .two-col { grid-template-columns: 1fr; }
  /* Tables: stack on mobile */
  table { display: block; width: 100%; overflow-x: hidden; }
  table thead { display: none; }
  table tbody { display: block; width: 100%; }
  table tr { display: block; padding: 12px; border-bottom: 1px solid var(--border); }
  table td { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border: none; font-size: 14px; }
  table td::before { content: attr(data-label); font-weight: 600; color: var(--text-secondary); font-size: 12px; margin-right: 12px; }
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/user-top-nav.php'; ?>

<div class="main">
  <div class="top-header">
    <div class="header-left">
      <h1>Withdraw Funds</h1>
      <p>Request a withdrawal to your bank or UPI account</p>
    </div>
  </div>

  <div class="content">

  <div class="balance-bar">
    <i class="fa fa-wallet" style="color:var(--groww-green);font-size:20px"></i>
    <div>
      <div class="bal-label">Available for Withdrawal</div>
      <div class="bal-value"><?= formatINR((float)$user['current_balance']) ?></div>
    </div>
  </div>

  <div class="two-col">
    <!-- Form -->
    <div class="card">
      <div class="card-header"><h3>New Withdrawal Request</h3></div>
      <div class="card-body">
        <div id="alertError" class="alert alert-error"></div>
        <div id="alertSuccess" class="alert alert-success"></div>

        <div class="form-group">
          <label>Amount (₹)</label>
          <div class="input-wrap">
            <i class="fa fa-rupee-sign"></i>
            <input type="number" id="amount" placeholder="Minimum ₹100" min="100" max="<?= $user['current_balance'] ?>">
          </div>
        </div>

        <div class="form-group">
          <label>Withdraw via</label>
          <div class="tab-row">
            <button class="tab-btn active" onclick="switchTab('bank', this)"><i class="fa fa-university"></i> Bank Transfer</button>
            <button class="tab-btn" onclick="switchTab('upi', this)"><i class="fa fa-mobile-alt"></i> UPI</button>
          </div>
        </div>

        <!-- Bank Fields -->
        <div id="bankFields">
          <div class="form-group">
            <label>Bank Name <?php if ($bankDetails && $bankDetails['bank_name']): ?><span class="saved-tag">Saved</span><?php endif; ?></label>
            <div class="input-wrap"><i class="fa fa-university"></i><input type="text" id="bank_name" value="<?= htmlspecialchars($bankDetails['bank_name'] ?? '') ?>" placeholder="e.g. HDFC Bank"></div>
          </div>
          <div class="form-group">
            <label>Account Number <?php if ($bankDetails && $bankDetails['account_number']): ?><span class="saved-tag">Saved</span><?php endif; ?></label>
            <div class="input-wrap"><i class="fa fa-credit-card"></i><input type="text" id="account_number" value="<?= htmlspecialchars($bankDetails['account_number'] ?? '') ?>" placeholder="Account number"></div>
          </div>
          <div class="form-group">
            <label>IFSC Code <?php if ($bankDetails && $bankDetails['ifsc_code']): ?><span class="saved-tag">Saved</span><?php endif; ?></label>
            <div class="input-wrap"><i class="fa fa-code"></i><input type="text" id="ifsc_code" value="<?= htmlspecialchars($bankDetails['ifsc_code'] ?? '') ?>" placeholder="e.g. HDFC0001234"></div>
          </div>
        </div>

        <!-- UPI Fields -->
        <div id="upiFields" style="display:none">
          <div class="form-group">
            <label>UPI ID <?php if ($bankDetails && $bankDetails['upi_id']): ?><span class="saved-tag">Saved</span><?php endif; ?></label>
            <div class="input-wrap"><i class="fa fa-at"></i><input type="text" id="upi_id" value="<?= htmlspecialchars($bankDetails['upi_id'] ?? '') ?>" placeholder="yourname@upi"></div>
          </div>
        </div>

        <div class="info-box"><i class="fa fa-info-circle"></i> Funds are held immediately and released within 24–48 hours after admin approval. If rejected, balance is refunded automatically.</div>

        <button class="btn" id="submitBtn" onclick="submitWithdrawal()">
          <i class="fa fa-spinner fa-spin" id="spinner" style="display:none"></i>
          <span id="btnText">Request Withdrawal</span>
        </button>
      </div>
    </div>

    <!-- History -->
    <div class="card">
      <div class="card-header"><h3>Withdrawal History</h3></div>
      <table>
        <thead><tr><th>Amount</th><th>To</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($history as $w): ?>
          <tr>
            <td><?= formatINR((float)$w['amount']) ?></td>
            <td style="font-size:12px;color:var(--groww-text-secondary)"><?= htmlspecialchars($w['upi_id'] ?? $w['bank_name'] ?? '—') ?></td>
            <td>
              <span class="badge badge-<?= $w['status'] ?>"><?= ucfirst($w['status']) ?></span>
              <?php if ($w['status'] === 'rejected' && $w['admin_remark']): ?>
                <div style="font-size:11px;color:var(--groww-red);margin-top:3px"><?= htmlspecialchars($w['admin_remark']) ?></div>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--groww-text-secondary)"><?= date('d M Y', strtotime($w['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($history)): ?>
            <tr><td colspan="4" class="empty">No withdrawals yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  </div>
</div>

<script>
let currentTab = 'bank';

function switchTab(tab, btn) {
  currentTab = tab;
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('bankFields').style.display = tab === 'bank' ? 'block' : 'none';
  document.getElementById('upiFields').style.display  = tab === 'upi'  ? 'block' : 'none';
}

async function submitWithdrawal() {
  const btn    = document.getElementById('submitBtn');
  const errBox = document.getElementById('alertError');
  const sucBox = document.getElementById('alertSuccess');
  errBox.style.display = 'none';
  sucBox.style.display = 'none';

  const amount = parseFloat(document.getElementById('amount').value);
  if (!amount || amount < 100) { showErr('Minimum withdrawal is ₹100.'); return; }

  const payload = { amount, method: currentTab };

  if (currentTab === 'bank') {
    payload.bank_name      = document.getElementById('bank_name').value.trim();
    payload.account_number = document.getElementById('account_number').value.trim();
    payload.ifsc_code      = document.getElementById('ifsc_code').value.trim();
    if (!payload.bank_name || !payload.account_number || !payload.ifsc_code) {
      showErr('Please fill in all bank details.'); return;
    }
  } else {
    payload.upi_id = document.getElementById('upi_id').value.trim();
    if (!payload.upi_id) { showErr('Please enter your UPI ID.'); return; }
  }

  btn.disabled = true;
  document.getElementById('spinner').style.display = 'inline';
  document.getElementById('btnText').textContent = 'Submitting…';

  try {
    const res  = await fetch('../api/create-withdrawal.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      sucBox.textContent = data.message;
      sucBox.style.display = 'block';
      setTimeout(() => location.reload(), 2500);
    } else {
      showErr(data.message);
      resetBtn();
    }
  } catch {
    showErr('Network error. Please try again.');
    resetBtn();
  }
}

function showErr(msg) {
  const b = document.getElementById('alertError');
  b.textContent = msg; b.style.display = 'block';
}
function resetBtn() {
  const btn = document.getElementById('submitBtn');
  btn.disabled = false;
  document.getElementById('spinner').style.display = 'none';
  document.getElementById('btnText').textContent = 'Request Withdrawal';
}

</script>

</body>
</html>
