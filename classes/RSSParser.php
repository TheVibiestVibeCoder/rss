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
        $newItems = [];
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
                $newItems[$feedData['name']] = $items;
            }
        }

        $emailSent = false;
        if (!empty($newItems)) {
            $emailSent = $this->sendEmail($newItems);
        }

        return [
            'checked'    => $checkedCount,
            'feeds_with_new' => count($newItems),
            'total_items' => array_sum(array_map('count', $newItems)),
            'errors'     => $errorCount,
            'email_sent' => $emailSent,
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

    private function sendEmail(array $allItems): bool
    {
        $totalItems = array_sum(array_map('count', $allItems));
        $feedCount = count($allItems);

        $subject = "RSS Alert: {$totalItems} new article(s) from {$feedCount} feed(s)";
        $htmlBody = $this->buildHtmlEmail($allItems);
        $textBody = $this->buildTextEmail($allItems);

        $boundary = 'PHP-alt-' . md5(time());

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

        return mail($this->config['email']['to'], $subject, $body, $headers);
    }

    private function buildHtmlEmail(array $allItems): string
    {
        $totalItems = array_sum(array_map('count', $allItems));

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 700px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: #fff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 3px solid #3b82f6;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .header h1 {
            color: #1e40af;
            margin: 0 0 5px 0;
            font-size: 22px;
        }
        .header .meta {
            color: #6b7280;
            font-size: 14px;
        }
        .feed-section {
            margin-bottom: 30px;
        }
        .feed-title {
            color: #1e40af;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .article {
            padding: 15px;
            margin-bottom: 12px;
            background: #f9fafb;
            border-left: 3px solid #3b82f6;
            border-radius: 4px;
        }
        .article-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .article-title a {
            color: #1e40af;
            text-decoration: none;
        }
        .article-title a:hover {
            text-decoration: underline;
        }
        .article-date {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        .article-content {
            font-size: 14px;
            color: #4b5563;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>RSS Feed Updates</h1>
            <div class="meta">{$totalItems} new article(s) | Generated on {$this->formatDate()}</div>
        </div>
HTML;

        foreach ($allItems as $feedName => $items) {
            $count = count($items);
            $feedNameSafe = htmlspecialchars($feedName);
            $html .= <<<HTML
        <div class="feed-section">
            <div class="feed-title">{$feedNameSafe} ({$count} article(s))</div>
HTML;

            foreach ($items as $item) {
                $title = htmlspecialchars($item['title']);
                $link = htmlspecialchars($item['link']);
                $date = htmlspecialchars($item['published']);
                $content = strip_tags($item['content'], '<p><br><strong><em><b><i>');

                $html .= <<<HTML
            <div class="article">
                <div class="article-title"><a href="{$link}">{$title}</a></div>
                <div class="article-date">{$date}</div>
                <div class="article-content">{$content}</div>
            </div>
HTML;
            }

            $html .= "        </div>\n";
        }

        $html .= <<<HTML
        <div class="footer">
            RSS Feed Manager | Automated email notification
        </div>
    </div>
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
