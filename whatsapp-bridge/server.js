/**
 * WhatsApp Bridge — Self-hosted HTTP service for sending WhatsApp messages
 *
 * Uses whatsapp-web.js (open source, Apache 2.0) which connects to your own
 * WhatsApp account via the WhatsApp Web protocol. No third-party services.
 *
 * First run: a QR code is printed to the terminal — scan it in WhatsApp
 *   (Settings > Linked Devices > Link a Device)
 *   The session is saved in ./session/ and reused on restart.
 *
 * Usage:
 *   npm install
 *   node server.js
 *
 * Environment variables (optional):
 *   WA_BRIDGE_PORT=3030        Port to listen on (default: 3030)
 *   WA_BRIDGE_TOKEN=secret     Auth token for the HTTP API (recommended)
 */

'use strict';

const { Client, LocalAuth } = require('whatsapp-web.js');
const express = require('express');
const qrcode  = require('qrcode-terminal');

const PORT       = parseInt(process.env.WA_BRIDGE_PORT || '3030', 10);
const AUTH_TOKEN = process.env.WA_BRIDGE_TOKEN || '';

const app = express();
app.use(express.json());

// ── WhatsApp client ──────────────────────────────────────────────────────────

let clientReady = false;

const client = new Client({
    authStrategy: new LocalAuth({ dataPath: './session' }),
    puppeteer: {
        // Needed when running as root or in Docker
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    },
});

client.on('qr', (qr) => {
    console.log('\n=========================================================');
    console.log('  Scan the QR code below in WhatsApp:');
    console.log('  Settings > Linked Devices > Link a Device');
    console.log('=========================================================\n');
    qrcode.generate(qr, { small: true });
});

client.on('ready', () => {
    console.log('[bridge] WhatsApp client ready — session active.');
    clientReady = true;
});

client.on('authenticated', () => {
    console.log('[bridge] Authenticated successfully.');
});

client.on('auth_failure', (msg) => {
    console.error('[bridge] Auth failure:', msg);
    clientReady = false;
});

client.on('disconnected', (reason) => {
    console.warn('[bridge] Disconnected:', reason);
    clientReady = false;
    // Attempt to reconnect after a short delay
    setTimeout(() => {
        console.log('[bridge] Attempting to reconnect...');
        client.initialize().catch(console.error);
    }, 5000);
});

client.initialize().catch((err) => {
    console.error('[bridge] Failed to initialize client:', err);
    process.exit(1);
});

// ── Auth middleware ──────────────────────────────────────────────────────────

function requireAuth(req, res, next) {
    if (!AUTH_TOKEN) return next(); // No token configured → open (local use only)
    const token = req.headers['x-auth-token'] || req.query.token;
    if (token !== AUTH_TOKEN) {
        return res.status(401).json({ error: 'Unauthorized' });
    }
    next();
}

// ── Routes ───────────────────────────────────────────────────────────────────

/**
 * GET /status
 * Returns whether the WhatsApp client is connected and ready.
 */
app.get('/status', requireAuth, (req, res) => {
    res.json({ ready: clientReady });
});

/**
 * POST /send
 * Body: { chatId: "...", message: "..." }
 *
 * chatId formats:
 *   Group:       "1234567890-1234567890@g.us"
 *   Individual:  "491234567890@c.us"  (country code + number, no +)
 *
 * Returns: { success: true } or { error: "..." }
 */
app.post('/send', requireAuth, async (req, res) => {
    const { chatId, message } = req.body;

    if (!chatId || !message) {
        return res.status(400).json({ error: 'chatId and message are required' });
    }

    if (!clientReady) {
        return res.status(503).json({
            error: 'WhatsApp client not ready. Check the bridge terminal for the QR code.',
        });
    }

    try {
        await client.sendMessage(chatId, message);
        console.log(`[bridge] Sent message to ${chatId}`);
        res.json({ success: true });
    } catch (err) {
        console.error(`[bridge] Failed to send to ${chatId}:`, err.message);
        res.status(500).json({ error: err.message });
    }
});

// ── Start ─────────────────────────────────────────────────────────────────────

// Bind to 127.0.0.1 only — the bridge is meant to be called locally by PHP
app.listen(PORT, '127.0.0.1', () => {
    console.log(`[bridge] HTTP API listening on http://127.0.0.1:${PORT}`);
    if (!AUTH_TOKEN) {
        console.warn('[bridge] Warning: WA_BRIDGE_TOKEN is not set. Set it to protect the API.');
    }
});
