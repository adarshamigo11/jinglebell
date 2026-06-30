<?php
// Admin header bar - included in all admin sub-pages
$adminName = $admin['name'] ?? 'Admin';
$adminInitial = strtoupper(substr($adminName, 0, 1));
?>
<style>
/* ── Top Header Bar (Light TradeZenfy Theme) ── */
.top-header { background: #fff; border-bottom: 1px solid #E5E7EB; padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.top-header .logo { font-size: 18px; font-weight: 700; color: #1A1A1A; text-decoration: none; display: flex; align-items: center; gap: 12px; }
.top-header .logo span { color: #2563EB; }
.top-header .logo .back-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: #F3F4F6; border: 1px solid #E5E7EB; border-radius: 8px; color: #6B7280; font-size: 13px; font-weight: 500; text-decoration: none; transition: all .15s; }
.top-header .logo .back-btn:hover { color: #1A1A1A; border-color: #2563EB; }
.header-right { display: flex; align-items: center; gap: 16px; }
.admin-badge { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #6B7280; }
.admin-badge .avatar { width: 32px; height: 32px; border-radius: 50%; background: #2563EB; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #fff; }
.logout-btn { padding: 7px 16px; background: #FEF2F2; color: #EF4444; border: 1px solid #FECACA; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: background .2s; }
.logout-btn:hover { background: #FEE2E2; }
.hamburger-btn { display: none; background: none; border: none; font-size: 20px; color: #1A1A1A; cursor: pointer; padding: 8px; }
.admin-mobile-menu { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; }
.admin-mobile-menu.active { display: block; }
.admin-menu-panel { position: absolute; top: 0; right: 0; width: 280px; height: 100%; background: #fff; box-shadow: -2px 0 8px rgba(0,0,0,0.1); padding: 20px; overflow-y: auto; }
.admin-menu-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #E5E7EB; }
.admin-menu-header h3 { font-size: 16px; font-weight: 600; color: #1A1A1A; }
.admin-menu-close { background: none; border: none; font-size: 24px; color: #6B7280; cursor: pointer; }
.admin-menu-links { display: flex; flex-direction: column; gap: 4px; }
.admin-menu-links a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #1A1A1A; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 500; transition: background 0.2s; }
.admin-menu-links a:hover { background: #F3F4F6; }
.admin-menu-links a i { width: 20px; color: #2563EB; }
@media (max-width: 768px) {
  .top-header { padding: 12px 16px; }
  .top-header .logo .back-btn { display: none; }
  .admin-badge span { display: none; }
  .logout-btn span { display: none; }
  .hamburger-btn { display: block; }
}
</style>

<div class="top-header">
  <div class="logo">
    <a href="index.php" class="back-btn"><i class="fa fa-arrow-left"></i> <span>Dashboard</span></a>
    TradeZenfy
  </div>
  <div class="header-right">
    <div class="admin-badge">
      <div class="avatar"><?= $adminInitial ?></div>
      <span><?= htmlspecialchars($adminName) ?></span>
    </div>
    <a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i> <span>Logout</span></a>
    <button class="hamburger-btn" onclick="toggleAdminMenu()"><i class="fa fa-bars"></i></button>
  </div>
</div>

<div class="admin-mobile-menu" id="adminMobileMenu" onclick="closeAdminMenu(event)">
  <div class="admin-menu-panel">
    <div class="admin-menu-header">
      <h3>Menu</h3>
      <button class="admin-menu-close" onclick="toggleAdminMenu()">&times;</button>
    </div>
    <div class="admin-menu-links">
      <a href="index.php"><i class="fa fa-home"></i> Dashboard</a>
      <a href="manage-user.php"><i class="fa fa-users"></i> Manage Users</a>
      <a href="orders.php"><i class="fa fa-list-alt"></i> Orders</a>
      <a href="stocks.php"><i class="fa fa-chart-line"></i> Stocks</a>
      <a href="payment-details.php"><i class="fa fa-money-bill-wave"></i> Deposits</a>
      <a href="withdrawals.php"><i class="fa fa-money-check"></i> Withdrawals</a>
      <a href="holdings.php"><i class="fa fa-briefcase"></i> Holdings</a>
      <a href="activity-log.php"><i class="fa fa-history"></i> Activity Log</a>
      <a href="fno-dashboard.php"><i class="fa fa-chart-bar"></i> F&O Dashboard</a>
      <a href="admins.php"><i class="fa fa-user-shield"></i> Admins</a>
      <a href="../logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>
  </div>
</div>

<script>
function toggleAdminMenu() {
  document.getElementById('adminMobileMenu').classList.toggle('active');
}
function closeAdminMenu(event) {
  if (event.target.id === 'adminMobileMenu') {
    toggleAdminMenu();
  }
}
</script>
