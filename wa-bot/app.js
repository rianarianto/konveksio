const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    makeCacheableSignalKeyStore
} = require('@whiskeysockets/baileys');
const pino = require('pino');
const qrcode = require('qrcode');
const qrcodeTerminal = require('qrcode-terminal');
const express = require('express');
const http = require('http');
const socketIO = require('socket.io');
const fs = require('fs-extra');
const path = require('path');

const app = express();
const server = http.createServer(app);
const io = socketIO(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

// ============================================================
// STATE MANAGEMENT
// ============================================================
let clientStatus = 'not ready';
let lastQRUrl = '';
let rawQR = '';
let sock = null;
let connectedPhone = null;     // Nomor WA yang terhubung
let botStartedAt = new Date(); // Waktu bot di-start

// In-memory message store for getMessage handler (Baileys retry mechanism)
const messageStore = new Map();
const MESSAGE_STORE_MAX = 500; // Max messages to keep in store

// Delivery tracking
let totalSentToday = 0;
let totalDeliveredToday = 0;
let totalFailedToday = 0;
let consecutiveFailures = 0;       // Track consecutive ghost messages
const MAX_CONSECUTIVE_FAILURES = 3; // Auto-reconnect threshold
let lastMessageSentAt = null;
let lastDeliveryConfirmedAt = null;
let isReconnecting = false;

// Message log (last 50 sent messages)
const messageLog = [];
const MESSAGE_LOG_MAX = 50;

// Pending delivery map: messageId -> { jid, timestamp, status }
const pendingDelivery = new Map();
const DELIVERY_TIMEOUT_MS = 15000; // 15 seconds to wait for delivery ack

// Path auth folder Baileys
const AUTH_DIR = path.join(__dirname, 'auth_info_baileys');

// Reset daily counters at midnight
const resetDailyCounters = () => {
    const now = new Date();
    if (now.getHours() === 0 && now.getMinutes() === 0) {
        totalSentToday = 0;
        totalDeliveredToday = 0;
        totalFailedToday = 0;
    }
};
setInterval(resetDailyCounters, 60000);

// ============================================================
// HELPER FUNCTIONS
// ============================================================

// Broadcast status function
const broadcastStatus = (status, message) => {
    clientStatus = status;
    console.log(`📢 Broadcasting status: ${status} - ${message}`);
    io.emit('status', { status, message });

    if (status === 'ready') {
        io.emit('ready', message);
    } else if (status === 'qr') {
        io.emit('qr', lastQRUrl);
    } else {
        io.emit('disconnected', message);
    }
};

// Format phone number to JID format for Baileys
const formatToJid = (numberStr) => {
    if (!numberStr) return null;
    let cleaned = String(numberStr).trim().replace(/\D/g, '');
    
    if (cleaned.startsWith('0')) {
        cleaned = '62' + cleaned.slice(1);
    } else if (cleaned.startsWith('8')) {
        cleaned = '62' + cleaned;
    }

    if (!cleaned.endsWith('@s.whatsapp.net')) {
        cleaned = cleaned + '@s.whatsapp.net';
    }

    return cleaned;
};

// Clean directory helper
const cleanAuthDir = async () => {
    try {
        if (await fs.pathExists(AUTH_DIR)) {
            await fs.remove(AUTH_DIR);
            console.log('🗑️ Auth directory cleaned up successfully.');
        }
    } catch (err) {
        console.error('❌ Failed to clean auth directory:', err);
    }
};

// Add to message log
const addToLog = (entry) => {
    messageLog.unshift({
        ...entry,
        timestamp: new Date().toISOString(),
    });
    if (messageLog.length > MESSAGE_LOG_MAX) {
        messageLog.pop();
    }
};

// Store message for Baileys getMessage retry
const storeMessage = (msgId, message) => {
    messageStore.set(msgId, message);
    // Evict oldest if full
    if (messageStore.size > MESSAGE_STORE_MAX) {
        const oldest = messageStore.keys().next().value;
        messageStore.delete(oldest);
    }
};

// ============================================================
// WHATSAPP CONNECTION (Baileys)
// ============================================================
const connectToWhatsApp = async () => {
    if (isReconnecting) {
        console.log('⏳ Already reconnecting, skipping...');
        return;
    }

    isReconnecting = true;
    console.log('🔄 Initializing WhatsApp connection via Baileys...');
    broadcastStatus('loading', 'Menyiapkan modul WhatsApp...');

    try {
        const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
        const { version, isLatest } = await fetchLatestBaileysVersion();
        console.log(`ℹ️ Baileys version: ${version.join('.')}, isLatest: ${isLatest}`);

        sock = makeWASocket({
            version,
            logger: pino({ level: 'silent' }),
            printQRInTerminal: false,
            auth: {
                creds: state.creds,
                keys: makeCacheableSignalKeyStore(state.keys, pino({ level: 'silent' }))
            },
            browser: ['Konveksio Bot', 'Chrome', '1.0.0'],
            generateHighQualityLinkPreview: true,
            connectTimeoutMs: 60000,
            defaultQueryTimeoutMs: 60000,
            keepAliveIntervalMs: 25000,
            // getMessage handler — CRITICAL for retry/re-encrypt
            getMessage: async (key) => {
                const msg = messageStore.get(key.id);
                if (msg) {
                    console.log(`📦 getMessage: Found stored message ${key.id}`);
                    return msg;
                }
                console.log(`⚠️ getMessage: Message ${key.id} not found in store`);
                return { conversation: '' };
            }
        });

        // Bind credentials save event
        sock.ev.on('creds.update', saveCreds);

        // Connection Update Handler
        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                console.log('📱 QR Code received from Baileys!');
                rawQR = qr;
                try {
                    // Terminal display
                    qrcodeTerminal.generate(qr, { small: true });
                    // Data URL generation
                    lastQRUrl = await qrcode.toDataURL(qr);
                    broadcastStatus('qr', 'Silakan scan QR code');
                } catch (err) {
                    console.error('❌ Failed to process QR Code:', err);
                }
            }

            if (connection === 'open') {
                console.log('✅✅✅ WHATSAPP IS CONNECTED & READY! ✅✅✅');
                rawQR = '';
                lastQRUrl = '';
                isReconnecting = false;
                consecutiveFailures = 0;

                // Extract connected phone number from credentials
                try {
                    const credsPath = path.join(AUTH_DIR, 'creds.json');
                    if (await fs.pathExists(credsPath)) {
                        const creds = await fs.readJSON(credsPath);
                        if (creds.me && creds.me.id) {
                            connectedPhone = creds.me.id.split(':')[0].split('@')[0];
                            console.log(`📱 Connected as: ${connectedPhone}`);
                        }
                    }
                } catch (e) {
                    console.log('⚠️ Could not read connected phone:', e.message);
                }

                broadcastStatus('ready', 'WhatsApp terhubung dan siap digunakan!');
            }

            if (connection === 'close') {
                isReconnecting = false;
                const statusCode = lastDisconnect?.error?.output?.statusCode;
                const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
                console.log(`🔌 Connection closed. Reason: ${lastDisconnect?.error?.message || statusCode}, Should Reconnect: ${shouldReconnect}`);

                if (statusCode === DisconnectReason.loggedOut) {
                    console.log('🔒 Client logged out. Clearing auth directory...');
                    connectedPhone = null;
                    broadcastStatus('not ready', 'WhatsApp logged out. Please scan QR again.');
                    await cleanAuthDir();
                    setTimeout(connectToWhatsApp, 3000);
                } else if (shouldReconnect) {
                    broadcastStatus('not ready', 'Koneksi terputus. Menghubungkan ulang...');
                    setTimeout(connectToWhatsApp, 5000);
                } else {
                    console.log('⚠️ Connection ended. Restarting connection...');
                    setTimeout(connectToWhatsApp, 5000);
                }
            }
        });

        // ============================================================
        // MESSAGE DELIVERY TRACKING (ack monitoring)
        // ============================================================
        sock.ev.on('messages.update', (updates) => {
            for (const update of updates) {
                const msgId = update.key?.id;
                if (!msgId) continue;

                const ack = update.update?.status;
                // ack values: 1=pending, 2=server, 3=delivered, 4=read
                if (ack !== undefined) {
                    const pending = pendingDelivery.get(msgId);
                    if (pending) {
                        pending.ack = ack;
                        const ackLabel = { 1: 'PENDING', 2: 'SERVER_ACK', 3: 'DELIVERED', 4: 'READ' }[ack] || ack;
                        console.log(`📨 Message ${msgId} ack: ${ackLabel}`);

                        if (ack >= 3) {
                            // Message confirmed delivered
                            pending.status = 'delivered';
                            totalDeliveredToday++;
                            lastDeliveryConfirmedAt = new Date();
                            consecutiveFailures = 0; // Reset failure counter
                            pendingDelivery.delete(msgId);

                            // Update log
                            const logEntry = messageLog.find(l => l.messageId === msgId);
                            if (logEntry) {
                                logEntry.deliveryStatus = 'delivered';
                                logEntry.ack = ack;
                            }
                        }
                    }
                }
            }
        });

    } catch (err) {
        console.error('❌ Failed to initialize Baileys connection:', err);
        isReconnecting = false;
        broadcastStatus('not ready', 'Gagal inisialisasi modul WhatsApp');
        setTimeout(connectToWhatsApp, 10000);
    }
};

