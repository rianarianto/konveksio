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
// SINGLE INSTANCE ENFORCER (Mencegah Zombie / Duplikasi Proses)
// ============================================================
const PID_FILE = path.join(__dirname, 'bot.pid');
const currentPid = process.pid;

function enforceSingleInstance() {
    try {
        if (fs.existsSync(PID_FILE)) {
            const oldPid = parseInt(fs.readFileSync(PID_FILE, 'utf8').trim(), 10);
            if (oldPid && oldPid !== currentPid) {
                try {
                    process.kill(oldPid, 0);
                    console.log(`⚠️ Terdeteksi proses bot lama (PID: ${oldPid}). Mematikan proses zombie...`);
                    process.kill(oldPid, 'SIGTERM');
                    setTimeout(() => {
                        try { process.kill(oldPid, 'SIGKILL'); } catch (e) {}
                    }, 1000);
                } catch (e) {
                    // Proses lama sudah mati
                }
            }
        }
        fs.writeFileSync(PID_FILE, String(currentPid), 'utf8');
        console.log(`🔒 Bot Single-Instance Lock aktif untuk PID: ${currentPid}`);
    } catch (err) {
        console.error('⚠️ Gagal memproses PID file:', err.message);
    }
}

function cleanupPidFile() {
    try {
        if (fs.existsSync(PID_FILE)) {
            const savedPid = parseInt(fs.readFileSync(PID_FILE, 'utf8').trim(), 10);
            if (savedPid === currentPid) {
                fs.unlinkSync(PID_FILE);
            }
        }
    } catch (e) {}
}

enforceSingleInstance();

process.on('exit', cleanupPidFile);
process.on('SIGINT', () => { cleanupPidFile(); process.exit(0); });
process.on('SIGTERM', () => { cleanupPidFile(); process.exit(0); });

// ============================================================
// CONSTANTS & PATHS
// ============================================================
const AUTH_BASE_DIR = path.join(__dirname, 'auth_info_baileys');
const MESSAGE_STORE_MAX = 500;
const MESSAGE_LOG_MAX = 50;
const MAX_CONSECUTIVE_FAILURES = 3;
const DELIVERY_TIMEOUT_MS = 15000;
const DEFAULT_SHOP_ID = '1';

// Auto-migrate old single-session format if exists
function autoMigrateLegacySession() {
    try {
        const legacyCredsPath = path.join(AUTH_BASE_DIR, 'creds.json');
        const shop1Dir = path.join(AUTH_BASE_DIR, 'shop_1');

        if (fs.existsSync(legacyCredsPath) && !fs.existsSync(shop1Dir)) {
            console.log('🔄 Migrating legacy single-session to shop_1...');
            fs.ensureDirSync(shop1Dir);
            const files = fs.readdirSync(AUTH_BASE_DIR);
            for (const file of files) {
                if (file.startsWith('shop_')) continue;
                const src = path.join(AUTH_BASE_DIR, file);
                const dest = path.join(shop1Dir, file);
                fs.moveSync(src, dest, { overwrite: true });
            }
            console.log('✅ Legacy session successfully migrated to shop_1!');
        }
    } catch (err) {
        console.error('⚠️ Migration note:', err.message);
    }
}
autoMigrateLegacySession();

// ============================================================
// MULTI-SESSION STATE MANAGEMENT
// ============================================================
const sessions = new Map();

function normalizeShopId(rawId) {
    if (!rawId) return DEFAULT_SHOP_ID;
    const clean = String(rawId).trim().replace(/^shop_/, '');
    return clean || DEFAULT_SHOP_ID;
}

function createSessionState(shopId) {
    const authDir = path.join(AUTH_BASE_DIR, `shop_${shopId}`);
    fs.ensureDirSync(authDir);

    return {
        shopId: String(shopId),
        clientStatus: 'not ready',
        lastQRUrl: '',
        rawQR: '',
        sock: null,
        connectedPhone: null,
        botStartedAt: new Date(),
        messageStore: new Map(),
        totalSentToday: 0,
        totalDeliveredToday: 0,
        totalFailedToday: 0,
        consecutiveFailures: 0,
        lastMessageSentAt: null,
        lastDeliveryConfirmedAt: null,
        isReconnecting: false,
        messageLog: [],
        pendingDelivery: new Map(),
        authDir: authDir,
    };
}

