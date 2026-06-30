<?php
require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
$currentPage = 'fno-positions';
$db = getDB();

$positions = $db->query("
    SELECT fp.*, fc.symbol, fc.stock_name, fc.contract_type, fc.strike_price, fc.expiry_date, fc.current_price
    FROM fno_positions fp
    JOIN fno_contracts fc ON fc.id = fp.contract_id
    WHERE fp.user_id = {$user['id']} AND fp.is_active = 1
    ORDER BY fp.created_at DESC
")->fetchAll();

$totalMargin = array_sum(array_column($positions, 'margin_used'));
$totalPnl = 0;
foreach ($positions as $pos) {
    $pnl = $pos['position_type'] === 'BUY' 
        ? ($pos['current_price'] - $pos['entry_price']) * $pos['quantity']
        : ($pos['entry_price'] - $pos['current_price']) * $pos['quantity'];
    $totalPnl += $pnl;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F&O Positions - TradeZenfy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/assets/css/groww-ui.css">
    <link rel="stylesheet" href="../public/assets/css/layout-new.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--groww-card); border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-label { font-size: 13px; color: var(--groww-text-secondary); margin-bottom: 8px; }
        .stat-value { font-size: 28px; font-weight: 700; }
        .stat-value.positive { color: var(--success); }
        .stat-value.negative { color: var(--danger); }
        
        .position-card { background: var(--groww-card); border-radius: 12px; padding: 20px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .position-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
        .position-symbol { font-weight: 700; font-size: 18px; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .type-FUTURES { background: #DBEAFE; color: #3B82F6; }
        .type-CALL { background: #DCFCE7; color: var(--success); }
        .type-PUT { background: #FEE2E2; color: var(--danger); }
        .pos-BUY { background: #DCFCE7; color: var(--success); }
        .pos-SELL { background: #FEE2E2; color: var(--danger); }
        
        .position-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px; margin-bottom: 16px; }
        .detail-label { font-size: 12px; color: var(--groww-text-secondary); margin-bottom: 4px; }
        .detail-value { font-size: 16px; font-weight: 600; }
        
        .close-btn { background: var(--danger); color: white; border: none; padding: 10px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        
        .empty { text-align: center; padding: 60px 20px; color: var(--groww-text-secondary); background: white; border-radius: 12px; }
        .empty i { font-size: 64px; margin-bottom: 16px; opacity: 0.3; }
        
        @media (max-width: 768px) {
            .main, .main-content { padding: 16px; padding-bottom: 72px; overflow-x: hidden; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .position-details { grid-template-columns: repeat(2, 1fr); }
            /* Tables: stack on mobile */
            table { display: block; width: 100%; overflow-x: hidden; }
            table thead { display: none; }
            table tbody { display: block; width: 100%; }
            table tr { display: block; padding: 12px; border-bottom: 1px solid var(--border); }
            table td { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border: none; font-size: 14px; }
            table td::before { content: attr(data-label); font-weight: 600; color: var(--text-secondary); font-size: 12px; margin-right: 12px; }
        }
    </style>
<link rel="stylesheet" href="../public/assets/css/mobile-responsive.css">
</head>
<body>

<?php include __DIR__ . '/../includes/user-top-nav.php'; ?>

<div class="main-content">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 28px; margin-bottom: 8px;">F&O Positions</h1>
        <p style="color: var(--groww-text-secondary);"><?= count($positions) ?> open positions</p>
    </div>
    
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Total Margin Used</div>
            <div class="stat-value">₹<?= number_format($totalMargin, 2) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total P&L</div>
            <div class="stat-value <?= $totalPnl >= 0 ? 'positive' : 'negative' ?>">
                <?= $totalPnl >= 0 ? '+' : '' ?>₹<?= number_format($totalPnl, 2) ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Open Positions</div>
            <div class="stat-value"><?= count($positions) ?></div>
        </div>
    </div>
    
    <?php if (count($positions) > 0): ?>
        <?php foreach ($positions as $pos): 
            $pnl = $pos['position_type'] === 'BUY' 
                ? ($pos['current_price'] - $pos['entry_price']) * $pos['quantity']
                : ($pos['entry_price'] - $pos['current_price']) * $pos['quantity'];
        ?>
        <div class="position-card">
            <div class="position-header">
                <div>
                    <span class="position-symbol"><?= $pos['symbol'] ?></span>
                    <span class="badge type-<?= $pos['contract_type'] ?>"><?= $pos['contract_type'] ?></span>
                    <?php if ($pos['contract_type'] !== 'FUTURES'): ?>
                        <span style="font-size: 13px; color: var(--groww-text-secondary); margin-left: 8px;">
                            Strike: <?= number_format($pos['strike_price'], 2) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <span class="badge pos-<?= $pos['position_type'] ?>"><?= $pos['position_type'] ?></span>
            </div>
            
            <div class="position-details">
                <div>
                    <div class="detail-label">Quantity</div>
                    <div class="detail-value"><?= number_format($pos['quantity']) ?></div>
                </div>
                <div>
                    <div class="detail-label">Entry Price</div>
                    <div class="detail-value"><?= number_format($pos['entry_price'], 2) ?></div>
                </div>
                <div>
                    <div class="detail-label">Current Price</div>
                    <div class="detail-value"><?= number_format($pos['current_price'], 2) ?></div>
                </div>
                <div>
                    <div class="detail-label">Margin Used</div>
                    <div class="detail-value">₹<?= number_format($pos['margin_used'], 2) ?></div>
                </div>
                <div>
                    <div class="detail-label">P&L</div>
                    <div class="detail-value" style="color: <?= $pnl >= 0 ? 'var(--groww-green)' : 'var(--groww-red)' ?>">
                        <?= $pnl >= 0 ? '+' : '' ?>₹<?= number_format($pnl, 2) ?>
                    </div>
                </div>
                <div>
                    <div class="detail-label">Expiry</div>
                    <div class="detail-value"><?= date('d M Y', strtotime($pos['expiry_date'])) ?></div>
                </div>
            </div>
            
            <button class="close-btn" onclick="closePosition(<?= $pos['id'] ?>)">
                <i class="fa fa-times"></i> Close Position
            </button>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty">
            <i class="fa fa-chart-line"></i>
            <h3>No Open Positions</h3>
            <p>Start trading to see your positions here</p>
            <a href="../fno-market.php" style="color: var(--groww-green); text-decoration: none; font-weight: 600; margin-top: 12px; display: inline-block;">Browse F&O Contracts →</a>
        </div>
    <?php endif; ?>
</div>

<script>
async function closePosition(positionId) {
    if (!confirm('Are you sure you want to close this position?')) return;
    
    try {
        const response = await fetch('../api/close-fno-position.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ position_id: positionId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Position closed! P&L: ₹' + data.pnl.toFixed(2));
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Failed to close position');
    }
}
</script>



</body>
</html>
