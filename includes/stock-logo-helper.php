<?php
/**
 * Stock Logo Helper Functions
 * Uses Google Favicon API for company logos (more reliable than Clearbit)
 */

/**
 * Generate Google Favicon URL for a stock
 * @param string $website Company website domain (e.g., 'reliance.com')
 * @param int $size Logo size (default 64)
 * @return string Full logo URL
 */
function getStockLogoUrl($website, $size = 128) {
    if (empty($website)) {
        return '';
    }
    
    // Google Favicon API - More reliable and faster
    return "https://www.google.com/s2/favicons?domain={$website}&sz={$size}";
}

/**
 * Generate logo URL with fallback placeholder
 * @param string $website Company website domain
 * @param string $symbol Stock symbol for fallback
 * @param int $size Logo size
 * @return string Logo URL or placeholder
 */
function getStockLogoWithFallback($website, $symbol, $size = 64) {
    if (!empty($website)) {
        return getStockLogoUrl($website, $size);
    }
    
    // Return empty for CSS placeholder
    return '';
}

/**
 * Extract domain from company name
 * @param string $companyName Company name
 * @return string Domain name
 */
function extractDomainFromName($companyName) {
    if (empty($companyName)) return '';
    
    // Remove common suffixes
    $domain = strtolower($companyName);
    $domain = preg_replace('/\s*(ltd|limited|corp|corporation|inc|incorporated|pvt|private)\.?$/i', '', $domain);
    $domain = str_replace(' ', '', $domain);
    $domain = str_replace('&', 'and', $domain);
    
    return $domain . '.com';
}
?>
