<?php
/**
 * Discover F&O tokens from Angel One master file
 * Populates fno_contracts table with futures and options
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');
set_time_limit(120);
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

$underlyings = [
    ['symbol' => 'RELIANCE', 'name' => 'Reliance Industries', 'lot_size' => 250],
    ['symbol' => 'TCS',      'name' => 'Tata Consultancy',  'lot_size' => 150],
    ['symbol' => 'INFY',     'name' => 'Infosys',           'lot_size' => 400],
    ['symbol' => 'HDFCBANK', 'name' => 'HDFC Bank',         'lot_size' => 550],
    ['symbol' => 'ICICIBANK','name' => 'ICICI Bank',        'lot_size' => 1375],
    ['symbol' => 'SBIN',     'name' => 'State Bank',        'lot_size' => 1500],
    ['symbol' => 'BHARTIARTL','name' => 'Bharti Airtel',    'lot_size' => 950],
    ['symbol' => 'ITC',      'name' => 'ITC Limited',       'lot_size' => 1600],
    ['symbol' => 'LT',       'name' => 'Larsen & Toubro',   'lot_size' => 300],
    ['symbol' => 'HINDUNILVR','name' => 'Hindustan Unilever','lot_size' => 300],
    ['symbol' => 'NIFTY',    'name' => 'Nifty 50',          'lot_size' => 50,  'index' => true],
    ['symbol' => 'BANKNIFTY','name' => 'Bank Nifty',        'lot_size' => 15,  'index' => true],
];

try {
    $db = getDB();
    
    // Download master file
    $masterUrl = 'https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json';
    $json = file_get_contents($masterUrl);
    if (!$json) throw new Exception('Failed to download master file');
    
    $master = json_decode($json, true);
    if (!$master) throw new Exception('Invalid JSON: ' . json_last_error_msg());
    
    // Build token map
    $tokenMap = [];
    foreach ($master as $item) {
        $exch = $item['exch_seg'] ?? '';
        $tsym = $item['symbol'] ?? '';
        $token = $item['token'] ?? '';
        $name = $item['name'] ?? '';
        $strike = $item['strike'] ?? 0;
        $expiry = $item['expiry'] ?? '';
        $instrumentType = $item['instrumenttype'] ?? '';
        $lot = $item['lotsize'] ?? 0;
        
        if (!$token || !$tsym) continue;
        $key = $exch . ':' . $tsym;
        $tokenMap[$key] = [
            'token' => $token,
            'exchange' => $exch,
            'tradingsymbol' => $tsym,
            'name' => $name,
            'strike' => $strike,
            'expiry' => $expiry,
            'instrumenttype' => $instrumentType,
            'lotsize' => $lot
        ];
    }
    
    $inserted = 0;
    $updated = 0;
    $optionsInserted = 0;
    
    foreach ($underlyings as $und) {
        $base = $und['symbol'];
        $nearestExpiry = '';
        
        // Find all F&O contracts for this underlying
        $allContracts = [];
        foreach ($tokenMap as $key => $value) {
            if ($value['exchange'] !== 'NFO') continue;
            if ($value['name'] === $base) {
                $allContracts[] = $value;
            }
        }
        
        // Find nearest expiry
        $expiryMap = [];
        foreach ($allContracts as $c) {
            if ($c['expiry']) {
                $ts = strtotime($c['expiry']);
                if ($ts) $expiryMap[$c['expiry']] = $ts;
            }
        }
        if (!empty($expiryMap)) {
            asort($expiryMap);
            $nearestExpiry = array_key_first($expiryMap);
        }
        
        if (!$nearestExpiry) continue;
        $expiryDate = date('Y-m-d', strtotime($nearestExpiry));
        
        // Find nearest expiry futures contract
        $futures = array_filter($allContracts, function($c) {
            return ($c['instrumenttype'] === 'FUTSTK' || $c['instrumenttype'] === 'FUTIDX') && substr($c['tradingsymbol'], -3) === 'FUT';
        });
        
        usort($futures, function($a, $b) {
            return strtotime($a['expiry']) <=> strtotime($b['expiry']);
        });
        
        if (!empty($futures)) {
            $fut = $futures[0];
            $lotSize = $fut['lotsize'] > 0 ? (int)$fut['lotsize'] : $und['lot_size'];
            
            $check = $db->prepare("SELECT id FROM fno_contracts WHERE symbol = ? AND contract_type = 'FUTURES' AND expiry_date = ?");
            $check->execute([$base, $expiryDate]);
            $existing = $check->fetchColumn();
            
            if ($existing) {
                $upd = $db->prepare("UPDATE fno_contracts SET token = ?, exchange = ?, lot_size = ?, stock_name = ?, updated_at = NOW() WHERE id = ?");
                $upd->execute([$fut['token'], $fut['exchange'], $lotSize, $und['name'], $existing]);
                $updated++;
            } else {
                $ins = $db->prepare("INSERT INTO fno_contracts (symbol, stock_name, contract_type, strike_price, expiry_date, lot_size, token, exchange) VALUES (?, ?, 'FUTURES', 0, ?, ?, ?, ?)");
                $ins->execute([$base, $und['name'], $expiryDate, $lotSize, $fut['token'], $fut['exchange']]);
                $inserted++;
            }
        }
        
        // Find options for nearest expiry
        $options = array_filter($allContracts, function($c) use ($nearestExpiry) {
            return ($c['instrumenttype'] === 'OPTSTK' || $c['instrumenttype'] === 'OPTIDX') && $c['expiry'] === $nearestExpiry;
        });
        
        // Group by strike
        $strikes = [];
        foreach ($options as $opt) {
            $strikeVal = (float)$opt['strike'];
            if ($strikeVal > 100000) $strikeVal = $strikeVal / 100;
            $strikeKey = number_format($strikeVal, 2, '.', '');
            $type = (substr($opt['tradingsymbol'], -2) === 'PE') ? 'PUT' : 'CALL';
            if (!isset($strikes[$strikeKey])) $strikes[$strikeKey] = [];
            $strikes[$strikeKey][$type] = $opt;
        }
        
        // Sort strikes and pick middle 20 (around ATM)
        ksort($strikes);
        $strikeKeys = array_keys($strikes);
        $total = count($strikeKeys);
        $start = max(0, floor($total / 2) - 10);
        $selectedStrikes = array_slice($strikeKeys, $start, 20, true);
        
        foreach ($selectedStrikes as $strikeKey) {
            $row = $strikes[$strikeKey];
            foreach (['CALL', 'PUT'] as $type) {
                if (!isset($row[$type])) continue;
                $opt = $row[$type];
                $strikeVal = (float)$opt['strike'];
                if ($strikeVal > 100000) $strikeVal = $strikeVal / 100;
                
                $check = $db->prepare("SELECT id FROM fno_contracts WHERE symbol = ? AND contract_type = ? AND strike_price = ? AND expiry_date = ?");
                $check->execute([$base, $type, $strikeVal, $expiryDate]);
                $existing = $check->fetchColumn();
                
                if ($existing) {
                    $upd = $db->prepare("UPDATE fno_contracts SET token = ?, exchange = ?, lot_size = ?, updated_at = NOW() WHERE id = ?");
                    $upd->execute([$opt['token'], $opt['exchange'], $und['lot_size'], $existing]);
                } else {
                    $ins = $db->prepare("INSERT INTO fno_contracts (symbol, stock_name, contract_type, strike_price, expiry_date, lot_size, token, exchange) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $ins->execute([$base, $und['name'], $type, $strikeVal, $expiryDate, $und['lot_size'], $opt['token'], $opt['exchange']]);
                    $optionsInserted++;
                }
            }
        }
    }
    
    // Clean up debug output
    echo json_encode([
        'success' => true,
        'master_count' => count($master),
        'futures_inserted' => $inserted,
        'futures_updated' => $updated,
        'options_inserted' => $optionsInserted,
        'message' => 'F&O tokens discovered and stored'
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
