<?php
/**
 * RSSParser - Parses RSS/Atom feeds and sends email notifications
 */
class RSSParser
{
    private FeedManager $manager;
    private array $config;

    public function __construct(FeedManager $manager, array $config)
    {
        $this->manager = $manager;
        $this->config = $config;
    }

    /**
     * Check all active feeds for new items
     * @return array Statistics about the check
     */
    public function checkAllFeeds(): array
    {
        $feeds = $this->manager->getAll();
        $newItems = []; // feedId => ['name' => ..., 'items' => [...]]
        $checkedCount = 0;
        $errorCount = 0;

        foreach ($feeds as $feedId => $feedData) {
            if (!$feedData['active']) {
                continue;
            }

            $checkedCount++;
            $items = $this->checkFeed($feedId, $feedData);

            if ($items === false) {
                $errorCount++;
            } elseif (!empty($items)) {
                $newItems[$feedId] = [
                    'name' => $feedData['name'],
                    'items' => $items,
                ];
            }
        }

        $emailsSent = 0;
        if (!empty($newItems)) {
            $emailsSent = $this->sendEmails($newItems);
        }

        return [
            'checked'    => $checkedCount,
            'feeds_with_new' => count($newItems),
            'total_items' => array_sum(array_map(fn($f) => count($f['items']), $newItems)),
            'errors'     => $errorCount,
            'emails_sent' => $emailsSent,
        ];
    }

    /**
     * Check a single feed for new items
     */
    private function checkFeed(string $feedId, array $feedData): array|false
    {
        try {
            libxml_use_internal_errors(true);
            $rss = @simplexml_load_file($feedData['url']);
            libxml_clear_errors();

            if (!$rss) {
                error_log("RSS Parser: Failed to load feed '{$feedData['name']}' from {$feedData['url']}");
                return false;
            }

            $lastCheckTime = $this->manager->getLastCheck($feedId);
            $newItems = [];

            // Handle Atom feeds (e.g., Google Alerts)
            if (isset($rss->entry)) {
                $newItems = $this->parseAtomFeed($rss, $lastCheckTime);
            }
            // Handle RSS feeds
            elseif (isset($rss->channel->item)) {
                $newItems = $this->parseRSSFeed($rss, $lastCheckTime);
            }

            if (!empty($newItems)) {
                $this->manager->updateLastCheck($feedId);
            }

            return $newItems;

        } catch (Exception $e) {
            error_log("RSS Parser: Error checking feed '{$feedData['name']}': " . $e->getMessage());
            return false;
        }
    }

    private function parseAtomFeed(SimpleXMLElement $feed, int $lastCheckTime): array
    {
        $items = [];
        foreach ($feed->entry as $entry) {
            $pubDate = strtotime((string)$entry->published);

            if ($pubDate > $lastCheckTime) {
                $items[] = [
                    'title'     => (string)$entry->title,
                    'link'      => (string)$entry->link['href'],
                    'published' => date('d.m.Y H:i', $pubDate),
                    'content'   => (string)$entry->content,
                ];
            }
        }
        return $items;
    }

    private function parseRSSFeed(SimpleXMLElement $feed, int $lastCheckTime): array
    {
        $items = [];
        foreach ($feed->channel->item as $item) {
            $pubDate = strtotime((string)$item->pubDate);

            if ($pubDate > $lastCheckTime) {
                $items[] = [
                    'title'     => (string)$item->title,
                    'link'      => (string)$item->link,
                    'published' => date('d.m.Y H:i', $pubDate),
                    'content'   => (string)$item->description,
                ];
            }
        }
        return $items;
    }