// Force reconnect function (used by auto-reconnect and manual trigger)
const forceReconnect = async (reason = 'manual') => {
    console.log(`🔄 Force reconnecting... Reason: ${reason}`);
    
    if (sock) {
        try {
            sock.end(undefined);
        } catch (e) {
            console.log('⚠️ Error closing socket:', e.message);
        }
        sock = null;
    }

    clientStatus = 'not ready';
    isReconnecting = false;
    consecutiveFailures = 0;
    
    // Small delay before reconnecting
    await new Promise(resolve => setTimeout(resolve, 2000));
    await connectToWhatsApp();
};

// Check for stale pending messages and trigger reconnect if needed
const checkPendingDeliveries = () => {
    const now = Date.now();
    for (const [msgId, pending] of pendingDelivery.entries()) {
        if (now - pending.timestamp > DELIVERY_TIMEOUT_MS && pending.status === 'sent') {
            pending.status = 'unconfirmed';
            consecutiveFailures++;
            console.log(`⚠️ Message ${msgId} delivery unconfirmed after ${DELIVERY_TIMEOUT_MS / 1000}s (failures: ${consecutiveFailures}/${MAX_CONSECUTIVE_FAILURES})`);

            // Update log
            const logEntry = messageLog.find(l => l.messageId === msgId);
            if (logEntry) {
                logEntry.deliveryStatus = 'unconfirmed';
            }

            pendingDelivery.delete(msgId);

            // Auto-reconnect if too many consecutive failures (ghost session)
            if (consecutiveFailures >= MAX_CONSECUTIVE_FAILURES && !isReconnecting) {
                console.log('🚨 Ghost session detected! Too many unconfirmed deliveries. Force reconnecting...');
                totalFailedToday += consecutiveFailures;
                addToLog({
                    type: 'system',
                    message: `Ghost session detected (${consecutiveFailures} unconfirmed). Auto-reconnecting...`,
                    messageId: null,
                    to: null,
                    deliveryStatus: 'reconnecting',
                });
                forceReconnect('ghost_session_detected');
                break;
            }
        }
    }
};
setInterval(checkPendingDeliveries, 5000);


