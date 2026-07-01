<?php
require_once __DIR__ . '/includes/middleware.php';
$user = requireUser();
$currentPage = 'stock-detail';
$db   = getDB();

$stockId = (int)($_GET['id'] ?? 0);
if (!$stockId) { header('Location: /stock-market.php'); exit; }

$stmt = $db->prepare("
    SELECT s.*,
           COALESCE(c.ltp, s.ltp)                AS ltp,
           COALESCE(c.open_price, 0)              AS open_now,
           COALESCE(c.high_price, 0)              AS high_now,
           COALESCE(c.low_price, 0)               AS low_now,
           COALESCE(c.volume, 0)                  AS volume_now,
           COALESCE(c.change_percent, s.change_percent) AS change_pct,
           COALESCE(c.source, 'database')         AS price_source,
           COALESCE(c.updated_at, s.updated_at)   AS price_updated
    FROM stocks s
    LEFT JOIN stock_price_cache c ON c.stock_id = s.id
    WHERE s.id = ? AND s.is_active = 1
");
$stmt->execute([$stockId]);
$stock = $stmt->fetch();
if (!$stock) { header('Location: /stock-market.php'); exit; }

// User's holding for this stock
$holding = $db->prepare("SELECT * FROM user_holdings WHERE user_id = ? AND stock_id = ?");
$holding->execute([$user['id'], $stockId]);
$holding = $holding->fetch();

// In watchlist?
$inWL = $db->prepare("SELECT id FROM user_watchlist WHERE user_id = ? AND stock_id = ?");
$inWL->execute([$user['id'], $stockId]);
$inWL = (bool)$inWL->fetch();

// Recent user orders for this stock
$myOrders = $db->prepare("SELECT * FROM user_orders WHERE user_id = ? AND stock_id = ? ORDER BY created_at DESC LIMIT 10");
$myOrders->execute([$user['id'], $stockId]);
$myOrders = $myOrders->fetchAll();

$ltp    = (float)$stock['ltp'];
$prev   = (float)$stock['previous_close'];
$chg    = (float)$stock['change_pct'];
$chgVal = round($ltp - $prev, 2);
$isPos  = $chg >= 0;
$symbol = $stock['symbol']; // Add symbol variable
$tvSymbol = $stock['exchange'] === 'BSE' ? 'BSE:' . $symbol : 'NSE:' . $symbol;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($stock['symbol']) ?> — TradeZenfy</title>
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

.time-btn:hover {
  background: var(--groww-hover);
  color: var(--groww-text);
}

.time-btn.active {
  background: rgba(0, 208, 156, 0.1);
  color: var(--groww-green);
}

/* Chart Canvas */
#stockChart {
  width: 100%;
  height: 100%;
}

/* Chart Loading */
.chart-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: var(--groww-text-secondary);
  font-size: 14px;
}

@media (max-width: 768px) {
  .time-btn {
    padding: 6px 10px;
    font-size: 11px;
  }
  
  #stockChart {
    height: 300px;
  }
  
  #chart-price {
    font-size: 24px !important;
  }
  
  #chart-change {
    font-size: 14px !important;
  }
  
  #time-range-selector {
    padding: 8px 16px;
    overflow-x: auto;
  }
}

/* Stock Detail Specific Styles */
.back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--groww-text-secondary); text-decoration: none; font-size: 14px; margin-bottom: 20px; padding: 8px 16px; background: var(--groww-card); border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.back-link:hover { color: var(--groww-green); background: var(--groww-hover); }

