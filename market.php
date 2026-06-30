<?php $title = 'Live Market | TradeZenfy'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $title ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'nav.php'; ?>

<div class="market-page container">
  <div class="market-header">
    <div>
      <div class="section-label">Live Data</div>
      <h1>Market Terminal</h1>
    </div>
    <div style="display:flex;align-items:center;gap:1rem;">
      <div class="live-badge"><span class="pulse-dot" style="width:6px;height:6px;background:#ef4444;border-radius:50%;animation:pulse 1.5s infinite;"></span> LIVE</div>
      <div style="font-size:0.78rem;color:var(--muted);font-family:'Orbitron',monospace;" id="market-time">--:--:--</div>
    </div>
  </div>

  <!-- Indices row -->
  <div class="indices-strip" style="margin-bottom:1.5rem;">
    <div class="idx-card">
      <div class="idx-name">NIFTY 50</div>
      <div class="idx-val" id="nifty-val">24,187.40</div>
      <div class="idx-chg" style="color:var(--green);" id="nifty-chg">▲ 131.05 (+0.54%)</div>
    </div>
    <div class="idx-card">
      <div class="idx-name">SENSEX</div>
      <div class="idx-val">79,843.25</div>
      <div class="idx-chg" style="color:var(--red);">▼ 95.80 (-0.12%)</div>
    </div>
    <div class="idx-card">
      <div class="idx-name">NIFTY BANK</div>
      <div class="idx-val">52,438.60</div>
      <div class="idx-chg" style="color:var(--green);">▲ 284.30 (+0.54%)</div>
    </div>
    <div class="idx-card">
      <div class="idx-name">VIX</div>
      <div class="idx-val">13.42</div>
      <div class="idx-chg" style="color:var(--red);">▼ 0.85 (-5.96%)</div>
    </div>
  </div>

  <!-- Main Grid -->
  <div class="market-grid">
    <!-- Chart -->
    <div>
      <div class="chart-area">
        <div class="chart-top">
          <div>
            <div class="chart-sym">NIFTY 50</div>
            <div style="font-size:0.75rem;color:var(--muted);margin-top:0.2rem;">NSE · INR · 1D</div>
          </div>
          <div style="text-align:right;">
            <div class="chart-price" id="chart-price">24,187.40</div>
            <div class="chart-change">▲ 131.05  (+0.54%)</div>
          </div>
        </div>
        <div style="padding:0 1rem 0.5rem;display:flex;gap:0.5rem;">
          <?php foreach(['1D','1W','1M','3M','1Y','5Y'] as $tf): ?>
          <button class="ftab <?= $tf==='1D'?'active':'' ?>" onclick="setTf(this)" style="font-size:0.7rem;padding:0.25rem 0.6rem;"><?= $tf ?></button>
          <?php endforeach; ?>
        </div>
        <div style="padding:0.5rem 1rem 1rem;">
          <canvas id="marketChart" style="width:100%;display:block;"></canvas>
        </div>
        <!-- OHLC strip -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0;border-top:1px solid var(--border);">
          <?php $ohlc = ['Open'=>'24,056.35','High'=>'24,210.80','Low'=>'23,980.10','Prev Close'=>'24,056.35']; ?>
          <?php foreach($ohlc as $lbl=>$val): ?>
          <div style="padding:0.75rem;border-right:1px solid var(--border);text-align:center;">
            <div style="font-size:0.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.2rem;"><?= $lbl ?></div>
            <div style="font-family:'Orbitron',monospace;font-size:0.85rem;color:#fff;"><?= $val ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Top movers below chart -->
      <div class="top-movers" style="margin-top:1.5rem;">
        <div class="movers-head">Top Gainers &amp; Losers</div>
        <?php
        $movers = [
          ['ADANIENT','up','3,284.50','▲ 3.42%'],
          ['M&M','up','2,948.75','▲ 2.87%'],
          ['INFY','up','1,654.30','▲ 2.10%'],
          ['WIPRO','up','482.90','▲ 1.54%'],
          ['BAJFIN','down','6,942.15','▼ 0.32%'],
          ['HDFC','down','1,723.80','▼ 0.88%'],
          ['TCS','down','3,812.00','▼ 0.45%'],
          ['MARUTI','down','12,384.50','▼ 1.14%'],
        ];
        foreach($movers as $m): ?>
        <div class="mover-row">
          <div class="mover-sym"><?= $m[0] ?></div>
          <div class="mover-prc">₹<?= $m[2] ?></div>
          <div style="font-size:0.78rem;font-weight:600;color:<?= $m[1]==='up' ? 'var(--green)' : 'var(--red)' ?>;"><?= $m[3] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Right sidebar -->
    <div class="chart-sidebar">
      <!-- Order Book -->
      <div class="orderbook">
        <h4>Order Book — NIFTY 50</h4>
        <div class="ob-row header">
          <span>Price</span><span>Qty</span><span>Orders</span>
        </div>
        <?php
        $asks = [
          ['24,200.00','1,240','18'],
          ['24,195.50','890','12'],
          ['24,192.00','2,340','31'],
          ['24,190.25','540','8'],
          ['24,188.00','1,120','16'],
        ];
        foreach($asks as $a): ?>
        <div class="ob-row">
          <span class="ob-ask"><?= $a[0] ?></span>
          <span style="color:var(--muted)"><?= $a[1] ?></span>
          <span style="color:var(--muted)"><?= $a[2] ?></span>
        </div>
        <?php endforeach; ?>
        <div style="text-align:center;padding:0.5rem;background:rgba(255,255,255,0.03);margin:0.25rem 0;font-family:'Orbitron',monospace;font-size:0.8rem;color:#fff;border-radius:4px;">
          24,187.40 <span style="color:var(--green);font-size:0.65rem;">▲</span>
        </div>
        <?php
        $bids = [
          ['24,185.75','2,100','28'],
          ['24,182.00','1,450','19'],
          ['24,178.50','3,200','42'],
          ['24,175.00','890','11'],
          ['24,170.25','1,670','22'],
        ];
        foreach($bids as $b): ?>
        <div class="ob-row">
          <span class="ob-bid"><?= $b[0] ?></span>
          <span style="color:var(--muted)"><?= $b[1] ?></span>
          <span style="color:var(--muted)"><?= $b[2] ?></span>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:0.75rem;display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;font-size:0.72rem;text-align:center;">
          <div>
            <div style="color:var(--muted);">Total Buy Qty</div>
            <div style="color:var(--green);font-weight:600;font-family:'Orbitron',monospace;">9,310</div>
          </div>
          <div>
            <div style="color:var(--muted);">Total Sell Qty</div>
            <div style="color:var(--red);font-weight:600;font-family:'Orbitron',monospace;">6,130</div>
          </div>
        </div>
      </div>

      <!-- Order Panel -->
      <div class="order-panel">
        <h4>Place Order</h4>
        <div class="order-tabs">
          <button class="otab buy active">Buy</button>
          <button class="otab sell">Sell</button>
        </div>
        <!-- Note: configure your trading API endpoint here -->
        <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:8px;padding:0.75rem;margin-bottom:1rem;font-size:0.75rem;color:#fbbf24;line-height:1.5;">
          ⚠ Configure market data API to enable live order placement.
        </div>
        <div class="o-input">
          <label>Symbol</label>
          <input type="text" value="NIFTY 50" readonly style="color:var(--accent2);">
          <label>Qty / Lots</label>
          <input type="number" value="1" min="1" placeholder="1">
          <label>Order Type</label>
          <select style="width:100%;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:8px;padding:0.6rem 0.875rem;color:#fff;font-family:'Syne',sans-serif;font-size:0.85rem;outline:none;margin-bottom:0.75rem;">
            <option>Market</option>
            <option>Limit</option>
            <option>Stop Loss</option>
            <option>Stop Loss Market</option>
          </select>
          <label>Price</label>
          <input type="number" value="24187.40" placeholder="Market Price">
        </div>
        <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:var(--muted);margin-bottom:0.75rem;padding:0 0.25rem;">
          <span>Margin Required</span>
          <span style="color:#fff;font-family:'Orbitron',monospace;">₹1,22,450</span>
        </div>
        <button class="btn-buy-full">PLACE BUY ORDER</button>
      </div>

      <!-- Market Depth Bar -->
      <div style="background:var(--card);border:1px solid var(--border);border-radius:16px;padding:1.25rem;">
        <h4 style="font-family:'Orbitron',monospace;font-size:0.8rem;color:var(--muted);margin-bottom:1rem;text-transform:uppercase;letter-spacing:0.1em;">Buy vs Sell Pressure</h4>
        <div style="height:10px;border-radius:100px;overflow:hidden;display:flex;margin-bottom:0.75rem;">
          <div style="width:60%;background:var(--green);opacity:0.7;"></div>
          <div style="width:40%;background:var(--red);opacity:0.7;"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:0.75rem;">
          <span style="color:var(--green);">Buy 60%</span>
          <span style="color:var(--red);">Sell 40%</span>
        </div>
      </div>
    </div>
  </div>

  <div style="margin-top:1.5rem;padding:1rem 1.5rem;background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.15);border-radius:12px;font-size:0.78rem;color:#fbbf24;line-height:1.7;margin-bottom:4rem;">
    <strong>Disclaimer:</strong> All data shown is sample/placeholder. Configure your live market data API (NSE, BSE data feed or third-party provider) in the backend to display real-time prices. Investments in securities are subject to market risk.
  </div>
</div>

<?php include 'footer.php'; ?>
<script>
function setTf(el) {
  document.querySelectorAll('.chart-area .ftab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
}
// Clock
function updateClock() {
  const now = new Date();
  document.getElementById('market-time').textContent =
    now.toLocaleTimeString('en-IN', {hour12: false});
}
setInterval(updateClock, 1000);
updateClock();

// Simulated price flicker
setInterval(() => {
  const base = 24187.40;
  const delta = (Math.random() - 0.5) * 8;
  const price = (base + delta).toFixed(2);
  const el = document.getElementById('chart-price');
  if (el) {
    el.textContent = parseFloat(price).toLocaleString('en-IN', {minimumFractionDigits: 2});
  }
}, 2000);
</script>
<script src="main.js"></script>
</body>
</html>
