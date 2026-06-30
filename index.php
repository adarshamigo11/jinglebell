<?php
session_start();
$loggedInUser  = !empty($_SESSION['user_id']);
$loggedInAdmin = !empty($_SESSION['admin_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TradeZenfy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background: #0b0e14;
  color: #e2e8f0;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  overflow-x: hidden;
}

/* Subtle grid background */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background-image:
    linear-gradient(rgba(99, 102, 241, 0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(99, 102, 241, 0.03) 1px, transparent 1px);
  background-size: 60px 60px;
  pointer-events: none;
  z-index: 0;
}

/* Glow blobs */
body::after {
  content: '';
  position: fixed;
  top: -200px;
  left: 50%;
  transform: translateX(-50%);
  width: 600px;
  height: 600px;
  background: radial-gradient(circle, rgba(99, 102, 241, 0.08) 0%, transparent 70%);
  pointer-events: none;
  z-index: 0;
}

.wrapper {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 960px;
}

/* Logo */
.brand {
  text-align: center;
  margin-bottom: 48px;
}
.brand h1 {
  font-size: 32px;
  font-weight: 700;
  color: #fff;
  letter-spacing: -0.5px;
}
.brand h1 span { color: #6366f1; }
.brand p {
  color: #64748b;
  font-size: 15px;
  margin-top: 8px;
}

/* Cards grid */
.cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
}

.card {
  background: #12151f;
  border: 1px solid #1e2235;
  border-radius: 16px;
  padding: 36px 28px;
  text-align: center;
  text-decoration: none;
  color: inherit;
  transition: all 0.25s ease;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  position: relative;
  overflow: hidden;
}

.card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  border-radius: 16px 16px 0 0;
  opacity: 0;
  transition: opacity 0.25s;
}

.card:hover {
  border-color: #2d3148;
  transform: translateY(-4px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}
.card:hover::before { opacity: 1; }

/* Card accent colors */
.card.card-admin::before { background: linear-gradient(90deg, #f59e0b, #f97316); }
.card.card-login::before  { background: linear-gradient(90deg, #6366f1, #8b5cf6); }
.card.card-register::before { background: linear-gradient(90deg, #10b981, #14b8a6); }

.card-icon {
  width: 64px;
  height: 64px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  flex-shrink: 0;
}

.card-admin .card-icon {
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}
.card-login .card-icon {
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
}
.card-register .card-icon {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.card-title {
  font-size: 18px;
  font-weight: 600;
  color: #fff;
}

.card-desc {
  font-size: 13px;
  color: #64748b;
  line-height: 1.5;
}

.card-btn {
  margin-top: auto;
  padding: 10px 28px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  letter-spacing: 0.01em;
  transition: all 0.2s;
}

.card-admin .card-btn {
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
  border: 1px solid rgba(245, 158, 11, 0.2);
}
.card-admin:hover .card-btn {
  background: #f59e0b;
  color: #000;
}

.card-login .card-btn {
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
  border: 1px solid rgba(99, 102, 241, 0.2);
}
.card-login:hover .card-btn {
  background: #6366f1;
  color: #fff;
}

.card-register .card-btn {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.2);
}
.card-register:hover .card-btn {
  background: #10b981;
  color: #fff;
}

/* Logged-in badges */
.card .logged-badge {
  display: none;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #10b981;
  background: rgba(16, 185, 129, 0.1);
  padding: 4px 12px;
  border-radius: 20px;
}

/* Footer */
.footer {
  margin-top: 48px;
  text-align: center;
  font-size: 12px;
  color: #334155;
}

/* ── Responsive ── */
@media (max-width: 768px) {
  .cards {
    grid-template-columns: 1fr;
    gap: 16px;
  }
  .brand h1 { font-size: 26px; }
  .brand { margin-bottom: 32px; }
  .card { padding: 28px 24px; }
}

@media (max-width: 480px) {
  body { padding: 24px 16px; }
  .brand h1 { font-size: 22px; }
  .card { padding: 24px 20px; }
  .card-icon { width: 52px; height: 52px; font-size: 20px; }
}
</style>
</head>
<body>

<div class="wrapper">
  <div class="brand">
    <h1>TradeZenfy</h1>
    <p>Professional trading platform — Stocks, F&O, and more</p>
  </div>

  <div class="cards">

    <!-- Admin Login -->
    <a href="admin/login.php" class="card card-admin">
      <div class="card-icon">
        <i class="fa fa-shield-alt"></i>
      </div>
      <div class="card-title">Admin Login</div>
      <div class="card-desc">Access the admin panel to manage users, orders, and platform settings.</div>
      <div class="card-btn">Admin Portal <i class="fa fa-arrow-right" style="margin-left:6px;font-size:12px;"></i></div>
    </a>

    <!-- User Login -->
    <a href="login.php" class="card card-login">
      <div class="card-icon">
        <i class="fa fa-user"></i>
      </div>
      <div class="card-title">User Login</div>
      <div class="card-desc">Sign in to your trading account to manage portfolios, orders, and funds.</div>
      <div class="card-btn">Sign In <i class="fa fa-arrow-right" style="margin-left:6px;font-size:12px;"></i></div>
    </a>

    <!-- Register -->
    <a href="register.php" class="card card-register">
      <div class="card-icon">
        <i class="fa fa-user-plus"></i>
      </div>
      <div class="card-title">Create Account</div>
      <div class="card-desc">Open a new trading account in minutes. Start investing in stocks and F&O.</div>
      <div class="card-btn">Register Now <i class="fa fa-arrow-right" style="margin-left:6px;font-size:12px;"></i></div>
    </a>

  </div>

  <div class="footer">
    &copy; <?= date('Y') ?> TradeZenfy. All rights reserved.
  </div>
</div>

</body>
</html>
