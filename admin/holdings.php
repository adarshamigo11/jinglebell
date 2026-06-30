<?php
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
$db    = getDB();

$search = trim($_GET['search'] ?? '');
$where  = '';
$params = [];
if ($search) {
    $where    = "WHERE (a.first_name LIKE ? OR a.last_name LIKE ? OR a.email LIKE ? OR h.symbol LIKE ?)";
    $like     = "%$search%";
    $params   = [$like, $like, $like, $like];
}

$holdings = $db->prepare("
    SELECT h.*, a.first_name, a.last_name, a.email,
           COALESCE(c.ltp, s.ltp) AS live_ltp,
           s.name AS stock_name
    FROM user_holdings h
    JOIN account_registrations a ON a.id = h.user_id
    JOIN stocks s ON s.id = h.stock_id
    LEFT JOIN stock_price_cache c ON c.stock_id = h.stock_id
    $where
    AND h.quantity > 0
    ORDER BY h.pnl DESC
    LIMIT 100
");
$holdings->execute($params);
$holdings = $holdings->fetchAll();

$totalInvested = array_sum(array_column($holdings, 'invested_amount'));
$totalValue    = array_sum(array_column($holdings, 'current_value'));
$totalPnl      = array_sum(array_column($holdings, 'pnl'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Holdings — TradeZenfy Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#F5F7FA;color:#1A1A1A;min-height:100vh;overflow-x:hidden}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.page-header h1{font-size:22px;font-weight:600}
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px}
.stat-card{background:#FFFFFF;border:1px solid #E5E7EB;border-radius:12px;padding:18px}
.stat-card .s-label{font-size:12px;color:#6B7280;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px}
.stat-card .s-value{font-size:20px;font-weight:700}
.pos{color:#10B981}.neg{color:#EF4444}
.search-bar{display:flex;gap:10px;margin-bottom:20px}
.search-bar input{flex:1;padding:10px 14px;background:#FFFFFF;border:1px solid #E5E7EB;border-radius:8px;color:#1A1A1A;font-size:14px;outline:none}
.search-bar input:focus{border-color:#2563EB}
.search-bar button{padding:10px 18px;background:#2563EB;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer}
.card{background:#FFFFFF;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden}
table{width:100%;border-collapse:collapse}
th{font-size:11px;color:#6B7280;text-transform:uppercase;letter-spacing:.05em;padding:12px 16px;text-align:left;background:#F9FAFB;white-space:nowrap}
td{padding:12px 16px;font-size:13px;border-top:1px solid #E5E7EB;vertical-align:middle}
tr:hover td{background:#F3F4F6}
.empty{padding:40px;text-align:center;color:#9CA3AF}

/* ── Mobile Responsive ── */
@media (max-width: 768px) {
  .main { padding: 20px 16px; }
  .page-header { flex-direction: column; align-items: flex-start; gap: 8px; }
  .page-header h1 { font-size: 20px; }
  .stats-row { grid-template-columns: 1fr; }
  .search-bar { flex-direction: column; }
  .search-bar button { width: 100%; }
  table { display: block; overflow-x: auto; white-space: nowrap; }
  th, td { padding: 10px 12px; font-size: 13px; }
}
@media (max-width: 480px) {
  .main { padding: 16px 12px; }
  .page-header h1 { font-size: 18px; }
  .stat-card .s-value { font-size: 18px; }
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin-header.php'; ?>

<div class="main" style="padding:32px">
  <div class="page-header">
    <h1><i class="fa fa-briefcase" style="color:#2563EB;margin-right:10px"></i>All Holdings</h1>
    <span style="font-size:13px;color:#6B7280"><?= count($holdings) ?> position(s)</span>
  </div>

  <div class="stats-row">
    <div class="stat-card">
      <div class="s-label">Total Invested (All Users)</div>
      <div class="s-value">₹<?= number_format($totalInvested,2) ?></div>
    </div>
    <div class="stat-card">
      <div class="s-label">Total Current Value</div>
      <div class="s-value">₹<?= number_format($totalValue,2) ?></div>
    </div>
    <div class="stat-card">
      <div class="s-label">Total Unrealized P&amp;L</div>
      <div class="s-value <?= $totalPnl>=0?'pos':'neg' ?>">
        <?= ($totalPnl>=0?'+':'').'₹'.number_format(abs($totalPnl),2) ?>
      </div>
    </div>
  </div>

  <form method="GET" class="search-bar">
    <input type="text" name="search" placeholder="Search by user name, email, or symbol…" value="<?= htmlspecialchars($search) ?>">
    <button type="submit"><i class="fa fa-search"></i> Search</button>
  </form>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th>User</th><th>Symbol</th><th>Qty</th><th>Avg Buy</th>
          <th>Live LTP</th><th>Invested</th><th>Current Value</th><th>Unrealized P&amp;L</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($holdings as $h):
          $ltp    = (float)$h['live_ltp'];
          $curVal = $ltp * $h['quantity'];
          $pnl    = $curVal - (float)$h['invested_amount'];
          $pnlPct = (float)$h['invested_amount']>0?($pnl/(float)$h['invested_amount'])*100:0;
        ?>
        <tr>
          <td>
            <a href="view-account.php?id=<?= $h['user_id'] ?>" style="color:#1A1A1A;text-decoration:none;font-weight:500"><?= htmlspecialchars($h['first_name'].' '.$h['last_name']) ?></a>
            <div style="font-size:11px;color:#9CA3AF"><?= htmlspecialchars($h['email']) ?></div>
          </td>
          <td>
            <div style="font-weight:700"><?= htmlspecialchars($h['symbol']) ?></div>
            <div style="font-size:11px;color:#9CA3AF"><?= htmlspecialchars($h['stock_name']) ?></div>
          </td>
          <td style="font-weight:600"><?= $h['quantity'] ?></td>
          <td>₹<?= number_format((float)$h['average_price'],2) ?></td>
          <td style="font-weight:600">₹<?= number_format($ltp,2) ?></td>
          <td>₹<?= number_format((float)$h['invested_amount'],2) ?></td>
          <td>₹<?= number_format($curVal,2) ?></td>
          <td class="<?= $pnl>=0?'pos':'neg' ?>">
            <?= ($pnl>=0?'+':'').'₹'.number_format(abs($pnl),2) ?>
            <span style="font-size:11px">(<?= ($pnlPct>=0?'+':'').number_format($pnlPct,2) ?>%)</span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($holdings)): ?>
          <tr><td colspan="8" class="empty">No holdings found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