/* Stock hero */
.stock-hero { background: var(--groww-card); border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.hero-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.hero-sym { font-size: 28px; font-weight: 700; color: var(--groww-text); }
.hero-name { font-size: 14px; color: var(--groww-text-secondary); margin-top: 4px; }
.hero-badges { display: flex; gap: 8px; margin-top: 10px; }
.badge-sm { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; background: var(--groww-hover); color: var(--groww-text-secondary); font-weight: 500; }
.ltp-block { text-align: right; }
.ltp-big { font-size: 32px; font-weight: 700; color: var(--groww-text); }
.ltp-change { font-size: 15px; font-weight: 600; margin-top: 4px; }
.hero-ohlc { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0; margin-top: 20px; border-top: 1px solid var(--groww-border); padding-top: 18px; }
.ohlc-item { text-align: center; border-right: 1px solid var(--groww-border); }
.ohlc-item:last-child { border-right: none; }
.ohlc-label { font-size: 11px; color: var(--groww-text-secondary); text-transform: uppercase; margin-bottom: 4px; }
.ohlc-val { font-size: 15px; font-weight: 600; color: var(--groww-text); }
.watch-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; background: var(--groww-hover); border: 1px solid var(--groww-border); color: var(--groww-text-secondary); font-size: 13px; cursor: pointer; transition: all .15s; font-weight: 500; }
.watch-btn.active { color: #F59E0B; border-color: #F59E0B; background: #FEF3C7; }
.watch-btn:hover { border-color: #F59E0B; color: #F59E0B; }

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

/* TradingView */
.tradingview-widget-container { 
  width: 100%; 
  height: 100% !important;
}

/* Canvas rendering optimization */
canvas {
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-rendering: optimizeLegibility;
}

/* Mobile Responsive */
@media (max-width: 768px) {
  /* Layout */
  .main { padding: 12px; }
  .main-content { padding: 12px; padding-bottom: 72px; }
  .content-grid { grid-template-columns: 1fr; gap: 16px; }
  
  /* Back link */
  .back-link { font-size: 13px; padding: 6px 12px; margin-bottom: 12px; }
  
  /* Stock Hero */
  .stock-hero { padding: 16px; margin-bottom: 16px; }
  .hero-top { flex-direction: column; gap: 12px; }
  .ltp-block { text-align: left; width: 100%; }
  .hero-sym { font-size: 22px; }
  .hero-name { font-size: 13px; }
  .ltp-big { font-size: 26px; }
  .ltp-change { font-size: 14px; }
  .hero-badges { flex-wrap: wrap; }
  .badge-sm { font-size: 10px; padding: 3px 10px; }
  
  /* OHLC Grid */
  .hero-ohlc { 
    grid-template-columns: repeat(3, 1fr); 
    gap: 0; 
    margin-top: 16px;
    padding-top: 12px;
  }
  .ohlc-item { padding: 8px 4px; }
  .ohlc-label { font-size: 10px; }
  .ohlc-val { font-size: 13px; }
  
  /* Watch Button */
  .watch-btn { font-size: 12px; padding: 6px 12px; }
  
  /* Chart Card */
  .card { margin-bottom: 16px !important; overflow: hidden; }
  .card-header { padding: 14px 16px; }
  
  /* Chart Price */
  #chart-price { font-size: 26px !important; }
  #chart-change { font-size: 14px !important; }
  #chart-date { font-size: 12px !important; }
  
  /* Time Range Selector */
  #time-range-selector { 
    padding: 8px 12px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    gap: 4px;
    flex-wrap: nowrap;
  }
  .time-btn {
    padding: 6px 10px;
    font-size: 11px;
    white-space: nowrap;
    flex-shrink: 0;
  }
  
  /* Chart Container - Full width, no overflow */
  .card > div[style*="height: 400px"] {
    height: 280px !important;
    padding: 0 !important;
    overflow: hidden;
    max-width: 100%;
  }
  
  /* Canvas should fill container */
  #stockChart {
    width: 100% !important;
    height: 100% !important;
    display: block;
    max-width: 100%;
  }
  
  /* OHLC Stats */
  #ohlc-stats { 
    grid-template-columns: repeat(2, 1fr) !important;
    padding: 16px 20px !important;
    gap: 16px !important;
    margin-top: 0 !important;
  }
  
  /* Order Form */
  .order-card { padding: 16px !important; }
  .order-tabs { gap: 8px; }
  .tab-btn { padding: 10px 16px; font-size: 13px; }
  .input-wrap { margin-bottom: 12px; }
  input, select { font-size: 16px; padding: 10px 10px 10px 36px; }
  .total-row { padding: 10px 12px; }
  .place-btn { padding: 12px; font-size: 14px; }
  .balance-info { font-size: 11px; }
  
  /* Orders Table - stack on mobile */
  table { 
    display: block;
    width: 100%;
    overflow-x: hidden;
  }
  table thead { display: none; }
  table tbody { display: block; width: 100%; }
  table tr { 
    display: block;
    padding: 12px;
    border-bottom: 1px solid var(--border);
  }
  table td { 
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    border: none;
    font-size: 13px;
  }
  table td::before {
    content: attr(data-label);
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 12px;
    margin-right: 12px;
  }
  .cancel-btn { font-size: 10px; padding: 3px 8px; }
}

