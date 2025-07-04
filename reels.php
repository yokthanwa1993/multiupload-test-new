<?php
/**
 * Facebook Reel Uploader with Custom Thumbnail - Standalone PHP
 * [เวอร์ชันล่าสุด 2025 - ใช้ Logic ที่ทำงานได้จริง พร้อม Progress Bar]
 */

// --- CONFIGURATION ---
$FACEBOOK_PAGE_ID = '675135025677492';
$FACEBOOK_PAGE_ACCESS_TOKEN = 'EAAChZCKmUTDcBO4Vv1pFtfMQCehJiA73VA7u1i8le8PvnghlH1A9ejbsU6rL7FCZCcyZA9DusZADmHLdvCZAEeddtFUgK1EuiqvOZCnE4C6WaUQDUw35AzahShrcXGsgebUoZBa6U2gHRDqHZCVCadHM0xjZCztnbiTO2RYlHKHETNzuzgKBulLPt5LouwwTeVe9FKZCSBkEwxjFBMXmqXn7ZCR';

// --- UPLOAD HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    set_time_limit(300); // Allow script to run for 5 minutes

    if ($_POST['action'] === 'upload') {
        $description = $_POST['description'] ?? 'ทดสอบการอัปโหลด Reel';
        
        if (!isset($_FILES['videoFile']) || $_FILES['videoFile']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'กรุณาเลือกไฟล์วิดีโอ']);
            exit;
        }
        
        $result = uploadFacebookReel($FACEBOOK_PAGE_ID, $FACEBOOK_PAGE_ACCESS_TOKEN, $_FILES['videoFile'], $_FILES['coverFile'] ?? null, $description);
        echo json_encode($result);
        exit;
    }
}

function uploadFacebookReel($pageId, $accessToken, $videoFile, $coverFile, $description) {
    try {
        // 1. อัปโหลดวิดีโอและรับ Video ID
        $videoId = uploadVideoAndGetId($pageId, $accessToken, $videoFile, $description);
        
        if (!$videoId) {
            return ['success' => false, 'message' => 'การอัปโหลดวิดีโอล้มเหลว'];
        }
        
        // 2. รอให้วิดีโอประมวลผลเสร็จ
        $isReady = waitForVideoProcessing($videoId, $accessToken);
        
        if (!$isReady) {
            return ['success' => false, 'message' => 'วิดีโอประมวลผลไม่สำเร็จภายในเวลาที่กำหนด', 'videoId' => $videoId];
        }
        
        // 3. ตั้งค่าภาพปก (ถ้ามี)
        $thumbnailResult = true; // Assume success if no cover file
        if ($coverFile && $coverFile['error'] === UPLOAD_ERR_OK) {
            $thumbnailResult = setCustomThumbnail($videoId, $accessToken, $coverFile);
        }
        
        // 4. สร้าง URL ของ Reel
        $reelUrl = "https://www.facebook.com/reel/$videoId";
        
        if ($thumbnailResult) {
            return ['success' => true, 'message' => 'อัปโหลดวิดีโอและตั้งค่าภาพปกสำเร็จสมบูรณ์!', 'videoId' => $videoId, 'reelUrl' => $reelUrl];
        } else {
            return ['success' => true, 'message' => 'อัปโหลดวิดีโอสำเร็จ แต่การตั้งค่าภาพปกล้มเหลว', 'videoId' => $videoId, 'reelUrl' => $reelUrl];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function uploadVideoAndGetId($pageId, $accessToken, $videoFile, $description) {
    $baseUrl = "https://graph.facebook.com/v19.0/$pageId/video_reels";
    
    // 1a: เริ่มต้น Session
    $initUrl = "$baseUrl?upload_phase=start&access_token=$accessToken";
    $ch = curl_init($initUrl);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false]);
    $initResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) return null;
    $initData = json_decode($initResponse, true);
    if (!isset($initData['video_id'])) return null;
    
    $videoId = $initData['video_id'];
    $uploadUrl = $initData['upload_url'];
    
    // 1b: อัปโหลดไฟล์วิดีโอ
    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => file_get_contents($videoFile['tmp_name']),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: OAuth $accessToken", "Offset: 0", "File_Size: " . filesize($videoFile['tmp_name']), "Content-Type: application/octet-stream"],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $uploadResponse = curl_exec($ch);
    $uploadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($uploadHttpCode !== 200) return null;
    
    // 1c: ส่งวิดีโอเข้าคิวประมวลผล
    $finishUrl = "$baseUrl?video_id=$videoId&upload_phase=finish&video_state=PUBLISHED&description=" . urlencode($description) . "&access_token=$accessToken";
    $ch = curl_init($finishUrl);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false]);
    $finishResponse = curl_exec($ch);
    $finishHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($finishHttpCode !== 200) return null;
    
    $finishData = json_decode($finishResponse, true);
    if (isset($finishData['success']) || isset($finishData['post_id'])) {
        return $videoId;
    }
    
    return null;
}

