<?php
require_once __DIR__ . '/includes/middleware.php';
$user = requireUser();
$currentPage = 'index-detail';
$db   = getDB();

$rawSymbol = $_GET['symbol'] ?? '';
if (!$rawSymbol) { header('Location: user/dashboard.php'); exit; }

// Index name map: DB symbol => Display name
$indexNames = [
    '^NSEI' => 'Nifty 50',
    '^NSEBANK' => 'Nifty Bank',
    '^BSESN' => 'BSE Sensex',
    '^CNXIT' => 'Nifty IT',
    '^CNXFIN' => 'Nifty Financial Services',
    '^NSEMDCP100' => 'Nifty Midcap 100',
    '^NSESMLCP100' => 'Nifty Smallcap 100',
    '^CNXAUTO' => 'Nifty Auto',
    '^CNXPHARMA' => 'Nifty Pharma',
    '^CNXMETAL' => 'Nifty Metal',
];

// Reverse map: Display name => DB symbol
$reverseMap = array_flip($indexNames);

// Resolve symbol: if user passed display name like "Nifty 50", convert to "^NSEI"
$symbol = $reverseMap[$rawSymbol] ?? $rawSymbol;
$indexName = $indexNames[$symbol] ?? $rawSymbol;

// TradingView symbol mapping
$tvSymbols = [
    '^NSEI'         => 'NSE:NIFTY',
    '^NSEBANK'      => 'NSE:BANKNIFTY',
    '^BSESN'        => 'BSE:SENSEX',
    '^CNXIT'        => 'NSE:CNXIT',
    '^CNXFIN'       => 'NSE:CNXFIN',
    '^NSEMDCP100'   => 'NSE:NIFTYMIDCAP100',
    '^NSESMLCP100'  => 'NSE:NIFTYSMLCAP100',
    '^CNXAUTO'      => 'NSE:CNXAUTO',
    '^CNXPHARMA'    => 'NSE:CNXPHARMA',
    '^CNXMETAL'     => 'NSE:CNXMETAL',
];
$tvSymbol = $tvSymbols[$symbol] ?? $symbol;

// Get stock_id for this index from DB
$stockStmt = $db->prepare("SELECT id, symbol, name, exchange, ltp, previous_close, sector FROM stocks WHERE symbol = ? AND is_active = 1");
$stockStmt->execute([$symbol]);
$indexStock = $stockStmt->fetch();
$indexStockId = $indexStock ? (int)$indexStock['id'] : 0;
$ltp = $indexStock ? (float)$indexStock['ltp'] : 0;
$prev = $indexStock ? (float)$indexStock['previous_close'] : 0;
$chg = $prev > 0 ? round((($ltp - $prev) / $prev) * 100, 2) : 0;
$chgVal = round($ltp - $prev, 2);
$isPos = $chg >= 0;

// User's holding for this index
$holding = null;
if ($indexStockId) {
    $holdStmt = $db->prepare("SELECT * FROM user_holdings WHERE user_id = ? AND stock_id = ?");
    $holdStmt->execute([$user['id'], $indexStockId]);
    $holding = $holdStmt->fetch();
}

// Recent user orders for this index
$myOrders = [];
if ($indexStockId) {
    $orderStmt = $db->prepare("SELECT * FROM user_orders WHERE user_id = ? AND stock_id = ? ORDER BY created_at DESC LIMIT 10");
    $orderStmt->execute([$user['id'], $indexStockId]);
    $myOrders = $orderStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($indexName) ?> — TradeZenfy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="public/assets/css/groww-ui.css">
<link rel="stylesheet" href="public/assets/css/layout-new.css">
<style>
/* Time Range Buttons */
.time-btn {
  padding: 8px 16px;
  border: none;
  background: transparent;
  color: var(--groww-text-secondary);
  font-size: 13px;
  font-weight: 600;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}
.time-btn:hover { background: var(--groww-hover); color: var(--groww-text); }
.time-btn.active { background: rgba(0, 208, 156, 0.1); color: var(--groww-green); }

/* Chart Canvas */
#indexChart { width: 100%; height: 100%; }

/* Back link */
.back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--groww-text-secondary); text-decoration: none; font-size: 14px; margin-bottom: 20px; padding: 8px 16px; background: var(--groww-card); border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.back-link:hover { color: var(--groww-green); background: var(--groww-hover); }

