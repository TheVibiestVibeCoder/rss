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

        $subject = "RSS Alert: {$totalItems} new article(s) from {$feedCount} feed(s)";
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
        
        // Agency-style Design
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
        
        /* Design System */
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            width: 100% !important;
            color: #1f2937;
        }
        
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f3f4f6;
            padding-bottom: 60px;
        }
        
        .main-container {
            background-color: #ffffff;
            margin: 0 auto;
            width: 100%;
            max-width: 600px;
            border-spacing: 0;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1f2937;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border-radius: 12px;
            overflow: hidden;
        }

        /* Header */
        .header-td {
            padding: 40px 40px 30px 40px;
            background-color: #ffffff;
            border-bottom: 1px solid #f3f4f6;
        }
        .header-title {
            font-size: 24px;
            font-weight: 800;
            color: #111827;
            margin: 0 0 8px 0;
            letter-spacing: -0.025em;
        }
        .header-meta {
            font-size: 13px;
            font-weight: 500;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* Content */
        .content-td {
            padding: 0 40px;
        }
        
        .feed-group {
            margin-top: 35px;
            margin-bottom: 10px;
        }
        
        .feed-header {
            font-size: 12px;
            font-weight: 700;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid #f3f4f6;
            display: inline-block;
        }
        
        .article-item {
            padding: 25px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .article-item:last-child {
            border-bottom: none;
        }
        
        .article-title {
            font-size: 18px;
            font-weight: 700;
            line-height: 1.4;
            margin: 0 0 8px 0;
        }
        .article-title a {
            color: #111827;
            text-decoration: none;
            transition: color 0.2s;
        }
        .article-title a:hover {
            color: #2563eb;
        }
        
        .article-meta {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 12px;
            display: block;
        }
        
        .article-excerpt {
            font-size: 15px;
            line-height: 1.6;
            color: #4b5563;
            margin: 0 0 15px 0;
        }
        
        .btn-read {
            display: inline-block;
            font-size: 13px;
            font-weight: 600;
            color: #2563eb;
            text-decoration: none;
        }
        .btn-read:hover {
            text-decoration: underline;
        }

        /* Footer */
        .footer-td {
            padding: 30px 40px;
            background-color: #f9fafb;
            text-align: center;
        }
        .footer-text {
            font-size: 12px;
            color: #9ca3af;
            line-height: 1.5;
        }

        /* Mobile */
        @media screen and (max-width: 600px) {
            .wrapper { padding-bottom: 0; }
            .main-container { width: 100% !important; border-radius: 0; box-shadow: none; }
            .header-td { padding: 30px 20px; }
            .content-td { padding: 0 20px; }
            .footer-td { padding: 30px 20px; }
        }
    </style>
</head>
<body>
    <center class="wrapper">
        <table class="main-container" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td class="header-td">
                    <h1 class="header-title">Your Daily Briefing</h1>
                    <div class="header-meta">
                        {$totalItems} updates &middot; {$this->formatDate()}
                    </div>
                </td>
            </tr>
            
            <tr>
                <td class="content-td">
HTML;

        foreach ($allItems as $feedName => $items) {
            $count = count($items);
            $feedNameSafe = htmlspecialchars($feedName);
            
            $html .= <<<HTML
                    <div class="feed-group">
                        <div class="feed-header">{$feedNameSafe} <span style="color:#d1d5db;">/</span> {$count}</div>
HTML;

            foreach ($items as $item) {
                $title = htmlspecialchars($item['title']);
                $link = htmlspecialchars($item['link']);
                $date = htmlspecialchars($item['published']);
                // Use strip_tags but allow NO html in the excerpt for the clean design look
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
                            <a href="{$link}" class="btn-read">Read Article &rarr;</a>
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
                        <strong>RSS Feed Manager</strong><br>
                        Automated Delivery System
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