@media (max-width: 480px) {
  /* Extra Small Devices */
  .main { padding: 8px; }
  .main-content { padding: 8px; padding-bottom: 72px; }
  
  .stock-hero { padding: 12px; }
  .hero-sym { font-size: 20px; }
  .ltp-big { font-size: 24px; }
  
  /* OHLC - 2 columns */
  .hero-ohlc { grid-template-columns: repeat(2, 1fr); }
  .ohlc-item:nth-child(3) { border-right: none; }
  
  /* Chart - Full width on mobile */
  .card > div[style*="height: 400px"] {
    height: 240px !important;
    padding: 0 !important;
  }
  
  #stockChart {
    width: 100% !important;
    height: 100% !important;
  }
  
  #chart-price { font-size: 22px !important; }
  #chart-change { font-size: 12px !important; }
  
  /* Time buttons - scrollable */
  #time-range-selector {
    padding: 6px 8px;
  }
  .time-btn {
    padding: 5px 8px;
    font-size: 10px;
  }
  
  /* OHLC Stats - stack on very small screens */
  #ohlc-stats {
    grid-template-columns: repeat(2, 1fr) !important;
  }
  
  /* Order form */
  .order-card { padding: 12px !important; }
  .tab-btn { padding: 8px 12px; font-size: 12px; }
  
  /* Tables */
  table { display: block; overflow-x: hidden; }
  th, td { padding: 8px 10px; }
}
</style>
<link rel="stylesheet" href="public/assets/css/mobile-responsive.css">
</head>
<body>

<?php include 'includes/user-top-nav.php'; ?>