// ============================================================
// EXPRESS MIDDLEWARE & ROUTES
// ============================================================
// Handle CORS for all API routes
app.use((req, res, next) => {
    res.header('Access-Control-Allow-Origin', '*');
    res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    res.header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, x-bot-key, Authorization');
    if (req.method === 'OPTIONS') {
        return res.sendStatus(200);
    }
    next();
});

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Handle cPanel Passenger base path prefix (e.g. /bot or /bot/)
app.use((req, res, next) => {
    if (req.url.startsWith('/bot')) {
        req.url = req.url.replace(/^\/bot/, '') || '/';
    }
    next();
});

// Middleware Proteksi API Key (Anti-Spam / Open Proxy)
const verifyApiKey = (req, res, next) => {
    const secretKey = process.env.BOT_SECRET_KEY;
    if (!secretKey) {
        return next(); // Jika tidak di-set di env, izinkan request (lokal dev)
    }

    const clientKey = req.headers['x-bot-key'] || req.query.bot_key || req.body.bot_key;
    if (!clientKey || clientKey !== secretKey) {
        console.warn('⚠️ Unauthorized API access attempt from:', req.ip);
        return res.status(401).json({
            status: 'error',
            pesan: 'Unauthorized: Header x-bot-key atau BOT_SECRET_KEY tidak valid.'
        });
    }
    next();
};

