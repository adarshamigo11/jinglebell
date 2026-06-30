<?php
// Shared User Sidebar - Include this in all authenticated user pages
// Requires: $user variable from requireUser()
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$inUser = strpos($_SERVER['PHP_SELF'], '/user/') !== false;
$rootPrefix = $inUser ? '../' : '';
$userPrefix = $inUser ? '' : 'user/';
?>
<button class="sidebar-toggle" id="sidebarToggle" onclick="toggleUserSidebar()" aria-label="Toggle menu">
  <i class="fa fa-bars"></i>
</button>

<div class="sidebar" id="userSidebar">
  <div class="sidebar-logo">
    <h2>TradeZenfy</h2>
  </div>
  <nav class="nav">
    <div class="nav-section">Main</div>
    <a href="<?= $userPrefix ?>dashboard.php" <?= $currentPage === 'dashboard' ? 'class="active"' : '' ?>><i class="fa fa-home"></i> Dashboard</a>
    <div class="nav-section">Investments</div>
    <a href="<?= $rootPrefix ?>stock-market.php" <?= in_array($currentPage, ['stock-market','stock-detail']) ? 'class="active"' : '' ?>><i class="fa fa-chart-line"></i> Stocks</a>
    <a href="<?= $rootPrefix ?>fno-market.php" <?= in_array($currentPage, ['fno-market','fno-detail']) ? 'class="active"' : '' ?>><i class="fa fa-chart-bar"></i> F&amp;O</a>
    <a href="<?= $userPrefix ?>portfolio.php" <?= $currentPage === 'portfolio' ? 'class="active"' : '' ?>><i class="fa fa-briefcase"></i> Stock Portfolio</a>
    <a href="<?= $userPrefix ?>fno-positions.php" <?= in_array($currentPage, ['fno-positions','fno-dashboard']) ? 'class="active"' : '' ?>><i class="fa fa-wallet"></i> F&amp;O Positions</a>
    <div class="nav-section">Orders &amp; Reports</div>
    <a href="<?= $userPrefix ?>orders.php" <?= $currentPage === 'orders' ? 'class="active"' : '' ?>><i class="fa fa-list-alt"></i> Stock Orders</a>
    <a href="<?= $userPrefix ?>fno-orders.php" <?= $currentPage === 'fno-orders' ? 'class="active"' : '' ?>><i class="fa fa-list-alt"></i> F&amp;O Orders</a>
    <a href="<?= $userPrefix ?>pnl-report.php" <?= $currentPage === 'pnl-report' ? 'class="active"' : '' ?>><i class="fa fa-chart-bar"></i> P&amp;L Report</a>
    <div class="nav-section">Funds</div>
    <a href="<?= $userPrefix ?>deposit.php" <?= $currentPage === 'deposit' ? 'class="active"' : '' ?>><i class="fa fa-plus-circle"></i> Add Money</a>
    <a href="<?= $userPrefix ?>withdraw.php" <?= $currentPage === 'withdraw' ? 'class="active"' : '' ?>><i class="fa fa-minus-circle"></i> Withdraw</a>
    <div class="nav-section">Account</div>
    <a href="<?= $userPrefix ?>edit-profile.php" <?= $currentPage === 'edit-profile' ? 'class="active"' : '' ?>><i class="fa fa-user-edit"></i> Profile</a>
    <a href="<?= $rootPrefix ?>logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
  </nav>
  <div class="sidebar-footer">
    <strong><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></strong>
    <p>@<?= htmlspecialchars($user['username'] ?? '') ?></p>
    <a href="<?= $rootPrefix ?>logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
  </div>
</div>

<script>
function toggleUserSidebar() {
  const sidebar = document.getElementById('userSidebar');
  const toggle = document.getElementById('sidebarToggle');
  sidebar.classList.toggle('expanded');
  const isExpanded = sidebar.classList.contains('expanded');
  toggle.innerHTML = isExpanded ? '<i class="fa fa-times"></i>' : '<i class="fa fa-bars"></i>';
}
document.addEventListener('click', function(e) {
  const sidebar = document.getElementById('userSidebar');
  const toggle = document.getElementById('sidebarToggle');
  if (window.innerWidth <= 768 && sidebar.classList.contains('expanded')) {
    if (!sidebar.contains(e.target) && !toggle.contains(e.target)) toggleUserSidebar();
  }
});
</script>
