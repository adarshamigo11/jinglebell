<?php
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
$db = getDB();

$filter = $_GET['filter'] ?? 'all';
$query = "SELECT * FROM fno_contracts WHERE 1=1";
if ($filter === 'futures') {
    $query .= " AND contract_type = 'FUTURES'";
} elseif ($filter === 'options') {
    $query .= " AND contract_type IN ('CALL', 'PUT')";
}
$query .= " ORDER BY symbol, contract_type, strike_price";
$contracts = $db->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F&O Contracts - Admin</title>
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
        
        .controls { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
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
        .badge-active { background: #DCFCE7; color: var(--groww-green); }
        .badge-inactive { background: #E5E7EB; color: var(--groww-text-secondary); }
        
        .toggle-btn { padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 12px; }
        .toggle-btn.active { background: var(--groww-red); color: white; }
        .toggle-btn.inactive { background: var(--groww-green); color: white; }
        
        @media (max-width: 768px) {
            .main { padding: 16px; }
            .controls { flex-wrap: wrap; gap: 8px; }
            .filter-tab { padding: 8px 14px; font-size: 12px; }
            table { display: block; overflow-x: auto; white-space: nowrap; }
            th, td { padding: 10px 12px; font-size: 12px; }
            h1 { font-size: 22px; }
            .add-btn { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .main { padding: 12px; }
            h1 { font-size: 20px; }
            .controls { gap: 6px; }
            .filter-tab { padding: 6px 12px; font-size: 11px; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/fno-admin-header.php'; ?>

<div class="main">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 28px; margin-bottom: 8px;">F&O Contracts</h1>
        <p style="color: var(--groww-text-secondary);"><?= count($contracts) ?> contracts</p>
    </div>
    
    <div class="controls">
        <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All</a>
        <a href="?filter=futures" class="filter-tab <?= $filter === 'futures' ? 'active' : '' ?>">Futures</a>
        <a href="?filter=options" class="filter-tab <?= $filter === 'options' ? 'active' : '' ?>">Options</a>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Symbol</th>
                <th>Type</th>
                <th>Strike</th>
                <th>Price</th>
                <th>Change %</th>
                <th>Lot Size</th>
                <th>Volume</th>
                <th>Expiry</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($contracts as $c): ?>
            <tr>
                <td><strong><?= $c['symbol'] ?></strong><br><small style="color: var(--groww-text-secondary)"><?= $c['stock_name'] ?></small></td>
                <td><span class="badge badge-<?= $c['contract_type'] ?>"><?= $c['contract_type'] ?></span></td>
                <td><?= $c['contract_type'] !== 'FUTURES' ? number_format($c['strike_price'], 2) : '-' ?></td>
                <td><strong><?= number_format($c['current_price'], 2) ?></strong></td>
                <td style="color: <?= $c['change_percent'] >= 0 ? 'var(--groww-green)' : 'var(--groww-red)' ?>">
                    <?= $c['change_percent'] >= 0 ? '+' : '' ?><?= $c['change_percent'] ?>%
                </td>
                <td><?= number_format($c['lot_size']) ?></td>
                <td><?= number_format($c['volume']) ?></td>
                <td><?= date('d M Y', strtotime($c['expiry_date'])) ?></td>
                <td><span class="badge badge-<?= $c['is_active'] ? 'active' : 'inactive' ?>"><?= $c['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                <td>
                    <button class="toggle-btn <?= $c['is_active'] ? 'active' : 'inactive' ?>" onclick="toggleContract(<?= $c['id'] ?>)">
                        <?= $c['is_active'] ? 'Deactivate' : 'Activate' ?>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
async function toggleContract(contractId) {
    try {
        const response = await fetch('../api/toggle-fno-contract.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ contract_id: contractId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Failed to toggle contract');
    }
}
</script>

</body>
</html>
