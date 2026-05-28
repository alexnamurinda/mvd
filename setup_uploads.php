<?php
// One-time setup: creates and fixes permissions on upload directories
// DELETE THIS FILE after running it once.

$dirs = [
    __DIR__ . '/uploads',
    __DIR__ . '/uploads/income_attachments',
];

foreach ($dirs as $dir) {
    // Create if missing
    if (!is_dir($dir)) {
        if (mkdir($dir, 0775, true)) {
            echo "✔ Created: $dir<br>";
        } else {
            echo "✘ Could not create: $dir<br>";
            continue;
        }
    } else {
        echo "✔ Exists: $dir<br>";
    }

    // Try to set writable permissions
    if (chmod($dir, 0775)) {
        echo "&nbsp;&nbsp;✔ chmod 775 OK<br>";
    } else {
        echo "&nbsp;&nbsp;⚠ chmod failed — trying 0777...<br>";
        if (chmod($dir, 0777)) {
            echo "&nbsp;&nbsp;✔ chmod 777 OK (consider tightening later)<br>";
        } else {
            echo "&nbsp;&nbsp;✘ chmod also failed. PHP runs as: <strong>" . get_current_user() . "</strong>, dir owner may differ.<br>";
        }
    }

    echo "&nbsp;&nbsp;Writable now? <strong>" . (is_writable($dir) ? 'YES ✔' : 'NO ✘') . "</strong><br><br>";
}

echo "<hr>";
echo "PHP running as user: <strong>" . shell_exec('whoami') . "</strong><br>";
echo "App directory: <strong>" . __DIR__ . "</strong><br>";
echo "<br><strong style='color:red'>Delete this file from your server immediately after reading the output above.</strong>";
