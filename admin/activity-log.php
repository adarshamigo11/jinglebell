<?php
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
$db    = getDB();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;

$totalRows  = $db->query("SELECT COUNT(*) FROM admin_activity_log")->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$logs = $db->prepare("
    SELECT l.*, a.name, a.username
    FROM admin_activity_log l
    JOIN admins a ON a.id = l.admin_id
    ORDER BY l.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$logs->execute();
$logs = $logs->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Log — Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', sans-serif; background: #F5F7FA; color: #1A1A1A; min-height: 100vh; overflow-x: hidden; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.page-header h1 { font-size: 22px; font-weight: 600; }
.card { background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 12px; overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
th { font-size: 11px; color: #6B7280; text-transform: uppercase; letter-spacing: .05em; padding: 12px 16px; text-align: left; background: #F9FAFB; }
td { padding: 12px 16px; font-size: 13px; border-top: 1px solid #E5E7EB; vertical-align: middle; }
tr:hover td { background: #F3F4F6; }
.action-chip { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: #F3F4F6; color: #6B7280; }
.action-chip.login    { background: #DBEAFE; color: #2563EB; }
.action-chip.approve  { background: #D1FAE5; color: #059669; }
.action-chip.rejected { background: #FEE2E2; color: #EF4444; }
.action-chip.credit   { background: #CCFBF1; color: #0D9488; }
.action-chip.debit    { background: #FEF3C7; color: #D97706; }
.empty { padding: 40px; text-align: center; color: #9CA3AF; }
.pagination { display: flex; gap: 8px; margin-top: 20px; justify-content: center; }
.pagination a, .pagination span { padding: 7px 13px; border-radius: 7px; font-size: 13px; background: #FFFFFF; border: 1px solid #E5E7EB; color: #6B7280; text-decoration: none; }
.pagination a:hover, .pagination .current { background: #2563EB; color: #fff; border-color: #2563EB; }

/* ── Mobile Responsive ── */
@media (max-width: 768px) {
  .main { padding: 20px 16px; }
  .page-header { flex-direction: column; align-items: flex-start; gap: 8px; }
  .page-header h1 { font-size: 20px; }
  table { display: block; overflow-x: auto; white-space: nowrap; }
  th, td { padding: 10px 12px; font-size: 13px; }
  .pagination { flex-wrap: wrap; justify-content: center; gap: 6px; }
  .pagination a, .pagination span { padding: 6px 12px; font-size: 12px; }
}
@media (max-width: 480px) {
  .main { padding: 16px 12px; }
  .page-header h1 { font-size: 18px; }
  th, td { padding: 8px 10px; font-size: 12px; }
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin-header.php'; ?>

<div class="main" style="padding:32px">
  <div class="page-header">
    <h1><i class="fa fa-history" style="color:#2563EB;margin-right:10px"></i>Activity Log</h1>
    <span style="font-size:13px;color:#6B7280"><?= number_format($totalRows) ?> entries</span>
  </div>
  <div class="card">
    <table>
      <thead><tr><th>Admin</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th><th>Time</th></tr></thead>
      <tbody>
        <?php foreach ($logs as $log):
          $actionLower = strtolower($log['action']);
          $chipClass   = str_contains($actionLower, 'login') ? 'login' : (str_contains($actionLower, 'approve') || str_contains($actionLower, 'approved') ? 'approve' : (str_contains($actionLower, 'reject') ? 'rejected' : (str_contains($actionLower, 'credit') ? 'credit' : (str_contains($actionLower, 'debit') ? 'debit' : ''))));
        ?>
        <tr>
          <td>
            <div style="font-weight:500"><?= htmlspecialchars($log['name']) ?></div>
            <div style="font-size:11px;color:#9CA3AF">@<?= htmlspecialchars($log['username']) ?></div>
          </td>
          <td><span class="action-chip <?= $chipClass ?>"><?= htmlspecialchars($log['action']) ?></span></td>
          <td style="color:#6B7280"><?= htmlspecialchars($log['entity_type'] ?? '—') ?><?= $log['entity_id'] ? ' #' . $log['entity_id'] : '' ?></td>
          <td style="color:#6B7280;max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($log['details'] ?? '—') ?></td>
          <td style="color:#9CA3AF;font-size:12px"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
          <td style="color:#9CA3AF;font-size:12px;white-space:nowrap"><?= date('d M Y, H:i', strtotime($log['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
          <tr><td colspan="6" class="empty">No activity yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <?php if ($i === $page): ?><span class="current"><?= $i ?></span>
      <?php else: ?><a href="?page=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