function getOrCreateSession(shopId) {
    const id = normalizeShopId(shopId);
    if (!sessions.has(id)) {
        console.log(`✨ Creating session state for Shop ID: ${id}`);
        const session = createSessionState(id);
        sessions.set(id, session);
        connectToWhatsApp(session);
    }
    return sessions.get(id);
}

// Reset daily counters for all sessions
setInterval(() => {
    const now = new Date();
    if (now.getHours() === 0 && now.getMinutes() === 0) {
        for (const session of sessions.values()) {
            session.totalSentToday = 0;
            session.totalDeliveredToday = 0;
            session.totalFailedToday = 0;
        }
    }
}, 60000);

// ============================================================
// HELPER FUNCTIONS
// ============================================================

const broadcastStatus = (session, status, message) => {
    session.clientStatus = status;
    console.log(`📢 [Shop ${session.shopId}] Broadcasting status: ${status} - ${message}`);
    
    // Global emit with shop_id
    io.emit('status', { shop_id: session.shopId, status, message });

    if (status === 'ready') {
        io.emit('ready', { shop_id: session.shopId, message });
    } else if (status === 'qr') {
        io.emit('qr', { shop_id: session.shopId, qr: session.lastQRUrl });
    } else {
        io.emit('disconnected', { shop_id: session.shopId, message });
    }
};

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

const cleanAuthDir = async (session) => {
    try {
        if (await fs.pathExists(session.authDir)) {
            await fs.remove(session.authDir);
            await fs.ensureDir(session.authDir);
            console.log(`🗑️ [Shop ${session.shopId}] Auth directory cleaned up successfully.`);
        }
    } catch (err) {
        console.error(`❌ [Shop ${session.shopId}] Failed to clean auth directory:`, err);
    }
};

const addToLog = (session, entry) => {
    session.messageLog.unshift({
        ...entry,
        timestamp: new Date().toISOString(),
    });
    if (session.messageLog.length > MESSAGE_LOG_MAX) {
        session.messageLog.pop();
    }
};

const storeMessage = (session, msgId, message) => {
    session.messageStore.set(msgId, message);
    if (session.messageStore.size > MESSAGE_STORE_MAX) {
        const oldest = session.messageStore.keys().next().value;
        session.messageStore.delete(oldest);
    }
};