/* Index hero - matches stock-hero */
.index-hero { background: var(--groww-card); border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.hero-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.hero-sym { font-size: 28px; font-weight: 700; color: var(--groww-text); }
.hero-name { font-size: 14px; color: var(--groww-text-secondary); margin-top: 4px; }
.hero-badges { display: flex; gap: 8px; margin-top: 10px; }
.badge-sm { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; background: var(--groww-hover); color: var(--groww-text-secondary); font-weight: 500; }
.ltp-block { text-align: right; }
.ltp-big { font-size: 32px; font-weight: 700; color: var(--groww-text); }
.ltp-change { font-size: 15px; font-weight: 600; margin-top: 4px; }
.hero-ohlc { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; margin-top: 20px; border-top: 1px solid var(--groww-border); padding-top: 18px; }
.ohlc-item { text-align: center; border-right: 1px solid var(--groww-border); }
.ohlc-item:last-child { border-right: none; }
.ohlc-label { font-size: 11px; color: var(--groww-text-secondary); text-transform: uppercase; margin-bottom: 4px; }
.ohlc-val { font-size: 15px; font-weight: 600; color: var(--groww-text); }

/* Content grid */
.content-grid { display: grid; grid-template-columns: 1fr 360px; gap: 24px; }
@media (max-width: 1100px) { .content-grid { grid-template-columns: 1fr; } }

/* Holding card */
.holding-card { background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%); border: 1px solid #BBF7D0; border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; }
.holding-card .h-sym { font-size: 13px; color: var(--groww-green); font-weight: 600; }
.holding-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
.h-item .h-label { font-size: 11px; color: var(--groww-text-secondary); text-transform: uppercase; }
.h-item .h-val   { font-size: 14px; font-weight: 600; color: var(--groww-text); }

/* Order form */
.tab-row { display: flex; margin-bottom: 16px; background: var(--groww-hover); border-radius: 8px; overflow: hidden; }
.tab-btn { flex: 1; padding: 12px; background: none; color: var(--groww-text-secondary); font-size: 14px; font-weight: 600; cursor: pointer; border: none; transition: all .15s; }
.tab-btn.buy.active  { background: var(--groww-green); color: white; }
.tab-btn.sell.active { background: var(--groww-red); color: white; }
.form-group { margin-bottom: 14px; }
label { display: block; font-size: 13px; color: var(--groww-text-secondary); margin-bottom: 5px; font-weight: 500; }
.input-wrap { position: relative; }
.input-wrap i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--groww-text-secondary); }
input, select { width: 100%; padding: 11px 12px 11px 38px; background: var(--groww-bg); border: 1px solid var(--groww-border); border-radius: 8px; color: var(--groww-text); font-size: 14px; outline: none; transition: border-color .2s; font-family: inherit; }
select { padding-left: 12px; }
input:focus, select:focus { border-color: var(--groww-green); }
.total-row { background: var(--groww-hover); border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
.total-row .t-label { font-size: 12px; color: var(--groww-text-secondary); }
.total-row .t-val   { font-size: 16px; font-weight: 700; color: var(--groww-text); }
.place-btn { width: 100%; padding: 13px; border: none; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer; transition: opacity .15s; }
.place-btn.buy  { background: var(--groww-green); color: white; }
.place-btn.sell { background: var(--groww-red); color: white; }
.place-btn:hover { opacity: .85; }
.place-btn:disabled { opacity: .5; cursor: not-allowed; }
.balance-info { font-size: 12px; color: var(--groww-text-secondary); margin-top: 10px; text-align: center; }

/* Orders table */
table { width: 100%; border-collapse: collapse; }
th { font-size: 11px; color: var(--groww-text-secondary); text-transform: uppercase; letter-spacing: .05em; padding: 12px 16px; text-align: left; background: var(--groww-bg); font-weight: 600; }
td { padding: 12px 16px; font-size: 13px; border-top: 1px solid var(--groww-border); color: var(--groww-text); }
tr:hover td { background: var(--groww-hover); }
.b-pending  { background: #FEF3C7; color: #D97706; display:inline-flex; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.b-executed { background: #DCFCE7; color: var(--groww-green); display:inline-flex; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.b-rejected, .b-cancelled { background: #FEE2E2; color: var(--groww-red); display:inline-flex; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.cancel-btn { background: none; border: 1px solid var(--groww-border); color: var(--groww-red); font-size: 11px; padding: 4px 10px; border-radius: 6px; cursor: pointer; font-weight: 500; }
.cancel-btn:hover { background: #FEE2E2; border-color: var(--groww-red); }
.alert { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 12px; display: none; font-weight: 500; }
.alert-error   { background: #FEE2E2; border: 1px solid #FECACA; color: var(--groww-red); }
.alert-success { background: #DCFCE7; border: 1px solid #BBF7D0; color: var(--groww-green); }
.empty { padding: 24px; text-align: center; color: var(--groww-text-secondary); font-size: 13px; }
.pos { color: var(--groww-green); } .neg { color: var(--groww-red); }

/* Performance */
.perf-section { background: var(--groww-card); border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.perf-title { font-size: 16px; font-weight: 700; color: var(--groww-text); margin-bottom: 16px; }
.perf-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; }
.perf-item { padding: 16px; border-radius: 10px; background: var(--groww-hover); text-align: center; }
.perf-label { font-size: 12px; color: var(--groww-text-secondary); margin-bottom: 6px; }
.perf-value { font-size: 18px; font-weight: 700; }

/* Canvas rendering optimization */
canvas { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; text-rendering: optimizeLegibility; }

/* Mobile Responsive */
@media (max-width: 768px) {
  .main-content { padding: 12px; padding-bottom: 72px; }
  .content-grid { grid-template-columns: 1fr; gap: 16px; }
  .back-link { font-size: 13px; padding: 6px 12px; margin-bottom: 12px; }
  .index-hero { padding: 16px; margin-bottom: 16px; }
  .hero-top { flex-direction: column; gap: 12px; }
  .ltp-block { text-align: left; width: 100%; }
  .hero-sym { font-size: 22px; }
  .hero-name { font-size: 13px; }
  .ltp-big { font-size: 26px; }
  .ltp-change { font-size: 14px; }
  .hero-badges { flex-wrap: wrap; }
  .badge-sm { font-size: 10px; padding: 3px 10px; }
  .hero-ohlc { grid-template-columns: repeat(2, 1fr); gap: 0; margin-top: 16px; padding-top: 12px; }
  .ohlc-item:nth-child(3) { border-right: none; }
  .ohlc-item { padding: 8px 4px; }
  .ohlc-label { font-size: 10px; }
  .ohlc-val { font-size: 13px; }
  .card { margin-bottom: 16px !important; overflow: hidden; }
  .card-header { padding: 14px 16px; }
  #chart-price { font-size: 26px !important; }
  #chart-change { font-size: 14px !important; }
  #chart-date { font-size: 12px !important; }
  .chart-container { height: 280px !important; }
  #indexChart { width: 100% !important; height: 100% !important; }
  .time-btn { padding: 6px 10px; font-size: 11px; white-space: nowrap; flex-shrink: 0; }
  .order-card { padding: 16px !important; }
  .tab-btn { padding: 10px 16px; font-size: 13px; }
  input, select { font-size: 16px; padding: 10px 10px 10px 36px; }
  .total-row { padding: 10px 12px; }
  .place-btn { padding: 12px; font-size: 14px; }
  .balance-info { font-size: 11px; }
  table { display: block; width: 100%; overflow-x: hidden; }
  table thead { display: none; }
  table tbody { display: block; width: 100%; }
  table tr { display: block; padding: 12px; border-bottom: 1px solid var(--groww-border); }
  table td { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border: none; font-size: 13px; }
  table td::before { content: attr(data-label); font-weight: 600; color: var(--groww-text-secondary); font-size: 12px; margin-right: 12px; }
  .cancel-btn { font-size: 10px; padding: 3px 8px; }
  .perf-section { padding: 16px; margin-bottom: 16px; }
  .perf-grid { grid-template-columns: repeat(2, 1fr); }
  .perf-item { padding: 12px; }
  .perf-value { font-size: 15px; }
}

@media (max-width: 480px) {
  .main-content { padding: 8px; padding-bottom: 72px; }
  .index-hero { padding: 12px; }
  .hero-sym { font-size: 20px; }
  .ltp-big { font-size: 24px; }
  .hero-ohlc { grid-template-columns: repeat(2, 1fr); }
  .ohlc-item:nth-child(3) { border-right: none; }
  .chart-container { height: 240px !important; }
  #chart-price { font-size: 22px !important; }
  #chart-change { font-size: 12px !important; }
  .order-card { padding: 12px !important; }
  .tab-btn { padding: 8px 12px; font-size: 12px; }
  th, td { padding: 8px 10px; }
}
</style>
<link rel="stylesheet" href="public/assets/css/mobile-responsive.css">
</head>
<body>

<?php include 'includes/user-top-nav.php'; ?>

<div class="main-content">
  <a href="javascript:history.back()" class="back-link"><i class="fa fa-arrow-left"></i> Back</a>

  <!-- Index Hero -->
  <div class="index-hero">
    <div class="hero-top">
      <div>
        <div class="hero-sym" id="heroSym"><?= htmlspecialchars($symbol) ?></div>
        <div class="hero-name" id="heroName"><?= htmlspecialchars($indexName) ?></div>
        <div class="hero-badges">
          <span class="badge-sm"><?= htmlspecialchars($indexStock['exchange'] ?? 'NSE') ?></span>
          <span class="badge-sm"><?= htmlspecialchars($indexStock['sector'] ?? 'Index') ?></span>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:12px">
        <div class="ltp-block">
          <div class="ltp-big <?= $isPos ? 'pos' : 'neg' ?>" id="ltpBig">₹<?= number_format($ltp, 2) ?></div>
          <div class="ltp-change <?= $isPos ? 'pos' : 'neg' ?>" id="chgBig">
            <i class="fa <?= $isPos ? 'fa-caret-up' : 'fa-caret-down' ?>"></i>
            <?= ($chg >= 0 ? '+' : '') . number_format($chg, 2) ?>%
            (<?= ($chgVal >= 0 ? '+' : '') . number_format($chgVal, 2) ?>)
          </div>
        </div>
      </div>
    </div>
    <div class="hero-ohlc">
      <div class="ohlc-item"><div class="ohlc-label">Open</div><div class="ohlc-val" id="ohlcOpen">-</div></div>
      <div class="ohlc-item"><div class="ohlc-label">High</div><div class="ohlc-val pos" id="ohlcHigh">-</div></div>
      <div class="ohlc-item"><div class="ohlc-label">Low</div><div class="ohlc-val neg" id="ohlcLow">-</div></div>
      <div class="ohlc-item"><div class="ohlc-label">Prev Close</div><div class="ohlc-val" id="ohlcPrevClose">₹<?= number_format($prev, 2) ?></div></div>
    </div>
  </div>

  <div class="content-grid">
    <!-- Left: Chart + Performance + Orders -->
    <div>
      <!-- Chart Card -->
      <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
          <div>
            <div style="display: flex; align-items: baseline; gap: 12px; margin-bottom: 8px;">
              <span id="chart-price" style="font-size: 32px; font-weight: 700; color: var(--groww-text);">₹<?= number_format($ltp, 2) ?></span>
              <span id="chart-change" style="font-size: 16px; font-weight: 600;"><?= $chg >= 0 ? '+' : '' ?><?= number_format($chg, 2) ?>%</span>
            </div>
            <div style="font-size: 13px; color: var(--groww-text-secondary);" id="chart-date">Loading...</div>
          </div>
        </div>
        
        <!-- Time Range Selector -->
        <div style="padding: 12px 24px; border-bottom: 1px solid var(--groww-border); display: flex; gap: 8px;" id="time-range-selector">
          <button class="time-btn" data-range="1D" onclick="loadChart('1D')">1D</button>
          <button class="time-btn" data-range="5D" onclick="loadChart('5D')">5D</button>
          <button class="time-btn active" data-range="1M" onclick="loadChart('1M')">1M</button>
          <button class="time-btn" data-range="6M" onclick="loadChart('6M')">6M</button>
          <button class="time-btn" data-range="1Y" onclick="loadChart('1Y')">1Y</button>
          <button class="time-btn" data-range="5Y" onclick="loadChart('5Y')">5Y</button>
        </div>
        
        <!-- Chart Container -->
        <div class="chart-container" style="height: 400px; position: relative; background: #FFFFFF;">
          <div id="tradingview_chart" style="width:100%;height:100%;"></div>
        </div>
        
        <!-- OHLC Stats Below Chart -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; padding: 16px 24px; border-top: 1px solid var(--groww-border); background: var(--groww-hover);" id="ohlc-stats">
          <div>
            <div style="font-size: 12px; color: var(--groww-text-secondary); margin-bottom: 4px;">Open</div>
            <div style="font-size: 14px; font-weight: 600;" id="stat-open">-</div>
          </div>
          <div>
            <div style="font-size: 12px; color: var(--groww-text-secondary); margin-bottom: 4px;">High</div>
            <div style="font-size: 14px; font-weight: 600;" id="stat-high">-</div>
          </div>
          <div>
            <div style="font-size: 12px; color: var(--groww-text-secondary); margin-bottom: 4px;">Low</div>
            <div style="font-size: 14px; font-weight: 600;" id="stat-low">-</div>
          </div>
          <div>
            <div style="font-size: 12px; color: var(--groww-text-secondary); margin-bottom: 4px;">Prev Close</div>
            <div style="font-size: 14px; font-weight: 600;" id="stat-prevclose">₹<?= number_format($prev, 2) ?></div>
          </div>
        </div>
      </div>

      <!-- Performance -->
      <div class="perf-section">
        <div class="perf-title">Performance</div>
        <div class="perf-grid">
          <div class="perf-item">
            <div class="perf-label">1 Day</div>
            <div class="perf-value" id="perf1D">-</div>
          </div>
          <div class="perf-item">
            <div class="perf-label">1 Month</div>
            <div class="perf-value" id="perf1M">-</div>
          </div>
          <div class="perf-item">
            <div class="perf-label">6 Months</div>
            <div class="perf-value" id="perf6M">-</div>
          </div>
          <div class="perf-item">
            <div class="perf-label">1 Year</div>
            <div class="perf-value" id="perf1Y">-</div>
          </div>
        </div>
      </div>

      <!-- My Orders -->
      <div class="card">
        <div class="card-header"><i class="fa fa-list-alt" style="color:#6366f1;margin-right:8px"></i>My Orders for <?= htmlspecialchars($indexName) ?></div>
        <table>
          <thead><tr><th>Type</th><th>Qty</th><th>Price</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($myOrders as $o): ?>
            <tr>
              <td style="font-weight:600;color:<?= $o['order_type'] === 'BUY' ? '#4ade80' : '#f87171' ?>"><?= $o['order_type'] ?></td>
              <td><?= $o['quantity'] ?></td>
              <td>₹<?= number_format((float)$o['price'], 2) ?></td>
              <td>₹<?= number_format((float)$o['total_amount'], 2) ?></td>
              <td><span class="b-<?= strtolower($o['status']) ?>"><?= ucfirst(strtolower($o['status'])) ?></span></td>
              <td style="color:#4b5563"><?= date('d M', strtotime($o['created_at'])) ?></td>
              <td>
                <?php if ($o['status'] === 'PENDING'): ?>
                  <button class="cancel-btn" onclick="cancelOrder(<?= $o['id'] ?>)">Cancel</button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($myOrders)): ?>
              <tr><td colspan="7" class="empty">No orders yet for <?= htmlspecialchars($indexName) ?>.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Right: Holding + Order Form -->
    <div>
      <?php if ($holding && $holding['quantity'] > 0): ?>
      <div class="holding-card">
        <div class="h-sym"><i class="fa fa-briefcase"></i> You hold <?= $holding['quantity'] ?> unit(s)</div>
        <div class="holding-grid">
          <div class="h-item"><div class="h-label">Avg Buy</div><div class="h-val">₹<?= number_format((float)$holding['average_price'], 2) ?></div></div>
          <div class="h-item"><div class="h-label">Invested</div><div class="h-val">₹<?= number_format((float)$holding['invested_amount'], 2) ?></div></div>
          <div class="h-item"><div class="h-label">Current Value</div><div class="h-val" id="holdingCurVal">₹<?= number_format((float)$holding['current_value'], 2) ?></div></div>
          <div class="h-item"><div class="h-label">Unrealized P&amp;L</div>
            <div class="h-val <?= $holding['pnl'] >= 0 ? 'pos' : 'neg' ?>" id="holdingPnl">
              <?= ($holding['pnl'] >= 0 ? '+' : '') . '₹' . number_format((float)$holding['pnl'], 2) ?>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header"><i class="fa fa-bolt" style="color:#6366f1;margin-right:8px"></i>Place Order</div>
        <div class="card-body" style="padding:20px">
          <div id="orderAlert" class="alert alert-error"></div>
          <div id="orderSuccess" class="alert alert-success"></div>

          <div class="tab-row">
            <button class="tab-btn buy active" id="buyTab" onclick="switchOrderTab('BUY')"><i class="fa fa-arrow-up"></i> BUY</button>
            <button class="tab-btn sell" id="sellTab" onclick="switchOrderTab('SELL')"><i class="fa fa-arrow-down"></i> SELL</button>
          </div>

          <div class="form-group">
            <label>Order Mode</label>
            <select id="orderMode" onchange="updateMode()">
              <option value="MARKET">Market Order (at current price)</option>
              <option value="LIMIT">Limit Order (set your price)</option>
            </select>
          </div>

          <div class="form-group">
            <label>Price (₹)</label>
            <div class="input-wrap">
              <i class="fa fa-rupee-sign"></i>
              <input type="number" id="orderPrice" value="<?= $ltp ?>" step="0.05" min="0.01">
            </div>
          </div>

          <div class="form-group">
            <label>Quantity</label>
            <div class="input-wrap">
              <i class="fa fa-hashtag"></i>
              <input type="number" id="orderQty" value="1" min="1" oninput="calcTotal()">
            </div>
          </div>

          <div class="total-row">
            <span class="t-label">Estimated Total</span>
            <span class="t-val" id="orderTotal">₹<?= number_format($ltp, 2) ?></span>
          </div>

          <button class="place-btn buy" id="placeBtn" onclick="placeOrder()">
            <i class="fa fa-spinner fa-spin" id="orderSpinner" style="display:none"></i>
            <span id="placeBtnText">Place BUY Order</span>
          </button>
          <div class="balance-info">Available balance: <strong>₹<?= number_format((float)$user['current_balance'], 2) ?></strong></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const INDEX_SYMBOL = '<?= $symbol ?>';
const INDEX_DISPLAY = '<?= $rawSymbol ?>';
const INDEX_STOCK_ID = <?= $indexStockId ?>;
const INDEX_LTP = <?= $ltp ?>;
const TV_SYMBOL = '<?= $tvSymbol ?>';
let currentRange = '1M';
let currentOrderType = 'BUY';

// ── Order form ──
function switchOrderTab(type) {
    currentOrderType = type;
    document.getElementById('buyTab').classList.toggle('active', type === 'BUY');
    document.getElementById('sellTab').classList.toggle('active', type === 'SELL');
    const btn = document.getElementById('placeBtn');
    btn.className = 'place-btn ' + type.toLowerCase();
    document.getElementById('placeBtnText').textContent = 'Place ' + type + ' Order';
}

function updateMode() {
    const mode = document.getElementById('orderMode').value;
    const price = document.getElementById('orderPrice');
    if (mode === 'MARKET') { price.readOnly = true; price.style.opacity = '.6'; }
    else { price.readOnly = false; price.style.opacity = '1'; }
    calcTotal();
}

function calcTotal() {
    const price = parseFloat(document.getElementById('orderPrice').value) || INDEX_LTP;
    const qty = parseInt(document.getElementById('orderQty').value) || 1;
    document.getElementById('orderTotal').textContent = '₹' + (price * qty).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

async function placeOrder() {
    if (!INDEX_STOCK_ID) { alert('Index not found in system'); return; }
    const errBox = document.getElementById('orderAlert');
    const sucBox = document.getElementById('orderSuccess');
    errBox.style.display = 'none';
    sucBox.style.display = 'none';

    const price = parseFloat(document.getElementById('orderPrice').value) || INDEX_LTP;
    const qty = parseInt(document.getElementById('orderQty').value) || 1;
    const mode = document.getElementById('orderMode').value;

    if (!price || price <= 0) { errBox.textContent = 'Enter a valid price.'; errBox.style.display = 'block'; return; }
    if (!qty || qty <= 0) { errBox.textContent = 'Enter a valid quantity.'; errBox.style.display = 'block'; return; }

    const btn = document.getElementById('placeBtn');
    btn.disabled = true;
    document.getElementById('orderSpinner').style.display = 'inline';

    try {
        const res = await fetch('api/place-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ stock_id: INDEX_STOCK_ID, order_type: currentOrderType, quantity: qty, price: price, order_mode: mode })
        });
        const data = await res.json();
        if (data.success) {
            sucBox.textContent = data.message || currentOrderType + ' order placed successfully!';
            sucBox.style.display = 'block';
            setTimeout(() => location.reload(), 2000);
        } else {
            errBox.textContent = data.message || 'Order failed';
            errBox.style.display = 'block';
            btn.disabled = false;
            document.getElementById('orderSpinner').style.display = 'none';
        }
    } catch (e) {
        errBox.textContent = 'Network error. Please try again.';
        errBox.style.display = 'block';
        btn.disabled = false;
        document.getElementById('orderSpinner').style.display = 'none';
    }
}

async function cancelOrder(orderId) {
    if (!confirm('Cancel this order?')) return;
    try {
        const res = await fetch('api/cancel-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId })
        });
        const data = await res.json();
        if (data.success) { setTimeout(() => location.reload(), 1000); }
        else { alert(data.message); }
    } catch (e) { alert('Network error'); }
}

// Init
calcTotal();
updateMode();

// ── Load index data ──
async function loadIndexData() {
  try {
    const res = await fetch('api/get-indexes.php');
    const data = await res.json();
    
    if (data.success && data.indexes) {
      // Match by display name (e.g., "Nifty 50") since API returns display names as symbol
      const idx = data.indexes.find(i => i.symbol === INDEX_DISPLAY || i.name === INDEX_DISPLAY);
      
      if (idx) {
        const price = idx.price || 0;
        const change = idx.change || 0;
        const changePct = idx.change_percent || 0;
        const high = idx.high || price;
        const low = idx.low || price;
        const isPos = change >= 0;
        const icon = isPos ? 'fa-caret-up' : 'fa-caret-down';
        
        // Hero LTP
        document.getElementById('ltpBig').textContent = '₹' + price.toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('ltpBig').className = 'ltp-big ' + (isPos ? 'pos' : 'neg');
        document.getElementById('chgBig').innerHTML = '<i class="fa ' + icon + '"></i> ' + (changePct >= 0 ? '+' : '') + changePct.toFixed(2) + '% (' + (change >= 0 ? '+' : '') + change.toFixed(2) + ')';
        document.getElementById('chgBig').className = 'ltp-change ' + (isPos ? 'pos' : 'neg');
        
        // OHLC in hero
        document.getElementById('ohlcHigh').textContent = '₹' + high.toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('ohlcLow').textContent = '₹' + low.toLocaleString('en-IN', {minimumFractionDigits: 2});
        
        // Chart header
        document.getElementById('chart-price').textContent = '₹' + price.toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('chart-change').textContent = (change >= 0 ? '+' : '') + change.toFixed(2) + ' (' + (changePct >= 0 ? '+' : '') + changePct.toFixed(2) + '%)';
        document.getElementById('chart-change').style.color = isPos ? 'var(--groww-green)' : 'var(--groww-red)';
        
        // OHLC stats below chart
        document.getElementById('stat-high').textContent = '₹' + high.toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('stat-low').textContent = '₹' + low.toLocaleString('en-IN', {minimumFractionDigits: 2});
        
        // Update market order price
        if (document.getElementById('orderMode').value === 'MARKET') {
            document.getElementById('orderPrice').value = price.toFixed(2);
            calcTotal();
        }
        
        // Performance
        const el1d = document.getElementById('perf1D');
        el1d.textContent = (changePct >= 0 ? '+' : '') + changePct.toFixed(2) + '%';
        el1d.className = 'perf-value ' + (changePct >= 0 ? 'pos' : 'neg');
      } else {
        document.getElementById('ltpBig').textContent = 'Index not found';
      }
    } else {
      document.getElementById('ltpBig').textContent = 'Data unavailable';
    }
  } catch (e) {
    console.error('Error loading index data:', e);
    document.getElementById('ltpBig').textContent = 'Error loading data';
  }
}

// ── Load TradingView chart ──
let tvWidget = null;

function getTVInterval(range) {
  const map = { '1D': '15', '5D': '60', '1M': 'D', '6M': 'D', '1Y': 'W', '5Y': 'M' };
  return map[range] || 'D';
}

function loadChart(range) {
  currentRange = range;
  document.querySelectorAll('.time-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.range === range);
  });

  const container = document.getElementById('tradingview_chart');
  container.innerHTML = '';

  if (!TV_SYMBOL || TV_SYMBOL === INDEX_SYMBOL) {
    container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--groww-text-secondary);">Chart not available for this symbol</div>';
    return;
  }

  tvWidget = new TradingView.widget({
    width: '100%',
    height: '100%',
    symbol: TV_SYMBOL,
    interval: getTVInterval(range),
    timezone: 'Asia/Kolkata',
    theme: 'light',
    style: '1',
    locale: 'in',
    toolbar_bg: '#f1f3f6',
    enable_publishing: false,
    hide_top_toolbar: false,
    hide_side_toolbar: true,
    save_image: false,
    container_id: 'tradingview_chart',
    disabled_features: ['use_localstorage_for_settings'],
    overrides: {
      "mainSeriesProperties.candleStyle.upColor": "#00D09C",
      "mainSeriesProperties.candleStyle.downColor": "#FF4D4D"
    }
  });
}

function renderChart(prices, isPositive) {
  // Kept for compatibility; TradingView handles rendering
}

// Init
loadIndexData();
loadChart('1M');

// Resize handler
let resizeTimer;
window.addEventListener('resize', () => {
  clearTimeout(resizeTimer);
  resizeTimer = setTimeout(() => loadChart(currentRange), 250);
});
</script>
<script type="text/javascript" src="https://s3.tradingview.com/tv.js"></script>

</body>
</html>