    /**
     * Send emails to all recipients based on their subscriptions
     * @param array $newItems feedId => ['name' => ..., 'items' => [...]]
     * @return int Number of emails sent
     */
    private function sendEmails(array $newItems): int
    {
        $emailsData = $this->manager->getEmails();
        $emailsSent = 0;

        // If no emails configured, send to fallback from config
        if (empty($emailsData)) {
            $recipientItems = $this->formatItemsForEmail($newItems);
            if ($this->sendSingleEmail($this->config['email']['to'], $recipientItems)) {
                $emailsSent++;
            }
            return $emailsSent;
        }

        // Send to each recipient based on their subscriptions
        foreach ($emailsData as $email => $subscription) {
            $recipientItems = [];

            foreach ($newItems as $feedId => $feedData) {
                // Check if this recipient is subscribed to this feed
                if ($subscription['subscribeAll'] || in_array($feedId, $subscription['feeds'])) {
                    $recipientItems[$feedData['name']] = $feedData['items'];
                }
            }

            // Only send if there are items for this recipient
            if (!empty($recipientItems)) {
                if ($this->sendSingleEmail($email, $recipientItems)) {
                    $emailsSent++;
                }
            }
        }

        return $emailsSent;
    }

    private function formatItemsForEmail(array $newItems): array
    {
        $formatted = [];
        foreach ($newItems as $feedId => $feedData) {
            $formatted[$feedData['name']] = $feedData['items'];
        }
        return $formatted;
    }

    private function sendSingleEmail(string $recipient, array $items): bool
    {
        $totalItems = array_sum(array_map('count', $items));
        $feedCount = count($items);

        $subject = "Your Feed: {$totalItems} new article(s) from {$feedCount} source(s)";
        $htmlBody = $this->buildHtmlEmail($items);
        $textBody = $this->buildTextEmail($items);

        $boundary = 'PHP-alt-' . md5(time() . $recipient);

        $headers = implode("\r\n", [
            "From: {$this->config['email']['from']}",
            "Reply-To: {$this->config['email']['from']}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        ]);

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $textBody . "\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";
        $body .= "--{$boundary}--";

        return mail($recipient, $subject, $body, $headers);
    }

    private function buildHtmlEmail(array $allItems): string
    {
        $totalItems = array_sum(array_map('count', $allItems));
        
        $html = <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Feed Digest</title>
    <style type="text/css">
        /* Base Resets */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        
        /* Soothing Design System */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc; /* Very light cool grey */
            margin: 0;
            padding: 0;
            width: 100% !important;
            color: #374151; /* Soft dark grey */
        }
        
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f8fafc;
            padding-top: 40px;
            padding-bottom: 60px;
        }
        
        .main-container {
            background-color: #ffffff;
            margin: 0 auto;
            width: 100%;
            max-width: 600px;
            border-spacing: 0;
            border-radius: 16px; /* Softer corners */
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.03), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
        }

        /* Header */
        .header-td {
            padding: 45px 40px 30px 40px;
            background-color: #ffffff;
            text-align: center;
        }
        .header-title {
            font-size: 26px;
            font-weight: 800;
            color: #111827;
            margin: 0 0 10px 0;
            letter-spacing: -0.03em;
        }
        .header-meta {
            font-size: 14px;
            color: #9ca3af;
            background-color: #f3f4f6;
            padding: 6px 16px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 500;
        }
        
        /* Content */
        .content-td {
            padding: 0 40px 20px 40px;
        }
        
        /* The New Feed Header: High Visibility */
        .feed-group-wrapper {
            margin-bottom: 40px;
        }
        
        .feed-header-box {
            background-color: #f0f9ff; /* Very soft blue background */
            border-left: 4px solid #0ea5e9; /* Nice blue accent line */
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 0 8px 8px 0;
        }
        
        .feed-name {
            font-size: 16px;
            font-weight: 700;
            color: #0c4a6e; /* Darker blue text for readability */
            margin: 0;
        }
        
        .feed-count {
            font-size: 12px;
            font-weight: 500;
            color: #7dd3fc;
            text-transform: uppercase;
            float: right;
            margin-top: 2px;
        }
        
        /* Article Items */
        .article-item {
            padding-bottom: 25px;
            margin-bottom: 25px;
            border-bottom: 1px solid #f1f5f9; /* Super subtle divider */
        }
        .article-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .article-meta {
            font-size: 12px;
            color: #9ca3af;
            margin-bottom: 8px;
            font-weight: 500;
            letter-spacing: 0.02em;
        }
        
