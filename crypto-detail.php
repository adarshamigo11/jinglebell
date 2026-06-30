<?php
require_once __DIR__ . '/includes/middleware.php';
$user = requireUser();
$currentPage = 'crypto-detail';
$db = getDB();

$cryptoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($cryptoId <= 0) { header('Location: crypto-market.php'); exit; }

// Fetch crypto
$stmt = $db->prepare("SELECT * FROM stocks WHERE id = ? AND sector = 'Cryptocurrency' AND is_active = 1");
$stmt->execute([$cryptoId]);
$crypto = $stmt->fetch();
if (!$crypto) { header('Location: crypto-market.php'); exit; }

$cryptoId = $crypto['id'];
$SYMBOL = $crypto['symbol'];
$ltp = (float)$crypto['ltp'];
$prev = (float)$crypto['previous_close'];
$chgVal = round($ltp - $prev, 2);
$chgPct = $prev > 0 ? round(($chgVal / $prev) * 100, 2) : 0;
$isPos = $chgVal >= 0;

// Holdings
$holdStmt = $db->prepare("SELECT * FROM user_holdings WHERE user_id = ? AND stock_id = ? AND quantity > 0");
$holdStmt->execute([$user['id'], $cryptoId]);
$holding = $holdStmt->fetch();

// Orders
$orderStmt = $db->prepare("SELECT * FROM user_orders WHERE user_id = ? AND stock_id = ? ORDER BY created_at DESC LIMIT 20");
$orderStmt->execute([$user['id'], $cryptoId]);
$orders = $orderStmt->fetchAll();

