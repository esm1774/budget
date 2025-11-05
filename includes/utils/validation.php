<?php
function validateExpenseData($data) {
    $errors = [];
    
    if (empty($data['expense_date'])) {
        $errors[] = 'تاريخ النفقة مطلوب';
    }
    
    if (empty($data['category'])) {
        $errors[] = 'فئة النفقة مطلوبة';
    }
    
    if (empty($data['amount']) || $data['amount'] <= 0) {
        $errors[] = 'المبلغ يجب أن يكون أكبر من صفر';
    }
    
    if (empty($data['description']) || strlen(trim($data['description'])) < 10) {
        $errors[] = 'الوصف يجب أن يكون على الأقل 10 أحرف';
    }
    
    return $errors;
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateFileUpload($files) {
    $errors = [];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    foreach ($files['tmp_name'] as $key => $tmp_name) {
        if ($files['error'][$key] !== UPLOAD_ERR_OK) {
            $errors[] = "خطأ في رفع الملف: {$files['name'][$key]}";
            continue;
        }
        
        if ($files['size'][$key] > $maxSize) {
            $errors[] = "الملف {$files['name'][$key]} أكبر من 5MB";
        }
        
        $fileType = mime_content_type($tmp_name);
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "نوع الملف {$files['name'][$key]} غير مسموح به";
        }
    }
    
    return $errors;
}
?>