// ============================================================
// MAIN SEND API HANDLER
// ============================================================
const apiSendHandler = async (req, res) => {
    console.log('📩 API Request received:', req.method, req.query, req.body);

    let nohp = req.query.nohp || req.query.number || req.body.nohp || req.body.number;
    const pesan = req.query.pesan || req.query.message || req.body.pesan || req.body.message;

    if (!nohp) {
        return res.status(400).json({ status: "error", pesan: "Parameter 'nohp' atau 'number' wajib diisi" });
    }
    if (!pesan) {
        return res.status(400).json({ status: "error", pesan: "Parameter 'pesan' atau 'message' wajib diisi" });
    }

    const jid = formatToJid(nohp);
    const messageText = String(pesan).trim();

    console.log('📱 Formatted JID for Baileys:', jid);

    if (!sock || clientStatus !== 'ready') {
        console.error('❌ WhatsApp client not ready. Current Status:', clientStatus);
        return res.status(503).json({
            status: "error",
            pesan: "WhatsApp client belum siap. Buka dashboard bot (/bot) untuk scan QR.",
            clientStatus
        });
    }

    try {
        console.log(`📤 Sending WhatsApp message via Baileys to ${jid}...`);
        
        // Timeout 12s safety race
        const messageContent = { text: messageText };
        const sendPromise = sock.sendMessage(jid, messageContent);
        const timeoutPromise = new Promise((_, reject) => 
            setTimeout(() => reject(new Error('WhatsApp sendMessage timeout (12s)')), 12000)
        );

        const result = await Promise.race([sendPromise, timeoutPromise]);
        const msgId = result?.key?.id || null;
        console.log('✅ Message sent successfully!', msgId);

        // Store message for getMessage handler (retry capability)
        if (msgId) {
            storeMessage(msgId, messageContent);
            
            // Track delivery
            pendingDelivery.set(msgId, {
                jid,
                timestamp: Date.now(),
                status: 'sent',
                ack: 1,
            });
        }

        totalSentToday++;
        lastMessageSentAt = new Date();

        // Add to log
        const logEntry = {
            type: 'outgoing',
            messageId: msgId,
            to: jid.replace(/@s\.whatsapp\.net$/, ''),
            preview: messageText.substring(0, 80) + (messageText.length > 80 ? '...' : ''),
            deliveryStatus: 'sent',
            ack: 1,
        };
        addToLog(logEntry);

        return res.json({ 
            status: "berhasil terkirim", 
            pesan: messageText, 
            to: jid,
            id: msgId,
            delivery: 'pending_confirmation',
            note: 'Pesan dikirim ke server WhatsApp. Delivery confirmation akan di-track otomatis.'
        });
    } catch (error) {
        console.error('❌ Send Message Error:', error.message);
        totalFailedToday++;
        consecutiveFailures++;

        addToLog({
            type: 'outgoing',
            messageId: null,
            to: jid.replace(/@s\.whatsapp\.net$/, ''),
            preview: messageText.substring(0, 80),
            deliveryStatus: 'error',
            error: error.message,
        });

        // Auto-reconnect if error indicates stale connection
        if (error.message.includes('timeout') || error.message.includes('not open')) {
            if (!isReconnecting) {
                console.log('🚨 Send error indicates stale connection. Scheduling reconnect...');
                setTimeout(() => forceReconnect('send_error'), 1000);
            }
        }

        return res.status(500).json({ 
            status: "error", 
            pesan: "Gagal kirim: " + error.message 
        });
    }
};

// API Send Routes
app.post('/api', verifyApiKey, apiSendHandler);
app.get('/api', verifyApiKey, apiSendHandler);
app.post('/send-message', verifyApiKey, apiSendHandler);
app.get('/send-message', verifyApiKey, apiSendHandler);

// ============================================================
// STATUS / HEALTH / MANAGEMENT ENDPOINTS
// ============================================================

