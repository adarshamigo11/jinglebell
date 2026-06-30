<?php
$current = basename($_SERVER['PHP_SELF'], '.php');
$pages = [
  'index' => ['Home', 'index.php'],
  'about' => ['About Us', 'about.php'],
  'stock' => ['Stocks', 'stock.php'],
  'market' => ['Live Market', 'market.php'],
  'contact' => ['Contact', 'contact.php'],
];
?>
<nav>
  <a href="index.php" class="logo">YOUR<span>◆</span>COMPANY</a>
  <button class="mobile-menu-btn" onclick="toggleMobileNav()" aria-label="Toggle menu">
    <i class="fas fa-bars"></i>
  </button>
  <div class="nav-links" id="navLinks">
    <?php foreach($pages as $key => [$label, $href]): ?>
      <a href="<?= $href ?>" class="<?= $current === $key || ($current === '' && $key === 'index') ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
    <div class="nav-cta mobile">
      <a href="login.php" class="btn-nav btn-nav-ghost">Login</a>
      <a href="register.php" class="btn-nav btn-nav-fill">Open Account</a>
    </div>
  </div>
  <div class="nav-cta desktop">
    <a href="login.php" class="btn-nav btn-nav-ghost">Login</a>
    <a href="register.php" class="btn-nav btn-nav-fill">Open Account</a>
  </div>
</nav>
<div class="mobile-nav-overlay" id="mobileNavOverlay" onclick="toggleMobileNav()"></div>
<script>
function toggleMobileNav() {
  const navLinks = document.getElementById('navLinks');
  const overlay = document.getElementById('mobileNavOverlay');
  navLinks.classList.toggle('active');
  overlay.classList.toggle('active');
  document.body.style.overflow = navLinks.classList.contains('active') ? 'hidden' : '';
}
</script>
