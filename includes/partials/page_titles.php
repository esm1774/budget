<?php
// includes/page_titles.php

$page_titles = [
    'dashboard.php' => 'لوحة التحكم',
    'budget_batches.php' => 'إدارة الدفعات المالية',
    'departments.php' => 'إدارة الأقسام',
    'expenses.php' => 'إدارة النفقات',
    'reports.php' => 'التقارير والإحصائيات',
    'distributions.php' => 'العهد المستلمة',
    'report.php' => 'تقارير القسم'
];

// الحصول على اسم الصفحة الحالية
$current_page = basename($_SERVER['PHP_SELF']);

// تحديد العنوان بناءً على الصفحة الحالية
$page_title = $page_titles[$current_page] ?? 'نظام إدارة الميزانيات';
?>