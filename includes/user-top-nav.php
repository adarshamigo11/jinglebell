<?php
// Shared User Top Navigation - Include this in all authenticated user pages
// Requires: $user variable from requireUser()
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$inUser = strpos($_SERVER['PHP_SELF'], '/user/') !== false;
$rootPrefix = $inUser ? '../' : '';
$userPrefix = $inUser ? '' : 'user/';

// Get user initials for avatar
$userInitials = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
$displayName = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
?>

<!-- Desktop Top Navigation -->
<nav class="top-nav">
  <div class="top-nav-left">
    <a href="<?= $userPrefix ?>dashboard.php" class="top-nav-logo">
      TradeZenfy
    </a>
    <div class="top-nav-links">
      <a href="<?= $userPrefix ?>dashboard.php" <?= $currentPage === 'dashboard' ? 'class="active"' : '' ?>>
        Dashboard
      </a>
      <a href="<?= $rootPrefix ?>stock-market.php" <?= in_array($currentPage, ['stock-market','stock-detail','index-detail']) ? 'class="active"' : '' ?>>
        Stocks
      </a>
      <a href="<?= $rootPrefix ?>commodity-market.php" <?= in_array($currentPage, ['commodity-market','commodity-detail']) ? 'class="active"' : '' ?>>
        Commodities
      </a>
      <a href="<?= $rootPrefix ?>crypto-market.php" <?= in_array($currentPage, ['crypto-market','crypto-detail']) ? 'class="active"' : '' ?>>
        Crypto
      </a>
      <a href="<?= $rootPrefix ?>fno-market.php" <?= in_array($currentPage, ['fno-market','fno-detail']) ? 'class="active"' : '' ?>>
        F&O
      </a>
      <a href="<?= $userPrefix ?>portfolio.php" <?= $currentPage === 'portfolio' ? 'class="active"' : '' ?>>
        Portfolio
      </a>
      <a href="<?= $userPrefix ?>orders.php" <?= $currentPage === 'orders' ? 'class="active"' : '' ?>>
        Orders
      </a>
      <a href="<?= $userPrefix ?>deposit.php" <?= in_array($currentPage, ['deposit','withdraw']) ? 'class="active"' : '' ?>>
        Funds
      </a>
    </div>
  </div>
  
  <div class="top-nav-right">
    <button class="nav-icon-btn" title="Notifications">
      <i class="fa fa-bell"></i>
    </button>
    
    <div class="nav-user-info" onclick="window.location.href='<?= $userPrefix ?>edit-profile.php'">
      <div class="nav-user-avatar"><?= $userInitials ?></div>
      <div class="nav-user-name"><?= htmlspecialchars($displayName) ?></div>
    </div>
    
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleMobileMenu()" aria-label="Toggle menu">
      <i class="fa fa-bars"></i>
    </button>
  </div>
</nav>

<!-- Mobile Header: Logo + Profile Avatar -->
<div class="mobile-header">
  <a href="<?= $userPrefix ?>dashboard.php" class="mobile-header-logo">
    TradeZenfy
  </a>
  <a href="<?= $userPrefix ?>edit-profile.php" class="mobile-header-avatar" title="Profile">
    <?= $userInitials ?>
  </a>
</div>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-bottom-nav">
  <a href="<?= $userPrefix ?>dashboard.php" class="mobile-bottom-nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
    <i class="fa fa-home"></i>
    <span>Home</span>
  </a>
  <a href="<?= $rootPrefix ?>stock-market.php" class="mobile-bottom-nav-item <?= in_array($currentPage, ['stock-market','stock-detail','index-detail']) ? 'active' : '' ?>">
    <i class="fa fa-chart-line"></i>
    <span>Stocks</span>
  </a>
  <a href="<?= $rootPrefix ?>commodity-market.php" class="mobile-bottom-nav-item <?= in_array($currentPage, ['commodity-market','commodity-detail']) ? 'active' : '' ?>">
    <i class="fa fa-gem"></i>
    <span>Commodities</span>
  </a>
  <a href="<?= $rootPrefix ?>crypto-market.php" class="mobile-bottom-nav-item <?= in_array($currentPage, ['crypto-market','crypto-detail']) ? 'active' : '' ?>">
    <i class="fa fa-bitcoin"></i>
    <span>Crypto</span>
  </a>
  <a href="<?= $rootPrefix ?>fno-market.php" class="mobile-bottom-nav-item <?= in_array($currentPage, ['fno-market','fno-detail']) ? 'active' : '' ?>">
    <i class="fa fa-chart-bar"></i>
    <span>F&O</span>
  </a>
  <a href="<?= $userPrefix ?>portfolio.php" class="mobile-bottom-nav-item <?= $currentPage === 'portfolio' ? 'active' : '' ?>">
    <i class="fa fa-briefcase"></i>
    <span>Portfolio</span>
  </a>
  <a href="<?= $userPrefix ?>deposit.php" class="mobile-bottom-nav-item <?= in_array($currentPage, ['deposit','withdraw']) ? 'active' : '' ?>">
    <i class="fa fa-wallet"></i>
    <span>Funds</span>
  </a>
