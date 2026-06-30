<?php $title = 'Stocks | TradeZenfy'; ?>
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

<div class="stock-dashboard container">
  <div style="padding-top:2rem;">
    <div class="section-label">Explore Markets</div>
    <h1 class="section-title">Stock Universe</h1>
  </div>

  <!-- Indices Strip -->
  <div class="indices-strip">
    <div class="idx-card">
      <div class="idx-name">NIFTY 50</div>
      <div class="idx-val">24,187.40</div>
      <div class="idx-chg" style="color:var(--green);">▲ 131.05 (+0.54%)</div>
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
      <div class="idx-name">NIFTY IT</div>
      <div class="idx-val">38,926.15</div>
      <div class="idx-chg" style="color:var(--green);">▲ 512.45 (+1.33%)</div>
    </div>
  </div>

  <!-- Search -->
  <div class="stock-search-bar">
    <input type="text" placeholder="Search stocks, ETFs, mutual funds...">
    <button>Search</button>
  </div>

  <!-- Table -->
  <div class="stock-table-wrap">
    <div class="stock-table-head">
      <h3>Top Stocks</h3>
      <div class="filter-tabs">
        <button class="ftab active">All</button>
        <button class="ftab">NSE</button>
        <button class="ftab">BSE</button>
        <button class="ftab">F&amp;O</button>
        <button class="ftab">Gainers</button>
        <button class="ftab">Losers</button>
      </div>
    </div>
    <?php
    $stocks = [
      ['Reliance Industries','RELIANCE','2,948.50','1.23','up','4,12,834','Large Cap'],
      ['Tata Consultancy','TCS','3,812.00','-0.45','down','1,98,342','Large Cap'],
      ['Infosys','INFY','1,654.30','2.10','up','3,45,621','Large Cap'],
      ['HDFC Bank','HDFCBANK','1,723.80','-0.88','down','2,87,445','Large Cap'],
      ['ICICI Bank','ICICIBANK','1,089.60','0.67','up','5,12,384','Large Cap'],
      ['Wipro','WIPRO','482.90','1.54','up','2,14,738','Large Cap'],
      ['Bajaj Finance','BAJFIN','6,942.15','-0.32','down','89,234','Large Cap'],
      ['SBI','SBIN','798.45','0.91','up','6,82,143','Large Cap'],
      ['Adani Ports','ADANIPORTS','1,428.75','2.87','up','1,34,821','Large Cap'],
      ['Maruti Suzuki','MARUTI','12,384.50','-1.14','down','45,293','Large Cap'],
    ];
    ?>
    <table class="stocks">
      <thead>
        <tr>
          <th>Company</th>
          <th>LTP</th>
          <th>Change</th>
          <th>Volume</th>
          <th>Segment</th>
          <th>Trend</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($stocks as $s): ?>
        <tr>
          <td>
            <div class="s-name"><?= $s[0] ?></div>
            <div class="s-sym"><?= $s[1] ?></div>
          </td>
          <td class="s-price">₹<?= $s[2] ?></td>
          <td>
            <span class="badge-<?= $s[4] ?>">
              <?= $s[4] === 'up' ? '▲' : '▼' ?> <?= abs($s[3]) ?>%
            </span>
          </td>
          <td style="color:var(--muted);font-size:0.85rem;"><?= $s[5] ?></td>
          <td><span style="font-size:0.72rem;color:var(--accent2);"><?= $s[6] ?></span></td>
          <td>
            <div class="mini-chart" data-trend="<?= $s[4] ?>"></div>
          </td>
          <td style="display:flex;gap:0.5rem;">
            <button class="btn-trade btn-buy">Buy</button>
            <button class="btn-trade btn-sell">Sell</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div style="text-align:center;padding:2rem 0 5rem;">
    <a href="market.php" class="btn-primary" style="display:inline-block;margin-top:1rem;">View Live Market Data &rarr;</a>
  </div>
</div>

<?php include 'footer.php'; ?>
<script src="main.js"></script>
</body>
</html>