        .article-title {
            font-size: 18px;
            font-weight: 700;
            line-height: 1.4;
            margin: 0 0 10px 0;
        }
        .article-title a {
            color: #1f2937;
            text-decoration: none;
        }
        .article-title a:hover {
            color: #0ea5e9;
        }
        
        .article-excerpt {
            font-size: 15px;
            line-height: 1.7; /* Relaxed reading */
            color: #4b5563;
            margin: 0 0 16px 0;
        }
        
        /* Button Style Link */
        .btn-read {
            display: inline-block;
            font-size: 13px;
            font-weight: 600;
            color: #0ea5e9;
            background-color: #e0f2fe;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn-read:hover {
            background-color: #bae6fd;
        }

        /* Footer */
        .footer-td {
            padding: 30px;
            background-color: #f8fafc;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        .footer-text {
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.5;
        }

        /* Mobile */
        @media screen and (max-width: 600px) {
            .wrapper { padding-top: 10px; padding-bottom: 20px; }
            .main-container { width: 100% !important; border-radius: 0; box-shadow: none; }
            .header-td { padding: 30px 20px; }
            .content-td { padding: 0 20px 20px 20px; }
        }
    </style>
</head>
<body>
    <center class="wrapper">
        <table class="main-container" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td class="header-td">
                    <h1 class="header-title">Daily Briefing</h1>
                    <div class="header-meta">
                        {$this->formatDate()} &bull; {$totalItems} New Updates
                    </div>
                </td>
            </tr>
            
            <tr>
                <td class="content-td">
HTML;

        foreach ($allItems as $feedName => $items) {
            $count = count($items);
            $feedNameSafe = htmlspecialchars($feedName);
            
            // Note: Changed header to a full colored box for clarity
            $html .= <<<HTML
                    <div class="feed-group-wrapper">
                        <div class="feed-header-box">
                            <span class="feed-name">{$feedNameSafe}</span>
                            <span class="feed-count">{$count} New</span>
                            <div style="clear:both;"></div>
                        </div>
HTML;

            foreach ($items as $item) {
                $title = htmlspecialchars($item['title']);
                $link = htmlspecialchars($item['link']);
                $date = htmlspecialchars($item['published']);
                
                // Strip tags for clean summary, limit length
                $content = strip_tags($item['content']);
                if (strlen($content) > 280) {
                    $content = substr($content, 0, 280) . '...';
                }

                $html .= <<<HTML
                        <div class="article-item">
                            <div class="article-meta">{$date}</div>
                            <h2 class="article-title">
                                <a href="{$link}" target="_blank">{$title}</a>
                            </h2>
                            <div class="article-excerpt">{$content}</div>
                            <a href="{$link}" class="btn-read">Read Article</a>
                        </div>
HTML;
            }

            $html .= "</div>\n";
        }

        $html .= <<<HTML
                </td>
            </tr>

            <tr>
                <td class="footer-td">
                    <div class="footer-text">
                        Sent by <strong>RSS Feed Manager</strong>
                    </div>
                </td>
            </tr>
        </table>
    </center>
</body>
</html>
HTML;

        return $html;
    }

    private function buildTextEmail(array $allItems): string
    {
        $text = "RSS FEED UPDATES\n";
        $text .= "Generated on " . $this->formatDate() . "\n";
        $text .= str_repeat("=", 60) . "\n\n";

        foreach ($allItems as $feedName => $items) {
            $text .= strtoupper($feedName) . " (" . count($items) . " article(s))\n";
            $text .= str_repeat("-", 50) . "\n\n";

            foreach ($items as $item) {
                $text .= "* " . $item['title'] . "\n";
                $text .= "  Date: " . $item['published'] . "\n";
                $text .= "  Link: " . $item['link'] . "\n";
                $content = strip_tags($item['content']);
                if (strlen($content) > 200) {
                    $content = substr($content, 0, 200) . '...';
                }
                $text .= "  " . wordwrap($content, 70, "\n  ") . "\n\n";
            }

            $text .= "\n";
        }

        return $text;
    }

    private function formatDate(): string
    {
        return date('Y-m-d H:i');
    }
}