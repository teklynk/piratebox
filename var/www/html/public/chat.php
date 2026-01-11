<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$DATA_FILE = __DIR__ . '/../data/chat.json';
$chat = [];
$chat_size = 50;

// Read file (Similar to messages.php)
if (file_exists($DATA_FILE)) {
    $json = file_get_contents($DATA_FILE);
    if ($json !== false) {
        $chat = json_decode($json, true) ?? [];
    }
}

if (isset($_GET['fetch'])) {
    header('Content-Type: application/json');
    echo json_encode($chat);
    exit;
}

if (isset($_POST["content"]) and isset($_POST["name"])) {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }

    $name = trim(strip_tags($_POST["name"]));
    $content = trim(strip_tags($_POST["content"]));

    if ($name === '') {
        $name = 'Anonymous';
    }

    if ($content !== '') {
        $next_id = (count($chat) > 0) ? $chat[count($chat) - 1]["id"] + 1 : 0;
        $chat[] = [
            "id" => $next_id,
            "time" => time(),
            "name" => $name,
            "content" => $content
        ];

        if (count($chat) > $chat_size) {
            $chat = array_slice($chat, count($chat) - $chat_size);
        }

        file_put_contents($DATA_FILE, json_encode($chat, JSON_PRETTY_PRINT));
    }

    exit();
}

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PirateBox - Live Chat</title>
    <link rel="stylesheet" href="assets/styles.css">
    <script src="assets/scripts.js"></script>
</head>

<body class="chat-page">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <ul id="chat" data-last-message-id="<?= !empty($chat) ? $chat[count($chat) - 1]['id'] : -1 ?>">
        <?php foreach ($chat as $msg): ?>
            <li>
                <small>
                    <span class="chat-name"><?= htmlspecialchars($msg['name']) ?></span> (<span class="chat-timestamp" data-timestamp="<?= $msg['time'] ?>"></span>):
                </small>
                <span><?= htmlspecialchars($msg['content']) ?></span>
            </li>
        <?php endforeach; ?>
        <template>
            <li class="pending">
                <small>…</small>
                <span>…</span>
            </li>
        </template>
    </ul>

    <form id="chat-form" method=post
        action="<?= htmlentities($_SERVER["PHP_SELF"], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, "UTF-8") ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="input-group">
            <input type=text name=name placeholder="Anonymous" maxlength="32">
            <input type=text name=content placeholder="Message" maxlength="255" autofocus>
            <button>Send</button>
        </div>
        <div class="char-counter">
            <span id="char-count">0 / 255</span>
        </div>
    </form>

</body>

</html>