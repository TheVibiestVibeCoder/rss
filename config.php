<?php
// Configuration
define('EMAIL_TO', 'markus@disinfoconsulting.eu');
define('EMAIL_FROM', 'alerts@rss.markusschwinghammer.com');
define('FEEDS_FILE', __DIR__ . '/feeds.json');
define('LAST_CHECK_FILE', __DIR__ . '/last_check.json');
define('ADMIN_PASSWORD', 'dein_sicheres_passwort_hier'); // Bitte ändern!

// Zeitzone
date_default_timezone_set('Europe/Vienna');

class FeedManager {
    private $feeds_file;
    private $last_check_file;
    
    public function __construct() {
        $this->feeds_file = FEEDS_FILE;
        $this->last_check_file = LAST_CHECK_FILE;
        
        // Init files if they don't exist
        if (!file_exists($this->feeds_file)) {
            file_put_contents($this->feeds_file, json_encode([], JSON_PRETTY_PRINT));
        }
        if (!file_exists($this->last_check_file)) {
            file_put_contents($this->last_check_file, json_encode([], JSON_PRETTY_PRINT));
        }
    }
    
    public function getFeeds() {
        $content = file_get_contents($this->feeds_file);
        return json_decode($content, true) ?: [];
    }
    
    public function addFeed($name, $url) {
        $feeds = $this->getFeeds();
        $id = uniqid();
        $feeds[$id] = [
            'name' => $name,
            'url' => $url,
            'added' => date('Y-m-d H:i:s'),
            'active' => true
        ];
        file_put_contents($this->feeds_file, json_encode($feeds, JSON_PRETTY_PRINT));
        return $id;
    }
    
    public function removeFeed($id) {
        $feeds = $this->getFeeds();
        if (isset($feeds[$id])) {
            unset($feeds[$id]);
            file_put_contents($this->feeds_file, json_encode($feeds, JSON_PRETTY_PRINT));
            return true;
        }
        return false;
    }
    
    public function toggleFeed($id) {
        $feeds = $this->getFeeds();
        if (isset($feeds[$id])) {
            $feeds[$id]['active'] = !$feeds[$id]['active'];
            file_put_contents($this->feeds_file, json_encode($feeds, JSON_PRETTY_PRINT));
            return true;
        }
        return false;
    }
    
    public function getLastCheck($feed_id) {
        $content = file_get_contents($this->last_check_file);
        $checks = json_decode($content, true) ?: [];
        return isset($checks[$feed_id]) ? strtotime($checks[$feed_id]) : 0;
    }
    
    public function updateLastCheck($feed_id) {
        $content = file_get_contents($this->last_check_file);
        $checks = json_decode($content, true) ?: [];
        $checks[$feed_id] = date('c');
        file_put_contents($this->last_check_file, json_encode($checks, JSON_PRETTY_PRINT));
    }
}
?>