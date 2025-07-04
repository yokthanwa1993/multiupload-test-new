<?php
// login.php
require_once 'config.php';

// หากมีไฟล์ token อยู่แล้ว (หมายถึงเคยล็อกอินแล้ว) ให้ redirect ไปที่ index.php
if (getAccessToken()) {
    header('Location: index.php');
    exit();
}

// หากยังไม่ได้ล็อกอิน ให้สร้าง URL สำหรับการขอสิทธิ์และแสดงปุ่มล็อกอิน
$scopes = 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.force-ssl';
$authUrl = createAuthUrl($scopes);
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ล็อกอิน - ระบบอัพโหลด</title>
    <?php
        $css_file = 'css/style.css';
        $css_version = file_exists($css_file) ? filemtime($css_file) : '1.0';
    ?>
    <link rel="stylesheet" href="css/style.css?v=<?php echo $css_version; ?>">
</head>
<body class="flex items-center justify-center" style="min-height: 100vh;">
    <div class="container" style="max-width: 480px;">
        <div class="card text-center">
            <div class="card-body">
                <h1 class="text-2xl font-bold mb-4">กรุณาล็อกอินด้วย Google</h1>
                <p class="text-secondary mb-6">เพื่ออัพโหลดวิดีโอไปยังช่อง YouTube ของคุณ</p>
                <a href="<?php echo htmlspecialchars($authUrl); ?>" class="btn btn-primary btn-effect" style="background-color: #dd4b39; border-color: #dd4b39;">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true" style="width: 20px; height: 20px; margin-right: 8px;">
                        <path d="M22.675 12.001c0-.78-.068-1.527-.197-2.25H12v4.5h6.347c-.274 1.455-.992 2.684-2.037 3.593V20.5h3.943c2.25-2.088 3.555-5.187 3.555-8.499z" fill="#4285F4"/>
                        <path d="M12 22c3.24 0 5.938-1.077 7.917-2.917l-3.943-3.041c-1.112.75-2.544 1.192-3.974 1.192-3.057 0-5.652-2.062-6.577-4.821H2.433v3.041C4.303 19.34 7.917 22 12 22z" fill="#34A853"/>
                        <path d="M5.423 13.917c-.25-.75-.39-1.554-.39-2.399s.14-1.649.39-2.399V5.083H1.48c-.96.96-1.523 2.222-1.48 3.541.002 1.353.567 2.617 1.48 3.576L5.423 13.917z" fill="#FBBC05"/>
                        <path d="M12 4.175c1.787 0 3.393.738 4.673 1.917L19.7 2.458C17.72 1.054 15.12 0 12 0 7.917 0 4.303 2.66 2.433 5.083l3.943 3.041c.925-2.759 3.52-4.821 6.577-4.821z" fill="#EA4335"/>
                    </svg>
                    ล็อกอินด้วย Google
                </a>
            </div>
        </div>
    </div>
</body>
</html>