<div class="main-content">
  <a href="javascript:history.back()" class="back-link"><i class="fa fa-arrow-left"></i> Back to Market</a>

  <!-- Stock Hero -->
  <div class="stock-hero">
    <div class="hero-top">
      <div>
        <div class="hero-sym"><?= htmlspecialchars($stock['symbol']) ?></div>
        <div class="hero-name"><?= htmlspecialchars($stock['name']) ?></div>
        <div class="hero-badges">
          <span class="badge-sm"><?= htmlspecialchars($stock['exchange']) ?></span>
          <span class="badge-sm"><?= htmlspecialchars($stock['sector'] ?? 'N/A') ?></span>
          <?php if (!empty($stock['isin'] ?? '')): ?><span class="badge-sm">ISIN: <?= htmlspecialchars($stock['isin']) ?></span><?php endif; ?>
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
        <button class="watch-btn <?= $inWL ? 'active' : '' ?>" id="watchBtn" onclick="toggleWatch()">
          <i class="fa fa-star"></i> <?= $inWL ? 'Watching' : 'Add to Watchlist' ?>
        </button>
      </div>
    </div>
    <div class="hero-ohlc">
      <div class="ohlc-item"><div class="ohlc-label">Open</div><div class="ohlc-val" id="ohlcOpen">₹<?= number_format((float)$stock['open_now'], 2) ?></div></div>
      <div class="ohlc-item"><div class="ohlc-label">High</div><div class="ohlc-val pos" id="ohlcHigh">₹<?= number_format((float)$stock['high_now'], 2) ?></div></div>
      <div class="ohlc-item"><div class="ohlc-label">Low</div><div class="ohlc-val neg" id="ohlcLow">₹<?= number_format((float)$stock['low_now'], 2) ?></div></div>
      <div class="ohlc-item"><div class="ohlc-label">Prev Close</div><div class="ohlc-val">₹<?= number_format($prev, 2) ?></div></div>
      <div class="ohlc-item"><div class="ohlc-label">Volume</div><div class="ohlc-val" id="ohlcVol"><?= number_format((int)$stock['volume_now']) ?></div></div>
    </div>
  </div>

  <div class="content-grid">
    <!-- Left: Chart + Orders -->
    <div>
      <!-- Stock Chart Card -->
      <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
          <div>
            <div style="display: flex; align-items: baseline; gap: 12px; margin-bottom: 8px;">
              <span id="chart-price" style="font-size: 32px; font-weight: 700; color: var(--groww-text);">₹<?= number_format((float)$stock['ltp'], 2) ?></span>
              <span id="chart-change" style="font-size: 16px; font-weight: 600;"><?= (float)$stock['change_percent'] >= 0 ? '+' : '' ?><?= number_format((float)$stock['change_percent'], 2) ?>%</span>
            </div>
            <div style="font-size: 13px; color: var(--groww-text-secondary);" id="chart-date">Loading...</div>
          </div>
        </div>
        
        <!-- Time Range Selector -->
        <div style="padding: 12px 24px; border-bottom: 1px solid var(--groww-border); display: flex; gap: 8px;" id="time-range-selector">
          <button class="time-btn" data-range="1D" onclick="loadChartData('1D')">1D</button>
          <button class="time-btn" data-range="5D" onclick="loadChartData('5D')">5D</button>
          <button class="time-btn" data-range="1M" onclick="loadChartData('1M')">1M</button>
          <button class="time-btn" data-range="6M" onclick="loadChartData('6M')">6M</button>
          <button class="time-btn" data-range="1Y" onclick="loadChartData('1Y')">1Y</button>
          <button class="time-btn" data-range="5Y" onclick="loadChartData('5Y')">5Y</button>
          <button class="time-btn active" data-range="ALL" onclick="loadChartData('ALL')">ALL</button>
        </div>
        
        <!-- Chart Container - Full Width -->
        <div style="height: 400px; position: relative; background: #FFFFFF;">
          <div id="tradingview_chart" style="width:100%;height:100%;"></div>
          <div id="chart-tooltip" style="display: none; position: absolute; background: rgba(0,0,0,0.85); color: white; padding: 8px 12px; border-radius: 8px; font-size: 13px; pointer-events: none; z-index: 10; box-shadow: 0 2px 8px rgba(0,0,0,0.2);"></div>
        </div>
        
        <!-- OHLC Stats -->
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
            <div style="font-size: 14px; font-weight: 600;" id="stat-prevclose">₹<?= number_format((float)$stock['previous_close'], 2) ?></div>
          </div>
        </div>
      </div>

      <!-- My orders -->
      <div class="card">
      <div class="card-header"><i class="fa fa-list-alt" style="color:#6366f1;margin-right:8px"></i>My Orders for <?= htmlspecialchars($stock['symbol']) ?></div>
      <table>
        <thead><tr><th>Type</th><th>Qty</th><th>Price</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody id="ordersBody">
          <?php foreach ($myOrders as $o): ?>
          <tr id="order-row-<?= $o['id'] ?>">
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
            <tr><td colspan="7" class="empty">No orders yet for this stock.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    </div>

    <!-- Right: Order form -->
    <div>
      <?php if ($holding && $holding['quantity'] > 0): ?>
      <div class="holding-card">
        <div class="h-sym"><i class="fa fa-briefcase"></i> You hold <?= $holding['quantity'] ?> share(s)</div>
        <div class="holding-grid">
          <div class="h-item"><div class="h-label">Avg Buy</div><div class="h-val">₹<?= number_format((float)$holding['average_price'], 2) ?></div></div>
          <div class="h-item"><div class="h-label">Invested</div><div class="h-val">₹<?= number_format((float)$holding['invested_amount'], 2) ?></div></div>
          <div class="h-item"><div class="h-label">Current Value</div><div class="h-val">₹<?= number_format((float)$holding['current_value'], 2) ?></div></div>
          <div class="h-item"><div class="h-label">Unrealized P&amp;L</div>
            <div class="h-val <?= $holding['pnl'] >= 0 ? 'pos' : 'neg' ?>">
              <?= ($holding['pnl'] >= 0 ? '+' : '') . '₹' . number_format((float)$holding['pnl'], 2) ?>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header"><i class="fa fa-bolt" style="color:#6366f1;margin-right:8px"></i>Place Order</div>
        <div class="card-body">
          <div id="orderAlert" class="alert alert-error"></div>
          <div id="orderSuccess" class="alert alert-success"></div>

          <div class="tab-row">
            <button class="tab-btn buy active" id="buyTab"  onclick="switchOrderTab('BUY')"><i class="fa fa-arrow-up"></i> BUY</button>
            <button class="tab-btn sell"       id="sellTab" onclick="switchOrderTab('SELL')"><i class="fa fa-arrow-down"></i> SELL</button>
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

