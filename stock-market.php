<?php
require_once __DIR__ . '/includes/middleware.php';
if (file_exists(__DIR__ . '/includes/stock-logo-helper.php')) {
    require_once __DIR__ . '/includes/stock-logo-helper.php';
}
$user = requireUser();
$currentPage = 'stock-market';
$db   = getDB();

// All active stocks (excluding commodities, indices, and crypto)
$stmt = $db->prepare("
    SELECT s.id, s.symbol, s.name, s.website, s.exchange, s.sector,
           COALESCE(c.ltp, s.ltp) AS ltp,
           COALESCE(c.change_percent, s.change_percent) AS change_percent,
           s.previous_close
    FROM stocks s
    LEFT JOIN stock_price_cache c ON c.stock_id = s.id
    WHERE s.is_active = 1
    AND s.sector NOT IN ('Commodity', 'Index', 'Cryptocurrency')
    ORDER BY s.symbol ASC
");
$stmt->execute();
$stocks = $stmt->fetchAll();

// Get watchlist status
$watchlistStmt = $db->prepare("SELECT stock_id FROM user_watchlist WHERE user_id = ?");
$watchlistStmt->execute([$user['id']]);
$watchlistIds = array_column($watchlistStmt->fetchAll(), 'stock_id');

// Add watchlist flag to each stock
foreach ($stocks as &$stock) {
    $stock['in_watchlist'] = in_array($stock['id'], $watchlistIds);
}

// Get unique sectors
$sectors = array_unique(array_filter(array_column($stocks, 'sector')));
sort($sectors);

// Holdings summary
$holdingsCount = $db->prepare("SELECT COUNT(*) FROM user_holdings WHERE user_id = ? AND quantity > 0");
$holdingsCount->execute([$user['id']]);
$holdingsCount = $holdingsCount->fetchColumn();

// Pending orders
$pendingOrders = $db->prepare("SELECT COUNT(*) FROM user_orders WHERE user_id = ? AND status = 'PENDING'");
$pendingOrders->execute([$user['id']]);
$pendingOrders = $pendingOrders->fetchColumn();

// Available balance
$availableBalance = (float)$user['current_balance'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stock Market — TradeZenfy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="public/assets/css/groww-ui.css">
<link rel="stylesheet" href="public/assets/css/layout-new.css">
<style>
body { background: var(--bg); }

/* Search and Filters */
.toolbar {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
  flex-wrap: wrap;
  align-items: center;
}

.search-wrap {
  position: relative;
  flex: 1;
  min-width: 220px;
}

.search-wrap i {
  position: absolute;
  left: 13px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-secondary);
}

.search-wrap input {
  width: 100%;
  padding: 10px 12px 10px 36px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text);
  font-size: 14px;
  outline: none;
}

.search-wrap input:focus {
  border-color: var(--primary);
}

select {
  padding: 10px 12px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text);
  font-size: 14px;
  outline: none;
}

select:focus {
  border-color: var(--primary);
}

/* Stock Table */
.stock-table {
  width: 100%;
  border-collapse: collapse;
  background: var(--card);
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--border);
}

.stock-table th {
  padding: 14px 20px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  background: var(--hover);
}

.stock-table td {
  padding: 16px 20px;
  font-size: 14px;
  border-top: 1px solid var(--border);
  color: var(--text);
}

.stock-table tbody tr {
  cursor: pointer;
  transition: background 0.2s;
}

.stock-table tbody tr:hover {
  background: var(--hover);
}

.stock-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.stock-logo {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  object-fit: contain;
  background: var(--hover);
  padding: 5px;
}

.stock-logo-placeholder {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 700;
  font-size: 14px;
}

.stock-symbol {
  font-weight: 600;
  font-size: 14px;
  color: var(--text);
}

.stock-name {
  font-size: 12px;
  color: var(--text-secondary);
  margin-top: 2px;
}

.ltp {
  font-weight: 600;
  font-size: 15px;
}

.change {
  font-weight: 600;
  font-size: 13px;
}

.change.positive {
  color: var(--success);
}

.change.negative {
  color: var(--danger);
}

.sector-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11px;
  background: var(--hover);
  color: var(--text-secondary);
}

.trade-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: var(--primary);
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.2s;
}

.trade-btn:hover {
  background: var(--primary-dark);
}

.watch-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 15px;
  color: var(--text-secondary);
  padding: 4px;
  transition: color 0.15s;
}

.watch-btn.active {
  color: #F59E0B;
}

.watch-btn:hover {
  color: #F59E0B;
}

/* Top Movers Section */
.movers-section {
  margin-top: 32px;
}

.movers-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.movers-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: 8px;
}

.mover-tabs {
  display: flex;
  gap: 8px;
}

