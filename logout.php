<?php
// logout.php

// เรียกใช้ไฟล์ config เพื่อเข้าถึงฟังก์ชันจัดการ Token
require_once 'config.php';

// เรียกใช้ฟังก์ชันเพื่อลบไฟล์ Token ของผู้ใช้
deleteAccessToken();

// ส่งผู้ใช้กลับไปยังหน้าหลัก
header('Location: index.php');
exit();
?>
