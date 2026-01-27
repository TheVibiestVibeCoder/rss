<?php
/**
 * RSS Feed Manager - REST API
 *
 * Endpoints:
 *   GET    /api.php?action=list          - List all feeds
 *   POST   /api.php?action=add           - Add a new feed (name, url)
 *   POST   /api.php?action=remove        - Remove a feed (id)
 *   POST   /api.php?action=toggle        - Toggle feed active/inactive (id)
 *   POST   /api.php?action=update        - Update feed (id, name, url)
 *   POST   /api.php?action=check         - Check all feeds now
 *   POST   /api.php?action=login         - Authenticate (password)
 *   GET    /api.php?action=logout        - Logout
 *   GET    /api.php?action=status        - Check login status
 */

session_start();
header('Content-Type: application/json');

// Load configuration
$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['timezone']);

// Load classes
require_once __DIR__ . '/classes/FeedManager.php';
require_once __DIR__ . '/classes/RSSParser.php';

// Initialize
$manager = new FeedManager($config);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Helper function for JSON responses
function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Check if user is logged in
function isLoggedIn(): bool
{
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

// Require authentication for protected actions
function requireAuth(): void
{
    if (!isLoggedIn()) {
        jsonResponse(['error' => 'Authentication required'], 401);
    }
}

// Route the request
switch ($action) {
    case 'status':
        jsonResponse([
            'authenticated' => isLoggedIn(),
            'session_id' => session_id(),
        ]);
        break;

    case 'login':
        $password = $_POST['password'] ?? '';
        if ($password === $config['admin_password']) {
            $_SESSION['authenticated'] = true;
            jsonResponse(['success' => true, 'message' => 'Login successful']);
        } else {
            jsonResponse(['error' => 'Invalid password'], 401);
        }
        break;

    case 'logout':
        session_destroy();
        jsonResponse(['success' => true, 'message' => 'Logged out']);
        break;

    case 'list':
        requireAuth();
        $feeds = $manager->getAll();
        jsonResponse([
            'success' => true,
            'feeds' => $feeds,
            'count' => count($feeds),
        ]);
        break;

    case 'add':
        requireAuth();
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');

        if (empty($name) || empty($url)) {
            jsonResponse(['error' => 'Name and URL are required'], 400);
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            jsonResponse(['error' => 'Invalid URL format'], 400);
        }

        $id = $manager->add($name, $url);
        jsonResponse([
            'success' => true,
            'id' => $id,
            'message' => 'Feed added successfully',
        ]);
        break;

    case 'remove':
        requireAuth();
        $id = $_POST['id'] ?? '';

        if (empty($id)) {
            jsonResponse(['error' => 'Feed ID is required'], 400);
        }

        if ($manager->remove($id)) {
            jsonResponse(['success' => true, 'message' => 'Feed removed']);
        } else {
            jsonResponse(['error' => 'Feed not found'], 404);
        }
        break;

    case 'toggle':
        requireAuth();
        $id = $_POST['id'] ?? '';

        if (empty($id)) {
            jsonResponse(['error' => 'Feed ID is required'], 400);
        }

        if ($manager->toggle($id)) {
            jsonResponse(['success' => true, 'message' => 'Feed toggled']);
        } else {
            jsonResponse(['error' => 'Feed not found'], 404);
        }
        break;

    case 'update':
        requireAuth();
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');

        if (empty($id) || empty($name) || empty($url)) {
            jsonResponse(['error' => 'ID, name, and URL are required'], 400);
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            jsonResponse(['error' => 'Invalid URL format'], 400);
        }

        if ($manager->update($id, $name, $url)) {
            jsonResponse(['success' => true, 'message' => 'Feed updated']);
        } else {
            jsonResponse(['error' => 'Feed not found'], 404);
        }
        break;

    case 'check':
        requireAuth();
        $parser = new RSSParser($manager, $config);
        $result = $parser->checkAllFeeds();
        jsonResponse([
            'success' => true,
            'result' => $result,
            'message' => "Checked {$result['checked']} feeds, found {$result['total_items']} new items",
        ]);
        break;

    default:
        jsonResponse(['error' => 'Invalid action', 'available_actions' => [
            'status', 'login', 'logout', 'list', 'add', 'remove', 'toggle', 'update', 'check'
        ]], 400);
}
