<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

ini_set('upload_max_filesize', '120M');   // maximum size of a single file
ini_set('post_max_size', '130M');   // total size of the POST body
ini_set('memory_limit', '256M');   // optional: helps with large multipart parsing

$UPLOAD_DIR = __DIR__ . '/uploads';
$MAX_SIZE = 120 * 1024 * 1024; // 120MiB per file (adjust as you wish)
$OPEN_FILE_TYPES = ['mp4','mp3','png','jpg','jpeg','gif'];

// Ensure the upload folder exists
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
}

$files = [];
foreach ((is_dir($UPLOAD_DIR) ? scandir($UPLOAD_DIR) : []) as $entry) {
    if ($entry === '.' || $entry === '..')
        continue;

    $fullPath = $UPLOAD_DIR . '/' . $entry;

    if (!is_file($fullPath))
        continue;

    $files[] = [
        'name' => $entry,
        'size' => filesize($fullPath),
        'uploaded' => filemtime($fullPath),   // timestamp of last modification (upload time)
        'created' => filectime($fullPath),   // inode creation time (may equal uploaded on ext4)
    ];
}

// Sort newest first
usort($files, fn($a, $b) => $b['uploaded'] <=> $a['uploaded']);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PirateBox - Offline File Share</title>
    <link rel="stylesheet" href="assets/styles.css">
    <script src="assets/scripts.js"></script>
</head>

<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <?php if (!empty($msg)): ?>
        <p><strong><?= htmlspecialchars($msg) ?></strong></p>
    <?php endif; ?>

    <form action="/upload.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <label>Select a file (max <?= $MAX_SIZE / 1024 / 1024 ?>MiB):
            <input type="file" name="file" required data-max-size="<?= $MAX_SIZE ?>">
        </label>
        <button type="submit">Upload</button>
    </form>

    <?php if (empty($files)): ?>
        <p style="text-align:center; color: #606085;">No files uploaded yet.</p>
    <?php else: ?>
        <h2>Available files</h2>
        <div class="search-container">
            <input type="text" id="fileSearch" placeholder="Search files..." aria-label="Search files">
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Size</th>
                        <th>Uploaded</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $f): ?>
                        <?php 
                        $download = "";
                        if (!in_array(pathinfo(rawurlencode($f['name']), PATHINFO_EXTENSION), $OPEN_FILE_TYPES)) {
                            $download = "download";
                        }    
                        ?>
                        <tr>
                            <td><a href="uploads/<?= rawurlencode($f['name']) ?>" <?= $download ?>><?= htmlspecialchars($f['name']) ?></a></td>
                            <td><?= round($f['size'] / 1024, 1) ?>KB</td>
                            <td><span class="file-timestamp" data-timestamp="<?= $f['uploaded'] ?>"></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</body>

</html>