.mover-tab {
  padding: 6px 14px;
  border: 1px solid var(--border);
  background: var(--card);
  color: var(--text-secondary);
  font-size: 13px;
  font-weight: 500;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.mover-tab:hover {
  border-color: var(--primary);
  color: var(--primary);
}

.mover-tab.active {
  background: var(--primary);
  border-color: var(--primary);
  color: white;
}

.mover-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.mover-block {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
  cursor: pointer;
  transition: all 0.2s;
  aspect-ratio: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.mover-block:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.12);
  border-color: var(--primary);
}

.mover-block-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}

.mover-logo {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 700;
  font-size: 14px;
}

.mover-symbol-info {
  flex: 1;
}

.mover-symbol {
  font-weight: 700;
  color: var(--text);
  font-size: 15px;
  line-height: 1.2;
}

.mover-name {
  font-size: 11px;
  color: var(--text-secondary);
  margin-top: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mover-price {
  font-weight: 700;
  color: var(--text);
  font-size: 20px;
  margin-bottom: 8px;
}

.mover-change {
  font-weight: 600;
  font-size: 14px;
  padding: 6px 10px;
  border-radius: 6px;
  text-align: center;
}

.mover-change.positive {
  color: var(--success);
  background: rgba(16, 185, 129, 0.1);
}

.mover-change.negative {
  color: var(--danger);
  background: rgba(239, 68, 68, 0.1);
}

@media (max-width: 1024px) {
  .mover-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .mover-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
  }
  
  .mover-block {
    padding: 14px;
  }
  
  .mover-logo {
    width: 32px;
    height: 32px;
    font-size: 12px;
  }
  
  .mover-symbol {
    font-size: 13px;
  }
  
  .mover-price {
    font-size: 16px;
  }
}
</style>
</head>
<body>

<?php include 'includes/user-top-nav.php'; ?>

