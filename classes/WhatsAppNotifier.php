<?php
/**
 * WhatsAppNotifier - Sends RSS feed updates to WhatsApp groups/channels
 *
 * Uses a self-hosted whatsapp-web.js bridge (whatsapp-bridge/server.js).
 * No third-party services or API fees required.
 *
 * Setup:
 *   1. cd whatsapp-bridge && npm install
 *   2. node server.js          ← scan the QR code that appears in the terminal
 *   3. Add WA_BRIDGE_URL and WA_BRIDGE_TOKEN to your .env file
 *   4. Add WhatsApp group/channel IDs via the web UI
 *
 * Chat ID formats:
 *   - WhatsApp Group:     1234567890-1234567890@g.us
 *   - Individual number:  491234567890@c.us  (country code + number, no +)
 *
 * To find a group's chat ID:
 *   Run this once in the bridge directory:
 *     node -e "
 *       const {Client,LocalAuth}=require('whatsapp-web.js');
 *       const c=new Client({authStrategy:new LocalAuth({dataPath:'./session'})});
 *       c.on('ready',async()=>{const chats=await c.getChats();
 *         chats.filter(ch=>ch.isGroup).forEach(ch=>console.log(ch.id._serialized,ch.name));
 *         c.destroy();});
 *       c.initialize();
 *     "
 */
class WhatsAppNotifier
{
    private string $bridgeUrl;
    private string $bridgeToken;

    public function __construct(array $config)
    {
        $this->bridgeUrl   = rtrim($config['whatsapp']['bridge_url'] ?? '', '/');
        $this->bridgeToken = $config['whatsapp']['bridge_token'] ?? '';
    }

    /**
     * Returns true if the bridge URL is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->bridgeUrl);
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
     * Send a single message to a WhatsApp chat via the local bridge
     */
    public function sendMessage(string $chatId, string $message): bool
    {
        $url     = "{$this->bridgeUrl}/send";
        $payload = json_encode(['chatId' => $chatId, 'message' => $message]);

        $headers = ['Content-Type: application/json'];
        if (!empty($this->bridgeToken)) {
            $headers[] = "x-auth-token: {$this->bridgeToken}";
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
        if (empty($data['success'])) {
            error_log("WhatsAppNotifier: Bridge error for chat {$chatId}: {$response}");
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
