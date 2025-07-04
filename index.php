<?php
// index.php (Dual Platform Uploader - Final Fix v10)
require_once 'config.php';

// --- AJAX UPLOAD HANDLER ---
if (isset($_POST['action']) && $_POST['action'] === 'upload_facebook') {
    header('Content-Type: application/json; charset=utf-8');
    set_time_limit(300);
    $savedVideoPath = null;
    $savedThumbPath = null;
    try {
        // --- File Validation and Saving ---
        if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            $errorMessage = 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์: ';
            if (!isset($_FILES['video_file'])) {
                $errorMessage .= 'ไม่พบข้อมูลไฟล์ที่ส่งมา';
            } else {
                 switch ($_FILES['video_file']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                        $errorMessage .= 'ไฟล์มีขนาดใหญ่เกินกว่าที่ server กำหนด (upload_max_filesize)';
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $errorMessage .= 'ไฟล์มีขนาดใหญ่เกินกว่าที่ฟอร์มกำหนด';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $errorMessage .= 'ไฟล์ถูกอัปโหลดมาไม่สมบูรณ์';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $errorMessage .= 'ไม่ได้เลือกไฟล์ใดๆ';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $errorMessage .= 'ไม่พบ temporary folder';
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $errorMessage .= 'ไม่สามารถเขียนไฟล์ลงดิสก์ได้';
                        break;
                    default:
                        $errorMessage .= 'เกิดข้อผิดพลาดที่ไม่รู้จัก (Error code: ' . $_FILES['video_file']['error'] . ')';
                        break;
                }
            }
            echo json_encode(['status' => 'error', 'message' => $errorMessage]);
            exit();
        }
        $videoFile = $_FILES['video_file'];
        $uploadDir = __DIR__ . '/uploads/';
        
        // Debug: Check directory permissions
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('ไม่สามารถสร้างโฟลเดอร์ uploads ได้');
            }
        }
        
        // Debug: Check if directory is writable
        if (!is_writable($uploadDir)) {
            throw new Exception('โฟลเดอร์ uploads ไม่สามารถเขียนได้ - Permission: ' . substr(sprintf('%o', fileperms($uploadDir)), -4));
        }
        $savedVideoFileName = uniqid('vid_', true) . '.' . pathinfo($videoFile['name'], PATHINFO_EXTENSION);
        $savedVideoPath = $uploadDir . $savedVideoFileName;
        if (!move_uploaded_file($videoFile['tmp_name'], $savedVideoPath)) {
            $error_msg = 'ไม่สามารถบันทึกไฟล์วิดีโอชั่วคราวได้';
            $error_msg .= ' | Upload Error: ' . $videoFile['error'];
            $error_msg .= ' | Temp file: ' . $videoFile['tmp_name'];
            $error_msg .= ' | Target: ' . $savedVideoPath;
            $error_msg .= ' | Dir writable: ' . (is_writable($uploadDir) ? 'Yes' : 'No');
            throw new Exception($error_msg);
        }
        if (isset($_FILES['thumbnail_file']) && $_FILES['thumbnail_file']['error'] === UPLOAD_ERR_OK) {
            $thumbFile = $_FILES['thumbnail_file'];
            $savedThumbFileName = uniqid('thumb_', true) . '.' . pathinfo($thumbFile['name'], PATHINFO_EXTENSION);
            $savedThumbPath = $uploadDir . $savedThumbFileName;
            if (!move_uploaded_file($thumbFile['tmp_name'], $savedThumbPath)) {
                $savedThumbPath = null;
            }
        }
        $caption = trim($_POST['description'] ?? '');
        
        // Define the standard hashtags and prevent duplication
        $reelsHashtags_str = '#เล่าเรื่อง #คลิปไวรัล #reels #viralvideo';
        $baseHashtags = explode(' ', $reelsHashtags_str);
        $hashtagsToAppend = [];
        foreach ($baseHashtags as $tag) {
            // Only add hashtag if it's not already in the caption
            if (strpos($caption, $tag) === false) {
                $hashtagsToAppend[] = $tag;
            }
        }

        $fbDescription = $caption;
        if (!empty($hashtagsToAppend)) {
            $fbDescription .= ' ' . implode(' ', $hashtagsToAppend);
        }
        $fbDescription = trim($fbDescription);

        $isScheduled = !empty($_POST['schedule_post']) && !empty($_POST['publish_at']);
        $publishTimestamp = null;
        if ($isScheduled) {
            $publishTime = new DateTime($_POST['publish_at'], new DateTimeZone('Asia/Bangkok'));
            $now = new DateTime('now', new DateTimeZone('Asia/Bangkok'));
            $now->add(new DateInterval('PT14M59S'));
            if ($publishTime <= $now) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'เวลาที่ตั้งต้องเป็นอนาคตอย่างน้อย 15 นาที']);
                exit();
            }
            $publishTimestamp = $publishTime->getTimestamp();
        }
        $fbReelUrl = uploadToFacebookReel($savedVideoPath, $savedThumbPath, $fbDescription, $publishTimestamp);
        echo json_encode([
            'status' => 'success',
            'message' => 'Facebook Reels: ' . ($isScheduled ? 'ตั้งเวลาสำเร็จ!' : 'อัปโหลดสำเร็จ!') . ' <a href="' . $fbReelUrl . '" target="_blank">ดู Reel</a>',
            'fb_url' => $fbReelUrl,
            'video_path' => $savedVideoPath,
            'thumb_path' => $savedThumbPath,
            'caption' => $caption,
            'is_scheduled' => $isScheduled,
            'publish_at' => $_POST['publish_at'] ?? null
        ]);
    } catch (Exception $e) {
        // Log detailed error for debugging
        error_log("Facebook Upload Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        http_response_code(500);
        echo json_encode([
            'status' => 'error', 
            'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            'debug_info' => [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ]);
    }
    exit();
}

if (isset($_POST['action']) && $_POST['action'] === 'upload_youtube') {
    header('Content-Type: application/json; charset=utf-8');
    set_time_limit(300);
    try {
        $savedVideoPath = $_POST['video_path'];
        $savedThumbPath = $_POST['thumb_path'] ?? null;
        $caption = $_POST['caption'] ?? '';
        $isScheduled = $_POST['is_scheduled'] === 'true' ? true : false;
        $publish_at = $_POST['publish_at'] ?? null;
        $shortsHashtags = '#เล่าเรื่อง #คลิปไวรัล #viralvideo #shorts';
        $ytDescription = $caption . ' ' . $shortsHashtags;
        $finalTitle = !empty($caption) ? (mb_strlen($caption) > 100 ? mb_substr($caption, 0, 100) : $caption) : 'Untitled Video';
        $iso8601Time = null;
        if ($isScheduled && $publish_at) {
            $publishTime = new DateTime($publish_at, new DateTimeZone('Asia/Bangkok'));
            $iso8601Time = $publishTime->format('c');
        }
        $ytAccessToken = getAccessToken();
        if ($ytAccessToken) {
            if (time() >= ($ytAccessToken['created'] + $ytAccessToken['expires_in'])) {
                if (!isset($ytAccessToken['refresh_token'])) throw new Exception('YouTube: Token หมดอายุและไม่มี Refresh Token');
                $ytAccessToken = refreshAccessToken($ytAccessToken['refresh_token']);
                $ytAccessToken['created'] = time();
                saveAccessToken($ytAccessToken);
            }
            $youtube_status = $isScheduled && $iso8601Time ? ['privacyStatus' => 'private', 'publishAt' => $iso8601Time] : ['privacyStatus' => 'public'];
            $yt_video_metadata = ['snippet' => ['title' => $finalTitle, 'description' => $ytDescription, 'categoryId'  => '22'], 'status' => $youtube_status];
            $videoFileType = mime_content_type($savedVideoPath);
            $yt_upload_headers = ['Authorization: Bearer ' . $ytAccessToken['access_token'], 'X-Upload-Content-Type: ' . $videoFileType, 'X-Upload-Content-Length: ' . filesize($savedVideoPath)];
            $yt_init_response = makeCurlPostRequest(YOUTUBE_UPLOAD_URL, $yt_video_metadata, $yt_upload_headers, true);
            if ($yt_init_response['status_code'] !== 200) throw new Exception('YouTube: ไม่สามารถเริ่มการอัปโหลดได้.');
            $yt_upload_url = getHttpResponseHeaders($yt_init_response['headers'])['location'] ?? null;
            if (empty($yt_upload_url)) throw new Exception('YouTube: ไม่พบ URL สำหรับอัปโหลด');
            $yt_file_handle = fopen($savedVideoPath, 'rb');
            $yt_bytes_uploaded = 0;
            $yt_final_response = null;
            while (!feof($yt_file_handle)) {
                $chunk = fread($yt_file_handle, 1 * 1024 * 1024);
                $yt_chunk_response = makeCurlPutRequest($yt_upload_url, $savedVideoPath, $videoFileType, $ytAccessToken['access_token'], $yt_bytes_uploaded, strlen($chunk), filesize($savedVideoPath));
                if (in_array($yt_chunk_response['status_code'], [200, 201])) {
                    $yt_final_response = $yt_chunk_response['body'];
                    break;
                }
                if ($yt_chunk_response['status_code'] !== 308) throw new Exception('YouTube: เกิดข้อผิดพลาดระหว่างอัปโหลด');
                $yt_bytes_uploaded += strlen($chunk);
            }
            fclose($yt_file_handle);
            if (!$yt_final_response || !isset($yt_final_response['id'])) throw new Exception('YouTube: ไม่ได้รับ Video ID ที่สมบูรณ์');
            $yt_video_id = $yt_final_response['id'];
            $yt_video_url = 'https://www.youtube.com/shorts/' . $yt_video_id;
            echo json_encode(['status' => 'success', 'message' => 'YouTube: อัปโหลดสำเร็จ! <a href="' . $yt_video_url . '" target="_blank">ดูวิดีโอ</a>', 'yt_url' => $yt_video_url]);

            // --- Clean up temporary files ---
            if (!empty($savedVideoPath) && file_exists($savedVideoPath)) {
                unlink($savedVideoPath);
            }
            if (!empty($savedThumbPath) && file_exists($savedThumbPath)) {
                unlink($savedThumbPath);
            }

        } else {
            echo json_encode(['status' => 'error', 'message' => 'YouTube: ข้ามการอัปโหลดเนื่องจากไม่ได้ล็อกอิน']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'YouTube: ' . $e->getMessage()]);
    }
    exit();
}

// --- Page Rendering Logic ---
$accessToken = getAccessToken();
if (isset($_GET['logout'])) {
    deleteAccessToken();
    header('Location: index.php');
    exit();
}
if ($accessToken) {
    try {
        $currentTime = time();
        if (!isset($accessToken['created']) || !isset($accessToken['expires_in'])) {
            throw new Exception("Token ไม่สมบูรณ์ กรุณาล็อกอินใหม่อีกครั้ง");
        }
        $tokenExpireTime = $accessToken['created'] + $accessToken['expires_in'];
        if ($currentTime >= $tokenExpireTime) {
            if (!isset($accessToken['refresh_token'])) {
                throw new Exception("เซสชันหมดอายุและไม่สามารถรีเฟรชได้ กรุณาล็อกอินใหม่อีกครั้ง");
            }
            $newAccessToken = refreshAccessToken($accessToken['refresh_token']);
            $newAccessToken['created'] = time();
            saveAccessToken($newAccessToken);
            $accessToken = $newAccessToken;
            header('Location: index.php');
            exit();
        }
    } catch (Exception $e) {
        deleteAccessToken();
        $accessToken = null;
        $_SESSION['status_message'] = ['type' => 'error', 'text' => $e->getMessage()];
        header('Location: index.php');
        exit();
    }
}

$logo = $credentials['platform_logo'] ?? '';
?>
<!DOCTYPE html>
<html lang="th" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Video Uploader 2025</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <?php
        $css_file = 'css/style.css';
        $css_version = file_exists($css_file) ? filemtime($css_file) : '1.0';
    ?>
    <link rel="stylesheet" href="css/style.css?v=<?php echo $css_version; ?>">
    
    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4338ca;
            --secondary: #f59e0b;
            --accent: #ec4899;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            
            --bg-primary: #0f0f23;
            --bg-secondary: #1a1a2e;
            --bg-tertiary: #16213e;
            --bg-glass: rgba(255, 255, 255, 0.1);
            
            --text-primary: #ffffff;
            --text-secondary: #a0a9c0;
            --text-muted: #6b7280;
            
            --border: rgba(255, 255, 255, 0.1);
            --shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-glow: 0 0 50px rgba(99, 102, 241, 0.3);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Neural Network Animated Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(236, 72, 153, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(16, 185, 129, 0.1) 0%, transparent 50%);
            animation: backgroundShift 15s ease-in-out infinite;
            z-index: -1;
        }

        @keyframes backgroundShift {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(20px, -20px) rotate(1deg); }
            66% { transform: translate(-20px, 20px) rotate(-1deg); }
        }

        /* Main Container */
        .main-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        /* Logo Section */
        .logo-section {
            text-align: center;
            margin-bottom: 2rem;
            animation: logoFloat 6s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .logo-wrapper {
            position: relative;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .logo-wrapper::before {
            content: '';
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            background: conic-gradient(from 0deg, var(--primary), var(--accent), var(--success), var(--primary));
            border-radius: 50%;
            animation: logoGlow 3s linear infinite;
            z-index: -1;
        }

        @keyframes logoGlow {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .logo-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--bg-secondary);
            border: 3px solid rgba(255, 255, 255, 0.2);
            transition: all 0.4s ease;
            position: relative;
            z-index: 1;
        }

        .logo-image:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 0 40px rgba(99, 102, 241, 0.6);
        }

        /* Header */
        .app-title {
            background: linear-gradient(135deg, var(--primary-light), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: 0.5rem;
        }

        .app-subtitle {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Glassmorphism Container */
        .glass-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            box-shadow: var(--shadow-glow);
            position: relative;
            overflow: hidden;
        }

        .glass-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
        }

        /* Status Bar */
        .status-bar {
            padding: 1rem 1.5rem;
            border-radius: 20px 20px 0 0;
            font-weight: 600;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .status-online {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .status-offline {
            background: linear-gradient(135deg, var(--error), #dc2626);
            color: white;
        }

        .status-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            animation: statusShine 3s ease-in-out infinite;
        }

        @keyframes statusShine {
            0% { left: -100%; }
            50% { left: 100%; }
            100% { left: 100%; }
        }

        /* Form Layout - Split inside container (Image LEFT, Form RIGHT) */
        .form-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
            padding: 2rem;
        }

        .form-section {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-2px);
        }

        .form-input::placeholder {
            color: var(--text-muted);
        }

        /* Drag & Drop Upload Area */
        .upload-area {
            width: 100%;
            height: 100%;
            min-height: 400px;
            background: rgba(255, 255, 255, 0.03);
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .upload-area:hover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
            transform: translateY(-2px);
        }

        .upload-area.dragover {
            border-color: var(--accent);
            background: rgba(236, 72, 153, 0.1);
            transform: scale(1.02);
        }

        .upload-area.has-image {
            border-style: solid;
            border-color: var(--success);
        }

        .upload-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.6;
            transition: all 0.3s ease;
        }

        .upload-area:hover .upload-icon {
            transform: scale(1.1);
            opacity: 1;
        }

        .upload-text {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .upload-subtext {
            color: var(--text-muted);
            font-size: 0.75rem;
            text-align: center;
        }

        .preview-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 14px;
            position: absolute;
            top: 0;
            left: 0;
        }

        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 14px;
        }

        .upload-area:hover .image-overlay {
            opacity: 1;
        }

        .overlay-text {
            color: white;
            font-size: 0.875rem;
            text-align: center;
            margin-top: 0.5rem;
        }

        /* Hidden file input */
        .hidden-input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        /* Hashtag Display */
        .hashtag-display {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 1rem;
        }

        .hashtag-display p {
            margin: 0.25rem 0;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        /* Button Styles */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            color: white;
            padding: 0.875rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            font-size: 0.875rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        /* Checkbox */
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }

        /* Progress Bar */
        .progress-container {
            margin-top: 1.5rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            grid-column: 1 / -1;
        }

        .progress-text {
            text-align: center;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .progress-bar-container {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            overflow: hidden;
            position: relative;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 3px;
            transition: width 0.4s ease;
            position: relative;
        }

        .progress-bar-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            width: 20px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: progressShine 2s ease-in-out infinite;
        }

        @keyframes progressShine {
            0% { transform: translateX(-20px); }
            100% { transform: translateX(100vw); }
        }

        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid;
            grid-column: 1 / -1;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--error);
            color: var(--error);
        }

        /* Loading Animation */
        .loading {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Utility Classes */
        .hidden { display: none !important; }
        
        .form-disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                margin: 1rem auto;
                padding: 0 0.75rem;
            }
            
            .form-container {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 1.5rem;
            }

            .upload-area {
                min-height: 250px;
            }
            
            .logo-image {
                width: 80px;
                height: 80px;
            }
            
            .app-title {
                font-size: 1.75rem;
            }
        }

        /* Floating Particles */
        .particle {
            position: fixed;
            width: 4px;
            height: 4px;
            background: rgba(99, 102, 241, 0.6);
            border-radius: 50%;
            pointer-events: none;
            animation: float 15s infinite linear;
            z-index: -1;
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }

        /* Full Width Success Notification Design */
        .success-notification {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
            border: 1px solid var(--success);
            border-radius: 16px;
            padding: 0;
            margin-bottom: 1.5rem;
            overflow: hidden;
            position: relative;
            animation: slideInUp 0.6s ease-out;
            grid-column: 1 / -1; /* Span full width */
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-notification::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--success), #34d399, var(--success));
            animation: successShine 2s ease-in-out infinite;
        }

        @keyframes successShine {
            0%, 100% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
        }

        .notification-header {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .notification-icon {
            font-size: 1.5rem;
            animation: bounce 1s ease-in-out infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .notification-body {
            padding: 1.5rem;
        }

        .upload-results {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        /* Compact, Full-Width Platform Row */
        .platform-row {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            animation: slideInUp 0.5s ease-out;
        }

        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .platform-row:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .facebook-row {
            border-left: 4px solid #1877f2;
        }

        .youtube-row {
            border-left: 4px solid #ff0000;
        }

        .platform-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
        }

        .platform-icon-large {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .facebook-icon-large {
            background: linear-gradient(135deg, #1877f2, #42a5f5);
        }

        .youtube-icon-large {
            background: linear-gradient(135deg, #ff0000, #ff5722);
        }

        .platform-details {
            flex: 1;
        }

        .platform-name-large {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-primary);
            margin-bottom: 0.2rem;
        }

        .platform-description {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .platform-status-large {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: var(--success);
            background: rgba(16, 185, 129, 0.1);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .status-icon {
            font-size: 0.9rem;
        }

        .link-section {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .view-link, .copy-button-large {
            background: rgba(99, 102, 241, 0.1);
            color: var(--text-secondary);
            text-decoration: none;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        
        .copy-button-large {
             cursor: pointer;
        }

        .view-link:hover, .copy-button-large:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
            border-color: var(--primary);
        }

        .copy-button-large.copied {
            background: var(--success);
            color: white;
            border-color: var(--success);
            animation: copySuccess 0.4s ease;
        }

        @keyframes copySuccess {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Error Row Design */
        .error-row {
             border-left: 4px solid var(--error);
        }

        .status-failed {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
        }

        #status-box.has-results {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            grid-column: 1 / -1;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Floating Particles -->
    <div class="particle" style="left: 10%; animation-delay: 0s;"></div>
    <div class="particle" style="left: 20%; animation-delay: 2s;"></div>
    <div class="particle" style="left: 30%; animation-delay: 4s;"></div>
    <div class="particle" style="left: 40%; animation-delay: 6s;"></div>
    <div class="particle" style="left: 50%; animation-delay: 8s;"></div>
    <div class="particle" style="left: 60%; animation-delay: 10s;"></div>
    <div class="particle" style="left: 70%; animation-delay: 12s;"></div>
    <div class="particle" style="left: 80%; animation-delay: 14s;"></div>
    <div class="particle" style="left: 90%; animation-delay: 16s;"></div>

    <div class="main-container">
        <!-- Logo Section -->
        <div class="logo-section">
            <div class="logo-wrapper">
                <a href="setting.php">
                    <img src="<?php echo htmlspecialchars($logo ?: 'https://upload.wikimedia.org/wikipedia/commons/5/51/Facebook_f_logo_%282019%29.svg'); ?>" alt="Platform Logo" class="logo-image">
                </a>
            </div>
            <h1 class="app-title">AI UPLOADER</h1>
        </div>

        <!-- Main Card -->
        <div class="glass-container">
            <!-- Status Bar -->
            <?php
            $statusClass = isset($accessToken) && $accessToken ? 'status-online' : 'status-offline';
            $statusText = isset($accessToken) && $accessToken ? '🟢 NEURAL LINK ACTIVE' : '🔴 NEURAL LINK DISCONNECTED - <a href="login.php" style="color:white; text-decoration: underline;">CONNECT</a>';
            ?>
            <div class="status-bar <?php echo $statusClass; ?>">
                <?php echo $statusText; ?>
            </div>

            <!-- Form Container with Split Layout -->
            <div class="form-container">
                <?php
                if (isset($_SESSION['status_message'])) {
                    $message_type = $_SESSION['status_message']['type'] === 'error' ? 'alert-error' : 'alert-success';
                    echo '<div class="alert ' . $message_type . '">' . nl2br(htmlspecialchars($_SESSION['status_message']['text'])) . '</div>';
                    unset($_SESSION['status_message']);
                }
                ?>

                <div id="status-box" class="alert hidden"></div>

                <!-- Left Side - Drag & Drop Upload Area -->
                <div class="upload-section">
                    <div class="upload-area" id="uploadArea">
                        <div class="upload-icon">🖼️</div>
                        <div class="upload-text">Drag & Drop Thumbnail</div>
                        <div class="upload-subtext">or click to browse<br>JPG, PNG, GIF supported</div>
                        
                        <img id="imagePreview" class="hidden preview-image" alt="Image Preview"/>
                        
                        <div class="image-overlay">
                            <div class="upload-icon" style="font-size: 2rem; margin-bottom: 0.5rem;">📁</div>
                            <div class="overlay-text">Click to change image</div>
                        </div>
                    </div>
                    
                    <input type="file" id="thumbnail_file" name="thumbnail_file" class="hidden-input" accept="image/jpeg,image/png,image/gif">
                </div>

                <!-- Right Side - Form Fields -->
                <div class="form-section">
                    <form id="upload-form" method="POST" enctype="multipart/form-data">
                        <div class="<?php if (!isset($accessToken) || !$accessToken) echo 'form-disabled'; ?>">
                            <div class="form-group">
                                <label for="video_file" class="form-label">🎬 Select Video File (MP4)</label>
                                <input type="file" id="video_file" name="video_file" class="form-input" accept="video/*" required>
                            </div>

                            <div class="form-group">
                                <label for="description" class="form-label">📝 Title / Description</label>
                                <textarea id="description" name="description" class="form-input" rows="4" required placeholder="Enter your video description..."></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label">🏷️ Auto Hashtags</label>
                                <div class="hashtag-display">
                                    <p><strong>For Reels:</strong> #เล่าเรื่อง #คลิปไวรัล #reels #viralvideo</p>
                                    <p><strong>For Shorts:</strong> #เล่าเรื่อง #คลิปไวรัล #viralvideo #shorts</p>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="checkbox-wrapper">
                                    <input type="checkbox" id="schedule_post" name="schedule_post" value="true">
                                    <label for="schedule_post" class="form-label">⏰ Schedule Post</label>
                                </div>
                            </div>
                            <div class="form-group hidden" id="schedule-controls">
                                <label for="publish_at" class="form-label">Select Date & Time</label>
                                <input type="datetime-local" id="publish_at" name="publish_at" class="form-input">
                                <p id="time-validation-message" class="alert-error hidden">Please select a future time (at least 15 minutes)</p>
                            </div>

                            <button type="submit" id="submitButton" class="btn-primary">
                                <span id="button-text">🚀 Launch Upload</span>
                                <div id="button-loader" class="loading hidden"></div>
                            </button>
                        </div>
                    </form>
                </div>

                <div id="progress" class="progress-container hidden">
                    <div class="progress-text" id="progressText">Preparing neural networks...</div>
                    <div class="progress-bar-container">
                        <div id="progressFill" class="progress-bar-fill" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
    const uploadForm = document.getElementById('upload-form');
    const statusBox = document.getElementById('status-box');
    const submitButton = document.getElementById('submitButton');
    const buttonText = document.getElementById('button-text');
    const buttonLoader = document.getElementById('button-loader');
    
    const thumbnailFileInput = document.getElementById('thumbnail_file');
    const imagePreview = document.getElementById('imagePreview');
    const uploadArea = document.getElementById('uploadArea');

    const scheduleCheckbox = document.getElementById('schedule_post');
    const scheduleControls = document.getElementById('schedule-controls');
    const publishAtInput = document.getElementById('publish_at');
    const timeValidationMessage = document.getElementById('time-validation-message');
    
    const progressContainer = document.getElementById('progress');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');

    // Create a single row for a platform result
    function createResultRow(platform, result) {
        const isSuccess = result.status === 'success';
        const platformName = platform === 'facebook' ? 'Facebook Reels' : 'YouTube Shorts';
        const platformIcon = platform === 'facebook' ? '📘' : '📺';
        const platformClass = platform === 'facebook' ? 'facebook-row' : 'youtube-row';
        const platformIconClass = platform === 'facebook' ? 'facebook-icon-large' : 'youtube-icon-large';
        const url = isSuccess ? (platform === 'facebook' ? result.fb_url : result.yt_url) : '#';
        const linkText = platform === 'facebook' ? 'View Reel' : 'View Short';
        const description = isSuccess ? `Your video is now live on ${platformName}` : result.message;
        
        const row = document.createElement('div');
        row.className = `platform-row ${isSuccess ? platformClass : 'error-row'}`;
        
        row.innerHTML = `
            <div class="platform-info">
                <div class="platform-icon-large ${platformIconClass}">${platformIcon}</div>
                <div class="platform-details">
                    <div class="platform-name-large">${platformName}</div>
                    <div class="platform-description" style="color: ${isSuccess ? 'var(--text-secondary)' : 'var(--error)'};">${description}</div>
                </div>
            </div>
            ${isSuccess ? `
            <div class="platform-status-large">
                <div class="status-icon">✅</div>
                <div>Success</div>
            </div>
            <div class="link-section">
                <a href="${url}" target="_blank" class="view-link">
                    🔗 ${linkText}
                </a>
                <button class="copy-button-large" onclick="copyToClipboard('${url}', this)">
                    📋 Copy Link
                </button>
            </div>
            ` : `
            <div class="platform-status-large status-failed">
                <div class="status-icon">❌</div>
                <div>Failed</div>
            </div>
            `}
        `;
        return row;
    }

    // Function to create the main notification wrapper
    function createNotificationWrapper() {
        const wrapper = document.createElement('div');
        wrapper.className = 'success-notification';
        wrapper.innerHTML = `
            <div class="notification-header">
                <div class="notification-icon">📊</div>
                <div>Upload Results</div>
            </div>
            <div class="notification-body">
                <div class="upload-results" id="upload-results-container">
                    <!-- Results will be appended here -->
                </div>
            </div>
        `;
        return wrapper;
    }
    
    // Enhanced copy to clipboard with better feedback
    window.copyToClipboard = function(text, button) {
        navigator.clipboard.writeText(text).then(() => {
            const originalHTML = button.innerHTML;
            button.innerHTML = '✅ Copied!';
            button.classList.add('copied');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('copied');
            }, 2500);
        });
    };

    uploadForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        if (scheduleCheckbox.checked) {
            const selectedDate = new Date(publishAtInput.value);
            const now = new Date();
            now.setMinutes(now.getMinutes() + 14);
            
            if (selectedDate <= now) {
                timeValidationMessage.classList.remove('hidden');
                return;
            } else {
                timeValidationMessage.classList.add('hidden');
            }
        }

        submitButton.disabled = true;
        buttonText.classList.add('hidden');
        buttonLoader.classList.remove('hidden');
        
        statusBox.innerHTML = '';
        statusBox.className = 'hidden';

        progressContainer.classList.remove('hidden');

        function updateProgress(percent, text) {
            progressFill.style.width = percent + '%';
            progressText.textContent = text;
        }

        updateProgress(10, 'Initializing...');
        
        try {
            // Step 1: Upload to Facebook
            updateProgress(20, 'Uploading to Facebook Reels...');
            const formData = new FormData(uploadForm);
            formData.append('action', 'upload_facebook');
            if (thumbnailFileInput.files[0]) {
                formData.set('thumbnail_file', thumbnailFileInput.files[0]);
            }

            const fbResponse = await fetch('index.php', { method: 'POST', body: formData });
            const fbResult = await fbResponse.json();
            
            updateProgress(50, 'Facebook upload complete!');
            
            // Show result container and append Facebook result
            statusBox.classList.add('has-results');
            statusBox.classList.remove('hidden');
            statusBox.appendChild(createResultRow('facebook', fbResult));

            if (fbResult.status !== 'success') {
                throw new Error('Facebook upload failed. Aborting further uploads.');
            }

            // Step 2: Upload to YouTube
            await new Promise(resolve => setTimeout(resolve, 1000));
            updateProgress(60, 'Preparing YouTube upload...');
            
            const ytForm = new FormData();
            ytForm.append('action', 'upload_youtube');
            ytForm.append('video_path', fbResult.video_path);
            ytForm.append('thumb_path', fbResult.thumb_path || '');
            ytForm.append('caption', fbResult.caption);
            ytForm.append('is_scheduled', fbResult.is_scheduled);
            ytForm.append('publish_at', fbResult.publish_at || '');

            updateProgress(75, 'Uploading to YouTube Shorts...');
            const ytResponse = await fetch('index.php', { method: 'POST', body: ytForm });
            const ytResult = await ytResponse.json();

            updateProgress(100, 'All uploads processed! 🎉');
            
            // Append YouTube result
            statusBox.appendChild(createResultRow('youtube', ytResult));

            // Reset form only if both succeed
            if (ytResult.status === 'success') {
                uploadForm.reset();
                resetImagePreview();
                scheduleControls.classList.add('hidden');
                buttonText.innerHTML = '🚀 Launch Upload';
            }

        } catch (error) {
            console.error('Upload process error:', error.message);
        } finally {
            submitButton.disabled = false;
            buttonText.classList.remove('hidden');
            buttonLoader.classList.add('hidden');
            progressContainer.classList.add('hidden');
        }
    });

    // Drag & Drop functionality
    uploadArea.addEventListener('click', () => {
        thumbnailFileInput.click();
    });

    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const file = files[0];
            if (file.type.startsWith('image/')) {
                thumbnailFileInput.files = files;
                handleImagePreview(file);
            }
        }
    });

    thumbnailFileInput.addEventListener('change', () => {
        const file = thumbnailFileInput.files[0];
        if (file) {
            handleImagePreview(file);
        } else {
            resetImagePreview();
        }
    });

    function handleImagePreview(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            imagePreview.src = e.target.result;
            imagePreview.classList.remove('hidden');
            uploadArea.classList.add('has-image');
        }
        reader.readAsDataURL(file);
    }

    function resetImagePreview() {
        imagePreview.src = '';
        imagePreview.classList.add('hidden');
        uploadArea.classList.remove('has-image');
    }

    scheduleCheckbox.addEventListener('change', () => {
        const isChecked = scheduleCheckbox.checked;
        scheduleControls.classList.toggle('hidden', !isChecked);
        publishAtInput.required = isChecked;
        
        if (isChecked) {
            buttonText.innerHTML = '⏰ Schedule Upload';
        } else {
            buttonText.innerHTML = '🚀 Launch Upload';
        }
    });

    // Add some dynamic particles
    function createParticle() {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.animationDelay = Math.random() * 15 + 's';
        particle.style.background = `hsl(${Math.random() * 60 + 220}, 70%, 60%)`;
        document.body.appendChild(particle);
        
        setTimeout(() => {
            particle.remove();
        }, 15000);
    }

    // Create particles periodically
    setInterval(createParticle, 3000);
</script>
</body>
</html>