$availableBalance = (float)$user['current_balance'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($crypto['name']) ?> — TradeZenfy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="public/assets/css/groww-ui.css">
<link rel="stylesheet" href="public/assets/css/layout-new.css">
<style>
body { background: var(--groww-bg); margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }

/* Hero Section */
.stock-hero { background: var(--groww-card); border-bottom: 1px solid var(--groww-border); padding: 20px 0; }
.hero-top { max-width: 1200px; margin: 0 auto; padding: 0 24px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; }
.hero-sym { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
.sym-icon { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #f7931a 0%, #627eea 100%); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 16px; }
.hero-name h1 { font-size: 22px; font-weight: 700; color: var(--groww-text); margin: 0; }
.hero-name span { font-size: 13px; color: var(--groww-text-secondary); }
.ltp-block { text-align: right; }
.ltp-main { font-size: 28px; font-weight: 700; color: var(--groww-text); font-variant-numeric: tabular-nums; }
.ltp-change { font-size: 14px; font-weight: 600; margin-top: 2px; }
.ltp-change.positive { color: var(--groww-green); }
.ltp-change.negative { color: var(--groww-red); }
.hero-badges { display: flex; gap: 8px; margin-top: 8px; }
.badge { font-size: 11px; padding: 3px 10px; border-radius: 12px; background: var(--groww-hover); color: var(--groww-text-secondary); font-weight: 500; }

/* Content Grid */
.content-grid { max-width: 1200px; margin: 24px auto; padding: 0 24px; display: grid; grid-template-columns: 1fr 360px; gap: 24px; }
@media(max-width: 900px) { .content-grid { grid-template-columns: 1fr; } }

/* Cards */
.card { background: var(--groww-card); border: 1px solid var(--groww-border); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.card-header { padding: 16px 20px; border-bottom: 1px solid var(--groww-border); display: flex; justify-content: space-between; align-items: center; }
.card-header h3 { font-size: 16px; font-weight: 600; color: var(--groww-text); margin: 0; }

/* Chart */
.chart-card { margin-bottom: 20px; }
.chart-price { padding: 16px 20px; display: flex; align-items: baseline; gap: 10px; }
.chart-price .price { font-size: 24px; font-weight: 700; color: var(--groww-text); }
.chart-price .change { font-size: 14px; font-weight: 600; }
.chart-price .change.positive { color: var(--groww-green); }
.chart-price .change.negative { color: var(--groww-red); }
.time-range { display: flex; gap: 4px; padding: 0 20px 12px; }
.time-btn { padding: 6px 14px; border: none; background: transparent; color: var(--groww-text-secondary); font-size: 13px; font-weight: 600; cursor: pointer; border-radius: 6px; transition: all 0.15s; }
.time-btn:hover { background: var(--groww-hover); }
.time-btn.active { background: rgba(0, 208, 156, 0.1); color: var(--groww-green); }
.chart-container { padding: 0 20px 20px; position: relative; height: 320px; }
.chart-container canvas { width: 100% !important; height: 100% !important; }

/* Order Form */
.order-section { position: sticky; top: 20px; }
.tab-row { display: flex; gap: 8px; padding: 16px 20px 0; }
.tab-btn { flex: 1; padding: 10px; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.15s; }
.tab-btn.buy { background: var(--groww-hover); color: var(--groww-text-secondary); }
.tab-btn.sell { background: var(--groww-hover); color: var(--groww-text-secondary); }
.tab-btn.buy.active { background: var(--groww-green); color: #fff; }
.tab-btn.sell.active { background: var(--groww-red); color: #fff; }
.order-form { padding: 20px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; color: var(--groww-text-secondary); margin-bottom: 6px; font-weight: 500; }
.form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid var(--groww-border); border-radius: 8px; font-size: 14px; color: var(--groww-text); background: var(--groww-card); outline: none; box-sizing: border-box; }
.form-group input:focus, .form-group select:focus { border-color: var(--groww-green); }
.order-summary { background: var(--groww-hover); border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; }
.summary-row { display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0; }
.summary-row .label { color: var(--groww-text-secondary); }
.summary-row .value { color: var(--groww-text); font-weight: 600; }
.btn-place { width: 100%; padding: 14px; border: none; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.15s; }
.btn-place.buy { background: var(--groww-green); color: #fff; }
.btn-place.sell { background: var(--groww-red); color: #fff; }
.btn-place:hover { opacity: 0.9; }
.btn-place:disabled { opacity: 0.5; cursor: not-allowed; }

/* Holding Card */
.holding-card { margin-bottom: 20px; }
.holding-body { padding: 16px 20px; }
.holding-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--groww-border); }
.holding-row:last-child { border-bottom: none; }
.holding-row .label { font-size: 13px; color: var(--groww-text-secondary); }
.holding-row .value { font-size: 13px; color: var(--groww-text); font-weight: 600; text-align: right; }
.holding-row .value.positive { color: var(--groww-green); }
.holding-row .value.negative { color: var(--groww-red); }

/* Orders */
.orders-table { width: 100%; border-collapse: collapse; }
.orders-table th { text-align: left; padding: 12px 16px; font-size: 12px; font-weight: 600; color: var(--groww-text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--groww-border); }
.orders-table td { padding: 12px 16px; font-size: 13px; color: var(--groww-text); border-bottom: 1px solid var(--groww-border); }
.orders-table tr:hover { background: var(--groww-hover); }
.btn-cancel { padding: 5px 12px; border: 1px solid var(--groww-red); background: transparent; color: var(--groww-red); border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
.btn-cancel:hover { background: var(--groww-red); color: #fff; }
.empty-state { text-align: center; padding: 40px 20px; color: var(--groww-text-secondary); }
.empty-state i { font-size: 32px; margin-bottom: 12px; opacity: 0.5; }

/* Responsive */
@media(max-width: 768px) {
  .hero-top { flex-direction: column; }
  .ltp-block { text-align: left; }
  .content-grid { padding: 0 16px; }
}
</style>
</head>
<body>

<?php include __DIR__ . '/includes/user-top-nav.php'; ?>

<div style="max-width:1200px;margin:0 auto;padding:16px 24px;">
  <a href="javascript:history.back()" style="display:inline-flex;align-items:center;gap:8px;color:var(--text-secondary);text-decoration:none;font-size:14px;padding:8px 16px;background:var(--card);border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.08);"><i class="fa fa-arrow-left"></i> Back</a>
</div>

<div class="stock-hero">
  <div class="hero-top">
    <div>
      <div class="hero-sym">
        <div class="sym-icon"><?= strtoupper(substr($SYMBOL, 0, 3)) ?></div>
        <div class="hero-name">
          <h1><?= htmlspecialchars($crypto['name']) ?></h1>
          <span><?= htmlspecialchars($SYMBOL) ?> · <?= htmlspecialchars($crypto['exchange']) ?></span>
        </div>
      </div>
      <div class="hero-badges">
        <span class="badge"><?= htmlspecialchars($crypto['sector']) ?></span>
        <?php if (!empty($crypto['industry'])): ?>
        <span class="badge"><?= htmlspecialchars($crypto['industry']) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div class="ltp-block">
      <div class="ltp-main" id="liveLtp">₹<?= number_format($ltp, 2) ?></div>
      <div class="ltp-change <?= $isPos ? 'positive' : 'negative' ?>" id="liveChange">
        <?= $isPos ? '▲' : '▼' ?> <?= number_format(abs($chgVal), 2) ?> (<?= $isPos ? '+' : '' ?><?= number_format($chgPct, 2) ?>%)
      </div>
    </div>
  </div>
</div>

<div class="content-grid">
  <div>
    <!-- Chart Card -->
    <div class="card chart-card">
      <div class="chart-price">
        <span class="price" id="chartPrice">₹<?= number_format($ltp, 2) ?></span>
        <span class="change <?= $isPos ? 'positive' : 'negative' ?>" id="chartChange">
          <?= $isPos ? '+' : '' ?><?= number_format($chgVal, 2) ?> (<?= $isPos ? '+' : '' ?><?= number_format($chgPct, 2) ?>%)
        </span>
      </div>
      <div class="time-range">
        <button class="time-btn" data-range="1d" data-interval="5m">1D</button>
        <button class="time-btn active" data-range="5d" data-interval="15m">5D</button>
        <button class="time-btn" data-range="1mo" data-interval="1d">1M</button>
        <button class="time-btn" data-range="3mo" data-interval="1d">3M</button>
        <button class="time-btn" data-range="1y" data-interval="1wk">1Y</button>
        <button class="time-btn" data-range="5y" data-interval="1mo">5Y</button>
      </div>
      <div class="chart-container">
        <canvas id="priceChart"></canvas>
      </div>
    </div>

    <!-- Orders -->
    <div class="card">
      <div class="card-header">
        <h3>Your Orders</h3>
      </div>
      <?php if (empty($orders)): ?>
      <div class="empty-state">
        <i class="fa fa-receipt"></i>
        <p>No orders yet</p>
      </div>
      <?php else: ?>
      <div style="overflow-x: auto;">
        <table class="orders-table">
          <thead>
            <tr>
              <th>Type</th>
              <th>Qty</th>
              <th>Price</th>
              <th>Status</th>
              <th>Date</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $order): ?>
            <tr>
              <td><span style="color: <?= $order['order_type'] === 'BUY' ? 'var(--groww-green)' : 'var(--groww-red)' ?>; font-weight: 600;"><?= $order['order_type'] ?></span></td>
              <td><?= number_format($order['quantity']) ?></td>
              <td>₹<?= number_format($order['price'], 2) ?></td>
              <td><span style="padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; background: <?= $order['status'] === 'EXECUTED' ? 'rgba(0,208,156,0.1)' : ($order['status'] === 'CANCELLED' ? 'rgba(255,77,77,0.1)' : 'rgba(255,165,0,0.1)') ?>; color: <?= $order['status'] === 'EXECUTED' ? 'var(--groww-green)' : ($order['status'] === 'CANCELLED' ? 'var(--groww-red)' : '#ff9800') ?>;"><?= $order['status'] ?></span></td>
              <td><?= date('d M Y', strtotime($order['created_at'])) ?></td>
              <td>
                <?php if ($order['status'] === 'PENDING'): ?>
                <button class="btn-cancel" onclick="cancelOrder(<?= $order['id'] ?>)">Cancel</button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="order-section">
    <!-- Holding Card -->
    <?php if ($holding): ?>
    <?php
      $avgPrice = (float)$holding['average_price'];
      $qty = (int)$holding['quantity'];
      $invested = (float)$holding['invested_amount'];
      $currentVal = (float)$holding['current_value'];
      $unrealizedPnl = $currentVal - $invested;
      $unrealizedPnlPct = $invested > 0 ? ($unrealizedPnl / $invested) * 100 : 0;
    ?>
    <div class="card holding-card">
      <div class="card-header">
        <h3>Your Holdings</h3>
      </div>
      <div class="holding-body">
        <div class="holding-row">
          <span class="label">Quantity</span>
          <span class="value"><?= $qty ?> units</span>
        </div>
        <div class="holding-row">
          <span class="label">Avg. Buy Price</span>
          <span class="value">₹<?= number_format($avgPrice, 2) ?></span>
        </div>
        <div class="holding-row">
          <span class="label">Invested</span>
          <span class="value">₹<?= number_format($invested, 2) ?></span>
        </div>
        <div class="holding-row">
          <span class="label">Current Value</span>
          <span class="value">₹<?= number_format($currentVal, 2) ?></span>
        </div>
        <div class="holding-row">
          <span class="label">Unrealized P&L</span>
          <span class="value <?= $unrealizedPnl >= 0 ? 'positive' : 'negative' ?>">
            <?= $unrealizedPnl >= 0 ? '+' : '' ?>₹<?= number_format($unrealizedPnl, 2) ?>
            <span style="display: block; font-size: 11px;"><?= $unrealizedPnlPct >= 0 ? '+' : '' ?><?= number_format($unrealizedPnlPct, 2) ?>%</span>
          </span>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Order Form -->
    <div class="card">
      <div class="tab-row">
        <button class="tab-btn buy active" onclick="switchTab('BUY')">BUY</button>
        <button class="tab-btn sell" onclick="switchTab('SELL')">SELL</button>
      </div>
      <form class="order-form" id="orderForm" onsubmit="placeOrder(event)">
        <div class="form-group">
          <label>Order Type</label>
          <select id="orderType" onchange="togglePriceInput()">
            <option value="MARKET">Market Order</option>
            <option value="LIMIT">Limit Order</option>
          </select>
        </div>
        <div class="form-group" id="priceGroup" style="display: none;">
          <label>Price (₹)</label>
          <input type="number" id="orderPrice" step="0.01" min="0.01" placeholder="Enter price">
        </div>
        <div class="form-group">
          <label>Quantity</label>
          <input type="number" id="orderQty" step="0.0001" min="0.0001" value="0.01" placeholder="Enter quantity" required>
        </div>
        <div class="order-summary">
          <div class="summary-row">
            <span class="label">Available Balance</span>
            <span class="value">₹<?= number_format($availableBalance, 2) ?></span>
          </div>
          <div class="summary-row">
            <span class="label">Estimated Cost</span>
            <span class="value" id="estimatedCost">₹<?= number_format($ltp * 0.01, 2) ?></span>
          </div>
        </div>
        <button type="submit" class="btn-place buy" id="submitBtn">Buy</button>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const CRYPTO_ID = <?= $cryptoId ?>;
const SYMBOL = '<?= addslashes($SYMBOL) ?>';
const LTP = <?= $ltp ?>;
let currentTab = 'BUY';
let chart = null;

function switchTab(tab) {
  currentTab = tab;
  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
  document.querySelector(`.tab-btn.${tab.toLowerCase()}`).classList.add('active');
  const submitBtn = document.getElementById('submitBtn');
  submitBtn.className = `btn-place ${tab.toLowerCase()}`;
  submitBtn.textContent = tab === 'BUY' ? 'Buy' : 'Sell';
}

function togglePriceInput() {
  const type = document.getElementById('orderType').value;
  const priceGroup = document.getElementById('priceGroup');
  const priceInput = document.getElementById('orderPrice');
  if (type === 'LIMIT') {
    priceGroup.style.display = 'block';
    priceInput.value = LTP.toFixed(2);
  } else {
    priceGroup.style.display = 'none';
    priceInput.value = '';
  }
}

document.getElementById('orderQty').addEventListener('input', function() {
  const qty = parseFloat(this.value) || 0;
  const cost = qty * LTP;
  document.getElementById('estimatedCost').textContent = '₹' + cost.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
});

async function placeOrder(e) {
  e.preventDefault();
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.textContent = 'Placing...';

  const orderType = document.getElementById('orderType').value;
  const price = orderType === 'LIMIT' ? parseFloat(document.getElementById('orderPrice').value) : LTP;
  const qty = parseFloat(document.getElementById('orderQty').value);

  try {
    const res = await fetch('api/place-order.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        stock_id: CRYPTO_ID,
        order_type: currentTab,
        order_mode: orderType,
        quantity: qty,
        price: price
      })
    });
    const data = await res.json();
    if (data.success) {
      alert(`${currentTab} order placed successfully!`);
      location.reload();
    } else {
      alert('Error: ' + (data.message || 'Failed to place order'));
    }
  } catch (err) {
    alert('Error: ' + err.message);
  } finally {
    btn.disabled = false;
    btn.textContent = currentTab === 'BUY' ? 'Buy' : 'Sell';
  }
}

async function cancelOrder(orderId) {
  if (!confirm('Cancel this order?')) return;
  try {
    const res = await fetch('api/cancel-order.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({order_id: orderId})
    });
    const data = await res.json();
    if (data.success) {
      alert('Order cancelled!');
      location.reload();
    } else {
      alert('Error: ' + (data.message || 'Failed to cancel'));
    }
  } catch (err) {
    alert('Error: ' + err.message);
  }
}

// Chart
async function loadChart(range = '5d', interval = '15m') {
  const now = Math.floor(Date.now() / 1000);
  let period1 = now - (5 * 24 * 60 * 60);
  if (range === '1d') period1 = now - (24 * 60 * 60);
  else if (range === '1mo') period1 = now - (30 * 24 * 60 * 60);
  else if (range === '3mo') period1 = now - (90 * 24 * 60 * 60);
  else if (range === '1y') period1 = now - (365 * 24 * 60 * 60);
  else if (range === '5y') period1 = now - (5 * 365 * 24 * 60 * 60);

  try {
    const res = await fetch(`api/yahoo-chart-proxy.php?symbol=${encodeURIComponent(SYMBOL)}&period1=${period1}&period2=${now}&interval=${interval}`);
    const data = await res.json();
    if (data.success && data.chart && data.chart.length > 0) {
      renderChart(data.chart);
    }
  } catch (err) {
    console.error('Chart error:', err);
  }
}

function renderChart(points) {
  const ctx = document.getElementById('priceChart').getContext('2d');
  if (chart) chart.destroy();

  const labels = points.map(p => new Date(p.timestamp * 1000).toLocaleDateString('en-IN', {day: '2-digit', month: 'short'}));
  const prices = points.map(p => p.close);
  const firstPrice = prices[0];
  const lastPrice = prices[prices.length - 1];
  const isUp = lastPrice >= firstPrice;
  const lineColor = isUp ? '#00D09C' : '#FF4D4D';

  chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Price',
        data: prices,
        borderColor: lineColor,
        backgroundColor: isUp ? 'rgba(0, 208, 156, 0.1)' : 'rgba(255, 77, 77, 0.1)',
        fill: true,
        tension: 0.1,
        pointRadius: 0,
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { display: true, grid: { display: false } },
        y: { display: true, position: 'right', grid: { color: 'rgba(0,0,0,0.05)' } }
      }
    }
  });
}

document.querySelectorAll('.time-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.time-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    loadChart(this.dataset.range, this.dataset.interval);
  });
});

loadChart('5d', '15m');
</script>

</body>
</html>
