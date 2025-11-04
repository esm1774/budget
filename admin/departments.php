<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireAdmin();

$message = '';
$error = '';

// إضافة قسم جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name_ar = trim($_POST['name_ar']);
    $name_en = trim($_POST['name_en']);
    $code = trim($_POST['code']);
    $allocated_budget = floatval($_POST['allocated_budget']);
    $description = trim($_POST['description']);
    
    // بيانات المستخدم
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    
    if (empty($name_ar) || empty($code) || empty($username) || empty($password)) {
        $error = 'يرجى ملء جميع الحقول المطلوبة';
    } else {
        try {
            $db->beginTransaction();
            
            // إدراج القسم
            $query = "INSERT INTO departments (name_ar, name_en, code, allocated_budget, description) 
                      VALUES (:name_ar, :name_en, :code, :allocated_budget, :description)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name_ar', $name_ar);
            $stmt->bindParam(':name_en', $name_en);
            $stmt->bindParam(':code', $code);
            $stmt->bindParam(':allocated_budget', $allocated_budget);
            $stmt->bindParam(':description', $description);
            $stmt->execute();
            
            $department_id = $db->lastInsertId();
            
            // إنشاء مستخدم للقسم
            $result = $auth->createDepartmentUser($username, $password, $full_name, $department_id);
            
            if ($result['success']) {
                $db->commit();
                $auth->logActivity($_SESSION['user_id'], 'create_department', 'departments', $department_id, "إنشاء قسم: $name_ar");
                $message = 'تم إضافة القسم بنجاح';
            } else {
                $db->rollBack();
                $error = 'فشل إنشاء مستخدم القسم';
            }
        } catch(PDOException $e) {
            $db->rollBack();
            error_log("Create department error: " . $e->getMessage());
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = 'الكود أو اسم المستخدم مستخدم بالفعل';
            } else {
                $error = 'حدث خطأ أثناء إضافة القسم';
            }
        }
    }
}

// تحديث ميزانية قسم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_budget') {
    $department_id = intval($_POST['department_id']);
    $new_budget = floatval($_POST['allocated_budget']);
    
    try {
        $query = "UPDATE departments SET allocated_budget = :budget WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':budget', $new_budget);
        $stmt->bindParam(':id', $department_id);
        $stmt->execute();
        
        $auth->logActivity($_SESSION['user_id'], 'update_budget', 'departments', $department_id, "تحديث ميزانية القسم");
        $message = 'تم تحديث الميزانية بنجاح';
    } catch(PDOException $e) {
        error_log("Update budget error: " . $e->getMessage());
        $error = 'حدث خطأ أثناء تحديث الميزانية';
    }
}

// جلب الأقسام
$query = "SELECT d.*, 
          (SELECT COUNT(*) FROM users WHERE department_id = d.id) as user_count
          FROM departments d 
          WHERE d.is_active = 1 
          ORDER BY d.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأقسام</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="main-content">
        <div class="container">
            <h1 class="mb-3">إدارة الأقسام</h1>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header flex flex-between flex-center">
                    <span>إضافة قسم جديد</span>
                    <button class="btn btn-primary" onclick="toggleForm()">+ قسم جديد</button>
                </div>
                
                <div id="newDepartmentForm" style="display: none; padding: 1.5rem; border-top: 1px solid var(--border-color);">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="form-group">
                            <label class="form-label">اسم القسم (عربي) *</label>
                            <input type="text" name="name_ar" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">اسم القسم (إنجليزي)</label>
                            <input type="text" name="name_en" class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">كود القسم *</label>
                            <input type="text" name="code" class="form-input" required placeholder="مثال: HR">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">الميزانية المخصصة (ر.س)</label>
                            <input type="number" name="allocated_budget" class="form-input" step="0.01" value="0">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">الوصف</label>
                            <textarea name="description" class="form-textarea"></textarea>
                        </div>
                        
                        <hr style="margin: 2rem 0;">
                        <h3 style="margin-bottom: 1rem;">بيانات تسجيل الدخول للقسم</h3>
                        
                        <div class="form-group">
                            <label class="form-label">اسم المستخدم *</label>
                            <input type="text" name="username" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">كلمة المرور *</label>
                            <input type="password" name="password" class="form-input" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">الاسم الكامل *</label>
                            <input type="text" name="full_name" class="form-input" required>
                        </div>
                        
                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-success">حفظ القسم</button>
                            <button type="button" class="btn btn-secondary" onclick="toggleForm()">إلغاء</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">قائمة الأقسام</div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>اسم القسم</th>
                                <th>الكود</th>
                                <th>إجمالي المستلم</th>
                                <th>المصروف</th>
                                <th>المتبقي</th>
                                <th>آخر دفعة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dept['name_ar']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($dept['code']); ?></strong></td>
                                    <td><?php echo number_format($dept['total_received'], 2); ?> ر.س</td>
                                    <td style="color: #ef4444;"><?php echo number_format($dept['spent_amount'], 2); ?> ر.س</td>
                                    <td style="color: #10b981;">
                                        <?php echo number_format($dept['total_received'] - $dept['spent_amount'], 2); ?> ر.س
                                    </td>
                                    <td>
                                        <?php echo $dept['last_distribution_date'] ? date('Y-m-d', strtotime($dept['last_distribution_date'])) : 'لم يستلم بعد'; ?>
                                    </td>
                                    <td>
                                        <a href="department_details.php?id=<?php echo $dept['id']; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                            عرض التفاصيل
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal لتعديل الميزانية -->
    <div id="budgetModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>تعديل الميزانية</h3>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_budget">
                <input type="hidden" name="department_id" id="editDeptId">
                
                <div class="form-group">
                    <label class="form-label">الميزانية الجديدة (ر.س)</label>
                    <input type="number" name="allocated_budget" id="editBudget" class="form-input" step="0.01" required>
                </div>
                
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-success">حفظ</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleForm() {
            const form = document.getElementById('newDepartmentForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
        
        function editBudget(deptId, currentBudget) {
            document.getElementById('editDeptId').value = deptId;
            document.getElementById('editBudget').value = currentBudget;
            document.getElementById('budgetModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('budgetModal').classList.remove('active');
        }
        
        function toggleMenu() {
            document.getElementById('navMenu').classList.toggle('active');
        }
    </script>
</body>
</html>