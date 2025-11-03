<?php
session_start();

// إذا كان المستخدم مسجل دخوله، إعادة توجيهه للوحة التحكم المناسبة
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'admin') {
            header('Location: admin/dashboard.php');
            exit();
        } elseif ($_SESSION['role'] === 'department') {
            header('Location: department/dashboard.php');
            exit();
        }
    }
}

// إذا لم يكن مسجل دخوله، إعادة توجيهه لصفحة تسجيل الدخول
header('Location: login.php');
exit();
?>