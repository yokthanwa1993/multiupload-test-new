<?php
echo "<h2>Debug Information</h2>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>PHP version: " . phpversion() . "</p>";
echo "<p>Current directory: " . __DIR__ . "</p>";

echo "<h3>Directory Listing:</h3>";
$files = scandir(__DIR__);
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        echo "<p>$file</p>";
    }
}

echo "<h3>Uploads Directory:</h3>";
$uploadDir = __DIR__ . '/uploads/';
if (is_dir($uploadDir)) {
    echo "<p>✓ uploads directory exists</p>";
    echo "<p>Permissions: " . substr(sprintf('%o', fileperms($uploadDir)), -4) . "</p>";
    echo "<p>Writable: " . (is_writable($uploadDir) ? "Yes" : "No") . "</p>";
} else {
    echo "<p>✗ uploads directory does not exist</p>";
}

echo "<h3>Credentials Directory:</h3>";
$credDir = __DIR__ . '/credentials/';
if (is_dir($credDir)) {
    echo "<p>✓ credentials directory exists</p>";
    echo "<p>Permissions: " . substr(sprintf('%o', fileperms($credDir)), -4) . "</p>";
    echo "<p>Writable: " . (is_writable($credDir) ? "Yes" : "No") . "</p>";
} else {
    echo "<p>✗ credentials directory does not exist</p>";
}

echo "<h3>System Info:</h3>";
echo "<p>User: " . get_current_user() . "</p>";
echo "<p>Process ID: " . getmypid() . "</p>";
if (function_exists('posix_getpwuid')) {
    echo "<p>Web Server User: " . posix_getpwuid(posix_geteuid())['name'] . "</p>";
}
?> 