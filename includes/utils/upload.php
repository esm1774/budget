<?php
function handleFileUpload($expenseId, $files) {
    global $pdo;
    
    $uploadPath = UPLOAD_PATH . "expenses/{$expenseId}/";
    
    if (!file_exists($uploadPath)) {
        mkdir($uploadPath, 0777, true);
    }
    
    foreach ($files['tmp_name'] as $key => $tmp_name) {
        if ($files['error'][$key] === UPLOAD_ERR_OK) {
            $fileName = uniqid() . '_' . $files['name'][$key];
            $filePath = $uploadPath . $fileName;
            
            if (move_uploaded_file($tmp_name, $filePath)) {
                // حفظ معلومات الملف في قاعدة البيانات
                $sql = "INSERT INTO expense_invoices (expense_id, file_name, original_name, file_path, file_size) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $expenseId,
                    $fileName,
                    $files['name'][$key],
                    $filePath,
                    $files['size'][$key]
                ]);
            }
        }
    }
}
?>