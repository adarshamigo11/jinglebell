<?php
require_once __DIR__ . '/includes/middleware.php';
$user = requireUser();
$currentPage = 'fno-detail';
$db = getDB();

$contractId = intval($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM fno_contracts WHERE id = ? AND is_active = 1");
$stmt->execute([$contractId]);
$contract = $stmt->fetch();

if (!$contract) {
    header('Location: fno-market.php');
    exit;
}

// Get option chain if options
$optionChain = [];
if ($contract['contract_type'] !== 'FUTURES') {
    $chainStmt = $db->prepare("
        SELECT * FROM fno_contracts 
        WHERE symbol = ? AND expiry_date = ? AND contract_type IN ('CALL', 'PUT') AND is_active = 1
        ORDER BY strike_price, contract_type DESC
    ");
    $chainStmt->execute([$contract['symbol'], $contract['expiry_date']]);
    $options = $chainStmt->fetchAll();
    
    foreach ($options as $opt) {
        $strike = $opt['strike_price'];
        if (!isset($optionChain[$strike])) {
            $optionChain[$strike] = ['strike_price' => $strike];
        }
        $optionChain[$strike][$opt['contract_type']] = $opt;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $contract['symbol'] ?> - F&O Detail - TradeZenfy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="public/assets/css/groww-ui.css">
    <link rel="stylesheet" href="public/assets/css/layout-new.css">
    <style>
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
        .symbol { font-size: 32px; font-weight: 700; }
        .stock-name { font-size: 14px; color: var(--text-secondary); }
        .price-block { text-align: right; }
        .price { font-size: 36px; font-weight: 700; }
        .change { font-size: 18px; font-weight: 600; }
        .change.positive { color: var(--success); }
        .change.negative { color: var(--danger); }
        .card { background: var(--card); border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .card-title { font-size: 18px; font-weight: 600; margin-bottom: 16px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; }
        .stat-item { padding: 12px; background: #F9FAFB; border-radius: 8px; }
        .stat-label { font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; }
        .stat-value { font-size: 16px; font-weight: 600; }
        .order-form .form-group { margin-bottom: 16px; }
        .order-form label { display: block; font-size: 13px; color: var(--text-secondary); margin-bottom: 6px; font-weight: 500; }
        .order-form input, .order-form select { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; outline: none; }
        .order-form input:focus, .order-form select:focus { border-color: var(--primary); }
        .order-tabs { display: flex; gap: 8px; margin-bottom: 20px; }
        .order-tab { flex: 1; padding: 12px; border: 2px solid var(--border); background: white; border-radius: 8px; cursor: pointer; font-weight: 600; text-align: center; }
        .order-tab.buy.active { background: var(--success); color: white; border-color: var(--success); }
        .order-tab.sell.active { background: var(--danger); color: white; border-color: var(--danger); }
        .place-btn { width: 100%; padding: 14px; border: none; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; color: white; }
        .place-btn.buy { background: var(--success); }
        .place-btn.sell { background: var(--danger); }
        .option-chain-table { width: 100%; border-collapse: collapse; }
        .option-chain-table th { background: #F9FAFB; font-size: 11px; color: var(--text-secondary); text-transform: uppercase; padding: 12px; text-align: center; }
        .option-chain-table td { padding: 12px; font-size: 13px; border-top: 1px solid var(--border); text-align: center; }
        .option-chain-table tr:hover { background: #F9FAFB; cursor: pointer; }
        .strike-active { background: #FEF3C7; font-weight: 700; }
        .positive { color: var(--success); }
        .negative { color: var(--danger); }
        @media (max-width: 768px) {
            .header { flex-direction: column; }
            .price-block { text-align: left; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
    <link rel="stylesheet" href="public/assets/css/mobile-responsive.css">
</head>
<body>

<?php include 'includes/user-top-nav.php'; ?>

<div class="main-content">
    <a href="javascript:history.back()" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-secondary); text-decoration: none; margin-bottom: 16px;">
        <i class="fa fa-arrow-left"></i> Back
    </a>
    
    <div class="header">
        <div>
            <div class="symbol"><?= $contract['symbol'] ?></div>
            <div class="stock-name"><?= $contract['stock_name'] ?> - <?= $contract['contract_type'] ?></div>
            <?php if ($contract['contract_type'] !== 'FUTURES'): ?>
                <div style="font-size: 14px; color: var(--text-secondary); margin-top: 4px;">
                    Strike: <?= number_format($contract['strike_price'], 2) ?> | Expiry: <?= date('d M Y', strtotime($contract['expiry_date'])) ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="price-block">
            <div class="price"><?= number_format($contract['current_price'], 2) ?></div>
            <div class="change <?= $contract['change_percent'] >= 0 ? 'positive' : 'negative' ?>">
                <?= $contract['change_percent'] >= 0 ? '+' : '' ?><?= $contract['change_percent'] ?>%
            </div>
        </div>
    </div>
    
    <!-- Stats -->
    <div class="card">
        <h3 class="card-title">Contract Details</h3>
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-label">Lot Size</div>
                <div class="stat-value"><?= number_format($contract['lot_size']) ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Expiry Date</div>
                <div class="stat-value"><?= date('d M Y', strtotime($contract['expiry_date'])) ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Volume</div>
                <div class="stat-value"><?= number_format($contract['volume']) ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Open Interest</div>
                <div class="stat-value"><?= number_format($contract['open_interest']) ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">High</div>
                <div class="stat-value"><?= number_format($contract['high_price'], 2) ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Low</div>
                <div class="stat-value"><?= number_format($contract['low_price'], 2) ?></div>
            </div>
        </div>
    </div>
    
    <!-- Order Form -->
    <div class="card">
        <h3 class="card-title">Place Order</h3>
        
        <div class="order-tabs">
            <button class="order-tab buy active" onclick="setOrderType('BUY')">BUY</button>
            <button class="order-tab sell" onclick="setOrderType('SELL')">SELL</button>
        </div>
        
        <div class="order-form">
            <div class="form-group">
                <label>Quantity (in shares)</label>
                <input type="number" id="quantity" placeholder="Enter quantity" value="<?= $contract['lot_size'] ?>" step="<?= $contract['lot_size'] ?>" min="<?= $contract['lot_size'] ?>">
            </div>
            
            <div class="form-group">
                <label>Stop Loss (Optional)</label>
                <input type="number" id="stopLoss" placeholder="Stop loss price" step="0.01">
            </div>
            
            <div class="form-group">
                <label>Target (Optional)</label>
                <input type="number" id="target" placeholder="Target price" step="0.01">
            </div>
            
            <div style="background: #F9FAFB; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: var(--text-secondary);">Lots:</span>
                    <strong id="lotsCount">1</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: var(--text-secondary);">Margin Required:</span>
                    <strong id="marginRequired">₹0.00</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">Your Balance:</span>
                    <strong>₹<?= number_format($user['current_balance'], 2) ?></strong>
                </div>
            </div>
            
            <button class="place-btn buy" id="placeOrderBtn" onclick="placeOrder()">
                BUY <?= $contract['symbol'] ?>
            </button>
        </div>
    </div>
    
    <!-- Option Chain -->
    <?php if (count($optionChain) > 0): ?>
    <div class="card">
        <h3 class="card-title">Option Chain</h3>
        <table class="option-chain-table">
            <thead>
                <tr>
                    <th colspan="3" style="background: #DCFCE7; color: var(--success);">CALLS</th>
                    <th>Strike</th>
                    <th colspan="3" style="background: #FEE2E2; color: var(--danger);">PUTS</th>
                </tr>
                <tr>
                    <th>OI</th>
                    <th>LTP</th>
                    <th>Change</th>
                    <th></th>
                    <th>OI</th>
                    <th>LTP</th>
                    <th>Change</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($optionChain as $strike => $data): 
                    $isATM = abs($strike - $contract['current_price']) < 10;
                ?>
                <tr class="<?= $isATM ? 'strike-active' : '' ?>">
                    <td><?= isset($data['CALL']) ? number_format($data['CALL']['open_interest']) : '-' ?></td>
                    <td><?= isset($data['CALL']) ? number_format($data['CALL']['current_price'], 2) : '-' ?></td>
                    <td class="<?= isset($data['CALL']) && $data['CALL']['change_percent'] >= 0 ? 'positive' : 'negative' ?>">
                        <?= isset($data['CALL']) ? ($data['CALL']['change_percent'] >= 0 ? '+' : '') . $data['CALL']['change_percent'] . '%' : '-' ?>
                    </td>
                    <td><strong><?= number_format($strike, 2) ?></strong></td>
                    <td><?= isset($data['PUT']) ? number_format($data['PUT']['open_interest']) : '-' ?></td>
                    <td><?= isset($data['PUT']) ? number_format($data['PUT']['current_price'], 2) : '-' ?></td>
                    <td class="<?= isset($data['PUT']) && $data['PUT']['change_percent'] >= 0 ? 'positive' : 'negative' ?>">
                        <?= isset($data['PUT']) ? ($data['PUT']['change_percent'] >= 0 ? '+' : '') . $data['PUT']['change_percent'] . '%' : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
let currentOrderType = 'BUY';
const contractId = <?= $contractId ?>;
const lotSize = <?= $contract['lot_size'] ?>;

function setOrderType(type) {
    currentOrderType = type;
    document.querySelectorAll('.order-tab').forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    
    const btn = document.getElementById('placeOrderBtn');
    btn.textContent = `${type} <?= $contract['symbol'] ?>`;
    btn.className = `place-btn ${type.toLowerCase()}`;
    updateMargin();
}

document.getElementById('quantity').addEventListener('input', updateMargin);

function updateMargin() {
    const quantity = parseInt(document.getElementById('quantity').value) || 0;
    const lots = quantity / lotSize;
    document.getElementById('lotsCount').textContent = lots;
    
    fetch(`api/calculate-fno-margin.php?contract_id=${contractId}&quantity=${quantity}&order_type=${currentOrderType}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('marginRequired').textContent = '₹' + data.margin_required.toFixed(2);
            }
        });
}

async function placeOrder() {
    const quantity = parseInt(document.getElementById('quantity').value);
    const stopLoss = document.getElementById('stopLoss').value || null;
    const target = document.getElementById('target').value || null;
    
    if (!quantity || quantity % lotSize !== 0) {
        alert(`Quantity must be in multiples of ${lotSize}`);
        return;
    }
    
    if (!confirm(`Place ${currentOrderType} order for ${quantity} shares of <?= $contract['symbol'] ?>?`)) return;
    
    try {
        const response = await fetch('api/place-fno-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                contract_id: contractId,
                order_type: currentOrderType,
                quantity: quantity,
                stop_loss: stopLoss,
                target: target
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Order placed successfully!\nMargin Used: ₹' + data.margin_used.toFixed(2));
            window.location.href = 'user/fno-dashboard.php';
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Failed to place order');
    }
}

updateMargin();
</script>

</body>
</html>
