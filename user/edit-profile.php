<?php
require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
$db   = getDB();

$fullUser = $db->prepare("SELECT * FROM account_registrations WHERE id = ?");
$fullUser->execute([$user['id']]);
$fullUser = $fullUser->fetch();

$bankDetails = $db->prepare("SELECT * FROM user_payment_details WHERE user_id = ?");
$bankDetails->execute([$user['id']]);
$bankDetails = $bankDetails->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Profile — TradeZenfy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../public/assets/css/groww-ui.css">
<link rel="stylesheet" href="../public/assets/css/layout-new.css">
<style>
.top-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
.section-title{font-size:13px;font-weight:600;color:var(--groww-green);text-transform:uppercase;letter-spacing:.05em;margin:28px 0 14px;border-top:1px solid var(--groww-border);padding-top:22px}
.section-title:first-of-type{border-top:none;padding-top:0;margin-top:0}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
input[readonly]{opacity:.5;cursor:not-allowed}
@media (max-width: 768px) { .top-header { flex-direction: column; } }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/user-top-nav.php'; ?>

<div class="main">
  <div class="top-header">
    <div class="header-left">
      <h1>Edit Profile</h1>
      <p>Manage your account settings and preferences</p>
    </div>
    <div class="header-right">
      <a href="../logout.php" class="btn btn-danger" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:var(--danger);color:white;border:none;border-radius:8px;font-weight:600;font-size:14px;text-decoration:none;cursor:pointer;">
        <i class="fa fa-sign-out-alt"></i> Logout
      </a>
    </div>
  </div>

  <div class="content">

  <div id="alertError" class="alert alert-error"></div>
  <div id="alertSuccess" class="alert alert-success"></div>

  <!-- Personal Info -->
  <div class="section-title"><i class="fa fa-user"></i> Personal Information</div>
  <div class="grid-2">
    <div class="form-group"><label>First Name</label><div class="input-wrap"><i class="fa fa-user"></i><input type="text" id="first_name" value="<?= htmlspecialchars($fullUser['first_name']) ?>"></div></div>
    <div class="form-group"><label>Last Name</label><div class="input-wrap"><i class="fa fa-user"></i><input type="text" id="last_name" value="<?= htmlspecialchars($fullUser['last_name']) ?>"></div></div>
    <div class="form-group"><label>Email (read-only)</label><div class="input-wrap"><i class="fa fa-envelope"></i><input type="email" value="<?= htmlspecialchars($fullUser['email']) ?>" readonly></div></div>
    <div class="form-group"><label>Phone</label><div class="input-wrap"><i class="fa fa-phone"></i><input type="tel" id="phone" value="<?= htmlspecialchars($fullUser['phone']??'') ?>"></div></div>
    <div class="form-group"><label>City</label><div class="input-wrap"><i class="fa fa-city"></i><input type="text" id="city" value="<?= htmlspecialchars($fullUser['city']??'') ?>"></div></div>
    <div class="form-group"><label>Country</label><div class="input-wrap"><i class="fa fa-globe"></i><input type="text" id="country" value="<?= htmlspecialchars($fullUser['country']??'') ?>"></div></div>
  </div>

  <button class="btn" onclick="saveProfile()"><i class="fa fa-save"></i> Save Profile</button>

  <!-- Change Password -->
  <div class="section-title"><i class="fa fa-lock"></i> Change Password</div>
  <div class="grid-2">
    <div class="form-group"><label>Current Password</label><div class="input-wrap"><i class="fa fa-lock"></i><input type="password" id="current_password" placeholder="Current password"></div></div>
    <div class="form-group"><label>New Password</label><div class="input-wrap"><i class="fa fa-key"></i><input type="password" id="new_password" placeholder="Min 8 characters"></div></div>
  </div>
  <button class="btn" onclick="changePassword()"><i class="fa fa-key"></i> Change Password</button>

  <!-- Bank / UPI -->
  <div class="section-title"><i class="fa fa-university"></i> Bank &amp; UPI Details</div>
  <div class="grid-2">
    <div class="form-group"><label>Bank Name</label><div class="input-wrap"><i class="fa fa-university"></i><input type="text" id="bank_name" value="<?= htmlspecialchars($bankDetails['bank_name']??'') ?>" placeholder="e.g. HDFC Bank"></div></div>
    <div class="form-group"><label>Account Number</label><div class="input-wrap"><i class="fa fa-credit-card"></i><input type="text" id="account_number" value="<?= htmlspecialchars($bankDetails['account_number']??'') ?>"></div></div>
    <div class="form-group"><label>IFSC Code</label><div class="input-wrap"><i class="fa fa-code"></i><input type="text" id="ifsc_code" value="<?= htmlspecialchars($bankDetails['ifsc_code']??'') ?>"></div></div>
    <div class="form-group"><label>UPI ID</label><div class="input-wrap"><i class="fa fa-at"></i><input type="text" id="upi_id" value="<?= htmlspecialchars($bankDetails['upi_id']??'') ?>" placeholder="yourname@upi"></div></div>
  </div>
  <button class="btn" onclick="saveBankDetails()"><i class="fa fa-save"></i> Save Bank Details</button>
  </div>
</div>

<script>
function showAlert(type, msg) {
  const e = document.getElementById('alertError');
  const s = document.getElementById('alertSuccess');
  e.style.display = 'none'; s.style.display = 'none';
  window.scrollTo(0,0);
  if(type==='error'){e.textContent=msg;e.style.display='block';}
  else{s.textContent=msg;s.style.display='block';}
}

async function saveProfile() {
  const res  = await fetch('../api/update-profile.php',{
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({
      first_name: document.getElementById('first_name').value.trim(),
      last_name:  document.getElementById('last_name').value.trim(),
      phone:      document.getElementById('phone').value.trim(),
      city:       document.getElementById('city').value.trim(),
      country:    document.getElementById('country').value.trim(),
    })
  });
  const data = await res.json();
  showAlert(data.success?'success':'error', data.message);
}

async function changePassword() {
  const cur = document.getElementById('current_password').value;
  const nw  = document.getElementById('new_password').value;
  if(!cur||!nw){showAlert('error','Both fields are required.');return;}
  if(nw.length<8){showAlert('error','New password must be at least 8 characters.');return;}
  const res  = await fetch('../api/change-password.php',{
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({current_password:cur, new_password:nw})
  });
  const data = await res.json();
  showAlert(data.success?'success':'error', data.message);
  if(data.success){document.getElementById('current_password').value='';document.getElementById('new_password').value='';}
}

async function saveBankDetails() {
  const res  = await fetch('../api/save-payment-details.php',{
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({
      bank_name:      document.getElementById('bank_name').value.trim(),
      account_number: document.getElementById('account_number').value.trim(),
      ifsc_code:      document.getElementById('ifsc_code').value.trim(),
      upi_id:         document.getElementById('upi_id').value.trim(),
    })
  });
  const data = await res.json();
  showAlert(data.success?'success':'error', data.message);
}
</script>
</body>
</html>
