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

// Ensure the upload folder exists
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
}

$files = [];
foreach (scandir($UPLOAD_DIR) as $entry) {
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
    <link rel="stylesheet" href="styles.css">
    <script>
        const MAX_FILE_SIZE = <?= $MAX_SIZE ?>;

        document.addEventListener('DOMContentLoaded', function() {
            const uploadForm = document.querySelector('form');
            if (uploadForm) {
                uploadForm.addEventListener('submit', function(e) {
                    const btn = uploadForm.querySelector('button');
                    const fileLabel = uploadForm.querySelector('label');
                    const fileInput = uploadForm.querySelector('input[name="file"]');

                    if (fileInput && fileInput.files.length > 0) {
                        if (fileInput.files[0].size > MAX_FILE_SIZE) {
                            e.preventDefault();
                            alert('File is too large. Maximum size is ' + (MAX_FILE_SIZE / 1024 / 1024) + 'MiB.');
                            return;
                        }
                    }

                    btn.disabled = true;
                    btn.textContent = 'UPLOADING...';
                    btn.classList.add('upload-animation');
                    if (fileInput) {
                        fileInput.style.pointerEvents = 'none';
                        fileInput.style.opacity = '0.5';
                        fileInput.style.display = 'none';
                        fileLabel.style.display = 'none';
                    }
                });
            }

            const searchInput = document.getElementById('fileSearch');
            if (!searchInput) return;

            searchInput.addEventListener('keyup', function() {
                const filter = searchInput.value.toLowerCase();
                const rows = document.querySelectorAll('table tbody tr');

                rows.forEach(row => {
                    // Filter only if 3 or more characters, otherwise show all
                    if (filter.length >= 3 && !row.textContent.toLowerCase().includes(filter)) {
                        row.style.display = "none";
                    } else {
                        row.style.display = "";
                    }
                });
            });
        });
    </script>
</head>

<body>
    <a href="messages.php" class="top-right-link">Messages</a>
    <img src="500px-PirateBox-logo.svg.png" alt="PirateBox Logo" class="logo">
    <h1>PirateBox - Offline File Share</h1>

    <h2 style="text-align:center;"><a href="http://10.0.0.1/">10.0.0.1</a></h2>

    <?php if (!empty($msg)): ?>
        <p><strong><?= htmlspecialchars($msg) ?></strong></p>
    <?php endif; ?>

    <form action="/upload.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <label>Select a file (max <?= $MAX_SIZE / 1024 / 1024 ?>MiB):
            <input type="file" name="file" required>
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
                        <tr>
                            <td><a href="uploads/<?= rawurlencode($f['name']) ?>"><?= htmlspecialchars($f['name']) ?></a></td>
                            <td><?= round($f['size'] / 1024, 1) ?>KB</td>
                            <td><?= date('Y-m-d H:i', $f['uploaded']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <footer><p style="text-align:center;margin-top: 30px;"><small class="muted">Made with 🖤 by <a href="https://github.com/teklynk/piratebox" target="_blank">Teklynk</a></small></p></footer>
</body>

</html>