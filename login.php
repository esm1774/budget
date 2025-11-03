<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// إذا كان المستخدم مسجل دخوله، إعادة توجيهه
if ($auth->isLoggedIn()) {
    if ($auth->isAdmin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: department/dashboard.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
    } else {
        $result = $auth->login($username, $password);
        if ($result['success']) {
            if ($result['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: department/dashboard.php');
            }
            exit();
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام إدارة الميزانيات</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">💼</div>
                <h2>نظام إدارة الميزانيات</h2>
                <p style="color: #6b7280;">تسجيل الدخول إلى حسابك</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
                <div class="alert alert-warning">غير مصرح لك بالوصول إلى هذه الصفحة</div>
            <?php endif; ?>
            
            <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
                <div class="alert alert-success">تم تسجيل الخروج بنجاح</div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">اسم المستخدم</label>
                    <input type="text" name="username" class="form-input" required autofocus>
                </div>
                
                <div class="form-group">
                    <label class="form-label">كلمة المرور</label>
                    <input type="password" name="password" class="form-input" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    تسجيل الدخول
                </button>
            </form>
        </div>
    </div>
</body>
</html>