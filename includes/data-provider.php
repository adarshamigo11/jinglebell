<?php
/**
 * Data Provider Switcher
 * Routes data requests to appropriate API provider based on admin settings
 */

require_once __DIR__ . '/angel-one-api.php';

/**
 * Get the active provider for a specific asset type
 */
function getActiveProvider($assetType) {
    static $cache = [];
    
    if (isset($cache[$assetType])) {
        return $cache[$assetType];
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT dpp.provider, dpp.is_enabled
            FROM data_provider_preferences dpp
            WHERE dpp.asset_type = ?
        ");
        $stmt->execute([$assetType]);
        $pref = $stmt->fetch();
        
        if ($pref && $pref['is_enabled']) {
            // Check if the provider is active
            $stmt = $db->prepare("SELECT is_active FROM api_settings WHERE provider = ?");
            $stmt->execute([$pref['provider']]);
            $isActive = $stmt->fetchColumn();
            
            if ($isActive) {
                $cache[$assetType] = $pref['provider'];
                return $pref['provider'];
            }
        }
    } catch (Exception $e) {
        error_log("Error getting provider for $assetType: " . $e->getMessage());
    }
    
    // Default to Yahoo Finance
    $cache[$assetType] = 'yahoo_finance';
    return 'yahoo_finance';
}

/**
 * Get stock price from appropriate provider
 */
function getStockPrice($symbol, $exchange = 'NSE') {
    $provider = getActiveProvider('stocks');
    
    if ($provider === 'angel_one') {
        $api = AngelOneAPI::fromDatabase();
        if ($api) {
            $quote = $api->getQuote($symbol, $exchange);
            if ($quote) {
                return $quote;
            }
        }
        // Fallback to Yahoo Finance if Angel One fails
        error_log("Angel One failed for $symbol, falling back to Yahoo Finance");
    }
    
    // Yahoo Finance (default/fallback)
    return getYahooFinanceQuote($symbol);
}

/**
 * Get multiple stock prices at once
 */
function getStockPrices($symbols, $exchange = 'NSE') {
    $provider = getActiveProvider('stocks');
    
    if ($provider === 'angel_one') {
        $api = AngelOneAPI::fromDatabase();
        if ($api) {
            $quotes = $api->getQuotes($symbols, $exchange);
            if (!empty($quotes)) {
                return $quotes;
            }
        }
    }
    
    // Yahoo Finance fallback - fetch one by one
    $quotes = [];
    foreach ($symbols as $symbol) {
        $quote = getYahooFinanceQuote($symbol);
        if ($quote) {
            $quotes[$symbol] = $quote;
        }
    }
    return $quotes;
}

/**
 * Get commodity price
 */
function getCommodityPrice($symbol) {
    $provider = getActiveProvider('commodities');
    
    if ($provider === 'angel_one') {
        $api = AngelOneAPI::fromDatabase();
        if ($api) {
            $quote = $api->getCommodityQuote($symbol);
            if ($quote) {
                return $quote;
            }
        }
    }
    
    // Yahoo Finance fallback
    return getYahooFinanceQuote($symbol);
}

/**
 * Get index data
 */
function getIndexData($symbol) {
    $provider = getActiveProvider('indices');
    
    if ($provider === 'angel_one') {
        $api = AngelOneAPI::fromDatabase();
        if ($api) {
            $quote = $api->getIndexQuote($symbol);
            if ($quote) {
                return $quote;
            }
        }
    }
    
    // Yahoo Finance fallback
    return getYahooFinanceQuote($symbol);
}

/**
 * Get option chain data
 */
function getOptionChainData($symbol, $expiry = null) {
    $provider = getActiveProvider('stocks'); // Options use stocks provider
    
    if ($provider === 'angel_one') {
        $api = AngelOneAPI::fromDatabase();
        if ($api) {
            return $api->getOptionChain($symbol, $expiry);
        }
    }
    
    // Yahoo Finance doesn't have good option chain support for Indian markets
    return null;
}

/**
 * Get Yahoo Finance quote (fallback method)
 * This is a simplified version - use your existing Yahoo Finance implementation
 */
function getYahooFinanceQuote($symbol) {
    // This should call your existing Yahoo Finance API
    // For now, return data from database cache
    
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT s.symbol, s.ltp, s.previous_close, s.open_price, s.high_price, s.low_price, s.volume
            FROM stocks s
            WHERE s.symbol = ?
        ");
        $stmt->execute([$symbol]);
        $stock = $stmt->fetch();
        
        if ($stock) {
            $ltp = (float)$stock['ltp'];
            $prevClose = (float)$stock['previous_close'];
            $change = $ltp - $prevClose;
            $changePercent = $prevClose > 0 ? ($change / $prevClose) * 100 : 0;
            
            return [
                'symbol' => $stock['symbol'],
                'ltp' => $ltp,
                'open' => (float)$stock['open_price'],
                'high' => (float)$stock['high_price'],
                'low' => (float)$stock['low_price'],
                'close' => $prevClose,
                'volume' => (int)$stock['volume'],
                'change' => $change,
                'change_percent' => $changePercent
            ];
        }
    } catch (Exception $e) {
        error_log("Error fetching Yahoo Finance quote: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Check if Angel One is active for any asset type
 */
function isAngelOneActive() {
    try {
        $db = getDB();
        $stmt = $db->query("
            SELECT COUNT(*) FROM data_provider_preferences 
            WHERE provider = 'angel_one' AND is_enabled = 1
        ");
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get all provider preferences
 */
function getAllProviderPreferences() {
    try {
        $db = getDB();
        $stmt = $db->query("
            SELECT asset_type, provider, is_enabled
            FROM data_provider_preferences
            ORDER BY asset_type
        ");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Update provider preference for an asset type
 */
function updateProviderPreference($assetType, $provider, $isEnabled = true) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE data_provider_preferences
            SET provider = ?, is_enabled = ?
            WHERE asset_type = ?
        ");
        return $stmt->execute([$provider, $isEnabled ? 1 : 0, $assetType]);
    } catch (Exception $e) {
        error_log("Error updating provider preference: " . $e->getMessage());
        return false;
    }
}
?>