<div class="main-content">
  <!-- Summary Cards -->
  <div class="summary-cards">
    <div class="summary-card">
      <div class="summary-card-header">
        <div class="summary-card-title">Holdings <i class="fa fa-chevron-right" style="font-size: 12px;"></i></div>
      </div>
      <div class="summary-card-value"><?= $holdingsCount ?></div>
      <div class="summary-card-subtitle">
        <?php if ($holdingsCount > 0): ?>
          <a href="user/portfolio.php" style="color: var(--primary); text-decoration: none; font-weight: 600;">View Portfolio →</a>
        <?php else: ?>
          No holdings yet
        <?php endif; ?>
      </div>
    </div>

    <div class="summary-card">
      <div class="summary-card-header">
        <div class="summary-card-title">Positions <i class="fa fa-chevron-right" style="font-size: 12px;"></i></div>
      </div>
      <div class="summary-card-value"><?= $pendingOrders ?></div>
      <div class="summary-card-subtitle">
        <?php if ($pendingOrders > 0): ?>
          <a href="user/orders.php" style="color: var(--primary); text-decoration: none; font-weight: 600;">View Orders →</a>
        <?php else: ?>
          No open positions
        <?php endif; ?>
      </div>
    </div>

    <div class="summary-card">
      <div class="summary-card-header">
        <div class="summary-card-title">Funds <i class="fa fa-chevron-right" style="font-size: 12px;"></i></div>
      </div>
      <div class="summary-card-value"><?= formatINR($availableBalance) ?></div>
      <div class="summary-card-subtitle">Available</div>
      <button class="summary-card-action" onclick="window.location.href='user/deposit.php'" style="margin-top: 12px;">
        + Add
      </button>
    </div>
  </div>

  <!-- Market Overview Section -->
  <div class="market-section">
    <h2 class="market-section-title">Market overview</h2>
    
    <div class="market-tabs">
      <button class="market-tab" onclick="window.location.href='user/dashboard.php'">Indices</button>
      <button class="market-tab active">Stocks</button>
      <button class="market-tab" onclick="showComingSoon('commodities')">Commodities</button>
      <button class="market-tab" onclick="showComingSoon('global')">Global</button>
    </div>

    <!-- Stocks Tab Content -->
    <div id="tab-stocks" class="tab-content">
      <!-- Search and Filters -->
      <div class="toolbar">
        <div class="search-wrap">
          <i class="fa fa-search"></i>
          <input type="text" id="searchInput" placeholder="Search symbol or company name…" oninput="filterTable()">
        </div>
        <select id="sectorFilter" onchange="filterTable()">
          <option value="">All Sectors</option>
          <?php foreach ($sectors as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
          <?php endforeach; ?>
        </select>
        <select id="changeFilter" onchange="filterTable()">
          <option value="">All</option>
          <option value="gainers">Gainers</option>
          <option value="losers">Losers</option>
        </select>
      </div>

      <!-- Stock Table -->
      <table class="stock-table" id="stockTable">
        <thead>
          <tr>
            <th>Symbol</th>
            <th>LTP</th>
            <th>Change</th>
            <th>Sector</th>
            <th>Watch</th>
            <th>Trade</th>
          </tr>
        </thead>
        <tbody id="stockTbody">
          <?php foreach ($stocks as $s):
            $ltp    = (float)$s['ltp'];
            $chg    = (float)$s['change_percent'];
            $prev   = (float)$s['previous_close'];
            $chgVal = round($ltp - $prev, 2);
            $cls    = $chg > 0 ? 'positive' : ($chg < 0 ? 'negative' : '');
            $icon   = $chg > 0 ? 'fa-caret-up' : ($chg < 0 ? 'fa-caret-down' : 'fa-minus');
          ?>
          <tr class="stock-row"
              data-symbol="<?= $s['symbol'] ?>"
              data-name="<?= htmlspecialchars(strtolower($s['name'])) ?>"
              data-sector="<?= htmlspecialchars($s['sector']) ?>"
              data-change="<?= $chg ?>"
              onclick="goToStock(<?= $s['id'] ?>)">
            <td>
              <div class="stock-info">
                <?php 
                $logoUrl = getStockLogoWithFallback($s['website'], $s['symbol'], 64);
                if ($logoUrl): 
                ?>
                  <img src="<?= $logoUrl ?>" alt="<?= htmlspecialchars($s['symbol']) ?>" class="stock-logo" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                  <div class="stock-logo-placeholder" style="display:none"><?= strtoupper(substr($s['symbol'], 0, 2)) ?></div>
                <?php else: ?>
                  <div class="stock-logo-placeholder"><?= strtoupper(substr($s['symbol'], 0, 2)) ?></div>
                <?php endif; ?>
                <div>
                  <div class="stock-symbol"><?= htmlspecialchars($s['symbol']) ?></div>
                  <div class="stock-name"><?= htmlspecialchars($s['name']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <div class="ltp" id="ltp-<?= $s['id'] ?>">₹<?= number_format($ltp, 2) ?></div>
            </td>
            <td>
              <div class="change <?= $cls ?>" id="chg-<?= $s['id'] ?>">
                <i class="fa <?= $icon ?>"></i>
                <?= ($chg >= 0 ? '+' : '') . number_format($chg, 2) ?>%
                <span style="font-size:12px">(<?= ($chgVal >= 0 ? '+' : '') . number_format($chgVal, 2) ?>)</span>
              </div>
            </td>
            <td><span class="sector-badge"><?= htmlspecialchars($s['sector'] ?? '—') ?></span></td>
            <td onclick="event.stopPropagation()">
              <button class="watch-btn <?= $s['in_watchlist'] ? 'active' : '' ?>" id="wbtn-<?= $s['id'] ?>" onclick="toggleWatch(<?= $s['id'] ?>)" title="<?= $s['in_watchlist'] ? 'Remove from watchlist' : 'Add to watchlist' ?>">
                <i class="fa fa-star"></i>
              </button>
            </td>
            <td onclick="event.stopPropagation()">
              <a href="stock-detail.php?id=<?= $s['id'] ?>" class="trade-btn"><i class="fa fa-bolt"></i> Trade</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div id="emptyState" class="coming-soon" style="display:none">
        <i class="fa fa-search"></i>
        <h3>No stocks found</h3>
        <p>No stocks match your filter.</p>
      </div>
    </div>
  </div>

  <!-- Top Movers Section -->
  <div class="movers-section">
    <div class="movers-header">
      <h3 class="movers-title">
        <i class="fa fa-fire" style="color: var(--primary);"></i>
        Top Movers
      </h3>
      <div class="mover-tabs">
        <button class="mover-tab active" data-category="gainers" onclick="loadMovers('gainers')">Gainers</button>
        <button class="mover-tab" data-category="losers" onclick="loadMovers('losers')">Losers</button>
        <button class="mover-tab" data-category="most-active" onclick="loadMovers('most-active')">Most Active</button>
      </div>
    </div>
    <div id="movers-container">
      <div class="coming-soon">
        <i class="fa fa-spinner fa-spin"></i>
        <p>Loading top movers...</p>
      </div>
    </div>
  </div>
</div>

<script src="public/assets/js/yahoo-finance.js"></script>
<script>
// Stock data from PHP
const stockIds   = <?= json_encode(array_column($stocks, 'id', 'symbol')) ?>;
const allSymbols = <?= json_encode(array_column($stocks, 'symbol')) ?>;

// Yahoo Finance API
YahooFinanceAPI.init();
YahooFinanceAPI.subscribe(allSymbols, (symbol, data) => {
    const id  = stockIds[symbol];
    if (!id) return;

    const ltpEl = document.getElementById('ltp-' + id);
    const chgEl = document.getElementById('chg-' + id);
    if (!ltpEl) return;

    const newLtp    = data.ltp;
    const oldLtp    = parseFloat(ltpEl.textContent.replace('₹','').replace(/,/g,''));
    const chgPct    = data.changePercent;
    const chgVal    = data.change;

    ltpEl.textContent = '₹' + newLtp.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const cls    = chgPct > 0 ? 'positive' : (chgPct < 0 ? 'negative' : '');
    const icon   = chgPct > 0 ? 'fa-caret-up' : (chgPct < 0 ? 'fa-caret-down' : 'fa-minus');
    chgEl.className = 'change ' + cls;
    chgEl.innerHTML = `<i class="fa ${icon}"></i> ${chgPct >= 0 ? '+' : ''}${chgPct.toFixed(2)}% <span style="font-size:12px">(${chgVal >= 0 ? '+' : ''}${chgVal.toFixed(2)})</span>`;
    
    const row = ltpEl.closest('tr');
    if (row) {
        row.dataset.change = chgPct;
    }
});

// Filter & Sort
function filterTable() {
    const q      = document.getElementById('searchInput').value.toLowerCase();
    const sector = document.getElementById('sectorFilter').value;
    const chgF   = document.getElementById('changeFilter').value;
    let visible  = 0;

    document.querySelectorAll('.stock-row').forEach(row => {
        const sym  = row.dataset.symbol.toLowerCase();
        const name = row.dataset.name;
        const sec  = row.dataset.sector;
        const chg  = parseFloat(row.dataset.change || 0);

        const matchSearch = !q || sym.includes(q) || name.includes(q);
        const matchSector = !sector || sec === sector;
        const matchChg    = !chgF || (chgF === 'gainers' ? chg > 0 : chg < 0);

        const show = matchSearch && matchSector && matchChg;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    document.getElementById('emptyState').style.display = visible === 0 ? 'block' : 'none';
}

// Watchlist toggle
async function toggleWatch(stockId) {
    const res  = await fetch('api/toggle-watchlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ stock_id: stockId })
    });
    const data = await res.json();
    if (data.success) {
        const btn = document.getElementById('wbtn-' + stockId);
        btn.classList.toggle('active', data.action === 'added');
    }
}

function goToStock(id) { window.location.href = 'stock-detail.php?id=' + id; }

// Top Movers
async function loadMovers(category = 'gainers') {
  document.querySelectorAll('.mover-tab').forEach(tab => {
    tab.classList.toggle('active', tab.dataset.category === category);
  });
  
  const container = document.getElementById('movers-container');
  container.innerHTML = '<div class="coming-soon"><i class="fa fa-spinner fa-spin"></i><p>Loading...</p></div>';
  
  try {
    const response = await fetch(`api/yahoo-top-movers.php?category=${category}`);
    const data = await response.json();
    
    if (!data.success || data.count === 0) {
      container.innerHTML = '<div class="coming-soon"><i class="fa fa-exclamation-circle"></i><p>No data available</p></div>';
      return;
    }
    
    let html = '<div class="mover-grid">';
    data.movers.slice(0, 4).forEach(mover => {
      const isPositive = mover.changePercent >= 0;
      const changeIcon = isPositive ? '▲' : '▼';
      const changeClass = isPositive ? 'positive' : 'negative';
      const symbolFirst2 = mover.symbol.substring(0, 2).toUpperCase();
      
      html += `
        <div class="mover-block" onclick="window.location.href='stock-detail.php?id=${mover.id}'">
          <div class="mover-block-header">
            <div class="mover-logo">${symbolFirst2}</div>
            <div class="mover-symbol-info">
              <div class="mover-symbol">${mover.symbol}</div>
              <div class="mover-name">${mover.name}</div>
            </div>
          </div>
          <div class="mover-price">₹${mover.price.toFixed(2)}</div>
          <div class="mover-change ${changeClass}">
            ${changeIcon} ${Math.abs(mover.changePercent).toFixed(2)}%
          </div>
        </div>
      `;
    });
    html += '</div>';
    
    container.innerHTML = html;
    
  } catch (error) {
    console.error('Error loading movers:', error);
    container.innerHTML = '<div class="coming-soon"><i class="fa fa-exclamation-circle"></i><p>Failed to load data</p></div>';
  }
}

// Coming soon
function showComingSoon(type) {
  alert(type.charAt(0).toUpperCase() + type.slice(1) + ' coming soon!');
}

// Load gainers on page load
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => loadMovers('gainers'));
} else {
  loadMovers('gainers');
}
</script>

</body>
</html>