// ============================================================
// WHATSAPP CONNECTION (Baileys Per Session)
// ============================================================
const connectToWhatsApp = async (session) => {
    if (session.isReconnecting) {
        console.log(`⏳ [Shop ${session.shopId}] Already reconnecting, skipping...`);
        return;
    }

    session.isReconnecting = true;
    console.log(`🔄 [Shop ${session.shopId}] Initializing WhatsApp connection via Baileys...`);
    broadcastStatus(session, 'loading', 'Menyiapkan modul WhatsApp...');

    try {
        const { state, saveCreds } = await useMultiFileAuthState(session.authDir);
        const { version, isLatest } = await fetchLatestBaileysVersion();
        console.log(`ℹ️ [Shop ${session.shopId}] Baileys version: ${version.join('.')}, isLatest: ${isLatest}`);

        session.sock = makeWASocket({
            version,
            logger: pino({ level: 'silent' }),
            printQRInTerminal: false,
            auth: {
                creds: state.creds,
                keys: makeCacheableSignalKeyStore(state.keys, pino({ level: 'silent' }))
            },
            browser: [`Konveksio Shop ${session.shopId}`, 'Chrome', '1.0.0'],
            generateHighQualityLinkPreview: true,
            connectTimeoutMs: 60000,
            defaultQueryTimeoutMs: 60000,
            keepAliveIntervalMs: 25000,
            getMessage: async (key) => {
                const msg = session.messageStore.get(key.id);
                if (msg) {
                    return msg;
                }
                return { conversation: '' };
            }
        });

        session.sock.ev.on('creds.update', saveCreds);

        session.sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                console.log(`📱 [Shop ${session.shopId}] QR Code received from Baileys!`);
                session.rawQR = qr;
                try {
                    qrcodeTerminal.generate(qr, { small: true });
                    session.lastQRUrl = await qrcode.toDataURL(qr);
                    broadcastStatus(session, 'qr', 'Silakan scan QR code');
                } catch (err) {
                    console.error(`❌ [Shop ${session.shopId}] Failed to process QR Code:`, err);
                }
            }

            if (connection === 'open') {
                console.log(`✅✅✅ [Shop ${session.shopId}] WHATSAPP IS CONNECTED & READY! ✅✅✅`);
                session.rawQR = '';
                session.lastQRUrl = '';
                session.isReconnecting = false;
                session.consecutiveFailures = 0;

                try {
                    const credsPath = path.join(session.authDir, 'creds.json');
                    if (await fs.pathExists(credsPath)) {
                        const creds = await fs.readJSON(credsPath);
                        if (creds.me && creds.me.id) {
                            session.connectedPhone = creds.me.id.split(':')[0].split('@')[0];
                            console.log(`📱 [Shop ${session.shopId}] Connected as: ${session.connectedPhone}`);
                        }
                    }
                } catch (e) {
                    console.log(`⚠️ [Shop ${session.shopId}] Could not read connected phone:`, e.message);
                }

                broadcastStatus(session, 'ready', 'WhatsApp terhubung dan siap digunakan!');
            }

            if (connection === 'close') {
                session.isReconnecting = false;
                const statusCode = lastDisconnect?.error?.output?.statusCode;
                const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
                console.log(`🔌 [Shop ${session.shopId}] Connection closed. Reason: ${lastDisconnect?.error?.message || statusCode}, Should Reconnect: ${shouldReconnect}`);

                if (statusCode === DisconnectReason.loggedOut) {
                    console.log(`🔒 [Shop ${session.shopId}] Client logged out. Clearing auth directory...`);
                    session.connectedPhone = null;
                    broadcastStatus(session, 'not ready', 'WhatsApp logged out. Please scan QR again.');
                    await cleanAuthDir(session);
                    setTimeout(() => connectToWhatsApp(session), 3000);
                } else if (shouldReconnect) {
                    broadcastStatus(session, 'not ready', 'Koneksi terputus. Menghubungkan ulang...');
                    setTimeout(() => connectToWhatsApp(session), 5000);
                } else {
                    console.log(`⚠️ [Shop ${session.shopId}] Connection ended. Restarting connection...`);
                    setTimeout(() => connectToWhatsApp(session), 5000);
                }
            }
        });

        // Delivery tracking
        session.sock.ev.on('messages.update', (updates) => {
            for (const update of updates) {
                const msgId = update.key?.id;
                if (!msgId) continue;

                const ack = update.update?.status;
                if (ack !== undefined) {
                    const pending = session.pendingDelivery.get(msgId);
                    if (pending) {
                        pending.ack = ack;
                        if (ack >= 3) {
                            pending.status = 'delivered';
                            session.totalDeliveredToday++;
                            session.lastDeliveryConfirmedAt = new Date();
                            session.consecutiveFailures = 0;
                            session.pendingDelivery.delete(msgId);

                            const logEntry = session.messageLog.find(l => l.messageId === msgId);
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
        console.error(`❌ [Shop ${session.shopId}] Failed to initialize Baileys:`, err);
        session.isReconnecting = false;
        broadcastStatus(session, 'not ready', 'Gagal inisialisasi modul WhatsApp');
        setTimeout(() => connectToWhatsApp(session), 10000);
    }
};

const forceReconnect = async (session, reason = 'manual') => {
    console.log(`🔄 [Shop ${session.shopId}] Force reconnecting... Reason: ${reason}`);
    
    if (session.isReconnecting) {
        return;
    }

    session.isReconnecting = true;
    session.clientStatus = 'loading';
    broadcastStatus(session, 'loading', 'Menyambung ulang session WhatsApp...');

    if (session.sock) {
        try {
            session.sock.ev.removeAllListeners();
            session.sock.end(undefined);
        } catch (e) {
            console.log(`⚠️ [Shop ${session.shopId}] Error closing socket:`, e.message);
        }
        session.sock = null;
    }

    session.consecutiveFailures = 0;
    await new Promise(resolve => setTimeout(resolve, 3000));
    session.isReconnecting = false;
    await connectToWhatsApp(session);
};

// Check for stale pending deliveries
setInterval(() => {
    const now = Date.now();
    for (const session of sessions.values()) {
        for (const [msgId, pending] of session.pendingDelivery.entries()) {
            if (now - pending.timestamp > DELIVERY_TIMEOUT_MS && pending.status === 'sent') {
                pending.status = 'unconfirmed';
                session.consecutiveFailures++;

                const logEntry = session.messageLog.find(l => l.messageId === msgId);
                if (logEntry) {
                    logEntry.deliveryStatus = 'unconfirmed';
                }

                session.pendingDelivery.delete(msgId);

                if (session.consecutiveFailures >= MAX_CONSECUTIVE_FAILURES && !session.isReconnecting) {
                    console.log(`🚨 [Shop ${session.shopId}] Ghost session detected! Auto-reconnecting...`);
                    session.totalFailedToday += session.consecutiveFailures;
                    addToLog(session, {
                        type: 'system',
                        message: `Ghost session detected (${session.consecutiveFailures} unconfirmed). Auto-reconnecting...`,
                        messageId: null,
                        to: null,
                        deliveryStatus: 'reconnecting',
                    });
                    forceReconnect(session, 'ghost_session_detected');
                    break;
                }
            }
        }
    }
}, 5000);

// ============================================================
// DISCOVER EXISTING SESSIONS ON STARTUP
// ============================================================
function initStartupSessions() {
    fs.ensureDirSync(AUTH_BASE_DIR);
    const entries = fs.readdirSync(AUTH_BASE_DIR, { withFileTypes: true });
    const shopDirs = entries.filter(e => e.isDirectory() && e.name.startsWith('shop_')).map(e => e.name.replace(/^shop_/, ''));

    if (shopDirs.length === 0) {
        shopDirs.push(DEFAULT_SHOP_ID);
    }

    for (const shopId of shopDirs) {
        getOrCreateSession(shopId);
    }
}

// ============================================================
// MIDDLEWARE
// ============================================================
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Handle cPanel Passenger base path prefix (e.g. /bot or /bot/)
app.use((req, res, next) => {
    if (req.url.startsWith('/bot')) {
        req.url = req.url.replace(/^\/bot/, '') || '/';
    }
    next();
});

// CORS
app.use((req, res, next) => {
    res.header("Access-Control-Allow-Origin", "*");
    res.header("Access-Control-Allow-Headers", "Origin, X-Requested-With, Content-Type, Accept, x-bot-key, x-shop-id");
    res.header("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
    if (req.method === 'OPTIONS') {
        return res.sendStatus(200);
    }
    next();
});

// Helper to extract shop_id from request
function extractShopId(req) {
    return req.query.shop_id || req.body.shop_id || req.headers['x-shop-id'] || DEFAULT_SHOP_ID;
}

// API Key Verification Middleware
const verifyApiKey = (req, res, next) => {
    const apiKey = req.headers['x-bot-key'] || req.query.bot_key || req.body.bot_key;
    const expectedKey = process.env.BOT_SECRET_KEY || 'DuniaBordirSecretKey998877!@#';

    if (expectedKey && apiKey && apiKey !== expectedKey) {
        return res.status(401).json({ status: 'error', pesan: 'Invalid API Key' });
    }
    next();
};

// ============================================================
// API ENDPOINTS
// ============================================================

// Send Message Handler
const apiSendHandler = async (req, res) => {
    const shopId = extractShopId(req);
    const session = getOrCreateSession(shopId);

    const nohp = req.body.nohp || req.query.nohp;
    const pesan = req.body.pesan || req.query.pesan;

    if (!nohp || !pesan) {
        return res.status(400).json({ 
            status: "error", 
            pesan: "Parameter 'nohp' dan 'pesan' wajib diisi!" 
        });
    }

    if (!session.sock || session.clientStatus !== 'ready') {
        return res.status(503).json({ 
            status: "error", 
            pesan: `WhatsApp Toko ${session.shopId} belum terhubung. Silakan scan QR code di menu Pengaturan WhatsApp.` 
        });
    }

    const jid = formatToJid(nohp);
    const messageText = String(pesan).trim();
    const messageContent = { text: messageText };

    try {
        console.log(`📤 [Shop ${session.shopId}] Sending message to ${jid}: "${messageText.substring(0, 40)}..."`);
        const sentResult = await session.sock.sendMessage(jid, messageContent);
        const msgId = sentResult?.key?.id;
        console.log(`✅ [Shop ${session.shopId}] Message sent successfully! ID:`, msgId);

        if (msgId) {
            storeMessage(session, msgId, messageContent);
            session.pendingDelivery.set(msgId, {
                jid,
                timestamp: Date.now(),
                status: 'sent',
                ack: 1,
            });
        }

        session.totalSentToday++;
        session.lastMessageSentAt = new Date();

        addToLog(session, {
            type: 'outgoing',
            messageId: msgId,
            to: jid.replace(/@s\.whatsapp\.net$/, ''),
            preview: messageText.substring(0, 80) + (messageText.length > 80 ? '...' : ''),
            deliveryStatus: 'sent',
            ack: 1,
        });

        return res.json({ 
            status: "berhasil terkirim", 
            pesan: messageText, 
            to: jid,
            id: msgId,
            shop_id: session.shopId,
            delivery: 'sent',
            note: 'Pesan berhasil diserahkan ke jaringan WhatsApp.'
        });
    } catch (error) {
        console.error(`❌ [Shop ${session.shopId}] Send Message Error:`, error.message);
        session.totalFailedToday++;

        addToLog(session, {
            type: 'outgoing',
            messageId: null,
            to: jid.replace(/@s\.whatsapp\.net$/, ''),
            preview: messageText.substring(0, 80),
            deliveryStatus: 'error',
            error: error.message,
        });

        return res.status(500).json({ 
            status: "error", 
            pesan: "Gagal kirim: " + error.message 
        });
    }
};

app.post('/api', verifyApiKey, apiSendHandler);
app.get('/api', verifyApiKey, apiSendHandler);
app.post('/send-message', verifyApiKey, apiSendHandler);
app.get('/send-message', verifyApiKey, apiSendHandler);

// Health Endpoint
app.get('/health', (req, res) => {
    const shopList = {};
    for (const [id, s] of sessions.entries()) {
        const uptime = Math.floor((Date.now() - s.botStartedAt.getTime()) / 1000);
        shopList[id] = {
            status: s.clientStatus,
            hasSocket: !!s.sock,
            connectedPhone: s.connectedPhone,
            uptime_seconds: uptime,
            uptime_human: formatUptime(uptime),
            sent_today: s.totalSentToday,
            delivered_today: s.totalDeliveredToday,
            failed_today: s.totalFailedToday,
        };
    }

    res.json({
        status: 'ok',
        engine: 'Baileys Multi-Session',
        total_sessions: sessions.size,
        shops: shopList,
        timestamp: new Date().toISOString()
    });
});

// Detailed Status Endpoint per Shop
app.get('/api/status', verifyApiKey, (req, res) => {
    const shopId = extractShopId(req);
    const session = getOrCreateSession(shopId);

    const uptime = Math.floor((Date.now() - session.botStartedAt.getTime()) / 1000);
    res.json({
        status: session.clientStatus,
        shop_id: session.shopId,
        connectedPhone: session.connectedPhone,
        uptime_seconds: uptime,
        uptime_human: formatUptime(uptime),
        bot_started_at: session.botStartedAt.toISOString(),
        stats: {
            sent_today: session.totalSentToday,
            delivered_today: session.totalDeliveredToday,
            failed_today: session.totalFailedToday,
            unconfirmed_today: session.totalSentToday - session.totalDeliveredToday - session.totalFailedToday,
            consecutive_failures: session.consecutiveFailures,
            ghost_threshold: MAX_CONSECUTIVE_FAILURES,
        },
        last_activity: {
            last_message_sent: session.lastMessageSentAt?.toISOString() || null,
            last_delivery_confirmed: session.lastDeliveryConfirmedAt?.toISOString() || null,
        },
        connection: {
            has_socket: !!session.sock,
            is_reconnecting: session.isReconnecting,
            has_qr: !!session.rawQR,
            qr_url: session.lastQRUrl || null,
            auth_dir_exists: fs.pathExistsSync(session.authDir),
        }
    });
});

// Logs Endpoint per Shop
app.get('/api/logs', verifyApiKey, (req, res) => {
    const shopId = extractShopId(req);
    const session = getOrCreateSession(shopId);
    const limit = parseInt(req.query.limit) || 20;

    res.json({
        status: 'ok',
        shop_id: session.shopId,
        count: session.messageLog.length,
        logs: session.messageLog.slice(0, limit),
    });
});

// Force Reconnect Endpoint per Shop
app.post('/api/reconnect', verifyApiKey, async (req, res) => {
    const shopId = extractShopId(req);
    const session = getOrCreateSession(shopId);
    console.log(`🔄 [Shop ${session.shopId}] Manual reconnect triggered`);
    
    if (session.isReconnecting) {
        return res.json({ status: 'already_reconnecting', message: 'Sedang dalam proses reconnect...' });
    }

    res.json({ status: 'reconnecting', shop_id: session.shopId, message: 'Reconnect dimulai. Tunggu beberapa detik...' });
    setTimeout(() => forceReconnect(session, 'admin_dashboard'), 500);
});

// Logout Endpoint per Shop
app.post('/api/logout', verifyApiKey, async (req, res) => {
    const shopId = extractShopId(req);
    const session = getOrCreateSession(shopId);
    console.log(`🚪 [Shop ${session.shopId}] Logout triggered`);
    
    try {
        if (session.sock) {
            try {
                await session.sock.logout();
            } catch (e) {
                try { session.sock.end(undefined); } catch(e2) {}
            }
            session.sock = null;
        }

        session.connectedPhone = null;
        session.rawQR = '';
        session.lastQRUrl = '';
        session.consecutiveFailures = 0;
        session.pendingDelivery.clear();
        broadcastStatus(session, 'not ready', 'Session berhasil di-logout.');

        await cleanAuthDir(session);

        res.json({ 
            status: 'logged_out',
            shop_id: session.shopId, 
            message: 'Session berhasil di-logout. Menghasilkan QR code baru...' 
        });

        setTimeout(() => connectToWhatsApp(session), 2000);
    } catch (err) {
        console.error(`❌ [Shop ${session.shopId}] Logout Error:`, err);
        res.status(500).json({ status: 'error', pesan: 'Logout failed: ' + err.message });
    }
});

// Socket.IO
io.on('connection', (socket) => {
    console.log('⚡ Socket client connected:', socket.id);

    socket.on('join_shop', (shopId) => {
        const id = normalizeShopId(shopId);
        socket.join(`shop_${id}`);
        console.log(`📡 Socket ${socket.id} joined room shop_${id}`);

        const session = sessions.get(id);
        if (session) {
            if (session.clientStatus === 'ready') {
                socket.emit('ready', { shop_id: id, message: 'Client is ready!' });
                socket.emit('status', { shop_id: id, status: 'ready', message: 'WhatsApp terhubung!' });
            } else if (session.clientStatus === 'qr') {
                socket.emit('qr', { shop_id: id, qr: session.lastQRUrl });
                socket.emit('status', { shop_id: id, status: 'qr', message: 'Silakan scan QR code' });
            } else {
                socket.emit('status', { shop_id: id, status: session.clientStatus, message: 'Client not ready' });
            }
        }
    });

    socket.on('disconnect', () => {
        console.log('🔌 Socket disconnected:', socket.id);
    });
});

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

// Global Error Handling
process.on('unhandledRejection', (reason, promise) => {
    console.error('❌ Unhandled Rejection at:', promise, 'reason:', reason);
});

process.on('uncaughtException', (err) => {
    console.error('❌ Uncaught Exception:', err);
});

// START
initStartupSessions();

const PORT = process.env.PORT || 5001;
server.listen(PORT, () => {
    console.log(`🚀 Multi-Session WhatsApp Gateway running on port ${PORT}`);
    console.log(`🔍 Health check: http://localhost:${PORT}/health`);
    console.log(`📊 Status API: http://localhost:${PORT}/api/status?shop_id=1`);
});