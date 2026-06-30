<?php
require_once __DIR__ . '/includes/middleware.php';
$user = requireUser();
$currentPage = 'fno-market';
$db   = getDB();

$filter = $_GET['filter'] ?? 'all';
$symbol = $_GET['symbol'] ?? '';

$query  = "SELECT * FROM fno_contracts WHERE is_active = 1";
$params = [];

if ($filter === 'futures') {
    $query .= " AND contract_type = 'FUTURES'";
} elseif ($filter === 'options') {
    $query .= " AND contract_type IN ('CALL', 'PUT')";
}

if ($symbol) {
    $query   .= " AND symbol LIKE ?";
    $params[] = "%$symbol%";
}

$query .= " ORDER BY symbol, contract_type, strike_price";
$stmt   = $db->prepare($query);
$stmt->execute($params);
$contracts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F&amp;O Market - TradeZenfy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="public/assets/css/groww-ui.css">
    <link rel="stylesheet" href="public/assets/css/layout-new.css">
    <style>
        .controls { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
        .search-box { flex: 1; min-width: 250px; position: relative; }
        .search-box input { width: 100%; padding: 12px 12px 12px 40px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; outline: none; }
        .search-box input:focus { border-color: var(--primary); }
        .search-box i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); }
        .filter-tabs { display: flex; gap: 8px; }
        .filter-tab { padding: 10px 20px; border: 1px solid var(--border); background: white; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; text-decoration: none; color: var(--text); }
        .filter-tab.active { background: var(--primary); color: white; border-color: var(--primary); }
        table { width: 100%; border-collapse: collapse; background: var(--card); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th { font-size: 11px; color: var(--text-secondary); text-transform: uppercase; padding: 16px 12px; text-align: left; background: #F9FAFB; }
        td { padding: 16px 12px; font-size: 13px; border-top: 1px solid var(--border); }
        tr:hover td { background: #F9FAFB; cursor: pointer; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-FUTURES { background: #DBEAFE; color: #3B82F6; }
        .badge-CALL { background: #DCFCE7; color: var(--success); }
        .badge-PUT { background: #FEE2E2; color: var(--danger); }
        .positive { color: var(--success); }
        .negative { color: var(--danger); }
        @media (max-width: 768px) { table { display: block; overflow-x: auto; } }
    </style>
    <link rel="stylesheet" href="public/assets/css/mobile-responsive.css">
</head>
<body>

<?php include 'includes/user-top-nav.php'; ?>

<div class="main-content">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 28px; margin-bottom: 8px;">F&amp;O Market</h1>
        <p style="color: var(--text-secondary);">Trade Futures &amp; Options</p>
    </div>

    <div class="controls">
        <div class="search-box">
            <i class="fa fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search contracts..." onkeyup="filterContracts()">
        </div>
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All</a>
            <a href="?filter=futures" class="filter-tab <?= $filter === 'futures' ? 'active' : '' ?>">Futures</a>
            <a href="?filter=options" class="filter-tab <?= $filter === 'options' ? 'active' : '' ?>">Options</a>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Symbol</th>
                <th>Type</th>
                <th>Strike</th>
                <th>Price</th>
                <th>Change %</th>
                <th>Volume</th>
                <th>Lot Size</th>
                <th>Expiry</th>
            </tr>
        </thead>
        <tbody id="contractsTable">
            <?php foreach ($contracts as $c): ?>
            <tr onclick="window.location.href='fno-detail.php?id=<?= $c['id'] ?>'">
                <td><strong><?= htmlspecialchars($c['symbol']) ?></strong><br><small style="color: var(--text-secondary)"><?= htmlspecialchars($c['stock_name']) ?></small></td>
                <td><span class="badge badge-<?= $c['contract_type'] ?>"><?= $c['contract_type'] ?></span></td>
                <td><?= $c['contract_type'] !== 'FUTURES' ? number_format($c['strike_price'], 2) : '-' ?></td>
                <td><strong><?= number_format($c['current_price'], 2) ?></strong></td>
                <td class="<?= $c['change_percent'] >= 0 ? 'positive' : 'negative' ?>">
                    <?= $c['change_percent'] >= 0 ? '+' : '' ?><?= $c['change_percent'] ?>%
                </td>
                <td><?= number_format($c['volume']) ?></td>
                <td><?= number_format($c['lot_size']) ?></td>
                <td><?= date('d M Y', strtotime($c['expiry_date'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function filterContracts() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const rows   = document.querySelectorAll('#contractsTable tr');
    rows.forEach(row => {
        const symbol = row.cells[0].textContent.toLowerCase();
        row.style.display = symbol.includes(search) ? '' : 'none';
    });
}


</script>

</body>
</html>
