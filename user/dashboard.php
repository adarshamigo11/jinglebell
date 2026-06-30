<?php
require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
$db   = getDB();

// Holdings summary
$holdingsCount = $db->prepare("SELECT COUNT(*) FROM user_holdings WHERE user_id = ? AND quantity > 0");
$holdingsCount->execute([$user['id']]);
$holdingsCount = $holdingsCount->fetchColumn();

// Pending orders (positions)
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
<title>Dashboard — TradeZenfy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../public/assets/css/groww-ui.css">
<link rel="stylesheet" href="../public/assets/css/layout-new.css">
<style>
body { background: var(--bg); }

/* Skeleton Loading */
.skeleton-block {
  background: linear-gradient(90deg, #E5E7EB 25%, #F3F4F6 50%, #E5E7EB 75%);
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.5s ease-in-out infinite;
  border-radius: 6px;
}
@keyframes skeleton-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
.skeleton-row td { padding: 16px 12px; }

/* ===== LIVE PRICE FLASH ANIMATIONS ===== */
@keyframes flash-green {
  0% { background-color: rgba(16,185,129,0.35); }
  100% { background-color: transparent; }
}
@keyframes flash-red {
  0% { background-color: rgba(239,68,68,0.35); }
  100% { background-color: transparent; }
}
.price-flash-up {
  animation: flash-green 1.2s ease-out;
}
.price-flash-down {
  animation: flash-red 1.2s ease-out;
}
.ltp-value, .card-value {
  transition: color 0.3s ease;
  border-radius: 4px;
  padding: 2px 4px;
}
.live-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 600;
  color: #059669;
  background: rgba(5,150,105,0.08);
  padding: 3px 10px;
  border-radius: 20px;
  margin-left: 8px;
}
.live-badge::before {
  content: '';
  width: 6px; height: 6px;
  background: #059669;
  border-radius: 50%;
  animation: live-pulse 1.5s ease-in-out infinite;
}
.market-closed-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 600;
  color: #6B7280;
  background: rgba(107,114,128,0.08);
  padding: 3px 10px;
  border-radius: 20px;
  margin-left: 8px;
}

/* ===== MOBILE LIVE MARKET CARDS ===== */
.mobile-market-section { display: none; }

