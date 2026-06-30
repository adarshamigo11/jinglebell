<?php
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
$db    = getDB();

$status  = $_GET['status'] ?? 'pending';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = $status !== 'all' ? "WHERE w.status = ?" : "";
$params = $status !== 'all' ? [$status] : [];

$total     = $db->prepare("SELECT COUNT(*) FROM withdrawals w $where");
$total->execute($params);
$totalRows = $total->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $db->prepare("
    SELECT w.*, a.first_name, a.last_name, a.email
    FROM withdrawals w
    JOIN account_registrations a ON a.id = w.user_id
    $where
    ORDER BY w.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$withdrawals = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Withdrawals — TradeZenfy Admin</title>
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
.action-btn { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; border: none; margin-right: 4px; }
.btn-approve { background: #D1FAE5; color: #059669; }
.btn-reject  { background: #FEE2E2; color: #DC2626; }
.action-btn:hover { opacity: .75; }
.empty { padding: 40px; text-align: center; color: #9CA3AF; }
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 1000; align-items: center; justify-content: center; }
.modal-overlay.open { display: flex; }
.modal { background: #fff; border: 1px solid #E5E7EB; border-radius: 16px; padding: 28px; width: 100%; max-width: 460px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
.modal h3 { font-size: 17px; font-weight: 600; margin-bottom: 6px; }
.modal-meta { font-size: 13px; color: #6B7280; margin-bottom: 18px; }
.modal label { display: block; font-size: 13px; color: #6B7280; margin-bottom: 5px; }
.modal textarea { width: 100%; background: #F5F7FA; border: 1px solid #E5E7EB; border-radius: 8px; color: #1A1A1A; padding: 10px 12px; font-size: 14px; font-family: inherit; resize: vertical; outline: none; min-height: 80px; }
.modal textarea:focus { border-color: #2563EB; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }
.modal-btn { flex: 1; padding: 11px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }

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
  .modal-actions { flex-direction: column; }
  .modal-btn { width: 100%; }
  .info-row { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
  .main { padding: 20px 12px; }
  .page-header h1 { font-size: 18px; }
  .filters { gap: 6px; }
  .filter-btn { padding: 6px 12px; font-size: 11px; }
}
.modal-btn.approve { background: #16a34a; color: #fff; }
.modal-btn.reject  { background: #dc2626; color: #fff; }
.modal-btn.cancel  { background: #F3F4F6; color: #6B7280; }
.info-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; }
.info-item .i-label { font-size: 11px; color: #6B7280; text-transform: uppercase; margin-bottom: 3px; }
.info-item .i-val   { font-size: 14px; color: #1A1A1A; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin-header.php'; ?>

<div class="main">
  <div class="page-header">
    <h1><i class="fa fa-wallet" style="color:#6366f1;margin-right:10px"></i>Withdrawals</h1>
    <span style="font-size:13px;color:#94a3b8"><?= number_format($totalRows) ?> records</span>
  </div>

  <div class="filters">
    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $val => $label): ?>
      <a href="?status=<?= $val ?>" class="filter-btn <?= $status === $val ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>User</th>
          <th>Amount</th>
          <th>Withdraw To</th>
          <th>Status</th>
          <th>Requested</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($withdrawals as $w): ?>
        <tr>
          <td style="color:#4b5563"><?= $w['id'] ?></td>
          <td>
            <div><?= htmlspecialchars($w['first_name'] . ' ' . $w['last_name']) ?></div>
            <div style="font-size:12px;color:#4b5563"><?= htmlspecialchars($w['email']) ?></div>
          </td>
          <td style="font-weight:600;color:#f87171"><?= formatINR((float)$w['amount']) ?></td>
          <td style="font-size:13px">
            <?php if ($w['upi_id']): ?>
              <i class="fa fa-mobile-alt" style="color:#94a3b8"></i> <?= htmlspecialchars($w['upi_id']) ?>
            <?php elseif ($w['bank_name']): ?>
              <div><i class="fa fa-university" style="color:#94a3b8"></i> <?= htmlspecialchars($w['bank_name']) ?></div>
              <div style="font-size:12px;color:#4b5563">A/C: <?= htmlspecialchars($w['account_number']) ?> | <?= htmlspecialchars($w['ifsc_code']) ?></div>
            <?php else: ?>
              <span style="color:#4b5563">—</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge badge-<?= $w['status'] ?>"><?= ucfirst($w['status']) ?></span>
            <?php if ($w['admin_remark']): ?>
              <div style="font-size:11px;color:#94a3b8;margin-top:3px"><?= htmlspecialchars($w['admin_remark']) ?></div>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:#4b5563"><?= date('d M Y, H:i', strtotime($w['created_at'])) ?></td>
          <td>
            <?php if ($w['status'] === 'pending'): ?>
              <button class="action-btn btn-approve" onclick="openModal(<?= $w['id'] ?>, '<?= htmlspecialchars($w['first_name'] . ' ' . $w['last_name']) ?>', <?= $w['amount'] ?>, '<?= addslashes($w['upi_id'] ?? ($w['bank_name'] . ' / ' . $w['account_number'])) ?>', 'approve')"><i class="fa fa-check"></i> Approve</button>
              <button class="action-btn btn-reject"  onclick="openModal(<?= $w['id'] ?>, '<?= htmlspecialchars($w['first_name'] . ' ' . $w['last_name']) ?>', <?= $w['amount'] ?>, '<?= addslashes($w['upi_id'] ?? ($w['bank_name'] . ' / ' . $w['account_number'])) ?>', 'reject')"><i class="fa fa-times"></i> Reject</button>
            <?php else: ?>
              <span style="color:#4b5563;font-size:12px">Processed</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($withdrawals)): ?>
          <tr><td colspan="7" class="empty">No <?= $status ?> withdrawals found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <h3 id="modalTitle">Process Withdrawal</h3>
    <div class="modal-meta" id="modalMeta"></div>
    <div class="info-row">
      <div class="info-item"><div class="i-label">User</div><div class="i-val" id="mUser"></div></div>
      <div class="info-item"><div class="i-label">Amount</div><div class="i-val" id="mAmount"></div></div>
      <div class="info-item" style="grid-column:1/-1"><div class="i-label">Withdraw To</div><div class="i-val" id="mTo"></div></div>
    </div>
    <input type="hidden" id="modalWdId">
    <input type="hidden" id="modalAction">
    <label>Remark (optional)</label>
    <textarea id="modalRemark" placeholder="Add a note..."></textarea>
    <div class="modal-actions">
      <button class="modal-btn cancel" onclick="closeModal()">Cancel</button>
      <button class="modal-btn" id="modalConfirmBtn" onclick="confirmAction()">Confirm</button>
    </div>
  </div>
</div>

<script>
function openModal(id, user, amount, to, action) {
  document.getElementById('modalWdId').value  = id;
  document.getElementById('modalAction').value = action;
  document.getElementById('modalRemark').value = '';
  document.getElementById('modalTitle').textContent = (action === 'approve' ? 'Approve' : 'Reject') + ' Withdrawal #' + id;
  document.getElementById('mUser').textContent   = user;
  document.getElementById('mAmount').textContent = '₹' + parseFloat(amount).toLocaleString('en-IN', {minimumFractionDigits: 2});
  document.getElementById('mTo').textContent     = to;
  const btn = document.getElementById('modalConfirmBtn');
  btn.textContent = action === 'approve' ? 'Approve' : 'Reject & Refund';
  btn.className = 'modal-btn ' + (action === 'approve' ? 'approve' : 'reject');
  document.getElementById('modalOverlay').classList.add('open');
}
function closeModal() { document.getElementById('modalOverlay').classList.remove('open'); }

async function confirmAction() {
  const wdId   = document.getElementById('modalWdId').value;
  const action = document.getElementById('modalAction').value;
  const remark = document.getElementById('modalRemark').value.trim();

  const res  = await fetch('../api/admin-verify-withdrawal.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ withdrawal_id: wdId, status: action === 'approve' ? 'approved' : 'rejected', remark })
  });
  const data = await res.json();
  if (data.success) { location.reload(); }
  else { alert(data.message); }
}

document.getElementById('modalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});


</script>
</body>
</html>