// Enhanced health check endpoint
app.get('/health', (req, res) => {
    const uptime = Math.floor((Date.now() - botStartedAt.getTime()) / 1000);
    res.json({
        status: 'ok',
        engine: 'Baileys',
        whatsapp: {
            status: clientStatus,
            hasSocket: !!sock,
            connectedPhone: connectedPhone,
            timestamp: new Date().toISOString()
        },
        stats: {
            uptime_seconds: uptime,
            uptime_human: formatUptime(uptime),
            sent_today: totalSentToday,
            delivered_today: totalDeliveredToday,
            failed_today: totalFailedToday,
            consecutive_failures: consecutiveFailures,
            last_message_sent: lastMessageSentAt?.toISOString() || null,
            last_delivery_confirmed: lastDeliveryConfirmedAt?.toISOString() || null,
            pending_deliveries: pendingDelivery.size,
        }
    });
});

// Detailed status endpoint (for admin dashboard)
app.get('/api/status', verifyApiKey, (req, res) => {
    const uptime = Math.floor((Date.now() - botStartedAt.getTime()) / 1000);
    res.json({
        status: clientStatus,
        connectedPhone: connectedPhone,
        uptime_seconds: uptime,
        uptime_human: formatUptime(uptime),
        bot_started_at: botStartedAt.toISOString(),
        stats: {
            sent_today: totalSentToday,
            delivered_today: totalDeliveredToday,
            failed_today: totalFailedToday,
            unconfirmed_today: totalSentToday - totalDeliveredToday - totalFailedToday,
            consecutive_failures: consecutiveFailures,
            ghost_threshold: MAX_CONSECUTIVE_FAILURES,
        },
        last_activity: {
            last_message_sent: lastMessageSentAt?.toISOString() || null,
            last_delivery_confirmed: lastDeliveryConfirmedAt?.toISOString() || null,
        },
        connection: {
            has_socket: !!sock,
            is_reconnecting: isReconnecting,
            has_qr: !!rawQR,
            qr_url: lastQRUrl || null,
            auth_dir_exists: fs.pathExistsSync(AUTH_DIR),
        }
    });
});

// Force reconnect endpoint
app.post('/api/reconnect', verifyApiKey, async (req, res) => {
    console.log('🔄 Manual reconnect triggered from admin dashboard');
    
    if (isReconnecting) {
        return res.json({ status: 'already_reconnecting', message: 'Sedang dalam proses reconnect...' });
    }

    res.json({ status: 'reconnecting', message: 'Reconnect dimulai. Tunggu beberapa detik...' });
    
    // Do reconnect after sending response
    setTimeout(() => forceReconnect('admin_dashboard'), 500);
});

// Logout endpoint (clear auth, generate new QR)
app.post('/api/logout', verifyApiKey, async (req, res) => {
    console.log('🚪 Logout triggered from admin dashboard');
    
    try {
        // Close socket
        if (sock) {
            try {
                await sock.logout();
            } catch (e) {
                console.log('⚠️ Logout call failed (may already be disconnected):', e.message);
                // Try to just end the socket
                try { sock.end(undefined); } catch(e2) {}
            }
            sock = null;
        }

        connectedPhone = null;
        clientStatus = 'not ready';
        
        // Clean auth directory
        await cleanAuthDir();
        
        res.json({ status: 'logged_out', message: 'WhatsApp telah di-logout. Silakan scan QR baru.' });
        
        // Restart connection for new QR
        setTimeout(connectToWhatsApp, 2000);
    } catch (error) {
        console.error('❌ Logout error:', error);
        res.status(500).json({ status: 'error', message: 'Gagal logout: ' + error.message });
    }
});

// Message logs endpoint
app.get('/api/logs', verifyApiKey, (req, res) => {
    const limit = Math.min(parseInt(req.query.limit) || 20, MESSAGE_LOG_MAX);
    res.json({
        total: messageLog.length,
        logs: messageLog.slice(0, limit).map(log => ({
            ...log,
            // Redact phone number for privacy (show last 4 digits)
            to: log.to ? '****' + log.to.slice(-4) : null,
        }))
    });
});

// ============================================================
// QR CODE & DASHBOARD ROUTES
// ============================================================

