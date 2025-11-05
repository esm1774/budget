<?php
// includes/header.php

// تضمين ملف العناوين
require_once 'page_titles.php';
$user_role = $_SESSION['role'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - نظام إدارة الميزانيات</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@200;300;400;500;700;800;900&display=swap" rel="stylesheet">
<!-- CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico"></head>
<body>
    <?php
    // إذا كان المستخدم أدمن
    if ($user_role === 'admin'): ?>
        <header class="header">
            <div class="container">
                <nav class="navbar">
                    <div class="logo">💼 نظام إدارة الميزانيات</div>
                    <ul class="nav-menu" id="navMenu">
                        <li><a href="dashboard.php" <?php echo ($current_page == 'dashboard.php') ? 'class="active"' : ''; ?>>الرئيسية</a></li>
                        <li><a href="budget_batches.php" <?php echo ($current_page == 'budget_batches.php') ? 'class="active"' : ''; ?>>الدفعات المالية</a></li>
                        <li><a href="departments.php" <?php echo ($current_page == 'departments.php') ? 'class="active"' : ''; ?>>الأقسام</a></li>
                        <li><a href="expenses.php" <?php echo ($current_page == 'expenses.php') ? 'class="active"' : ''; ?>>نفقات الإدارة</a></li>
                        <li><a href="reports.php" <?php echo ($current_page == 'reports.php') ? 'class="active"' : ''; ?>>التقارير</a></li>
                    </ul>
                    <div class="user-info">
                        <span>مرحباً، <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                        <a href="../logout.php" class="btn btn-danger">خروج</a>
                    </div>
                    <div class="menu-toggle" onclick="toggleMenu()">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </nav>
            </div>
        </header>

    <?php else: // غير الأدمن (الأقسام أو المستخدمين الآخرين) ?>
        <header class="header no-print">
            <div class="container">
                <nav class="navbar">
                    <div class="logo">💼 <?php echo htmlspecialchars($department['name_ar'] ?? 'القسم'); ?></div>
                    <ul class="nav-menu" id="navMenu">
                        <li><a href="dashboard.php" <?php echo ($current_page == 'dashboard.php') ? 'class="active"' : ''; ?>>الرئيسية</a></li>
                        <li><a href="expenses.php" <?php echo ($current_page == 'expenses.php') ? 'class="active"' : ''; ?>>المصروفات</a></li>
                        <li><a href="distributions.php" <?php echo ($current_page == 'distributions.php') ? 'class="active"' : ''; ?>>العهد المستلمة</a></li>
                        <li><a href="report.php" <?php echo ($current_page == 'report.php') ? 'class="active"' : ''; ?>>التقرير</a></li>
                    </ul>
                    <div class="user-info">
                        <span>مرحباً، <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                        <a href="../logout.php" class="btn btn-danger">خروج</a>
                    </div>
                    <div class="menu-toggle" onclick="toggleMenu()">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </nav>
            </div>
        </header>
    <?php endif; ?>