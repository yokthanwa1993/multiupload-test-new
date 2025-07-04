<?php
// config.php (Final Working Version)

// Start output buffering to prevent headers already sent issues
ob_start();

// Suppress deprecated warnings in production
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

session_start();

$scriptName = basename($_SERVER['SCRIPT_NAME']);
// Allow these scripts to run even without a credentials file
$allowed_without_token = ['setting.php', 'fix_permissions.php'];

// --- Main Credentials Handling ---
$credentialsPath = __DIR__ . '/credentials/token.json';
$credentials = [];

if (file_exists($credentialsPath)) {
    $credentials = json_decode(file_get_contents($credentialsPath), true);
    // Handle JSON decoding errors gracefully
    if (json_last_error() !== JSON_ERROR_NONE) {
        if (!in_array($scriptName, $allowed_without_token)) {
            die("Error: Corrupted 'token.json' file. Please fix it manually or re-create it via <a href='setting.php'>settings</a>.");
        }
        $credentials = []; // Reset to empty on error for settings page
    }
} elseif (!in_array($scriptName, $allowed_without_token)) {
    // If credentials file is missing and we are not on an allowed page, redirect to the settings page.
    $credentialsDir = __DIR__ . '/credentials';
    if (!is_dir($credentialsDir)) {
        // In a proper Docker setup, this directory should already exist.
        // If it doesn't, it's a deployment configuration issue, not a runtime issue.
        die("Error: The 'credentials' directory does not exist. Please check your deployment configuration (Dockerfile or Volume settings in Coolify).");
    }
    // Redirect user to the setup page
    header('Location: setting.php');
    exit();
}

// --- Google/YouTube API Configuration ---
if (!empty($credentials['web']['client_id'])) {
    define('GOOGLE_CLIENT_ID', $credentials['web']['client_id']);
    define('GOOGLE_CLIENT_SECRET', $credentials['web']['client_secret']);
    define('GOOGLE_REDIRECT_URI', $credentials['web']['redirect_uris'][0]);
}

// --- Facebook API Configuration ---
if (!empty($credentials['facebook']['page_id'])) {
    define('FACEBOOK_PAGE_ID', $credentials['facebook']['page_id']);
    define('FACEBOOK_PAGE_ACCESS_TOKEN', $credentials['facebook']['page_access_token']);
}

// --- API Endpoint Constants ---
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('YOUTUBE_UPLOAD_URL', 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status');
define('YOUTUBE_THUMBNAIL_UPLOAD_URL', 'https://www.googleapis.com/upload/youtube/v3/thumbnails/set');
define('FACEBOOK_GRAPH_API_URL', 'https://graph.facebook.com/v19.0/');

// --- Token Management Functions ---
define('USER_TOKEN_PATH', __DIR__ . '/credentials/user_token.json');

function saveAccessToken($token) {
    if (!isset($token['refresh_token'])) {
        $currentToken = getAccessToken();
        if ($currentToken && isset($currentToken['refresh_token'])) {
            $token['refresh_token'] = $currentToken['refresh_token'];
        }
    }
    file_put_contents(USER_TOKEN_PATH, json_encode($token, JSON_PRETTY_PRINT));
}

function getAccessToken() {
    if (!file_exists(USER_TOKEN_PATH)) return null;
    return json_decode(file_get_contents(USER_TOKEN_PATH), true);
}

function deleteAccessToken() {
    if (file_exists(USER_TOKEN_PATH)) unlink(USER_TOKEN_PATH);
}


// --- cURL Functions ---
function makeCurlPostRequest($url, $data = [], $headers = [], $isJson = false, $filePath = null, $fileFieldName = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, true);

    if ($filePath && $fileFieldName) {
        $postFields = $data;
        if (class_exists('CURLFile')) {
            $postFields[$fileFieldName] = new CURLFile($filePath);
        } else {
             $postFields[$fileFieldName] = '@' . realpath($filePath);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    } elseif ($isJson) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers[] = 'Content-Type: application/json';
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $responseHeaders = substr($response, 0, $headerSize);
    $responseBody = substr($response, $headerSize);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) throw new Exception("cURL Error: " . $error);

    $jsonBody = json_decode($responseBody, true);
    return [
        'status_code' => $httpCode,
        'headers' => $responseHeaders,
        'body' => (json_last_error() === JSON_ERROR_NONE) ? $jsonBody : $responseBody
    ];
}

