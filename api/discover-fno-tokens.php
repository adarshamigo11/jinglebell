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
        $tsym = $item['tradingsymbol'] ?? '';
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
    
    foreach ($underlyings as $und) {
        $base = $und['symbol'];
        $isIndex = $und['index'] ?? false;
        $exchange = $isIndex ? 'NFO' : 'NFO';
        
        // Find nearest monthly expiry futures contract
        $futures = [];
        foreach ($tokenMap as $key => $value) {
            if ($value['exchange'] !== 'NFO') continue;
            $tsym = $value['tradingsymbol'];
            $instr = $value['instrumenttype'];
            
            // Match futures: e.g. RELIANCE30JUL25FUT or NIFTY31JUL25FUT
            if ($instr === 'FUTIDX' || $instr === 'FUTSTK') {
                if (strpos($tsym, $base) === 0 && substr($tsym, -3) === 'FUT') {
                    $futures[] = $value;
                }
            }
        }
        
        // Sort by expiry and pick nearest
        usort($futures, function($a, $b) {
            return strtotime($a['expiry']) <=> strtotime($b['expiry']);
        });
        
        if (!empty($futures)) {
            $fut = $futures[0];
            $expiryDate = date('Y-m-d', strtotime($fut['expiry']));
            $lotSize = $fut['lotsize'] > 0 ? (int)$fut['lotsize'] : $und['lot_size'];
            
            // Check if exists
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
    }
    
    // Debug: show raw sample entries
    $rawSamples = array_slice($master, 0, 5, true);
    
    // Debug: show any entries with 'FUT' in tradingsymbol
    $futSamples = [];
    foreach ($master as $item) {
        if (stripos($item['tradingsymbol'] ?? '', 'FUT') !== false) {
            $futSamples[] = $item;
            if (count($futSamples) >= 5) break;
        }
    }
    
    // Debug: show any entries with RELIANCE
    $relSamples = [];
    foreach ($master as $item) {
        if (stripos($item['tradingsymbol'] ?? '', 'RELIANCE') !== false) {
            $relSamples[] = $item;
            if (count($relSamples) >= 5) break;
        }
    }
    
    // Debug: show sample futures entries
    $samples = [];
    $count = 0;
    foreach ($tokenMap as $key => $value) {
        if (($value['instrumenttype'] === 'FUTIDX' || $value['instrumenttype'] === 'FUTSTK') && $count < 10) {
            $samples[] = $value;
            $count++;
        }
    }
    
    // Debug: show samples for first underlying
    $firstBase = $underlyings[0]['symbol'];
    $matchedSamples = [];
    foreach ($tokenMap as $key => $value) {
        if ($value['exchange'] === 'NFO' && strpos($value['tradingsymbol'], $firstBase) === 0) {
            $matchedSamples[] = $value;
            if (count($matchedSamples) >= 5) break;
        }
    }
    
    echo json_encode([
        'success' => true,
        'master_count' => count($master),
        'futures_inserted' => $inserted,
        'futures_updated' => $updated,
        'raw_samples' => $rawSamples,
        'fut_symbol_samples' => $futSamples,
        'reliance_samples' => $relSamples,
        'sample_futures' => $samples,
        'sample_matches_for_' . $firstBase => $matchedSamples,
        'message' => 'F&O tokens discovered and stored'
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
