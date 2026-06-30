<?php
require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
$db   = getDB();

// Fetch deposit history
$deposits = $db->prepare("SELECT id, amount, payment_method, transaction_id, status, admin_remark, created_at FROM payments WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$deposits->execute([$user['id']]);
$deposits = $deposits->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Money — TradeZenfy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../public/assets/css/groww-ui.css">
<link rel="stylesheet" href="../public/assets/css/layout-new.css">
<style>
.balance-bar{background:var(--groww-card);border-radius:12px;padding:20px 24px;margin-bottom:24px;display:flex;align-items:center;gap:16px;box-shadow:0 1px 3px rgba(0,0,0,0.08)}
.balance-bar .bal-label{font-size:13px;color:var(--groww-text-secondary)}
.balance-bar .bal-value{font-size:24px;font-weight:700;color:var(--groww-text)}
.two-col{display:grid;grid-template-columns:420px 1fr;gap:24px}
@media(max-width:960px){.two-col{grid-template-columns:1fr}}
.method-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:18px}
.method-btn{padding:12px 8px;border:1px solid var(--groww-border);border-radius:8px;background:var(--groww-card);color:var(--groww-text-secondary);font-size:13px;font-weight:500;cursor:pointer;text-align:center;transition:all .15s}
.method-btn:hover,.method-btn.selected{border-color:var(--groww-green);background:rgba(0,208,156,0.08);color:var(--groww-green)}
.method-btn i{display:block;font-size:20px;margin-bottom:6px}
.file-upload{border:2px dashed var(--groww-border);border-radius:8px;padding:24px;text-align:center;cursor:pointer;transition:all .2s;background:white}
.file-upload:hover{border-color:var(--groww-green)}
.file-upload i{font-size:32px;color:var(--groww-text-secondary);margin-bottom:10px;display:block}
.file-upload p{font-size:13px;color:var(--groww-text-secondary)}
.file-upload.has-file{border-color:var(--groww-green);background:rgba(0,208,156,0.05)}
.file-upload.has-file p{color:var(--groww-green)}
.info-box{background:#E0F2FE;border:1px solid #BAE6FD;border-radius:8px;padding:14px 16px;font-size:13px;color:#0369A1;margin-bottom:18px}
.info-box i{margin-right:6px}
.admin-account-card{background:white;border:1px solid var(--groww-border);border-radius:10px;padding:16px;margin-bottom:12px}
.admin-account-card.default{border-color:var(--groww-green);background:rgba(0,208,156,0.05)}
.admin-account-card .default-tag{display:inline-block;background:var(--groww-green);color:#fff;font-size:10px;padding:2px 8px;border-radius:20px;margin-bottom:10px;font-weight:600}
.account-info-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--groww-border)}
.account-info-row:last-child{border-bottom:none}
.account-info-row label{font-size:12px;color:var(--groww-text-secondary);margin:0}
.account-info-row .value{font-size:14px;color:var(--groww-text);font-family:monospace}
.qr-section{text-align:center;margin-top:12px}
.qr-section img{max-width:180px;max-height:180px;border-radius:8px;border:1px solid var(--groww-border)}
.copy-btn{background:var(--groww-hover);border:none;color:var(--groww-text-secondary);padding:4px 10px;border-radius:4px;font-size:11px;cursor:pointer;margin-left:8px;transition:all 0.2s}
.copy-btn:hover{background:var(--groww-green);color:white}
.no-accounts{text-align:center;padding:30px;color:var(--groww-text-secondary);font-size:13px}

