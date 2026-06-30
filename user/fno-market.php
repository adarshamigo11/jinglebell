<?php
require_once __DIR__ . '/../includes/middleware.php';
$user = requireUser();
$currentPage = 'fno-market';
$db = getDB();

$filter = $_GET['filter'] ?? 'all';
$symbol = $_GET['symbol'] ?? '';

$query = "SELECT * FROM fno_contracts WHERE is_active = 1";
$params = [];

if ($filter === 'futures') {
    $query .= " AND contract_type = 'FUTURES'";
} elseif ($filter === 'options') {
    $query .= " AND contract_type IN ('CALL', 'PUT')";
}

if ($symbol) {
    $query .= " AND symbol LIKE ?";
    $params[] = "%$symbol%";
}

$query .= " ORDER BY symbol, contract_type, strike_price";
$stmt = $db->prepare($query);
$stmt->execute($params);
$contracts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F&O Market - TradeZenfy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/assets/css/groww-ui.css">
    <link rel="stylesheet" href="../public/assets/css/layout-new.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        
        /* Main Content */
        .main { flex: 1; padding: 24px; max-width: 1400px; }
        
        .controls { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
        .search-box { flex: 1; min-width: 250px; position: relative; }
        .search-box input { width: 100%; padding: 12px 12px 12px 40px; border: 1px solid var(--groww-border); border-radius: 8px; font-size: 14px; outline: none; }
        .search-box input:focus { border-color: var(--primary); }
        .search-box i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--groww-text-secondary); }
        
        .filter-tabs { display: flex; gap: 8px; }
        .filter-tab { padding: 10px 20px; border: 1px solid var(--groww-border); background: white; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; text-decoration: none; color: var(--groww-text); }
        .filter-tab.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        table { width: 100%; border-collapse: collapse; background: var(--groww-card); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th { font-size: 11px; color: var(--groww-text-secondary); text-transform: uppercase; padding: 16px 12px; text-align: left; background: #F9FAFB; }
        td { padding: 16px 12px; font-size: 13px; border-top: 1px solid var(--groww-border); }
        tr:hover td { background: #F9FAFB; cursor: pointer; }
        
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-FUTURES { background: #DBEAFE; color: #3B82F6; }
        .badge-CALL { background: #DCFCE7; color: var(--success); }
        .badge-PUT { background: #FEE2E2; color: var(--danger); }
        
        .positive { color: var(--success); }
        .negative { color: var(--danger); }
        
        @media (max-width: 768px) {
            .main, .main-content { padding: 16px; padding-bottom: 72px; overflow-x: hidden; }
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

<!-- Main Content -->
<div class="main-content">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 28px; margin-bottom: 8px;">F&O Market</h1>
        <p style="color: var(--groww-text-secondary);">Trade Futures & Options</p>
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
            <tr onclick="window.location.href='../fno-detail.php?id=<?= $c['id'] ?>'">
                <td><strong><?= $c['symbol'] ?></strong><br><small style="color: var(--groww-text-secondary)"><?= $c['stock_name'] ?></small></td>
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
    const rows = document.querySelectorAll('#contractsTable tr');
    
    rows.forEach(row => {
        const symbol = row.cells[0].textContent.toLowerCase();
        row.style.display = symbol.includes(search) ? '' : 'none';
    });
}

function toggleSidebar() {
    const sidebar = document.getElementById('userSidebar');
    const toggle = document.getElementById('sidebarToggle');
    sidebar.classList.toggle('expanded');
    const isExpanded = sidebar.classList.contains('expanded');
    toggle.innerHTML = isExpanded ? '<i class="fa fa-times"></i>' : '<i class="fa fa-bars"></i>';
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('userSidebar');
    const toggle = document.getElementById('sidebarToggle');
    if (window.innerWidth <= 768 && sidebar.classList.contains('expanded')) {
        if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
            toggleSidebar();
        }
    }
});
</script>



</body>
</html>
