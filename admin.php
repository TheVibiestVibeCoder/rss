<?php
require_once 'config.php';
session_start();

// Simple authentication
$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

if (isset($_POST['login'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['logged_in'] = true;
        $logged_in = true;
    } else {
        $error = "Falsches Passwort!";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (!$logged_in) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>RSS Feed Manager - Login</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-box {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                width: 100%;
                max-width: 400px;
            }
            h1 {
                color: #333;
                margin-bottom: 30px;
                text-align: center;
            }
            .form-group {
                margin-bottom: 20px;
            }
            label {
                display: block;
                margin-bottom: 5px;
                color: #555;
                font-weight: 500;
            }
            input[type="password"] {
                width: 100%;
                padding: 12px;
                border: 2px solid #e0e0e0;
                border-radius: 5px;
                font-size: 16px;
                transition: border-color 0.3s;
            }
            input[type="password"]:focus {
                outline: none;
                border-color: #667eea;
            }
            button {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s;
            }
            button:hover {
                transform: translateY(-2px);
            }
            .error {
                background: #ffebee;
                color: #c62828;
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 20px;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>🔐 RSS Feed Manager</h1>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Passwort:</label>
                    <input type="password" name="password" required autofocus>
                </div>
                <button type="submit" name="login">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle AJAX requests
$manager = new FeedManager();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_feed') {
        $id = $manager->addFeed($_POST['name'], $_POST['url']);
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }
    
    if ($_POST['action'] === 'remove_feed') {
        $result = $manager->removeFeed($_POST['id']);
        echo json_encode(['success' => $result]);
        exit;
    }
    
    if ($_POST['action'] === 'toggle_feed') {
        $result = $manager->toggleFeed($_POST['id']);
        echo json_encode(['success' => $result]);
        exit;
    }
    
    if ($_POST['action'] === 'test_run') {
        require_once 'parser.php';
        $parser = new RSSParser();
        $count = $parser->checkAllFeeds();
        echo json_encode(['success' => true, 'count' => $count]);
        exit;
    }
}

$feeds = $manager->getFeeds();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSS Feed Manager</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            font-size: 14px;
        }
        .logout-btn {
            float: right;
            padding: 8px 16px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }
        .logout-btn:hover {
            background: #c82333;
        }
        .add-feed-box {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 2fr auto;
            gap: 15px;
            align-items: end;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        label {
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        input[type="text"], input[type="url"] {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        button, .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .feeds-list {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .feeds-list h2 {
            margin-bottom: 20px;
            color: #333;
        }
        .feed-item {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.2s;
        }
        .feed-item:hover {
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
        }
        .feed-item.inactive {
            opacity: 0.6;
            background: #f9f9f9;
        }
        .feed-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .feed-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        .feed-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .feed-status.active {
            background: #d4edda;
            color: #155724;
        }
        .feed-status.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .feed-url {
            color: #666;
            font-size: 13px;
            margin-bottom: 10px;
            word-break: break-all;
        }
        .feed-meta {
            color: #999;
            font-size: 12px;
            margin-bottom: 15px;
        }
        .feed-actions {
            display: flex;
            gap: 10px;
        }
        .feed-actions button {
            padding: 6px 12px;
            font-size: 13px;
        }
        .test-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            .feed-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .feed-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="?logout" class="logout-btn">Logout</a>
            <h1>📰 RSS Feed Manager</h1>
            <div class="subtitle">Verwalte deine RSS Feed Subscriptions</div>
        </div>

        <div class="test-section">
            <h3 style="margin-bottom: 15px;">🧪 Test Run</h3>
            <button class="btn btn-secondary" onclick="testRun()">Feeds jetzt checken & Email senden</button>
            <div id="test-result" style="margin-top: 15px;"></div>
        </div>

        <div class="add-feed-box">
            <h2 style="margin-bottom: 20px;">➕ Neuen Feed hinzufügen</h2>
            <form id="add-feed-form" onsubmit="return addFeed(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label>Feed Name:</label>
                        <input type="text" id="feed-name" required placeholder="z.B. FIMI Alerts">
                    </div>
                    <div class="form-group">
                        <label>RSS Feed URL:</label>
                        <input type="url" id="feed-url" required placeholder="https://...">
                    </div>
                    <button type="submit" class="btn btn-primary">Hinzufügen</button>
                </div>
            </form>
            <div id="add-result" style="margin-top: 15px;"></div>
        </div>

        <div class="feeds-list">
            <h2>📋 Deine Feeds (<?php echo count($feeds); ?>)</h2>
            <div id="feeds-container">
                <?php if (empty($feeds)): ?>
                    <div class="empty-state">
                        <p>Noch keine Feeds hinzugefügt.</p>
                        <p>Füge oben deinen ersten RSS Feed hinzu!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($feeds as $id => $feed): ?>
                        <div class="feed-item <?php echo $feed['active'] ? '' : 'inactive'; ?>" data-id="<?php echo $id; ?>">
                            <div class="feed-header">
                                <div class="feed-name"><?php echo htmlspecialchars($feed['name']); ?></div>
                                <span class="feed-status <?php echo $feed['active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $feed['active'] ? '✓ Aktiv' : '✗ Pausiert'; ?>
                                </span>
                            </div>
                            <div class="feed-url"><?php echo htmlspecialchars($feed['url']); ?></div>
                            <div class="feed-meta">Hinzugefügt am: <?php echo $feed['added']; ?></div>
                            <div class="feed-actions">
                                <button class="btn btn-secondary" onclick="toggleFeed('<?php echo $id; ?>')">
                                    <?php echo $feed['active'] ? 'Pausieren' : 'Aktivieren'; ?>
                                </button>
                                <button class="btn btn-danger" onclick="removeFeed('<?php echo $id; ?>')">
                                    Löschen
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function addFeed(e) {
            e.preventDefault();
            const name = document.getElementById('feed-name').value;
            const url = document.getElementById('feed-url').value;
            
            fetch('admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add_feed&name=${encodeURIComponent(name)}&url=${encodeURIComponent(url)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showMessage('add-result', 'Feed erfolgreich hinzugefügt!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage('add-result', 'Fehler beim Hinzufügen', 'error');
                }
            });
            
            return false;
        }

        function removeFeed(id) {
            if (!confirm('Feed wirklich löschen?')) return;
            
            fetch('admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=remove_feed&id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }

        function toggleFeed(id) {
            fetch('admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=toggle_feed&id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }

        function testRun() {
            const resultDiv = document.getElementById('test-result');
            resultDiv.innerHTML = '<div style="color: #666;">⏳ Checking feeds...</div>';
            
            fetch('admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=test_run'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showMessage('test-result', `✓ Check abgeschlossen! ${data.count} Feed(s) mit neuen Artikeln gefunden und Email gesendet.`, 'success');
                } else {
                    showMessage('test-result', 'Fehler beim Checken', 'error');
                }
            });
        }

        function showMessage(elementId, message, type) {
            const elem = document.getElementById(elementId);
            elem.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => { elem.innerHTML = ''; }, 3000);
        }
    </script>
</body>
</html>