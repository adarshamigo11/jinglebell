<?php
/**
 * Angel One Symbol Mapping
 * Maps Yahoo Finance symbols to Angel One SmartAPI symbols
 */

class AngelOneSymbolMap {
    
    /**
     * Convert Yahoo Finance symbol to Angel One symbol
     * 
     * @param string $yahooSymbol Yahoo Finance symbol (e.g., RELIANCE.NS)
     * @param string $exchange Exchange type (NSE, BSE, MCX)
     * @return string Angel One symbol (e.g., RELIANCE-NSE)
     */
    public static function toAngelOne($yahooSymbol, $exchange = 'NSE') {
        // Remove exchange suffix from Yahoo symbol
        $symbol = preg_replace('/\.(NS|BO|MF)$/i', '', $yahooSymbol);
        
        // Map based on exchange
        switch (strtoupper($exchange)) {
            case 'NSE':
                return $symbol . '-NSE';
            case 'BSE':
                return $symbol . '-BSE';
            case 'MCX':
                return $symbol . '-MCX';
            case 'NFO':
                return $symbol . '-NFO';
            default:
                return $symbol . '-NSE';
        }
    }
    
    /**
     * Convert Angel One symbol to Yahoo Finance symbol
     * 
     * @param string $angelSymbol Angel One symbol (e.g., RELIANCE-NSE)
     * @return string Yahoo Finance symbol (e.g., RELIANCE.NS)
     */
    public static function toYahoo($angelSymbol) {
        // Parse Angel One symbol
        if (preg_match('/^(.+)-(NSE|BSE|MCX|NFO)$/i', $angelSymbol, $matches)) {
            $symbol = $matches[1];
            $exchange = strtoupper($matches[2]);
            
            switch ($exchange) {
                case 'NSE':
                    return $symbol . '.NS';
                case 'BSE':
                    return $symbol . '.BO';
                case 'MCX':
                    return $symbol . '.MF';
                case 'NFO':
                    return $symbol . '.NS';
            }
        }
        
        return $angelSymbol;
    }
    
    /**
     * Get Angel One exchange code
     * 
     * @param string $exchange Exchange name
     * @return string Exchange code for Angel One API
     */
    public static function getExchangeCode($exchange) {
        $codes = [
            'NSE' => 'NSE',
            'BSE' => 'BSE',
            'MCX' => 'MCX',
            'NFO' => 'NFO',
            'CDS' => 'CDS',
            'NCDEX' => 'NCDEX'
        ];
        
        return $codes[strtoupper($exchange)] ?? 'NSE';
    }
    
    /**
     * Get Yahoo Finance exchange suffix
     * 
     * @param string $exchange Exchange name
     * @return string Exchange suffix for Yahoo Finance
     */
    public static function getYahooSuffix($exchange) {
        $suffixes = [
            'NSE' => '.NS',
            'BSE' => '.BO',
            'MCX' => '.MF',
            'NFO' => '.NS'
        ];
        
        return $suffixes[strtoupper($exchange)] ?? '.NS';
    }
    
    /**
     * Map commodity symbols
     * 
     * @param string $yahooSymbol Yahoo Finance commodity symbol
     * @return string Angel One commodity symbol
     */
    public static function mapCommodity($yahooSymbol) {
        // Common commodity mappings
        $mappings = [
            'GC=F' => 'GOLD-MCX',      // Gold
            'SI=F' => 'SILVER-MCX',    // Silver
            'CL=F' => 'CRUDEOIL-MCX',  // Crude Oil
            'NG=F' => 'NATURALGAS-MCX' // Natural Gas
        ];
        
        return $mappings[$yahooSymbol] ?? str_replace('=F', '-MCX', $yahooSymbol);
    }
    
    /**
     * Map index symbols
     * 
     * @param string $yahooSymbol Yahoo Finance index symbol
     * @return string Angel One index symbol
     */
    public static function mapIndex($yahooSymbol) {
        // Common index mappings
        $mappings = [
            '^NSEI' => 'NIFTY 50-NSE',
            '^BSESN' => 'SENSEX-BSE',
            '^NSEBANK' => 'BANK NIFTY-NSE',
            '^CNXIT' => 'NIFTY IT-NSE',
            '^CNXPHARMA' => 'NIFTY PHARMA-NSE'
        ];
        
        return $mappings[$yahooSymbol] ?? $yahooSymbol;
    }
    
    /**
     * Check if symbol is supported by Angel One
     * 
     * @param string $symbol Symbol to check
     * @param string $exchange Exchange type
     * @return bool True if supported
     */
    public static function isSupported($symbol, $exchange = 'NSE') {
        $supportedExchanges = ['NSE', 'BSE', 'MCX', 'NFO'];
        return in_array(strtoupper($exchange), $supportedExchanges);
    }
}
?>
