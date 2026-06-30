<?php
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
$db = getDB();

$positions = $db->query("
    SELECT fp.*, fc.symbol, fc.contract_type, fc.strike_price, fc.expiry_date, fc.current_price, 
           u.name as user_name, u.email as user_email
    FROM fno_positions fp
    JOIN fno_contracts fc ON fc.id = fp.contract_id
    JOIN users u ON u.id = fp.user_id
    WHERE fp.is_active = 1
    ORDER BY fp.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F&O Risk Monitor - Admin</title>
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
        
        .alert { background: #FEE2E2; border-left: 4px solid var(--groww-red); padding: 16px; margin-bottom: 24px; border-radius: 8px; }
        .alert-title { font-weight: 700; color: var(--groww-red); margin-bottom: 4px; }
        
        table { width: 100%; border-collapse: collapse; background: var(--groww-card); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th { font-size: 11px; color: var(--groww-text-secondary); text-transform: uppercase; padding: 16px 12px; text-align: left; background: #F9FAFB; }
        td { padding: 16px 12px; font-size: 13px; border-top: 1px solid var(--groww-border); }
        tr:hover td { background: #F9FAFB; }
        
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-FUTURES { background: #DBEAFE; color: #3B82F6; }
        .badge-CALL { background: #DCFCE7; color: var(--groww-green); }
        .badge-PUT { background: #FEE2E2; color: var(--groww-red); }
        .pos-BUY { background: #DCFCE7; color: var(--groww-green); padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .pos-SELL { background: #FEE2E2; color: var(--groww-red); padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        
        .risk-high { background: #FEE2E2; }
        .risk-medium { background: #FEF3C7; }
        
        @media (max-width: 768px) {
            .main { padding: 16px; }
            .alert { padding: 14px; margin-bottom: 20px; }
            .alert-title { font-size: 14px; }
            table { display: block; overflow-x: auto; white-space: nowrap; }
            th, td { padding: 10px 12px; font-size: 12px; }
            h1 { font-size: 22px; }
        }
        @media (max-width: 480px) {
            .main { padding: 12px; }
            h1 { font-size: 20px; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/fno-admin-header.php'; ?>

<div class="main">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 28px; margin-bottom: 8px;">F&O Risk Monitor</h1>
        <p style="color: var(--groww-text-secondary);">Monitor open positions & risk</p>
    </div>
    
    <?php
    // High risk positions (loss > 50%)
    $highRisk = [];
    foreach ($positions as $pos) {
        $pnl = $pos['position_type'] === 'BUY' 
            ? ($pos['current_price'] - $pos['entry_price']) * $pos['quantity']
            : ($pos['entry_price'] - $pos['current_price']) * $pos['quantity'];
        $pnlPercent = ($pnl / $pos['margin_used']) * 100;
        if ($pnlPercent < -50) {
            $highRisk[] = ['position' => $pos, 'pnl' => $pnl, 'pnl_percent' => $pnlPercent];
        }
    }
    ?>
    
    <?php if (count($highRisk) > 0): ?>
    <div class="alert">
        <div class="alert-title">⚠️ High Risk Positions: <?= count($highRisk) ?></div>
        <div>These positions have losses exceeding 50% of margin</div>
    </div>
    <?php endif; ?>
    
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Symbol</th>
                <th>Type</th>
                <th>Side</th>
                <th>Quantity</th>
                <th>Entry Price</th>
                <th>Current Price</th>
                <th>Margin Used</th>
                <th>P&L</th>
                <th>P&L %</th>
                <th>Expiry</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($positions as $pos): 
                $pnl = $pos['position_type'] === 'BUY' 
                    ? ($pos['current_price'] - $pos['entry_price']) * $pos['quantity']
                    : ($pos['entry_price'] - $pos['current_price']) * $pos['quantity'];
                $pnlPercent = ($pnl / $pos['margin_used']) * 100;
                $riskClass = $pnlPercent < -50 ? 'risk-high' : ($pnlPercent < -25 ? 'risk-medium' : '');
            ?>
            <tr class="<?= $riskClass ?>">
                <td><?= $pos['user_name'] ?><br><small style="color: var(--groww-text-secondary)"><?= $pos['user_email'] ?></small></td>
                <td><strong><?= $pos['symbol'] ?></strong></td>
                <td><span class="badge badge-<?= $pos['contract_type'] ?>"><?= $pos['contract_type'] ?></span></td>
                <td><span class="pos-<?= $pos['position_type'] ?>"><?= $pos['position_type'] ?></span></td>
                <td><?= number_format($pos['quantity']) ?></td>
                <td><?= number_format($pos['entry_price'], 2) ?></td>
                <td><?= number_format($pos['current_price'], 2) ?></td>
                <td>₹<?= number_format($pos['margin_used'], 2) ?></td>
                <td style="color: <?= $pnl >= 0 ? 'var(--groww-green)' : 'var(--groww-red)' ?>; font-weight: 600;">
                    <?= $pnl >= 0 ? '+' : '' ?>₹<?= number_format($pnl, 2) ?>
                </td>
                <td style="color: <?= $pnlPercent >= 0 ? 'var(--groww-green)' : 'var(--groww-red)' ?>; font-weight: 600;">
                    <?= $pnlPercent >= 0 ? '+' : '' ?><?= number_format($pnlPercent, 2) ?>%
                </td>
                <td><?= date('d M Y', strtotime($pos['expiry_date'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>
