<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$DATA_FILE = __DIR__ . '/../data/messages.json';
$CHAT_KEY_FILE = '/etc/piratebox/chat.key';
$messages = [];
$message_size = 100;

$key_hex = @file_get_contents($CHAT_KEY_FILE);
$encryption_key = $key_hex === false ? false : hex2bin(trim($key_hex));
if (!is_string($encryption_key) || strlen($encryption_key) !== 32) {
    http_response_code(500);
    exit('Messages encryption key is missing or invalid.');
}

if (file_exists($DATA_FILE)) {
    $stored_json = @file_get_contents($DATA_FILE);
    $stored = $stored_json === false ? null : json_decode($stored_json, true);
    if (!is_array($stored)
        || ($stored['format'] ?? null) !== 'piratebox-messages-v1'
        || !isset($stored['nonce'], $stored['tag'], $stored['ciphertext'])
        || !is_string($stored['nonce'])
        || !is_string($stored['tag'])
        || !is_string($stored['ciphertext'])) {
        http_response_code(500);
        exit('Messages data is not encrypted or has an invalid format. Remove it to start fresh.');
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
        exit('Messages data could not be decrypted.');
    }
    $messages = $decoded;
}

// Used by javascript to fetch json data using ?fetch=1
if (isset($_GET['fetch'])) {
    header('Content-Type: application/json');
    echo json_encode($messages);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["message"]) && isset($_POST["name"])) {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        exit('Invalid CSRF token.');
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

        if (count($messages) > $message_size) {
            $messages = array_slice($messages, 0, $message_size);
        }

        $nonce = random_bytes(12);
        $plaintext = json_encode($messages, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        $ciphertext = $plaintext === false
            ? false
            : openssl_encrypt($plaintext, 'aes-256-gcm', $encryption_key, OPENSSL_RAW_DATA, $nonce, $tag);

        if ($ciphertext === false) {
            http_response_code(500);
            exit('Unable to encrypt messages data.');
        }

        $encrypted_json = json_encode([
            'format' => 'piratebox-messages-v1',
            'nonce' => base64_encode($nonce),
            'tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
        ], JSON_PRETTY_PRINT);

        if ($encrypted_json === false || file_put_contents($DATA_FILE, $encrypted_json, LOCK_EX) === false) {
            http_response_code(500);
            exit('Unable to save messages data.');
        }

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