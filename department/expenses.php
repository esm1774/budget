<?php
// تفعيل عرض الأخطاء (للتطوير فقط)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/file_upload.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireDepartment();

$department_id = $_SESSION['department_id'];
$message = '';
$error = '';

// إضافة نفقة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    try {
        // التحقق من البيانات
        $expense_date = $_POST['expense_date'] ?? '';
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $vendor_name = trim($_POST['vendor_name'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        // التحقق من صحة البيانات
        if (empty($expense_date)) {
            throw new Exception('تاريخ النفقة مطلوب');
        }
        if (empty($category)) {
            throw new Exception('الفئة مطلوبة');
        }
        if (empty($description)) {
            throw new Exception('الوصف مطلوب');
        }
        if ($amount <= 0) {
            throw new Exception('المبلغ يجب أن يكون أكبر من صفر');
        }
        
        // بدء المعاملة
        $db->beginTransaction();
        
        // إدراج النفقة
        $query = "INSERT INTO expenses (
                    department_id, expense_date, category, description, amount, 
                    payment_method, vendor_name, notes, created_by
                  ) VALUES (
                    :dept_id, :expense_date, :category, :description, :amount, 
                    :payment_method, :vendor_name, :notes, :created_by
                  )";
        
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            throw new Exception('فشل في تحضير الاستعلام: ' . implode(', ', $db->errorInfo()));
        }
        
        $user_id = $_SESSION['user_id'];
        
        $stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
        $stmt->bindParam(':expense_date', $expense_date, PDO::PARAM_STR);
        $stmt->bindParam(':category', $category, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':amount', $amount, PDO::PARAM_STR);
        $stmt->bindParam(':payment_method', $payment_method, PDO::PARAM_STR);
        $stmt->bindParam(':vendor_name', $vendor_name, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
        $stmt->bindParam(':created_by', $user_id, PDO::PARAM_INT);
        
        if (!$stmt->execute()) {
            throw new Exception('فشل في تنفيذ الاستعلام: ' . implode(', ', $stmt->errorInfo()));
        }
        
        $expense_id = $db->lastInsertId();
        
        if (!$expense_id) {
            throw new Exception('فشل في الحصول على معرف النفقة');
        }
        
        // رفع الفواتير
        if (isset($_FILES['invoices']) && !empty($_FILES['invoices']['name'][0])) {
            $fileUpload = new FileUpload();
            $files = $_FILES['invoices'];
            $file_count = count($files['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    ];
                    
                    try {
                        $result = $fileUpload->upload($file, 'invoices');
                        
                        if ($result['success']) {
                            $query = "INSERT INTO invoices (
                                        expense_id, file_name, file_path, file_type, file_size
                                      ) VALUES (
                                        :expense_id, :file_name, :file_path, :file_type, :file_size
                                      )";
                            
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':expense_id', $expense_id, PDO::PARAM_INT);
                            $stmt->bindParam(':file_name', $result['file_name'], PDO::PARAM_STR);
                            $stmt->bindParam(':file_path', $result['file_path'], PDO::PARAM_STR);
                            $stmt->bindParam(':file_type', $result['file_type'], PDO::PARAM_STR);
                            $stmt->bindParam(':file_size', $result['file_size'], PDO::PARAM_INT);
                            $stmt->execute();
                        } else {
                            error_log("File upload failed: " . ($result['message'] ?? 'Unknown error'));
                        }
                    } catch (Exception $e) {
                        error_log("File upload exception: " . $e->getMessage());
                        // نكمل حتى لو فشل رفع الملف
                    }
                }
            }
        }
        
        // تحديث مجموع المصروفات
        $query = "UPDATE departments 
                  SET spent_amount = (
                      SELECT COALESCE(SUM(amount), 0) 
                      FROM expenses 
                      WHERE department_id = :dept_id1
                  ) 
                  WHERE id = :dept_id2";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':dept_id1', $department_id, PDO::PARAM_INT);
        $stmt->bindParam(':dept_id2', $department_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // تأكيد المعاملة
        $db->commit();
        
        // تسجيل النشاط
        $auth->logActivity(
            $_SESSION['user_id'], 
            'create_expense', 
            'expenses', 
            $expense_id, 
            "إضافة نفقة: $category"
        );
        
        $message = 'تم إضافة النفقة بنجاح';
        
        // إعادة توجيه لتجنب إعادة إرسال النموذج
        header("Location: expenses.php?success=1");
        exit;
        
    } catch(PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log("Database error in expenses.php: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        $error = 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage();
        
    } catch(Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log("General error in expenses.php: " . $e->getMessage());
        $error = $e->getMessage();
    }
}

