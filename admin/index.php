<?php
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
$db    = getDB();

// Stats
$queries = [
    'total_users'         => "SELECT COUNT(*) FROM account_registrations",
    'pending_users'       => "SELECT COUNT(*) FROM account_registrations WHERE status='pending'",
    'approved_users'      => "SELECT COUNT(*) FROM account_registrations WHERE status='approved'",
    'pending_deposits'    => "SELECT COUNT(*) FROM payments WHERE status='pending'",
    'pending_withdrawals' => "SELECT COUNT(*) FROM withdrawals WHERE status='pending'",
    'pending_orders'      => "SELECT COUNT(*) FROM user_orders WHERE status='PENDING'",
    'total_balance'       => "SELECT COALESCE(SUM(current_balance),0) FROM account_registrations WHERE status='approved'",
];
$stats = [];
foreach ($queries as $key => $sql) {
    $stats[$key] = $db->query($sql)->fetchColumn();
}

$recentUsers = $db->query("SELECT id, first_name, last_name, email, status, created_at FROM account_registrations ORDER BY created_at DESC LIMIT 8")->fetchAll();
$recentLogs  = $db->query("SELECT l.action, l.entity_type, l.details, l.created_at, a.name FROM admin_activity_log l JOIN admins a ON a.id = l.admin_id ORDER BY l.created_at DESC LIMIT 6")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — TradeZenfy Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F5F7FA; color: #1A1A1A; min-height: 100vh; overflow-x: hidden; }

