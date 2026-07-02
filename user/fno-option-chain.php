<?php
require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
$currentPage = 'fno-option-chain';
$symbol = $_GET['symbol'] ?? 'NIFTY';
$validSymbols = ['RELIANCE','TCS','INFY','HDFCBANK','ICICIBANK','SBIN','BHARTIARTL','ITC','LT','HINDUNILVR','NIFTY','BANKNIFTY'];
if (!in_array($symbol, $validSymbols)) $symbol = 'NIFTY';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($symbol) ?> Option Chain - TradeZenfy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/assets/css/groww-ui.css">
    <link rel="stylesheet" href="../public/assets/css/layout-new.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .main-content { padding: 24px; max-width: 1400px; }
        .symbol-select { padding: 10px 16px; border: 1px solid var(--groww-border); border-radius: 8px; font-size: 14px; outline: none; margin-bottom: 16px; }
        .expiry-tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
        .expiry-tab { padding: 8px 14px; border: 1px solid var(--groww-border); background: white; border-radius: 20px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .expiry-tab.active { background: var(--primary); color: white; border-color: var(--primary); }
        .spot-price { font-size: 24px; font-weight: 700; margin-bottom: 16px; }
        .oc-table { width: 100%; border-collapse: collapse; background: var(--groww-card); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .oc-table th { font-size: 11px; color: var(--groww-text-secondary); text-transform: uppercase; padding: 14px 8px; text-align: center; background: #F9FAFB; }
        .oc-table td { padding: 12px 8px; font-size: 13px; text-align: center; border-top: 1px solid var(--groww-border); }
        .oc-table tr:hover td { background: #F9FAFB; }
        .strike { font-weight: 700; background: #F3F4F6; }
        .call-side { background: #F0FDF4; }
        .put-side { background: #FEF2F2; }
        .positive { color: var(--success); }
        .negative { color: var(--danger); }
        .loading { padding: 40px; text-align: center; color: var(--groww-text-secondary); }
        @media (max-width: 768px) {
            .main-content { padding: 16px; padding-bottom: 72px; }
            .oc-table { font-size: 11px; }
            .oc-table th, .oc-table td { padding: 8px 4px; }
        }
    </style>
<link rel="stylesheet" href="../public/assets/css/mobile-responsive.css">
</head>
<body>

<?php include __DIR__ . '/../includes/user-top-nav.php'; ?>

<div class="main-content">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 28px; margin-bottom: 8px;">Option Chain</h1>
        <p style="color: var(--groww-text-secondary);">Live options data from Angel One</p>
    </div>
    
    <select class="symbol-select" id="symbolSelect" onchange="changeSymbol(this.value)">
        <?php foreach ($validSymbols as $s): ?>
        <option value="<?= $s ?>" <?= $s === $symbol ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
    </select>
    
    <div class="spot-price" id="spotPrice">-</div>
    
    <div class="expiry-tabs" id="expiryTabs"></div>
    
    <div id="loading" class="loading"><i class="fa fa-circle-notch fa-spin"></i> Loading option chain...</div>
    
    <table class="oc-table" id="ocTable" style="display:none;">
        <thead>
            <tr>
                <th colspan="3" class="call-side">CALL</th>
                <th rowspan="2" class="strike">Strike</th>
                <th colspan="3" class="put-side">PUT</th>
            </tr>
            <tr>
                <th class="call-side">LTP</th>
                <th class="call-side">Chg%</th>
                <th class="call-side">OI</th>
                <th class="put-side">LTP</th>
                <th class="put-side">Chg%</th>
                <th class="put-side">OI</th>
            </tr>
        </thead>
        <tbody id="ocBody"></tbody>
    </table>
</div>

<script>
const SYMBOL = <?= json_encode($symbol) ?>;
let expiryDates = [];
let currentExpiry = '';

async function loadOptionChain(symbol, expiry = '') {
    document.getElementById('loading').style.display = 'block';
    document.getElementById('ocTable').style.display = 'none';
    
    try {
        const url = '../api/get-option-chain-live.php?symbol=' + encodeURIComponent(symbol) + (expiry ? '&expiry=' + encodeURIComponent(expiry) : '');
        const res = await fetch(url);
        const data = await res.json();
        
        if (!data.success) {
            document.getElementById('loading').innerHTML = '<i class="fa fa-exclamation-circle"></i> ' + (data.error || 'Failed to load');
            return;
        }
        
        expiryDates = data.expiry_dates || [];
        if (!expiry && expiryDates.length > 0) currentExpiry = expiryDates[0];
        else currentExpiry = expiry;
        
        document.getElementById('spotPrice').textContent = 'Spot: ₹' + (data.spot_price || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        renderExpiryTabs();
        renderChain(data.option_chain || []);
        
        document.getElementById('loading').style.display = 'none';
        document.getElementById('ocTable').style.display = 'table';
    } catch (e) {
        document.getElementById('loading').innerHTML = '<i class="fa fa-exclamation-circle"></i> Error loading option chain';
        console.error(e);
    }
}

function renderExpiryTabs() {
    const container = document.getElementById('expiryTabs');
    container.innerHTML = '';
    expiryDates.forEach(exp => {
        const btn = document.createElement('div');
        btn.className = 'expiry-tab ' + (exp === currentExpiry ? 'active' : '');
        btn.textContent = new Date(exp).toLocaleDateString('en-IN', {day:'2-digit', month:'short'});
        btn.onclick = () => loadOptionChain(SYMBOL, exp);
        container.appendChild(btn);
    });
}

function renderChain(chain) {
    const tbody = document.getElementById('ocBody');
    tbody.innerHTML = '';
    
    if (chain.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;">No option chain data</td></tr>';
        return;
    }
    
    chain.forEach(row => {
        const call = row.CALL || {};
        const put = row.PUT || {};
        const callChg = call.change_percent || 0;
        const putChg = put.change_percent || 0;
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="call-side"><strong>₹${(call.ltp || 0).toFixed(2)}</strong></td>
            <td class="call-side ${callChg >= 0 ? 'positive' : 'negative'}">${callChg >= 0 ? '+' : ''}${callChg.toFixed(2)}%</td>
            <td class="call-side">${(call.oi || 0).toLocaleString('en-IN')}</td>
            <td class="strike">${row.strike.toFixed(2)}</td>
            <td class="put-side"><strong>₹${(put.ltp || 0).toFixed(2)}</strong></td>
            <td class="put-side ${putChg >= 0 ? 'positive' : 'negative'}">${putChg >= 0 ? '+' : ''}${putChg.toFixed(2)}%</td>
            <td class="put-side">${(put.oi || 0).toLocaleString('en-IN')}</td>
        `;
        tbody.appendChild(tr);
    });
}

function changeSymbol(symbol) {
    window.location.href = '?symbol=' + encodeURIComponent(symbol);
}

function toggleSidebar() {
    const sidebar = document.getElementById('userSidebar');
    const toggle = document.getElementById('sidebarToggle');
    if (!sidebar || !toggle) return;
    sidebar.classList.toggle('expanded');
    const isExpanded = sidebar.classList.contains('expanded');
    toggle.innerHTML = isExpanded ? '<i class="fa fa-times"></i>' : '<i class="fa fa-bars"></i>';
}

document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('userSidebar');
    const toggle = document.getElementById('sidebarToggle');
    if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('expanded')) {
        if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
            toggleSidebar();
        }
    }
});

loadOptionChain(SYMBOL);
</script>

</body>
</html>
