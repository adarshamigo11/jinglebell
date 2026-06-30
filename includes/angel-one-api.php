<?php
/**
 * Angel One SmartAPI Wrapper
 * Handles authentication and API calls to Angel One SmartAPI
 */

require_once __DIR__ . '/angel-one-symbol-map.php';

class AngelOneAPI {
    
    private $apiKey;
    private $clientId;
    private $password;
    private $totpSecret;
    private $jwtToken;
    private $feedToken;
    private $baseUrl = 'https://apiconnect.angelone.in';
    private $lastError;
    
    /**
     * Constructor
     */
    public function __construct($apiKey = null, $clientId = null, $password = null, $totpSecret = null) {
        $this->apiKey = $apiKey;
        $this->clientId = $clientId;
        $this->password = $password;
        $this->totpSecret = $totpSecret;
    }
    
    /**
     * Load credentials from database
     */
    public static function fromDatabase() {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM api_settings WHERE provider = 'angel_one' AND is_active = 1");
        $stmt->execute();
        $settings = $stmt->fetch();
        
        if (!$settings) {
            return null;
        }
        
        return new self(
            $settings['api_key'],
            $settings['client_id'],
            $settings['password'],
            $settings['totp_secret']
        );
    }
    
    /**
     * Login to Angel One and get JWT token
     */
    public function login() {
        if (!$this->apiKey || !$this->clientId || !$this->password) {
            $this->lastError = 'API credentials not configured';
            return false;
        }
        
        // Generate TOTP if secret is provided
        $totp = $this->totpSecret ? $this->generateTOTP($this->totpSecret) : '';
        
        $payload = [
            'apikey' => $this->apiKey,
            'clientcode' => $this->clientId,
            'password' => $this->password,
            'totp' => $totp
        ];
        
        $response = $this->request('/rest/auth/angelbroking/user/v1/loginByPassword', $payload);
        
        if ($response && $response['status'] && isset($response['data']['jwtToken'])) {
            $this->jwtToken = $response['data']['jwtToken'];
            $this->feedToken = $response['data']['feedToken'] ?? null;
            return true;
        }
        
        $this->lastError = $response['message'] ?? 'Login failed';
        return false;
    }
    
    /**
     * Get live quote for a symbol
     */
    public function getQuote($symbol, $exchange = 'NSE') {
        if (!$this->jwtToken && !$this->login()) {
            return null;
        }
        
        $angelSymbol = AngelOneSymbolMap::toAngelOne($symbol, $exchange);
        
        $payload = [
            'mode' => 'LTP',
            'exchangeTokens' => [
                AngelOneSymbolMap::getExchangeCode($exchange) => [$angelSymbol]
            ]
        ];
        
        $response = $this->request('/rest/secure/angelbroking/market/v1/quote/', $payload, true);
        
        if ($response && $response['status'] && isset($response['data']['fetched'])) {
            $quotes = $response['data']['fetched'];
            if (!empty($quotes)) {
                return $this->formatQuote($quotes[0]);
            }
        }
        
        $this->lastError = $response['message'] ?? 'Failed to fetch quote';
        return null;
    }
    
    /**
     * Get multiple quotes at once
     */
    public function getQuotes($symbols, $exchange = 'NSE') {
        if (!$this->jwtToken && !$this->login()) {
            return [];
        }
        
        $exchangeTokens = [];
        foreach ($symbols as $symbol) {
            $angelSymbol = AngelOneSymbolMap::toAngelOne($symbol, $exchange);
            $exchangeCode = AngelOneSymbolMap::getExchangeCode($exchange);
            
            if (!isset($exchangeTokens[$exchangeCode])) {
                $exchangeTokens[$exchangeCode] = [];
            }
            $exchangeTokens[$exchangeCode][] = $angelSymbol;
        }
        
        $payload = [
            'mode' => 'LTP',
            'exchangeTokens' => $exchangeTokens
        ];
        
        $response = $this->request('/rest/secure/angelbroking/market/v1/quote/', $payload, true);
        
        if ($response && $response['status'] && isset($response['data']['fetched'])) {
            $quotes = [];
            foreach ($response['data']['fetched'] as $quote) {
                $yahooSymbol = AngelOneSymbolMap::toYahoo($quote['symbol']);
                $quotes[$yahooSymbol] = $this->formatQuote($quote);
            }
            return $quotes;
        }
        
        return [];
    }
    
