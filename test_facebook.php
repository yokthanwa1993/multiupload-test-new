<?php
// Simple Facebook API test
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageId = '675135025677492';
$accessToken = 'EAAChZCKmUTDcBO4Vv1pFtfMQCehJiA73VA7u1i8le8PvnghlH1A9ejbsU6rL7FCZCcyZA9DusZADmHLdvCZAEeddtFUgK1EuiqvOZCnE4C6WaUQDUw35AzahShrcXGsgebUoZBa6U2gHRDqHZCVCadHM0xjZCztnbiTO2RYlHKHETNzuzgKBulLPt5LouwwTeVe9FKZCSBkEwxjFBMXmqXn7ZCR';

echo "<h1>Facebook API Test</h1>";

// Test 1: Check page info
echo "<h2>Test 1: Page Info</h2>";
$url = "https://graph.facebook.com/v19.0/$pageId?access_token=$accessToken";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
echo "<p><strong>Response:</strong></p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Test 2: Try to start video upload
echo "<h2>Test 2: Video Upload Init</h2>";
$initUrl = "https://graph.facebook.com/v19.0/$pageId/video_reels?upload_phase=start&access_token=$accessToken";
$ch = curl_init($initUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
echo "<p><strong>Response:</strong></p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

if ($httpCode !== 200) {
    $errorData = json_decode($response, true);
    if (isset($errorData['error'])) {
        echo "<h3 style='color: red;'>Error Details:</h3>";
        echo "<p><strong>Type:</strong> " . ($errorData['error']['type'] ?? 'Unknown') . "</p>";
        echo "<p><strong>Code:</strong> " . ($errorData['error']['code'] ?? 'Unknown') . "</p>";
        echo "<p><strong>Message:</strong> " . ($errorData['error']['message'] ?? 'Unknown') . "</p>";
    }
}
?> 