/* Mobile bottom padding */
@media (max-width: 768px) {
  body {
    padding-bottom: 70px;
  }
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
  <div class="page-header">
    <h1><i class="fa fa-plus-circle" style="color:#6366f1;margin-right:10px"></i>Add Funds</h1>
    <p>Deposit funds into your trading account</p>
  </div>

  <div class="balance-bar">
    <i class="fa fa-wallet" style="color:#6366f1;font-size:20px"></i>
    <div>
      <div class="bal-label">Current Balance</div>
      <div class="bal-value"><?= formatINR((float)$user['current_balance']) ?></div>
    </div>
  </div>

  <div class="two-col">
    <!-- Deposit Form -->
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-paper-plane" style="color:#6366f1;margin-right:8px"></i>New Deposit Request</h3></div>
      <div class="card-body">
        <div class="info-box">
          <i class="fa fa-info-circle"></i>
          Transfer funds to one of our accounts below and submit the transaction details. Your balance will be credited after admin verification.
        </div>

        <!-- Admin Bank Accounts -->
        <div class="admin-accounts-section" id="adminAccountsSection">
          <h3><i class="fa fa-university" style="color:#6366f1;margin-right:8px"></i>Our Bank Accounts</h3>
          <div id="adminAccountsList">
            <div class="no-accounts"><i class="fa fa-spinner fa-spin"></i> Loading accounts...</div>
          </div>
        </div>

        <div id="alertError" class="alert alert-error"></div>
        <div id="alertSuccess" class="alert alert-success"></div>

        <div class="form-group">
          <label>Payment Method</label>
          <div class="method-grid">
            <div class="method-btn selected" data-method="upi" onclick="selectMethod(this)">
              <i class="fa fa-mobile-alt"></i>UPI
            </div>
            <div class="method-btn" data-method="neft" onclick="selectMethod(this)">
              <i class="fa fa-university"></i>NEFT
            </div>
            <div class="method-btn" data-method="rtgs" onclick="selectMethod(this)">
              <i class="fa fa-bolt"></i>RTGS
            </div>
            <div class="method-btn" data-method="imps" onclick="selectMethod(this)">
              <i class="fa fa-exchange-alt"></i>IMPS
            </div>
            <div class="method-btn" data-method="netbanking" onclick="selectMethod(this)">
              <i class="fa fa-globe"></i>Net Banking
            </div>
          </div>
          <input type="hidden" id="payment_method" value="upi">
        </div>

        <div class="form-group">
          <label>Amount (₹)</label>
          <div class="input-wrap">
            <i class="fa fa-rupee-sign"></i>
            <input type="number" id="amount" placeholder="Minimum ₹100" min="100">
          </div>
        </div>

        <div class="form-group">
          <label>Transaction ID / UTR Number</label>
          <div class="input-wrap">
            <i class="fa fa-hashtag"></i>
            <input type="text" id="transaction_id" placeholder="Enter your transaction reference">
          </div>
        </div>

        <div class="form-group">
          <label>Payment Proof (Screenshot)</label>
          <div class="file-upload" id="fileZone" onclick="document.getElementById('proof_image').click()">
            <i class="fa fa-cloud-upload-alt"></i>
            <p id="fileLabel">Click to upload payment screenshot (JPG, PNG, WebP — max 5MB)</p>
          </div>
          <input type="file" id="proof_image" accept="image/*" style="display:none" onchange="fileSelected(this)">
        </div>

        <button class="btn" id="submitBtn" onclick="submitDeposit()">
          <i class="fa fa-spinner fa-spin" id="spinner" style="display:none"></i>
          <span id="btnText">Submit Deposit Request</span>
        </button>
      </div>
    </div>

    <!-- History -->
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-history" style="color:#6366f1;margin-right:8px"></i>Deposit History</h3></div>
      <table>
        <thead><tr><th>Amount</th><th>Method</th><th>TXN ID</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($deposits as $d): ?>
          <tr>
            <td><?= formatINR((float)$d['amount']) ?></td>
            <td style="font-size:12px;color:#94a3b8;text-transform:uppercase"><?= htmlspecialchars($d['payment_method']) ?></td>
            <td style="font-size:12px;color:#94a3b8;max-width:120px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($d['transaction_id'] ?? '—') ?></td>
            <td>
              <span class="badge badge-<?= $d['status'] ?>"><?= ucfirst($d['status']) ?></span>
              <?php if ($d['status'] === 'rejected' && $d['admin_remark']): ?>
                <div style="font-size:11px;color:#f87171;margin-top:3px"><?= htmlspecialchars($d['admin_remark']) ?></div>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:#4b5563"><?= date('d M Y', strtotime($d['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($deposits)): ?>
            <tr><td colspan="5" class="empty">No deposits yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
let selectedMethod = 'upi';

function selectMethod(el) {
  document.querySelectorAll('.method-btn').forEach(b => b.classList.remove('selected'));
  el.classList.add('selected');
  selectedMethod = el.dataset.method;
  document.getElementById('payment_method').value = selectedMethod;
}

function fileSelected(input) {
  const zone  = document.getElementById('fileZone');
  const label = document.getElementById('fileLabel');
  if (input.files.length) {
    zone.classList.add('has-file');
    label.textContent = input.files[0].name;
  }
}

// Load admin bank accounts
async function loadAdminAccounts() {
  try {
    const res = await fetch('../api/get-admin-bank-accounts.php');
    const data = await res.json();
    if (data.success && data.accounts.length > 0) {
      renderAdminAccounts(data.accounts);
    } else {
      document.getElementById('adminAccountsList').innerHTML = `
        <div class="no-accounts">
          <i class="fa fa-university" style="font-size:24px;display:block;margin-bottom:8px;"></i>
          Please contact admin for bank account details
        </div>
      `;
    }
  } catch (err) {
    document.getElementById('adminAccountsList').innerHTML = `
      <div class="no-accounts">Failed to load bank accounts</div>
    `;
  }
}

function renderAdminAccounts(accounts) {
  const container = document.getElementById('adminAccountsList');
  container.innerHTML = accounts.map(acc => `
    <div class="admin-account-card ${acc.is_default == 1 ? 'default' : ''}">
      ${acc.is_default == 1 ? '<span class="default-tag">RECOMMENDED</span>' : ''}
      <div class="account-info-row">
        <label>Account Name</label>
        <div class="value">${escapeHtml(acc.account_name)}</div>
      </div>
      <div class="account-info-row">
        <label>Bank Name</label>
        <div class="value">${escapeHtml(acc.bank_name)}</div>
      </div>
      <div class="account-info-row">
        <label>Account Number</label>
        <div class="value">
          ${escapeHtml(acc.account_number)}
          <button class="copy-btn" onclick="copyToClipboard('${escapeHtml(acc.account_number)}')">Copy</button>
        </div>
      </div>
      <div class="account-info-row">
        <label>IFSC Code</label>
        <div class="value">
          ${escapeHtml(acc.ifsc_code)}
          <button class="copy-btn" onclick="copyToClipboard('${escapeHtml(acc.ifsc_code)}')">Copy</button>
        </div>
      </div>
      ${acc.upi_id ? `
      <div class="account-info-row">
        <label>UPI ID</label>
        <div class="value">
          ${escapeHtml(acc.upi_id)}
          <button class="copy-btn" onclick="copyToClipboard('${escapeHtml(acc.upi_id)}')">Copy</button>
        </div>
      </div>
      ` : ''}
      ${acc.qr_code_image ? `
      <div class="qr-section">
        <img src="../${escapeHtml(acc.qr_code_image)}" alt="QR Code">
      </div>
      ` : ''}
    </div>
  `).join('');
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => {
    alert('Copied to clipboard!');
  }).catch(() => {
    // Fallback
    const textarea = document.createElement('textarea');
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
    alert('Copied to clipboard!');
  });
}

