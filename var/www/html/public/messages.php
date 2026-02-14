<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$DATA_FILE = __DIR__ . '/../data/messages.json';
$messages = [];

if (file_exists($DATA_FILE)) {
    $json = file_get_contents($DATA_FILE);
    if ($json !== false) {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $messages = $decoded;
        }
    }
}

if (isset($_GET['fetch'])) {
    header('Content-Type: application/json');
    echo json_encode($messages);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        die('Invalid CSRF token.');
    }

    $name = trim(strip_tags($_POST['name'] ?? ''));
    $content = trim(strip_tags($_POST['message'] ?? ''));

    if ($name === '') {
        $name = 'Anonymous';
    }

    if ($content !== '') {
        $next_id = (!empty($messages) && isset($messages[0]['id'])) ? $messages[0]['id'] + 1 : 0;

        $newMessage = [
            'id' => $next_id,
            'name' => $name,
            'message' => $content,
            'timestamp' => time()
        ];

        // Add to the beginning of the array (Newest first)
        array_unshift($messages, $newMessage);

        // Save to file
        file_put_contents($DATA_FILE, json_encode($messages, JSON_PRETTY_PRINT));

        // Redirect to avoid resubmission
        header('Location: messages.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PirateBox - Messages</title>
    <link rel="stylesheet" href="assets/styles.css">
    <script src="assets/scripts.js"></script>
</head>

<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <form id="message-form" action="messages.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <label>Name:
            <input type="text" name="name" placeholder="Anonymous" maxlength="32">
        </label>
        <label>Message:
            <textarea name="message" required rows="3" placeholder="Write a message..." maxlength="2000"></textarea>
        </label>
        <button type="submit">Post Message</button>
        <div class="char-counter">
            <span id="char-count">0 / 2000</span>
        </div>
    </form>

    <div class="message-container">
        <?php if (empty($messages)): ?>
            <p style="text-align:center; color: #606085;">No messages yet. Be the first!</p>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message-card">
                    <div class="message-header">
                        <span class="message-name"><?= htmlspecialchars($msg['name']) ?></span>
                        <span class="message-time" data-timestamp="<?= $msg['timestamp'] ?>"></span>
                    </div>
                    <div class="message-body"><?= htmlspecialchars($msg['message']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
</body>

</html>