@media (max-width: 768px) {
  /* Hide desktop market section on mobile */
  .market-section { display: none !important; }
  /* Show mobile market section */
  .mobile-market-section { display: block; }

  /* Summary cards mobile */
  .summary-cards { grid-template-columns: 1fr; gap: 12px; }
  .summary-card { padding: 16px; }
  .summary-card-value { font-size: 20px; }

  /* Mobile market header */
  .mobile-market-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
    padding: 0 4px;
  }
  .mobile-market-title {
    font-size: 17px;
    font-weight: 700;
    color: var(--text);
  }
  .mobile-market-live {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 600;
    color: var(--primary);
    background: rgba(37,99,235,0.08);
    padding: 4px 10px;
    border-radius: 20px;
  }
  .mobile-market-live::before {
    content: '';
    width: 6px; height: 6px;
    background: var(--primary);
    border-radius: 50%;
    animation: live-pulse 1.5s ease-in-out infinite;
  }
  @keyframes live-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
  }

  /* Mobile tabs */
  .mobile-market-tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 14px;
    overflow-x: auto;
    padding-bottom: 4px;
    -webkit-overflow-scrolling: touch;
  }
  .mobile-market-tabs::-webkit-scrollbar { display: none; }
  .mobile-market-tab {
    padding: 7px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid var(--border);
    background: var(--card);
    color: var(--text-secondary);
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s;
  }
  .mobile-market-tab.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
  }

  /* Card grid */
  .mobile-card-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }

  /* Market card */
  .mobile-market-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    cursor: pointer;
    transition: all 0.15s;
    text-decoration: none;
    color: inherit;
    display: block;
    position: relative;
    overflow: hidden;
  }
  .mobile-market-card:active {
    transform: scale(0.97);
    background: var(--hover);
  }
  .mobile-market-card .card-badge {
    width: 32px; height: 32px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 10px;
    letter-spacing: 0.3px;
  }
  .card-badge-blue { background: #2563EB; }
  .card-badge-purple { background: #7C3AED; }
  .card-badge-teal { background: #0D9488; }
  .card-badge-orange { background: #D97706; }
  .card-badge-pink { background: #DB2777; }
  .card-badge-green { background: #059669; }
  .card-badge-amber { background: #B45309; }

  .mobile-market-card .card-name {
    font-size: 12px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .mobile-market-card .card-value {
    font-size: 15px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 2px;
  }
  .mobile-market-card .card-change {
    font-size: 11px;
    font-weight: 600;
  }
  .card-change.positive { color: #10B981; }
  .card-change.negative { color: #EF4444; }

  /* Skeleton cards for mobile */
  .mobile-skeleton-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
  }
  .mobile-skeleton-card .skel-badge {
    width: 32px; height: 32px;
    border-radius: 10px;
    margin-bottom: 10px;
  }
  .mobile-skeleton-card .skel-name {
    height: 12px;
    width: 60%;
    margin-bottom: 6px;
  }
  .mobile-skeleton-card .skel-value {
    height: 16px;
    width: 80%;
    margin-bottom: 4px;
  }
  .mobile-skeleton-card .skel-change {
    height: 11px;
    width: 45%;
  }

  /* Stock movers mobile layout */
  .mobile-movers-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 14px;
  }
  .mobile-movers-col-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 8px;
    padding-left: 2px;
  }
  .mobile-mover-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 12px;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.15s;
  }
  .mobile-mover-card:active { transform: scale(0.97); background: var(--hover); }
  .mobile-mover-card .mover-name {
    font-size: 12px;
    font-weight: 600;
    color: var(--text);
  }
  .mobile-mover-card .mover-sub {
    font-size: 10px;
    color: var(--text-secondary);
    margin-top: 1px;
  }
  .mobile-mover-card .mover-pct {
    font-size: 12px;
    font-weight: 700;
    text-align: right;
  }

  /* "View all" link */
  .mobile-view-all {
    display: block;
    text-align: center;
    padding: 10px;
    margin-top: 12px;
    color: var(--primary);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--card);
  }
  .mobile-view-all:active { background: var(--hover); }
}

@media (max-width: 480px) {
  .summary-card-value { font-size: 18px; }
  .mobile-market-title { font-size: 15px; }
  .mobile-market-card { padding: 12px; }
  .mobile-market-card .card-value { font-size: 14px; }
}
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/user-top-nav.php'; ?>

<div class="main-content">
  <!-- Summary Cards -->
  <div class="summary-cards">
    <div class="summary-card">
      <div class="summary-card-header">
        <div class="summary-card-title">
          Holdings <i class="fa fa-chevron-right" style="font-size: 12px;"></i>
        </div>
      </div>
      <div class="summary-card-value"><?= $holdingsCount ?></div>
      <div class="summary-card-subtitle">
        <?php if ($holdingsCount > 0): ?>
          <a href="portfolio.php" style="color: var(--primary); text-decoration: none; font-weight: 600;">Explore Screeners →</a>
        <?php else: ?>
          You don't have any Holdings.
        <?php endif; ?>
      </div>
    </div>

    <div class="summary-card">
      <div class="summary-card-header">
        <div class="summary-card-title">
          Positions <i class="fa fa-chevron-right" style="font-size: 12px;"></i>
        </div>
      </div>
      <div class="summary-card-value"><?= $pendingOrders ?></div>
      <div class="summary-card-subtitle">
        <?php if ($pendingOrders > 0): ?>
          <a href="orders.php" style="color: var(--primary); text-decoration: none; font-weight: 600;">View positions →</a>
        <?php else: ?>
          You don't have any positions yet.
        <?php endif; ?>
      </div>
    </div>

    <div class="summary-card">
      <div class="summary-card-header">
        <div class="summary-card-title">
          Funds <i class="fa fa-chevron-right" style="font-size: 12px;"></i>
        </div>
      </div>
      <div class="summary-card-value"><?= formatINR($availableBalance) ?></div>
      <div class="summary-card-subtitle">Available</div>
      <button class="summary-card-action" onclick="window.location.href='deposit.php'" style="margin-top: 12px;">
        + Add
      </button>
    </div>
  </div>

  <!-- Market Overview Section -->
  <div class="market-section">
    <h2 class="market-section-title">Market overview</h2>
    
    <div class="market-tabs">
      <button class="market-tab active" onclick="switchTab('indices')">Indices</button>
      <button class="market-tab" onclick="switchTab('stocks')">Stocks</button>
      <button class="market-tab" onclick="switchTab('commodities')">Commodities</button>
      <button class="market-tab" onclick="switchTab('global')">Global</button>
    </div>

    <!-- Indices Tab -->
    <div id="tab-indices" class="tab-content">
      <table class="market-table" id="indicesTable">
        <thead>
          <tr>
            <th>Index</th>
            <th>LTP / Change</th>
            <th>Day Range</th>
            <th>High</th>
            <th>Volume</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="indicesBody">
          <tr class="skeleton-row"><td><div class="skeleton-block" style="width:120px;height:18px;"></div></td><td><div class="skeleton-block" style="width:90px;height:18px;"></div></td><td><div class="skeleton-block" style="width:100px;height:16px;"></div></td><td><div class="skeleton-block" style="width:90px;height:18px;"></div></td><td><div class="skeleton-block" style="width:80px;height:16px;"></div></td><td><div class="skeleton-block" style="width:60px;height:24px;border-radius:6px;"></div></td></tr>
          <tr class="skeleton-row"><td><div class="skeleton-block" style="width:110px;height:18px;"></div></td><td><div class="skeleton-block" style="width:85px;height:18px;"></div></td><td><div class="skeleton-block" style="width:95px;height:16px;"></div></td><td><div class="skeleton-block" style="width:88px;height:18px;"></div></td><td><div class="skeleton-block" style="width:75px;height:16px;"></div></td><td><div class="skeleton-block" style="width:60px;height:24px;border-radius:6px;"></div></td></tr>
          <tr class="skeleton-row"><td><div class="skeleton-block" style="width:115px;height:18px;"></div></td><td><div class="skeleton-block" style="width:92px;height:18px;"></div></td><td><div class="skeleton-block" style="width:98px;height:16px;"></div></td><td><div class="skeleton-block" style="width:92px;height:18px;"></div></td><td><div class="skeleton-block" style="width:78px;height:16px;"></div></td><td><div class="skeleton-block" style="width:60px;height:24px;border-radius:6px;"></div></td></tr>
        </tbody>
      </table>
    </div>

    <!-- Stocks Tab -->
    <div id="tab-stocks" class="tab-content" style="display: none;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h3 style="font-size: 18px; color: var(--text); margin: 0;">Stock Market</h3>
        <a href="../stock-market.php" style="display: inline-block; padding: 10px 24px; background: var(--primary); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">
          <i class="fa fa-external-link-alt" style="margin-right: 6px;"></i> Visit Stock Page
        </a>
      </div>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px;">
        <!-- Top Gainers -->
        <div>
          <h3 style="margin-bottom: 12px; font-size: 16px; color: var(--text);">Top Gainers</h3>
          <table class="market-table" id="gainersTable">
            <thead>
              <tr>
                <th>Stock</th>
                <th>LTP</th>
                <th>Change %</th>
              </tr>
            </thead>
            <tbody id="gainersBody">
              <tr>
                <td colspan="3" style="text-align: center; padding: 20px;">
                  <i class="fa fa-spinner fa-spin" style="font-size: 18px; color: var(--text-secondary);"></i>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Top Losers -->
        <div>
          <h3 style="margin-bottom: 12px; font-size: 16px; color: var(--text);">Top Losers</h3>
          <table class="market-table" id="losersTable">
            <thead>
              <tr>
                <th>Stock</th>
                <th>LTP</th>
                <th>Change %</th>
              </tr>
            </thead>
            <tbody id="losersBody">
              <tr>
                <td colspan="3" style="text-align: center; padding: 20px;">
                  <i class="fa fa-spinner fa-spin" style="font-size: 18px; color: var(--text-secondary);"></i>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- All Stocks Section -->
      <div>
        <h3 style="margin-bottom: 12px; font-size: 16px; color: var(--text);">All Stocks</h3>
        <div style="max-height: 500px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px;">
          <table class="market-table" id="allStocksTable" style="width: 100%;">
            <thead style="position: sticky; top: 0; background: var(--bg-card); z-index: 1;">
              <tr>
                <th>Stock</th>
                <th>LTP</th>
                <th>Change %</th>
                <th>Volume</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="allStocksBody">
              <tr>
                <td colspan="5" style="text-align: center; padding: 20px;">
                  <i class="fa fa-spinner fa-spin" style="font-size: 18px; color: var(--text-secondary);"></i>
                  Loading all stocks...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Commodities Tab -->
    <div id="tab-commodities" class="tab-content" style="display: none;">
      <div style="display: flex; justify-content: flex-end; margin-bottom: 16px;">
        <a href="../commodity-market.php" style="display: inline-block; padding: 10px 24px; background: var(--primary); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">
          <i class="fa fa-external-link-alt" style="margin-right: 6px;"></i> Visit Commodities Page
        </a>
      </div>
      <table class="market-table" id="commoditiesTable">
        <thead>
          <tr>
            <th>Commodity</th>
            <th>Exchange</th>
            <th>LTP</th>
            <th>Change %</th>
            <th>Trade</th>
          </tr>
        </thead>
        <tbody id="commoditiesBody">
          <tr>
            <td colspan="5" style="text-align: center; padding: 20px;">
              <i class="fa fa-spinner fa-spin" style="font-size: 18px; color: var(--text-secondary);"></i>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Crypto Tab -->
    <div id="tab-global" class="tab-content" style="display: none;">
      <div style="display: flex; justify-content: flex-end; margin-bottom: 16px;">
        <a href="../crypto-market.php" style="display: inline-block; padding: 10px 24px; background: var(--primary); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">
          <i class="fa fa-external-link-alt" style="margin-right: 6px;"></i> Visit Crypto Page
        </a>
      </div>
      <table class="market-table" id="cryptoTable">
        <thead>
          <tr>
            <th>Cryptocurrency</th>
            <th>Exchange</th>
            <th>LTP</th>
            <th>Change %</th>
            <th>Trade</th>
          </tr>
        </thead>
        <tbody id="cryptoBody">
          <tr>
            <td colspan="5" style="text-align: center; padding: 20px;">
              <i class="fa fa-spinner fa-spin" style="font-size: 18px; color: var(--text-secondary);"></i>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ===== MOBILE-ONLY LIVE MARKET SECTION ===== -->
  <div class="mobile-market-section">
    <div class="mobile-market-header">
      <span class="mobile-market-title">Live Market</span>
      <span class="mobile-market-live">LIVE</span>
    </div>

    <!-- Mobile Tabs -->
    <div class="mobile-market-tabs">
      <button class="mobile-market-tab active" data-tab="m-indices" onclick="mobileSwitchTab('m-indices', this)">Nifty</button>
      <button class="mobile-market-tab" data-tab="m-stocks" onclick="mobileSwitchTab('m-stocks', this)">Stocks</button>
      <button class="mobile-market-tab" data-tab="m-commodities" onclick="mobileSwitchTab('m-commodities', this)">Commodities</button>
      <button class="mobile-market-tab" data-tab="m-crypto" onclick="mobileSwitchTab('m-crypto', this)">Crypto</button>
    </div>

    <!-- Mobile Indices Cards -->
    <div id="m-indices" class="mobile-tab-content">
      <div class="mobile-card-grid" id="mobileIndicesGrid">
        <!-- Skeleton cards -->
        <div class="mobile-skeleton-card">
          <div class="skel-badge skeleton-block"></div>
          <div class="skel-name skeleton-block"></div>
          <div class="skel-value skeleton-block"></div>
          <div class="skel-change skeleton-block"></div>
        </div>
        <div class="mobile-skeleton-card">
          <div class="skel-badge skeleton-block"></div>
          <div class="skel-name skeleton-block"></div>
          <div class="skel-value skeleton-block"></div>
          <div class="skel-change skeleton-block"></div>
        </div>
        <div class="mobile-skeleton-card">
          <div class="skel-badge skeleton-block"></div>
          <div class="skel-name skeleton-block"></div>
          <div class="skel-value skeleton-block"></div>
          <div class="skel-change skeleton-block"></div>
        </div>
        <div class="mobile-skeleton-card">
          <div class="skel-badge skeleton-block"></div>
          <div class="skel-name skeleton-block"></div>
          <div class="skel-value skeleton-block"></div>
          <div class="skel-change skeleton-block"></div>
        </div>
        <div class="mobile-skeleton-card">
          <div class="skel-badge skeleton-block"></div>
          <div class="skel-name skeleton-block"></div>
          <div class="skel-value skeleton-block"></div>
          <div class="skel-change skeleton-block"></div>
        </div>
        <div class="mobile-skeleton-card">
          <div class="skel-badge skeleton-block"></div>
          <div class="skel-name skeleton-block"></div>
          <div class="skel-value skeleton-block"></div>
          <div class="skel-change skeleton-block"></div>
        </div>
      </div>
    </div>

    <!-- Mobile Stocks Cards -->
    <div id="m-stocks" class="mobile-tab-content" style="display:none;">
      <div class="mobile-movers-row">
        <div>
          <div class="mobile-movers-col-title" style="color:#10B981;">Top Gainers</div>
          <div id="mobileGainers" style="display:flex;flex-direction:column;gap:6px;"></div>
        </div>
        <div>
          <div class="mobile-movers-col-title" style="color:#EF4444;">Top Losers</div>
          <div id="mobileLosers" style="display:flex;flex-direction:column;gap:6px;"></div>
        </div>
      </div>
      <a href="../stock-market.php" class="mobile-view-all">View All Stocks <i class="fa fa-arrow-right" style="margin-left:4px;font-size:11px;"></i></a>
    </div>

    <!-- Mobile Commodities Cards -->
    <div id="m-commodities" class="mobile-tab-content" style="display:none;">
      <div class="mobile-card-grid" id="mobileCommoditiesGrid">
        <div class="mobile-skeleton-card">
          <div class="skel-badge skeleton-block"></div>
          <div class="skel-name skeleton-block"></div>
          <div class="skel-value skeleton-block"></div>
          <div class="skel-change skeleton-block"></div>
        </div>
        <div class="mobile-skeleton-card">
          <div class="skel-badge skeleton-block"></div>
          <div class="skel-name skeleton-block"></div>
          <div class="skel-value skeleton-block"></div>
          <div class="skel-change skeleton-block"></div>
        </div>
        <div class="mobile-skeleton-card">
          <div class="skel-badge skeleton-block"></div>
          <div class="skel-name skeleton-block"></div>
          <div class="skel-value skeleton-block"></div>
          <div class="skel-change skeleton-block"></div>
        </div>
        <div class="mobile-skeleton-card">
          <div class="skel-badge skeleton-block"></div>
          <div class="skel-name skeleton-block"></div>
          <div class="skel-value skeleton-block"></div>
          <div class="skel-change skeleton-block"></div>
        </div>
      </div>
      <a href="../commodity-market.php" class="mobile-view-all">View All Commodities <i class="fa fa-arrow-right" style="margin-left:4px;font-size:11px;"></i></a>
    </div>

    <!-- Mobile Crypto Cards -->
    <div id="m-crypto" class="mobile-tab-content" style="display:none;">
      <div class="mobile-card-grid" id="mobileCryptoGrid">
        <div class="mobile-skeleton-card">
          <div class="skel-badge skeleton-block"></div>
          <div class="skel-name skeleton-block"></div>
          <div class="skel-value skeleton-block"></div>
          <div class="skel-change skeleton-block"></div>
        </div>
        <div class="mobile-skeleton-card">
          <div class="skel-badge skeleton-block"></div>
          <div class="skel-name skeleton-block"></div>
          <div class="skel-value skeleton-block"></div>
          <div class="skel-change skeleton-block"></div>
        </div>
        <div class="mobile-skeleton-card">
          <div class="skel-badge skeleton-block"></div>
          <div class="skel-name skeleton-block"></div>
          <div class="skel-value skeleton-block"></div>
          <div class="skel-change skeleton-block"></div>
        </div>
        <div class="mobile-skeleton-card">
          <div class="skel-badge skeleton-block"></div>
          <div class="skel-name skeleton-block"></div>
          <div class="skel-value skeleton-block"></div>
          <div class="skel-change skeleton-block"></div>
        </div>
      </div>
      <a href="../crypto-market.php" class="mobile-view-all">View All Crypto <i class="fa fa-arrow-right" style="margin-left:4px;font-size:11px;"></i></a>
    </div>
  </div>
</div>

<script>
// Tab switching
function switchTab(tabName) {
  // Update active tab
  document.querySelectorAll('.market-tab').forEach(tab => {
    tab.classList.remove('active');
  });
  event.target.classList.add('active');
  
  // Show/hide content
  document.querySelectorAll('.tab-content').forEach(content => {
    content.style.display = 'none';
  });
  document.getElementById('tab-' + tabName).style.display = 'block';

  // Load stock movers when stocks tab is opened
  if (tabName === 'stocks') {
    loadMovers('gainers');
    loadMovers('losers');
    loadAllStocks();
  }
  
  // Load commodities when commodities tab is opened
  if (tabName === 'commodities') {
    loadCommodities();
  }
  
  // Load crypto when global tab is opened
  if (tabName === 'global') {
    loadCryptos();
  }
}

// Load top movers (gainers/losers)
async function loadMovers(category, isRefresh = false) {
  const tbodyId = category === 'gainers' ? 'gainersBody' : 'losersBody';
  try {
    const response = await fetch('../api/yahoo-top-movers.php?category=' + category);
    const data = await response.json();
    
    if (data.success && data.movers && data.movers.length > 0) {
      const tbody = document.getElementById(tbodyId);
      
      if (!isRefresh) {
        tbody.innerHTML = '';
        data.movers.forEach(stock => {
          const isPositive = stock.changePercent >= 0;
          const changeClass = isPositive ? 'positive' : 'negative';
          const changeIcon = isPositive ? '▲' : '▼';
          const row = document.createElement('tr');
          row.onclick = () => window.location.href = '../stock-detail.php?id=' + stock.id;
          row.style.cursor = 'pointer';
          row.innerHTML = `
            <td>
              <div class="index-name">${stock.symbol}</div>
              <div style="font-size: 11px; color: var(--text-secondary);">${stock.name}</div>
            </td>
            <td>
              <div class="ltp-value">₹${stock.price.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
            </td>
            <td>
              <div class="ltp-change ${changeClass}">
                ${changeIcon} ${isPositive ? '+' : ''}${stock.changePercent.toFixed(2)}%
              </div>
            </td>
          `;
          tbody.appendChild(row);
          _prevMoverPrices[stock.symbol] = stock.price;
        });
      } else {
        // Smart update: compare prices and flash
        data.movers.forEach((stock, i) => {
          const row = tbody.rows[i];
          if (!row) return;
          const ltpCell = row.querySelector('.ltp-value');
          const changeCell = row.querySelector('.ltp-change');
          if (!ltpCell) return;
          
          const oldPrice = _prevMoverPrices[stock.symbol] || stock.price;
          if (stock.price !== oldPrice) {
            const wentUp = stock.price > oldPrice;
            ltpCell.classList.remove('price-flash-up', 'price-flash-down');
            void ltpCell.offsetWidth;
            ltpCell.classList.add(wentUp ? 'price-flash-up' : 'price-flash-down');
          }
          
          const isPositive = stock.changePercent >= 0;
          const changeIcon = isPositive ? '▲' : '▼';
          ltpCell.textContent = '₹' + stock.price.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
          changeCell.className = 'ltp-change ' + (isPositive ? 'positive' : 'negative');
          changeCell.textContent = changeIcon + ' ' + (isPositive ? '+' : '') + stock.changePercent.toFixed(2) + '%';
          _prevMoverPrices[stock.symbol] = stock.price;
        });
      }
    } else if (!isRefresh) {
      document.getElementById(tbodyId).innerHTML = `
        <tr>
          <td colspan="3" style="text-align: center; padding: 20px; color: var(--text-secondary);">
            No ${category} data available.
          </td>
        </tr>
      `;
    }
  } catch (error) {
    console.error('Error loading ' + category + ':', error);
    if (!isRefresh) {
      document.getElementById(tbodyId).innerHTML = `
        <tr>
          <td colspan="3" style="text-align: center; padding: 20px; color: var(--danger);">
            Failed to load data.
          </td>
        </tr>
      `;
    }
  }
}

// Load all stocks list
async function loadAllStocks() {
  const tbody = document.getElementById('allStocksBody');
  try {
    const response = await fetch('../api/get-all-stocks.php');
    const data = await response.json();
    
    if (data.success && data.stocks && data.stocks.length > 0) {
      tbody.innerHTML = '';
      data.stocks.forEach(stock => {
        const isPositive = stock.change_percent >= 0;
        const changeClass = isPositive ? 'positive' : 'negative';
        const changeIcon = isPositive ? '▲' : '▼';
        const row = document.createElement('tr');
        row.style.cursor = 'pointer';
        row.onclick = () => window.location.href = '../stock-detail.php?id=' + stock.id;
        row.innerHTML = `
          <td>
            <div class="index-name">${stock.symbol}</div>
            <div style="font-size: 11px; color: var(--text-secondary);">${stock.name}</div>
          </td>
          <td>
            <div class="ltp-value">₹${stock.price.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
          </td>
          <td>
            <div class="ltp-change ${changeClass}">
              ${changeIcon} ${isPositive ? '+' : ''}${stock.change_percent.toFixed(2)}%
            </div>
          </td>
          <td>
            <div style="font-size: 12px; color: var(--text-secondary);">
              ${stock.volume ? stock.volume.toLocaleString('en-IN') : '-'}
            </div>
          </td>
          <td>
            <a href="../stock-detail.php?id=${stock.id}" class="btn-watchlist" style="padding: 4px 12px; font-size: 12px;">View</a>
          </td>
        `;
        tbody.appendChild(row);
      });
    } else {
      tbody.innerHTML = `
        <tr>
          <td colspan="5" style="text-align: center; padding: 20px; color: var(--text-secondary);">
            No stocks available.
          </td>
        </tr>
      `;
    }
  } catch (error) {
    console.error('Error loading all stocks:', error);
    tbody.innerHTML = `
      <tr>
        <td colspan="5" style="text-align: center; padding: 20px; color: var(--danger);">
          Failed to load stocks. Please try again.
        </td>
      </tr>
    `;
  }
}

// Load commodities data
async function loadCommodities() {
  try {
    const response = await fetch('../api/get-commodities.php');
    const data = await response.json();
    
    if (data.success && data.commodities && data.commodities.length > 0) {
      const tbody = document.getElementById('commoditiesBody');
      tbody.innerHTML = '';
      
      data.commodities.forEach(cmd => {
        const isPositive = cmd.change_percent >= 0;
        const changeClass = isPositive ? 'positive' : 'negative';
        const changeIcon = isPositive ? '▲' : '▼';
        
        const row = document.createElement('tr');
        row.onclick = () => window.location.href = '../commodity-detail.php?id=' + cmd.id;
        row.style.cursor = 'pointer';
        
        row.innerHTML = `
          <td>
            <div class="index-name">${cmd.symbol}</div>
            <div style="font-size: 11px; color: var(--text-secondary);">${cmd.name}</div>
          </td>
          <td>
            <span style="font-size: 12px; color: var(--text-secondary); background: var(--hover); padding: 2px 8px; border-radius: 12px;">${cmd.exchange}</span>
          </td>
          <td>
            <div class="ltp-value">₹${parseFloat(cmd.ltp).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
          </td>
          <td>
            <div class="ltp-change ${changeClass}">
              ${changeIcon} ${isPositive ? '+' : ''}${parseFloat(cmd.change_percent).toFixed(2)}%
            </div>
          </td>
          <td onclick="event.stopPropagation()">
            <a href="../commodity-detail.php?id=${cmd.id}" style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; background: var(--primary); color: white; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; white-space: nowrap;">
              <i class="fa fa-bolt"></i> Trade
            </a>
          </td>
        `;
        
        tbody.appendChild(row);
      });
    } else {
      document.getElementById('commoditiesBody').innerHTML = `
        <tr>
          <td colspan="5" style="text-align: center; padding: 20px; color: var(--text-secondary);">
            No commodities data available.
          </td>
        </tr>
      `;
    }
  } catch (error) {
    console.error('Error loading commodities:', error);
    document.getElementById('commoditiesBody').innerHTML = `
      <tr>
        <td colspan="5" style="text-align: center; padding: 20px; color: var(--danger);">
          Failed to load commodities data.
        </td>
      </tr>
    `;
  }
}

// Load crypto data
async function loadCryptos() {
  try {
    const response = await fetch('../api/get-cryptos.php');
    const data = await response.json();
    
    if (data.success && data.cryptos && data.cryptos.length > 0) {
      const tbody = document.getElementById('cryptoBody');
      tbody.innerHTML = '';
      
      data.cryptos.forEach(crypto => {
        const isPositive = crypto.change_percent >= 0;
        const changeClass = isPositive ? 'positive' : 'negative';
        const changeIcon = isPositive ? '▲' : '▼';
        
        const row = document.createElement('tr');
        row.onclick = () => window.location.href = '../crypto-detail.php?id=' + crypto.id;
        row.style.cursor = 'pointer';
        
        row.innerHTML = `
          <td>
            <div class="index-name">${crypto.symbol}</div>
            <div style="font-size: 11px; color: var(--text-secondary);">${crypto.name}</div>
          </td>
          <td>
            <span style="font-size: 12px; color: var(--text-secondary); background: var(--hover); padding: 2px 8px; border-radius: 12px;">${crypto.exchange}</span>
          </td>
          <td>
            <div class="ltp-value">₹${parseFloat(crypto.ltp).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
          </td>
          <td>
            <div class="ltp-change ${changeClass}">
              ${changeIcon} ${isPositive ? '+' : ''}${parseFloat(crypto.change_percent).toFixed(2)}%
            </div>
          </td>
          <td onclick="event.stopPropagation()">
            <a href="../crypto-detail.php?id=${crypto.id}" style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; background: var(--primary); color: white; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; white-space: nowrap;">
              <i class="fa fa-bolt"></i> Trade
            </a>
          </td>
        `;
        
        tbody.appendChild(row);
      });
    } else {
      document.getElementById('cryptoBody').innerHTML = `
        <tr>
          <td colspan="5" style="text-align: center; padding: 20px; color: var(--text-secondary);">
            No crypto data available.
          </td>
        </tr>
      `;
    }
  } catch (error) {
    console.error('Error loading cryptos:', error);
    document.getElementById('cryptoBody').innerHTML = `
      <tr>
        <td colspan="5" style="text-align: center; padding: 20px; color: var(--danger);">
          Failed to load crypto data.
        </td>
      </tr>
    `;
  }
}

// Load indices data
async function loadIndices(isRefresh = false) {
  try {
    const response = await fetch('../api/get-indexes.php');
    const data = await response.json();
    
    if (data.success && data.indexes.length > 0) {
      const tbody = document.getElementById('indicesBody');
      
      if (!isRefresh) {
        tbody.innerHTML = '';
        data.indexes.forEach(index => {
          const row = buildIndexRow(index);
          tbody.appendChild(row);
        });
      } else {
        // Smart update: compare prices and flash
        data.indexes.forEach((index, i) => {
          const row = tbody.rows[i];
          if (!row) return;
          const ltpCell = row.querySelector('.ltp-value');
          const changeCell = row.querySelector('.ltp-change');
          if (!ltpCell) return;
          
          const oldPrice = _prevIndexPrices[index.symbol] || index.price;
          const newPrice = index.price;
          
          if (newPrice !== oldPrice) {
            const wentUp = newPrice > oldPrice;
            ltpCell.classList.remove('price-flash-up', 'price-flash-down');
            void ltpCell.offsetWidth; // force reflow
            ltpCell.classList.add(wentUp ? 'price-flash-up' : 'price-flash-down');
          }
          
          const isPositive = (index.change || 0) >= 0;
          const changeClass = isPositive ? 'positive' : 'negative';
          const changeIcon = isPositive ? '▲' : '▼';
          const chgPct = index.change_percent || 0;
          ltpCell.textContent = '₹' + newPrice.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
          changeCell.className = 'ltp-change ' + changeClass;
          changeCell.textContent = changeIcon + ' ' + Math.abs(index.change || 0).toFixed(2) + ' (' + (isPositive ? '+' : '') + chgPct.toFixed(2) + '%)';
          
          // Update range bar
          const range = index.high - index.low;
          const position = range > 0 ? ((newPrice - index.low) / range) * 100 : 50;
          const marker = row.querySelector('.range-bar-marker');
          if (marker) marker.style.left = position + '%';
          
          _prevIndexPrices[index.symbol] = newPrice;
        });
        // Update live badge
        updateMarketStatus(true);
      }
      // Store prices on first load too
      if (!isRefresh) {
        data.indexes.forEach(index => {
          _prevIndexPrices[index.symbol] = index.price;
        });
        updateMarketStatus(true);
      }
    } else {
      if (!isRefresh) {
        document.getElementById('indicesBody').innerHTML = `
          <tr>
            <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);">
              No indices data available.
            </td>
          </tr>
        `;
      }
    }
  } catch (error) {
    console.error('Error loading indices:', error);
    if (!isRefresh) {
      document.getElementById('indicesBody').innerHTML = `
        <tr>
          <td colspan="6" style="text-align: center; padding: 40px; color: var(--danger);">
            Failed to load indices data. Please try again.
          </td>
        </tr>
      `;
    }
  }
}

function buildIndexRow(index) {
  const change = index.change || 0;
  const changePct = index.change_percent || 0;
  const high = index.high || index.price || 0;
  const low = index.low || index.price || 0;
  const isPositive = change >= 0;
  const changeClass = isPositive ? 'positive' : 'negative';
  const changeIcon = isPositive ? '▲' : '▼';
  const range = high - low;
  const position = range > 0 ? (((index.price || 0) - low) / range) * 100 : 50;

  const row = document.createElement('tr');
  row.onclick = () => window.location.href = '../index-detail.php?symbol=' + encodeURIComponent(index.symbol);
  row.style.cursor = 'pointer';
  row.innerHTML = `
    <td><div class="index-name">${index.name}</div></td>
    <td>
      <div class="ltp-value">₹${(index.price || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
      <div class="ltp-change ${changeClass}">
        ${changeIcon} ${Math.abs(change).toFixed(2)} (${isPositive ? '+' : ''}${changePct.toFixed(2)}%)
      </div>
    </td>
    <td>
      <div class="range-bar">
        <span style="font-size: 13px; min-width: 70px;">₹${low.toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
        <div class="range-bar-track"><div class="range-bar-marker" style="left: ${position}%;"></div></div>
      </div>
    </td>
    <td style="font-weight: 600;">₹${high.toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
    <td>
      <span style="font-size: 12px; color: var(--text-secondary);">${(index.volume || 0).toLocaleString('en-IN')}</span>
    </td>
    <td onclick="event.stopPropagation()">
      <a href="../index-detail.php?symbol=${encodeURIComponent(index.symbol)}" style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; background: var(--primary); color: white; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; white-space: nowrap;">
        <i class="fa fa-chart-line"></i> Details
      </a>
    </td>
  `;
  return row;
}

// ===== LIVE PRICE REFRESH SYSTEM =====
const _prevIndexPrices = {};
const _prevMoverPrices = {};
let _liveRefreshInterval = null;
let _moversRefreshInterval = null;

function isMarketHours() {
  // NSE market hours: 9:15 AM - 3:30 PM IST (Mon-Fri)
  const now = new Date();
  const istOffset = 5.5 * 60; // IST = UTC + 5:30
  const utcMinutes = now.getUTCHours() * 60 + now.getUTCMinutes();
  const istMinutes = (utcMinutes + istOffset) % (24 * 60);
  const istHour = Math.floor(istMinutes / 60);
  const istMin = istMinutes % 60;
  const istDay = (now.getUTCDay() + (istMinutes < utcMinutes ? 1 : 0)) % 7; // rough day adjustment
  
  // Weekend check
  if (istDay === 0 || istDay === 6) return false;
  
  // 9:15 AM to 3:30 PM = 555 to 930 minutes
  const marketOpen = 9 * 60 + 15;  // 555
  const marketClose = 15 * 60 + 30; // 930
  return istMinutes >= marketOpen && istMinutes <= marketClose;
}

function updateMarketStatus(isLive) {
  const existingBadge = document.getElementById('liveStatusBadge');
  if (existingBadge) existingBadge.remove();
  
  const header = document.querySelector('.market-section h2, .market-section .section-title');
  if (!header) return;
  
  const badge = document.createElement('span');
  badge.id = 'liveStatusBadge';
  if (isMarketHours() && isLive) {
    badge.className = 'live-badge';
    badge.textContent = 'LIVE';
  } else {
    badge.className = 'market-closed-badge';
    badge.innerHTML = '<i class="fa fa-clock" style="font-size:10px;"></i> Market Closed';
  }
  header.appendChild(badge);
}

function startLiveRefresh() {
  // Refresh indices every 5 seconds
  if (_liveRefreshInterval) clearInterval(_liveRefreshInterval);
  _liveRefreshInterval = setInterval(() => {
    loadIndices(true);
  }, 5000);
  
  // Refresh movers every 15 seconds
  if (_moversRefreshInterval) clearInterval(_moversRefreshInterval);
  _moversRefreshInterval = setInterval(() => {
    const activeTab = document.querySelector('.market-tab.active, [data-tab].active');
    if (activeTab) {
      const cat = activeTab.dataset.category || activeTab.textContent.toLowerCase().includes('gainer') ? 'gainers' : 'losers';
      loadMovers(cat, true);
    }
  }, 15000);
}

function stopLiveRefresh() {
  if (_liveRefreshInterval) { clearInterval(_liveRefreshInterval); _liveRefreshInterval = null; }
  if (_moversRefreshInterval) { clearInterval(_moversRefreshInterval); _moversRefreshInterval = null; }
}

// Load indices on page load
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    loadIndices();
    startLiveRefresh();
  });
} else {
  loadIndices();
  startLiveRefresh();
}

// Stop refresh when page is hidden (save resources)
document.addEventListener('visibilitychange', () => {
  if (document.hidden) stopLiveRefresh();
  else startLiveRefresh();
});

// ===== MOBILE MARKET FUNCTIONS =====

const INDEX_BADGES = [
  { abbr: 'NIF', cls: 'card-badge-blue' },
  { abbr: 'BNK', cls: 'card-badge-purple' },
  { abbr: 'SEN', cls: 'card-badge-teal' },
  { abbr: 'IT', cls: 'card-badge-orange' },
  { abbr: 'FIN', cls: 'card-badge-pink' },
  { abbr: 'MID', cls: 'card-badge-green' }
];

const COMMODITY_BADGES = [
  { abbr: 'GLD', cls: 'card-badge-amber' },
  { abbr: 'SLV', cls: 'card-badge-blue' },
  { abbr: 'OIL', cls: 'card-badge-orange' },
  { abbr: 'GAS', cls: 'card-badge-green' }
];

const CRYPTO_BADGES = [
  { abbr: 'BTC', cls: 'card-badge-orange' },
  { abbr: 'ETH', cls: 'card-badge-purple' },
  { abbr: 'SOL', cls: 'card-badge-green' },
  { abbr: 'XRP', cls: 'card-badge-blue' },
  { abbr: 'DOGE', cls: 'card-badge-amber' },
  { abbr: 'ADA', cls: 'card-badge-teal' }
];

function mobileSwitchTab(tabId, btn) {
  // Update tab styles
  document.querySelectorAll('.mobile-market-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  // Show/hide content
  document.querySelectorAll('.mobile-tab-content').forEach(c => c.style.display = 'none');
  document.getElementById(tabId).style.display = 'block';
  // Load data for the tab
  if (tabId === 'm-indices') mobileLoadIndices();
  if (tabId === 'm-stocks') { mobileLoadMovers('gainers'); mobileLoadMovers('losers'); }
  if (tabId === 'm-commodities') mobileLoadCommodities();
  if (tabId === 'm-crypto') mobileLoadCryptos();
}

function formatNum(n) {
  if (n >= 100000) return (n / 100000).toFixed(2) + 'L';
  if (n >= 1000) return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  return n.toFixed(2);
}

function buildCard(badge, name, value, changePct, href) {
  const isPos = changePct >= 0;
  const arrow = isPos ? '▲' : '▼';
  const cls = isPos ? 'positive' : 'negative';
  return '<a class="mobile-market-card" href="' + href + '">' +
    '<div class="card-badge ' + badge.cls + '">' + badge.abbr + '</div>' +
    '<div class="card-name">' + name + '</div>' +
    '<div class="card-value">₹' + value + '</div>' +
    '<div class="card-change ' + cls + '">' + arrow + ' ' + (isPos ? '+' : '') + changePct.toFixed(2) + '%</div>' +
  '</a>';
}

const _prevMobilePrices = {};
let _mobileRefreshInterval = null;

async function mobileLoadIndices(isRefresh = false) {
  try {
    const res = await fetch('../api/get-indexes.php');
    const data = await res.json();
    const grid = document.getElementById('mobileIndicesGrid');
    if (data.success && data.indexes && data.indexes.length > 0) {
      if (!isRefresh) {
        grid.innerHTML = '';
        data.indexes.forEach((idx, i) => {
          const badge = INDEX_BADGES[i] || { abbr: idx.name.substring(0,3).toUpperCase(), cls: 'card-badge-blue' };
          grid.innerHTML += buildCard(
            badge,
            idx.name,
            formatNum(idx.price || 0),
            idx.change_percent || 0,
            '../index-detail.php?symbol=' + encodeURIComponent(idx.symbol)
          );
          _prevMobilePrices['idx_' + idx.symbol] = idx.price;
        });
      } else {
        // Smart update: compare and flash
        const cards = grid.querySelectorAll('.mobile-market-card');
        data.indexes.forEach((idx, i) => {
          const card = cards[i];
          if (!card) return;
          const valueEl = card.querySelector('.card-value');
          const changeEl = card.querySelector('.card-change');
          if (!valueEl) return;
          
          const oldPrice = _prevMobilePrices['idx_' + idx.symbol] || idx.price;
          if (idx.price !== oldPrice) {
            const wentUp = idx.price > oldPrice;
            valueEl.classList.remove('price-flash-up', 'price-flash-down');
            void valueEl.offsetWidth;
            valueEl.classList.add(wentUp ? 'price-flash-up' : 'price-flash-down');
          }
          valueEl.textContent = '₹' + formatNum(idx.price || 0);
          const isPos = (idx.change_percent || 0) >= 0;
          const arrow = isPos ? '▲' : '▼';
          changeEl.className = 'card-change ' + (isPos ? 'positive' : 'negative');
          changeEl.textContent = arrow + ' ' + (isPos ? '+' : '') + (idx.change_percent || 0).toFixed(2) + '%';
          _prevMobilePrices['idx_' + idx.symbol] = idx.price;
        });
      }
    } else if (!isRefresh) {
      grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:20px;color:var(--text-secondary);font-size:13px;">No indices data available</div>';
    }
  } catch (e) {
    console.error('Mobile indices error:', e);
  }
}

async function mobileLoadMovers(category, isRefresh = false) {
  try {
    const res = await fetch('../api/yahoo-top-movers.php?category=' + category);
    const data = await res.json();
    const container = document.getElementById(category === 'gainers' ? 'mobileGainers' : 'mobileLosers');
    if (data.success && data.movers && data.movers.length > 0) {
      if (!isRefresh) {
        container.innerHTML = '';
        data.movers.slice(0, 5).forEach(stock => {
          const isPos = stock.changePercent >= 0;
          const cls = isPos ? 'positive' : 'negative';
          container.innerHTML +=
            '<a class="mobile-mover-card" href="../stock-detail.php?id=' + stock.id + '">' +
              '<div><div class="mover-name">' + stock.symbol + '</div><div class="mover-sub">' + stock.name + '</div></div>' +
              '<div class="mover-pct ' + cls + '">' + (isPos ? '+' : '') + stock.changePercent.toFixed(2) + '%</div>' +
            '</a>';
          _prevMobilePrices['mv_' + stock.symbol] = stock.price;
        });
      } else {
        const cards = container.querySelectorAll('.mobile-mover-card');
        data.movers.slice(0, 5).forEach((stock, i) => {
          const card = cards[i];
          if (!card) return;
          const pctEl = card.querySelector('.mover-pct');
          if (!pctEl) return;
          const isPos = stock.changePercent >= 0;
          pctEl.className = 'mover-pct ' + (isPos ? 'positive' : 'negative');
          pctEl.textContent = (isPos ? '+' : '') + stock.changePercent.toFixed(2) + '%';
        });
      }
    } else if (!isRefresh) {
      container.innerHTML = '<div style="font-size:12px;color:var(--text-secondary);padding:8px;">No data</div>';
    }
  } catch (e) {
    console.error('Mobile movers error:', e);
  }
}

async function mobileLoadCommodities() {
  try {
    const res = await fetch('../api/get-commodities.php');
    const data = await res.json();
    const grid = document.getElementById('mobileCommoditiesGrid');
    if (data.success && data.commodities && data.commodities.length > 0) {
      grid.innerHTML = '';
      data.commodities.forEach((cmd, i) => {
        const badge = COMMODITY_BADGES[i] || { abbr: cmd.symbol.substring(0,3).toUpperCase(), cls: 'card-badge-orange' };
        grid.innerHTML += buildCard(
          badge,
          cmd.name,
          formatNum(parseFloat(cmd.ltp)),
          parseFloat(cmd.change_percent),
          '../commodity-detail.php?id=' + cmd.id
        );
      });
    } else {
      grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:20px;color:var(--text-secondary);font-size:13px;">No commodities data</div>';
    }
  } catch (e) {
    console.error('Mobile commodities error:', e);
  }
}

async function mobileLoadCryptos() {
  try {
    const res = await fetch('../api/get-cryptos.php');
    const data = await res.json();
    const grid = document.getElementById('mobileCryptoGrid');
    if (data.success && data.cryptos && data.cryptos.length > 0) {
      grid.innerHTML = '';
      data.cryptos.forEach((crp, i) => {
        const badge = CRYPTO_BADGES[i] || { abbr: crp.symbol.substring(0,3).toUpperCase(), cls: 'card-badge-purple' };
        grid.innerHTML += buildCard(
          badge,
          crp.name,
          formatNum(parseFloat(crp.ltp)),
          parseFloat(crp.change_percent),
          '../crypto-detail.php?id=' + crp.id
        );
      });
    } else {
      grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:20px;color:var(--text-secondary);font-size:13px;">No crypto data</div>';
    }
  } catch (e) {
    console.error('Mobile crypto error:', e);
  }
}

// Auto-load mobile data on page load (only on mobile)
function initMobileMarket() {
  if (window.innerWidth <= 768) {
    mobileLoadIndices();
    // Start mobile auto-refresh every 5 seconds
    if (_mobileRefreshInterval) clearInterval(_mobileRefreshInterval);
    _mobileRefreshInterval = setInterval(() => {
      if (window.innerWidth <= 768) {
        mobileLoadIndices(true);
        // Refresh movers based on active tab
        const activeTab = document.querySelector('.mobile-market-tab.active');
        if (activeTab) {
          const tabId = activeTab.dataset.tab;
          if (tabId === 'm-stocks') {
            mobileLoadMovers('gainers', true);
            mobileLoadMovers('losers', true);
          }
        }
      }
    }, 5000);
  }
}

// Stop mobile refresh when page hidden
document.addEventListener('visibilitychange', () => {
  if (document.hidden && _mobileRefreshInterval) {
    clearInterval(_mobileRefreshInterval);
    _mobileRefreshInterval = null;
  }
});

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initMobileMarket);
} else {
  initMobileMarket();
}
</script>

</body>
</html>
