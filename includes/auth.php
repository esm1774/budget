<?php
session_start();

class Auth {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    // تسجيل الدخول
    public function login($username, $password) {
        try {
            $query = "SELECT id, username, password_hash, full_name, role, department_id, is_active 
                      FROM users WHERE username = :username AND is_active = 1 LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($password, $user['password_hash'])) {
                    // تحديث آخر دخول
                    $update = "UPDATE users SET last_login = NOW() WHERE id = :id";
                    $stmt_update = $this->db->prepare($update);
                    $stmt_update->bindParam(':id', $user['id']);
                    $stmt_update->execute();
                    
                    // حفظ بيانات الجلسة
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['department_id'] = $user['department_id'];
                    $_SESSION['logged_in'] = true;
                    
                    // تسجيل النشاط
                    $this->logActivity($user['id'], 'login', null, null, 'تسجيل دخول ناجح');
                    
                    return array('success' => true, 'role' => $user['role']);
                }
            }
            return array('success' => false, 'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة');
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return array('success' => false, 'message' => 'حدث خطأ في النظام');
        }
    }
    
    // تسجيل الخروج
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'logout', null, null, 'تسجيل خروج');
        }
        session_unset();
        session_destroy();
    }
    
    // التحقق من تسجيل الدخول
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    // التحقق من صلاحية الإداري
    public function isAdmin() {
        return $this->isLoggedIn() && $_SESSION['role'] === 'admin';
    }
    
    // التحقق من صلاحية القسم
    public function isDepartment() {
        return $this->isLoggedIn() && $_SESSION['role'] === 'department';
    }
    
    // حماية صفحات الإداري
    public function requireAdmin() {
        if (!$this->isAdmin()) {
            header('Location: /login.php?error=unauthorized');
            exit();
        }
    }
    
    // حماية صفحات الأقسام
    public function requireDepartment() {
        if (!$this->isDepartment()) {
            header('Location: /login.php?error=unauthorized');
            exit();
        }
    }
    
    // تسجيل النشاط
    public function logActivity($user_id, $action, $table_name = null, $record_id = null, $details = null) {
        try {
            $query = "INSERT INTO activity_log (user_id, action, table_name, record_id, details, ip_address) 
                      VALUES (:user_id, :action, :table_name, :record_id, :details, :ip_address)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':table_name', $table_name);
            $stmt->bindParam(':record_id', $record_id);
            $stmt->bindParam(':details', $details);
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt->bindParam(':ip_address', $ip);
            $stmt->execute();
        } catch(PDOException $e) {
            error_log("Activity log error: " . $e->getMessage());
        }
    }
    
    // إنشاء مستخدم جديد للقسم
    public function createDepartmentUser($username, $password, $full_name, $department_id) {
        try {
            $query = "INSERT INTO users (username, password_hash, full_name, role, department_id) 
                      VALUES (:username, :password_hash, :full_name, 'department', :department_id)";
            $stmt = $this->db->prepare($query);
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password_hash', $password_hash);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':department_id', $department_id);
            $stmt->execute();
            return array('success' => true, 'user_id' => $this->db->lastInsertId());
        } catch(PDOException $e) {
            error_log("Create user error: " . $e->getMessage());
            return array('success' => false, 'message' => 'فشل إنشاء المستخدم');
        }
    }

    
}
?>