<?php
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/stock-logo-helper.php';
$user = requireUser();
$db   = getDB();

// Fetch all holdings with live prices
$holdings = $db->prepare("
    SELECT h.*, s.name AS stock_name, s.symbol, s.website, s.exchange, s.sector,
           COALESCE(c.ltp, s.ltp) AS live_ltp,
           COALESCE(c.change_percent, s.change_percent) AS live_chg
    FROM user_holdings h
    JOIN stocks s ON s.id = h.stock_id
    LEFT JOIN stock_price_cache c ON c.stock_id = s.id
    WHERE h.user_id = ? AND h.quantity > 0
    ORDER BY h.invested_amount DESC
");
$holdings->execute([$user['id']]);
$holdings = $holdings->fetchAll();

// Totals
$totalInvested   = array_sum(array_column($holdings, 'invested_amount'));
$totalCurrentVal = array_sum(array_column($holdings, 'current_value'));
$totalUnPnl      = array_sum(array_column($holdings, 'pnl'));

// Realized P&L from trade_history
$realized = $db->prepare("SELECT COALESCE(SUM(realized_pnl),0) FROM trade_history WHERE user_id = ? AND order_type = 'SELL'");
$realized->execute([$user['id']]);
$totalRealizedPnl = (float)$realized->fetchColumn();

$symbols = array_column($holdings, 'symbol');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Portfolio — TradeZenfy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../public/assets/css/groww-ui.css">
<link rel="stylesheet" href="../public/assets/css/layout-new.css">
<style>
body{background:var(--bg);color:var(--text);min-height:100vh;line-height:1.5}
.top-header{background:var(--groww-card);border-bottom:1px solid var(--groww-border);padding:16px 32px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:100}
.header-left h1{font-size:20px;font-weight:700;color:var(--groww-text)}
.header-left p{font-size:13px;color:var(--groww-text-secondary);margin-top:2px}
.content{padding:24px 32px;max-width:1400px}
.summary-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-bottom:24px}
.sum-card{background:var(--groww-card);border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.08)}
.sum-card .s-label{font-size:13px;color:var(--groww-text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;font-weight:500}
.sum-card .s-value{font-size:24px;font-weight:700;color:var(--groww-text)}
.sum-card .s-sub{font-size:12px;color:var(--groww-text-secondary);margin-top:6px}
.sum-card .s-sub a{color:var(--primary);text-decoration:none;font-weight:600}
.pos{color:var(--success)}.neg{color:var(--danger)}.neu{color:var(--text-secondary)}
.card{background:var(--groww-card);border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);margin-bottom:24px}
.card-header{padding:18px 24px;border-bottom:1px solid var(--groww-border);display:flex;align-items:center;justify-content:space-between}
.card-header h3{font-size:16px;font-weight:600;color:var(--groww-text)}
card-header span{font-size:13px;color:var(--groww-text-secondary)}
table{width:100%;border-collapse:collapse}
th{font-size:12px;color:var(--groww-text-secondary);text-transform:uppercase;letter-spacing:.05em;padding:12px 24px;text-align:left;background:var(--groww-hover);font-weight:600}
td{padding:14px 24px;font-size:14px;border-top:1px solid var(--groww-border);vertical-align:middle;color:var(--groww-text)}
tr.holding-row:hover td{background:var(--groww-hover)}
.sym-cell .sym{font-weight:700;font-size:15px;color:var(--groww-text)}
.sym-cell .sname{font-size:12px;color:var(--groww-text-secondary);margin-top:2px}
.stock-logo{width:48px;height:48px;border-radius:10px;object-fit:contain;background:var(--groww-hover);padding:5px;flex-shrink:0}
.stock-logo-placeholder{width:48px;height:48px;border-radius:10px;background:linear-gradient(135deg,var(--groww-green),var(--groww-green-dark));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:16px;flex-shrink:0}
.badge-sm{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;background:var(--groww-hover);color:var(--groww-text-secondary)}
.ltp-cell{font-size:15px;font-weight:600;transition:color .3s}
.pnl-bar{height:4px;border-radius:2px;margin-top:5px}
.trade-link{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;background:rgba(37,99,235,0.1);color:var(--primary);border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;transition:all 0.2s}
.trade-link:hover{background:var(--primary);color:white}
.close-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;background:rgba(239,68,68,0.1);color:var(--danger);border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;transition:all 0.2s}
.close-btn:hover{background:var(--danger);color:white}
.action-btns{display:flex;gap:6px;flex-wrap:wrap}

