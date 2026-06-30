<?php
require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
$currentPage = 'fno-orders';
$db = getDB();

$status = $_GET['status'] ?? 'all';
$query = "SELECT fo.*, fc.symbol, fc.contract_type FROM fno_orders fo JOIN fno_contracts fc ON fc.id = fo.contract_id WHERE fo.user_id = {$user['id']}";
if ($status !== 'all') {
    $query .= " AND fo.status = '" . strtoupper($status) . "'";
}
$query .= " ORDER BY fo.created_at DESC";
$orders = $db->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F&O Orders - TradeZenfy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/assets/css/groww-ui.css">
    <link rel="stylesheet" href="../public/assets/css/layout-new.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        
        .filter-tabs { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
        .filter-tab { padding: 10px 20px; border: 1px solid var(--groww-border); background: white; border-radius: 8px; text-decoration: none; color: var(--groww-text); font-weight: 600; }
        .filter-tab.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        table { width: 100%; border-collapse: collapse; background: var(--groww-card); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th { font-size: 11px; color: var(--groww-text-secondary); text-transform: uppercase; padding: 16px 12px; text-align: left; background: #F9FAFB; }
        td { padding: 16px 12px; font-size: 13px; border-top: 1px solid var(--groww-border); }
        tr:hover td { background: #F9FAFB; }
        
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-FUTURES { background: #DBEAFE; color: #3B82F6; }
        .badge-CALL { background: #DCFCE7; color: var(--success); }
        .badge-PUT { background: #FEE2E2; color: var(--danger); }
        
        .status-EXECUTED { background: #DCFCE7; color: var(--success); padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .status-CLOSED { background: #E5E7EB; color: var(--groww-text-secondary); padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .status-PENDING { background: #FEF3C7; color: #D97706; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        
        .empty { text-align: center; padding: 60px 20px; color: var(--groww-text-secondary); background: white; border-radius: 12px; }
        .empty i { font-size: 64px; margin-bottom: 16px; opacity: 0.3; }
        
        @media (max-width: 768px) {
            .main, .main-content { padding: 16px; padding-bottom: 72px; overflow-x: hidden; }
            /* Tables: stack on mobile */
            table { display: block; width: 100%; overflow-x: hidden; }
            table thead { display: none; }
            table tbody { display: block; width: 100%; }
            table tr { display: block; padding: 12px; border-bottom: 1px solid var(--border); }
            table td { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border: none; font-size: 14px; }
            table td::before { content: attr(data-label); font-weight: 600; color: var(--text-secondary); font-size: 12px; margin-right: 12px; }
            th, td { padding: 12px 8px; font-size: 12px; }
        }
    </style>
<link rel="stylesheet" href="../public/assets/css/mobile-responsive.css">
</head>
<body>

<?php include __DIR__ . '/../includes/user-top-nav.php'; ?>

<div class="main-content">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 28px; margin-bottom: 8px;">F&O Orders</h1>
        <p style="color: var(--groww-text-secondary);">Your trading history</p>
    </div>
    
    <div class="filter-tabs">
        <a href="?status=all" class="filter-tab <?= $status === 'all' ? 'active' : '' ?>">All</a>
        <a href="?status=executed" class="filter-tab <?= $status === 'executed' ? 'active' : '' ?>">Executed</a>
        <a href="?status=closed" class="filter-tab <?= $status === 'closed' ? 'active' : '' ?>">Closed</a>
        <a href="?status=pending" class="filter-tab <?= $status === 'pending' ? 'active' : '' ?>">Pending</a>
    </div>
    
    <?php if (count($orders) > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
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
    <?php else: ?>
        <div class="empty">
            <i class="fa fa-list-alt"></i>
            <h3>No Orders Found</h3>
            <p>Your order history will appear here</p>
        </div>
    <?php endif; ?>
</div>



</body>
</html>
