<?php
// تضمين ملف العناوين
require_once 'page_titles.php';

// الحصول على عنوان الصفحة الحالية
$page_title = get_page_title();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - نظام إدارة الميزانيات</title>
    <meta name="description" content="نظام متكامل لإدارة الميزانيات والمصروفات">
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
</head>
<body>