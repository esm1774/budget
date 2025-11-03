<?php
class FileUpload {
    private $upload_dir = '../uploads/';
    private $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
    private $max_size = 5242880; // 5MB
    
    public function __construct() {
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    public function upload($file, $subfolder = '') {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'فشل رفع الملف'];
        }
        
        // التحقق من حجم الملف
        if ($file['size'] > $this->max_size) {
            return ['success' => false, 'message' => 'حجم الملف كبير جداً (الحد الأقصى 5MB)'];
        }
        
        // التحقق من نوع الملف
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $this->allowed_types)) {
            return ['success' => false, 'message' => 'نوع الملف غير مسموح'];
        }
        
        //
        $file_name = uniqid() . '_' . time() . '.' . $file_ext;
    // تحديد المسار
    $target_dir = $this->upload_dir;
    if ($subfolder) {
        $target_dir .= $subfolder . '/';
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
    }
    
    $target_path = $target_dir . $file_name;
    
    // نقل الملف
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return [
            'success' => true,
            'file_name' => $file['name'],
            'file_path' => str_replace('../', '', $target_path),
            'file_type' => $file['type'],
            'file_size' => $file['size']
        ];
    }
    
    return ['success' => false, 'message' => 'فشل حفظ الملف'];
}

public function delete($file_path) {
    $full_path = '../' . $file_path;
    if (file_exists($full_path)) {
        return unlink($full_path);
    }
    return false;
}
}
?>


