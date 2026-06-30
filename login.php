<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/middleware.php';
redirectIfLoggedIn();

$reason = $_GET['reason'] ?? '';
$msg    = $_GET['msg']    ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — TradeZenfy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', sans-serif; background: #0f1117; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
  .card { background: #1a1d27; border: 1px solid #2d3148; border-radius: 16px; padding: 40px; width: 100%; max-width: 420px; }
  .logo { text-align: center; margin-bottom: 28px; }
  .logo h1 { font-size: 26px; font-weight: 700; color: #fff; }
  .logo span { color: #6366f1; }
  .logo p { color: #94a3b8; font-size: 14px; margin-top: 4px; }
  .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
  .alert-error   { background: #2d1b1b; border: 1px solid #7f1d1d; color: #fca5a5; }
  .alert-success { background: #1b2d1b; border: 1px solid #14532d; color: #86efac; }
  .alert-warning { background: #2d2a1b; border: 1px solid #78350f; color: #fcd34d; }
  .form-group { margin-bottom: 18px; }
  label { display: block; font-size: 13px; color: #94a3b8; margin-bottom: 6px; }
  .input-wrap { position: relative; }
  .input-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #4b5563; font-size: 15px; }
  input { width: 100%; padding: 12px 14px 12px 40px; background: #0f1117; border: 1px solid #2d3148; border-radius: 8px; color: #e2e8f0; font-size: 15px; outline: none; transition: border-color .2s; }
  input:focus { border-color: #6366f1; }
  .btn { width: 100%; padding: 13px; background: #6366f1; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background .2s; }
  .btn:hover { background: #4f46e5; }
  .btn:disabled { opacity: .6; cursor: not-allowed; }
  .links { text-align: center; margin-top: 20px; font-size: 13px; color: #94a3b8; }
  .links a { color: #6366f1; text-decoration: none; }
  .spinner { display: none; }
  .btn.loading .spinner { display: inline-block; }
  .btn.loading .btn-text { display: none; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <h1>Your<span>Company</span></h1>
    <p>Indian Stock Market Platform</p>
  </div>

  <?php if ($reason === 'timeout'): ?>
    <div class="alert alert-warning"><i class="fa fa-clock"></i> Your session expired. Please log in again.</div>
  <?php elseif ($reason === 'blocked'): ?>
    <div class="alert alert-error"><i class="fa fa-ban"></i> Your account has been suspended.</div>
  <?php elseif ($msg === 'logged_out'): ?>
    <div class="alert alert-success"><i class="fa fa-check"></i> You have been logged out.</div>
  <?php endif; ?>

  <div id="alert-box" style="display:none" class="alert alert-error"></div>

  <div class="form-group">
    <label>Username or Email</label>
    <div class="input-wrap">
      <i class="fa fa-user"></i>
      <input type="text" id="username" placeholder="Enter username or email" autocomplete="username">
    </div>
  </div>
  <div class="form-group">
    <label>Password</label>
    <div class="input-wrap">
      <i class="fa fa-lock"></i>
      <input type="password" id="password" placeholder="Enter password" autocomplete="current-password">
    </div>
  </div>

  <button class="btn" id="loginBtn" onclick="doLogin()">
    <i class="fa fa-spinner fa-spin spinner"></i>
    <span class="btn-text">Sign In</span>
  </button>

  <div class="links">
    Don't have an account? <a href="register.php">Register</a>
  </div>
</div>

<script>
async function doLogin() {
  const btn      = document.getElementById('loginBtn');
  const alertBox = document.getElementById('alert-box');
  const username = document.getElementById('username').value.trim();
  const password = document.getElementById('password').value;

  alertBox.style.display = 'none';
  if (!username || !password) {
    showAlert('Please enter your username and password.');
    return;
  }

  btn.classList.add('loading');
  btn.disabled = true;

  try {
    const res  = await fetch('api/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password })
    });
    const data = await res.json();

    if (data.success) {
      window.location.href = data.redirect || 'user/dashboard.php';
    } else {
      showAlert(data.message);
      btn.classList.remove('loading');
      btn.disabled = false;
    }
  } catch {
    showAlert('Network error. Please try again.');
    btn.classList.remove('loading');
    btn.disabled = false;
  }
}

function showAlert(msg) {
  const box = document.getElementById('alert-box');
  box.textContent = msg;
  box.style.display = 'block';
}

document.addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });
</script>
</body>
</html>