</nav>

<!-- Mobile Slide-up Menu (hamburger) -->
<div class="mobile-menu" id="mobileMenu">
  <a href="<?= $userPrefix ?>dashboard.php" <?= $currentPage === 'dashboard' ? 'class="active"' : '' ?>>
    <i class="fa fa-home"></i> Dashboard
  </a>
  <a href="<?= $rootPrefix ?>stock-market.php" <?= in_array($currentPage, ['stock-market','stock-detail','index-detail']) ? 'class="active"' : '' ?>>
    <i class="fa fa-chart-line"></i> Stocks
  </a>
  <a href="<?= $rootPrefix ?>commodity-market.php" <?= in_array($currentPage, ['commodity-market','commodity-detail']) ? 'class="active"' : '' ?>>
    <i class="fa fa-gem"></i> Commodities
  </a>
  <a href="<?= $rootPrefix ?>crypto-market.php" <?= in_array($currentPage, ['crypto-market','crypto-detail']) ? 'class="active"' : '' ?>>
    <i class="fa fa-bitcoin"></i> Crypto
  </a>
  <a href="<?= $rootPrefix ?>fno-market.php" <?= in_array($currentPage, ['fno-market','fno-detail']) ? 'class="active"' : '' ?>>
    <i class="fa fa-chart-bar"></i> F&O
  </a>
  <a href="<?= $userPrefix ?>portfolio.php" <?= $currentPage === 'portfolio' ? 'class="active"' : '' ?>>
    <i class="fa fa-briefcase"></i> Portfolio
  </a>
  <a href="<?= $userPrefix ?>orders.php" <?= $currentPage === 'orders' ? 'class="active"' : '' ?>>
    <i class="fa fa-list-alt"></i> Orders
  </a>
  <a href="<?= $userPrefix ?>deposit.php" <?= $currentPage === 'deposit' ? 'class="active"' : '' ?>>
    <i class="fa fa-plus-circle"></i> Add Money
  </a>
  <a href="<?= $userPrefix ?>withdraw.php" <?= $currentPage === 'withdraw' ? 'class="active"' : '' ?>>
    <i class="fa fa-minus-circle"></i> Withdraw
  </a>
  <a href="<?= $userPrefix ?>edit-profile.php" <?= $currentPage === 'edit-profile' ? 'class="active"' : '' ?>>
    <i class="fa fa-user-edit"></i> Profile
  </a>
  <a href="<?= $rootPrefix ?>logout.php">
    <i class="fa fa-sign-out-alt"></i> Logout
  </a>
</div>

<script>
function toggleMobileMenu() {
  const menu = document.getElementById('mobileMenu');
  const btn = document.getElementById('hamburgerBtn');
  menu.classList.toggle('active');
  btn.innerHTML = menu.classList.contains('active') 
    ? '<i class="fa fa-times"></i>' 
    : '<i class="fa fa-bars"></i>';
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(e) {
  const menu = document.getElementById('mobileMenu');
  const btn = document.getElementById('hamburgerBtn');
  if (window.innerWidth <= 768 && menu.classList.contains('active')) {
    if (!menu.contains(e.target) && !btn.contains(e.target)) {
      menu.classList.remove('active');
      btn.innerHTML = '<i class="fa fa-bars"></i>';
    }
  }
});
</script>
