<?php
require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
$db   = getDB();

$status  = $_GET['status'] ?? 'all';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = $status !== 'all' ? "AND o.status = ?" : "";
$params = [$user['id']];
if ($status !== 'all') $params[] = strtoupper($status);

$total     = $db->prepare("SELECT COUNT(*) FROM user_orders o WHERE o.user_id = ? $where");
$total->execute($params);
$totalRows = $total->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $db->prepare("
    SELECT o.*, s.name AS stock_name, s.symbol
    FROM user_orders o
    JOIN stocks s ON s.id = o.stock_id
    WHERE o.user_id = ? $where
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
<title>Orders — TradeZenfy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../public/assets/css/groww-ui.css">
<link rel="stylesheet" href="../public/assets/css/layout-new.css">
<style>
.filters{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap}
.filter-btn{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;border:1px solid var(--groww-border);background:var(--groww-card);color:var(--groww-text-secondary);text-decoration:none;transition:all 0.2s}
.filter-btn.active,.filter-btn:hover{background:var(--groww-green);color:#fff;border-color:var(--groww-green)}
.type-buy{color:var(--groww-green);font-weight:700}
.type-sell{color:var(--groww-red);font-weight:700}
.cancel-btn{background:none;border:1px solid var(--groww-border);color:var(--groww-red);font-size:11px;padding:5px 12px;border-radius:5px;cursor:pointer;transition:all 0.2s}
.cancel-btn:hover{background:#FEE2E2;border-color:var(--groww-red)}
.pagination{display:flex;gap:8px;margin-top:20px;justify-content:center}
.pagination a,.pagination span{padding:8px 14px;border-radius:7px;font-size:13px;background:var(--groww-card);border:1px solid var(--groww-border);color:var(--groww-text-secondary);text-decoration:none;transition:all 0.2s}
.pagination a:hover,.pagination .current{background:var(--groww-green);color:#fff;border-color:var(--groww-green)}
@media (max-width: 768px) {
  .filters{gap:8px}
  .filter-btn{padding:6px 14px;font-size:12px}
  .main{overflow-x:hidden}
  .top-header{padding:12px 16px;position:static}
  .content{padding:0}
  /* Tables: stack on mobile */
  table{display:block;width:100%;overflow-x:hidden}
  table thead{display:none}
  table tbody{display:block;width:100%}
  table tr{display:block;padding:12px;border-bottom:1px solid var(--border)}
  table td{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border:none;font-size:14px}
  table td::before{content:attr(data-label);font-weight:600;color:var(--text-secondary);font-size:12px;margin-right:12px}
  .pagination{flex-wrap:wrap}
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/user-top-nav.php'; ?>

<div class="main">
  <div class="top-header">
    <div class="header-left">
      <h1>Orders</h1>
      <p><?= number_format($totalRows) ?> total orders</p>
    </div>
  </div>

  <div class="content">

  <div class="filters">
    <?php foreach (['all' => 'All', 'pending' => 'Pending', 'executed' => 'Executed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $val => $label): ?>
      <a href="?status=<?= $val ?>" class="filter-btn <?= $status === $val ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th>Stock</th>
          <th>Type</th>
          <th>Mode</th>
          <th>Qty</th>
          <th>Price</th>
          <th>Total</th>
          <th>Status</th>
          <th>Date</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
        <tr id="order-row-<?= $o['id'] ?>">
          <td>
            <a href="../stock-detail.php?id=<?= $o['stock_id'] ?>" style="color:var(--groww-text);text-decoration:none;font-weight:600"><?= htmlspecialchars($o['symbol']) ?></a>
            <div style="font-size:12px;color:var(--groww-text-secondary)"><?= htmlspecialchars($o['stock_name']) ?></div>
          </td>
          <td class="type-<?= strtolower($o['order_type']) ?>"><?= $o['order_type'] ?></td>
          <td style="font-size:12px;color:var(--groww-text-secondary)"><?= $o['order_mode'] ?></td>
          <td><?= $o['quantity'] ?></td>
          <td>₹<?= number_format((float)$o['price'], 2) ?></td>
          <td>₹<?= number_format((float)$o['total_amount'], 2) ?></td>
          <td>
            <span class="badge badge-<?= strtolower($o['status']) ?>"><?= ucfirst(strtolower($o['status'])) ?></span>
            <?php if ($o['admin_remark']): ?>
              <div style="font-size:11px;color:var(--groww-text-secondary);margin-top:3px"><?= htmlspecialchars($o['admin_remark']) ?></div>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--groww-text-secondary)"><?= date('d M Y, H:i', strtotime($o['created_at'])) ?></td>
          <td>
            <?php if ($o['status'] === 'PENDING'): ?>
              <button class="cancel-btn" onclick="cancelOrder(<?= $o['id'] ?>)">Cancel</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($orders)): ?>
          <tr><td colspan="9" class="empty">No orders found.</td></tr>
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
</div>

<script>
async function cancelOrder(orderId) {
    if (!confirm('Cancel this order? Blocked funds will be returned.')) return;
    const res  = await fetch('../api/cancel-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId })
    });
    const data = await res.json();
    if (data.success) { location.reload(); }
    else { alert(data.message); }
}

</script>
</body>
</html>
