<?php
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
$db    = getDB();

$stocks = $db->query("
    SELECT s.*, COALESCE(c.ltp, s.ltp) AS current_ltp, COALESCE(c.change_percent, s.change_percent) AS current_chg
    FROM stocks s
    LEFT JOIN stock_price_cache c ON c.stock_id = s.id
    WHERE s.is_active = 1
    ORDER BY s.symbol ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stocks — TradeZenfy Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F5F7FA; color: #1A1A1A; min-height: 100vh; overflow-x: hidden; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
.page-header h1 { font-size: 22px; font-weight: 600; }
.add-btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px; background: #2563EB; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.add-btn:hover { background: #1D4ED8; }
.card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
table { width: 100%; border-collapse: collapse; }
th { font-size: 11px; color: #6B7280; text-transform: uppercase; letter-spacing: .05em; padding: 12px 16px; text-align: left; background: #F9FAFB; font-weight: 600; }
td { padding: 12px 16px; font-size: 14px; border-top: 1px solid #F3F4F6; vertical-align: middle; color: #1A1A1A; }
tr:hover td { background: #F9FAFB; }
.pos { color: #10B981; }
.neg { color: #EF4444; }
.action-btn { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; border: none; margin-right: 4px; }
.btn-price  { background: #DBEAFE; color: #2563EB; }
.btn-toggle { background: #F3F4F6; color: #6B7280; }
.btn-toggle.active { background: #D1FAE5; color: #059669; }
.btn-delete { background: #FEE2E2; color: #EF4444; }
.action-btn:hover { opacity: .8; }
.sector-badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; background: #F3F4F6; color: #6B7280; }
.empty { padding: 40px; text-align: center; color: #9CA3AF; }

/* Modal */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 1000; align-items: center; justify-content: center; }
.modal-overlay.open { display: flex; }
.modal { background: #fff; border: 1px solid #E5E7EB; border-radius: 16px; padding: 28px; width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
.modal h3 { font-size: 17px; font-weight: 600; margin-bottom: 20px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-group { margin-bottom: 14px; }
.form-group.full { grid-column: 1 / -1; }
label { display: block; font-size: 13px; color: #6B7280; margin-bottom: 5px; }
input, select { width: 100%; padding: 10px 12px; background: #F5F7FA; border: 1px solid #E5E7EB; border-radius: 8px; color: #1A1A1A; font-size: 14px; outline: none; font-family: inherit; }
input:focus, select:focus { border-color: #2563EB; }
.modal-actions { display: flex; gap: 10px; margin-top: 20px; }
.modal-btn { flex: 1; padding: 11px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.modal-btn.confirm { background: #2563EB; color: #fff; }
.modal-btn.cancel  { background: #F3F4F6; color: #6B7280; }
.alert { padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 14px; display: none; }
.alert-error   { background: #FEE2E2; border: 1px solid #FECACA; color: #DC2626; }
.alert-success { background: #D1FAE5; border: 1px solid #A7F3D0; color: #059669; }

/* ── Mobile Responsive ── */
@media (max-width: 768px) {
  body { overflow-x: hidden; }
  .main { padding: 24px 16px; width: 100%; }
  .page-header { flex-direction: column; align-items: flex-start; gap: 12px; }
  .page-header h1 { font-size: 20px; }
  .page-header div { width: 100%; justify-content: space-between; }
  .add-btn { font-size: 13px; padding: 8px 14px; }
  .form-grid { grid-template-columns: 1fr; }
  table { display: block; overflow-x: auto; white-space: nowrap; }
  th, td { padding: 10px 12px; font-size: 13px; }
  .modal { margin: 16px; padding: 20px; max-width: calc(100% - 32px); }
  .modal h2 { font-size: 18px; }
  .modal-actions { flex-direction: column; }
  .modal-btn { width: 100%; }
}
@media (max-width: 480px) {
  .main { padding: 20px 12px; }
  .page-header h1 { font-size: 18px; }
  .add-btn span { display: none; }
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin-header.php'; ?>

<div class="main">
  <div class="page-header">
    <h1><i class="fa fa-chart-line" style="color:#6366f1;margin-right:10px"></i>Stocks</h1>
    <div style="display:flex;align-items:center;gap:12px">
      <span id="refreshTimer" style="font-size:13px;color:#94a3b8"><i class="fa fa-sync" style="margin-right:5px"></i>Auto-refresh in 10:00</span>
      <button class="add-btn" onclick="openAddModal()"><i class="fa fa-plus"></i> Add Stock</button>
    </div>
  </div>

  <div class="card">
    <table>
      <thead><tr><th>Symbol</th><th>Name</th><th>Exchange</th><th>Sector</th><th>LTP</th><th>Change</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($stocks as $s):
          $ltp = (float)$s['current_ltp'];
          $chg = (float)$s['current_chg'];
        ?>
        <tr data-symbol="<?= htmlspecialchars($s['symbol']) ?>">
          <td style="font-weight:600"><?= htmlspecialchars($s['symbol']) ?></td>
          <td style="font-size:13px"><?= htmlspecialchars($s['name']) ?></td>
          <td><span class="sector-badge"><?= $s['exchange'] ?></span></td>
          <td><span class="sector-badge"><?= htmlspecialchars($s['sector'] ?? '—') ?></span></td>
          <td style="font-weight:600" class="price-cell" data-ltp="<?= $ltp ?>"><?= $ltp > 0 ? '₹' . number_format($ltp, 2) : '<span style="color:#4b5563">Loading...</span>' ?></td>
          <td class="change-cell <?= $chg >= 0 ? 'pos' : 'neg' ?>" data-chg="<?= $chg ?>"><?= $ltp > 0 ? (($chg >= 0 ? '+' : '') . number_format($chg, 2) . '%') : '<span style="color:#4b5563">-</span>' ?></td>
          <td><span style="color:<?= $s['is_active'] ? '#4ade80' : '#f87171' ?>;font-size:13px"><?= $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
          <td>
            <button class="action-btn btn-price" onclick="openPriceModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['symbol']) ?>', <?= $ltp ?>)"><i class="fa fa-edit"></i> Update Price</button>
            <button class="action-btn btn-toggle <?= $s['is_active'] ? 'active' : '' ?>" onclick="toggleStock(<?= $s['id'] ?>, <?= $s['is_active'] ?>)">
              <i class="fa <?= $s['is_active'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i> <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
            </button>
            <button class="action-btn btn-delete" onclick="deleteStock(<?= $s['id'] ?>, '<?= htmlspecialchars($s['symbol']) ?>')"><i class="fa fa-trash"></i> Delete</button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($stocks)): ?>
          <tr><td colspan="8" class="empty">No stocks found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Stock Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal" style="max-width: 600px;">
    <h3><i class="fa fa-plus" style="color:#6366f1;margin-right:8px"></i>Add New Stock from NSE</h3>
    <div id="addAlert" class="alert alert-error"></div>
    
    <!-- Search Box -->
    <div class="form-group" style="position: relative;">
      <label><i class="fa fa-search" style="margin-right: 5px;"></i>Search NSE Stocks *</label>
      <input type="text" id="stockSearch" placeholder="Type company name or symbol (e.g., TATA, RELIANCE)..." autocomplete="off">
      <div id="searchResults" class="search-results" style="display: none;"></div>
    </div>
    
    <!-- Selected Stock Info -->
    <div id="selectedStockInfo" style="display: none; background: #1e2235; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
      <h4 style="margin: 0 0 10px 0; color: #6366f1;"><i class="fa fa-check-circle" style="margin-right: 5px;"></i>Selected Stock</h4>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 14px;">
        <div><span style="color: #94a3b8;">Symbol:</span> <strong id="selSymbol" style="color: #fff;"></strong></div>
        <div><span style="color: #94a3b8;">Exchange:</span> <strong id="selExchange" style="color: #fff;">NSE</strong></div>
        <div style="grid-column: 1 / -1;"><span style="color: #94a3b8;">Name:</span> <strong id="selName" style="color: #fff;"></strong></div>
        <div><span style="color: #94a3b8;">Sector:</span> <span id="selSector" style="color: #fff;"></span></div>
        <div><span style="color: #94a3b8;">Industry:</span> <span id="selIndustry" style="color: #fff;"></span></div>
      </div>
      <input type="hidden" id="addSymbol">
      <input type="hidden" id="addName">
      <input type="hidden" id="addExchange" value="NSE">
      <input type="hidden" id="addSector">
    </div>
    
    <div class="modal-actions">
      <button class="modal-btn cancel" onclick="closeModals()">Cancel</button>
      <button class="modal-btn confirm" id="addStockBtn" onclick="addStock()" disabled>Add Stock</button>
    </div>
  </div>
</div>

<style>
.search-results {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: #1a1d27;
  border: 1px solid #2d3148;
  border-radius: 8px;
  max-height: 300px;
  overflow-y: auto;
  z-index: 1001;
  margin-top: 5px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.5);
}
.search-result-item {
  padding: 12px 16px;
  cursor: pointer;
  border-bottom: 1px solid #2d3148;
  transition: background 0.2s;
}
.search-result-item:hover {
  background: #22253a;
}
.search-result-item:last-child {
  border-bottom: none;
}
.search-result-symbol {
  font-weight: 600;
  color: #6366f1;
  font-size: 14px;
}
.search-result-name {
  color: #e2e8f0;
  font-size: 13px;
  margin-top: 2px;
}
.search-result-sector {
  color: #94a3b8;
  font-size: 11px;
  margin-top: 2px;
}
.search-result-existing {
  color: #4ade80;
  font-size: 11px;
  margin-top: 2px;
}
</style>

<!-- Update Price Modal -->
<div class="modal-overlay" id="priceModal">
  <div class="modal">
    <h3><i class="fa fa-edit" style="color:#6366f1;margin-right:8px"></i>Update Price: <span id="priceSymbolLabel"></span></h3>
    <div id="priceAlert" class="alert alert-error"></div>
    <input type="hidden" id="priceStockId">
    <div class="form-group"><label>LTP (₹) *</label><input type="number" id="priceLtp" step="0.05"></div>
    <div class="form-group"><label>Open Price (₹)</label><input type="number" id="priceOpen" step="0.05"></div>
    <div class="form-group"><label>High Price (₹)</label><input type="number" id="priceHigh" step="0.05"></div>
    <div class="form-group"><label>Low Price (₹)</label><input type="number" id="priceLow" step="0.05"></div>
    <div class="form-group"><label>Previous Close (₹)</label><input type="number" id="pricePrevClose" step="0.05"></div>
    <div class="form-group"><label>Volume</label><input type="number" id="priceVolume"></div>
    <div class="modal-actions">
      <button class="modal-btn cancel" onclick="closeModals()">Cancel</button>
      <button class="modal-btn confirm" onclick="updatePrice()">Update</button>
    </div>
  </div>
</div>

<script>
let searchTimeout = null;
let selectedStock = null;

// Helper to safely escape HTML
function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Select stock by index from search results
function selectStockByIndex(index) {
  if (window.searchResultsStocks && window.searchResultsStocks[index]) {
    selectStock(window.searchResultsStocks[index]);
  }
}

function openAddModal()  { 
  document.getElementById('addModal').classList.add('open'); 
  document.getElementById('stockSearch').focus();
}
function openPriceModal(id, sym, ltp) {
  document.getElementById('priceStockId').value    = id;
  document.getElementById('priceSymbolLabel').textContent = sym;
  document.getElementById('priceLtp').value        = ltp;
  document.getElementById('priceModal').classList.add('open');
}
function closeModals() {
  document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('open'));
  // Reset add stock modal
  document.getElementById('stockSearch').value = '';
  document.getElementById('searchResults').style.display = 'none';
  document.getElementById('selectedStockInfo').style.display = 'none';
  document.getElementById('addStockBtn').disabled = true;
  selectedStock = null;
}

// Stock search functionality
document.getElementById('stockSearch').addEventListener('input', function(e) {
  const query = e.target.value.trim();
  const resultsDiv = document.getElementById('searchResults');
  
  clearTimeout(searchTimeout);
  
  if (query.length < 2) {
    resultsDiv.style.display = 'none';
    return;
  }
  
  searchTimeout = setTimeout(() => searchStocks(query), 300);
});

async function searchStocks(query) {
  const resultsDiv = document.getElementById('searchResults');
  resultsDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #94a3b8;"><i class="fa fa-spinner fa-spin"></i> Searching...</div>';
  resultsDiv.style.display = 'block';
  
  try {
    const res = await fetch('../api/search-nse-stocks.php?q=' + encodeURIComponent(query));
    const data = await res.json();
    
    if (!data.success || !data.stocks || data.stocks.length === 0) {
      resultsDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #94a3b8;">No stocks found</div>';
      return;
    }
    
    // Store stocks in a global array for safe access
    window.searchResultsStocks = data.stocks;
    
    resultsDiv.innerHTML = data.stocks.map((stock, index) => `
      <div class="search-result-item" onclick="selectStockByIndex(${index})">
        <div class="search-result-symbol">${escapeHtml(stock.symbol)}</div>
        <div class="search-result-name">${escapeHtml(stock.name)}</div>
        <div class="search-result-sector">${escapeHtml(stock.sector || 'NSE')} ${stock.industry ? '· ' + escapeHtml(stock.industry) : ''}</div>
        ${stock.existing ? '<div class="search-result-existing"><i class="fa fa-check"></i> Already in system</div>' : ''}
      </div>
    `).join('');
  } catch (error) {
    resultsDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #f87171;">Error searching stocks</div>';
  }
}

function selectStock(stock) {
  console.log('selectStock called with:', stock);
  selectedStock = stock;
  
  // Update hidden inputs
  document.getElementById('addSymbol').value = stock.symbol;
  document.getElementById('addName').value = stock.name;
  document.getElementById('addExchange').value = stock.exchange || 'NSE';
  document.getElementById('addSector').value = stock.sector || '';
  
  // Update display
  document.getElementById('selSymbol').textContent = stock.symbol;
  document.getElementById('selName').textContent = stock.name;
  document.getElementById('selSector').textContent = stock.sector || 'N/A';
  document.getElementById('selIndustry').textContent = stock.industry || 'N/A';
  
  // Show selected info
  document.getElementById('selectedStockInfo').style.display = 'block';
  document.getElementById('searchResults').style.display = 'none';
  document.getElementById('stockSearch').value = '';
  
  // Enable/disable add button based on whether stock already exists
  const addBtn = document.getElementById('addStockBtn');
  const isExisting = stock.existing === true;
  addBtn.disabled = isExisting;
  
  console.log('Stock existing:', isExisting, 'Button disabled:', addBtn.disabled);
  
  if (isExisting) {
    const alert = document.getElementById('addAlert');
    alert.textContent = 'This stock is already in the system';
    alert.className = 'alert alert-success';
    alert.style.display = 'block';
  } else {
    document.getElementById('addAlert').style.display = 'none';
  }
}

async function addStock() {
  console.log('addStock called, selectedStock:', selectedStock);
  
  if (!selectedStock) {
    const a = document.getElementById('addAlert');
    a.textContent = 'Please search and select a stock first';
    a.className = 'alert alert-error';
    a.style.display = 'block';
    return;
  }
  
  const body = {
    symbol:       selectedStock.symbol,
    name:         selectedStock.name,
    exchange:     selectedStock.exchange || 'NSE',
    sector:       selectedStock.sector || '',
    isin:         '',
    ltp:          0,
    prev_close:   0,
  };
  
  console.log('Sending request with body:', body);
  
  try {
    const res = await fetch('../api/admin-add-stock.php', { 
      method: 'POST', 
      headers: {'Content-Type': 'application/json'}, 
      body: JSON.stringify(body) 
    });
    
    const data = await res.json();
    console.log('Response:', data);
    
    // Show alert with price info before reloading
    if (data.success && data.ltp > 0) {
      alert(`Stock added! Price: ₹${data.ltp}`);
    }
    
    if (data.success) { 
      location.reload(); 
    } else { 
      const a = document.getElementById('addAlert'); 
      a.textContent = data.message; 
      a.className = 'alert alert-error';
      a.style.display = 'block'; 
    }
  } catch (error) {
    console.error('Error:', error);
    const a = document.getElementById('addAlert'); 
    a.textContent = 'Network error: ' + error.message; 
    a.className = 'alert alert-error';
    a.style.display = 'block'; 
  }
}

async function updatePrice() {
  const body = {
    stock_id:   parseInt(document.getElementById('priceStockId').value),
    ltp:        parseFloat(document.getElementById('priceLtp').value),
    open_price: parseFloat(document.getElementById('priceOpen').value) || null,
    high_price: parseFloat(document.getElementById('priceHigh').value) || null,
    low_price:  parseFloat(document.getElementById('priceLow').value) || null,
    prev_close: parseFloat(document.getElementById('pricePrevClose').value) || null,
    volume:     parseInt(document.getElementById('priceVolume').value) || null,
  };
  const res  = await fetch('../api/admin-update-price.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) });
  const data = await res.json();
  if (data.success) { location.reload(); }
  else { const a = document.getElementById('priceAlert'); a.textContent = data.message; a.style.display = 'block'; }
}

async function toggleStock(id, current) {
  const res  = await fetch('../api/admin-toggle-stock.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ stock_id: id, is_active: current ? 0 : 1 }) });
  const data = await res.json();
  if (data.success) location.reload();
  else alert(data.message);
}

async function deleteStock(id, symbol) {
  if (!confirm(`Are you sure you want to delete ${symbol}?\n\nThis will remove the stock from the stocks page, but users who hold this stock will still see it in their portfolios.\n\nThis action cannot be undone.`)) {
    return;
  }
  
  const res = await fetch('../api/admin-delete-stock.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ stock_id: id })
  });
  
  const data = await res.json();
  if (data.success) {
    alert(data.message);
    location.reload();
  } else {
    alert('Error: ' + data.message);
  }
}

// Auto-refresh page every 10 minutes (600,000 milliseconds)
setInterval(() => {
  location.reload();
}, 600000);

// Update countdown timer display
let timeLeft = 600;
const timerDisplay = document.getElementById('refreshTimer');
setInterval(() => {
  timeLeft--;
  if (timeLeft <= 0) timeLeft = 600;
  const minutes = Math.floor(timeLeft / 60);
  const seconds = timeLeft % 60;
  timerDisplay.innerHTML = `<i class="fa fa-sync" style="margin-right:5px"></i>Auto-refresh in ${minutes}:${seconds.toString().padStart(2, '0')}`;
}, 1000);

document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', function(e) {
  if (e.target === this) closeModals();
}));
</script>

<script src="../public/assets/js/yahoo-finance.js"></script>
<script>
// Live price updates via Yahoo Finance API (Free, 15-20 min delayed)
const stockSymbols = <?= json_encode(array_column($stocks, 'symbol')) ?>;

YahooFinanceAPI.init();
YahooFinanceAPI.subscribe(stockSymbols, (symbol, data) => {
  console.log('Price update received:', symbol, data);
  
  // Find the row with this symbol
  const row = document.querySelector(`tr[data-symbol="${symbol}"]`);
  if (!row) {
    console.log('Row not found for symbol:', symbol);
    return;
  }
  
  const priceCell = row.querySelector('.price-cell');
  const changeCell = row.querySelector('.change-cell');
  if (!priceCell || !changeCell) return;
  
  const oldLtp = parseFloat(priceCell.dataset.ltp) || 0;
  const newLtp = data.ltp;
  const chgPct = data.changePercent;
  
  // Flash effect
  priceCell.classList.remove('flash-up', 'flash-down');
  void priceCell.offsetWidth;
  priceCell.classList.add(newLtp >= oldLtp ? 'flash-up' : 'flash-down');
  setTimeout(() => priceCell.classList.remove('flash-up', 'flash-down'), 1200);
  
  // Update price display
  priceCell.textContent = '₹' + newLtp.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  priceCell.dataset.ltp = newLtp;
  
  // Update change percentage from API data
  const chgClass = chgPct >= 0 ? 'pos' : 'neg';
  changeCell.className = 'change-cell ' + chgClass;
  changeCell.textContent = (chgPct >= 0 ? '+' : '') + chgPct.toFixed(2) + '%';
  changeCell.dataset.chg = chgPct;
});


</script>
<style>
.flash-up { color: #4ade80 !important; transition: color 0.3s; }
.flash-down { color: #f87171 !important; transition: color 0.3s; }
</style>
</body>
</html>
