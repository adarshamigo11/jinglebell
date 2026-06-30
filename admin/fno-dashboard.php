<?php
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
$db = getDB();

$stats = [
    'total_contracts' => $db->query("SELECT COUNT(*) FROM fno_contracts WHERE is_active = 1")->fetchColumn(),
    'total_users' => $db->query("SELECT COUNT(DISTINCT user_id) FROM fno_positions")->fetchColumn(),
    'total_positions' => $db->query("SELECT COUNT(*) FROM fno_positions WHERE is_active = 1")->fetchColumn(),
    'total_orders' => $db->query("SELECT COUNT(*) FROM fno_orders")->fetchColumn(),
    'total_margin' => $db->query("SELECT COALESCE(SUM(margin_used), 0) FROM fno_positions WHERE is_active = 1")->fetchColumn(),
    'total_pnl' => $db->query("SELECT COALESCE(SUM(pnl), 0) FROM fno_orders WHERE status = 'CLOSED'")->fetchColumn(),
    'today_orders' => $db->query("SELECT COUNT(*) FROM fno_orders WHERE DATE(created_at) = CURDATE()")->fetchColumn()
];

$topContracts = $db->query("
    SELECT fc.symbol, fc.contract_type, COUNT(*) as position_count, SUM(fp.quantity) as total_qty
    FROM fno_positions fp
    JOIN fno_contracts fc ON fc.id = fp.contract_id
    WHERE fp.is_active = 1
    GROUP BY fc.symbol, fc.contract_type
    ORDER BY position_count DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F&O Admin Dashboard - TradeZenfy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --groww-green: #00D09C; --groww-red: #FF4D4D; --groww-bg: #F5F7FA;
            --groww-card: #F5F7FA; --groww-text: #1A1A1A; --groww-text-secondary: #6B7280;
            --groww-border: #E5E7EB;
        }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--groww-bg); color: var(--groww-text); }
        .main { max-width: 1400px; margin: 0 auto; padding: 24px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--groww-card); border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-label { font-size: 13px; color: var(--groww-text-secondary); margin-bottom: 8px; }
        .stat-value { font-size: 28px; font-weight: 700; }
        .stat-value.positive { color: var(--groww-green); }
        .stat-value.negative { color: var(--groww-red); }
        
        .card { background: var(--groww-card); border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .card-title { font-size: 18px; font-weight: 600; margin-bottom: 20px; }
        
        table { width: 100%; border-collapse: collapse; }
        th { font-size: 11px; color: var(--groww-text-secondary); text-transform: uppercase; padding: 12px; text-align: left; background: #F9FAFB; }
        td { padding: 12px; font-size: 13px; border-top: 1px solid var(--groww-border); }
        
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-FUTURES { background: #DBEAFE; color: #3B82F6; }
        .badge-CALL { background: #DCFCE7; color: var(--groww-green); }
        .badge-PUT { background: #FEE2E2; color: var(--groww-red); }
        
        @media (max-width: 768px) {
            .main { padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .stat-card { padding: 16px; }
            .stat-label { font-size: 11px; }
            .stat-value { font-size: 20px; }
            table { display: block; overflow-x: auto; white-space: nowrap; }
            th, td { padding: 10px 12px; font-size: 12px; }
            h1 { font-size: 22px; }
        }
        @media (max-width: 480px) {
            .main { padding: 12px; }
            .stats-grid { grid-template-columns: 1fr; }
            h1 { font-size: 20px; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/fno-admin-header.php'; ?>

<div class="main">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 28px; margin-bottom: 8px;">F&O Admin Dashboard</h1>
        <p style="color: var(--groww-text-secondary);">Monitor and manage F&O trading</p>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Active Contracts</div>
            <div class="stat-value"><?= $stats['total_contracts'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active Users</div>
            <div class="stat-value"><?= $stats['total_users'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Today's Orders</div>
            <div class="stat-value"><?= $stats['today_orders'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Open Positions</div>
            <div class="stat-value"><?= $stats['total_positions'] ?></div>
        </div>
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
    </div>
    
    <div class="card">
        <h3 class="card-title">Top Traded Contracts</h3>
        <table>
            <thead>
                <tr>
                    <th>Symbol</th>
                    <th>Type</th>
                    <th>Position Count</th>
                    <th>Total Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topContracts as $c): ?>
                <tr>
                    <td><strong><?= $c['symbol'] ?></strong></td>
                    <td><span class="badge badge-<?= $c['contract_type'] ?>"><?= $c['contract_type'] ?></span></td>
                    <td><?= $c['position_count'] ?></td>
                    <td><?= number_format($c['total_qty']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
