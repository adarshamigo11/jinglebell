<!-- TradeZenfy UI Components - Include in all user pages -->
<style>
:root {
  --groww-green: #00D09C;
  --groww-green-dark: #00B386;
  --groww-red: #FF4D4D;
  --groww-bg: #F5F7FA;
  --groww-card: #F5F7FA;
  --groww-text: #1A1A1A;
  --groww-text-secondary: #6B7280;
  --groww-border: #E5E7EB;
  --groww-hover: #F3F4F6;
}
</style>

<!-- Sidebar Component -->
<button class="sidebar-toggle" id="sidebarToggle" onclick="toggleUserSidebar()" aria-label="Toggle menu">
  <i class="fa fa-bars"></i>
</button>

<div class="sidebar" id="userSidebar">
  <div class="sidebar-logo">
    <h2>TradeZenfy</h2>
  </div>
  <nav class="nav">
    <div class="nav-section">Main</div>
    <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>"><i class="fa fa-home"></i> Dashboard</a>
    <div class="nav-section">Investments</div>
    <a href="../stock-market.php"><i class="fa fa-chart-line"></i> Stocks</a>
    <a href="watchlist.php" class="<?= basename($_SERVER['PHP_SELF']) == 'watchlist.php' ? 'active' : '' ?>"><i class="fa fa-star"></i> Watchlist</a>
    <a href="portfolio.php" class="<?= basename($_SERVER['PHP_SELF']) == 'portfolio.php' ? 'active' : '' ?>"><i class="fa fa-briefcase"></i> Portfolio</a>
    <div class="nav-section">Orders & Reports</div>
    <a href="orders.php" class="<?= basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : '' ?>"><i class="fa fa-list-alt"></i> Orders</a>
    <a href="pnl-report.php" class="<?= basename($_SERVER['PHP_SELF']) == 'pnl-report.php' ? 'active' : '' ?>"><i class="fa fa-chart-bar"></i> P&L Report</a>
    <div class="nav-section">Funds</div>
    <a href="deposit.php" class="<?= basename($_SERVER['PHP_SELF']) == 'deposit.php' ? 'active' : '' ?>"><i class="fa fa-plus-circle"></i> Add Money</a>
    <a href="withdraw.php" class="<?= basename($_SERVER['PHP_SELF']) == 'withdraw.php' ? 'active' : '' ?>"><i class="fa fa-minus-circle"></i> Withdraw</a>
    <div class="nav-section">Account</div>
    <a href="edit-profile.php" class="<?= basename($_SERVER['PHP_SELF']) == 'edit-profile.php' ? 'active' : '' ?>"><i class="fa fa-user-edit"></i> Profile</a>
    <a href="../logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
  </nav>
  <div class="sidebar-footer">
    <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>
    <p>@<?= htmlspecialchars($user['username']) ?></p>
    <a href="../logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
  </div>
</div>

<!-- Mobile Bottom Navigation -->
<div class="mobile-bottom-nav">
  <div class="bottom-nav-items">
    <a href="dashboard.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
      <i class="fa fa-home"></i>
      <span>Home</span>
    </a>
    <a href="../stock-market.php" class="bottom-nav-item">
      <i class="fa fa-chart-line"></i>
      <span>Stocks</span>
    </a>
    <a href="portfolio.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'portfolio.php' ? 'active' : '' ?>">
      <i class="fa fa-briefcase"></i>
      <span>Portfolio</span>
    </a>
    <a href="orders.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : '' ?>">
      <i class="fa fa-list-alt"></i>
      <span>Orders</span>
    </a>
    <a href="deposit.php" class="bottom-nav-item <?= in_array(basename($_SERVER['PHP_SELF']), ['deposit.php', 'withdraw.php']) ? 'active' : '' ?>">
      <i class="fa fa-wallet"></i>
      <span>Funds</span>
    </a>
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
    if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
      toggleUserSidebar();
    }
  }
});
</script>