// GET /qr endpoint - Render QR Code directly in browser
app.get('/qr', async (req, res) => {
    // If format=json requested, return raw data
    if (req.query.format === 'json') {
        return res.json({
            status: clientStatus,
            qr_data_url: clientStatus === 'qr' ? lastQRUrl : null,
        });
    }

    if (clientStatus === 'ready') {
        return res.send(`
            <div style="font-family:sans-serif; text-align:center; padding:50px;">
                <h2 style="color:#16a34a;">✅ WhatsApp Client Sudah Terhubung!</h2>
                <p>Status: Ready. Tidak perlu scan QR lagi.</p>
                <a href="https://sim.duniabordirkomputer.com" style="display:inline-block; margin-top:15px; padding:10px 20px; background:#6366f1; color:white; border-radius:8px; text-decoration:none; font-weight:600;">Kembali ke SIM Konveksio</a>
            </div>
        `);
    }

    if (!lastQRUrl && !rawQR) {
        return res.send(`
            <div style="font-family:sans-serif; text-align:center; padding:50px;">
                <h3 style="color:#ca8a04;">⏳ Memuat QR Code...</h3>
                <p>Silakan refresh halaman ini dalam beberapa detik.</p>
                <script>setTimeout(() => location.reload(), 3000);</script>
            </div>
        `);
    }

    try {
        const qrBuffer = await qrcode.toBuffer(rawQR || lastQRUrl);
        res.type('png');
        return res.send(qrBuffer);
    } catch (err) {
        return res.status(500).send('Gagal merender QR Code: ' + err.message);
    }
});

// Serve index.html Dashboard
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'index.html'));
});

// ============================================================
// SOCKET.IO CONNECTION HANDLER
// ============================================================
io.on('connection', (socket) => {
    console.log('🔗 Socket client connected:', socket.id);

    if (clientStatus === 'ready') {
        socket.emit('ready', 'Client is ready!');
        socket.emit('status', { status: 'ready', message: 'WhatsApp terhubung!' });
    } else if (clientStatus === 'qr') {
        socket.emit('qr', lastQRUrl);
        socket.emit('status', { status: 'qr', message: 'Silakan scan QR code' });
    } else {
        socket.emit('disconnected', 'Client was logged out');
        socket.emit('status', { status: 'not ready', message: 'Client not ready' });
    }

    socket.on('checkStatus', () => {
        console.log('📋 checkStatus requested by:', socket.id);
        if (clientStatus === 'ready') {
            socket.emit('ready', 'Client is ready!');
            socket.emit('status', { status: 'ready', message: 'WhatsApp terhubung!' });
        } else if (clientStatus === 'qr') {
            socket.emit('qr', lastQRUrl);
            socket.emit('status', { status: 'qr', message: 'Silakan scan QR code' });
        } else {
            socket.emit('disconnected', 'Client was logged out');
            socket.emit('status', { status: 'not ready', message: 'Client not ready' });
        }
    });

    socket.on('disconnect', () => {
        console.log('🔌 Socket disconnected:', socket.id);
    });
});

// ============================================================
// UTILITY FUNCTIONS
// ============================================================
function formatUptime(seconds) {
    const d = Math.floor(seconds / 86400);
    const h = Math.floor((seconds % 86400) / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    const parts = [];
    if (d > 0) parts.push(`${d}h`);
    if (h > 0) parts.push(`${h}j`);
    if (m > 0) parts.push(`${m}m`);
    parts.push(`${s}d`);
    return parts.join(' ');
}

// ============================================================
// GLOBAL ERROR HANDLING
// ============================================================
process.on('unhandledRejection', (reason, promise) => {
    console.error('❌ Unhandled Rejection at:', promise, 'reason:', reason);
});

process.on('uncaughtException', (err) => {
    console.error('❌ Uncaught Exception:', err);
});

// ============================================================
// START
// ============================================================
connectToWhatsApp();

const PORT = process.env.PORT || 5001;
server.listen(PORT, () => {
    console.log(`🚀 Server is running on port ${PORT}`);
    console.log(`🌐 Dashboard: http://localhost:${PORT}`);
    console.log(`📷 Direct QR: http://localhost:${PORT}/qr`);
    console.log(`🔍 Health check: http://localhost:${PORT}/health`);
    console.log(`📊 Status API: http://localhost:${PORT}/api/status`);
});