function makeCurlPutRequest($url, $filePath, $contentType, $accessToken, $offset = 0, $length = null, $totalSize = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $fileHandle = fopen($filePath, 'rb');
    fseek($fileHandle, $offset);
    $data = ($length !== null) ? fread($fileHandle, $length) : fread($fileHandle, filesize($filePath));
    fclose($fileHandle);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $headers = [
        "Content-Type: " . $contentType,
        "Authorization: Bearer " . $accessToken,
        "Content-Length: " . strlen($data)
    ];
    if ($totalSize !== null && $length !== null) {
        $endByte = $offset + strlen($data) - 1;
        $headers[] = "Content-Range: bytes {$offset}-{$endByte}/{$totalSize}";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $responseHeaders = substr($response, 0, $headerSize);
    $responseBody = substr($response, $headerSize);
    curl_close($ch);

    $jsonBody = json_decode($responseBody, true);
    return [
        'status_code' => $httpCode,
        'headers' => $responseHeaders,
        'body' => (json_last_error() === JSON_ERROR_NONE) ? $jsonBody : $responseBody
    ];
}


// --- Google Auth Functions ---
function getTokensFromCode($code) {
    $response = makeCurlPostRequest(GOOGLE_TOKEN_URL, [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code',
    ]);
    if ($response['status_code'] !== 200) throw new Exception("Failed to get Google tokens.");
    return $response['body'];
}

function refreshAccessToken($refreshToken) {
    $response = makeCurlPostRequest(GOOGLE_TOKEN_URL, [
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ]);
    if ($response['status_code'] !== 200) throw new Exception("Failed to refresh Google token.");
    return $response['body'];
}

function createAuthUrl($scope) {
    return GOOGLE_AUTH_URL . '?' . http_build_query([
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => $scope,
        'access_type' => 'offline',
        'prompt' => 'consent',
    ]);
}


// --- Facebook Upload Function (Based on working logic) ---
function uploadToFacebookReel($videoPath, $coverPath, $description, $scheduledTimestamp = null) {
    $baseUrl = "https://graph.facebook.com/v19.0/" . FACEBOOK_PAGE_ID . "/video_reels";
    
    // 1a: Init
    $initUrl = "$baseUrl?upload_phase=start&access_token=" . FACEBOOK_PAGE_ACCESS_TOKEN;
    $ch = curl_init($initUrl);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false]);
    $initResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) {
        $errorData = json_decode($initResponse, true);
        $errorMsg = isset($errorData['error']['message']) ? $errorData['error']['message'] : $initResponse;
        throw new Exception("FB Init Failed (HTTP $httpCode): " . $errorMsg);
    }
    $initData = json_decode($initResponse, true);
    if (!isset($initData['video_id'])) {
        throw new Exception("FB Init Failed: No video_id received. Response: " . $initResponse);
    }
    
    $videoId = $initData['video_id'];
    $uploadUrl = $initData['upload_url'];
    
    // 1b: Upload
    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => file_get_contents($videoPath),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: OAuth " . FACEBOOK_PAGE_ACCESS_TOKEN, "Offset: 0", "File_Size: " . filesize($videoPath), "Content-Type: application/octet-stream"],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $uploadResponse = curl_exec($ch);
    $uploadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($uploadHttpCode !== 200) throw new Exception("FB Upload Failed: " . $uploadResponse);
    
    // 1c: Finish
    $finishParams = [
        'video_id' => $videoId,
        'upload_phase' => 'finish',
        'description' => $description,
        'access_token' => FACEBOOK_PAGE_ACCESS_TOKEN
    ];
    if ($scheduledTimestamp) {
        $finishParams['video_state'] = 'SCHEDULED';
        $finishParams['scheduled_publish_time'] = $scheduledTimestamp;
    } else {
        $finishParams['video_state'] = 'PUBLISHED';
    }
    $finishUrl = "$baseUrl?" . http_build_query($finishParams);
    
    $ch = curl_init($finishUrl);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false]);
    $finishResponse = curl_exec($ch);
    $finishHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($finishHttpCode !== 200) throw new Exception("FB Finish Failed: " . $finishResponse);
    
    $finishData = json_decode($finishResponse, true);
    if (!isset($finishData['success']) && !isset($finishData['post_id'])) throw new Exception("FB Publish command did not succeed.");

    // 2. Wait for processing
    $isReady = false;
    for ($i = 0; $i < 24; $i++) {
        $statusUrl = "https://graph.facebook.com/v19.0/$videoId?fields=status&access_token=" . FACEBOOK_PAGE_ACCESS_TOKEN;
        $ch = curl_init($statusUrl);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false]);
        $statusResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200) {
            $statusData = json_decode($statusResponse, true);
            if (isset($statusData['status']['video_status']) && $statusData['status']['video_status'] === 'ready') {
                $isReady = true;
                break;
            }
        }
        sleep(5);
    }
    if (!$isReady) throw new Exception("วิดีโอใช้เวลาประมวลผลนานเกินไป");

    // 3. Set Thumbnail (if provided)
    if ($coverPath) {
        $thumbnailUrl = "https://graph.facebook.com/v19.0/$videoId/thumbnails";
        $postData = ['access_token' => FACEBOOK_PAGE_ACCESS_TOKEN, 'source' => new CURLFile($coverPath), 'is_preferred' => 'true'];
        $ch = curl_init($thumbnailUrl);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $postData, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false]);
        curl_exec($ch);
        curl_close($ch);
    }
    
    return "https://www.facebook.com/reel/$videoId";
}


// --- Helper Functions ---
function getHttpResponseHeaders($headerString) {
    $headers = [];
    foreach (explode("\n", $headerString) as $line) {
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $headers[trim(strtolower($key))] = trim($value);
        }
    }
    return $headers;
}
?>
