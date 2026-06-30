<?php
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
$db    = getDB();

$status  = $_GET['status'] ?? 'all';
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = [];
$params = [];

if ($status !== 'all') {
    $where[]  = "status = ?";
    $params[] = $status;
}
if ($search) {
    $where[]  = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $like      = "%$search%";
    $params   = array_merge($params, [$like, $like, $like, $like]);
}

$whereSQL  = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$total     = $db->prepare("SELECT COUNT(*) FROM account_registrations $whereSQL");
$total->execute($params);
$totalRows = $total->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $db->prepare("SELECT id, first_name, last_name, email, username, phone, status, current_balance, is_blocked, created_at FROM account_registrations $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users — TradeZenfy Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F5F7FA; color: #1A1A1A; min-height: 100vh; overflow-x: hidden; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.page-header h1 { font-size: 22px; font-weight: 600; }
.filters { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
.filter-btn { padding: 7px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; border: 1px solid #E5E7EB; background: #fff; color: #6B7280; text-decoration: none; transition: all .15s; }
.filter-btn.active, .filter-btn:hover { background: #2563EB; color: #fff; border-color: #2563EB; }
.search-bar { display: flex; gap: 10px; margin-bottom: 20px; }
.search-bar input { flex: 1; padding: 10px 14px; background: #fff; border: 1px solid #E5E7EB; border-radius: 8px; color: #1A1A1A; font-size: 14px; outline: none; }
.search-bar input:focus { border-color: #2563EB; }
.search-bar button { padding: 10px 18px; background: #2563EB; color: #fff; border: none; border-radius: 8px; font-size: 14px; cursor: pointer; }
.card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
table { width: 100%; border-collapse: collapse; }
th { font-size: 11px; color: #6B7280; text-transform: uppercase; letter-spacing: .05em; padding: 12px 16px; text-align: left; background: #F9FAFB; font-weight: 600; }
td { padding: 13px 16px; font-size: 14px; border-top: 1px solid #F3F4F6; color: #1A1A1A; }
tr:hover td { background: #F9FAFB; }
.badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-pending  { background: #FEF3C7; color: #D97706; }
.badge-approved { background: #D1FAE5; color: #059669; }
.badge-rejected { background: #FEE2E2; color: #DC2626; }
.badge-blocked  { background: #FEE2E2; color: #DC2626; }
.action-btn { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; border: none; text-decoration: none; transition: opacity .15s; }
.btn-view    { background: #DBEAFE; color: #2563EB; }
.btn-approve { background: #D1FAE5; color: #059669; }
.btn-reject  { background: #FEE2E2; color: #DC2626; }
.action-btn:hover { opacity: .8; }
.pagination { display: flex; gap: 8px; margin-top: 20px; justify-content: center; }
.pagination a, .pagination span { padding: 7px 13px; border-radius: 7px; font-size: 13px; background: #fff; border: 1px solid #E5E7EB; color: #6B7280; text-decoration: none; }
.pagination a:hover, .pagination .current { background: #2563EB; color: #fff; border-color: #2563EB; }
.empty { padding: 40px; text-align: center; color: #9CA3AF; }

/* ── Mobile Responsive ── */
@media (max-width: 768px) {
  body { overflow-x: hidden; }
  .main { padding: 24px 16px; width: 100%; }
  .page-header { flex-direction: column; align-items: flex-start; gap: 8px; }
  .page-header h1 { font-size: 20px; }
  .search-bar { flex-direction: column; gap: 8px; }
  .search-bar input { width: 100%; }
  .search-bar button { width: 100%; }
  .filters { justify-content: flex-start; flex-wrap: wrap; gap: 8px; }
  .filter-btn { padding: 8px 14px; font-size: 12px; }
  table { display: block; overflow-x: auto; white-space: nowrap; }
  th, td { padding: 10px 12px; font-size: 13px; }
  .action-btn { padding: 6px 10px; font-size: 11px; }
  .pagination { flex-wrap: wrap; justify-content: center; }
}
@media (max-width: 480px) {
  .main { padding: 20px 12px; }
  .page-header h1 { font-size: 18px; }
  .filters { gap: 6px; }
  .filter-btn { padding: 6px 12px; font-size: 11px; }
  .action-btn { padding: 5px 8px; font-size: 10px; }
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin-header.php'; ?>

<div class="main">
  <div class="page-header">
    <h1><i class="fa fa-users" style="color:#6366f1;margin-right:10px"></i>Manage Users</h1>
    <span style="font-size:13px;color:#94a3b8"><?= number_format($totalRows) ?> total</span>
  </div>

  <!-- Filters -->
  <div class="filters">
    <?php foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $val => $label): ?>
      <a href="?status=<?= $val ?>&search=<?= urlencode($search) ?>" class="filter-btn <?= $status === $val ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <!-- Search -->
  <form method="GET" class="search-bar">
    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
    <input type="text" name="search" placeholder="Search by name, email, or username..." value="<?= htmlspecialchars($search) ?>">
    <button type="submit"><i class="fa fa-search"></i> Search</button>
  </form>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email / Username</th>
          <th>Balance</th>
          <th>Status</th>
          <th>Registered</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td style="color:#4b5563"><?= $u['id'] ?></td>
          <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
          <td>
            <div style="font-size:13px"><?= htmlspecialchars($u['email']) ?></div>
            <div style="font-size:12px;color:#4b5563">@<?= htmlspecialchars($u['username']) ?></div>
          </td>
          <td><?= formatINR((float)$u['current_balance']) ?></td>
          <td>
            <?php if ($u['is_blocked']): ?>
              <span class="badge badge-blocked">Blocked</span>
            <?php else: ?>
              <span class="badge badge-<?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:#94a3b8"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
          <td style="display:flex;gap:6px;flex-wrap:wrap">
            <a href="view-account.php?id=<?= $u['id'] ?>" class="action-btn btn-view"><i class="fa fa-eye"></i> View</a>
            <?php if ($u['status'] === 'pending' && !$u['is_blocked']): ?>
              <button class="action-btn btn-approve" onclick="updateStatus(<?= $u['id'] ?>, 'approved')"><i class="fa fa-check"></i> Approve</button>
              <button class="action-btn btn-reject"  onclick="updateStatus(<?= $u['id'] ?>, 'rejected')"><i class="fa fa-times"></i> Reject</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
          <tr><td colspan="7" class="empty"><i class="fa fa-search" style="font-size:24px;margin-bottom:8px;display:block"></i>No users found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <?php if ($i === $page): ?>
        <span class="current"><?= $i ?></span>
      <?php else: ?>
        <a href="?status=<?= $status ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<script>
async function updateStatus(userId, status) {
  if (!confirm(`Are you sure you want to ${status} this user?`)) return;
  const res  = await fetch('../api/admin-update-user.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ user_id: userId, status })
  });
  const data = await res.json();
  if (data.success) { location.reload(); }
  else { alert(data.message); }
}

</script>
</body>
</html>
