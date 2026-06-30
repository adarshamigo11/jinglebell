<?php
require_once __DIR__ . '/includes/middleware.php';
$user = requireUser();
$currentPage = 'crypto-market';
$db = getDB();

// Fetch all active cryptocurrencies
$stmt = $db->prepare("
    SELECT s.*, 
           COALESCE(c.ltp, s.ltp) AS live_ltp,
           COALESCE(c.change_percent, s.change_percent) AS live_chg
    FROM stocks s
    LEFT JOIN stock_price_cache c ON c.stock_id = s.id
    WHERE s.is_active = 1 AND s.sector = 'Cryptocurrency'
    ORDER BY s.symbol ASC
");
$stmt->execute();
$cryptos = $stmt->fetchAll();

// Group by industry
$grouped = [];
foreach ($cryptos as $crypto) {
    $industry = $crypto['industry'] ?: 'Other';
    if (!isset($grouped[$industry])) $grouped[$industry] = [];
    $grouped[$industry][] = $crypto;
}

$holdingsCount = $db->prepare("SELECT COUNT(*) FROM user_holdings WHERE user_id = ? AND quantity > 0");
$holdingsCount->execute([$user['id']]);
$holdingsCount = $holdingsCount->fetchColumn();

$pendingOrders = $db->prepare("SELECT COUNT(*) FROM user_orders WHERE user_id = ? AND status = 'PENDING'");
$pendingOrders->execute([$user['id']]);
$pendingOrders = $pendingOrders->fetchColumn();

$availableBalance = (float)$user['current_balance'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crypto Market — TradeZenfy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="public/assets/css/groww-ui.css">
<link rel="stylesheet" href="public/assets/css/layout-new.css">
<style>
body { background: var(--groww-bg); margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
.page-wrap { max-width: 1200px; margin: 0 auto; padding: 24px; }
.page-header { margin-bottom: 24px; }
.page-header h1 { font-size: 24px; font-weight: 700; color: var(--groww-text); margin: 0 0 8px 0; }
.page-header p { font-size: 14px; color: var(--groww-text-secondary); margin: 0; }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
.stat-card { background: var(--groww-card); border: 1px solid var(--groww-border); border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.stat-card .label { font-size: 12px; color: var(--groww-text-secondary); margin-bottom: 4px; }
.stat-card .value { font-size: 20px; font-weight: 700; color: var(--groww-text); }

.category-section { margin-bottom: 32px; }
.category-header { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
.category-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.category-header h2 { font-size: 18px; font-weight: 600; color: var(--groww-text); margin: 0; }

.crypto-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.crypto-card { background: var(--groww-card); border: 1px solid var(--groww-border); border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); transition: all 0.2s; cursor: pointer; }
.crypto-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.12); transform: translateY(-2px); }
.crypto-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.crypto-icon { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #f7931a 0%, #627eea 100%); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 14px; }
.crypto-info h3 { font-size: 15px; font-weight: 600; color: var(--groww-text); margin: 0; }
.crypto-info span { font-size: 12px; color: var(--groww-text-secondary); }
.crypto-price { text-align: right; margin-bottom: 12px; }
.crypto-price .ltp { font-size: 18px; font-weight: 700; color: var(--groww-text); }
.crypto-price .change { font-size: 13px; font-weight: 600; }
.crypto-price .change.positive { color: var(--groww-green); }
.crypto-price .change.negative { color: var(--groww-red); }
.crypto-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid var(--groww-border); }
.crypto-footer .exchange { font-size: 11px; color: var(--groww-text-secondary); background: var(--groww-hover); padding: 3px 8px; border-radius: 12px; }
.btn-trade { padding: 6px 16px; background: var(--groww-green); color: #fff; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
.btn-trade:hover { opacity: 0.9; }

@media(max-width: 768px) {
  .page-wrap { padding: 16px; }
  .crypto-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<?php include __DIR__ . '/includes/user-top-nav.php'; ?>

<div class="page-wrap">
  <div class="page-header">
    <h1><i class="fa fa-coins" style="color: #f7931a; margin-right: 8px;"></i>Cryptocurrency Market</h1>
    <p>Trade top cryptocurrencies with virtual money</p>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="label">Available Cryptos</div>
      <div class="value"><?= count($cryptos) ?></div>
    </div>
    <div class="stat-card">
      <div class="label">Your Holdings</div>
      <div class="value"><?= $holdingsCount ?></div>
    </div>
    <div class="stat-card">
      <div class="label">Pending Orders</div>
      <div class="value"><?= $pendingOrders ?></div>
    </div>
    <div class="stat-card">
      <div class="label">Available Balance</div>
      <div class="value">₹<?= number_format($availableBalance, 2) ?></div>
    </div>
  </div>

  <?php foreach ($grouped as $industry => $industryCryptos): ?>
  <div class="category-section">
    <div class="category-header">
      <div class="category-icon" style="background: <?= $industry === 'Crypto' ? 'rgba(247, 147, 26, 0.1)' : 'rgba(98, 126, 234, 0.1)' ?>; color: <?= $industry === 'Crypto' ? '#f7931a' : '#627eea' ?>;">
        <i class="fa fa-<?= $industry === 'Crypto' ? 'bitcoin' : 'exchange-alt' ?>"></i>
      </div>
      <h2><?= htmlspecialchars($industry) ?></h2>
    </div>
    <div class="crypto-grid">
      <?php foreach ($industryCryptos as $crypto): 
        $ltp = (float)($crypto['live_ltp'] ?? $crypto['ltp']);
        $prev = (float)$crypto['previous_close'];
        $chgPct = (float)($crypto['live_chg'] ?? $crypto['change_percent']);
        $chgVal = $ltp - $prev;
        $isPos = $chgVal >= 0;
      ?>
      <div class="crypto-card" onclick="window.location.href='crypto-detail.php?id=<?= $crypto['id'] ?>'">
        <div class="crypto-header">
          <div class="crypto-icon"><?= strtoupper(substr($crypto['symbol'], 0, 3)) ?></div>
          <div class="crypto-info">
            <h3><?= htmlspecialchars($crypto['name']) ?></h3>
            <span><?= htmlspecialchars($crypto['symbol']) ?></span>
          </div>
        </div>
        <div class="crypto-price">
          <div class="ltp">₹<?= number_format($ltp, 2) ?></div>
          <div class="change <?= $isPos ? 'positive' : 'negative' ?>">
            <?= $isPos ? '▲' : '▼' ?> <?= $isPos ? '+' : '' ?><?= number_format($chgPct, 2) ?>%
          </div>
        </div>
        <div class="crypto-footer">
          <span class="exchange"><?= htmlspecialchars($crypto['exchange']) ?></span>
          <a href="crypto-detail.php?id=<?= $crypto['id'] ?>" class="btn-trade" onclick="event.stopPropagation()">Trade</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

</body>
</html>
