<?php
require_once __DIR__ . '/../includes/middleware.php';
$admin  = requireAdmin();
$db     = getDB();

$userId = (int)($_GET['id'] ?? 0);
if (!$userId) { header('Location: manage-user.php'); exit; }

$stmt = $db->prepare("SELECT * FROM account_registrations WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) { header('Location: manage-user.php'); exit; }

// Recent deposits
$deposits = $db->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$deposits->execute([$userId]);
$deposits = $deposits->fetchAll();

// Recent withdrawals
$withdrawals = $db->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$withdrawals->execute([$userId]);
$withdrawals = $withdrawals->fetchAll();

// Holdings
$holdings = $db->prepare("SELECT * FROM user_holdings WHERE user_id = ? AND quantity > 0 ORDER BY invested_amount DESC");
$holdings->execute([$userId]);
$holdings = $holdings->fetchAll();

// Bank details
$bankDetails = $db->prepare("SELECT * FROM user_payment_details WHERE user_id = ?");
$bankDetails->execute([$userId]);
$bankDetails = $bankDetails->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User: <?= htmlspecialchars($user['first_name']) ?> — Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F5F7FA; color: #1A1A1A; min-height: 100vh; overflow-x: hidden; }
.back-link { display: inline-flex; align-items: center; gap: 6px; color: #6B7280; text-decoration: none; font-size: 13px; margin-bottom: 20px; }
.back-link:hover { color: #1A1A1A; }
.user-hero { background: #fff; border: 1px solid #E5E7EB; border-radius: 14px; padding: 24px 28px; margin-bottom: 24px; display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; flex-wrap: wrap; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.user-hero .avatar { width: 54px; height: 54px; border-radius: 50%; background: #DBEAFE; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 700; color: #2563EB; flex-shrink: 0; }
.user-hero .name { font-size: 20px; font-weight: 600; }
.user-hero .sub { font-size: 13px; color: #6B7280; margin-top: 3px; }
.hero-left { display: flex; gap: 16px; align-items: center; }
.hero-right { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.badge { display: inline-flex; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.badge-pending  { background: #FEF3C7; color: #D97706; }
.badge-approved { background: #D1FAE5; color: #059669; }
.badge-rejected { background: #FEE2E2; color: #DC2626; }
.badge-blocked  { background: #FEE2E2; color: #DC2626; }
.action-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; text-decoration: none; }
.btn-approve { background: #D1FAE5; color: #059669; }
.btn-reject  { background: #FEE2E2; color: #DC2626; }
.btn-credit  { background: #DBEAFE; color: #2563EB; }
.btn-debit   { background: #FEF3C7; color: #D97706; }
.action-btn:hover { opacity: .8; }
.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px; }
.stat-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.stat-card .s-label { font-size: 12px; color: #6B7280; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 6px; }
.stat-card .s-value { font-size: 20px; font-weight: 700; }
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
.three-col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }

/* ── Mobile Responsive ── */
@media (max-width: 768px) {
  body { overflow-x: hidden; }
  .main { padding: 24px 16px; width: 100%; }
  .user-hero { flex-direction: column; }
  .hero-left, .hero-right { width: 100%; }
  .stats-row { grid-template-columns: repeat(2, 1fr); }
  .two-col, .three-col { grid-template-columns: 1fr; }
  table { display: block; overflow-x: auto; white-space: nowrap; }
  th, td { padding: 10px 12px; font-size: 13px; }
  .modal { margin: 16px; padding: 20px; max-width: calc(100% - 32px); }
  .modal h3 { font-size: 17px; }
  .modal-actions { flex-direction: column; }
  .modal-btn { width: 100%; }
  .info-grid { grid-template-columns: 1fr; }
  .info-row-item:nth-child(odd) { border-right: none; }
  .action-btn { padding: 6px 10px; font-size: 11px; }
}
@media (max-width: 480px) {
  .main { padding: 20px 12px; }
  .stats-row { grid-template-columns: 1fr; }
  .action-btn { padding: 5px 8px; font-size: 10px; }
}
@media (max-width: 1100px) { .three-col, .two-col { grid-template-columns: 1fr; } .stats-row { grid-template-columns: 1fr 1fr; } }
.card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.card-header { padding: 16px 20px; border-bottom: 1px solid #E5E7EB; }
.card-header h3 { font-size: 15px; font-weight: 600; color: #1A1A1A; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
.info-row-item { padding: 12px 20px; border-bottom: 1px solid #F3F4F6; }
.info-row-item:nth-child(odd) { border-right: 1px solid #F3F4F6; }
.info-row-item .i-label { font-size: 11px; color: #9CA3AF; text-transform: uppercase; margin-bottom: 3px; }
.info-row-item .i-val { font-size: 14px; color: #1A1A1A; }
table { width: 100%; border-collapse: collapse; }
th { font-size: 11px; color: #6B7280; text-transform: uppercase; letter-spacing: .05em; padding: 10px 16px; text-align: left; background: #F9FAFB; font-weight: 600; }
td { padding: 11px 16px; font-size: 13px; border-top: 1px solid #F3F4F6; color: #1A1A1A; }
.empty { padding: 20px; text-align: center; color: #9CA3AF; font-size: 13px; }
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 1000; align-items: center; justify-content: center; }
.modal-overlay.open { display: flex; }
.modal { background: #fff; border: 1px solid #E5E7EB; border-radius: 16px; padding: 28px; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
.modal h3 { font-size: 17px; font-weight: 600; margin-bottom: 18px; color: #1A1A1A; }
.modal label { display: block; font-size: 13px; color: #6B7280; margin-bottom: 5px; }
.modal input, .modal textarea { width: 100%; background: #F5F7FA; border: 1px solid #E5E7EB; border-radius: 8px; color: #1A1A1A; padding: 10px 12px; font-size: 14px; font-family: inherit; outline: none; margin-bottom: 14px; }
.modal input:focus, .modal textarea:focus { border-color: #2563EB; }
.modal textarea { resize: vertical; min-height: 70px; }
.modal-actions { display: flex; gap: 10px; margin-top: 6px; }
.modal-btn { flex: 1; padding: 11px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.modal-btn.confirm { background: #2563EB; color: #fff; }
.modal-btn.cancel  { background: #F3F4F6; color: #6B7280; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin-header.php'; ?>

<div class="main">
  <a href="manage-user.php" class="back-link"><i class="fa fa-arrow-left"></i> Back to Users</a>

  <!-- User Hero -->
  <div class="user-hero">
    <div class="hero-left">
      <div class="avatar"><?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?></div>
      <div>
        <div class="name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
        <div class="sub">@<?= htmlspecialchars($user['username']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($user['email']) ?></div>
        <div style="margin-top:8px">
          <?php if ($user['is_blocked']): ?>
            <span class="badge badge-blocked">Blocked</span>
          <?php else: ?>
            <span class="badge badge-<?= $user['status'] ?>"><?= ucfirst($user['status']) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="hero-right">
      <?php if ($user['status'] === 'pending'): ?>
        <button class="action-btn btn-approve" onclick="updateStatus('approved')"><i class="fa fa-check"></i> Approve</button>
        <button class="action-btn btn-reject"  onclick="updateStatus('rejected')"><i class="fa fa-times"></i> Reject</button>
      <?php endif; ?>
      <button class="action-btn btn-credit" onclick="openBalanceModal('credit')"><i class="fa fa-plus"></i> Credit</button>
      <button class="action-btn btn-debit"  onclick="openBalanceModal('debit')"><i class="fa fa-minus"></i> Debit</button>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="s-label">Balance</div>
      <div class="s-value" style="color:#4ade80"><?= formatINR((float)$user['current_balance']) ?></div>
    </div>
    <div class="stat-card">
      <div class="s-label">Portfolio Value</div>
      <div class="s-value"><?= formatINR((float)$user['portfolio_value']) ?></div>
    </div>
    <div class="stat-card">
      <div class="s-label">Total Invested</div>
      <div class="s-value"><?= formatINR((float)$user['total_invested']) ?></div>
    </div>
    <div class="stat-card">
      <div class="s-label">Total P&amp;L</div>
      <div class="s-value" style="color:<?= $user['total_pnl'] >= 0 ? '#4ade80' : '#f87171' ?>">
        <?= ($user['total_pnl'] >= 0 ? '+' : '') . formatINR((float)$user['total_pnl']) ?>
      </div>
    </div>
  </div>

  <!-- Personal Info & Bank Details -->
  <div class="two-col">
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-user" style="color:#6366f1;margin-right:8px"></i>Personal Info</h3></div>
      <div class="info-grid">
        <?php $fields = ['Phone' => 'phone', 'DOB' => 'dob', 'City' => 'city', 'Country' => 'country', 'Employment' => 'employment_status', 'Tax ID' => 'tax_id', 'Annual Income' => 'annual_income', 'Net Worth' => 'net_worth'];
        foreach ($fields as $label => $key): ?>
          <div class="info-row-item">
            <div class="i-label"><?= $label ?></div>
            <div class="i-val"><?= $user[$key] ? htmlspecialchars(in_array($key, ['annual_income','net_worth']) ? formatINR((float)$user[$key]) : $user[$key]) : '—' ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-university" style="color:#6366f1;margin-right:8px"></i>Bank / UPI Details</h3></div>
      <div class="info-grid">
        <?php if ($bankDetails): ?>
          <div class="info-row-item"><div class="i-label">Bank Name</div><div class="i-val"><?= htmlspecialchars($bankDetails['bank_name'] ?? '—') ?></div></div>
          <div class="info-row-item"><div class="i-label">Account No.</div><div class="i-val"><?= htmlspecialchars($bankDetails['account_number'] ?? '—') ?></div></div>
          <div class="info-row-item"><div class="i-label">IFSC</div><div class="i-val"><?= htmlspecialchars($bankDetails['ifsc_code'] ?? '—') ?></div></div>
          <div class="info-row-item"><div class="i-label">UPI ID</div><div class="i-val"><?= htmlspecialchars($bankDetails['upi_id'] ?? '—') ?></div></div>
          <div class="info-row-item"><div class="i-label">Account Type</div><div class="i-val"><?= ucfirst($bankDetails['account_type'] ?? '—') ?></div></div>
        <?php else: ?>
          <div class="info-row-item" style="grid-column:1/-1"><div class="i-val" style="color:#4b5563">No bank details saved.</div></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Deposits / Withdrawals / Holdings -->
  <div class="three-col">
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-money-bill-wave" style="color:#6366f1;margin-right:6px"></i>Recent Deposits</h3></div>
      <table>
        <thead><tr><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($deposits as $d): ?>
          <tr>
            <td><?= formatINR((float)$d['amount']) ?></td>
            <td><span class="badge badge-<?= $d['status'] ?>"><?= ucfirst($d['status']) ?></span></td>
            <td style="color:#4b5563"><?= date('d M', strtotime($d['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($deposits)): ?><tr><td colspan="3" class="empty">No deposits.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-wallet" style="color:#6366f1;margin-right:6px"></i>Recent Withdrawals</h3></div>
      <table>
        <thead><tr><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($withdrawals as $w): ?>
          <tr>
            <td><?= formatINR((float)$w['amount']) ?></td>
            <td><span class="badge badge-<?= $w['status'] ?>"><?= ucfirst($w['status']) ?></span></td>
            <td style="color:#4b5563"><?= date('d M', strtotime($w['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($withdrawals)): ?><tr><td colspan="3" class="empty">No withdrawals.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-briefcase" style="color:#6366f1;margin-right:6px"></i>Holdings</h3></div>
      <table>
        <thead><tr><th>Symbol</th><th>Qty</th><th>P&amp;L</th></tr></thead>
        <tbody>
          <?php foreach ($holdings as $h): ?>
          <tr>
            <td style="font-weight:600"><?= htmlspecialchars($h['symbol']) ?></td>
            <td><?= $h['quantity'] ?></td>
            <td style="color:<?= $h['pnl'] >= 0 ? '#4ade80' : '#f87171' ?>"><?= ($h['pnl'] >= 0 ? '+' : '') . formatINR((float)$h['pnl']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($holdings)): ?><tr><td colspan="3" class="empty">No holdings.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Balance Adjust Modal -->
<div class="modal-overlay" id="balanceModal">
  <div class="modal">
    <h3 id="balanceModalTitle">Adjust Balance</h3>
    <input type="hidden" id="balanceType">
    <label>Amount (₹)</label>
    <input type="number" id="balanceAmount" placeholder="Enter amount" min="1">
    <label>Reason</label>
    <textarea id="balanceReason" placeholder="Reason for adjustment..."></textarea>
    <div class="modal-actions">
      <button class="modal-btn cancel" onclick="closeBalanceModal()">Cancel</button>
      <button class="modal-btn confirm" onclick="submitBalance()">Confirm</button>
    </div>
  </div>
</div>

<script>
function updateStatus(status) {
  if (!confirm('Change user status to ' + status + '?')) return;
  fetch('../api/admin-update-user.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ user_id: <?= $user['id'] ?>, status })
  }).then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.message); });
}

function openBalanceModal(type) {
  document.getElementById('balanceType').value = type;
  document.getElementById('balanceModalTitle').textContent = (type === 'credit' ? 'Credit' : 'Debit') + ' Balance';
  document.getElementById('balanceAmount').value  = '';
  document.getElementById('balanceReason').value  = '';
  document.getElementById('balanceModal').classList.add('open');
}
function closeBalanceModal() { document.getElementById('balanceModal').classList.remove('open'); }

async function submitBalance() {
  const type   = document.getElementById('balanceType').value;
  const amount = parseFloat(document.getElementById('balanceAmount').value);
  const reason = document.getElementById('balanceReason').value.trim();
  if (!amount || amount <= 0) { alert('Enter a valid amount.'); return; }
  if (!reason) { alert('Reason is required.'); return; }

  const res  = await fetch('../api/admin-adjust-balance.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ user_id: <?= $user['id'] ?>, type, amount, reason })
  });
  const data = await res.json();
  if (data.success) { location.reload(); } else { alert(data.message); }
}

document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('open');
}));


</script>
</body>
</html>