// Load accounts on page load
loadAdminAccounts();

async function submitDeposit() {
  const btn    = document.getElementById('submitBtn');
  const errBox = document.getElementById('alertError');
  const sucBox = document.getElementById('alertSuccess');
  errBox.style.display = 'none';
  sucBox.style.display = 'none';

  const amount = document.getElementById('amount').value;
  const txnId  = document.getElementById('transaction_id').value.trim();
  const file   = document.getElementById('proof_image').files[0];

  if (!amount || amount < 100) { showErr('Minimum deposit is ₹100.'); return; }
  if (!txnId) { showErr('Please enter your Transaction ID / UTR.'); return; }

  const fd = new FormData();
  fd.append('amount', amount);
  fd.append('payment_method', selectedMethod);
  fd.append('transaction_id', txnId);
  if (file) fd.append('proof_image', file);

  btn.disabled = true;
  document.getElementById('spinner').style.display = 'inline';
  document.getElementById('btnText').textContent = 'Submitting…';

  try {
    const res  = await fetch('../api/submit-payment.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      sucBox.textContent = data.message;
      sucBox.style.display = 'block';
      setTimeout(() => location.reload(), 2000);
    } else {
      showErr(data.message || 'Unknown error occurred');
      resetBtn();
    }
  } catch (err) {
    showErr('Network error: ' + err.message + '. Please check browser console for details.');
    console.error('Deposit error:', err);
    resetBtn();
  }
}

function showErr(msg) {
  const b = document.getElementById('alertError');
  b.textContent = msg;
  b.style.display = 'block';
}

function resetBtn() {
  const btn = document.getElementById('submitBtn');
  btn.disabled = false;
  document.getElementById('spinner').style.display = 'none';
  document.getElementById('btnText').textContent = 'Submit Deposit Request';
}

</script>

</body>
</html>
