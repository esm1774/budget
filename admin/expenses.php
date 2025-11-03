<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/file_upload.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireAdmin();

$message = '';
$error = '';

// إضافة نفقة إدارية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $expense_date = $_POST['expense_date'];
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $payment_method = $_POST['payment_method'];
    $vendor_name = trim($_POST['vendor_name']);
    $notes = trim($_POST['notes']);
    
    if (empty($expense_date) || empty($category) || empty($description) || $amount <= 0) {
        $error = 'يرجى ملء جميع الحقول المطلوبة';
    } else {
        try {
            $db->beginTransaction();
            
            // إدراج النفقة
            $query = "INSERT INTO admin_expenses (expense_date, category, description, amount, 
                      payment_method, vendor_name, notes) 
                      VALUES (:expense_date, :category, :description, :amount, 
                      :payment_method, :vendor_name, :notes)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':expense_date', $expense_date);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':payment_method', $payment_method);
            $stmt->bindParam(':vendor_name', $vendor_name);
            $stmt->bindParam(':notes', $notes);
            $stmt->execute();
            
            $expense_id = $db->lastInsertId();
            
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
                        
                        $result = $fileUpload->upload($file, 'admin_invoices');
                        
                        if ($result['success']) {
                            $query = "INSERT INTO admin_invoices (admin_expense_id, file_name, file_path, file_type, file_size) 
                                      VALUES (:expense_id, :file_name, :file_path, :file_type, :file_size)";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':expense_id', $expense_id);
                            $stmt->bindParam(':file_name', $result['file_name']);
                            $stmt->bindParam(':file_path', $result['file_path']);
                            $stmt->bindParam(':file_type', $result['file_type']);
                            $stmt->bindParam(':file_size', $result['file_size']);
                            $stmt->execute();
                        }
                    }
                }
            }
            
            $db->commit();
            $auth->logActivity($_SESSION['user_id'], 'create_admin_expense', 'admin_expenses', $expense_id, "إضافة نفقة إدارية: $category");
            $message = 'تم إضافة النفقة بنجاح';
        } catch(PDOException $e) {
            $db->rollBack();
            error_log("Create admin expense error: " . $e->getMessage());
            $error = 'حدث خطأ أثناء إضافة النفقة';
        }
    }
}

// جلب النفقات الإدارية
$query = "SELECT ae.*, 
          (SELECT COUNT(*) FROM admin_invoices WHERE admin_expense_id = ae.id) as invoice_count
          FROM admin_expenses ae 
          ORDER BY ae.expense_date DESC, ae.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إجمالي النفقات
