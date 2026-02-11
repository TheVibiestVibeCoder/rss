# RSS Feed Manager

A clean, simple RSS feed monitoring system with email notifications.

## Features

- **Simple Web Interface** - Add, remove, and manage RSS feeds
- **Email Notifications** - Get notified when new articles appear
- **RSS & Atom Support** - Works with both feed formats (including Google Alerts)
- **No Database** - Uses simple JSON files
- **Shared Hosting Ready** - No command line needed

## File Structure

```
rss/
├── index.html          # Web interface
├── api.php             # REST API backend
├── config.php          # Loads settings from .env
├── cron.php            # Cron job script
├── .env                # YOUR SETTINGS (edit this!)
├── .htaccess           # Security rules
├── classes/
│   ├── FeedManager.php
│   └── RSSParser.php
└── data/               # Auto-created
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
