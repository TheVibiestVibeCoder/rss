<?php
/**
 * RSS Feed Manager - Configuration
 *
 * Edit the .env file to configure your settings.
 */

// Load .env file
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!empty($key)) {
            putenv("$key=$value");
        }
    }
}

// Auto-create data directory and files
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}
if (!file_exists($dataDir . '/feeds.json')) {
    @file_put_contents($dataDir . '/feeds.json', '[]');
}
if (!file_exists($dataDir . '/last_check.json')) {
    @file_put_contents($dataDir . '/last_check.json', '{}');
}

// Return configuration
return [
    'email' => [
        'to'   => getenv('EMAIL_TO') ?: 'your@email.com',
        'from' => getenv('EMAIL_FROM') ?: 'alerts@yourdomain.com',
    ],
    'whatsapp' => [
        // Green API credentials — see https://green-api.com
        // Leave empty to disable WhatsApp notifications
        'instance_id' => getenv('GREENAPI_INSTANCE_ID') ?: '',
        'token'       => getenv('GREENAPI_TOKEN') ?: '',
    ],
    'admin_password'  => getenv('ADMIN_PASSWORD') ?: 'changeme123',
    'cron_key'        => getenv('CRON_KEY') ?: 'change_this_to_random_string',
    'timezone'        => getenv('TIMEZONE') ?: 'UTC',
    'feeds_file'      => $dataDir . '/feeds.json',
    'last_check_file' => $dataDir . '/last_check.json',
];
