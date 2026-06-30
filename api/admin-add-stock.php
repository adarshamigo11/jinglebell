<?php
// =====================================================
// Trade-Zenfy - Admin Add Stock API
// POST /api/admin-add-stock.php
// =====================================================

// Prevent any output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

try {
    require_once __DIR__ . '/../includes/middleware.php';
    $admin = requireAdmin();
    header('Content-Type: application/json');
    
    // Clear any buffered output
    ob_clean();

    $input    = json_decode(file_get_contents('php://input'), true) ?: [];
    $symbol   = strtoupper(clean($input['symbol'] ?? ''));
    $name     = clean($input['name'] ?? '');
    $exchange = strtoupper(clean($input['exchange'] ?? 'NSE'));
    $sector   = clean($input['sector'] ?? '');
    $isin     = clean($input['isin'] ?? '');
    $ltp      = (float)($input['ltp'] ?? 0);
    $prevClose = (float)($input['prev_close'] ?? 0);
    
    // Auto-generate website from company name for logo
    $website = '';
    if ($name) {
        $website = strtolower($name);
        $website = preg_replace('/\s*(ltd|limited|corp|corporation|inc|incorporated|pvt|private)\.?$/i', '', $website);
        $website = preg_replace('/[^a-z0-9]/', '', $website);
        $website = $website . '.com';
    }

    if (!$symbol) jsonResponse(false, 'Symbol is required.');
    if (!$name)   jsonResponse(false, 'Company name is required.');
    if (!in_array($exchange, ['NSE', 'BSE'])) jsonResponse(false, 'Exchange must be NSE or BSE.');

    // Fetch current price from Yahoo Finance if not provided
    if ($ltp <= 0) {
        // Indices starting with ^ should not have .NS appended
        if (str_starts_with($symbol, '^')) {
            $yahooSymbol = $symbol;
        } else {
            $yahooSymbol = $symbol . '.NS';
        }
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$yahooSymbol}?interval=1d&range=1d";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if ($data && isset($data['chart']['result'][0])) {
                $result = $data['chart']['result'][0];
                $meta = $result['meta'];
                
                $prices = $result['indicators']['quote'][0]['close'] ?? [];
                $lastIndex = count($prices) - 1;
                $currentPrice = $meta['regularMarketPrice'] ?? ($prices[$lastIndex] ?? 0);
                $previousClose = $meta['previousClose'] ?? $meta['chartPreviousClose'] ?? 0;
                
                if ($currentPrice > 0) {
                    $ltp = round($currentPrice, 2);
                    $prevClose = round($previousClose, 2);
                }
            }
        }
    }

    $db = getDB();
    $check = $db->prepare("SELECT id, is_active FROM stocks WHERE symbol = ? AND exchange = ?");
    $check->execute([$symbol, $exchange]);
    $existing = $check->fetch();

    if ($existing) {
        if ($existing['is_active'] == 1) {
            jsonResponse(false, "Stock $symbol already exists on $exchange.");
        } else {
            // Stock was previously deleted, reactivate it
            $db->prepare("UPDATE stocks SET is_active = 1, deleted_at = NULL, deleted_by = NULL WHERE id = ?")
               ->execute([$existing['id']]);
            
            // Fetch current price for the reactivated stock
            if (str_starts_with($symbol, '^')) {
                $yahooSymbol = $symbol;
            } else {
                $yahooSymbol = $symbol . '.NS';
            }
            $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$yahooSymbol}?interval=1d&range=1d";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $newLtp = 0;
            $newPrevClose = 0;
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if ($data && isset($data['chart']['result'][0])) {
                    $result = $data['chart']['result'][0];
                    $meta = $result['meta'];
                    $prices = $result['indicators']['quote'][0]['close'] ?? [];
                    $lastIndex = count($prices) - 1;
                    $currentPrice = $meta['regularMarketPrice'] ?? ($prices[$lastIndex] ?? 0);
                    $previousClose = $meta['previousClose'] ?? $meta['chartPreviousClose'] ?? 0;
                    
                    if ($currentPrice > 0) {
                        $newLtp = round($currentPrice, 2);
                        $newPrevClose = round($previousClose, 2);
                        
                        // Update the stock with new price
                        $db->prepare("UPDATE stocks SET ltp = ?, previous_close = ? WHERE id = ?")
                           ->execute([$newLtp, $newPrevClose, $existing['id']]);
                    }
                }
            }
            
            logAdminAction($admin['id'], 'STOCK_REACTIVATED', 'stocks', $existing['id'], 
                "Stock $symbol ($name) reactivated on $exchange");
            
            $msg = "Stock $symbol has been reactivated.";
            if ($newLtp > 0) {
                $msg .= " Current price: ₹$newLtp";
            }
            jsonResponse(true, $msg, ['stock_id' => (int)$existing['id'], 'reactivated' => true]);
        }
    }

    $db->prepare("INSERT INTO stocks (symbol, name, exchange, sector, isin, website, ltp, previous_close, is_active) VALUES (?,?,?,?,?,?,?,?,1)")
       ->execute([$symbol, $name, $exchange, $sector, $isin, $website, $ltp, $prevClose]);
    $stockId = $db->lastInsertId();

    $db->prepare("INSERT INTO stock_price_cache (stock_id, ltp, change_percent) VALUES (?, ?, ?)")
       ->execute([$stockId, $ltp, $prevClose > 0 ? round((($ltp - $prevClose) / $prevClose) * 100, 2) : 0]);

    logAdminAction($admin['id'], 'STOCK_ADDED', 'stocks', $stockId, "$symbol ($name) added to $exchange");
    
    // Debug: return the actual price values
    jsonResponse(true, "Stock $symbol added successfully." . ($ltp > 0 ? " Current price: ₹$ltp" : ""), [
        'stock_id' => (int)$stockId,
        'ltp' => $ltp,
        'prev_close' => $prevClose,
        'debug' => [
            'symbol' => $symbol,
            'yahoo_symbol' => $yahooSymbol ?? null,
            'price_fetched' => $ltp > 0
        ]
    ]);
    
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
} catch (Error $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