    /**
     * Get option chain data
     */
    public function getOptionChain($symbol, $expiry = null, $exchange = 'NFO') {
        if (!$this->jwtToken && !$this->login()) {
            return null;
        }
        
        $payload = [
            'name' => $symbol,
            'exchange' => AngelOneSymbolMap::getExchangeCode($exchange)
        ];
        
        if ($expiry) {
            $payload['expiry'] = $expiry;
        }
        
        $response = $this->request('/rest/secure/angelbroking/option-chain/', $payload, true);
        
        if ($response && $response['status']) {
            return $response['data'];
        }
        
        $this->lastError = $response['message'] ?? 'Failed to fetch option chain';
        return null;
    }
    
    /**
     * Get commodity quote
     */
    public function getCommodityQuote($symbol) {
        return $this->getQuote($symbol, 'MCX');
    }
    
    /**
     * Get index quote
     */
    public function getIndexQuote($symbol) {
        // Angel One uses different symbol format for indices
        $angelSymbol = AngelOneSymbolMap::mapIndex($symbol);
        return $this->getQuote($angelSymbol, 'NSE');
    }
    
    /**
     * Search for symbols
     */
    public function searchSymbol($query, $exchange = 'NSE') {
        if (!$this->jwtToken && !$this->login()) {
            return [];
        }
        
        $payload = [
            'searchSymbol' => $query,
            'exchange' => AngelOneSymbolMap::getExchangeCode($exchange)
        ];
        
        $response = $this->request('/rest/secure/angelbroking/search/scrip/', $payload, true);
        
        if ($response && $response['status'] && isset($response['data'])) {
            return $response['data'];
        }
        
        return [];
    }
    
    /**
     * Get last error message
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Test API connection
     */
    public function testConnection() {
        return $this->login();
    }
    
    /**
     * Make API request
     */
    private function request($endpoint, $payload = [], $auth = false) {
        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-UserType: USER',
            'X-SourceID: WEB',
            'X-ClientLocalIP: CLIENT_LOCAL_IP',
            'X-ClientPublicIP: CLIENT_PUBLIC_IP',
            'X-MACAddress: MAC_ADDRESS',
            'X-PrivateKey: ' . $this->apiKey
        ];
        
        if ($auth && $this->jwtToken) {
            $headers[] = 'Authorization: Bearer ' . $this->jwtToken;
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $this->lastError = "HTTP Error: $httpCode";
            return null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Format quote response
     */
    private function formatQuote($data) {
        return [
            'symbol' => $data['symbol'] ?? '',
            'ltp' => (float)($data['ltp'] ?? 0),
            'open' => (float)($data['open'] ?? 0),
            'high' => (float)($data['high'] ?? 0),
            'low' => (float)($data['low'] ?? 0),
            'close' => (float)($data['close'] ?? 0),
            'volume' => (int)($data['tradeVolume'] ?? 0),
            'change' => (float)($data['netPriceChange'] ?? 0),
            'change_percent' => (float)($data['netPercentageChange'] ?? 0)
        ];
    }
    
    /**
     * Generate TOTP code from base32 secret
     */
    private function generateTOTP($secret) {
        // Remove spaces and convert to uppercase
        $secret = strtoupper(str_replace(' ', '', $secret));
        
        // Base32 decode the secret
        $decodedSecret = $this->base32Decode($secret);
        if ($decodedSecret === false) {
            $this->lastError = 'Invalid TOTP secret (base32 decode failed)';
            return '';
        }
        
        // Get current time step (30-second intervals)
        $timeStep = floor(time() / 30);
        
        // Pack time as 8-byte big-endian
        $timeBytes = pack('N*', 0, $timeStep);
        
        // HMAC-SHA1
        $hmac = hash_hmac('sha1', $timeBytes, $decodedSecret, true);
        
        // Dynamic truncation
        $offset = ord($hmac[19]) & 0x0F;
        $code = (
            ((ord($hmac[$offset]) & 0x7F) << 24) |
            ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
            ((ord($hmac[$offset + 2]) & 0xFF) << 8) |
            (ord($hmac[$offset + 3]) & 0xFF)
        ) % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Base32 decode
     */
    private function base32Decode($input) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper(rtrim($input, '='));
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';
        
        for ($i = 0; $i < strlen($input); $i++) {
            $val = strpos($alphabet, $input[$i]);
            if ($val === false) return false;
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $output;
    }
}
?>