/* ── Top Header ── */
.top-header { background: #fff; border-bottom: 1px solid #E5E7EB; padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.top-header .logo { font-size: 20px; font-weight: 700; color: #1A1A1A; }
.top-header .logo span { color: #2563EB; }
.top-header .logo small { font-size: 11px; color: #6B7280; letter-spacing: .06em; text-transform: uppercase; margin-left: 10px; }
.header-right { display: flex; align-items: center; gap: 16px; }
.admin-badge { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #6B7280; }
.admin-badge .avatar { width: 32px; height: 32px; border-radius: 50%; background: #2563EB; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #fff; }
.logout-btn { padding: 7px 16px; background: #FEF2F2; color: #EF4444; border: 1px solid #FECACA; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: background .2s; }
.logout-btn:hover { background: #FEE2E2; }

/* ── Main ── */
.main { max-width: 1200px; margin: 0 auto; padding: 28px; }
.page-header { margin-bottom: 28px; }
.page-header h1 { font-size: 22px; font-weight: 600; color: #1A1A1A; }
.page-header p { color: #6B7280; font-size: 14px; margin-top: 3px; }

/* ── Navigation Cards ── */
.nav-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 16px; margin-bottom: 32px; }
.nav-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 14px; padding: 24px 16px; text-align: center; text-decoration: none; color: #1A1A1A; transition: all .2s; display: flex; flex-direction: column; align-items: center; gap: 12px; position: relative; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.nav-card:hover { border-color: #2563EB; transform: translateY(-3px); box-shadow: 0 8px 24px rgba(37,99,235,0.12); }
.nav-card .icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.nav-card .label { font-size: 13px; font-weight: 600; color: #1A1A1A; }
.nav-card .badge-count { position: absolute; top: 10px; right: 10px; background: #F59E0B; color: #fff; border-radius: 20px; padding: 1px 8px; font-size: 11px; font-weight: 600; }

/* Icon color variants - Light theme */
.ic-purple { background: #EDE9FE; color: #7C3AED; }
.ic-orange { background: #FEF3C7; color: #D97706; }
.ic-green  { background: #D1FAE5; color: #059669; }
.ic-red    { background: #FEE2E2; color: #DC2626; }
.ic-blue   { background: #DBEAFE; color: #2563EB; }
.ic-teal   { background: #CCFBF1; color: #0D9488; }
.ic-pink   { background: #FCE7F3; color: #DB2777; }
.ic-yellow { background: #FEF3C7; color: #B45309; }

/* ── Stat cards ── */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 16px; margin-bottom: 28px; }
.stat-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 20px; display: flex; align-items: flex-start; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.stat-card .label { font-size: 12px; color: #6B7280; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 6px; }
.stat-card .value { font-size: 26px; font-weight: 700; color: #1A1A1A; }
.stat-card .sub { font-size: 12px; color: #9CA3AF; margin-top: 3px; }
.stat-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }

/* ── Two-col layout ── */
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 1100px) { .two-col { grid-template-columns: 1fr; } }

/* ── Table card ── */
.card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.card-header { padding: 18px 20px; border-bottom: 1px solid #E5E7EB; display: flex; align-items: center; justify-content: space-between; }
.card-header h3 { font-size: 15px; font-weight: 600; color: #1A1A1A; }
.card-header a { font-size: 13px; color: #2563EB; text-decoration: none; font-weight: 500; }
.card-header a:hover { text-decoration: underline; }
table { width: 100%; border-collapse: collapse; }
th { font-size: 11px; color: #6B7280; text-transform: uppercase; letter-spacing: .05em; padding: 10px 20px; text-align: left; background: #F9FAFB; font-weight: 600; }
td { padding: 12px 20px; font-size: 14px; border-top: 1px solid #F3F4F6; color: #1A1A1A; }
tr:hover td { background: #F9FAFB; }

/* ── Badges ── */
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
.badge-pending  { background: #FEF3C7; color: #D97706; }
.badge-approved { background: #D1FAE5; color: #059669; }
.badge-rejected { background: #FEE2E2; color: #DC2626; }

/* ── Alert banners ── */
.alert-banner { padding: 12px 20px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.alert-banner.orange { background: #FEF3C7; border: 1px solid #FDE68A; color: #92400E; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .top-header { padding: 12px 16px; }
  .top-header .logo small { display: none; }
  .main { padding: 20px 16px; }
  .nav-cards { grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .nav-card { padding: 18px 10px; }
  .nav-card .icon { width: 44px; height: 44px; font-size: 17px; }
  .nav-card .label { font-size: 11px; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .stat-card { padding: 16px; }
  .stat-card .value { font-size: 20px; }
  .stat-icon { width: 36px; height: 36px; font-size: 14px; }
  .two-col { grid-template-columns: 1fr; gap: 16px; }
  table { display: block; overflow-x: auto; white-space: nowrap; }
  th, td { padding: 10px 12px; font-size: 13px; }
  .page-header h1 { font-size: 20px; }
  .page-header p { font-size: 13px; }
  .alert-banner { font-size: 13px; padding: 12px 16px; }
}
@media (max-width: 480px) {
  .nav-cards { grid-template-columns: repeat(2, 1fr); }
  .stats-grid { grid-template-columns: 1fr; }
  .admin-badge span { display: none; }
  .page-header h1 { font-size: 18px; }
  .stat-card .value { font-size: 18px; }
}
</style>
</head>
<body>

<!-- ── Top Header ── -->
<div class="top-header">
  <div class="logo">TradeZenfy<small>Admin Panel</small></div>
  <div class="header-right">
    <div class="admin-badge">
      <div class="avatar"><?= strtoupper(substr($admin['name'], 0, 1)) ?></div>
      <span><?= htmlspecialchars($admin['name']) ?></span>
    </div>
    <a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i> Logout</a>
  </div>
</div>

<!-- ── Main ── -->
<div class="main">
  <div class="page-header">
    <h1>Dashboard</h1>
    <p>Welcome back, <?= htmlspecialchars($admin['name']) ?>. Here's what's happening today.</p>
  </div>

  <?php if ($stats['pending_users'] > 0 || $stats['pending_deposits'] > 0 || $stats['pending_withdrawals'] > 0): ?>
  <div class="alert-banner orange">
    <i class="fa fa-bell"></i>
    You have
    <?php $parts = [];
      if ($stats['pending_users'] > 0)       $parts[] = "<strong>{$stats['pending_users']}</strong> pending user(s)";
      if ($stats['pending_deposits'] > 0)    $parts[] = "<strong>{$stats['pending_deposits']}</strong> pending deposit(s)";
      if ($stats['pending_withdrawals'] > 0) $parts[] = "<strong>{$stats['pending_withdrawals']}</strong> pending withdrawal(s)";
      echo implode(', ', $parts);
    ?> awaiting action.
  </div>
  <?php endif; ?>

  <!-- Navigation Cards -->
  <div class="nav-cards">
    <a href="manage-user.php" class="nav-card">
      <div class="icon ic-purple"><i class="fa fa-users"></i></div>
      <div class="label">Manage Users</div>
      <?php if ($stats['pending_users'] > 0): ?><div class="badge-count"><?= $stats['pending_users'] ?></div><?php endif; ?>
    </a>
    <a href="payment-details.php" class="nav-card">
      <div class="icon ic-teal"><i class="fa fa-money-bill-wave"></i></div>
      <div class="label">Deposits</div>
      <?php if ($stats['pending_deposits'] > 0): ?><div class="badge-count"><?= $stats['pending_deposits'] ?></div><?php endif; ?>
    </a>
    <a href="withdrawals.php" class="nav-card">
      <div class="icon ic-red"><i class="fa fa-wallet"></i></div>
      <div class="label">Withdrawals</div>
      <?php if ($stats['pending_withdrawals'] > 0): ?><div class="badge-count"><?= $stats['pending_withdrawals'] ?></div><?php endif; ?>
    </a>
    <a href="stocks.php" class="nav-card">
      <div class="icon ic-blue"><i class="fa fa-chart-line"></i></div>
      <div class="label">Stocks</div>
    </a>
    <a href="orders.php" class="nav-card">
      <div class="icon ic-green"><i class="fa fa-list-alt"></i></div>
      <div class="label">Orders</div>
      <?php if ($stats['pending_orders'] > 0): ?><div class="badge-count"><?= $stats['pending_orders'] ?></div><?php endif; ?>
    </a>
    <a href="holdings.php" class="nav-card">
      <div class="icon ic-yellow"><i class="fa fa-briefcase"></i></div>
      <div class="label">Holdings</div>
    </a>
    <a href="fno-dashboard.php" class="nav-card">
      <div class="icon ic-pink"><i class="fa fa-chart-bar"></i></div>
      <div class="label">F&O Dashboard</div>
    </a>
    <a href="fno-contracts.php" class="nav-card">
      <div class="icon ic-orange"><i class="fa fa-file-contract"></i></div>
      <div class="label">F&O Contracts</div>
    </a>
    <a href="fno-orders.php" class="nav-card">
      <div class="icon ic-purple"><i class="fa fa-shopping-cart"></i></div>
      <div class="label">F&O Orders</div>
    </a>
    <a href="fno-risk-monitor.php" class="nav-card">
      <div class="icon ic-red"><i class="fa fa-exclamation-triangle"></i></div>
      <div class="label">Risk Monitor</div>
    </a>
    <a href="admins.php" class="nav-card">
      <div class="icon ic-blue"><i class="fa fa-user-shield"></i></div>
      <div class="label">Admins</div>
    </a>
    <a href="activity-log.php" class="nav-card">
      <div class="icon ic-teal"><i class="fa fa-history"></i></div>
      <div class="label">Activity Log</div>
    </a>
    <a href="api-settings.php" class="nav-card">
      <div class="icon ic-green"><i class="fa fa-key"></i></div>
      <div class="label">API Settings</div>
    </a>
  </div>

  <!-- Stat Cards -->
  <div class="stats-grid">
    <div class="stat-card">
      <div>
        <div class="label">Total Users</div>
        <div class="value"><?= number_format($stats['total_users']) ?></div>
        <div class="sub"><?= $stats['approved_users'] ?> approved</div>
      </div>
      <div class="stat-icon ic-purple"><i class="fa fa-users"></i></div>
    </div>
    <div class="stat-card">
      <div>
        <div class="label">Pending Approvals</div>
        <div class="value"><?= $stats['pending_users'] ?></div>
        <div class="sub">Awaiting review</div>
      </div>
      <div class="stat-icon ic-orange"><i class="fa fa-user-clock"></i></div>
    </div>
    <div class="stat-card">
      <div>
        <div class="label">Pending Deposits</div>
        <div class="value"><?= $stats['pending_deposits'] ?></div>
        <div class="sub">Need verification</div>
      </div>
      <div class="stat-icon ic-teal"><i class="fa fa-money-bill-wave"></i></div>
    </div>
    <div class="stat-card">
      <div>
        <div class="label">Pending Withdrawals</div>
        <div class="value"><?= $stats['pending_withdrawals'] ?></div>
        <div class="sub">Awaiting approval</div>
      </div>
      <div class="stat-icon ic-red"><i class="fa fa-wallet"></i></div>
    </div>
    <div class="stat-card">
      <div>
        <div class="label">Pending Orders</div>
        <div class="value"><?= $stats['pending_orders'] ?></div>
        <div class="sub">Awaiting execution</div>
      </div>
      <div class="stat-icon ic-blue"><i class="fa fa-list-alt"></i></div>
    </div>
    <div class="stat-card">
      <div>
        <div class="label">Total User Balance</div>
        <div class="value" style="font-size:20px"><?= formatINR((float)$stats['total_balance']) ?></div>
        <div class="sub">Across all accounts</div>
      </div>
      <div class="stat-icon ic-green"><i class="fa fa-rupee-sign"></i></div>
    </div>
  </div>

  <!-- Tables row -->
  <div class="two-col">
    <!-- Recent Users -->
    <div class="card">
      <div class="card-header">
        <h3><i class="fa fa-users" style="color:#2563EB;margin-right:8px"></i>Recent Registrations</h3>
        <a href="manage-user.php">View all</a>
      </div>
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($recentUsers as $u): ?>
          <tr onclick="window.location='view-account.php?id=<?= $u['id'] ?>'" style="cursor:pointer">
            <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
            <td style="color:#6B7280;font-size:13px"><?= htmlspecialchars($u['email']) ?></td>
            <td><span class="badge badge-<?= $u['status'] ?>"><?= $u['status'] ?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recentUsers)): ?>
          <tr><td colspan="3" style="text-align:center;color:#9CA3AF;padding:24px">No registrations yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Recent Activity Log -->
    <div class="card">
      <div class="card-header">
        <h3><i class="fa fa-history" style="color:#2563EB;margin-right:8px"></i>Recent Activity</h3>
        <a href="activity-log.php">View all</a>
      </div>
      <table>
        <thead><tr><th>Admin</th><th>Action</th><th>Time</th></tr></thead>
        <tbody>
          <?php foreach ($recentLogs as $log): ?>
          <tr>
            <td style="font-size:13px"><?= htmlspecialchars($log['name']) ?></td>
            <td style="font-size:13px;color:#6B7280"><?= htmlspecialchars($log['action']) ?></td>
            <td style="font-size:12px;color:#9CA3AF"><?= date('d M, H:i', strtotime($log['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recentLogs)): ?>
          <tr><td colspan="3" style="text-align:center;color:#9CA3AF;padding:24px">No activity yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</body>
</html>