// رسالة النجاح من إعادة التوجيه
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = 'تم إضافة النفقة بنجاح';
}

// جلب جميع النفقات
try {
    $query = "SELECT e.*, 
              (SELECT COUNT(*) FROM invoices WHERE expense_id = e.id) as invoice_count
              FROM expenses e 
              WHERE e.department_id = :dept_id 
              ORDER BY e.expense_date DESC, e.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
    $stmt->execute();
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching expenses: " . $e->getMessage());
    $expenses = [];
    $error = 'حدث خطأ أثناء جلب النفقات';
}

// جلب بيانات القسم
try {
    $query = "SELECT name_ar FROM departments WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $department_id, PDO::PARAM_INT);
    $stmt->execute();
    $department = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$department) {
        throw new Exception('القسم غير موجود');
    }
} catch(PDOException $e) {
    error_log("Error fetching department: " . $e->getMessage());
    $department = ['name_ar' => 'القسم'];
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة النفقات</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">💼 <?php echo htmlspecialchars($department['name_ar']); ?></div>
                <ul class="nav-menu" id="navMenu">
                    <li><a href="dashboard.php">الرئيسية</a></li>
                    <li><a href="expenses.php">النفقات</a></li>
                    <li><a href="distributions.php">الدفعات المستلمة</a></li>
                    <li><a href="report.php">التقرير</a></li>
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

    <main class="main-content">
        <div class="container">
            <h1 class="mb-3">إدارة النفقات</h1>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($message); ?>
                    <button onclick="this.parentElement.style.display='none'" style="float:left;background:none;border:none;font-size:1.2rem;cursor:pointer;">×</button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                    <button onclick="this.parentElement.style.display='none'" style="float:left;background:none;border:none;font-size:1.2rem;cursor:pointer;">×</button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header flex flex-between flex-center">
                    <span>إضافة نفقة جديدة</span>
                    <button class="btn btn-primary" onclick="toggleForm()">+ نفقة جديدة</button>
                </div>
                
                <div id="newExpenseForm" style="display: none; padding: 1.5rem; border-top: 1px solid var(--border-color);">
                    <form method="POST" action="" enctype="multipart/form-data" onsubmit="return validateForm()">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="form-group">
                            <label class="form-label">تاريخ النفقة *</label>
                            <input type="date" name="expense_date" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">الفئة *</label>
                            <select name="category" class="form-select" required>
                                <option value="">اختر الفئة</option>
                                <option value="رواتب">رواتب</option>
                                <option value="مستلزمات مكتبية">مستلزمات مكتبية</option>
                                <option value="صيانة">صيانة</option>
                                <option value="مرافق">مرافق (كهرباء، ماء)</option>
                                <option value="اتصالات">اتصالات</option>
                                <option value="سفر وتنقل">سفر وتنقل</option>
                                <option value="تدريب">تدريب</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">الوصف *</label>
                            <textarea name="description" class="form-textarea" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">المبلغ (ر.س) *</label>
                            <input type="number" name="amount" class="form-input" step="0.01" required min="0.01">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">طريقة الدفع</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">نقداً</option>
                                <option value="bank_transfer">تحويل بنكي</option>
                                <option value="check">شيك</option>
                                <option value="credit_card">بطاقة ائتمان</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">اسم المورد</label>
                            <input type="text" name="vendor_name" class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">ملاحظات</label>
                            <textarea name="notes" class="form-textarea" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">رفع الفواتير (PDF، صور)</label>
                            <div class="file-upload" onclick="document.getElementById('invoiceFiles').click()">
                                <div style="font-size: 3rem; margin-bottom: 1rem;">📎</div>
                                <p>اضغط لاختيار الملفات</p>
                                <p style="font-size: 0.875rem; color: #6b7280;">PDF, JPG, PNG (حد أقصى 5MB لكل ملف)</p>
                            </div>
                            <input type="file" id="invoiceFiles" name="invoices[]" multiple accept=".pdf,.jpg,.jpeg,.png,.gif" style="display: none;" onchange="displaySelectedFiles(this)">
                            <div id="selectedFiles" style="margin-top: 1rem;"></div>
                        </div>
                        
                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-success">حفظ النفقة</button>
                            <button type="button" class="btn btn-secondary" onclick="toggleForm()">إلغاء</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">قائمة النفقات</div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>الفئة</th>
                                <th>الوصف</th>
                                <th>المبلغ</th>
                                <th>طريقة الدفع</th>
                                <th>الفواتير</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($expenses) > 0): ?>
                                <?php foreach ($expenses as $expense): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d', strtotime($expense['expense_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($expense['category']); ?></td>
                                        <td><?php echo htmlspecialchars(mb_substr($expense['description'], 0, 40)); ?>...</td>
                                        <td style="font-weight: bold; color: #ef4444;">
                                            <?php echo number_format($expense['amount'], 2); ?> ر.س
                                        </td>
                                        <td>
                                            <?php 
                                            $payment_methods = [
                                                'cash' => 'نقداً',
                                                'bank_transfer' => 'تحويل بنكي',
                                                'check' => 'شيك',
                                                'credit_card' => 'بطاقة ائتمان'
                                            ];
                                            echo $payment_methods[$expense['payment_method']] ?? $expense['payment_method'];
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($expense['invoice_count'] > 0): ?>
                                                <span style="background: #d1fae5; color: #065f46; padding: 0.25rem 0.5rem; border-radius: 4px;">
                                                    <?php echo $expense['invoice_count']; ?> 📎
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #6b7280;">لا توجد</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="expense_details.php?id=<?php echo $expense['id']; ?>" 
                                               class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                                التفاصيل
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">لا توجد نفقات مسجلة</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleForm() {
            const form = document.getElementById('newExpenseForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
        
        function validateForm() {
            const amount = document.querySelector('input[name="amount"]').value;
            if (parseFloat(amount) <= 0) {
                alert('المبلغ يجب أن يكون أكبر من صفر');
                return false;
            }
            return true;
        }
        
        function displaySelectedFiles(input) {
            const container = document.getElementById('selectedFiles');
            container.innerHTML = '';
            
            if (input.files.length > 0) {
                const fileList = document.createElement('div');
                fileList.style.cssText = 'background: #f9fafb; padding: 1rem; border-radius: 6px;';
                
                for (let i = 0; i < input.files.length; i++) {
                    const file = input.files[i];
                    const fileSize = (file.size / 1024).toFixed(2);
                    
                    // التحقق من حجم الملف
                    if (file.size > 5 * 1024 * 1024) {
                        alert(`الملف ${file.name} أكبر من 5MB`);
                        continue;
                    }
                    
                    const fileItem = document.createElement('div');
                    fileItem.style.cssText = 'padding: 0.5rem; border-bottom: 1px solid #e5e7eb;';
                    fileItem.innerHTML = `
                        <span style="color: #10b981;">✓</span> 
                        ${file.name} 
                        <span style="color: #6b7280; font-size: 0.875rem;">
                            (${fileSize} KB)
                        </span>
                    `;
                    fileList.appendChild(fileItem);
                }
                
                container.appendChild(fileList);
            }
        }
        
        function toggleMenu() {
            document.getElementById('navMenu').classList.toggle('active');
        }
        
        // إخفاء الرسائل تلقائياً بعد 5 ثواني
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>