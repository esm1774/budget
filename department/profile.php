<?php
require_once '../config/database.php';
require_once '../includes/file_upload.php';

$database = new Database();
$db = $database->getConnection();

// بدء الجلسة إذا لم تبدأ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التحقق من تسجيل الدخول يدوياً
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// دالة تسجيل النشاط
function logActivity($db, $user_id, $action, $table_name, $record_id, $description = '') {
    try {
        $query = "INSERT INTO user_activities (user_id, action, table_name, record_id, description) 
                  VALUES (:user_id, :action, :table_name, :record_id, :description)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':table_name', $table_name);
        $stmt->bindParam(':record_id', $record_id, PDO::PARAM_INT);
        $stmt->bindParam(':description', $description);
        $stmt->execute();
        return true;
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
        return false;
    }
}

// جلب بيانات المستخدم الحالي
try {
    $query = "SELECT u.*, d.name_ar as department_name 
              FROM users u 
              LEFT JOIN departments d ON u.department_id = d.id 
              WHERE u.id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('المستخدم غير موجود');
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

// تحديث البيانات الشخصية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    try {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $position = trim($_POST['position'] ?? '');

        if (empty($full_name)) throw new Exception('الاسم الكامل مطلوب');
        if (empty($email)) throw new Exception('البريد الإلكتروني مطلوب');
        
        // التحقق من صحة البريد الإلكتروني
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('صيغة البريد الإلكتروني غير صحيحة');
        }

        // التحقق من عدم استخدام البريد الإلكتروني من قبل مستخدم آخر
        $checkEmailQuery = "SELECT id FROM users WHERE email = :email AND id != :user_id";
        $checkEmailStmt = $db->prepare($checkEmailQuery);
        $checkEmailStmt->bindParam(':email', $email);
        $checkEmailStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $checkEmailStmt->execute();
        
        if ($checkEmailStmt->rowCount() > 0) {
            throw new Exception('البريد الإلكتروني مستخدم من قبل مستخدم آخر');
        }

        $query = "UPDATE users SET 
                  full_name = :full_name, 
                  email = :email, 
                  phone = :phone,
                  position = :position,
                  updated_at = NOW()
                  WHERE id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':position', $position);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        // تحديث بيانات الجلسة
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
        $_SESSION['position'] = $position;

        // تسجيل النشاط
        logActivity($db, $user_id, 'update_profile', 'users', $user_id, "تحديث الملف الشخصي");

        $message = 'تم تحديث البيانات الشخصية بنجاح';
        
        // إعادة جلب بيانات المستخدم
        $query = "SELECT u.*, d.name_ar as department_name 
                  FROM users u 
                  LEFT JOIN departments d ON u.department_id = d.id 
                  WHERE u.id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// تغيير كلمة المرور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    try {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password)) throw new Exception('كلمة المرور الحالية مطلوبة');
        if (empty($new_password)) throw new Exception('كلمة المرور الجديدة مطلوبة');
        if (empty($confirm_password)) throw new Exception('تأكيد كلمة المرور مطلوب');

        // التحقق من كلمة المرور الحالية
        $checkQuery = "SELECT password_hash FROM users WHERE id = :user_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $checkStmt->execute();
        $user_data = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($current_password, $user_data['password_hash'])) {
            throw new Exception('كلمة المرور الحالية غير صحيحة');
        }

        if ($new_password !== $confirm_password) {
            throw new Exception('كلمة المرور الجديدة وتأكيدها غير متطابقين');
        }

        if (strlen($new_password) < 6) {
            throw new Exception('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
        }

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $query = "UPDATE users SET 
                  password_hash = :password_hash,
                  updated_at = NOW()
                  WHERE id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':password_hash', $hashed_password);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        // تسجيل النشاط
        logActivity($db, $user_id, 'change_password', 'users', $user_id, "تغيير كلمة المرور");

        $message = 'تم تغيير كلمة المرور بنجاح';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// تحديث الصورة الشخصية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_avatar') {
    try {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $fileUpload = new FileUpload();
            $file = $_FILES['avatar'];
            
            // حذف الصورة القديمة إذا كانت موجودة
            if (!empty($user['avatar'])) {
                $old_avatar_path = '../' . $user['avatar'];
                if (file_exists($old_avatar_path)) {
                    unlink($old_avatar_path);
                }
            }
            
            $result = $fileUpload->uploadAvatar($file, $user_id);
            if ($result['success']) {
                $avatar_path = $result['file_path'];
                
                $query = "UPDATE users SET 
                          avatar = :avatar,
                          updated_at = NOW()
                          WHERE id = :user_id";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':avatar', $avatar_path);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->execute();
                
                // تحديث الجلسة
                $_SESSION['avatar'] = $avatar_path;
                
                // تسجيل النشاط
                logActivity($db, $user_id, 'update_avatar', 'users', $user_id, "تحديث الصورة الشخصية");
                
                $message = 'تم تحديث الصورة الشخصية بنجاح';
                
                // إعادة جلب بيانات المستخدم
                $query = "SELECT u.*, d.name_ar as department_name 
                          FROM users u 
                          LEFT JOIN departments d ON u.department_id = d.id 
                          WHERE u.id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                throw new Exception($result['message'] ?? 'حدث خطأ أثناء رفع الصورة');
            }
        } else {
            throw new Exception('لم تقم باختيار صورة');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

?>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <h1>
                    <i class="fas fa-user-circle"></i>
                    الملف الشخصي
                </h1>
                <div class="breadcrumb">
                    <a href="dashboard.php">الرئيسية</a>
                    <span>/</span>
                    <span>الملف الشخصي</span>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="profile-grid">
                <!-- البطاقة الأولى: المعلومات الشخصية -->
                <div class="card">
                    <div class="card-header">
                        <h2>
                            <i class="fas fa-user"></i>
                            المعلومات الشخصية
                        </h2>
                    </div>
                    <div class="profile-info">
                        <div class="avatar-section">
                            <div class="avatar-container">
                                <img src="<?php echo !empty($user['avatar']) ? '../' . htmlspecialchars($user['avatar']) : '../assets/images/default-avatar.png'; ?>" 
                                     alt="الصورة الشخصية" 
                                     class="avatar"
                                     id="avatarPreview">
                                <form method="POST" enctype="multipart/form-data" class="avatar-form">
                                    <input type="hidden" name="action" value="update_avatar">
                                    <input type="file" name="avatar" id="avatarInput" accept="image/*" style="display: none;">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('avatarInput').click()">
                                        <i class="fas fa-camera"></i>
                                        تغيير الصورة
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <form method="POST" class="profile-form">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="full_name" class="form-label">
                                        <i class="fas fa-signature"></i>
                                        الاسم الكامل *
                                    </label>
                                    <input type="text" 
                                           id="full_name" 
                                           name="full_name" 
                                           class="form-input" 
                                           value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope"></i>
                                        البريد الإلكتروني *
                                    </label>
                                    <input type="email" 
                                           id="email" 
                                           name="email" 
                                           class="form-input" 
                                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="phone" class="form-label">
                                        <i class="fas fa-phone"></i>
                                        رقم الهاتف
                                    </label>
                                    <input type="tel" 
                                           id="phone" 
                                           name="phone" 
                                           class="form-input" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="position" class="form-label">
                                        <i class="fas fa-briefcase"></i>
                                        المسمى الوظيفي
                                    </label>
                                    <input type="text" 
                                           id="position" 
                                           name="position" 
                                           class="form-input" 
                                           value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-building"></i>
                                        القسم
                                    </label>
                                    <input type="text" 
                                           class="form-input" 
                                           value="<?php echo htmlspecialchars($user['department_name'] ?? 'لا يوجد'); ?>" 
                                           disabled
                                           style="background-color: #f9fafb;">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-user-tag"></i>
                                        الصلاحية
                                    </label>
                                    <input type="text" 
                                           class="form-input" 
                                           value="<?php 
                                               $role_text = '';
                                               if ($user['role'] === 'admin') $role_text = 'مدير النظام';
                                               elseif ($user['role'] === 'department') $role_text = 'مدير قسم';
                                               else $role_text = 'مستخدم';
                                               echo htmlspecialchars($role_text); 
                                           ?>" 
                                           disabled
                                           style="background-color: #f9fafb;">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-user"></i>
                                        اسم المستخدم
                                    </label>
                                    <input type="text" 
                                           class="form-input" 
                                           value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" 
                                           disabled
                                           style="background-color: #f9fafb;">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-calendar-alt"></i>
                                        تاريخ التسجيل
                                    </label>
                                    <input type="text" 
                                           class="form-input" 
                                           value="<?php echo htmlspecialchars($user['created_at'] ?? ''); ?>" 
                                           disabled
                                           style="background-color: #f9fafb;">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                حفظ التغييرات
                            </button>
                        </form>
                    </div>
                </div>

                <!-- البطاقة الثانية: تغيير كلمة المرور -->
                <div class="card">
                    <div class="card-header">
                        <h2>
                            <i class="fas fa-lock"></i>
                            تغيير كلمة المرور
                        </h2>
                    </div>
                    <div class="password-form">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group">
                                <label for="current_password" class="form-label">
                                    <i class="fas fa-key"></i>
                                    كلمة المرور الحالية *
                                </label>
                                <input type="password" 
                                       id="current_password" 
                                       name="current_password" 
                                       class="form-input" 
                                       required
                                       minlength="6">
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password" class="form-label">
                                    <i class="fas fa-lock"></i>
                                    كلمة المرور الجديدة *
                                </label>
                                <input type="password" 
                                       id="new_password" 
                                       name="new_password" 
                                       class="form-input" 
                                       required
                                       minlength="6">
                                <small class="form-text">يجب أن تكون كلمة المرور 6 أحرف على الأقل</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock"></i>
                                    تأكيد كلمة المرور *
                                </label>
                                <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       class="form-input" 
                                       required
                                       minlength="6">
                            </div>
                            
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-sync-alt"></i>
                                تغيير كلمة المرور
                            </button>
                        </form>
                    </div>
                </div>

                <!-- البطاقة الثالثة: الإحصائيات والنشاط -->
                <div class="card">
                    <div class="card-header">
                        <h2>
                            <i class="fas fa-chart-bar"></i>
                            الإحصائيات والنشاط
                        </h2>
                    </div>
                    <div class="stats-section">
                        <div class="stats-grid">
                            <?php
                            // جلب إحصائيات المستخدم
                            try {
                                // عدد النفقات
                                $expensesQuery = "SELECT COUNT(*) as count FROM expenses WHERE created_by = :user_id";
                                $expensesStmt = $db->prepare($expensesQuery);
                                $expensesStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                                $expensesStmt->execute();
                                $expenses_count = $expensesStmt->fetch(PDO::FETCH_ASSOC)['count'];
                                
                                // إجمالي المصروفات
                                $totalExpensesQuery = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE created_by = :user_id";
                                $totalExpensesStmt = $db->prepare($totalExpensesQuery);
                                $totalExpensesStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                                $totalExpensesStmt->execute();
                                $total_expenses = $totalExpensesStmt->fetch(PDO::FETCH_ASSOC)['total'];
                                
                                // آخر نشاط
                                $lastActivityQuery = "SELECT action, timestamp FROM user_activities 
                                                     WHERE user_id = :user_id 
                                                     ORDER BY timestamp DESC 
                                                     LIMIT 1";
                                $lastActivityStmt = $db->prepare($lastActivityQuery);
                                $lastActivityStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                                $lastActivityStmt->execute();
                                $last_activity = $lastActivityStmt->fetch(PDO::FETCH_ASSOC);
                                
                            } catch (Exception $e) {
                                $expenses_count = 0;
                                $total_expenses = 0;
                                $last_activity = null;
                            }
                            ?>
                            
                            <div class="stat-card">
                                <div class="stat-label">
                                    <i class="fas fa-receipt"></i>
                                    عدد النفقات
                                </div>
                                <div class="stat-value"><?php echo $expenses_count; ?></div>
                            </div>
                            
                            <div class="stat-card success">
                                <div class="stat-label">
                                    <i class="fas fa-money-bill-wave"></i>
                                    إجمالي المصروفات
                                </div>
                                <div class="stat-value"><?php echo number_format($total_expenses, 2); ?> ر.س</div>
                            </div>
                            
                            <div class="stat-card warning">
                                <div class="stat-label">
                                    <i class="fas fa-clock"></i>
                                    آخر نشاط
                                </div>
                                <div class="stat-value" style="font-size: 1.1rem;">
                                    <?php 
                                    if ($last_activity) {
                                        $action_text = '';
                                        switch ($last_activity['action']) {
                                            case 'create_expense': $action_text = 'إضافة نفقة'; break;
                                            case 'update_expense': $action_text = 'تحديث نفقة'; break;
                                            case 'delete_expense': $action_text = 'حذف نفقة'; break;
                                            case 'update_profile': $action_text = 'تحديث الملف'; break;
                                            case 'change_password': $action_text = 'تغيير كلمة المرور'; break;
                                            default: $action_text = $last_activity['action'];
                                        }
                                        echo $action_text . '<br><small>' . $last_activity['timestamp'] . '</small>';
                                    } else {
                                        echo 'لا يوجد نشاط';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script>
    // معالجة تغيير الصورة الشخصية
    document.getElementById('avatarInput').addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('avatarPreview').src = e.target.result;
            }
            reader.readAsDataURL(this.files[0]);
            
            // إرسال النموذج تلقائياً
            document.querySelector('.avatar-form').submit();
        }
    });

    // التحقق من تطابق كلمة المرور
    document.querySelector('form[action="change_password"]')?.addEventListener('submit', function(e) {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('كلمة المرور الجديدة وتأكيدها غير متطابقين');
            return false;
        }
        
        if (newPassword.length < 6) {
            e.preventDefault();
            alert('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
            return false;
        }
    });
    </script>

    <style>
    .profile-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
    }
    
    .profile-info {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 2rem;
        align-items: start;
    }
    
    .avatar-section {
        text-align: center;
    }
    
    .avatar-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
    }
    
    .avatar {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid var(--primary-color);
        box-shadow: var(--shadow-md);
    }
    
    .profile-form,
    .password-form {
        width: 100%;
    }
    
    .stats-section {
        padding: 1rem 0;
    }
    
    .form-text {
        color: var(--text-secondary);
        font-size: 0.875rem;
        margin-top: 0.25rem;
        display: block;
    }
    
    @media (max-width: 968px) {
        .profile-grid {
            grid-template-columns: 1fr;
        }
        
        .profile-info {
            grid-template-columns: 1fr;
            text-align: center;
        }
        
        .avatar-container {
            margin-bottom: 1rem;
        }
    }
    
    @media (max-width: 768px) {
        .avatar {
            width: 120px;
            height: 120px;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</body>
</html>