/* Close Position Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:2000;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal-box{background:#fff;border-radius:16px;padding:28px;width:90%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal-box h3{font-size:18px;font-weight:700;margin-bottom:8px;color:var(--text)}
.modal-box p{font-size:14px;color:var(--text-secondary);margin-bottom:16px;line-height:1.6}
.modal-detail{background:var(--hover);border-radius:10px;padding:14px;margin-bottom:20px}
.modal-detail .md-row{display:flex;justify-content:space-between;padding:6px 0;font-size:13px}
.modal-detail .md-row .md-label{color:var(--text-secondary)}
.modal-detail .md-row .md-value{font-weight:600;color:var(--text)}
.modal-actions{display:flex;gap:10px}
.modal-actions button{flex:1;padding:12px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s}
.btn-cancel{background:var(--hover);color:var(--text-secondary)}
.btn-cancel:hover{background:var(--border)}
.btn-confirm-close{background:var(--danger);color:white}
.btn-confirm-close:hover{background:#DC2626}
.btn-confirm-close:disabled{opacity:.5;cursor:not-allowed}
.empty{padding:60px;text-align:center;color:var(--groww-text-secondary)}
.empty a{color:var(--primary);text-decoration:none;font-weight:600}
.alloc-section{display:grid;grid-template-columns:1fr 320px;gap:20px;margin-top:24px}
@media(max-width:1050px){.alloc-section{grid-template-columns:1fr}}
.alloc-card{background:var(--groww-card);border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.08)}
.alloc-card h3{font-size:16px;font-weight:600;margin-bottom:16px;color:var(--groww-text)}
.alloc-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.alloc-row .alloc-sym{font-weight:600;font-size:14px;color:var(--groww-text)}
.alloc-row .alloc-pct{font-size:13px;color:var(--groww-text-secondary)}
.alloc-bar-wrap{flex:1;height:6px;background:var(--groww-hover);border-radius:3px;margin:0 12px}
.alloc-bar-fill{height:100%;border-radius:3px;background:var(--primary)}
@media (max-width: 768px) {
  body{padding-bottom:0}
  .main{width:100%;overflow-x:hidden}
  .top-header{padding:12px 16px;position:static}
  .header-left h1{font-size:18px}
  .content{padding:0}
  .summary-grid{grid-template-columns:repeat(2,1fr);gap:12px}
  .sum-card{padding:16px}
  .sum-card .s-value{font-size:20px}
  /* Tables: stack on mobile */
  table{display:block;width:100%;overflow-x:hidden}
  table thead{display:none}
  table tbody{display:block;width:100%}
  table tr{display:block;padding:12px;border-bottom:1px solid var(--border)}
  table td{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border:none;font-size:14px}
  table td::before{content:attr(data-label);font-weight:600;color:var(--text-secondary);font-size:12px;margin-right:12px}
  th,td{padding:10px 12px;font-size:13px}
  .alloc-section{grid-template-columns:1fr}
}
@media (max-width: 480px) {
  .summary-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/user-top-nav.php'; ?>

<div class="main">
  <div class="top-header">
    <div class="header-left">
      <h1>Portfolio</h1>
      <p>Your investments and holdings</p>
    </div>
  </div>

  <div class="content">

  <!-- Summary -->
  <div class="summary-grid">
    <div class="sum-card">
      <div class="s-label">Total Invested</div>
      <div class="s-value">₹<?= number_format($totalInvested,2) ?></div>
      <div class="s-sub"><?= count($holdings) ?> stock(s)</div>
    </div>
    <div class="sum-card">
      <div class="s-label">Current Value</div>
      <div class="s-value" id="totalCurrentVal">₹<?= number_format($totalCurrentVal,2) ?></div>
      <div class="s-sub">Live market value</div>
    </div>
    <div class="sum-card">
      <div class="s-label">Unrealized P&amp;L</div>
      <div class="s-value <?= $totalUnPnl>=0?'pos':'neg' ?>" id="totalUnPnl">
        <?= ($totalUnPnl>=0?'+':'').'₹'.number_format(abs($totalUnPnl),2) ?>
      </div>
      <div class="s-sub"><?= $totalInvested>0?number_format(($totalUnPnl/$totalInvested)*100,2).'%':'0.00%' ?> return</div>
    </div>
    <div class="sum-card">
      <div class="s-label">Realized P&amp;L</div>
      <div class="s-value <?= $totalRealizedPnl>=0?'pos':'neg' ?>">
        <?= ($totalRealizedPnl>=0?'+':'').'₹'.number_format(abs($totalRealizedPnl),2) ?>
      </div>
      <div class="s-sub">From closed positions</div>
    </div>
    <div class="sum-card">
      <div class="s-label">Available Balance</div>
      <div class="s-value">₹<?= number_format((float)$user['current_balance'],2) ?></div>
      <div class="s-sub"><a href="deposit.php" style="color:#6366f1;text-decoration:none">Add Funds</a></div>
    </div>
  </div>

  <!-- Holdings Table -->
  <?php if(empty($holdings)): ?>
  <div class="card"><div class="empty">
    <i class="fa fa-briefcase" style="font-size:36px;color:#2d3148;display:block;margin-bottom:12px"></i>
    <div style="font-size:16px;margin-bottom:8px">No holdings yet</div>
    <div style="font-size:14px">Browse the <a href="../stock-market.php">Stock Market</a> and place your first order.</div>
  </div></div>
  <?php else: ?>
  <div class="card">
    <div class="card-header">
      <h3><i class="fa fa-layer-group" style="color:#6366f1;margin-right:8px"></i>Holdings</h3>
      <span style="font-size:13px;color:#94a3b8"><?= count($holdings) ?> position(s)</span>
    </div>
    <table>
      <thead>
        <tr>
          <th>Stock</th><th>Qty</th><th>Avg Buy</th>
          <th>LTP</th><th>Invested</th><th>Current Value</th>
          <th>Unrealized P&amp;L</th><th>Day Chg</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($holdings as $h):
          $ltp      = (float)$h['live_ltp'];
          $avgBuy   = (float)$h['average_price'];
          $invested = (float)$h['invested_amount'];
          $curVal   = $ltp * $h['quantity'];
          $unPnl    = $curVal - $invested;
          $unPnlPct = $invested > 0 ? ($unPnl/$invested)*100 : 0;
          $dayChg   = (float)$h['live_chg'];
          $pnlCls   = $unPnl >= 0 ? 'pos' : 'neg';
          $dayCls   = $dayChg >= 0 ? 'pos' : 'neg';
        ?>
        <tr class="holding-row">
          <td>
            <div class="sym-cell">
              <?php 
              $logoUrl = getStockLogoWithFallback($h['website'], $h['symbol'], 64);
              if ($logoUrl): 
              ?>
                <img src="<?= $logoUrl ?>" alt="<?= htmlspecialchars($h['symbol'] ?? '') ?>" class="stock-logo" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="stock-logo-placeholder" style="display:none"><?= strtoupper(substr($h['symbol'] ?? '', 0, 2)) ?></div>
              <?php else: ?>
                <div class="stock-logo-placeholder"><?= strtoupper(substr($h['symbol'] ?? '', 0, 2)) ?></div>
              <?php endif; ?>
              <div>
                <div class="sym"><?= htmlspecialchars($h['symbol'] ?? '') ?></div>
                <div class="sname"><?= htmlspecialchars($h['stock_name']) ?></div>
                <span class="badge-sm"><?= $h['exchange'] ?></span>
              </div>
            </div>
          </td>
          <td style="font-weight:600"><?= $h['quantity'] ?></td>
          <td>₹<?= number_format($avgBuy,2) ?></td>
          <td>
            <div class="ltp-cell <?= $dayChg>=0?'pos':'neg' ?>" id="port-ltp-<?= $h['stock_id'] ?>">
              ₹<?= number_format($ltp,2) ?>
            </div>
          </td>
          <td>₹<?= number_format($invested,2) ?></td>
          <td id="port-curval-<?= $h['stock_id'] ?>">₹<?= number_format($curVal,2) ?></td>
          <td>
            <div class="<?= $pnlCls ?>" id="port-pnl-<?= $h['stock_id'] ?>">
              <?= ($unPnl>=0?'+':'').'₹'.number_format(abs($unPnl),2) ?>
            </div>
            <div class="<?= $pnlCls ?>" style="font-size:12px" id="port-pnlpct-<?= $h['stock_id'] ?>">
              (<?= ($unPnlPct>=0?'+':'').number_format($unPnlPct,2) ?>%)
            </div>
            <div class="pnl-bar" style="background:<?= $unPnl>=0?'#4ade8033':'#f8717133' ?>">
              <div style="width:<?= min(100,abs($unPnlPct)) ?>%;background:<?= $unPnl>=0?'#4ade80':'#f87171' ?>;height:100%;border-radius:2px"></div>
            </div>
          </td>
          <td class="<?= $dayCls ?>" id="port-daychg-<?= $h['stock_id'] ?>">
            <?= ($dayChg>=0?'+':'').number_format($dayChg,2) ?>%
          </td>
          <td>
            <div class="action-btns">
              <a href="../stock-detail.php?id=<?= $h['stock_id'] ?>" class="trade-link">
                <i class="fa fa-bolt"></i> Trade
              </a>
              <button class="close-btn" onclick="confirmClose(<?= $h['stock_id'] ?>, '<?= htmlspecialchars($h['symbol']) ?>', <?= $h['quantity'] ?>, <?= number_format($ltp,2,'.','') ?>, <?= number_format($avgBuy,2,'.','') ?>)">
                <i class="fa fa-times-circle"></i> Close
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Allocation -->
  <div class="alloc-section">
    <div class="alloc-card">
      <h3><i class="fa fa-chart-pie" style="color:#6366f1;margin-right:8px"></i>Portfolio Allocation</h3>
      <?php foreach($holdings as $h):
        $pct = $totalCurrentVal > 0 ? ((float)$h['current_value']/$totalCurrentVal)*100 : 0;
        $colors = ['#6366f1','#4ade80','#f87171','#fbbf24','#60a5fa','#a78bfa','#34d399','#fb923c'];
        $ci = array_search($h['symbol'], array_column($holdings,'symbol')) % count($colors);
      ?>
      <div class="alloc-row">
        <div class="alloc-sym"><?= htmlspecialchars($h['symbol']) ?></div>
        <div class="alloc-bar-wrap">
          <div class="alloc-bar-fill" style="width:<?= number_format($pct,1) ?>%;background:<?= $colors[$ci] ?>"></div>
        </div>
        <div class="alloc-pct"><?= number_format($pct,1) ?>%</div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="alloc-card">
      <h3><i class="fa fa-info-circle" style="color:#6366f1;margin-right:8px"></i>Summary</h3>
      <?php
        $rows = [
          'Total Invested'    => '₹'.number_format($totalInvested,2),
          'Current Value'     => '₹'.number_format($totalCurrentVal,2),
          'Unrealized P&L'    => ($totalUnPnl>=0?'+':'').'₹'.number_format($totalUnPnl,2),
          'Realized P&L'      => ($totalRealizedPnl>=0?'+':'').'₹'.number_format($totalRealizedPnl,2),
          'Total P&L'         => ($totalUnPnl+$totalRealizedPnl>=0?'+':'').'₹'.number_format(abs($totalUnPnl+$totalRealizedPnl),2),
          'Available Balance' => '₹'.number_format((float)$user['current_balance'],2),
        ];
        foreach($rows as $k=>$v): ?>
      <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #1e2235;font-size:14px">
        <span style="color:#94a3b8"><?= $k ?></span>
        <span style="font-weight:600"><?= $v ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Close Position Confirmation Modal -->
<div class="modal-overlay" id="closeModal">
  <div class="modal-box">
    <h3><i class="fa fa-exclamation-triangle" style="color:var(--danger);margin-right:8px"></i>Close Position</h3>
    <p>Are you sure you want to close this position? This will sell <strong>all shares</strong> at the current market price.</p>
    <div class="modal-detail" id="closeModalDetail">
      <!-- Populated by JS -->
    </div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal()">Cancel</button>
      <button class="btn-confirm-close" id="confirmCloseBtn" onclick="executeClose()">Yes, Close Position</button>
    </div>
  </div>
</div>

<script src="../public/assets/js/yahoo-finance.js"></script>
<script>
const portSymbols = <?= json_encode($symbols) ?>;
const portData    = <?= json_encode(array_column($holdings,'stock_id','symbol')) ?>;
const portQty     = <?= json_encode(array_column($holdings,'quantity','stock_id')) ?>;
const portAvg     = <?= json_encode(array_column($holdings,'average_price','stock_id')) ?>;

// Yahoo Finance API - Free, no API key needed
YahooFinanceAPI.init();
YahooFinanceAPI.subscribe(portSymbols,(symbol,data)=>{
  const sid     = portData[symbol];
  if(!sid) return;
  const ltp     = data.ltp;
  const qty     = parseInt(portQty[sid]||0);
  const avg     = parseFloat(portAvg[sid]||0);
  const curVal  = ltp*qty;
  const invested= avg*qty;
  const pnl     = curVal-invested;
  const pnlPct  = invested>0?(pnl/invested)*100:0;

  const ltpEl   = document.getElementById('port-ltp-'+sid);
  const cvEl    = document.getElementById('port-curval-'+sid);
  const pnlEl   = document.getElementById('port-pnl-'+sid);
  const pctEl   = document.getElementById('port-pnlpct-'+sid);

  if(ltpEl) ltpEl.textContent = '₹'+ltp.toLocaleString('en-IN',{minimumFractionDigits:2});
  if(cvEl)  cvEl.textContent  = '₹'+curVal.toLocaleString('en-IN',{minimumFractionDigits:2});
  if(pnlEl) { pnlEl.textContent=(pnl>=0?'+':'')+'₹'+Math.abs(pnl).toLocaleString('en-IN',{minimumFractionDigits:2}); pnlEl.className=pnl>=0?'pos':'neg'; }
  if(pctEl) { pctEl.textContent='('+(pnlPct>=0?'+':'')+pnlPct.toFixed(2)+'%)'; pctEl.className=pnlPct>=0?'pos':'neg'; }
});

// ===== Close Position Logic =====
let closeStockId = null;

function confirmClose(stockId, symbol, qty, ltp, avgBuy) {
  closeStockId = stockId;
  const totalValue = ltp * qty;
  const pnl = (ltp - avgBuy) * qty;
  const pnlPct = avgBuy > 0 ? ((ltp - avgBuy) / avgBuy) * 100 : 0;
  const pnlClass = pnl >= 0 ? 'color:#10B981' : 'color:#EF4444';
  const pnlSign = pnl >= 0 ? '+' : '';

  document.getElementById('closeModalDetail').innerHTML = `
    <div class="md-row"><span class="md-label">Stock</span><span class="md-value">${symbol}</span></div>
    <div class="md-row"><span class="md-label">Quantity</span><span class="md-value">${qty} shares</span></div>
    <div class="md-row"><span class="md-label">Sell Price (LTP)</span><span class="md-value">₹${ltp.toLocaleString('en-IN',{minimumFractionDigits:2})}</span></div>
    <div class="md-row"><span class="md-label">Total Value</span><span class="md-value">₹${totalValue.toLocaleString('en-IN',{minimumFractionDigits:2})}</span></div>
    <div class="md-row"><span class="md-label">Realized P&L</span><span class="md-value" style="${pnlClass};font-weight:700">${pnlSign}₹${Math.abs(pnl).toLocaleString('en-IN',{minimumFractionDigits:2})} (${pnlSign}${pnlPct.toFixed(2)}%)</span></div>
  `;
  document.getElementById('closeModal').classList.add('open');
  document.getElementById('confirmCloseBtn').disabled = false;
  document.getElementById('confirmCloseBtn').textContent = 'Yes, Close Position';
}

function closeModal() {
  document.getElementById('closeModal').classList.remove('open');
  closeStockId = null;
}

async function executeClose() {
  if (!closeStockId) return;
  const btn = document.getElementById('confirmCloseBtn');
  btn.disabled = true;
  btn.textContent = 'Closing...';

  try {
    const res = await fetch('../api/close-position.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({stock_id: closeStockId})
    });
    const data = await res.json();

    if (data.success) {
      closeModal();
      // Reload page to reflect changes
      location.reload();
    } else {
      alert('Error: ' + data.message);
      btn.disabled = false;
      btn.textContent = 'Yes, Close Position';
    }
  } catch (err) {
    alert('Network error: ' + err.message);
    btn.disabled = false;
    btn.textContent = 'Yes, Close Position';
  }
}

// Close modal on backdrop click
document.getElementById('closeModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>

</body>
</html>
