#!/usr/bin/env php
<?php
/**
 * RSS Feed Manager - Cron Job Script
 *
 * Run this script via cron to automatically check feeds and send email notifications.
 *
 * Example crontab entry (every 15 minutes):
 *   *\/15 * * * * /usr/bin/php /path/to/rss/cron.php >> /path/to/rss/cron.log 2>&1
 *
 * Or via HTTP (if CLI not available):
 *   *\/15 * * * * curl -s https://yourdomain.com/rss/cron.php?key=YOUR_SECRET_KEY
 */

// Security: Allow CLI execution or HTTP with secret key
$config = require __DIR__ . '/config.php';
$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    // HTTP access requires a secret key for security
    $secretKey = $config['cron_key'] ?? 'default_cron_key_change_me';
    $providedKey = $_GET['key'] ?? '';

    if ($providedKey !== $secretKey) {
        http_response_code(403);
        echo "Access denied.\n";
        exit(1);
    }
}

// Set timezone
date_default_timezone_set($config['timezone']);

// Load classes
require_once __DIR__ . '/classes/FeedManager.php';
require_once __DIR__ . '/classes/RSSParser.php';

// Initialize
$manager = new FeedManager($config);
$parser = new RSSParser($manager, $config);

// Run the check
$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] Starting feed check...\n";

$result = $parser->checkAllFeeds();

echo "[{$timestamp}] Results:\n";
echo "  - Feeds checked: {$result['checked']}\n";
echo "  - Feeds with new items: {$result['feeds_with_new']}\n";
echo "  - Total new items: {$result['total_items']}\n";
echo "  - Errors: {$result['errors']}\n";
echo "  - Email sent: " . ($result['email_sent'] ? 'Yes' : 'No') . "\n";
echo "[{$timestamp}] Done.\n";
