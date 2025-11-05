<?php
// includes/init.php

// المسار الأساسي للمشروع
define('BASE_PATH', dirname(__DIR__));

// تحميل ملفات التهيئة
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

// الاتصال بقاعدة البيانات
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER, 
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// بدء الجلسة إذا لم تكن بدأت
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تضمين الدوال الأساسية
require_once __DIR__ . '/functions/auth_functions.php';
?>