function waitForVideoProcessing($videoId, $accessToken, $timeoutSeconds = 180) {
    $pollingInterval = 15;
    $maxAttempts = $timeoutSeconds / $pollingInterval;
    
    for ($i = 0; $i < $maxAttempts; $i++) {
        $statusUrl = "https://graph.facebook.com/v19.0/$videoId?fields=status&access_token=$accessToken";
        $ch = curl_init($statusUrl);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false]);
        $statusResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $statusData = json_decode($statusResponse, true);
            if (isset($statusData['status']['video_status']) && $statusData['status']['video_status'] === 'ready') {
                return true;
            }
        }
        sleep($pollingInterval);
    }
    return false;
}

function setCustomThumbnail($videoId, $accessToken, $coverFile) {
    $thumbnailUrl = "https://graph.facebook.com/v19.0/$videoId/thumbnails";
    $postData = [
        'access_token' => $accessToken,
        'source' => new CURLFile($coverFile['tmp_name'], $coverFile['type'], $coverFile['name']),
        'is_preferred' => 'true'
    ];
    $ch = curl_init($thumbnailUrl);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $postData, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        return isset($result['success']) && $result['success'];
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facebook Reel Uploader</title>
    <link rel="stylesheet" href="css/reels_style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎬 Facebook Reel Uploader</h1>
            <p>อัปโหลดวิดีโอและภาพปกสำหรับ Facebook Reels</p>
        </div>
        
        <form id="uploadForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            
            <div class="form-group">
                <label for="description">คำอธิบาย Reel:</label>
                <textarea id="description" name="description" rows="3" placeholder="ใส่คำอธิบายสำหรับ Reel ของคุณ..." required></textarea>
            </div>
            
            <div class="form-group">
                <label for="videoFile">วิดีโอ Reel:</label>
                <input type="file" id="videoFile" name="videoFile" accept="video/*" required>
            </div>
            
            <div class="form-group">
                <label for="coverFile">ภาพปก:</label>
                <input type="file" id="coverFile" name="coverFile" accept="image/*">
            </div>
            
            <button type="submit" class="submit-btn" id="submitBtn">🚀 อัปโหลด Reel</button>
        </form>
        
        <div class="progress" id="progress">
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <div class="progress-text" id="progressText">กำลังเตรียมการ...</div>
        </div>
        
        <div class="result" id="result"></div>
    </div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = document.getElementById('submitBtn');
            const progress = document.getElementById('progress');
            const resultDiv = document.getElementById('result');
            let progressInterval;

            submitBtn.disabled = true;
            submitBtn.textContent = '🔄 กำลังอัปโหลด...';
            progress.style.display = 'block';
            resultDiv.style.display = 'none';
            
            function updateProgress(percent, text) {
                document.getElementById('progressFill').style.width = percent + '%';
                document.getElementById('progressText').textContent = text;
            }

            updateProgress(10, 'กำลังเริ่มต้นการอัปโหลด...');
            
            // --- Simulate progress while waiting for server ---
            let currentProgress = 10;
            progressInterval = setInterval(() => {
                if (currentProgress < 90) {
                    currentProgress += Math.random() * 5; // Slower progress
                    updateProgress(Math.min(currentProgress, 90), 'กำลังดำเนินการ...');
                }
            }, 1500); // Update every 1.5 seconds

            try {
                const response = await fetch('reels.php', {
                    method: 'POST',
                    body: formData
                });
                
                clearInterval(progressInterval); // Stop simulation
                updateProgress(95, 'เกือบเสร็จแล้ว...');
                
                const data = await response.json();
                
                updateProgress(100, 'เสร็จสิ้น!');
                
                if (data.success) {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = `
                        <h4>🎉 อัปโหลดสำเร็จ!</h4>
                        <p><strong>สถานะ:</strong> ${data.message}</p>
                        ${data.reelUrl ? `<p><strong>ลิงก์ Reel:</strong> <a href="${data.reelUrl}" target="_blank">${data.reelUrl}</a></p>` : ''}
                    `;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `<h4>❌ เกิดข้อผิดพลาด</h4><p>${data.message}</p>`;
                }
            } catch (error) {
                clearInterval(progressInterval); // Stop simulation on error
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `<h4>❌ เกิดข้อผิดพลาด</h4><p>ไม่สามารถติดต่อเซิร์ฟเวอร์ได้ หรือการตอบกลับมีปัญหา</p>`;
            }
            
            submitBtn.disabled = false;
            submitBtn.textContent = '🚀 อัปโหลด Reel';
            resultDiv.style.display = 'block';
        });
    </script>
</body>
</html>
