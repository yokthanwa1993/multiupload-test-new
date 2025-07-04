<?php
// setting.php - หน้าแก้ไขค่าต่างๆใน credentials/token.json

$tokenPath = __DIR__ . '/credentials/token.json';
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'web' => [
            'client_id' => $_POST['client_id'] ?? '',
            'client_secret' => $_POST['client_secret'] ?? '',
            'redirect_uris' => array_map('trim', explode("\n", $_POST['redirect_uris'] ?? '')),
            'project_id' => $_POST['project_id'] ?? '',
            'auth_uri' => $_POST['auth_uri'] ?? '',
            'token_uri' => $_POST['token_uri'] ?? '',
            'auth_provider_x509_cert_url' => $_POST['auth_provider_x509_cert_url'] ?? ''
        ],
        'facebook' => [
            'page_id' => $_POST['fb_page_id'] ?? '',
            'page_access_token' => $_POST['fb_page_access_token'] ?? ''
        ]
    ];

    // โลโก้เดียว
    if (isset($_FILES['platform_logo']) && $_FILES['platform_logo']['error'] === UPLOAD_ERR_OK) {
        $logoPath = 'uploads/platform_logo_' . time() . '.png';
        move_uploaded_file($_FILES['platform_logo']['tmp_name'], __DIR__ . '/' . $logoPath);
        $data['platform_logo'] = $logoPath;
    } else if (!empty($_POST['platform_logo_current'])) {
        $data['platform_logo'] = $_POST['platform_logo_current'];
    }

    if (file_put_contents($tokenPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
        $message = '<div class="success">บันทึกข้อมูลเรียบร้อยแล้ว</div>';
    } else {
        $message = '<div class="error">เกิดข้อผิดพลาดในการบันทึกไฟล์</div>';
    }
}

// อ่านค่าปัจจุบัน
$current = file_exists($tokenPath) ? json_decode(file_get_contents($tokenPath), true) : [];
$web = $current['web'] ?? [];
$fb = $current['facebook'] ?? [];

function h($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
function join_uris($arr) { return is_array($arr) ? implode("\n", $arr) : $arr; }
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>ตั้งค่า Credentials</title>
    <?php
        $css_file = 'css/style.css';
        $css_version = file_exists($css_file) ? filemtime($css_file) : '1.0';
    ?>
    <link rel="stylesheet" href="css/style.css?v=<?php echo $css_version; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body class="p-4">
<div class="container" style="max-width: 800px;">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gradient">ตั้งค่า Credentials</h1>
        <a href="index.php" class="btn btn-secondary">กลับหน้าหลัก</a>
    </div>

    <?php if ($message): ?>
        <div class="alert <?php echo strpos($message, 'success') !== false ? 'alert-success' : 'alert-error'; ?>">
            <?php echo strip_tags($message); ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="card mb-6">
            <div class="card-header" style="background: linear-gradient(90deg, #1877f2 80%, #42a5f5 100%); color: white;">
                Facebook Reels & General Settings
            </div>
            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="form-group">
                    <label class="form-label">โลโก้เพจ/ช่อง (ใช้ร่วมกัน)</label>
                    <?php if (!empty($current['platform_logo']) && file_exists(__DIR__ . '/' . $current['platform_logo'])): ?>
                        <img src="<?php echo h($current['platform_logo']); ?>" class="rounded-full shadow-lg mb-4" style="width: 80px; height: 80px; object-fit: cover;">
                    <?php endif; ?>
                    <input type="file" name="platform_logo" class="form-input" accept="image/*">
                    <input type="hidden" name="platform_logo_current" value="<?php echo h($current['platform_logo'] ?? ''); ?>">
                </div>
                <div>
                    <div class="form-group">
                        <label for="fb_page_id" class="form-label">Facebook Page ID</label>
                        <input type="text" id="fb_page_id" name="fb_page_id" class="form-input" value="<?php echo h($fb['page_id'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="fb_page_access_token" class="form-label">Facebook Page Access Token</label>
                        <input type="text" id="fb_page_access_token" name="fb_page_access_token" class="form-input" value="<?php echo h($fb['page_access_token'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="background: linear-gradient(90deg, #ea4335 80%, #ff5252 100%); color: white;">
                YouTube Shorts (Google API)
            </div>
            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="form-group">
                    <label for="client_id" class="form-label">Google Client ID</label>
                    <input type="text" id="client_id" name="client_id" class="form-input" value="<?php echo h($web['client_id'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="client_secret" class="form-label">Google Client Secret</label>
                    <input type="text" id="client_secret" name="client_secret" class="form-input" value="<?php echo h($web['client_secret'] ?? ''); ?>" required>
                </div>
                <div class="form-group md:col-span-2">
                    <label for="redirect_uris" class="form-label">Google Redirect URIs (1 บรรทัดต่อ 1 URI)</label>
                    <textarea id="redirect_uris" name="redirect_uris" class="form-input" required><?php echo h(join_uris($web['redirect_uris'] ?? [])); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="project_id" class="form-label">Google Project ID</label>
                    <input type="text" id="project_id" name="project_id" class="form-input" value="<?php echo h($web['project_id'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="auth_uri" class="form-label">Google Auth URI</label>
                    <input type="text" id="auth_uri" name="auth_uri" class="form-input" value="<?php echo h($web['auth_uri'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="token_uri" class="form-label">Google Token URI</label>
                    <input type="text" id="token_uri" name="token_uri" class="form-input" value="<?php echo h($web['token_uri'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="auth_provider_x509_cert_url" class="form-label">Google Auth Provider x509 Cert URL</label>
                    <input type="text" id="auth_provider_x509_cert_url" name="auth_provider_x509_cert_url" class="form-input" value="<?php echo h($web['auth_provider_x509_cert_url'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary mt-6 w-full" style="width: 100%;">บันทึกการตั้งค่า</button>
    </form>
</div>

<style>
@media (max-width: 768px) {
    .md\:grid-cols-2 {
        grid-template-columns: repeat(1, 1fr);
    }
    .md\:col-span-2 {
        grid-column: span 1 / span 1;
    }
}
</style>

</body>
</html> 