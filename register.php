<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/middleware.php';
redirectIfLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — TradeZenfy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', sans-serif; background: #0f1117; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 16px; }
  .card { background: #1a1d27; border: 1px solid #2d3148; border-radius: 16px; padding: 40px; width: 100%; max-width: 560px; }
  .logo { text-align: center; margin-bottom: 28px; }
  .logo h1 { font-size: 26px; font-weight: 700; color: #fff; }
  .logo span { color: #6366f1; }
  .logo p { color: #94a3b8; font-size: 14px; margin-top: 4px; }
  .section-title { font-size: 13px; font-weight: 600; color: #6366f1; text-transform: uppercase; letter-spacing: .05em; margin: 24px 0 14px; border-top: 1px solid #2d3148; padding-top: 20px; }
  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  .form-group { margin-bottom: 14px; }
  label { display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px; }
  .input-wrap { position: relative; }
  .input-wrap i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #4b5563; font-size: 14px; }
  input, select, textarea { width: 100%; padding: 11px 12px 11px 38px; background: #0f1117; border: 1px solid #2d3148; border-radius: 8px; color: #e2e8f0; font-size: 14px; outline: none; transition: border-color .2s; font-family: inherit; }
  select, textarea { padding-left: 12px; }
  input:focus, select:focus, textarea:focus { border-color: #6366f1; }
  .btn { width: 100%; padding: 13px; background: #6366f1; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 10px; transition: background .2s; }
  .btn:hover { background: #4f46e5; }
  .btn:disabled { opacity: .6; cursor: not-allowed; }
  .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; display: none; }
  .alert-error   { background: #2d1b1b; border: 1px solid #7f1d1d; color: #fca5a5; }
  .alert-success { background: #1b2d1b; border: 1px solid #14532d; color: #86efac; }
  .links { text-align: center; margin-top: 16px; font-size: 13px; color: #94a3b8; }
  .links a { color: #6366f1; text-decoration: none; }
  .required { color: #f87171; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <h1>Your<span>Company</span></h1>
    <p>Create your trading account</p>
  </div>

  <div id="alertBox" class="alert alert-error"></div>
  <div id="successBox" class="alert alert-success"></div>

  <!-- Personal Info -->
  <div class="section-title"><i class="fa fa-user"></i> Personal Information</div>
  <div class="grid-2">
    <div class="form-group">
      <label>Title</label>
      <select id="title"><option value="">Select</option><option>Mr</option><option>Ms</option><option>Mrs</option><option>Dr</option></select>
    </div>
    <div class="form-group">
      <label>Date of Birth</label>
      <div class="input-wrap"><i class="fa fa-calendar"></i><input type="date" id="dob"></div>
    </div>
  </div>
  <div class="grid-2">
    <div class="form-group">
      <label>First Name <span class="required">*</span></label>
      <div class="input-wrap"><i class="fa fa-user"></i><input type="text" id="first_name" placeholder="First name"></div>
    </div>
    <div class="form-group">
      <label>Last Name <span class="required">*</span></label>
      <div class="input-wrap"><i class="fa fa-user"></i><input type="text" id="last_name" placeholder="Last name"></div>
    </div>
  </div>
  <div class="grid-2">
    <div class="form-group">
      <label>Email <span class="required">*</span></label>
      <div class="input-wrap"><i class="fa fa-envelope"></i><input type="email" id="email" placeholder="you@example.com"></div>
    </div>
    <div class="form-group">
      <label>Phone</label>
      <div class="input-wrap"><i class="fa fa-phone"></i><input type="tel" id="phone" placeholder="+91 XXXXX XXXXX"></div>
    </div>
  </div>

  <!-- Account -->
  <div class="section-title"><i class="fa fa-key"></i> Account Credentials</div>
  <div class="grid-2">
    <div class="form-group">
      <label>Username <span class="required">*</span></label>
      <div class="input-wrap"><i class="fa fa-at"></i><input type="text" id="username" placeholder="e.g. john_doe"></div>
    </div>
    <div class="form-group">
      <label>Password <span class="required">*</span></label>
      <div class="input-wrap"><i class="fa fa-lock"></i><input type="password" id="password" placeholder="Min 8 characters"></div>
    </div>
  </div>

  <!-- Address -->
  <div class="section-title"><i class="fa fa-map-marker-alt"></i> Address</div>
  <div class="form-group">
    <label>Street Address</label>
    <div class="input-wrap"><i class="fa fa-home"></i><input type="text" id="street" placeholder="Street / Area"></div>
  </div>
  <div class="grid-2">
    <div class="form-group">
      <label>City</label>
      <div class="input-wrap"><i class="fa fa-city"></i><input type="text" id="city" placeholder="City"></div>
    </div>
    <div class="form-group">
      <label>Postal Code</label>
      <div class="input-wrap"><i class="fa fa-mail-bulk"></i><input type="text" id="postal_code" placeholder="PIN code"></div>
    </div>
  </div>
  <div class="grid-2">
    <div class="form-group">
      <label>Country</label>
      <div class="input-wrap"><i class="fa fa-globe"></i><input type="text" id="country" placeholder="Country"></div>
    </div>
    <div class="form-group">
      <label>State</label>
      <div class="input-wrap"><i class="fa fa-map"></i><input type="text" id="state_citizenship" placeholder="State"></div>
    </div>
  </div>

  <!-- Financial -->
  <div class="section-title"><i class="fa fa-rupee-sign"></i> Financial Details</div>
  <div class="grid-2">
    <div class="form-group">
      <label>Employment Status</label>
      <select id="employment_status">
        <option value="">Select</option>
        <option>Employed</option><option>Self-Employed</option>
        <option>Business Owner</option><option>Retired</option><option>Student</option>
      </select>
    </div>
    <div class="form-group">
      <label>Annual Income (₹)</label>
      <div class="input-wrap"><i class="fa fa-rupee-sign"></i><input type="number" id="annual_income" placeholder="0.00"></div>
    </div>
  </div>
  <div class="grid-2">
    <div class="form-group">
      <label>Net Worth (₹)</label>
      <div class="input-wrap"><i class="fa fa-chart-bar"></i><input type="number" id="net_worth" placeholder="0.00"></div>
    </div>
    <div class="form-group">
      <label>Initial Deposit (₹)</label>
      <div class="input-wrap"><i class="fa fa-wallet"></i><input type="number" id="initial_deposit" placeholder="0.00"></div>
    </div>
  </div>
  <div class="form-group">
    <label>Source of Wealth</label>
    <div class="input-wrap"><i class="fa fa-coins"></i><input type="text" id="source_of_wealth" placeholder="e.g. Salary, Business, Investments"></div>
  </div>
  <div class="form-group">
    <label>PAN / Tax ID</label>
    <div class="input-wrap"><i class="fa fa-id-card"></i><input type="text" id="tax_id" placeholder="PAN number"></div>
  </div>

  <button class="btn" id="regBtn" onclick="doRegister()">
    <i class="fa fa-spinner fa-spin" id="spinner" style="display:none"></i>
    <span id="btnText">Create Account</span>
  </button>

  <div class="links">Already have an account? <a href="login.php">Sign in</a></div>
</div>

<script>
async function doRegister() {
  const btn    = document.getElementById('regBtn');
  const alert  = document.getElementById('alertBox');
  const succ   = document.getElementById('successBox');
  alert.style.display = 'none';
  succ.style.display  = 'none';

  const payload = {
    title:              document.getElementById('title').value,
    first_name:         document.getElementById('first_name').value.trim(),
    last_name:          document.getElementById('last_name').value.trim(),
    email:              document.getElementById('email').value.trim(),
    username:           document.getElementById('username').value.trim(),
    password:           document.getElementById('password').value,
    dob:                document.getElementById('dob').value,
    phone:              document.getElementById('phone').value.trim(),
    street:             document.getElementById('street').value.trim(),
    city:               document.getElementById('city').value.trim(),
    postal_code:        document.getElementById('postal_code').value.trim(),
    country:            document.getElementById('country').value.trim(),
    state_citizenship:  document.getElementById('state_citizenship').value.trim(),
    employment_status:  document.getElementById('employment_status').value,
    annual_income:      document.getElementById('annual_income').value || null,
    net_worth:          document.getElementById('net_worth').value || null,
    initial_deposit:    document.getElementById('initial_deposit').value || null,
    source_of_wealth:   document.getElementById('source_of_wealth').value.trim(),
    tax_id:             document.getElementById('tax_id').value.trim(),
  };

  btn.disabled = true;
  document.getElementById('spinner').style.display = 'inline';
  document.getElementById('btnText').textContent = 'Creating account…';

  try {
    const res  = await fetch('api/register-account.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();

    if (data.success) {
      succ.textContent = data.message + ' You will be redirected to login.';
      succ.style.display = 'block';
      setTimeout(() => window.location.href = 'login.php', 3000);
    } else {
      alert.textContent = data.message;
      alert.style.display = 'block';
      btn.disabled = false;
      document.getElementById('spinner').style.display = 'none';
      document.getElementById('btnText').textContent = 'Create Account';
    }
  } catch {
    alert.textContent = 'Network error. Please try again.';
    alert.style.display = 'block';
    btn.disabled = false;
    document.getElementById('spinner').style.display = 'none';
    document.getElementById('btnText').textContent = 'Create Account';
  }
}
</script>
</body>
</html>
