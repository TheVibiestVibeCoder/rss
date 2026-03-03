<?php
/**
 * WhatsAppNotifier - Sends RSS feed updates to WhatsApp groups/channels via Green API
 *
 * Setup:
 *   1. Register at https://green-api.com and create a free instance
 *   2. Scan the QR code in their dashboard to link your WhatsApp account
 *   3. Add GREENAPI_INSTANCE_ID and GREENAPI_TOKEN to your .env file
 *   4. Add your WhatsApp group/channel IDs via the web UI
 *
 * Chat ID formats:
 *   - WhatsApp Group:     1234567890-1234567890@g.us  (copy from Green API "Groups" section)
 *   - Individual number:  491234567890@c.us            (country code + number, no +)
 */
class WhatsAppNotifier
{
    private string $instanceId;
    private string $token;
    private string $baseUrl;

    public function __construct(array $config)
    {
        $this->instanceId = $config['whatsapp']['instance_id'] ?? '';
        $this->token      = $config['whatsapp']['token'] ?? '';
        $this->baseUrl    = 'https://api.green-api.com';
    }

    /**
     * Returns true if Green API credentials are configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->instanceId) && !empty($this->token);
    }

    /**
     * Send RSS updates to all configured WhatsApp channels based on their subscriptions
     *
     * @param array $newItems  feedId => ['name' => ..., 'items' => [...]]
     * @param array $channels  chatId => ['label' => ..., 'subscribeAll' => ..., 'feeds' => [...]]
     * @return int Number of messages sent
     */
    public function sendNotifications(array $newItems, array $channels): int
    {
        if (!$this->isConfigured() || empty($channels) || empty($newItems)) {
            return 0;
        }

        $sent = 0;
        foreach ($channels as $chatId => $channel) {
            $channelItems = [];

            foreach ($newItems as $feedId => $feedData) {
                if ($channel['subscribeAll'] || in_array($feedId, $channel['feeds'])) {
                    $channelItems[$feedData['name']] = $feedData['items'];
                }
            }

            if (!empty($channelItems)) {
                $message = $this->buildMessage($channelItems);
                if ($this->sendMessage($chatId, $message)) {
                    $sent++;
                }
            }
        }

        return $sent;
    }

    /**
     * Send a single message to a WhatsApp chat via Green API
     */
    public function sendMessage(string $chatId, string $message): bool
    {
        $url = "{$this->baseUrl}/waInstance{$this->instanceId}/sendMessage/{$this->token}";

        $payload = json_encode([
            'chatId'  => $chatId,
            'message' => $message,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("WhatsAppNotifier: cURL error for chat {$chatId}: {$curlError}");
            return false;
        }

        if ($httpCode !== 200) {
            error_log("WhatsAppNotifier: HTTP {$httpCode} for chat {$chatId}: {$response}");
            return false;
        }

        $data = json_decode($response, true);
        if (empty($data['idMessage'])) {
            error_log("WhatsAppNotifier: Unexpected response for chat {$chatId}: {$response}");
            return false;
        }

        return true;
    }

    /**
     * Build a plain-text WhatsApp message from feed items
     */
    private function buildMessage(array $allItems): string
    {
        $totalItems = array_sum(array_map('count', $allItems));
        $date = date('d.m.Y H:i');

        $lines = [];
        $lines[] = "📰 *RSS Feed Update* — {$date}";
        $lines[] = "_" . $totalItems . " new article(s) from " . count($allItems) . " source(s)_";
        $lines[] = '';

        foreach ($allItems as $feedName => $items) {
            $lines[] = "━━━━━━━━━━━━━━━━━━";
            $lines[] = "*" . $feedName . "* (" . count($items) . " new)";
            $lines[] = '';

            foreach ($items as $item) {
                $title = strip_tags($item['title']);
                // Truncate long titles
                if (strlen($title) > 120) {
                    $title = substr($title, 0, 117) . '...';
                }

                $lines[] = "• *{$title}*";
                $lines[] = "  📅 {$item['published']}";
                $lines[] = "  🔗 {$item['link']}";
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }
}
