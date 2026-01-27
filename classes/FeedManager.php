<?php
/**
 * FeedManager - Handles all feed storage operations
 */
class FeedManager
{
    private string $feedsFile;
    private string $lastCheckFile;

    public function __construct(array $config)
    {
        $this->feedsFile = $config['feeds_file'];
        $this->lastCheckFile = $config['last_check_file'];
        $this->initFiles();
    }

    private function initFiles(): void
    {
        $dir = dirname($this->feedsFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (!file_exists($this->feedsFile)) {
            file_put_contents($this->feedsFile, json_encode([], JSON_PRETTY_PRINT));
        }
        if (!file_exists($this->lastCheckFile)) {
            file_put_contents($this->lastCheckFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    public function getAll(): array
    {
        $content = file_get_contents($this->feedsFile);
        return json_decode($content, true) ?: [];
    }

    public function get(string $id): ?array
    {
        $feeds = $this->getAll();
        return $feeds[$id] ?? null;
    }

    public function add(string $name, string $url): string
    {
        $feeds = $this->getAll();
        $id = uniqid('feed_');

        $feeds[$id] = [
            'name'   => trim($name),
            'url'    => trim($url),
            'added'  => date('Y-m-d H:i:s'),
            'active' => true,
        ];

        $this->saveFeeds($feeds);
        return $id;
    }

    public function remove(string $id): bool
    {
        $feeds = $this->getAll();
        if (!isset($feeds[$id])) {
            return false;
        }

        unset($feeds[$id]);
        $this->saveFeeds($feeds);
        $this->removeLastCheck($id);
        return true;
    }

    public function toggle(string $id): bool
    {
        $feeds = $this->getAll();
        if (!isset($feeds[$id])) {
            return false;
        }

        $feeds[$id]['active'] = !$feeds[$id]['active'];
        $this->saveFeeds($feeds);
        return true;
    }

    public function update(string $id, string $name, string $url): bool
    {
        $feeds = $this->getAll();
        if (!isset($feeds[$id])) {
            return false;
        }

        $feeds[$id]['name'] = trim($name);
        $feeds[$id]['url'] = trim($url);
        $this->saveFeeds($feeds);
        return true;
    }

    public function getLastCheck(string $feedId): int
    {
        $checks = $this->getAllLastChecks();
        return isset($checks[$feedId]) ? strtotime($checks[$feedId]) : 0;
    }

    public function updateLastCheck(string $feedId): void
    {
        $checks = $this->getAllLastChecks();
        $checks[$feedId] = date('c');
        file_put_contents($this->lastCheckFile, json_encode($checks, JSON_PRETTY_PRINT));
    }

    private function getAllLastChecks(): array
    {
        $content = file_get_contents($this->lastCheckFile);
        return json_decode($content, true) ?: [];
    }

    private function removeLastCheck(string $feedId): void
    {
        $checks = $this->getAllLastChecks();
        unset($checks[$feedId]);
        file_put_contents($this->lastCheckFile, json_encode($checks, JSON_PRETTY_PRINT));
    }

    private function saveFeeds(array $feeds): void
    {
        file_put_contents($this->feedsFile, json_encode($feeds, JSON_PRETTY_PRINT));
    }
}
