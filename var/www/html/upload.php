<?php
declare(strict_types=1);
session_start();

// Raise the limits for this request only
ini_set('upload_max_filesize', '120M');   // maximum size of a single file
ini_set('post_max_size', '130M');   // total size of the POST body
ini_set('memory_limit', '256M');   // optional - helps with large multipart parsing

$UPLOAD_DIR = __DIR__ . '/uploads';
$MAX_SIZE = 130 * 1024 * 1024;   // 130MiB change as needed

// Make sure the uploads directory exists (same as index.php)
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    header('Location: /');
    exit;
}

// CSRF Protection
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    die('Invalid CSRF token.');
}

$err = $_FILES['file']['error'];
$tmp = $_FILES['file']['tmp_name'];
$name = basename($_FILES['file']['name']);

if ($err !== UPLOAD_ERR_OK) {
    $msg = "Upload error (code $err).";
} elseif ($_FILES['file']['size'] > $MAX_SIZE) {
    $msg = "File too big - limit is " . ($MAX_SIZE / 1024 / 1024) . "MiB.";
} else {

    // Security: Prevent PHP execution by renaming dangerous extensions
    if (preg_match('/\.(php|phtml|phar|pl|py|rb|cgi|sh|exe)$/i', $name)) {
        $name .= '.txt';
    }
    // Security: Prevent hidden system files (like .htaccess)
    if (str_starts_with($name, '.')) {
        $name = '_' . substr($name, 1);
    }
    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    $dest = $UPLOAD_DIR . '/' . $safe;

    $i = 1;
    while (file_exists($dest)) {
        $info = pathinfo($safe);
        $dest = $UPLOAD_DIR . '/' .
            $info['filename'] . '_' . $i .
            (isset($info['extension']) ? '.' . $info['extension'] : '');
        $i++;
    }

    if (move_uploaded_file($tmp, $dest)) {
        // SUCCESS: redirect back to the portal UI (PRG)
        header('Location: /');
        exit;
    } else {
        $msg = "Failed to move uploaded file.";
    }
}

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upload error</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body class="centered-page">
    <h1>Upload error</h1>
    <p><?= htmlspecialchars($msg) ?></p>
    <p><a href="/">Back to portal</a></p>
</body>

</html>