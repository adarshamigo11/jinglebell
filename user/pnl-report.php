<?php
require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
$db   = getDB();

// Realized trades
$trades = $db->prepare("
    SELECT th.*, s.name AS stock_name, s.exchange
    FROM trade_history th
    JOIN stocks s ON s.id = th.stock_id
    WHERE th.user_id = ?
    ORDER BY th.executed_at DESC
");
$trades->execute([$user['id']]);
$trades = $trades->fetchAll();

// Unrealized from holdings - calculate live P&L
$holdings = $db->prepare("
    SELECT h.*, s.name AS stock_name, s.symbol,
           COALESCE(c.ltp, s.ltp) AS live_ltp
    FROM user_holdings h
    JOIN stocks s ON s.id = h.stock_id
    LEFT JOIN stock_price_cache c ON c.stock_id = h.stock_id
    WHERE h.user_id = ? AND h.quantity > 0
");
$holdings->execute([$user['id']]);
$holdings = $holdings->fetchAll();

// Calculate unrealized P&L using live prices
foreach($holdings as &$h) {
    $ltp = (float)$h['live_ltp'];
    $avgPrice = (float)$h['average_price'];
    $qty = (int)$h['quantity'];
    $h['calc_invested'] = $avgPrice * $qty;
    $h['calc_current_value'] = $ltp * $qty;
    $h['calc_pnl'] = $h['calc_current_value'] - $h['calc_invested'];
    $h['calc_pnl_pct'] = $h['calc_invested'] > 0 ? ($h['calc_pnl'] / $h['calc_invested']) * 100 : 0;
}
unset($h);

$totalRealized   = array_sum(array_column($trades, 'realized_pnl'));
$totalUnrealized = array_sum(array_column($holdings, 'calc_pnl'));
$totalPnl        = $totalRealized + $totalUnrealized;

$buyTrades  = array_filter($trades, fn($t) => $t['order_type']==='BUY');
$sellTrades = array_filter($trades, fn($t) => $t['order_type']==='SELL');
$winTrades  = array_filter($sellTrades, fn($t) => (float)$t['realized_pnl'] > 0);
$lossTrades = array_filter($sellTrades, fn($t) => (float)$t['realized_pnl'] < 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>P&L Report — TradeZenfy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../public/assets/css/groww-ui.css">
<link rel="stylesheet" href="../public/assets/css/layout-new.css">
<style>
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:16px;margin-bottom:28px}
.tab-row{display:flex;gap:0;background:var(--groww-hover);border-radius:8px;overflow:hidden;margin-bottom:0}
.tab-btn{flex:1;padding:12px 24px;background:none;color:var(--groww-text-secondary);font-size:14px;font-weight:500;cursor:pointer;border:none;border-bottom:2px solid transparent;transition:all .15s}
.tab-btn.active{color:var(--groww-green);border-bottom-color:var(--groww-green);background:white}
.tab-content{display:none}.tab-content.active{display:block}
@media (max-width: 768px) {
  .main { overflow-x: hidden; }
  .top-header { padding: 12px 16px; position: static; }
  .content { padding: 0; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
  /* Tables: stack on mobile */
  table { display: block; width: 100%; overflow-x: hidden; }
  table thead { display: none; }
  table tbody { display: block; width: 100%; }
  table tr { display: block; padding: 12px; border-bottom: 1px solid var(--border); }
  table td { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border: none; font-size: 14px; }
  table td::before { content: attr(data-label); font-weight: 600; color: var(--text-secondary); font-size: 12px; margin-right: 12px; }
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/user-top-nav.php'; ?>

<div class="main">
  <div class="top-header">
    <div class="header-left">
      <h1>P&L Report</h1>
      <p>Complete breakdown of realized and unrealized profit & loss</p>
    </div>
  </div>

  <div class="content">

  <!-- Summary stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="s-label">Total P&amp;L</div>
      <div class="s-value <?= $totalPnl>=0?'pos':'neg' ?>"><?= ($totalPnl>=0?'+':'').'₹'.number_format(abs($totalPnl),2) ?></div>
    </div>
    <div class="stat-card">
      <div class="s-label">Realized P&amp;L</div>
      <div class="s-value <?= $totalRealized>=0?'pos':'neg' ?>"><?= ($totalRealized>=0?'+':'').'₹'.number_format(abs($totalRealized),2) ?></div>
    </div>
    <div class="stat-card">
      <div class="s-label">Unrealized P&amp;L</div>
      <div class="s-value <?= $totalUnrealized>=0?'pos':'neg' ?>"><?= ($totalUnrealized>=0?'+':'').'₹'.number_format(abs($totalUnrealized),2) ?></div>
    </div>
    <div class="stat-card">
      <div class="s-label">Total Trades</div>
      <div class="s-value"><?= count($trades) ?></div>
    </div>
    <div class="stat-card">
      <div class="s-label">Winning Sells</div>
      <div class="s-value pos"><?= count($winTrades) ?></div>
    </div>
    <div class="stat-card">
      <div class="s-label">Losing Sells</div>
      <div class="s-value neg"><?= count($lossTrades) ?></div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="card">
    <div class="tab-row">
      <button class="tab-btn active" onclick="switchTab('realized',this)">Realized Trades</button>
      <button class="tab-btn" onclick="switchTab('unrealized',this)">Open Positions</button>
    </div>

    <!-- Realized -->
    <div id="tab-realized" class="tab-content active">
      <table>
        <thead><tr>
          <th>Stock</th><th>Type</th><th>Qty</th><th>Price</th>
          <th>Total</th><th>Realized P&amp;L</th><th>Date</th>
        </tr></thead>
        <tbody>
          <?php foreach($trades as $t):
            $pnl = (float)$t['realized_pnl'];
          ?>
          <tr>
            <td>
              <div style="font-weight:600"><?= htmlspecialchars($t['symbol']) ?></div>
              <div style="font-size:12px;color:var(--groww-text-secondary)"><?= htmlspecialchars($t['stock_name']) ?></div>
            </td>
            <td class="type-<?= $t['order_type'] ?>"><?= $t['order_type'] ?></td>
            <td><?= $t['quantity'] ?></td>
            <td>₹<?= number_format((float)$t['price'],2) ?></td>
            <td>₹<?= number_format((float)$t['total_amount'],2) ?></td>
            <td class="<?= $t['order_type']==='SELL'?($pnl>=0?'pos':'neg'):'neu' ?>">
              <?= $t['order_type']==='SELL'?($pnl>=0?'+':'').'₹'.number_format(abs($pnl),2):'—' ?>
            </td>
            <td style="font-size:12px;color:var(--groww-text-secondary)"><?= date('d M Y',strtotime($t['executed_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($trades)): ?>
            <tr><td colspan="7" class="empty">No trades yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Unrealized -->
    <div id="tab-unrealized" class="tab-content">
      <table>
        <thead><tr>
          <th>Stock</th><th>Qty</th><th>Avg Buy</th>
          <th>LTP</th><th>Invested</th><th>Current Value</th><th>Unrealized P&amp;L</th>
        </tr></thead>
        <tbody>
          <?php foreach($holdings as $h):
            $ltp     = (float)$h['live_ltp'];
            $avgPrice = (float)$h['average_price'];
            $qty     = (int)$h['quantity'];
            $curVal  = $ltp * $qty;
            $invested= $avgPrice * $qty;
            $pnl     = $curVal - $invested;
            $pnlPct  = $invested>0?($pnl/$invested)*100:0;
          ?>
          <tr data-stock-id="<?= $h['stock_id'] ?>">
            <td>
              <div style="font-weight:600"><?= htmlspecialchars($h['symbol']) ?></div>
              <div style="font-size:12px;color:var(--groww-text-secondary)"><?= htmlspecialchars($h['stock_name']) ?></div>
            </td>
            <td><?= $qty ?></td>
            <td>₹<?= number_format($avgPrice,2) ?></td>
            <td class="pnl-ltp" id="pnl-ltp-<?= $h['stock_id'] ?>">₹<?= number_format($ltp,2) ?></td>
            <td>₹<?= number_format($invested,2) ?></td>
            <td class="pnl-curval" id="pnl-curval-<?= $h['stock_id'] ?>">₹<?= number_format($curVal,2) ?></td>
            <td class="<?= $pnl>=0?'pos':'neg' ?>" id="pnl-val-<?= $h['stock_id'] ?>">
              <?= ($pnl>=0?'+':'').'₹'.number_format(abs($pnl),2) ?>
              <span style="font-size:12px">(<?= ($pnlPct>=0?'+':'').number_format($pnlPct,2) ?>%)</span>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($holdings)): ?>
            <tr><td colspan="7" class="empty">No open positions.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  </div>
</div>

<script src="../public/assets/js/yahoo-finance.js"></script>
<script>
function switchTab(name,btn){
  document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('tab-'+name).classList.add('active');
  btn.classList.add('active');
}

// Live P&L updates via Yahoo Finance
const pnlSymbols = <?= json_encode(array_column($holdings, 'symbol')) ?>;
const pnlData    = <?= json_encode(array_reduce($holdings, function($carry, $h) {
    $carry[$h['symbol']] = [
        'stock_id' => $h['stock_id'],
        'avg_price' => (float)$h['average_price'],
        'qty' => (int)$h['quantity']
    ];
    return $carry;
}, [])) ?>;

YahooFinanceAPI.init();
YahooFinanceAPI.subscribe(pnlSymbols, (symbol, data) => {
    const info = pnlData[symbol];
    if(!info) return;
    
    const sid = info.stock_id;
    const ltp = data.ltp;
    const avg = info.avg_price;
    const qty = info.qty;
    
    const curVal = ltp * qty;
    const invested = avg * qty;
    const pnl = curVal - invested;
    const pnlPct = invested > 0 ? (pnl / invested) * 100 : 0;
    
    const ltpEl = document.getElementById('pnl-ltp-' + sid);
    const curvalEl = document.getElementById('pnl-curval-' + sid);
    const pnlEl = document.getElementById('pnl-val-' + sid);
    
    if(ltpEl) ltpEl.textContent = '₹' + ltp.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if(curvalEl) curvalEl.textContent = '₹' + curVal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if(pnlEl) {
        pnlEl.textContent = (pnl >= 0 ? '+' : '') + '₹' + Math.abs(pnl).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        pnlEl.className = pnl >= 0 ? 'pos' : 'neg';
    }
});
</script>
</body>
</html>
