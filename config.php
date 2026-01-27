<?php
/**
 * RSS Feed Manager - Configuration
 *
 * Edit these settings to match your environment.
 */

return [
    // Email Configuration
    'email' => [
        'to'   => 'markus@disinfoconsulting.eu',
        'from' => 'alerts@rss.markusschwinghammer.com',
    ],

    // Admin Password (change this!)
    'admin_password' => 'changeme123',

    // Timezone
    'timezone' => 'Europe/Vienna',

    // Cron Secret Key (for HTTP cron access - change this!)
    'cron_key' => 'your_secret_cron_key_here',

    // Data Files (relative to this file)
    'feeds_file'      => __DIR__ . '/data/feeds.json',
    'last_check_file' => __DIR__ . '/data/last_check.json',
];
