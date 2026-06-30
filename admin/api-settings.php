<?php
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
$db    = getDB();

// Fetch current API settings
$angelOne = $db->query("SELECT * FROM api_settings WHERE provider = 'angel_one'")->fetch();
$yahooFinance = $db->query("SELECT * FROM api_settings WHERE provider = 'yahoo_finance'")->fetch();

// Fetch provider preferences
$prefs = [];
$stmt = $db->query("SELECT asset_type, provider, is_enabled FROM data_provider_preferences ORDER BY asset_type");
while ($row = $stmt->fetch()) {
    $prefs[$row['asset_type']] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>API Settings — TradeZenfy Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F5F7FA; color: #1A1A1A; min-height: 100vh; overflow-x: hidden; }

/* ── Top Header ── */
.top-header { background: #fff; border-bottom: 1px solid #E5E7EB; padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.top-header .logo { font-size: 20px; font-weight: 700; color: #1A1A1A; }
.top-header .logo span { color: #2563EB; }
.top-header .logo small { font-size: 11px; color: #6B7280; letter-spacing: .06em; text-transform: uppercase; margin-left: 10px; }
.header-right { display: flex; align-items: center; gap: 16px; }
.admin-badge { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #6B7280; }
.admin-badge .avatar { width: 32px; height: 32px; border-radius: 50%; background: #2563EB; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #fff; }
.logout-btn { padding: 7px 16px; background: #FEF2F2; color: #EF4444; border: 1px solid #FECACA; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: background .2s; }
.logout-btn:hover { background: #FEE2E2; }

/* ── Main ── */
.main { max-width: 1200px; margin: 0 auto; padding: 28px; }
.page-header { margin-bottom: 28px; display: flex; align-items: center; justify-content: space-between; }
.page-header h1 { font-size: 22px; font-weight: 600; color: #1A1A1A; }
.back-btn { padding: 8px 16px; background: #F3F4F6; color: #6B7280; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
.back-btn:hover { background: #E5E7EB; }

/* ── Cards ── */
.card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px; }
.card-header { padding: 18px 20px; border-bottom: 1px solid #E5E7EB; display: flex; align-items: center; justify-content: space-between; }
.card-header h3 { font-size: 15px; font-weight: 600; color: #1A1A1A; display: flex; align-items: center; gap: 8px; }
.card-body { padding: 20px; }

/* ── Form ── */
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; color: #6B7280; margin-bottom: 6px; font-weight: 500; }
.form-group input { width: 100%; padding: 10px 12px; background: #F5F7FA; border: 1px solid #E5E7EB; border-radius: 8px; color: #1A1A1A; font-size: 14px; outline: none; }
.form-group input:focus { border-color: #2563EB; background: #fff; }
.form-group small { display: block; margin-top: 4px; font-size: 12px; color: #9CA3AF; }

/* ── Buttons ── */
.btn { padding: 10px 20px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all .2s; }
.btn-primary { background: #2563EB; color: #fff; }
.btn-primary:hover { background: #1D4ED8; }
.btn-secondary { background: #F3F4F6; color: #6B7280; }
.btn-secondary:hover { background: #E5E7EB; }
.btn-success { background: #10B981; color: #fff; }
.btn-success:hover { background: #059669; }

/* ── Toggle Switch ── */
.toggle-container { display: flex; align-items: center; justify-content: space-between; padding: 16px 0; border-bottom: 1px solid #F3F4F6; }
.toggle-container:last-child { border-bottom: none; }
.toggle-label { display: flex; flex-direction: column; gap: 2px; }
.toggle-label .title { font-size: 14px; font-weight: 600; color: #1A1A1A; }
.toggle-label .desc { font-size: 12px; color: #9CA3AF; }
.toggle-switch { position: relative; width: 50px; height: 26px; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 34px; }
.toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
input:checked + .toggle-slider { background-color: #2563EB; }
input:checked + .toggle-slider:before { transform: translateX(24px); }

/* ── Provider Selector ── */
.provider-selector { display: flex; gap: 12px; margin-top: 8px; }
.provider-option { flex: 1; padding: 12px; border: 2px solid #E5E7EB; border-radius: 8px; cursor: pointer; transition: all .2s; text-align: center; }
.provider-option:hover { border-color: #2563EB; }
.provider-option.active { border-color: #2563EB; background: #EFF6FF; }
.provider-option .name { font-size: 13px; font-weight: 600; color: #1A1A1A; }
.provider-option .status { font-size: 11px; color: #6B7280; margin-top: 2px; }

/* ── Alert ── */
.alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; display: none; white-space: pre-wrap; }
.alert-success { background: #D1FAE5; border: 1px solid #A7F3D0; color: #059669; }
.alert-error { background: #FEE2E2; border: 1px solid #FECACA; color: #DC2626; }

/* ── Status Badge ── */
.status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.status-active { background: #D1FAE5; color: #059669; }
.status-inactive { background: #F3F4F6; color: #6B7280; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .top-header { padding: 12px 16px; }
  .top-header .logo small { display: none; }
  .main { padding: 20px 16px; }
  .page-header { flex-direction: column; align-items: flex-start; gap: 12px; }
  .card-body { padding: 16px; }
  .provider-selector { flex-direction: column; }
}
</style>
</head>
<body>

<!-- ── Top Header ── -->
<div class="top-header">
  <div class="logo">TradeZenfy<small>Admin Panel</small></div>
  <div class="header-right">
    <div class="admin-badge">
      <div class="avatar"><?= strtoupper(substr($admin['name'], 0, 1)) ?></div>
      <span><?= htmlspecialchars($admin['name']) ?></span>
    </div>
    <a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out-alt"></i> Logout</a>
  </div>
</div>

<!-- ── Main ── -->
<div class="main">
  <div class="page-header">
    <h1><i class="fa fa-key" style="color:#2563EB;margin-right:8px"></i>API Settings</h1>
    <a href="index.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
  </div>

  <div id="alertBox" class="alert"></div>

  <!-- Angel One SmartAPI Settings -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa fa-plug" style="color:#10B981"></i>Angel One SmartAPI</h3>
      <span class="status-badge <?= $angelOne && $angelOne['is_active'] ? 'status-active' : 'status-inactive' ?>">
        <?= $angelOne && $angelOne['is_active'] ? 'Active' : 'Inactive' ?>
      </span>
    </div>
    <div class="card-body">
      <form id="angelOneForm">
        <div class="form-group">
          <label>API Key *</label>
          <input type="text" id="apiKey" value="<?= htmlspecialchars($angelOne['api_key'] ?? '') ?>" placeholder="Enter your Angel One API Key">
          <small>Get this from your Angel One SmartAPI developer account</small>
        </div>
        <div class="form-group">
          <label>Client ID *</label>
          <input type="text" id="clientId" value="<?= htmlspecialchars($angelOne['client_id'] ?? '') ?>" placeholder="e.g., AB1234">
          <small>Your Angel One trading account client code</small>
        </div>
        <div class="form-group">
          <label>Password *</label>
          <input type="password" id="password" value="<?= htmlspecialchars($angelOne['password'] ?? '') ?>" placeholder="Enter your Angel One password">
          <small>Your Angel One trading account password</small>
        </div>
        <div class="form-group">
          <label>TOTP Secret (Optional)</label>
          <input type="text" id="totpSecret" value="<?= htmlspecialchars($angelOne['totp_secret'] ?? '') ?>" placeholder="Enter TOTP secret if 2FA enabled">
          <small>Only required if you have 2FA enabled on your Angel One account</small>
        </div>
        <div style="display:flex;gap:10px;margin-top:20px">
          <button type="button" class="btn btn-primary" onclick="saveAngelOne()"><i class="fa fa-save"></i> Save Credentials</button>
          <button type="button" class="btn btn-success" onclick="testAngelOne()"><i class="fa fa-vial"></i> Test Connection</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Data Provider Preferences -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa fa-exchange-alt" style="color:#2563EB"></i>Data Provider Preferences</h3>
    </div>
    <div class="card-body">
      <p style="font-size:13px;color:#6B7280;margin-bottom:16px">Choose which API provider to use for each asset type. Toggle between Yahoo Finance and Angel One SmartAPI.</p>
      
      <form id="preferencesForm">
        <?php
        $assetTypes = [
            'stocks' => ['title' => 'Indian Stocks (NSE/BSE)', 'desc' => 'Real-time stock prices for NSE and BSE', 'icon' => 'fa-chart-line'],
            'commodities' => ['title' => 'Commodities (MCX)', 'desc' => 'Gold, Silver, Crude Oil, Natural Gas', 'icon' => 'fa-coins'],
            'indices' => ['title' => 'Indices', 'desc' => 'Nifty 50, Bank Nifty, Sensex', 'icon' => 'fa-chart-bar'],
            'crypto' => ['title' => 'Cryptocurrency', 'desc' => 'Bitcoin, Ethereum, etc. (Yahoo Finance only)', 'icon' => 'fa-bitcoin'],
            'forex' => ['title' => 'Forex', 'desc' => 'Currency pairs (Yahoo Finance only)', 'icon' => 'fa-dollar-sign']
        ];
        
        foreach ($assetTypes as $type => $info):
            $pref = $prefs[$type] ?? ['provider' => 'yahoo_finance', 'is_enabled' => 1];
            $isAngelOne = $pref['provider'] === 'angel_one';
            $isDisabled = in_array($type, ['crypto', 'forex']); // These only support Yahoo Finance
        ?>
        <div class="toggle-container">
          <div class="toggle-label">
            <div class="title"><i class="fa <?= $info['icon'] ?>" style="margin-right:6px;color:#2563EB"></i><?= $info['title'] ?></div>
            <div class="desc"><?= $info['desc'] ?></div>
            <div class="provider-selector">
              <div class="provider-option <?= !$isAngelOne ? 'active' : '' ?>" onclick="selectProvider('<?= $type ?>', 'yahoo_finance', this)">
                <div class="name">Yahoo Finance</div>
                <div class="status"><?= !$isAngelOne ? 'Selected' : 'Available' ?></div>
              </div>
              <div class="provider-option <?= $isAngelOne ? 'active' : '' ?> <?= $isDisabled ? 'disabled' : '' ?>" 
                   onclick="<?= $isDisabled ? '' : "selectProvider('$type', 'angel_one', this)" ?>"
                   style="<?= $isDisabled ? 'opacity:0.5;cursor:not-allowed' : '' ?>">
                <div class="name">Angel One</div>
                <div class="status"><?= $isAngelOne ? 'Selected' : ($isDisabled ? 'Not Supported' : 'Available') ?></div>
              </div>
            </div>
          </div>
          <input type="hidden" name="provider_<?= $type ?>" id="provider_<?= $type ?>" value="<?= $pref['provider'] ?>">
        </div>
        <?php endforeach; ?>
        
        <div style="margin-top:20px">
          <button type="button" class="btn btn-primary" onclick="savePreferences()"><i class="fa fa-save"></i> Save Preferences</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function showAlert(message, type) {
    const alert = document.getElementById('alertBox');
    alert.className = 'alert alert-' + type;
    alert.textContent = message;
    alert.style.display = 'block';
    setTimeout(() => { alert.style.display = 'none'; }, 5000);
}

function saveAngelOne() {
    const data = {
        angel_one: {
            api_key: document.getElementById('apiKey').value,
            client_id: document.getElementById('clientId').value,
            password: document.getElementById('password').value,
            totp_secret: document.getElementById('totpSecret').value,
            is_active: 1
        }
    };
    
    fetch('../api/admin-save-api-settings.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showAlert('Angel One credentials saved!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('Error: ' + res.message, 'error');
        }
    })
    .catch(err => showAlert('Network error: ' + err.message, 'error'));
}

function testAngelOne() {
    const data = {
        provider: 'angel_one',
        api_key: document.getElementById('apiKey').value,
        client_id: document.getElementById('clientId').value,
        password: document.getElementById('password').value,
        totp_secret: document.getElementById('totpSecret').value
    };
    
    if (!data.api_key || !data.client_id || !data.password) {
        showAlert('Please enter API credentials first', 'error');
        return;
    }
    
    showAlert('Testing connection...', 'success');
    
    fetch('../api/admin-test-api-connection.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showAlert('✓ ' + res.message, 'success');
        } else {
            let msg = '✗ ' + res.message;
            if (res.debug) {
                msg += '\n\n--- Debug Info ---';
                msg += '\nServer Time: ' + (res.debug.server_time || 'N/A');
                msg += '\nTimezone: ' + (res.debug.server_timezone || 'N/A');
                msg += '\nTOTP Secret: ' + (res.debug.totp_secret_provided || 'N/A');
                msg += '\nValid Base32: ' + (res.debug.valid_base32 || 'N/A');
                msg += '\nClient ID: ' + (res.debug.client_id || 'N/A');
            }
            showAlert(msg, 'error');
        }
    })
    .catch(err => showAlert('Network error: ' + err.message, 'error'));
}

function selectProvider(assetType, provider, element) {
    // Update UI
    const container = element.parentElement;
    container.querySelectorAll('.provider-option').forEach(opt => opt.classList.remove('active'));
    element.classList.add('active');
    
    // Update hidden input
    document.getElementById('provider_' + assetType).value = provider;
    
    // Update status text
    container.querySelectorAll('.provider-option').forEach(opt => {
        const statusEl = opt.querySelector('.status');
        if (opt.classList.contains('active')) {
            statusEl.textContent = 'Selected';
        } else {
            statusEl.textContent = 'Available';
        }
    });
}

function savePreferences() {
    const preferences = {};
    const assetTypes = ['stocks', 'commodities', 'indices', 'crypto', 'forex'];
    
    assetTypes.forEach(type => {
        preferences[type] = {
            provider: document.getElementById('provider_' + type).value,
            is_enabled: 1
        };
    });
    
    fetch('../api/admin-save-api-settings.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({preferences})
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showAlert('Preferences saved successfully!', 'success');
        } else {
            showAlert('Error: ' + res.message, 'error');
        }
    })
    .catch(err => showAlert('Network error: ' + err.message, 'error'));
}
</script>

</body>
</html>
