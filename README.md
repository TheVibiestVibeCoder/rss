# RSS Feed Manager

A clean, simple RSS feed monitoring system with email and WhatsApp notifications.

## Features

- **Simple Web Interface** - Add, remove, and manage RSS feeds
- **Email Notifications** - Get notified when new articles appear
- **WhatsApp Notifications** - Send feed updates to WhatsApp groups or channels (self-hosted, no fees)
- **RSS & Atom Support** - Works with both feed formats (including Google Alerts)
- **No Database** - Uses simple JSON files
- **Shared Hosting Ready** - No command line needed (email only); WhatsApp requires a Node.js server

## File Structure

```
rss/
├── index.html              # Web interface
├── api.php                 # REST API backend
├── config.php              # Loads settings from .env
├── cron.php                # Cron job script
├── .env                    # YOUR SETTINGS (edit this!)
├── .htaccess               # Security rules
├── classes/
│   ├── FeedManager.php
│   ├── RSSParser.php
│   └── WhatsAppNotifier.php
├── whatsapp-bridge/        # Self-hosted WhatsApp bridge (Node.js)
│   ├── server.js
│   └── package.json
└── data/                   # Auto-created
    ├── feeds.json
    └── last_check.json
```

## Installation

### 1. Upload Files

Upload all files to your web server via FTP.

### 2. Edit .env File

Open `.env` and fill in your settings:

```
ADMIN_PASSWORD=your_secure_password
EMAIL_TO=your@email.com
EMAIL_FROM=alerts@yourdomain.com
CRON_KEY=some_random_string_here
TIMEZONE=Europe/Vienna

# WhatsApp bridge (optional — see section below)
WA_BRIDGE_URL=http://127.0.0.1:3030
WA_BRIDGE_TOKEN=some_other_random_secret
```

### 3. Set Up Cron Job

In your hosting panel (cPanel, Plesk, etc.), add a cron job:

**Every 15 minutes:**
```
*/15 * * * * curl -s 'https://yourdomain.com/rss/cron.php?key=YOUR_CRON_KEY'
```

Replace `YOUR_CRON_KEY` with the value you set in `.env`.

### 4. Done!

Open `https://yourdomain.com/rss/` in your browser and log in.

---

## WhatsApp Notifications (Self-Hosted)

WhatsApp messages are sent through a small Node.js bridge that uses your own WhatsApp account via the [whatsapp-web.js](https://github.com/pedroslopez/whatsapp-web.js) library (open source, Apache 2.0). **No third-party service or API fees required.**

### Requirements

- Node.js 18 or newer
- A WhatsApp account (personal or business)
- A server where the bridge process can run continuously (e.g. a VPS, not shared hosting)

### Setup

**1. Install dependencies**

```bash
cd whatsapp-bridge
npm install
```

**2. Start the bridge**

```bash
WA_BRIDGE_TOKEN=your_secret node server.js
```

On first run a QR code is printed in the terminal. Open WhatsApp on your phone:
> **Settings → Linked Devices → Link a Device** → scan the QR code

The session is saved in `whatsapp-bridge/session/` and reused on restart — you only need to scan once.

**3. Configure .env**

```
WA_BRIDGE_URL=http://127.0.0.1:3030
WA_BRIDGE_TOKEN=your_secret   # same value as above
```

**4. Add channels in the web UI**

Log into the RSS Manager and add WhatsApp group or channel IDs under the WhatsApp section.

### Finding a Group's Chat ID

Run the following one-liner from the `whatsapp-bridge/` directory (after scanning the QR code at least once so the session exists):

```bash
node -e "
  const {Client, LocalAuth} = require('whatsapp-web.js');
  const c = new Client({ authStrategy: new LocalAuth({ dataPath: './session' }) });
  c.on('ready', async () => {
    const chats = await c.getChats();
    chats.filter(ch => ch.isGroup).forEach(ch =>
      console.log(ch.id._serialized, '\t', ch.name)
    );
    c.destroy();
  });
  c.initialize();
"
```

Copy the chat ID (format: `1234567890-1234567890@g.us`) and paste it into the web UI.

### Chat ID Formats

| Target              | Format                         |
|---------------------|--------------------------------|
| WhatsApp Group      | `1234567890-1234567890@g.us`   |
| Individual contact  | `491234567890@c.us`            |

For individual contacts: use the full number with country code, no `+`.

### Running the Bridge as a Service (systemd)

To keep the bridge running in the background on a Linux server:

```ini
# /etc/systemd/system/wa-bridge.service
[Unit]
Description=WhatsApp Bridge for RSS Feed Manager
After=network.target

[Service]
WorkingDirectory=/path/to/rss/whatsapp-bridge
ExecStart=/usr/bin/node server.js
Restart=on-failure
Environment=WA_BRIDGE_PORT=3030
Environment=WA_BRIDGE_TOKEN=your_secret

[Install]
WantedBy=multi-user.target
```

```bash
systemctl enable --now wa-bridge
```

---

## Usage

1. Open the URL in your browser
2. Log in with your password
3. Add RSS feed URLs
4. Wait for the cron job or click "Check Feeds Now"

## Finding RSS Feed URLs

**Google Alerts:**
1. Go to https://www.google.com/alerts
2. Create an alert
3. Click gear icon > "RSS Feed"
4. Copy the URL

**Most Websites:**
- Try adding `/feed`, `/rss`, or `/atom.xml` to the URL
- Look for RSS icon in browser

## Troubleshooting

**No emails?**
- Check spam folder
- Verify cron job is set up

**Can't log in?**
- Check your password in `.env`
- Make sure `.env` file was uploaded

**Feeds not updating?**
- Verify the feed URL works in your browser
- Make sure cron job is running

**WhatsApp messages not sending?**
- Check that `node server.js` is running (`systemctl status wa-bridge`)
- Check the bridge logs for errors
- Visit `http://127.0.0.1:3030/status` — should return `{"ready":true}`
- Make sure `WA_BRIDGE_URL` and `WA_BRIDGE_TOKEN` in `.env` match the bridge settings