<script src="public/assets/js/yahoo-finance.js"></script>
<script>
let currentOrderType = 'BUY';
const STOCK_ID     = <?= $stockId ?>;
const SYMBOL       = '<?= $stock['symbol'] ?>';
const TV_SYMBOL    = '<?= $tvSymbol ?>';

// ── Live Price (Yahoo Finance - Free, 15-20 min delayed) ──
YahooFinanceAPI.init();
YahooFinanceAPI.subscribe([SYMBOL], (symbol, data) => {
    const newLtp  = data.ltp;
    const chgPct  = data.changePercent;
    const chgVal  = data.change;
    const isPos   = chgPct >= 0;
    const icon    = isPos ? 'fa-caret-up' : 'fa-caret-down';

    document.getElementById('ltpBig').textContent = '₹' + newLtp.toLocaleString('en-IN', {minimumFractionDigits:2});
    document.getElementById('ltpBig').className   = 'ltp-big ' + (isPos ? 'pos' : 'neg');
    document.getElementById('chgBig').innerHTML   = `<i class="fa ${icon}"></i> ${chgPct >= 0 ? '+' : ''}${chgPct.toFixed(2)}% (${chgVal >= 0 ? '+' : ''}${chgVal.toFixed(2)})`;
    document.getElementById('chgBig').className   = 'ltp-change ' + (isPos ? 'pos' : 'neg');

    // Update market order price
    if (document.getElementById('orderMode').value === 'MARKET') {
        document.getElementById('orderPrice').value = newLtp.toFixed(2);
        calcTotal();
    }
});

// ── Order form ────────────────────────────────────────
function switchOrderTab(type) {
    currentOrderType = type;
    document.getElementById('buyTab').classList.toggle('active', type === 'BUY');
    document.getElementById('sellTab').classList.toggle('active', type === 'SELL');
    const btn = document.getElementById('placeBtn');
    btn.className = 'place-btn ' + type.toLowerCase();
    document.getElementById('placeBtnText').textContent = `Place ${type} Order`;
}

function updateMode() {
    const mode  = document.getElementById('orderMode').value;
    const price = document.getElementById('orderPrice');
    if (mode === 'MARKET') {
        price.readOnly = true;
        price.style.opacity = '.6';
    } else {
        price.readOnly = false;
        price.style.opacity = '1';
    }
    calcTotal();
}

