<?php
// Shared Mobile Bottom Navigation - Include this in all authenticated user pages
// Requires: $currentPage variable set before including this file
// Example: $currentPage = 'dashboard'; // before including

$inUser = strpos($_SERVER['PHP_SELF'], '/user/') !== false;
$rootPrefix = $inUser ? '../' : '';

// Determine active page
$activePage = $currentPage ?? 'dashboard';
?>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-bottom-nav">
  <div class="bottom-nav-items">
    <a href="<?= $inUser ? 'dashboard.php' : $rootPrefix . 'user/dashboard.php' ?>" class="bottom-nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>">
      <i class="fa fa-home"></i>
      <span>Home</span>
    </a>
    <a href="<?= $rootPrefix ?>stock-market.php" class="bottom-nav-item <?= in_array($activePage, ['stock-market', 'stock-detail']) ? 'active' : '' ?>">
      <i class="fa fa-chart-line"></i>
      <span>Stocks</span>
    </a>
    <a href="<?= $inUser ? 'portfolio.php' : $rootPrefix . 'user/portfolio.php' ?>" class="bottom-nav-item <?= in_array($activePage, ['portfolio', 'orders']) ? 'active' : '' ?>">
      <i class="fa fa-briefcase"></i>
      <span>Portfolio</span>
    </a>
    <a href="<?= $inUser ? 'deposit.php' : $rootPrefix . 'user/deposit.php' ?>" class="bottom-nav-item <?= in_array($activePage, ['deposit', 'withdraw']) ? 'active' : '' ?>">
      <i class="fa fa-wallet"></i>
      <span>Funds</span>
    </a>
    <a href="<?= $inUser ? 'edit-profile.php' : $rootPrefix . 'user/edit-profile.php' ?>" class="bottom-nav-item <?= in_array($activePage, ['edit-profile']) ? 'active' : '' ?>">
      <i class="fa fa-user"></i>
      <span>Profile</span>
    </a>
  </div>
</nav>
