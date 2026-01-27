<?php
require_once 'config.php';

class RSSParser {
    private $manager;
    
    public function __construct() {
        $this->manager = new FeedManager();
    }
    
    public function checkAllFeeds() {
        $feeds = $this->manager->getFeeds();
        $all_new_items = [];
        
        foreach ($feeds as $feed_id => $feed_data) {
            if (!$feed_data['active']) {
                continue;
            }
            
            $new_items = $this->checkFeed($feed_id, $feed_data);
            if (!empty($new_items)) {
                $all_new_items[$feed_data['name']] = $new_items;
            }
        }
        
        if (!empty($all_new_items)) {
            $this->sendEmail($all_new_items);
            return count($all_new_items);
        }
        
        return 0;
    }
    
    private function checkFeed($feed_id, $feed_data) {
        $new_items = [];
        
        try {
            // Suppress warnings for malformed feeds
            libxml_use_internal_errors(true);
            $rss = simplexml_load_file($feed_data['url']);
            libxml_clear_errors();
            
            if (!$rss) {
                error_log("Failed to parse feed: " . $feed_data['name']);
                return [];
            }
            
            $last_check_time = $this->manager->getLastCheck($feed_id);
            
            // Handle both RSS and Atom feeds
            if (isset($rss->entry)) {
                // Atom feed (Google Alerts format)
                foreach ($rss->entry as $item) {
                    $pub_date = strtotime((string)$item->published);
                    
                    if ($pub_date > $last_check_time) {
                        $new_items[] = [
                            'title' => (string)$item->title,
                            'link' => (string)$item->link['href'],
                            'published' => date('d.m.Y H:i', $pub_date),
                            'content' => (string)$item->content
                        ];
                    }
                }
            } elseif (isset($rss->channel->item)) {
                // RSS feed
                foreach ($rss->channel->item as $item) {
                    $pub_date = strtotime((string)$item->pubDate);
                    
                    if ($pub_date > $last_check_time) {
                        $new_items[] = [
                            'title' => (string)$item->title,
                            'link' => (string)$item->link,
                            'published' => date('d.m.Y H:i', $pub_date),
                            'content' => (string)$item->description
                        ];
                    }
                }
            }
            
            if (!empty($new_items)) {
                $this->manager->updateLastCheck($feed_id);
            }
            
        } catch (Exception $e) {
            error_log("Error checking feed " . $feed_data['name'] . ": " . $e->getMessage());
        }
        
        return $new_items;
    }
    
    private function sendEmail($all_items) {
        $total_items = 0;
        foreach ($all_items as $items) {
            $total_items += count($items);
        }
        
        $subject = "RSS Alerts: {$total_items} neue Artikel von " . count($all_items) . " Feed(s)";
        
        $html_message = $this->buildHTMLEmail($all_items);
        $text_message = $this->buildTextEmail($all_items);
        
        // Headers for HTML email
        $headers = "From: " . EMAIL_FROM . "\r\n";
        $headers .= "Reply-To: " . EMAIL_FROM . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"PHP-alt-boundary\"\r\n";
        
        $body = "--PHP-alt-boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $text_message . "\r\n\r\n";
        $body .= "--PHP-alt-boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $html_message . "\r\n\r\n";
        $body .= "--PHP-alt-boundary--";
        
        mail(EMAIL_TO, $subject, $body, $headers);
    }
    
    private function buildHTMLEmail($all_items) {
        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 3px solid #0066cc;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        h1 {
            color: #0066cc;
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .timestamp {
            color: #666;
            font-size: 14px;
        }
        .feed-section {
            margin-bottom: 40px;
        }
        .feed-title {
            color: #0066cc;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }
        .item {
            margin-bottom: 25px;
            padding: 15px;
            background-color: #f9f9f9;
            border-left: 4px solid #0066cc;
            border-radius: 4px;
        }
        .item-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .item-title a {
            color: #0066cc;
            text-decoration: none;
        }
        .item-title a:hover {
            text-decoration: underline;
        }
        .item-meta {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
        }
        .item-content {
            font-size: 14px;
            color: #444;
            line-height: 1.5;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #999;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📰 RSS Feed Updates</h1>
            <div class="timestamp">Gesendet am ' . date('d.m.Y \u\m H:i') . ' Uhr</div>
        </div>
';
        
        foreach ($all_items as $feed_name => $items) {
            $html .= '
        <div class="feed-section">
            <div class="feed-title">' . htmlspecialchars($feed_name) . ' (' . count($items) . ' neue Artikel)</div>
';
            
            foreach ($items as $item) {
                $html .= '
            <div class="item">
                <div class="item-title">
                    <a href="' . htmlspecialchars($item['link']) . '" target="_blank">' . htmlspecialchars($item['title']) . '</a>
                </div>
                <div class="item-meta">📅 ' . htmlspecialchars($item['published']) . '</div>
                <div class="item-content">' . strip_tags($item['content'], '<p><br><strong><em>') . '</div>
            </div>
';
            }
            
            $html .= '
        </div>
';
        }
        
        $html .= '
        <div class="footer">
            RSS to Email System | rss.markusschwinghammer.com
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    private function buildTextEmail($all_items) {
        $text = "RSS FEED UPDATES\n";
        $text .= "Gesendet am " . date('d.m.Y \u\m H:i') . " Uhr\n";
        $text .= str_repeat("=", 60) . "\n\n";
        
        foreach ($all_items as $feed_name => $items) {
            $text .= strtoupper($feed_name) . " (" . count($items) . " neue Artikel)\n";
            $text .= str_repeat("-", 60) . "\n\n";
            
            foreach ($items as $item) {
                $text .= "• " . $item['title'] . "\n";
                $text .= "  " . $item['published'] . "\n";
                $text .= "  " . $item['link'] . "\n";
                $text .= "  " . strip_tags($item['content']) . "\n\n";
            }
            
            $text .= "\n";
        }
        
        return $text;
    }
}

// If called directly (e.g., from cron)
if (php_sapi_name() === 'cli' || basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $parser = new RSSParser();
    $count = $parser->checkAllFeeds();
    echo date('Y-m-d H:i:s') . " - Checked feeds. Sent {$count} feed(s) with new items.\n";
}
?>