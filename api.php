<?php
/**
 * RSS Feed Manager - REST API
 *
 * Feed Endpoints:
 *   GET    /api.php?action=list          - List all feeds
 *   POST   /api.php?action=add           - Add a new feed (name, url)
 *   POST   /api.php?action=remove        - Remove a feed (id)
 *   POST   /api.php?action=toggle        - Toggle feed active/inactive (id)
 *   POST   /api.php?action=check         - Check all feeds now
 *
 * Email Endpoints:
 *   GET    /api.php?action=emails             - List all email recipients with subscriptions
 *   POST   /api.php?action=add_email          - Add email recipient (email)
 *   POST   /api.php?action=remove_email       - Remove email recipient (email)
 *   POST   /api.php?action=update_subscription - Update email subscription (email, subscribe_all, feeds[])
 *
 * WhatsApp Endpoints:
 *   GET    /api.php?action=whatsapp_channels           - List all WhatsApp channels
 *   POST   /api.php?action=add_whatsapp_channel        - Add channel (chat_id, label)
 *   POST   /api.php?action=remove_whatsapp_channel     - Remove channel (chat_id)
 *   POST   /api.php?action=update_whatsapp_subscription - Update subscription (chat_id, subscribe_all, feeds[])
 *   GET    /api.php?action=whatsapp_status             - Check if Green API credentials are configured
 *
 * Auth Endpoints:
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
require_once __DIR__ . '/classes/WhatsAppNotifier.php';

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

    // Email management
    case 'emails':
        requireAuth();
        $emails = $manager->getEmails();
        jsonResponse([
            'success' => true,
            'emails' => $emails,
            'count' => count($emails),
        ]);
        break;

    case 'add_email':
        requireAuth();
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            jsonResponse(['error' => 'Email is required'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Invalid email format'], 400);
        }

        if ($manager->addEmail($email)) {
            jsonResponse(['success' => true, 'message' => 'Email added']);
        } else {
            jsonResponse(['error' => 'Email already exists or invalid'], 400);
        }
        break;

    case 'remove_email':
        requireAuth();
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            jsonResponse(['error' => 'Email is required'], 400);
        }

        if ($manager->removeEmail($email)) {
            jsonResponse(['success' => true, 'message' => 'Email removed']);
        } else {
            jsonResponse(['error' => 'Email not found'], 404);
        }
        break;

    case 'update_subscription':
        requireAuth();
        $email = trim($_POST['email'] ?? '');
        $subscribeAll = ($_POST['subscribe_all'] ?? 'true') === 'true';
        $feeds = isset($_POST['feeds']) ? json_decode($_POST['feeds'], true) : [];

        if (empty($email)) {
            jsonResponse(['error' => 'Email is required'], 400);
        }

        if (!is_array($feeds)) {
            $feeds = [];
        }

        if ($manager->updateEmailSubscription($email, $subscribeAll, $feeds)) {
            jsonResponse(['success' => true, 'message' => 'Subscription updated']);
        } else {
            jsonResponse(['error' => 'Email not found'], 404);
        }
        break;

    // WhatsApp management
    case 'whatsapp_status':
        requireAuth();
        $notifier = new WhatsAppNotifier($config);
        jsonResponse([
            'success'    => true,
            'configured' => $notifier->isConfigured(),
        ]);
        break;

    case 'whatsapp_channels':
        requireAuth();
        $channels = $manager->getWhatsAppChannels();
        jsonResponse([
            'success'  => true,
            'channels' => $channels,
            'count'    => count($channels),
        ]);
        break;

    case 'add_whatsapp_channel':
        requireAuth();
        $chatId = trim($_POST['chat_id'] ?? '');
        $label  = trim($_POST['label'] ?? '');

        if (empty($chatId) || empty($label)) {
            jsonResponse(['error' => 'chat_id and label are required'], 400);
        }

        if ($manager->addWhatsAppChannel($chatId, $label)) {
            jsonResponse(['success' => true, 'message' => 'WhatsApp channel added']);
        } else {
            jsonResponse(['error' => 'Channel already exists or invalid input'], 400);
        }
        break;

    case 'remove_whatsapp_channel':
        requireAuth();
        $chatId = trim($_POST['chat_id'] ?? '');

        if (empty($chatId)) {
            jsonResponse(['error' => 'chat_id is required'], 400);
        }

        if ($manager->removeWhatsAppChannel($chatId)) {
            jsonResponse(['success' => true, 'message' => 'WhatsApp channel removed']);
        } else {
            jsonResponse(['error' => 'Channel not found'], 404);
        }
        break;

    case 'update_whatsapp_subscription':
        requireAuth();
        $chatId      = trim($_POST['chat_id'] ?? '');
        $subscribeAll = ($_POST['subscribe_all'] ?? 'true') === 'true';
        $feeds       = isset($_POST['feeds']) ? json_decode($_POST['feeds'], true) : [];

        if (empty($chatId)) {
            jsonResponse(['error' => 'chat_id is required'], 400);
        }

        if (!is_array($feeds)) {
            $feeds = [];
        }

        if ($manager->updateWhatsAppSubscription($chatId, $subscribeAll, $feeds)) {
            jsonResponse(['success' => true, 'message' => 'WhatsApp subscription updated']);
        } else {
            jsonResponse(['error' => 'Channel not found'], 404);
        }
        break;

    default:
        jsonResponse(['error' => 'Invalid action', 'available_actions' => [
            'status', 'login', 'logout', 'list', 'add', 'remove', 'toggle', 'check',
            'emails', 'add_email', 'remove_email', 'update_subscription',
            'whatsapp_status', 'whatsapp_channels', 'add_whatsapp_channel',
            'remove_whatsapp_channel', 'update_whatsapp_subscription',
        ]], 400);
}
