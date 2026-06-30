<?php
$adminName = $admin['name'] ?? 'Admin';
$adminInitial = strtoupper(substr($adminName, 0, 1));
?>
<style>
.fno-top-header { background: #fff; border-bottom: 1px solid var(--groww-border); padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
.fno-top-header .fno-logo { font-size: 18px; font-weight: 700; color: var(--groww-text); display: flex; align-items: center; gap: 12px; }
.fno-top-header .fno-logo span { color: var(--groww-green); }
.fno-top-header .back-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: #f0f2f5; border: 1px solid var(--groww-border); border-radius: 8px; color: var(--groww-text-secondary); font-size: 13px; font-weight: 500; text-decoration: none; transition: all .15s; }
.fno-top-header .back-btn:hover { color: var(--groww-text); border-color: var(--groww-green); }
.fno-top-header .header-right { display: flex; align-items: center; gap: 14px; }
.fno-top-header .admin-badge { display: flex; align-items: center; gap: 8px; }
.fno-top-header .avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--groww-green); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; }
.fno-top-header .admin-badge span { font-size: 13px; font-weight: 600; color: var(--groww-text); }
.fno-top-header .logout-btn { padding: 6px 14px; background: #fff; border: 1px solid #fecaca; border-radius: 8px; color: var(--groww-red); font-size: 13px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all .15s; }
.fno-top-header .logout-btn:hover { background: #fef2f2; }
.fno-hamburger-btn { display: none; background: none; border: none; font-size: 20px; color: var(--groww-text); cursor: pointer; padding: 8px; }
.fno-admin-mobile-menu { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; }
.fno-admin-mobile-menu.active { display: block; }
.fno-admin-menu-panel { position: absolute; top: 0; right: 0; width: 280px; height: 100%; background: #fff; box-shadow: -2px 0 8px rgba(0,0,0,0.1); padding: 20px; overflow-y: auto; }
.fno-admin-menu-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--groww-border); }
.fno-admin-menu-header h3 { font-size: 16px; font-weight: 600; color: var(--groww-text); }
.fno-admin-menu-close { background: none; border: none; font-size: 24px; color: var(--groww-text-secondary); cursor: pointer; }
.fno-admin-menu-links { display: flex; flex-direction: column; gap: 4px; }
.fno-admin-menu-links a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--groww-text); text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 500; transition: background 0.2s; }
.fno-admin-menu-links a:hover { background: #F3F4F6; }
.fno-admin-menu-links a i { width: 20px; color: var(--groww-green); }
@media (max-width: 768px) {
  .fno-top-header { padding: 12px 16px; }
  .fno-top-header .back-btn { display: none; }
  .fno-top-header .admin-badge span { display: none; }
  .fno-top-header .logout-btn span { display: none; }
  .fno-hamburger-btn { display: block; }
}
</style>
<div class="fno-top-header">
  <div class="fno-logo">
    <a href="index.php" class="back-btn"><i class="fa fa-arrow-left"></i> <span>Dashboard</span></a>
    TradeZenfy
  </div>
  <div class="header-right">
    <div class="admin-badge">
      <div class="avatar"><?= $adminInitial ?></div>
      <span><?= htmlspecialchars($adminName) ?></span>
    </div>
    <a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i> <span>Logout</span></a>
    <button class="fno-hamburger-btn" onclick="toggleFnoAdminMenu()"><i class="fa fa-bars"></i></button>
  </div>
</div>

<div class="fno-admin-mobile-menu" id="fnoAdminMobileMenu" onclick="closeFnoAdminMenu(event)">
  <div class="fno-admin-menu-panel">
    <div class="fno-admin-menu-header">
      <h3>F&O Menu</h3>
      <button class="fno-admin-menu-close" onclick="toggleFnoAdminMenu()">&times;</button>
    </div>
    <div class="fno-admin-menu-links">
      <a href="fno-dashboard.php"><i class="fa fa-chart-bar"></i> Dashboard</a>
      <a href="fno-contracts.php"><i class="fa fa-file-contract"></i> Contracts</a>
      <a href="fno-orders.php"><i class="fa fa-list-alt"></i> Orders</a>
      <a href="fno-risk-monitor.php"><i class="fa fa-exclamation-triangle"></i> Risk Monitor</a>
      <a href="index.php"><i class="fa fa-home"></i> Main Dashboard</a>
      <a href="../logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>
  </div>
</div>

<script>
function toggleFnoAdminMenu() {
  document.getElementById('fnoAdminMobileMenu').classList.toggle('active');
}
function closeFnoAdminMenu(event) {
  if (event.target.id === 'fnoAdminMobileMenu') {
    toggleFnoAdminMenu();
  }
}
</script>
