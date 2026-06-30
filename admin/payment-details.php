<?php
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
$db    = getDB();

$tab = $_GET['tab'] ?? 'deposits';

// Deposits data
$status  = $_GET['status'] ?? 'pending';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = $status !== 'all' ? "WHERE p.status = ?" : "";
$params = $status !== 'all' ? [$status] : [];

$total     = $db->prepare("SELECT COUNT(*) FROM payments p $where");
$total->execute($params);
$totalRows = $total->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $db->prepare("
    SELECT p.*, a.first_name, a.last_name, a.email
    FROM payments p
    JOIN account_registrations a ON a.id = p.user_id
    $where
    ORDER BY p.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Deposits — TradeZenfy Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F5F7FA; color: #1A1A1A; min-height: 100vh; overflow-x: hidden; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.page-header h1 { font-size: 22px; font-weight: 600; }
.filters { display: flex; gap: 10px; margin-bottom: 20px; }
.filter-btn { padding: 7px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; border: 1px solid #E5E7EB; background: #fff; color: #6B7280; text-decoration: none; }
.filter-btn.active, .filter-btn:hover { background: #2563EB; color: #fff; border-color: #2563EB; }
.card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
table { width: 100%; border-collapse: collapse; }
th { font-size: 11px; color: #6B7280; text-transform: uppercase; letter-spacing: .05em; padding: 12px 16px; text-align: left; background: #F9FAFB; font-weight: 600; }
td { padding: 13px 16px; font-size: 14px; border-top: 1px solid #F3F4F6; vertical-align: middle; color: #1A1A1A; }
tr:hover td { background: #F9FAFB; }
.badge { display: inline-flex; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-pending  { background: #FEF3C7; color: #D97706; }
.badge-approved { background: #D1FAE5; color: #059669; }
.badge-rejected { background: #FEE2E2; color: #DC2626; }
.action-btn { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; border: none; transition: opacity .15s; margin-right: 4px; }
.btn-approve { background: #D1FAE5; color: #059669; }
.btn-reject  { background: #FEE2E2; color: #DC2626; }
.btn-view    { background: #DBEAFE; color: #2563EB; text-decoration: none; }
.action-btn:hover { opacity: .75; }
.proof-link { display: inline-flex; align-items: center; gap: 5px; color: #2563EB; font-size: 12px; text-decoration: none; }
.proof-link:hover { text-decoration: underline; }
.empty { padding: 40px; text-align: center; color: #9CA3AF; }
.pagination { display: flex; gap: 8px; margin-top: 20px; justify-content: center; }
.pagination a, .pagination span { padding: 7px 13px; border-radius: 7px; font-size: 13px; background: #fff; border: 1px solid #E5E7EB; color: #6B7280; text-decoration: none; }
.pagination a:hover, .pagination .current { background: #2563EB; color: #fff; border-color: #2563EB; }

/* Modal */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 1000; align-items: center; justify-content: center; }
.modal-overlay.open { display: flex; }
.modal { background: #fff; border: 1px solid #E5E7EB; border-radius: 16px; padding: 28px; width: 100%; max-width: 440px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
.modal h3 { font-size: 17px; font-weight: 600; margin-bottom: 18px; }
.modal label { display: block; font-size: 13px; color: #6B7280; margin-bottom: 5px; }
.modal textarea { width: 100%; background: #F5F7FA; border: 1px solid #E5E7EB; border-radius: 8px; color: #1A1A1A; padding: 10px 12px; font-size: 14px; font-family: inherit; resize: vertical; outline: none; min-height: 80px; }
.modal textarea:focus { border-color: #2563EB; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }
.modal-btn { flex: 1; padding: 11px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.modal-btn.approve { background: #16a34a; color: #fff; }
.modal-btn.reject  { background: #dc2626; color: #fff; }
.modal-btn.cancel  { background: #F3F4F6; color: #6B7280; }

/* Bank Accounts Styles */
.bank-accounts-section { margin-bottom: 32px; }
.section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.section-header h2 { font-size: 18px; font-weight: 600; }
.add-account-btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 16px; background: #2563EB; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.add-account-btn:hover { background: #1D4ED8; }
.bank-account-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 20px; margin-bottom: 16px; position: relative; }
.bank-account-card.inactive { opacity: 0.6; }
.bank-account-card.default { border-color: #2563EB; }
.bank-account-card .default-badge { position: absolute; top: 12px; right: 12px; background: #2563EB; color: #fff; font-size: 10px; padding: 3px 8px; border-radius: 20px; font-weight: 600; }
.account-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.account-icon { width: 44px; height: 44px; background: #DBEAFE; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #2563EB; font-size: 20px; }
.account-title h3 { font-size: 16px; font-weight: 600; margin-bottom: 2px; }
.account-title span { font-size: 13px; color: #6B7280; }
.account-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 16px; }
.detail-item { background: #F9FAFB; padding: 12px 14px; border-radius: 8px; }
.detail-item label { display: block; font-size: 11px; color: #9CA3AF; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
.detail-item .value { font-size: 14px; color: #1A1A1A; font-family: monospace; }
.qr-preview { max-width: 150px; max-height: 150px; border-radius: 8px; border: 1px solid #E5E7EB; }
.account-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.account-actions button { padding: 7px 14px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; border: none; }
.btn-edit { background: #DBEAFE; color: #2563EB; }
.btn-toggle { background: #F3F4F6; color: #6B7280; }
.btn-delete { background: #FEE2E2; color: #EF4444; }
.btn-default { background: #D1FAE5; color: #059669; }
.empty-accounts { text-align: center; padding: 60px 20px; color: #9CA3AF; }
.empty-accounts i { font-size: 48px; margin-bottom: 16px; color: #E5E7EB; }
.empty-accounts p { font-size: 14px; }

/* Form Styles for Modal */
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; color: #6B7280; margin-bottom: 6px; }
.form-group input[type="text"], .form-group input[type="number"] { width: 100%; padding: 10px 12px; background: #F5F7FA; border: 1px solid #E5E7EB; border-radius: 8px; color: #1A1A1A; font-size: 14px; outline: none; }
.form-group input:focus { border-color: #2563EB; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.checkbox-group { display: flex; align-items: center; gap: 8px; }
.checkbox-group input[type="checkbox"] { width: 18px; height: 18px; accent-color: #2563EB; }
.checkbox-group label { margin: 0; color: #1A1A1A; }
.qr-upload { border: 2px dashed #E5E7EB; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s; }
.qr-upload:hover { border-color: #2563EB; background: rgba(37, 99, 235, 0.05); }
.qr-upload i { font-size: 32px; color: #9CA3AF; margin-bottom: 8px; }
.qr-upload p { font-size: 13px; color: #6B7280; }
.qr-preview-upload { max-width: 100%; max-height: 200px; margin-top: 12px; border-radius: 8px; }

/* ── Mobile Responsive ── */
@media (max-width: 768px) {
  body { overflow-x: hidden; }
  .main { padding: 24px 16px; width: 100%; }
  .page-header { flex-direction: column; align-items: flex-start; gap: 8px; }
  .page-header h1 { font-size: 20px; }
  .filters { flex-wrap: wrap; gap: 8px; }
  .filter-btn { padding: 8px 14px; font-size: 12px; }
  table { display: block; overflow-x: auto; white-space: nowrap; }
  th, td { padding: 10px 12px; font-size: 13px; }
  .modal { margin: 16px; padding: 20px; max-width: calc(100% - 32px); }
  .modal h3 { font-size: 17px; }
  .form-grid { grid-template-columns: 1fr; }
  .form-row { flex-direction: column; }
}
@media (max-width: 480px) {
  .main { padding: 20px 12px; }
  .page-header h1 { font-size: 18px; }
  .filters { gap: 6px; }
  .filter-btn { padding: 6px 12px; font-size: 11px; }
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin-header.php'; ?>

<div class="main">
  <div class="page-header">
    <h1><i class="fa fa-money-bill-wave" style="color:#6366f1;margin-right:10px"></i>Deposits</h1>
    <span style="font-size:13px;color:#94a3b8"><?= number_format($totalRows) ?> records</span>
  </div>

  <div class="filters">
    <a href="?tab=deposits&status=pending" class="filter-btn <?= $tab === 'deposits' ? 'active' : '' ?>"><i class="fa fa-money-bill-wave"></i> User Deposits</a>
    <a href="?tab=accounts" class="filter-btn <?= $tab === 'accounts' ? 'active' : '' ?>"><i class="fa fa-university"></i> Bank Accounts</a>
  </div>

  <?php if ($tab === 'deposits'): ?>
  <div class="filters">
    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $val => $label): ?>
      <a href="?tab=deposits&status=<?= $val ?>" class="filter-btn <?= $status === $val ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>User</th>
          <th>Amount</th>
          <th>Method</th>
          <th>TXN ID</th>
          <th>Proof</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td style="color:#4b5563"><?= $p['id'] ?></td>
          <td>
            <div><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></div>
            <div style="font-size:12px;color:#4b5563"><?= htmlspecialchars($p['email']) ?></div>
          </td>
          <td style="font-weight:600;color:#4ade80"><?= formatINR((float)$p['amount']) ?></td>
          <td style="font-size:12px;text-transform:uppercase;color:#94a3b8"><?= htmlspecialchars($p['payment_method']) ?></td>
          <td style="font-size:12px;color:#94a3b8;max-width:130px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($p['transaction_id'] ?? '—') ?></td>
          <td>
            <?php if ($p['proof_image']): ?>
              <a href="/<?= htmlspecialchars($p['proof_image']) ?>" target="_blank" class="proof-link"><i class="fa fa-image"></i> View</a>
            <?php else: ?>
              <span style="color:#4b5563;font-size:12px">None</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span>
            <?php if ($p['admin_remark']): ?>
              <div style="font-size:11px;color:#94a3b8;margin-top:3px"><?= htmlspecialchars($p['admin_remark']) ?></div>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:#4b5563"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
          <td>
            <?php if ($p['status'] === 'pending'): ?>
              <button class="action-btn btn-approve" onclick="openModal(<?= $p['id'] ?>, 'approve')"><i class="fa fa-check"></i> Approve</button>
              <button class="action-btn btn-reject"  onclick="openModal(<?= $p['id'] ?>, 'reject')"><i class="fa fa-times"></i> Reject</button>
            <?php else: ?>
              <span style="color:#4b5563;font-size:12px">Processed</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($payments)): ?>
          <tr><td colspan="9" class="empty">No <?= $status ?> deposits found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <?php if ($i === $page): ?>
        <span class="current"><?= $i ?></span>
      <?php else: ?>
        <a href="?tab=deposits&status=<?= $status ?>&page=<?= $i ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <?php elseif ($tab === 'accounts'): ?>
  <!-- Bank Accounts Management -->
  <div class="bank-accounts-section">
    <div class="section-header">
      <h2><i class="fa fa-university" style="color:#6366f1;margin-right:8px"></i>Bank Accounts for User Deposits</h2>
      <button class="add-account-btn" onclick="openAccountModal()">
        <i class="fa fa-plus"></i> Add Bank Account
      </button>
    </div>
    
    <div id="bankAccountsList">
      <!-- Accounts loaded via JavaScript -->
      <div class="empty-accounts">
        <i class="fa fa-spinner fa-spin"></i>
        <p>Loading bank accounts...</p>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Deposit Modal -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <h3 id="modalTitle">Process Deposit</h3>
    <input type="hidden" id="modalPaymentId">
    <input type="hidden" id="modalAction">
    <div style="margin-bottom:14px">
      <label>Remark (optional)</label>
      <textarea id="modalRemark" placeholder="Add a note for the user..."></textarea>
    </div>
    <div class="modal-actions">
      <button class="modal-btn cancel" onclick="closeModal()">Cancel</button>
      <button class="modal-btn" id="modalConfirmBtn" onclick="confirmAction()">Confirm</button>
    </div>
  </div>
</div>

<!-- Bank Account Modal -->
<div class="modal-overlay" id="accountModalOverlay">
  <div class="modal" style="max-width: 520px;">
    <h3 id="accountModalTitle">Add Bank Account</h3>
    <input type="hidden" id="accountId">
    
    <div class="form-row">
      <div class="form-group">
        <label>Account Holder Name *</label>
        <input type="text" id="accountName" placeholder="e.g., TradeZenfy Pvt Ltd">
      </div>
      <div class="form-group">
        <label>Bank Name *</label>
        <input type="text" id="bankName" placeholder="e.g., HDFC Bank">
      </div>
    </div>
    
    <div class="form-row">
      <div class="form-group">
        <label>Account Number *</label>
        <input type="text" id="accountNumber" placeholder="e.g., 1234567890">
      </div>
      <div class="form-group">
        <label>IFSC Code *</label>
        <input type="text" id="ifscCode" placeholder="e.g., HDFC0001234">
      </div>
    </div>
    
    <div class="form-row">
      <div class="form-group">
        <label>UPI ID (Optional)</label>
        <input type="text" id="upiId" placeholder="e.g., company@upi">
      </div>
      <div class="form-group">
        <label>Display Order</label>
        <input type="number" id="displayOrder" value="0" min="0">
      </div>
    </div>
    
    <div class="form-group">
      <label>QR Code Image</label>
      <div class="qr-upload" onclick="document.getElementById('qrFileInput').click()">
        <i class="fa fa-qrcode"></i>
        <p>Click to upload QR code image</p>
        <img id="qrPreview" class="qr-preview-upload" style="display:none;">
      </div>
      <input type="file" id="qrFileInput" accept="image/*" style="display:none" onchange="handleQRUpload(event)">
      <input type="hidden" id="qrCodePath">
    </div>
    
    <div class="form-row">
      <div class="checkbox-group">
        <input type="checkbox" id="isDefault">
        <label for="isDefault">Set as default account</label>
      </div>
      <div class="checkbox-group">
        <input type="checkbox" id="isActive" checked>
        <label for="isActive">Active</label>
      </div>
    </div>
    
    <div class="modal-actions">
      <button class="modal-btn cancel" onclick="closeAccountModal()">Cancel</button>
      <button class="modal-btn approve" id="accountSaveBtn" onclick="saveAccount()">Save Account</button>
    </div>
  </div>
</div>

<script>
function openModal(id, action) {
  document.getElementById('modalPaymentId').value = id;
  document.getElementById('modalAction').value    = action;
  document.getElementById('modalRemark').value    = '';
  document.getElementById('modalTitle').textContent = action === 'approve' ? 'Approve Deposit #' + id : 'Reject Deposit #' + id;
  const btn = document.getElementById('modalConfirmBtn');
  btn.textContent = action === 'approve' ? 'Approve & Credit' : 'Reject';
  btn.className = 'modal-btn ' + (action === 'approve' ? 'approve' : 'reject');
  document.getElementById('modalOverlay').classList.add('open');
}

function closeModal() { document.getElementById('modalOverlay').classList.remove('open'); }

async function confirmAction() {
  const payId  = document.getElementById('modalPaymentId').value;
  const action = document.getElementById('modalAction').value;
  const remark = document.getElementById('modalRemark').value.trim();

  const res  = await fetch('../api/admin-verify-payment.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ payment_id: payId, status: action === 'approve' ? 'approved' : 'rejected', remark })
  });
  const data = await res.json();
  if (data.success) { location.reload(); }
  else { alert(data.message); }
}

document.getElementById('modalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

document.getElementById('accountModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeAccountModal();
});

// Bank Account Management
let bankAccounts = [];

async function loadBankAccounts() {
  try {
    const res = await fetch('../api/admin-bank-account.php');
    const data = await res.json();
    if (data.success) {
      bankAccounts = data.accounts;
      renderBankAccounts();
    }
  } catch (err) {
    document.getElementById('bankAccountsList').innerHTML = `
      <div class="empty-accounts">
        <i class="fa fa-exclamation-circle"></i>
        <p>Failed to load bank accounts</p>
      </div>
    `;
  }
}

function renderBankAccounts() {
  const container = document.getElementById('bankAccountsList');
  if (bankAccounts.length === 0) {
    container.innerHTML = `
      <div class="empty-accounts">
        <i class="fa fa-university"></i>
        <p>No bank accounts added yet. Click "Add Bank Account" to create one.</p>
      </div>
    `;
    return;
  }
  
  container.innerHTML = bankAccounts.map(acc => `
    <div class="bank-account-card ${acc.is_active == 0 ? 'inactive' : ''} ${acc.is_default == 1 ? 'default' : ''}">
      ${acc.is_default == 1 ? '<span class="default-badge">DEFAULT</span>' : ''}
      <div class="account-header">
        <div class="account-icon"><i class="fa fa-university"></i></div>
        <div class="account-title">
          <h3>${escapeHtml(acc.account_name)}</h3>
          <span>${escapeHtml(acc.bank_name)}</span>
        </div>
      </div>
      <div class="account-details">
        <div class="detail-item">
          <label>Account Number</label>
          <div class="value">${escapeHtml(acc.account_number)}</div>
        </div>
        <div class="detail-item">
          <label>IFSC Code</label>
          <div class="value">${escapeHtml(acc.ifsc_code)}</div>
        </div>
        ${acc.upi_id ? `
        <div class="detail-item">
          <label>UPI ID</label>
          <div class="value">${escapeHtml(acc.upi_id)}</div>
        </div>
        ` : ''}
        ${acc.qr_code_image ? `
        <div class="detail-item">
          <label>QR Code</label>
          <img src="../${escapeHtml(acc.qr_code_image)}" class="qr-preview" alt="QR Code">
        </div>
        ` : ''}
      </div>
      <div class="account-actions">
        <button class="btn-edit" onclick="editAccount(${acc.id})"><i class="fa fa-edit"></i> Edit</button>
        <button class="btn-${acc.is_active == 1 ? 'toggle' : 'default'}" onclick="toggleAccount(${acc.id}, ${acc.is_active == 1 ? 0 : 1})">
          <i class="fa fa-${acc.is_active == 1 ? 'eye-slash' : 'eye'}"></i> ${acc.is_active == 1 ? 'Deactivate' : 'Activate'}
        </button>
        ${acc.is_default == 0 ? `<button class="btn-default" onclick="setDefaultAccount(${acc.id})"><i class="fa fa-check-circle"></i> Set Default</button>` : ''}
        <button class="btn-delete" onclick="deleteAccount(${acc.id})"><i class="fa fa-trash"></i> Delete</button>
      </div>
    </div>
  `).join('');
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function openAccountModal(accountId = null) {
  document.getElementById('accountId').value = accountId || '';
  document.getElementById('accountModalTitle').textContent = accountId ? 'Edit Bank Account' : 'Add Bank Account';
  
  if (accountId) {
    const acc = bankAccounts.find(a => a.id == accountId);
    if (acc) {
      document.getElementById('accountName').value = acc.account_name;
      document.getElementById('bankName').value = acc.bank_name;
      document.getElementById('accountNumber').value = acc.account_number;
      document.getElementById('ifscCode').value = acc.ifsc_code;
      document.getElementById('upiId').value = acc.upi_id || '';
      document.getElementById('displayOrder').value = acc.display_order;
      document.getElementById('isDefault').checked = acc.is_default == 1;
      document.getElementById('isActive').checked = acc.is_active == 1;
      document.getElementById('qrCodePath').value = acc.qr_code_image || '';
      
      const qrPreview = document.getElementById('qrPreview');
      if (acc.qr_code_image) {
        qrPreview.src = '../' + acc.qr_code_image;
        qrPreview.style.display = 'block';
      } else {
        qrPreview.style.display = 'none';
      }
    }
  } else {
    // Clear form
    document.getElementById('accountName').value = '';
    document.getElementById('bankName').value = '';
    document.getElementById('accountNumber').value = '';
    document.getElementById('ifscCode').value = '';
    document.getElementById('upiId').value = '';
    document.getElementById('displayOrder').value = '0';
    document.getElementById('isDefault').checked = false;
    document.getElementById('isActive').checked = true;
    document.getElementById('qrCodePath').value = '';
    document.getElementById('qrPreview').style.display = 'none';
  }
  
  document.getElementById('accountModalOverlay').classList.add('open');
}

function closeAccountModal() {
  document.getElementById('accountModalOverlay').classList.remove('open');
}

async function handleQRUpload(event) {
  const file = event.target.files[0];
  if (!file) return;
  
  const formData = new FormData();
  formData.append('qr_image', file);
  
  try {
    const res = await fetch('../api/admin-upload-qr.php', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    
    if (data.success) {
      document.getElementById('qrCodePath').value = data.path;
      const preview = document.getElementById('qrPreview');
      preview.src = data.url;
      preview.style.display = 'block';
    } else {
      alert(data.message);
    }
  } catch (err) {
    alert('Failed to upload QR code');
  }
}

async function saveAccount() {
  const accountId = document.getElementById('accountId').value;
  const data = {
    account_name: document.getElementById('accountName').value.trim(),
    bank_name: document.getElementById('bankName').value.trim(),
    account_number: document.getElementById('accountNumber').value.trim(),
    ifsc_code: document.getElementById('ifscCode').value.trim(),
    upi_id: document.getElementById('upiId').value.trim(),
    qr_code_image: document.getElementById('qrCodePath').value,
    display_order: parseInt(document.getElementById('displayOrder').value) || 0,
    is_default: document.getElementById('isDefault').checked ? 1 : 0,
    is_active: document.getElementById('isActive').checked ? 1 : 0
  };
  
  if (!data.account_name || !data.bank_name || !data.account_number || !data.ifsc_code) {
    alert('Please fill in all required fields');
    return;
  }
  
  if (accountId) data.id = parseInt(accountId);
  
  try {
    const res = await fetch('../api/admin-bank-account.php', {
      method: accountId ? 'PUT' : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const result = await res.json();
    
    if (result.success) {
      closeAccountModal();
      loadBankAccounts();
    } else {
      alert(result.message);
    }
  } catch (err) {
    alert('Failed to save account');
  }
}

function editAccount(id) {
  openAccountModal(id);
}

async function toggleAccount(id, isActive) {
  const acc = bankAccounts.find(a => a.id == id);
  if (!acc) return;
  
  try {
    const res = await fetch('../api/admin-bank-account.php', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...acc, is_active: isActive })
    });
    const result = await res.json();
    if (result.success) loadBankAccounts();
  } catch (err) {
    alert('Failed to update account');
  }
}

async function setDefaultAccount(id) {
  const acc = bankAccounts.find(a => a.id == id);
  if (!acc) return;
  
  try {
    const res = await fetch('../api/admin-bank-account.php', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...acc, is_default: 1 })
    });
    const result = await res.json();
    if (result.success) loadBankAccounts();
  } catch (err) {
    alert('Failed to set default account');
  }
}

async function deleteAccount(id) {
  if (!confirm('Are you sure you want to delete this bank account?')) return;
  
  try {
    const res = await fetch('../api/admin-bank-account.php', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    const result = await res.json();
    if (result.success) loadBankAccounts();
  } catch (err) {
    alert('Failed to delete account');
  }
}

// Load bank accounts on page load
if (document.getElementById('bankAccountsList')) {
  loadBankAccounts();
}


</script>
</body>
</html>
