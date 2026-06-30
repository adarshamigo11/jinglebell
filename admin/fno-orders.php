<?php
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
$db = getDB();

$status = $_GET['status'] ?? 'all';
$query = "SELECT fo.*, fc.symbol, fc.contract_type, u.name as user_name, u.email as user_email 
          FROM fno_orders fo 
          JOIN fno_contracts fc ON fc.id = fo.contract_id
          JOIN users u ON u.id = fo.user_id";
if ($status !== 'all') {
    $query .= " WHERE fo.status = '" . strtoupper($status) . "'";
}
$query .= " ORDER BY fo.created_at DESC LIMIT 200";
$orders = $db->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F&O Orders - Admin</title>
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
        
        .filter-tabs { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
        .filter-tab { padding: 10px 20px; border: 1px solid var(--groww-border); background: white; border-radius: 8px; text-decoration: none; color: var(--groww-text); font-weight: 600; }
        .filter-tab.active { background: var(--groww-green); color: white; border-color: var(--groww-green); }
        
        table { width: 100%; border-collapse: collapse; background: var(--groww-card); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th { font-size: 11px; color: var(--groww-text-secondary); text-transform: uppercase; padding: 16px 12px; text-align: left; background: #F9FAFB; }
        td { padding: 16px 12px; font-size: 13px; border-top: 1px solid var(--groww-border); }
        tr:hover td { background: #F9FAFB; }
        
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-FUTURES { background: #DBEAFE; color: #3B82F6; }
        .badge-CALL { background: #DCFCE7; color: var(--groww-green); }
        .badge-PUT { background: #FEE2E2; color: var(--groww-red); }
        .status-EXECUTED { background: #DCFCE7; color: var(--groww-green); padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .status-CLOSED { background: #E5E7EB; color: var(--groww-text-secondary); padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        
        @media (max-width: 768px) {
            .main { padding: 16px; }
            .filter-tabs { gap: 6px; }
            .filter-tab { padding: 8px 14px; font-size: 12px; }
            table { display: block; overflow-x: auto; white-space: nowrap; }
            th, td { padding: 10px 12px; font-size: 12px; }
            h1 { font-size: 22px; }
        }
        @media (max-width: 480px) {
            .main { padding: 12px; }
            .filter-tabs { gap: 4px; }
            .filter-tab { padding: 6px 12px; font-size: 11px; }
            h1 { font-size: 20px; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/fno-admin-header.php'; ?>

<div class="main">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 28px; margin-bottom: 8px;">F&O Orders</h1>
        <p style="color: var(--groww-text-secondary);"><?= count($orders) ?> orders</p>
    </div>
    
    <div class="filter-tabs">
        <a href="?status=all" class="filter-tab <?= $status === 'all' ? 'active' : '' ?>">All</a>
        <a href="?status=executed" class="filter-tab <?= $status === 'executed' ? 'active' : '' ?>">Executed</a>
        <a href="?status=closed" class="filter-tab <?= $status === 'closed' ? 'active' : '' ?>">Closed</a>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Symbol</th>
                <th>Type</th>
                <th>Side</th>
                <th>Quantity</th>
                <th>Entry Price</th>
                <th>Exit Price</th>
                <th>P&L</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
            <tr>
                <td>#<?= $order['id'] ?></td>
                <td><?= $order['user_name'] ?><br><small style="color: var(--groww-text-secondary)"><?= $order['user_email'] ?></small></td>
                <td><strong><?= $order['symbol'] ?></strong></td>
                <td><span class="badge badge-<?= $order['contract_type'] ?>"><?= $order['contract_type'] ?></span></td>
                <td style="color: <?= $order['order_type'] === 'BUY' ? 'var(--groww-green)' : 'var(--groww-red)' ?>; font-weight: 600;"><?= $order['order_type'] ?></td>
                <td><?= number_format($order['quantity']) ?></td>
                <td><?= number_format($order['entry_price'], 2) ?></td>
                <td><?= $order['exit_price'] > 0 ? number_format($order['exit_price'], 2) : '-' ?></td>
                <td style="color: <?= $order['pnl'] >= 0 ? 'var(--groww-green)' : 'var(--groww-red)' ?>; font-weight: 600;">
                    <?= $order['pnl'] != 0 ? ($order['pnl'] >= 0 ? '+' : '') . number_format($order['pnl'], 2) : '-' ?>
                </td>
                <td><span class="status-<?= $order['status'] ?>"><?= $order['status'] ?></span></td>
                <td><?= date('d M Y H:i', strtotime($order['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>
