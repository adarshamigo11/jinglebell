<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/middleware.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// FIX: Changed absolute path /admin/index.php to relative index.php
if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$reason = $_GET['reason'] ?? '';
$msg    = $_GET['msg']    ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — TradeZenfy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', sans-serif; background: #0a0c13; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
  .card { background: #12151f; border: 1px solid #1e2235; border-radius: 16px; padding: 40px; width: 100%; max-width: 400px; }
  .logo { text-align: center; margin-bottom: 28px; }
  .logo .badge { display: inline-block; background: #1e2235; color: #94a3b8; font-size: 11px; padding: 3px 10px; border-radius: 20px; margin-bottom: 12px; letter-spacing: .06em; text-transform: uppercase; }
  .logo h1 { font-size: 24px; font-weight: 700; color: #fff; }
  .logo span { color: #6366f1; }
  .alert { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 18px; }
  .alert-error   { background: #2d1b1b; border: 1px solid #7f1d1d; color: #fca5a5; }
  .alert-success { background: #1b2d1b; border: 1px solid #14532d; color: #86efac; }
  .alert-warning { background: #2d2a1b; border: 1px solid #78350f; color: #fcd34d; }
  .form-group { margin-bottom: 16px; }
  label { display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px; }
  .input-wrap { position: relative; }
  .input-wrap i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #4b5563; font-size: 14px; }
  input { width: 100%; padding: 11px 12px 11px 38px; background: #0a0c13; border: 1px solid #1e2235; border-radius: 8px; color: #e2e8f0; font-size: 14px; outline: none; transition: border-color .2s; }
  input:focus { border-color: #6366f1; }
  .btn { width: 100%; padding: 13px; background: #6366f1; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background .2s; }
  .btn:hover { background: #4f46e5; }
  .btn:disabled { opacity: .6; cursor: not-allowed; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="badge"><i class="fa fa-shield-alt"></i> Admin Panel</div>
    <h1>Your<span>Company</span></h1>
  </div>

  <?php if ($reason === 'timeout'): ?>
    <div class="alert alert-warning"><i class="fa fa-clock"></i> Session expired. Please log in again.</div>
  <?php elseif ($msg === 'logged_out'): ?>
    <div class="alert alert-success"><i class="fa fa-check"></i> Logged out successfully.</div>
  <?php endif; ?>

  <div id="alertBox" class="alert alert-error" style="display:none"></div>

  <div class="form-group">
    <label>Admin Username</label>
    <div class="input-wrap"><i class="fa fa-user-shield"></i><input type="text" id="username" placeholder="Admin username" autocomplete="username"></div>
  </div>
  <div class="form-group">
    <label>Password</label>
    <div class="input-wrap"><i class="fa fa-lock"></i><input type="password" id="password" placeholder="Password" autocomplete="current-password"></div>
  </div>

  <button class="btn" id="loginBtn" onclick="doLogin()">
    <i class="fa fa-spinner fa-spin" id="spinner" style="display:none"></i>
    <span id="btnText">Sign In</span>
  </button>
</div>

<script>
async function doLogin() {
  const alertBox = document.getElementById('alertBox');
  const btn      = document.getElementById('loginBtn');
  alertBox.style.display = 'none';

  const username = document.getElementById('username').value.trim();
  const password = document.getElementById('password').value;
  if (!username || !password) { showAlert('Please fill in all fields.'); return; }

  btn.disabled = true;
  document.getElementById('spinner').style.display = 'inline';
  document.getElementById('btnText').textContent = 'Signing in…';

  try {
    // Step back out of /admin folder to find /api folder
    const res  = await fetch('../api/admin-login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password })
    });
    
    const data = await res.json();
    console.log("Server Response:", data); // Helpful for debugging

    if (data.success) {
      // Force redirect to local index.php in the same folder
      window.location.href = 'index.php';
    } else {
      showAlert(data.message || 'Invalid credentials.');
      resetBtn();
    }
  } catch (err) {
    console.error("Login error:", err);
    showAlert('Network error or file not found. Check console.');
    resetBtn();
  }
}

function showAlert(msg) {
  const b = document.getElementById('alertBox');
  b.textContent = msg;
  b.style.display = 'block';
}

function resetBtn() {
  const btn = document.getElementById('loginBtn');
  btn.disabled = false;
  document.getElementById('spinner').style.display = 'none';
  document.getElementById('btnText').textContent = 'Sign In';
}

document.addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });
</script>
</body>
</html>