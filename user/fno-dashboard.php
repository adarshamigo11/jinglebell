<?php
require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
$currentPage = 'fno-dashboard';
$db = getDB();

// Get F&O stats
$stats = [
    'total_margin' => $db->query("SELECT COALESCE(SUM(margin_used), 0) FROM fno_positions WHERE user_id = {$user['id']} AND is_active = 1")->fetchColumn(),
    'total_pnl' => $db->query("SELECT COALESCE(SUM(pnl), 0) FROM fno_orders WHERE user_id = {$user['id']} AND status = 'CLOSED'")->fetchColumn(),
    'open_positions' => $db->query("SELECT COUNT(*) FROM fno_positions WHERE user_id = {$user['id']} AND is_active = 1")->fetchColumn(),
    'today_trades' => $db->query("SELECT COUNT(*) FROM fno_orders WHERE user_id = {$user['id']} AND DATE(created_at) = CURDATE()")->fetchColumn()
];

// Get open positions
$positions = $db->query("
    SELECT fp.*, fc.symbol, fc.stock_name, fc.contract_type, fc.strike_price, fc.expiry_date, fc.current_price
    FROM fno_positions fp
    JOIN fno_contracts fc ON fc.id = fp.contract_id
    WHERE fp.user_id = {$user['id']} AND fp.is_active = 1
    ORDER BY fp.created_at DESC
    LIMIT 10
")->fetchAll();

// Get recent orders
$recentOrders = $db->query("
    SELECT fo.*, fc.symbol, fc.contract_type
    FROM fno_orders fo
    JOIN fno_contracts fc ON fc.id = fo.contract_id
    WHERE fo.user_id = {$user['id']}
    ORDER BY fo.created_at DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F&O Dashboard - TradeZenfy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/assets/css/groww-ui.css">
    <link rel="stylesheet" href="../public/assets/css/layout-new.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--groww-card); border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-label { font-size: 13px; color: var(--groww-text-secondary); margin-bottom: 8px; }
        .stat-value { font-size: 28px; font-weight: 700; }
        .stat-value.positive { color: var(--success); }
        .stat-value.negative { color: var(--danger); }
        
        .section { background: var(--groww-card); border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-title { font-size: 18px; font-weight: 600; }
        
        .position-card { border: 1px solid var(--groww-border); border-radius: 8px; padding: 16px; margin-bottom: 12px; }
        .position-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .position-symbol { font-weight: 700; font-size: 16px; }
        .position-type { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .type-FUTURES { background: #DBEAFE; color: #3B82F6; }
        .type-CALL { background: #DCFCE7; color: var(--success); }
        .type-PUT { background: #FEE2E2; color: var(--danger); }
        .pos-BUY { background: #DCFCE7; color: var(--success); }
        .pos-SELL { background: #FEE2E2; color: var(--danger); }
        
        .position-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; font-size: 13px; }
        .detail-label { color: var(--groww-text-secondary); font-size: 11px; }
        .detail-value { font-weight: 600; margin-top: 2px; }
        
        .close-btn { background: var(--groww-red); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; margin-top: 12px; }
        
        table { width: 100%; border-collapse: collapse; }
        th { font-size: 11px; color: var(--groww-text-secondary); text-transform: uppercase; padding: 12px; text-align: left; background: var(--groww-hover); }
        td { padding: 12px; font-size: 13px; border-top: 1px solid var(--groww-border); }
        .status-EXECUTED { background: #DCFCE7; color: var(--success); padding: 3px 10px; border-radius: 20px; font-size: 11px; }
        .status-CLOSED { background: #E5E7EB; color: var(--groww-text-secondary); padding: 3px 10px; border-radius: 20px; font-size: 11px; }
        
        .empty { padding: 40px; text-align: center; color: var(--groww-text-secondary); }
        .empty i { font-size: 48px; margin-bottom: 12px; opacity: 0.3; }
        
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .action-btn { padding: 16px; border-radius: 12px; text-align: center; text-decoration: none; font-weight: 600; display: block; }
        .action-btn.primary { background: var(--primary); color: white; }
        .action-btn.secondary { background: white; border: 2px solid var(--groww-border); color: var(--groww-text); }
        
        @media (max-width: 768px) {
            .main, .main-content { padding: 16px; padding-bottom: 72px; overflow-x: hidden; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
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
        <h1 style="font-size: 28px; margin-bottom: 8px;">F&O Dashboard</h1>
        <p style="color: var(--groww-text-secondary);">Futures & Options Trading</p>
    </div>
    
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Margin Used</div>
            <div class="stat-value">₹<?= number_format($stats['total_margin'], 2) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total P&L</div>
            <div class="stat-value <?= $stats['total_pnl'] >= 0 ? 'positive' : 'negative' ?>">
                <?= $stats['total_pnl'] >= 0 ? '+' : '' ?>₹<?= number_format($stats['total_pnl'], 2) ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Open Positions</div>
            <div class="stat-value"><?= $stats['open_positions'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Today's Trades</div>
            <div class="stat-value"><?= $stats['today_trades'] ?></div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="../fno-market.php" class="action-btn primary">
            <i class="fa fa-chart-line" style="font-size: 24px; display: block; margin-bottom: 8px;"></i>
            Trade F&O
        </a>
        <a href="fno-positions.php" class="action-btn secondary">
            <i class="fa fa-briefcase" style="font-size: 24px; display: block; margin-bottom: 8px;"></i>
            View Positions
        </a>
        <a href="fno-orders.php" class="action-btn secondary">
            <i class="fa fa-list-alt" style="font-size: 24px; display: block; margin-bottom: 8px;"></i>
            Order History
        </a>
    </div>
    
    <!-- Open Positions -->
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">Open Positions</h2>
            <a href="fno-positions.php" style="color: var(--groww-green); text-decoration: none; font-weight: 600;">View All →</a>
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
                        <span class="position-type type-<?= $pos['contract_type'] ?>"><?= $pos['contract_type'] ?></span>
                        <?php if ($pos['contract_type'] !== 'FUTURES'): ?>
                            <span style="font-size: 12px; color: var(--groww-text-secondary);">Strike: <?= number_format($pos['strike_price'], 2) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="position-type pos-<?= $pos['position_type'] ?>"><?= $pos['position_type'] ?></span>
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
                <p>No open positions</p>
                <a href="../fno-market.php" style="color: var(--groww-green); text-decoration: none; font-weight: 600;">Start Trading →</a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Recent Orders -->
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">Recent Orders</h2>
            <a href="fno-orders.php" style="color: var(--groww-green); text-decoration: none; font-weight: 600;">View All →</a>
        </div>
        
        <?php if (count($recentOrders) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Symbol</th>
                    <th>Type</th>
                    <th>Side</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>P&L</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentOrders as $order): ?>
                <tr>
                    <td><strong><?= $order['symbol'] ?></strong></td>
                    <td><span class="position-type type-<?= $order['contract_type'] ?>"><?= $order['contract_type'] ?></span></td>
                    <td style="color: <?= $order['order_type'] === 'BUY' ? 'var(--groww-green)' : 'var(--groww-red)' ?>"><?= $order['order_type'] ?></td>
                    <td><?= number_format($order['quantity']) ?></td>
                    <td><?= number_format($order['entry_price'], 2) ?></td>
                    <td style="color: <?= $order['pnl'] >= 0 ? 'var(--groww-green)' : 'var(--groww-red)' ?>">
                        <?= $order['pnl'] != 0 ? ($order['pnl'] >= 0 ? '+' : '') . number_format($order['pnl'], 2) : '-' ?>
                    </td>
                    <td><span class="status-<?= $order['status'] ?>"><?= $order['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty">
                <i class="fa fa-list-alt"></i>
                <p>No orders yet</p>
            </div>
        <?php endif; ?>
    </div>
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
