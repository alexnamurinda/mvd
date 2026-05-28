<?php
// One-time setup: creates required upload directories
// DELETE THIS FILE after running it once.

$dirs = [
    __DIR__ . '/uploads',
    __DIR__ . '/uploads/income_attachments',
];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        echo "✔ Already exists: $dir<br>";
    } elseif (mkdir($dir, 0775, true)) {
        echo "✔ Created: $dir<br>";
    } else {
        echo "✘ FAILED to create: $dir — set write permission on the parent folder.<br>";
    }
}

echo "<br><strong>Done. Delete this file from your server now.</strong>";
