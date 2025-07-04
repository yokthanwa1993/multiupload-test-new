<?php
// Fix permissions script for uploads directory
echo "=== Checking File Permissions ===\n";

$uploadDir = __DIR__ . '/uploads/';
$credentialsDir = __DIR__ . '/credentials/';

// Check if directories exist
if (!is_dir($uploadDir)) {
    echo "Creating uploads directory...\n";
    if (mkdir($uploadDir, 0755, true)) {
        echo "✓ uploads directory created\n";
    } else {
        echo "✗ Failed to create uploads directory\n";
    }
} else {
    echo "✓ uploads directory exists\n";
}

if (!is_dir($credentialsDir)) {
    echo "Creating credentials directory...\n";
    if (mkdir($credentialsDir, 0755, true)) {
        echo "✓ credentials directory created\n";
    } else {
        echo "✗ Failed to create credentials directory\n";
    }
} else {
    echo "✓ credentials directory exists\n";
}

// Check permissions
echo "\n=== Current Permissions ===\n";
echo "uploads directory: " . substr(sprintf('%o', fileperms($uploadDir)), -4) . "\n";
echo "credentials directory: " . substr(sprintf('%o', fileperms($credentialsDir)), -4) . "\n";

// Check if writable
echo "\n=== Write Permissions ===\n";
echo "uploads writable: " . (is_writable($uploadDir) ? "Yes" : "No") . "\n";
echo "credentials writable: " . (is_writable($credentialsDir) ? "Yes" : "No") . "\n";

// Try to fix permissions
echo "\n=== Fixing Permissions ===\n";
if (chmod($uploadDir, 0755)) {
    echo "✓ uploads permissions fixed\n";
} else {
    echo "✗ Failed to fix uploads permissions\n";
}

if (chmod($credentialsDir, 0755)) {
    echo "✓ credentials permissions fixed\n";
} else {
    echo "✗ Failed to fix credentials permissions\n";
}

// Test file creation
echo "\n=== Testing File Creation ===\n";
$testFile = $uploadDir . 'test_' . time() . '.txt';
if (file_put_contents($testFile, 'test content')) {
    echo "✓ Can create files in uploads directory\n";
    unlink($testFile); // Clean up
} else {
    echo "✗ Cannot create files in uploads directory\n";
}

echo "\n=== System Information ===\n";
echo "PHP User: " . get_current_user() . "\n";
echo "PHP Process ID: " . getmypid() . "\n";
echo "Web Server User: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'Unknown') . "\n";

echo "\nDone!\n";
?> 