function calcTotal() {
    const price = parseFloat(document.getElementById('orderPrice').value) || 0;
    const qty   = parseInt(document.getElementById('orderQty').value) || 0;
    document.getElementById('orderTotal').textContent = '₹' + (price * qty).toLocaleString('en-IN', {minimumFractionDigits:2});
}

async function placeOrder() {
    const errBox = document.getElementById('orderAlert');
    const sucBox = document.getElementById('orderSuccess');
    errBox.style.display = 'none';
    sucBox.style.display = 'none';

    const price = parseFloat(document.getElementById('orderPrice').value);
    const qty   = parseInt(document.getElementById('orderQty').value);
    const mode  = document.getElementById('orderMode').value;

    if (!price || price <= 0) { showErr('Enter a valid price.'); return; }
    if (!qty || qty <= 0)     { showErr('Enter a valid quantity.'); return; }

    const btn = document.getElementById('placeBtn');
    btn.disabled = true;
    document.getElementById('orderSpinner').style.display = 'inline';

    try {
        const res  = await fetch('api/place-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ stock_id: STOCK_ID, order_type: currentOrderType, quantity: qty, price, order_mode: mode })
        });
        const data = await res.json();
        if (data.success) {
            sucBox.textContent = data.message;
            sucBox.style.display = 'block';
            setTimeout(() => location.reload(), 2000);
        } else {
            showErr(data.message);
            btn.disabled = false;
            document.getElementById('orderSpinner').style.display = 'none';
        }
    } catch {
        showErr('Network error. Please try again.');
        btn.disabled = false;
        document.getElementById('orderSpinner').style.display = 'none';
    }
}

async function cancelOrder(orderId) {
    if (!confirm('Cancel this order?')) return;
    const res  = await fetch('api/cancel-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId })
    });
    const data = await res.json();
    if (data.success) {
        const row = document.getElementById('order-row-' + orderId);
        if (row) { const badge = row.querySelector('[class^="b-"]'); if (badge) { badge.className = 'b-cancelled'; badge.textContent = 'Cancelled'; } row.querySelector('.cancel-btn')?.remove(); }
    } else { alert(data.message); }
}

async function toggleWatch() {
    const res  = await fetch('api/toggle-watchlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ stock_id: STOCK_ID })
    });
    const data = await res.json();
    if (data.success) {
        const btn = document.getElementById('watchBtn');
        const added = data.action === 'added';
        btn.classList.toggle('active', added);
        btn.innerHTML = `<i class="fa fa-star"></i> ${added ? 'Watching' : 'Add to Watchlist'}`;
    }
}

function showErr(msg) {
    const b = document.getElementById('orderAlert');
    b.textContent = msg; b.style.display = 'block';
}

// Init
calcTotal();
updateMode();

// ── TradingView Chart ──────────────────────────────
let currentRange = 'ALL';
let tvWidget = null;

function getTVInterval(range) {
  const map = { '1D': '15', '5D': '60', '1M': 'D', '6M': 'D', '1Y': 'W', '5Y': 'M', 'ALL': 'M' };
  return map[range] || 'D';
}

function loadChartData(range) {
  currentRange = range;
  document.querySelectorAll('.time-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.range === range);
  });

  const container = document.getElementById('tradingview_chart');
  container.innerHTML = '';

  if (!TV_SYMBOL) {
    container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--groww-text-secondary);">Chart not available</div>';
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

function renderChart(data, isPositive) {
  // Kept for compatibility; TradingView handles rendering
}

// Initialize chart when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => loadChartData('ALL'));
} else {
  loadChartData('ALL');
}

// Handle window resize with debounce
let resizeTimer;
window.addEventListener('resize', () => {
  clearTimeout(resizeTimer);
  resizeTimer = setTimeout(() => loadChartData(currentRange), 250);
});
</script>
<script type="text/javascript" src="https://s3.tradingview.com/tv.js"></script>

</body>
</html>
