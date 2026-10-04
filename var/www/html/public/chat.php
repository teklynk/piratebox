<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$DATA_FILE = __DIR__ . '/../data/chat.json';
$CHAT_KEY_FILE = '/etc/piratebox/chat.key';
$chat = [];
$chat_size = 100;

$key_hex = @file_get_contents($CHAT_KEY_FILE);
$encryption_key = $key_hex === false ? false : hex2bin(trim($key_hex));
if (!is_string($encryption_key) || strlen($encryption_key) !== 32) {
    http_response_code(500);
    exit('Chat encryption key is missing or invalid.');
}

if (file_exists($DATA_FILE)) {
    $stored_json = @file_get_contents($DATA_FILE);
    $stored = $stored_json === false ? null : json_decode($stored_json, true);
    if (!is_array($stored)
        || ($stored['format'] ?? null) !== 'piratebox-chat-v1'
        || !isset($stored['nonce'], $stored['tag'], $stored['ciphertext'])
        || !is_string($stored['nonce'])
        || !is_string($stored['tag'])
        || !is_string($stored['ciphertext'])) {
        http_response_code(500);
        exit('Chat data is not encrypted or has an invalid format. Remove it to start a fresh chat.');
    }

    $nonce = base64_decode($stored['nonce'], true);
    $tag = base64_decode($stored['tag'], true);
    $ciphertext = base64_decode($stored['ciphertext'], true);
    $plaintext = ($nonce !== false && strlen($nonce) === 12
        && $tag !== false && strlen($tag) === 16 && $ciphertext !== false)
        ? openssl_decrypt($ciphertext, 'aes-256-gcm', $encryption_key, OPENSSL_RAW_DATA, $nonce, $tag)
        : false;
    $decoded = $plaintext === false ? null : json_decode($plaintext, true);
    if (!is_array($decoded)) {
        http_response_code(500);
        exit('Chat data could not be decrypted.');
    }
    $chat = $decoded;
}

// Used by javascript to fetch json data using ?fetch=1
if (isset($_GET['fetch'])) {
    header('Content-Type: application/json');
    echo json_encode($chat);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["message"]) && isset($_POST["name"])) {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }

    $name = trim(strip_tags($_POST['name'] ?? ''));
    $message = trim(strip_tags($_POST['message'] ?? ''));

    if ($name === '') {
        $name = 'Anonymous';
    }

    if ($message !== '') {
        $next_id = (count($chat) > 0) ? $chat[count($chat) - 1]["id"] + 1 : 0;

        $newChat = [
            "id" => $next_id,
            "name" => $name,
            "message" => $message,
            "timestamp" => time()
        ];

        // Add to the end of the array (Newest last)
        $chat[] = $newChat;

        if (count($chat) > $chat_size) {
            $chat = array_slice($chat, -$chat_size);
        }

        $nonce = random_bytes(12);
        $plaintext = json_encode($chat, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        $ciphertext = $plaintext === false
            ? false
            : openssl_encrypt($plaintext, 'aes-256-gcm', $encryption_key, OPENSSL_RAW_DATA, $nonce, $tag);

        if ($ciphertext === false) {
            http_response_code(500);
            exit('Unable to encrypt chat data.');
        }

        $encrypted_json = json_encode([
            'format' => 'piratebox-chat-v1',
            'nonce' => base64_encode($nonce),
            'tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
        ], JSON_PRETTY_PRINT);

        if ($encrypted_json === false || file_put_contents($DATA_FILE, $encrypted_json, LOCK_EX) === false) {
            http_response_code(500);
            exit('Unable to save chat data.');
        }
    }
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
                    <span class="chat-name"><?= htmlspecialchars($msg['name']) ?></span> (<span class="chat-timestamp" data-timestamp="<?= $msg['timestamp'] ?>"></span>):
                </small>
                <span><?= htmlspecialchars($msg['message']) ?></span>
            </li>
        <?php endforeach; ?>
        <template>
            <li class="pending">
                <small>…</small>
                <span>…</span>
            </li>
        </template>
    </ul>

    <form id="chat-form" method="post" action="chat.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="input-group">
            <input type="text" name="name" placeholder="Anonymous" maxlength="32">
            <input type="text" name="message" placeholder="Message" maxlength="2000" autofocus>
            <button type="submit">Send</button>
        </div>
        <div class="char-counter">
            <span id="char-count">0 / 2000</span>
        </div>
    </form>

</body>

</html>