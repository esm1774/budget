<?php
// ثوابت التطبيق
define('USER_ROLES', [
    'admin' => 'مدير النظام',
    'user' => 'مستخدم عادي',
    'department_head' => 'رئيس قسم'
]);

define('EXPENSE_CATEGORIES', [
    'مصاريف تشغيلية',
    'مصاريف صيانة',
    'مصاريف رواتب',
    'مصاريف تسويق',
    'مصاريف إدارية',
    'مصاريف أخرى'
]);

define('PAYMENT_METHODS', [
    'cash' => 'نقداً',
    'bank_transfer' => 'تحويل بنكي',
    'check' => 'شيك',
    'credit_card' => 'بطاقة ائتمان'
]);
?>