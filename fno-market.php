<?php
require_once __DIR__ . '/includes/middleware.php';
$user = requireUser();
$currentPage = 'fno-market';
$symbol = $_GET['symbol'] ?? 'NIFTY';
$validSymbols = ['NIFTY','BANKNIFTY','FINNIFTY','MIDCPNIFTY','RELIANCE','TCS','INFY','HDFCBANK','ICICIBANK','SBIN','BHARTIARTL','ITC','LT','HINDUNILVR'];
if (!in_array($symbol, $validSymbols)) $symbol = 'NIFTY';
$indexSymbols = ['NIFTY','BANKNIFTY','FINNIFTY','MIDCPNIFTY'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($symbol) ?> Option Chain - TradeZenfy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="public/assets/css/groww-ui.css">
    <link rel="stylesheet" href="public/assets/css/layout-new.css">
    <style>
        :root {
            --oc-header-bg: #1a237e;
            --oc-header-text: #ffffff;
            --oc-call-bg: #f0fdf4;
            --oc-put-bg: #fef2f2;
            --oc-strike-bg: #eef2ff;
            --oc-strike-color: #1e40af;
            --oc-row-alt: #fafbfc;
            --oc-border: #e2e8f0;
            --oc-positive: #16a34a;
            --oc-negative: #dc2626;
            --oc-filter-border: #d97706;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .main-content { padding: 20px 24px; max-width: 1600px; margin: 0 auto; }

        /* Filter Bar */
        .oc-filter-bar {
            display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
            padding: 14px 18px; background: #fff; border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 16px;
        }
        .oc-filter-bar label { font-size: 13px; font-weight: 600; color: #444; white-space: nowrap; }
        .oc-filter-bar select {
            padding: 8px 14px; border: 2px solid var(--oc-filter-border); border-radius: 6px;
            font-size: 13px; font-weight: 600; color: #1a1a1a; background: #fff;
            cursor: pointer; outline: none; min-width: 120px;
        }
        .oc-filter-bar select:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(37,99,235,0.15); }
        .oc-filter-sep { font-size: 12px; font-weight: 700; color: #9ca3af; padding: 0 4px; }

        /* Spot Price Row */
        .oc-spot-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 18px; background: #fff; border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 16px; flex-wrap: wrap; gap: 10px;
        }
        .oc-spot-left { display: flex; align-items: baseline; gap: 12px; }
        .oc-spot-label { font-size: 13px; color: #6b7280; }
        .oc-spot-value { font-size: 22px; font-weight: 800; color: #111; }
        .oc-spot-time { font-size: 12px; color: #9ca3af; }
        .oc-spot-right { display: flex; align-items: center; gap: 16px; }
        .oc-spot-right a { font-size: 12px; color: var(--primary); text-decoration: none; }
        .oc-spot-right a:hover { text-decoration: underline; }

        /* Streaming Toggle */
        .oc-stream-toggle { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #6b7280; }
        .oc-stream-toggle .toggle-track {
            width: 36px; height: 20px; border-radius: 10px; background: #d1d5db;
            position: relative; cursor: pointer; transition: background 0.2s;
        }
        .oc-stream-toggle .toggle-track.active { background: var(--success); }
        .oc-stream-toggle .toggle-track .toggle-knob {
            width: 16px; height: 16px; border-radius: 50%; background: #fff;
            position: absolute; top: 2px; left: 2px; transition: left 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        .oc-stream-toggle .toggle-track.active .toggle-knob { left: 18px; }

        /* Option Chain Table */
        .oc-table-wrap {
            background: #fff; border-radius: 10px; overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08); overflow-x: auto;
        }
        .oc-table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        .oc-table thead th {
            background: var(--oc-header-bg); color: var(--oc-header-text);
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            padding: 12px 6px; text-align: center; letter-spacing: 0.3px;
            border-right: 1px solid rgba(255,255,255,0.1); position: sticky; top: 0; z-index: 2;
        }
        .oc-table thead th:last-child { border-right: none; }
        .oc-table thead th.call-hdr { background: #1b5e20; }
        .oc-table thead th.put-hdr { background: #b71c1c; }
        .oc-table thead th.strike-hdr { background: #283593; min-width: 90px; }
        .oc-table tbody td {
            padding: 10px 6px; font-size: 12px; text-align: center;
            border-bottom: 1px solid var(--oc-border); white-space: nowrap;
        }
        .oc-table tbody tr:nth-child(even) td { background: var(--oc-row-alt); }
        .oc-table tbody tr:hover td { background: #eef6ff; }
        .oc-table tbody td.call-cell { background: rgba(240,253,244,0.5); }
        .oc-table tbody td.put-cell { background: rgba(254,242,242,0.5); }
        .oc-table tbody tr:nth-child(even) td.call-cell { background: rgba(240,253,244,0.7); }
        .oc-table tbody tr:nth-child(even) td.put-cell { background: rgba(254,242,242,0.7); }
        .oc-table tbody tr:hover td.call-cell { background: rgba(220,252,231,0.8); }
        .oc-table tbody tr:hover td.put-cell { background: rgba(254,226,226,0.8); }
        td.strike-cell {
            background: var(--oc-strike-bg) !important; font-weight: 700;
            color: var(--oc-strike-color); text-decoration: underline;
            font-size: 13px; cursor: pointer;
        }
        .val-positive { color: var(--oc-positive); font-weight: 600; }
        .val-negative { color: var(--oc-negative); font-weight: 600; }
        .val-ltp { font-weight: 700; }
        .val-oi { font-weight: 500; }
        .val-bid-ask { color: #374151; }
        .oc-loading { padding: 60px 20px; text-align: center; color: #6b7280; font-size: 15px; }
        .oc-no-data { padding: 40px; text-align: center; color: #9ca3af; font-size: 14px; }

        /* Scroll to top */
        .oc-scroll-top {
            position: fixed; bottom: 80px; right: 24px; width: 44px; height: 44px;
            border-radius: 50%; background: #f59e0b; color: #fff; border: none;
            font-size: 18px; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            display: none; z-index: 99; transition: opacity 0.3s;
        }
        .oc-scroll-top:hover { background: #d97706; }
        .oc-scroll-top.visible { display: flex; align-items: center; justify-content: center; }

        @media (max-width: 768px) {
            .main-content { padding: 12px 8px; padding-bottom: 72px; }
            .oc-filter-bar { padding: 10px; gap: 8px; }
            .oc-filter-bar label { font-size: 11px; }
            .oc-filter-bar select { min-width: 90px; font-size: 12px; padding: 6px 8px; }
            .oc-spot-value { font-size: 18px; }
            .oc-table tbody td { padding: 8px 3px; font-size: 11px; }
            /* Mobile: only show LTP, Volume, Chng + Strike */
            .oc-table thead th:nth-child(1),
            .oc-table tbody td:nth-child(1),
            .oc-table thead th:nth-child(2),
            .oc-table tbody td:nth-child(2),
            .oc-table thead th:nth-child(4),
            .oc-table tbody td:nth-child(4),
            .oc-table thead th:nth-child(5),
            .oc-table tbody td:nth-child(5),
            .oc-table thead th:nth-child(7),
            .oc-table tbody td:nth-child(7),
            .oc-table thead th:nth-child(8),
            .oc-table tbody td:nth-child(8),
            .oc-table thead th:nth-child(9),
            .oc-table tbody td:nth-child(9),
            .oc-table thead th:nth-child(10),
            .oc-table tbody td:nth-child(10),
            .oc-table thead th:nth-child(12),
            .oc-table tbody td:nth-child(12),
            .oc-table thead th:nth-child(13),
            .oc-table tbody td:nth-child(13),
            .oc-table thead th:nth-child(14),
            .oc-table tbody td:nth-child(14),
            .oc-table thead th:nth-child(15),
            .oc-table tbody td:nth-child(15),
            .oc-table thead th:nth-child(18),
            .oc-table tbody td:nth-child(18),
            .oc-table thead th:nth-child(20),
            .oc-table tbody td:nth-child(20) { display: none; }
            .oc-table { min-width: 360px; }
        }
    </style>
    <link rel="stylesheet" href="public/assets/css/mobile-responsive.css">
</head>
<body>

<?php include 'includes/user-top-nav.php'; ?>

<div class="main-content">
    <!-- Spot Price Row -->
    <div class="oc-spot-row">
        <div class="oc-spot-left">
            <span class="oc-spot-label">Underlying Index:</span>
            <span class="oc-spot-value" id="spotPrice">-</span>
            <span class="oc-spot-time" id="spotTime"></span>
        </div>
        <div class="oc-spot-right">
            <label>View Options Contracts for:</label>
            <select id="indexSelect2" onchange="changeSymbol(this.value)" style="padding:6px 12px;border:2px solid var(--oc-filter-border);border-radius:6px;font-size:13px;font-weight:600;">
                <?php foreach ($validSymbols as $s): ?>
                <option value="<?= $s ?>" <?= $s === $symbol ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Loading -->
    <div id="loading" class="oc-loading">
        <i class="fa fa-circle-notch fa-spin" style="font-size:24px;margin-bottom:10px;display:block;"></i>
        Loading option chain...
    </div>

    <!-- Option Chain Table -->
    <div class="oc-table-wrap" id="tableWrap" style="display:none;">
        <table class="oc-table" id="ocTable">
            <thead>
                <tr>
                    <th class="call-hdr">OI</th>
                    <th class="call-hdr">Chng in OI</th>
                    <th class="call-hdr">Volume</th>
                    <th class="call-hdr">IV</th>
                    <th class="call-hdr">LTP</th>
                    <th class="call-hdr">Chng</th>
                    <th class="call-hdr">Bid Qty</th>
                    <th class="call-hdr">Bid</th>
                    <th class="call-hdr">Ask</th>
                    <th class="call-hdr">Ask Qty</th>
                    <th class="strike-hdr">STRIKE</th>
                    <th class="put-hdr">Bid Qty</th>
                    <th class="put-hdr">Bid</th>
                    <th class="put-hdr">Ask</th>
                    <th class="put-hdr">Ask Qty</th>
                    <th class="put-hdr">Chng</th>
                    <th class="put-hdr">LTP</th>
                    <th class="put-hdr">IV</th>
                    <th class="put-hdr">Volume</th>
                    <th class="put-hdr">Chng in OI</th>
                    <th class="put-hdr">OI</th>
                </tr>
            </thead>
            <tbody id="ocBody"></tbody>
        </table>
    </div>

    <div id="noData" class="oc-no-data" style="display:none;">No option chain data available for selected index and expiry.</div>
</div>

<!-- Scroll to Top -->
<button class="oc-scroll-top" id="scrollTopBtn" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <i class="fa fa-arrow-up"></i>
</button>

<script>
const SYMBOL = <?= json_encode($symbol) ?>;
let expiryDates = [];
let currentExpiry = '';
let currentChain = [];
let streamOn = false;
let streamInterval = null;

// --- Load option chain ---
async function loadOptionChain(symbol, expiry) {
    expiry = expiry || '';
    document.getElementById('loading').style.display = 'block';
    document.getElementById('tableWrap').style.display = 'none';
    document.getElementById('noData').style.display = 'none';

    try {
        const url = 'api/get-option-chain-live.php?symbol=' + encodeURIComponent(symbol) + (expiry ? '&expiry=' + encodeURIComponent(expiry) : '');
        const res = await fetch(url);
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); } catch(pe) {
            console.error('Invalid JSON response:', text.substring(0, 500));
            document.getElementById('loading').innerHTML = '<i class="fa fa-exclamation-circle" style="font-size:24px;color:#ef4444;margin-bottom:10px;display:block;"></i> Server returned invalid response. Check console.';
            return;
        }

        if (!data.success) {
            document.getElementById('loading').innerHTML = '<i class="fa fa-exclamation-circle" style="font-size:24px;color:#ef4444;margin-bottom:10px;display:block;"></i>' + (data.error || 'Failed to load');
            return;
        }

        expiryDates = data.expiry_dates || [];
        currentExpiry = expiry || (expiryDates.length > 0 ? expiryDates[0] : '');
        currentChain = data.option_chain || [];

        updateSpotPrice(symbol, data.spot_price);
        renderChain(currentChain);

        document.getElementById('loading').style.display = 'none';
        if (currentChain.length > 0) {
            document.getElementById('tableWrap').style.display = 'block';
        } else {
            document.getElementById('noData').style.display = 'block';
        }
    } catch (e) {
        document.getElementById('loading').innerHTML = '<i class="fa fa-exclamation-circle" style="font-size:24px;color:#ef4444;margin-bottom:10px;display:block;"></i> Error: ' + e.message;
        console.error(e);
    }
}

function updateSpotPrice(symbol, price) {
    const spot = price || 0;
    document.getElementById('spotPrice').textContent = symbol + ' ' + spot.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
    const now = new Date();
    document.getElementById('spotTime').textContent = 'As on ' + now.toLocaleDateString('en-IN', {day:'2-digit', month:'short', year:'numeric'}) + ' ' + now.toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:false}) + ' IST';
}

function renderExpiryDropdown() {
    const sel = document.getElementById('expirySelect');
    sel.innerHTML = '';
    expiryDates.forEach((exp, i) => {
        const opt = document.createElement('option');
        opt.value = exp;
        const d = new Date(exp);
        opt.textContent = d.toLocaleDateString('en-IN', {day:'2-digit', month:'short', year:'numeric'});
        if (exp === currentExpiry) opt.selected = true;
        sel.appendChild(opt);
    });
}

function renderStrikeFilter() {
    const sel = document.getElementById('strikeFilter');
    sel.innerHTML = '<option value="">All</option>';
    currentChain.forEach(row => {
        const opt = document.createElement('option');
        opt.value = row.strike;
        opt.textContent = Number(row.strike).toLocaleString('en-IN', {minimumFractionDigits:2});
        sel.appendChild(opt);
    });
}

function renderChain(chain, filterStrike) {
    const tbody = document.getElementById('ocBody');
    tbody.innerHTML = '';

    if (chain.length === 0) {
        tbody.innerHTML = '<tr><td colspan="21" style="text-align:center;padding:30px;color:#9ca3af;">No data</td></tr>';
        return;
    }

    chain.forEach(row => {
        if (filterStrike && row.strike != filterStrike) return;

        const call = row.CALL || {};
        const put = row.PUT || {};

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="call-cell val-oi">${fmtNum(call.oi)}</td>
            <td class="call-cell ${chgClass(call.oi_change)}">${fmtSigned(call.oi_change)}</td>
            <td class="call-cell">${fmtNum(call.volume)}</td>
            <td class="call-cell">${fmtDec(call.iv)}</td>
            <td class="call-cell val-ltp">${fmtDec(call.ltp)}</td>
            <td class="call-cell ${chgClass(call.change)}">${fmtSigned(call.change)}</td>
            <td class="call-cell val-bid-ask">${fmtNum(call.bid_qty)}</td>
            <td class="call-cell val-bid-ask">${fmtDec(call.bid)}</td>
            <td class="call-cell val-bid-ask">${fmtDec(call.ask)}</td>
            <td class="call-cell val-bid-ask">${fmtNum(call.ask_qty)}</td>
            <td class="strike-cell">${Number(row.strike).toLocaleString('en-IN',{minimumFractionDigits:2})}</td>
            <td class="put-cell val-bid-ask">${fmtNum(put.bid_qty)}</td>
            <td class="put-cell val-bid-ask">${fmtDec(put.bid)}</td>
            <td class="put-cell val-bid-ask">${fmtDec(put.ask)}</td>
            <td class="put-cell val-bid-ask">${fmtNum(put.ask_qty)}</td>
            <td class="put-cell ${chgClass(put.change)}">${fmtSigned(put.change)}</td>
            <td class="put-cell val-ltp">${fmtDec(put.ltp)}</td>
            <td class="put-cell">${fmtDec(put.iv)}</td>
            <td class="put-cell">${fmtNum(put.volume)}</td>
            <td class="put-cell ${chgClass(put.oi_change)}">${fmtSigned(put.oi_change)}</td>
            <td class="put-cell val-oi">${fmtNum(put.oi)}</td>
        `;
        tbody.appendChild(tr);
    });
}

// --- Formatters ---
function fmtNum(v) { return v ? Number(v).toLocaleString('en-IN') : '-'; }
function fmtDec(v) { return v ? Number(v).toFixed(2) : '-'; }
function fmtSigned(v) {
    if (!v && v !== 0) return '-';
    const n = Number(v);
    if (n === 0) return '0';
    return (n > 0 ? '+' : '') + n.toLocaleString('en-IN');
}
function chgClass(v) {
    if (!v && v !== 0) return '';
    return Number(v) >= 0 ? 'val-positive' : 'val-negative';
}

// --- Actions ---
function changeSymbol(sym) { window.location.href = '?symbol=' + encodeURIComponent(sym); }
function changeExpiry(exp) { loadOptionChain(SYMBOL, exp); }
function filterStrike(val) { renderChain(currentChain, val || null); }

function toggleStream() {
    streamOn = !streamOn;
    const track = document.getElementById('streamToggle');
    const label = document.getElementById('streamLabel');
    if (streamOn) {
        track.classList.add('active');
        label.textContent = 'On';
        streamInterval = setInterval(() => loadOptionChain(SYMBOL, currentExpiry), 10000);
    } else {
        track.classList.remove('active');
        label.textContent = 'Off';
        clearInterval(streamInterval);
    }
}

function downloadCSV() {
    if (!currentChain.length) return;
    let csv = 'CALL OI,CALL OI Chng,CALL Volume,CALL IV,CALL LTP,CALL Chng,CALL BidQty,CALL Bid,CALL Ask,CALL AskQty,STRIKE,PUT BidQty,PUT Bid,PUT Ask,PUT AskQty,PUT Chng,PUT LTP,PUT IV,PUT Volume,PUT OI Chng,PUT OI\n';
    currentChain.forEach(r => {
        const c = r.CALL || {}, p = r.PUT || {};
        csv += [c.oi,c.oi_change,c.volume,c.iv,c.ltp,c.change,c.bid_qty,c.bid,c.ask,c.ask_qty,r.strike,p.bid_qty,p.bid,p.ask,p.ask_qty,p.change,p.ltp,p.iv,p.volume,p.oi_change,p.oi].map(v => v ?? '-').join(',') + '\n';
    });
    const blob = new Blob([csv], {type:'text/csv'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = SYMBOL + '_option_chain_' + currentExpiry + '.csv';
    a.click();
}

// --- Sidebar & scroll ---
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
        if (!sidebar.contains(e.target) && !toggle.contains(e.target)) toggleSidebar();
    }
});

window.addEventListener('scroll', function() {
    const btn = document.getElementById('scrollTopBtn');
    btn.classList.toggle('visible', window.scrollY > 300);
});

// --- Init ---
document.addEventListener('DOMContentLoaded', function() { loadOptionChain(SYMBOL); });
</script>

</body>
</html>
