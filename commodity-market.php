<?php
require_once __DIR__ . '/includes/middleware.php';
$user = requireUser();
$currentPage = 'commodity-market';
$db   = getDB();

// All active commodities
$stmt = $db->prepare("
    SELECT s.id, s.symbol, s.name, s.exchange, s.industry,
           COALESCE(c.ltp, s.ltp) AS ltp,
           COALESCE(c.change_percent, s.change_percent) AS change_percent,
           s.previous_close
    FROM stocks s
    LEFT JOIN stock_price_cache c ON c.stock_id = s.id
    WHERE s.is_active = 1 AND s.sector = 'Commodity'
    ORDER BY s.industry, s.symbol ASC
");
$stmt->execute();
$commodities = $stmt->fetchAll();

// Group by industry
$grouped = [];
foreach ($commodities as $cmd) {
    $industry = $cmd['industry'] ?: 'Other';
    if (!isset($grouped[$industry])) $grouped[$industry] = [];
    $grouped[$industry][] = $cmd;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Commodities Market — TradeZenfy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="public/assets/css/groww-ui.css">
<link rel="stylesheet" href="public/assets/css/layout-new.css">
<style>
body { background: var(--bg); }

.page-header { margin-bottom: 24px; }
.page-title { font-size: 24px; font-weight: 700; color: var(--groww-text); margin-bottom: 4px; }
.page-subtitle { font-size: 14px; color: var(--groww-text-secondary); }

/* Category Section */
.category-section { margin-bottom: 32px; }
.category-header {
  display: flex; align-items: center; gap: 10px; margin-bottom: 16px;
  padding-bottom: 12px; border-bottom: 1px solid var(--groww-border);
}
.category-icon {
  width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
  font-size: 16px; color: white;
}
.category-icon.energy { background: linear-gradient(135deg, #F59E0B, #D97706); }
.category-icon.precious { background: linear-gradient(135deg, #FBBF24, #F59E0B); }
.category-icon.base { background: linear-gradient(135deg, #6B7280, #4B5563); }
.category-icon.agri { background: linear-gradient(135deg, #10B981, #059669); }
.category-title { font-size: 18px; font-weight: 700; color: var(--groww-text); }
.category-count { font-size: 12px; color: var(--groww-text-secondary); background: var(--groww-hover); padding: 2px 10px; border-radius: 20px; }

/* Commodity Cards Grid */
.commodity-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
.commodity-card {
  background: var(--groww-card); border-radius: 12px; padding: 20px;
  border: 1px solid var(--groww-border); cursor: pointer;
  transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  text-decoration: none; color: inherit; display: block;
}
.commodity-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.12); transform: translateY(-2px); border-color: var(--groww-green); }

.card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.card-left {}
.cmd-symbol { font-size: 16px; font-weight: 700; color: var(--groww-text); margin-bottom: 2px; }
.cmd-name { font-size: 13px; color: var(--groww-text-secondary); }
.cmd-exchange { font-size: 11px; color: var(--groww-text-secondary); background: var(--groww-hover); padding: 2px 8px; border-radius: 12px; margin-top: 6px; display: inline-block; }

.card-right { text-align: right; }
.cmd-ltp { font-size: 18px; font-weight: 700; color: var(--groww-text); }
.cmd-change { font-size: 13px; font-weight: 600; margin-top: 2px; }
.cmd-change.positive { color: var(--groww-green); }
.cmd-change.negative { color: var(--groww-red); }

.card-bottom { display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid var(--groww-border); }
.cmd-prev { font-size: 12px; color: var(--groww-text-secondary); }
.trade-btn {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 6px 14px; background: var(--groww-green); color: white;
  border-radius: 6px; font-size: 12px; font-weight: 600;
  text-decoration: none; transition: opacity 0.2s;
}
.trade-btn:hover { opacity: 0.85; }

@media (max-width: 768px) {
  .main-content { padding: 12px; padding-bottom: 72px; }
  .page-title { font-size: 20px; }
  .commodity-grid { grid-template-columns: 1fr; }
  .commodity-card { padding: 16px; }
}
</style>
<link rel="stylesheet" href="public/assets/css/mobile-responsive.css">
</head>
<body>

<?php include 'includes/user-top-nav.php'; ?>

<div class="main-content">
  <div class="page-header">
    <div class="page-title">Commodities Market</div>
    <div class="page-subtitle">Trade energy, metals, and agricultural commodities</div>
  </div>

  <?php
  $iconMap = [
    'Energy' => ['class' => 'energy', 'icon' => 'fa-fire'],
    'Precious Metals' => ['class' => 'precious', 'icon' => 'fa-gem'],
    'Base Metals' => ['class' => 'base', 'icon' => 'fa-cube'],
    'Agriculture' => ['class' => 'agri', 'icon' => 'fa-seedling'],
  ];
  
  // Sort industries: Energy, Precious Metals, Base Metals, Agriculture
  $industryOrder = ['Energy', 'Precious Metals', 'Base Metals', 'Agriculture'];
  $sortedIndustries = [];
  foreach ($industryOrder as $ind) {
    if (isset($grouped[$ind])) $sortedIndustries[$ind] = $grouped[$ind];
  }
  foreach ($grouped as $ind => $items) {
    if (!isset($sortedIndustries[$ind])) $sortedIndustries[$ind] = $items;
  }
  ?>

  <?php foreach ($sortedIndustries as $industry => $items): 
    $iconInfo = $iconMap[$industry] ?? ['class' => 'base', 'icon' => 'fa-box'];
  ?>
  <div class="category-section">
    <div class="category-header">
      <div class="category-icon <?= $iconInfo['class'] ?>">
        <i class="fa <?= $iconInfo['icon'] ?>"></i>
      </div>
      <div>
        <div class="category-title"><?= htmlspecialchars($industry) ?></div>
      </div>
      <span class="category-count"><?= count($items) ?> items</span>
    </div>
    
    <div class="commodity-grid">
      <?php foreach ($items as $cmd): 
        $chg = (float)$cmd['change_percent'];
        $isPos = $chg >= 0;
        $ltp = (float)$cmd['ltp'];
        $prevClose = (float)$cmd['previous_close'];
        $chgVal = $ltp - $prevClose;
      ?>
      <a href="commodity-detail.php?id=<?= $cmd['id'] ?>" class="commodity-card">
        <div class="card-top">
          <div class="card-left">
            <div class="cmd-symbol"><?= htmlspecialchars($cmd['symbol']) ?></div>
            <div class="cmd-name"><?= htmlspecialchars($cmd['name']) ?></div>
            <span class="cmd-exchange"><?= htmlspecialchars($cmd['exchange']) ?></span>
          </div>
          <div class="card-right">
            <div class="cmd-ltp">₹<?= number_format($ltp, 2) ?></div>
            <div class="cmd-change <?= $isPos ? 'positive' : 'negative' ?>">
              <i class="fa fa-caret-<?= $isPos ? 'up' : 'down' ?>"></i>
              <?= ($isPos ? '+' : '') . number_format($chg, 2) ?>%
            </div>
          </div>
        </div>
        <div class="card-bottom">
          <span class="cmd-prev">Prev: ₹<?= number_format($prevClose, 2) ?></span>
          <span class="trade-btn"><i class="fa fa-bolt"></i> Trade</span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if (empty($commodities)): ?>
  <div class="card" style="padding: 60px; text-align: center;">
    <i class="fa fa-box-open" style="font-size: 48px; color: var(--groww-text-secondary); margin-bottom: 16px;"></i>
    <div style="font-size: 18px; font-weight: 600; color: var(--groww-text); margin-bottom: 8px;">No Commodities Available</div>
    <div style="font-size: 14px; color: var(--groww-text-secondary);">Check back later for commodity trading options.</div>
  </div>
  <?php endif; ?>
</div>

</body>
</html>
