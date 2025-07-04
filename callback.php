<?php
// callback.php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยืนยันสิทธิ์ Google</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .message { padding: 1rem; border-radius: 0.375rem; margin-bottom: 1rem; font-weight: 500; }
        .message.success { background-color: #d1fae5; color: #065f46; }
        .message.error { background-color: #fee2e2; color: #991b1b; }
        .message.info { background-color: #e0f2fe; color: #1e40af; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md text-center">
    <?php
    function displayMessage($message, $type = 'info') {
        echo '<div class="message ' . htmlspecialchars($type) . '">' . nl2br(htmlspecialchars($message)) . '</div>';
    }

    // ตรวจสอบว่าได้รับ 'code' จาก Google หรือไม่
    if (isset($_GET['code'])) {
        try {
            // แลกเปลี่ยน authorization code เป็น access token และ refresh token
            $token = getTokensFromCode($_GET['code']);
            $token['created'] = time(); // บันทึกเวลาที่สร้าง token
            saveAccessToken($token); // บันทึก token ลงในไฟล์

            displayMessage("การยืนยันสิทธิ์สำเร็จ! กำลังเปลี่ยนเส้นทาง...", "success");
            // ใช้ JavaScript redirect เพื่อให้ข้อความแสดงก่อน
            echo '<script>setTimeout(function(){ window.location.href = "index.php"; }, 2000);</script>';
            exit();

        } catch (Exception $e) {
            displayMessage("เกิดข้อผิดพลาดในการยืนยันสิทธิ์: " . $e->getMessage(), "error");
            echo '<p class="mt-4"><a href="login.php" class="text-blue-600 hover:underline">ลองล็อกอินอีกครั้ง</a></p>';
        }
    } else {
        // กรณีที่ไม่มี 'code' (อาจถูกยกเลิกการอนุญาต)
        displayMessage("การยืนยันสิทธิ์ถูกยกเลิกหรือไม่สมบูรณ์", "info");
        echo '<p class="mt-4"><a href="login.php" class="text-blue-600 hover:underline">กลับไปยังหน้าล็อกอินเพื่อลองอีกครั้ง</a></p>';
    }
    ?>
    </div>
</body>
</html>
