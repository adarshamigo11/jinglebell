/**
 * Trade-Zenfy - Yahoo Finance API Integration
 * Free stock market data from Yahoo Finance public endpoints
 * 
 * API Endpoints Used:
 * 1. Quote Summary: https://query1.finance.yahoo.com/v8/finance/chart/{symbol}
 *    - Real-time price, change, volume
 * 2. Search: https://query1.finance.yahoo.com/v1/finance/search?q={query}
 *    - Stock symbol lookup
 * 
 * Features:
 * - Real-time stock quotes (delayed 15-20 min for free tier)
 * - Historical chart data
 * - Multiple symbol support
 * - Auto-reconnect on failure
 * - Rate limiting protection
 */

const YahooFinanceAPI = (() => {
    let subscribedSymbols = new Set();
    let priceCallback = null;
    let updateInterval = null;
    let isRunning = false;
    const UPDATE_INTERVAL_MS = 30000; // 30 seconds (respect rate limits)
    const BATCH_SIZE = 10; // Max symbols per request

    /**
     * Convert NSE symbol to Yahoo Finance format
     * NSE:RELIANCE -> RELIANCE.NS
     * NSE:TCS -> TCS.NS
     * ^NSEI -> ^NSEI (indices keep their ^ prefix)
     */
    function toYahooSymbol(symbol) {
        // Remove exchange prefix if present
        let cleanSymbol = symbol;
        if (symbol.includes(':')) {
            cleanSymbol = symbol.split(':')[1];
        }
        // Indices starting with ^ should not be modified
        if (cleanSymbol.startsWith('^')) {
            return cleanSymbol;
        }
        // Add .NS suffix for NSE stocks
        if (!cleanSymbol.endsWith('.NS') && !cleanSymbol.endsWith('.BO')) {
            cleanSymbol += '.NS';
        }
        return cleanSymbol;
    }

    /**
     * Convert Yahoo symbol back to plain symbol
     * RELIANCE.NS -> RELIANCE
     * ^NSEI -> ^NSEI (indices keep their ^ prefix)
     */
    function fromYahooSymbol(yahooSymbol) {
        // Indices starting with ^ should not be modified
        if (yahooSymbol.startsWith('^')) {
            return yahooSymbol;
        }
        return yahooSymbol.replace('.NS', '').replace('.BO', '');
    }

    /**
     * Fetch real-time quotes for multiple symbols
     * Uses PHP proxy to avoid CORS issues
     */
    async function fetchQuotes(symbols) {
        const results = {};
        
        // Determine proxy URL based on current location
        const pathname = window.location.pathname;
        let proxyUrl;
        if (pathname.includes('/user/') || pathname.includes('/admin/')) {
            proxyUrl = '../api/yahoo-proxy.php';
        } else {
            proxyUrl = 'api/yahoo-proxy.php';
        }
        
        try {
            // Use PHP proxy to avoid CORS - fetch all symbols at once
            const symbolsParam = symbols.join(',');
            const url = `${proxyUrl}?symbols=${encodeURIComponent(symbolsParam)}`;
            
            console.log('[YahooFinance] Fetching via proxy:', url);
            
            const response = await fetch(url);
            
            if (!response.ok) {
                console.warn(`[YahooFinance] Proxy error: HTTP ${response.status}`);
                return results;
            }

            const data = await response.json();
            
            if (data.success && data.quotes) {
                Object.entries(data.quotes).forEach(([symbol, quote]) => {
                    results[symbol] = {
                        symbol: symbol,
                        ltp: quote.ltp,
                        change: quote.change,
                        changePercent: quote.changePercent,
                        volume: quote.volume,
                        open: quote.open,
                        high: quote.high,
                        low: quote.low,
                        previousClose: quote.previousClose,
                        timestamp: quote.timestamp * 1000,
                        currency: quote.currency,
                        exchange: quote.exchange
                    };
                });
            }
        } catch (error) {
            console.warn('[YahooFinance] Fetch error:', error);
        }
        
        return results;
    }

    /**
     * Parse Yahoo Finance response into standardized format
     */
    function parseQuoteData(data, requestedSymbols) {
        const results = {};
        
        if (!data.chart || !data.chart.result) {
            return results;
        }

        // Handle single or multiple symbols
        const results_array = Array.isArray(data.chart.result) ? data.chart.result : [data.chart.result];
        
        results_array.forEach((result, index) => {
            if (!result || !result.meta) return;
            
            const meta = result.meta;
            const symbol = fromYahooSymbol(meta.symbol || requestedSymbols[index]);
            
            // Get latest price data
            const timestamps = result.timestamp || [];
            const prices = result.indicators?.quote?.[0]?.close || [];
            const volumes = result.indicators?.quote?.[0]?.volume || [];
            
            const lastIndex = prices.length - 1;
            const currentPrice = meta.regularMarketPrice || prices[lastIndex] || 0;
            const previousClose = meta.previousClose || meta.chartPreviousClose || 0;
            const volume = meta.regularMarketVolume || volumes[lastIndex] || 0;
            
            // Calculate change
            const change = currentPrice - previousClose;
            const changePercent = previousClose > 0 ? (change / previousClose) * 100 : 0;
            
            results[symbol] = {
                symbol: symbol,
                ltp: currentPrice,
                change: change,
                changePercent: changePercent,
                volume: volume,
                open: meta.regularMarketOpen || 0,
                high: meta.regularMarketDayHigh || 0,
                low: meta.regularMarketDayLow || 0,
                previousClose: previousClose,
                timestamp: timestamps[lastIndex] ? timestamps[lastIndex] * 1000 : Date.now(),
                currency: meta.currency || 'INR',
                exchange: meta.exchangeName || 'NSE'
            };
        });

        return results;
    }

    /**
     * Fetch historical chart data for a symbol
     * @param {string} symbol - Stock symbol
     * @param {string} range - Time range (1d, 5d, 1mo, 3mo, 6mo, 1y, 2y, 5y, max)
     * @param {string} interval - Data interval (1m, 2m, 5m, 15m, 30m, 60m, 1d, 1wk, 1mo)
     */
    async function fetchHistoricalData(symbol, range = '1mo', interval = '1d') {
        const yahooSymbol = toYahooSymbol(symbol);
        
        try {
            const url = `https://query1.finance.yahoo.com/v8/finance/chart/${yahooSymbol}?interval=${interval}&range=${range}&includeAdjustedClose=true`;
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            
            if (!data.chart || !data.chart.result || !data.chart.result[0]) {
                return null;
            }

            const result = data.chart.result[0];
            const timestamps = result.timestamp || [];
            const prices = result.indicators?.quote?.[0] || {};
            
            return timestamps.map((timestamp, i) => ({
                timestamp: timestamp * 1000,
                date: new Date(timestamp * 1000),
                open: prices.open?.[i] || 0,
                high: prices.high?.[i] || 0,
                low: prices.low?.[i] || 0,
                close: prices.close?.[i] || 0,
                volume: prices.volume?.[i] || 0
            })).filter(d => d.close > 0);
        } catch (error) {
            console.warn('[YahooFinance] Historical data error:', error);
            return null;
        }
    }

    /**
     * Search for stocks by query
     */
    async function searchStocks(query) {
        try {
            const url = `https://query1.finance.yahoo.com/v1/finance/search?q=${encodeURIComponent(query)}&quotesCount=10&newsCount=0`;
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            
            return (data.quotes || []).map(quote => ({
                symbol: fromYahooSymbol(quote.symbol),
                yahooSymbol: quote.symbol,
                name: quote.shortname || quote.longname || quote.symbol,
                exchange: quote.exchange,
                type: quote.quoteType,
                sector: quote.sector,
                industry: quote.industry
            }));
        } catch (error) {
            console.warn('[YahooFinance] Search error:', error);
            return [];
        }
    }

    /**
     * Start polling for price updates
     */
    function startPolling() {
        if (updateInterval) return;
        
        isRunning = true;
        
        // Immediate first fetch
        pollPrices();
        
        // Set up interval
        updateInterval = setInterval(pollPrices, UPDATE_INTERVAL_MS);
    }

    /**
     * Stop polling
     */
    function stopPolling() {
        isRunning = false;
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    }

    /**
     * Poll current prices for all subscribed symbols
     */
    async function pollPrices() {
        if (subscribedSymbols.size === 0) return;
        
        const symbols = Array.from(subscribedSymbols);
        console.log('[YahooFinance] Polling prices for:', symbols);
        
        // Fetch all symbols (each individually)
        const quotes = await fetchQuotes(symbols);
        
        console.log('[YahooFinance] Received quotes:', quotes);
        
        if (quotes && priceCallback) {
            Object.entries(quotes).forEach(([symbol, data]) => {
                priceCallback(symbol, data);
            });
            
            // Push to backend to update DB
            Object.entries(quotes).forEach(([symbol, data]) => {
                if (data.ltp > 0) {
                    pushToBackend(symbol, data.ltp, data.volume);
                }
            });
        }
    }

    /**
     * Push price update to backend
     */
    async function pushToBackend(symbol, ltp, volume) {
        try {
            // Determine correct path based on current location
            const pathname = window.location.pathname;
            let apiPath;
            if (pathname.includes('/user/')) {
                apiPath = '../api/update-stock-price.php';
            } else if (pathname.includes('/admin/')) {
                apiPath = '../api/update-stock-price.php';
            } else {
                apiPath = 'api/update-stock-price.php';
            }
            
            await fetch(apiPath, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ symbol, ltp, volume })
            });
        } catch (e) {
            // Silent fail - don't break UI if backend update fails
            console.warn('[YahooFinance] Failed to push to backend:', e);
        }
    }

    // Public API
    return {
        /**
         * Initialize the API (no API key needed for Yahoo Finance)
         */
        init() {
            console.log('[YahooFinance] Initialized - Free tier (15-20 min delayed)');
            return this;
        },

        /**
         * Subscribe to price updates for symbols
         * @param {string[]} symbols - Array of stock symbols (e.g., ['RELIANCE', 'TCS'])
         * @param {function} callback - Callback function(symbol, data)
         */
        subscribe(symbols, callback) {
            priceCallback = callback;
            symbols.forEach(sym => subscribedSymbols.add(sym));
            
            if (!isRunning) {
                startPolling();
            } else {
                // Fetch immediately for new symbols
                pollPrices();
            }
        },

        /**
         * Unsubscribe from symbols
         */
        unsubscribe(symbols) {
            symbols.forEach(sym => subscribedSymbols.delete(sym));
            if (subscribedSymbols.size === 0) {
                stopPolling();
            }
        },

        /**
         * Get historical chart data
         */
        async getHistoricalData(symbol, range, interval) {
            return await fetchHistoricalData(symbol, range, interval);
        },

        /**
         * Search for stocks
         */
        async search(query) {
            return await searchStocks(query);
        },

        /**
         * Get single quote (one-time fetch)
         */
        async getQuote(symbol) {
            const quotes = await fetchQuotes([symbol]);
            return quotes ? quotes[symbol] : null;
        },

        /**
         * Disconnect and cleanup
         */
        disconnect() {
            stopPolling();
            subscribedSymbols.clear();
            priceCallback = null;
        },

        /**
         * Check if connected
         */
        isConnected() {
            return isRunning;
        },

        /**
         * Get subscribed symbols count
         */
        getSubscribedCount() {
            return subscribedSymbols.size;
        }
    };
})();

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = YahooFinanceAPI;
}
