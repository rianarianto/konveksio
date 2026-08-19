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

let clientStatus = 'not ready';
let lastQRUrl = '';
let rawQR = '';
let sock = null;

// Path auth folder Baileys
const AUTH_DIR = path.join(__dirname, 'auth_info_baileys');

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

// Initialize WhatsApp Socket (Baileys)
const connectToWhatsApp = async () => {
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
            keepAliveIntervalMs: 25000
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
                broadcastStatus('ready', 'WhatsApp terhubung dan siap digunakan!');
            }

            if (connection === 'close') {
                const statusCode = lastDisconnect?.error?.output?.statusCode;
                const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
                console.log(`🔌 Connection closed. Reason: ${lastDisconnect?.error?.message || statusCode}, Should Reconnect: ${shouldReconnect}`);

                if (statusCode === DisconnectReason.loggedOut) {
                    console.log('🔒 Client logged out. Clearing auth directory...');
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

    } catch (err) {
        console.error('❌ Failed to initialize Baileys connection:', err);
        broadcastStatus('not ready', 'Gagal inisialisasi modul WhatsApp');
        setTimeout(connectToWhatsApp, 10000);
    }
};

// Express Middleware
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Main Send API Handler (Compatible with Laravel NotificationHelper & WorkOrderService)
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
            pesan: "WhatsApp client belum siap. Buka http://localhost:5001 untuk scan QR.",
            clientStatus
        });
    }

    try {
        console.log(`📤 Sending WhatsApp message via Baileys to ${jid}...`);
        
        // Timeout 12s safety race
        const sendPromise = sock.sendMessage(jid, { text: messageText });
        const timeoutPromise = new Promise((_, reject) => 
            setTimeout(() => reject(new Error('WhatsApp sendMessage timeout (12s)')), 12000)
        );

        const result = await Promise.race([sendPromise, timeoutPromise]);
        console.log('✅ Message sent successfully!', result?.key?.id);

        return res.json({ 
            status: "berhasil terkirim", 
            pesan: messageText, 
            to: jid,
            id: result?.key?.id || null 
        });
    } catch (error) {
        console.error('❌ Send Message Error:', error.message);
        return res.status(500).json({ 
            status: "error", 
            pesan: "Gagal kirim: " + error.message 
        });
    }
};

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

// API Routes
app.post('/api', verifyApiKey, apiSendHandler);
app.get('/api', verifyApiKey, apiSendHandler);
app.post('/send-message', verifyApiKey, apiSendHandler);
app.get('/send-message', verifyApiKey, apiSendHandler);

// GET /qr endpoint - Render QR Code directly in browser
app.get('/qr', async (req, res) => {
    if (clientStatus === 'ready') {
        return res.send(`
            <div style="font-family:sans-serif; text-align:center; padding:50px;">
                <h2 style="color:#16a34a;">✅ WhatsApp Client Sudah Terhubung!</h2>
                <p>Status: Ready. Tidak perlu scan QR lagi.</p>
                <a href="/">Kembali ke Dashboard</a>
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

// Socket.IO Connection Handler
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

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({
        status: 'ok',
        engine: 'Baileys',
        whatsapp: {
            status: clientStatus,
            hasSocket: !!sock,
            timestamp: new Date().toISOString()
        }
    });
});

// Global Error Handling
process.on('unhandledRejection', (reason, promise) => {
    console.error('❌ Unhandled Rejection at:', promise, 'reason:', reason);
});

process.on('uncaughtException', (err) => {
    console.error('❌ Uncaught Exception:', err);
});

// Start Baileys Connection
connectToWhatsApp();

// Start Express Server
const PORT = process.env.PORT || 5001;
server.listen(PORT, () => {
    console.log(`🚀 Server is running on port ${PORT}`);
    console.log(`🌐 Dashboard: http://localhost:${PORT}`);
    console.log(`📷 Direct QR: http://localhost:${PORT}/qr`);
    console.log(`🔍 Health check: http://localhost:${PORT}/health`);
});