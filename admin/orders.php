<?php
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
$db    = getDB();

$status  = $_GET['status'] ?? 'PENDING';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$where  = $status !== 'ALL' ? "WHERE o.status = ?" : "";
$params = $status !== 'ALL' ? [$status] : [];

$total     = $db->prepare("SELECT COUNT(*) FROM user_orders o $where");
$total->execute($params);
$totalRows = $total->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $db->prepare("
    SELECT o.*, a.first_name, a.last_name, a.email, a.current_balance,
           s.name AS stock_name, s.sector
    FROM user_orders o
    JOIN account_registrations a ON a.id = o.user_id
    JOIN stocks s ON s.id = o.stock_id
    $where
    ORDER BY o.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orders — TradeZenfy Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F5F7FA; color: #1A1A1A; min-height: 100vh; overflow-x: hidden; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
.page-header h1 { font-size: 22px; font-weight: 600; }
.filters { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
.filter-btn { padding: 7px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; border: 1px solid #E5E7EB; background: #fff; color: #6B7280; text-decoration: none; }
.filter-btn.active, .filter-btn:hover { background: #2563EB; color: #fff; border-color: #2563EB; }
.card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
table { width: 100%; border-collapse: collapse; }
th { font-size: 11px; color: #6B7280; text-transform: uppercase; letter-spacing: .05em; padding: 12px 16px; text-align: left; background: #F9FAFB; white-space: nowrap; font-weight: 600; }
td { padding: 12px 16px; font-size: 13px; border-top: 1px solid #F3F4F6; vertical-align: middle; color: #1A1A1A; }
tr:hover td { background: #F9FAFB; }
.badge { display: inline-flex; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-PENDING   { background: #FEF3C7; color: #D97706; }
.badge-EXECUTED  { background: #D1FAE5; color: #059669; }
.badge-REJECTED  { background: #FEE2E2; color: #DC2626; }
.badge-CANCELLED { background: #F3F4F6; color: #6B7280; }
.type-BUY  { color: #10B981; font-weight: 700; font-size: 13px; }
.type-SELL { color: #EF4444; font-weight: 700; font-size: 13px; }
.action-btn { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; border: none; margin-right: 4px; }
.btn-execute { background: #D1FAE5; color: #059669; }
.btn-reject  { background: #FEE2E2; color: #DC2626; }
.action-btn:hover { opacity: .8; }
.empty { padding: 40px; text-align: center; color: #9CA3AF; }
.pagination { display: flex; gap: 8px; margin-top: 20px; justify-content: center; }
.pagination a, .pagination span { padding: 7px 13px; border-radius: 7px; font-size: 13px; background: #fff; border: 1px solid #E5E7EB; color: #6B7280; text-decoration: none; }
.pagination a:hover, .pagination .current { background: #2563EB; color: #fff; border-color: #2563EB; }
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 1000; align-items: center; justify-content: center; }
.modal-overlay.open { display: flex; }
.modal { background: #fff; border: 1px solid #E5E7EB; border-radius: 16px; padding: 28px; width: 100%; max-width: 460px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
.modal h3 { font-size: 17px; font-weight: 600; margin-bottom: 16px; }
.order-summary { background: #F9FAFB; border-radius: 8px; padding: 14px; margin-bottom: 16px; }
.order-summary .row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px; }
.order-summary .row:last-child { margin-bottom: 0; }
.order-summary .row .k { color: #6B7280; }
.order-summary .row .v { font-weight: 600; }
.modal label { display: block; font-size: 13px; color: #6B7280; margin-bottom: 5px; }
.modal textarea { width: 100%; background: #F5F7FA; border: 1px solid #E5E7EB; border-radius: 8px; color: #1A1A1A; padding: 10px 12px; font-size: 14px; font-family: inherit; resize: vertical; outline: none; min-height: 70px; }
.modal textarea:focus { border-color: #2563EB; }
.modal-actions { display: flex; gap: 10px; margin-top: 16px; }
.modal-btn { flex: 1; padding: 11px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.modal-btn.execute { background: #16a34a; color: #fff; }
.modal-btn.reject  { background: #dc2626; color: #fff; }
.modal-btn.cancel  { background: #F3F4F6; color: #6B7280; }

/* ── Mobile Responsive ── */
@media (max-width: 768px) {
  body { overflow-x: hidden; }
  .main { padding: 24px 16px; width: 100%; }
  .page-header { flex-direction: column; align-items: flex-start; gap: 8px; }
  .page-header h1 { font-size: 20px; }
  .filters { justify-content: flex-start; flex-wrap: wrap; gap: 8px; }
  .filter-btn { padding: 8px 14px; font-size: 12px; }
  table { display: block; overflow-x: auto; white-space: nowrap; }
  th, td { padding: 10px 12px; font-size: 12px; }
  .modal { margin: 16px; padding: 20px; max-width: calc(100% - 32px); }
  .modal h3 { font-size: 17px; }
  .modal-actions { flex-direction: column; }
  .modal-btn { width: 100%; }
  .order-summary .row { flex-direction: column; gap: 4px; }
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
    <h1><i class="fa fa-list-alt" style="color:#6366f1;margin-right:10px"></i>Orders</h1>
    <span style="font-size:13px;color:#94a3b8"><?= number_format($totalRows) ?> records</span>
  </div>

  <div class="filters">
    <?php foreach (['PENDING' => 'Pending', 'EXECUTED' => 'Executed', 'REJECTED' => 'Rejected', 'CANCELLED' => 'Cancelled', 'ALL' => 'All'] as $val => $label): ?>
      <a href="?status=<?= $val ?>" class="filter-btn <?= $status === $val ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th>#</th><th>User</th><th>Stock</th><th>Type</th><th>Mode</th>
          <th>Qty</th><th>Price</th><th>Total</th><th>Status</th><th>Date</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
        <tr id="order-<?= $o['id'] ?>">
          <td style="color:#4b5563"><?= $o['id'] ?></td>
          <td>
            <div style="font-weight:500"><?= htmlspecialchars($o['first_name'] . ' ' . $o['last_name']) ?></div>
            <div style="font-size:11px;color:#4b5563"><?= htmlspecialchars($o['email']) ?></div>
          </td>
          <td>
            <div style="font-weight:600"><?= htmlspecialchars($o['symbol']) ?></div>
            <div style="font-size:11px;color:#4b5563"><?= htmlspecialchars($o['stock_name']) ?></div>
          </td>
          <td class="type-<?= $o['order_type'] ?>"><?= $o['order_type'] ?></td>
          <td style="font-size:12px;color:#94a3b8"><?= $o['order_mode'] ?></td>
          <td><?= $o['quantity'] ?></td>
          <td>₹<?= number_format((float)$o['price'], 2) ?></td>
          <td style="font-weight:600">₹<?= number_format((float)$o['total_amount'], 2) ?></td>
          <td><span class="badge badge-<?= $o['status'] ?>"><?= ucfirst(strtolower($o['status'])) ?></span></td>
          <td style="font-size:12px;color:#4b5563;white-space:nowrap"><?= date('d M Y, H:i', strtotime($o['created_at'])) ?></td>
          <td>
            <?php if ($o['status'] === 'PENDING'): ?>
              <button class="action-btn btn-execute" onclick="openModal(<?= $o['id'] ?>, '<?= $o['order_type'] ?>', '<?= $o['symbol'] ?>', <?= $o['quantity'] ?>, <?= $o['price'] ?>, <?= $o['total_amount'] ?>, '<?= htmlspecialchars($o['first_name'] . ' ' . $o['last_name']) ?>', 'execute')">
                <i class="fa fa-check"></i> Execute
              </button>
              <button class="action-btn btn-reject" onclick="openModal(<?= $o['id'] ?>, '<?= $o['order_type'] ?>', '<?= $o['symbol'] ?>', <?= $o['quantity'] ?>, <?= $o['price'] ?>, <?= $o['total_amount'] ?>, '<?= htmlspecialchars($o['first_name'] . ' ' . $o['last_name']) ?>', 'reject')">
                <i class="fa fa-times"></i> Reject
              </button>
            <?php else: ?>
              <span style="font-size:12px;color:#4b5563">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($orders)): ?>
          <tr><td colspan="11" class="empty">No <?= strtolower($status) ?> orders found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <?php if ($i === $page): ?><span class="current"><?= $i ?></span>
      <?php else: ?><a href="?status=<?= $status ?>&page=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Execute/Reject Modal -->
<div class="modal-overlay" id="orderModal">
  <div class="modal">
    <h3 id="modalTitle">Process Order</h3>
    <div class="order-summary" id="orderSummary"></div>
    <input type="hidden" id="mOrderId">
    <input type="hidden" id="mAction">
    <label>Remark (optional)</label>
    <textarea id="mRemark" placeholder="Add a note for the user..."></textarea>
    <div class="modal-actions">
      <button class="modal-btn cancel" onclick="closeModal()">Cancel</button>
      <button class="modal-btn" id="mConfirmBtn" onclick="confirmAction()">Confirm</button>
    </div>
  </div>
</div>

<script>
function openModal(id, type, sym, qty, price, total, user, action) {
  document.getElementById('mOrderId').value = id;
  document.getElementById('mAction').value  = action;
  document.getElementById('mRemark').value  = '';
  document.getElementById('modalTitle').textContent = (action === 'execute' ? 'Execute' : 'Reject') + ' Order #' + id;
  document.getElementById('orderSummary').innerHTML = `
    <div class="row"><span class="k">User</span><span class="v">${user}</span></div>
    <div class="row"><span class="k">Order</span><span class="v" style="color:${type==='BUY'?'#4ade80':'#f87171'}">${type} ${qty} × ${sym} @ ₹${parseFloat(price).toLocaleString('en-IN',{minimumFractionDigits:2})}</span></div>
    <div class="row"><span class="k">Total</span><span class="v">₹${parseFloat(total).toLocaleString('en-IN',{minimumFractionDigits:2})}</span></div>
  `;
  const btn = document.getElementById('mConfirmBtn');
  btn.textContent = action === 'execute' ? 'Execute Order' : 'Reject Order';
  btn.className   = 'modal-btn ' + (action === 'execute' ? 'execute' : 'reject');
  document.getElementById('orderModal').classList.add('open');
}
function closeModal() { document.getElementById('orderModal').classList.remove('open'); }

async function confirmAction() {
  const orderId = document.getElementById('mOrderId').value;
  const action  = document.getElementById('mAction').value;
  const remark  = document.getElementById('mRemark').value.trim();

  const res  = await fetch('../api/admin-execute-order.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id: orderId, action, remark })
  });
  const data = await res.json();
  if (data.success) { location.reload(); }
  else { alert(data.message); }
}

document.getElementById('orderModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

</script>
</body>
</html>