$query = "SELECT SUM(amount) as total FROM admin_expenses";
$stmt = $db->prepare($query);
$stmt->execute();
$total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نفقات الإدارة</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">💼 نظام إدارة الميزانيات</div>
                <ul class="nav-menu" id="navMenu">
                    <li><a href="dashboard.php">الرئيسية</a></li>
                    <li><a href="budget_batches.php">الدفعات المالية</a></li>
                    <li><a href="departments.php">الأقسام</a></li>
                    <li><a href="expenses.php">نفقات الإدارة</a></li>
                    <li><a href="reports.php">التقارير</a></li>
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
</header><main class="main-content">
    <div class="container">
        <h1 class="mb-3">نفقات الإدارة</h1>        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>        <!-- إجمالي النفقات -->
        <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 2rem;">
            <div style="padding: 2rem; text-align: center;">
                <h3 style="margin-bottom: 0.5rem;">إجمالي نفقات الإدارة</h3>
                <div style="font-size: 2.5rem; font-weight: bold;">
                    <?php echo number_format($total_expenses, 2); ?> ر.س
                </div>
            </div>
        </div>        <div class="card">
            <div class="card-header flex flex-between flex-center">
                <span>إضافة نفقة إدارية</span>
                <button class="btn btn-primary" onclick="toggleForm()">+ نفقة جديدة</button>
            </div>            <div id="newExpenseForm" style="display: none; padding: 1.5rem; border-top: 1px solid var(--border-color);">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">                    <div class="form-group">
                        <label class="form-label">تاريخ النفقة *</label>
                        <input type="date" name="expense_date" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                    </div>                    <div class="form-group">
                        <label class="form-label">الفئة *</label>
                        <select name="category" class="form-select" required>
                            <option value="">اختر الفئة</option>
                            <option value="مصاريف إدارية">مصاريف إدارية</option>
                            <option value="مستلزمات مكتبية">مستلزمات مكتبية</option>
                            <option value="صيانة">صيانة</option>
                            <option value="مرافق">مرافق (كهرباء، ماء)</option>
                            <option value="اتصالات">اتصالات</option>
                            <option value="سفر وتنقل">سفر وتنقل</option>
                            <option value="ضيافة">ضيافة</option>
                            <option value="استشارات">استشارات</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>                    <div class="form-group">
                        <label class="form-label">الوصف *</label>
                        <textarea name="description" class="form-textarea" required></textarea>
                    </div>                    <div class="form-group">
                        <label class="form-label">المبلغ (ر.س) *</label>
                        <input type="number" name="amount" class="form-input" step="0.01" required min="0.01">
                    </div>                    <div class="form-group">
                        <label class="form-label">طريقة الدفع</label>
                        <select name="payment_method" class="form-select">
                            <option value="cash">نقداً</option>
                            <option value="bank_transfer">تحويل بنكي</option>
                            <option value="check">شيك</option>
                            <option value="credit_card">بطاقة ائتمان</option>
                        </select>
                    </div>                    <div class="form-group">
                        <label class="form-label">اسم المورد</label>
                        <input type="text" name="vendor_name" class="form-input">
                    </div>                    <div class="form-group">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" class="form-textarea" rows="3"></textarea>
                    </div>                    <div class="form-group">
                        <label class="form-label">رفع الفواتير (PDF، صور)</label>
                        <div class="file-upload" onclick="document.getElementById('invoiceFiles').click()">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📎</div>
                            <p>اضغط لاختيار الملفات</p>
                            <p style="font-size: 0.875rem; color: #6b7280;">PDF, JPG, PNG (حد أقصى 5MB لكل ملف)</p>
                        </div>
                        <input type="file" id="invoiceFiles" name="invoices[]" multiple accept=".pdf,.jpg,.jpeg,.png,.gif" style="display: none;" onchange="displaySelectedFiles(this)">
                        <div id="selectedFiles" style="margin-top: 1rem;"></div>
                    </div>                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-success">حفظ النفقة</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleForm()">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>        <div class="card">
            <div class="card-header">قائمة النفقات الإدارية</div>            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>الفئة</th>
                            <th>الوصف</th>
                            <th>المبلغ</th>
                            <th>طريقة الدفع</th>
                            <th>المورد</th>
                            <th>الفواتير</th>
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
                                    <td><?php echo htmlspecialchars($expense['vendor_name']); ?></td>
                                    <td>
                                        <?php if ($expense['invoice_count'] > 0): ?>
                                            <span style="background: #d1fae5; color: #065f46; padding: 0.25rem 0.5rem; border-radius: 4px;">
                                                <?php echo $expense['invoice_count']; ?> 📎
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #6b7280;">لا توجد</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">لا توجد نفقات مسجلة</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if (count($expenses) > 0): ?>
                    <tfoot style="background: #f9fafb; font-weight: bold;">
                        <tr>
                            <td colspan="3">الإجمالي</td>
                            <td style="color: #ef4444;"><?php echo number_format($total_expenses, 2); ?> ر.س</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</main><script src="../assets/js/main.js"></script>
<script>
    function toggleForm() {
        const form = document.getElementById('newExpenseForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }    function displaySelectedFiles(input) {
        const container = document.getElementById('selectedFiles');
        container.innerHTML = '';        if (input.files.length > 0) {
            const fileList = document.createElement('div');
            fileList.style.cssText = 'background: #f9fafb; padding: 1rem; border-radius: 6px;';            for (let i = 0; i < input.files.length; i++) {
                const fileItem = document.createElement('div');
                fileItem.style.cssText = 'padding: 0.5rem; border-bottom: 1px solid #e5e7eb;';
                fileItem.innerHTML = `
                    <span style="color: #10b981;">✓</span> 
                    ${input.files[i].name} 
                    <span style="color: #6b7280; font-size: 0.875rem;">
                        (${(input.files[i].size / 1024).toFixed(2)} KB)
                    </span>
                `;
                fileList.appendChild(fileItem);
            }            container.appendChild(fileList);
        }
    }
</script>
</body>
</html>
```
