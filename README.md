# RSS Feed Manager

A clean, simple RSS feed monitoring system with email notifications.

## Features

- **Simple Web Interface** - Add, remove, and manage RSS feeds
- **Email Notifications** - Get notified when new articles appear
- **RSS & Atom Support** - Works with both feed formats (including Google Alerts)
- **JSON Storage** - No database required
- **Password Protected** - Secure admin access
- **Cron Ready** - Automated feed checking

## File Structure

```
rss/
├── index.html          # Web interface
├── api.php             # REST API backend
├── config.php          # Configuration file
├── cron.php            # Cron job script
├── setup.sh            # Setup script
├── classes/
│   ├── FeedManager.php # Feed management class
│   └── RSSParser.php   # RSS parsing & email class
└── data/
    ├── feeds.json      # Your feeds (auto-created)
    └── last_check.json # Last check times (auto-created)
```

## Installation

### 1. Upload Files

Upload all files to your web server:
```
/home/USERNAME/public_html/rss/
```

### 2. Run Setup

```bash
chmod +x setup.sh
./setup.sh
```

### 3. Configure

Edit `config.php`:

```php
return [
    'email' => [
        'to'   => 'your@email.com',
        'from' => 'alerts@yourdomain.com',
    ],
    'admin_password' => 'your_secure_password',  // CHANGE THIS!
    'cron_key' => 'your_secret_cron_key',        // CHANGE THIS!
    'timezone' => 'Europe/Vienna',
    // ...
];
```

### 4. Set Up Cron Job

Check feeds every 15 minutes:

**Option A - PHP CLI:**
```
*/15 * * * * /usr/bin/php /path/to/rss/cron.php
```

**Option B - HTTP (if CLI not available):**
```
*/15 * * * * curl -s 'https://yourdomain.com/rss/cron.php?key=YOUR_CRON_KEY'
```

## Usage

1. Open `https://yourdomain.com/rss/` in your browser
2. Log in with your admin password
3. Add RSS feed URLs with a name
4. Wait for the cron job or click "Check Feeds Now"

## API Endpoints

| Action | Method | Parameters |
|--------|--------|------------|
| `status` | GET | - |
| `login` | POST | password |
| `logout` | GET | - |
| `list` | GET | - |
| `add` | POST | name, url |
| `remove` | POST | id |
| `toggle` | POST | id |
| `update` | POST | id, name, url |
| `check` | POST | - |

## Finding RSS Feed URLs

**Google Alerts:**
1. Go to https://www.google.com/alerts
2. Create an alert
3. Click gear icon → "RSS Feed"
4. Copy the URL

**Most Websites:**
- Try adding `/feed`, `/rss`, or `/atom.xml` to the URL
- Look for RSS icon in the browser address bar
- Check page source for `<link rel="alternate" type="application/rss+xml"`

## Troubleshooting

**No emails?**
- Check spam folder
- Test PHP mail() function
- Verify cron job is running

**Feeds not updating?**
- Verify the feed URL works in your browser
- Check error logs
- Ensure data/ directory is writable

**Permission errors?**
```bash
chmod 666 data/feeds.json data/last_check.json
```

## License

MIT License - Use freely for personal or commercial projects.
