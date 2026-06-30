/**
 * Trade-Zenfy - Finnhub WebSocket Price Manager
 * Include this file on any page that needs live prices.
 *
 * Usage:
 *   FinnhubWS.init('YOUR_FINNHUB_API_KEY');
 *   FinnhubWS.subscribe(['RELIANCE', 'TCS', 'INFY'], (symbol, data) => {
 *       // data = { symbol, ltp, volume, timestamp }
 *       // Update your UI here
 *   });
 */

const FinnhubWS = (() => {
    let socket       = null;
    let apiKey       = '';
    let subscribed   = new Set();
    let callback     = null;
    let reconnectTimer = null;
    let isConnected  = false;
    let pendingSubs  = [];

    function connect() {
        if (!apiKey) return;
        clearTimeout(reconnectTimer);

        socket = new WebSocket(`wss://ws.finnhub.io?token=${apiKey}`);

        socket.addEventListener('open', () => {
            isConnected = true;
            console.log('[FinnhubWS] Connected');

            // Re-subscribe all symbols (reconnect case)
            subscribed.forEach(sym => sendSub(sym));
            // Subscribe pending
            pendingSubs.forEach(sym => {
                subscribed.add(sym);
                sendSub(sym);
            });
            pendingSubs = [];
        });

        socket.addEventListener('message', (event) => {
            try {
                const msg = JSON.parse(event.data);
                if (msg.type !== 'trade' || !msg.data) return;

                msg.data.forEach(trade => {
                    // Finnhub symbol for NSE: "NSE:RELIANCE" — strip exchange prefix
                    const rawSym = trade.s || '';
                    const symbol = rawSym.includes(':') ? rawSym.split(':')[1] : rawSym;
                    const ltp    = trade.p;
                    const volume = trade.v;

                    if (!symbol || !ltp) return;

                    const payload = { symbol, ltp, volume, timestamp: trade.t };

                    // Push to backend to update DB + P&L
                    fetch('../api/update-stock-price.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ symbol, ltp, volume })
                    }).catch(() => {}); // fire-and-forget

                    // Call UI callback
                    if (typeof callback === 'function') {
                        callback(symbol, payload);
                    }
                });
            } catch (e) {
                console.warn('[FinnhubWS] Parse error', e);
            }
        });

        socket.addEventListener('close', () => {
            isConnected = false;
            console.log('[FinnhubWS] Disconnected. Reconnecting in 5s...');
            reconnectTimer = setTimeout(connect, 5000);
        });

        socket.addEventListener('error', (err) => {
            console.warn('[FinnhubWS] Error:', err);
            socket.close();
        });
    }

    function sendSub(symbol) {
        if (!socket || socket.readyState !== WebSocket.OPEN) return;
        socket.send(JSON.stringify({ type: 'subscribe', symbol: `NSE:${symbol}` }));
    }

    function sendUnsub(symbol) {
        if (!socket || socket.readyState !== WebSocket.OPEN) return;
        socket.send(JSON.stringify({ type: 'unsubscribe', symbol: `NSE:${symbol}` }));
    }

    return {
        init(key) {
            apiKey = key;
            connect();
        },

        subscribe(symbols, cb) {
            callback = cb;
            symbols.forEach(sym => {
                if (subscribed.has(sym)) return;
                if (isConnected) {
                    subscribed.add(sym);
                    sendSub(sym);
                } else {
                    pendingSubs.push(sym);
                }
            });
        },

        unsubscribe(symbols) {
            symbols.forEach(sym => {
                subscribed.delete(sym);
                sendUnsub(sym);
            });
        },

        disconnect() {
            clearTimeout(reconnectTimer);
            if (socket) socket.close();
        },

        isConnected() { return isConnected; }
    };
})();
