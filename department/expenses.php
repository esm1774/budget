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

// ----------------------
// جلب الدفعات للقسم الحالي
// ----------------------
try {
    $query = "SELECT b.id, b.batch_name 
              FROM budget_batches b
              INNER JOIN budget_distributions d ON b.id = d.batch_id
              WHERE d.department_id = :dept_id
              ORDER BY b.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
    $stmt->execute();
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching batches: " . $e->getMessage());
    $batches = [];
}

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
        $batch_id = intval($_POST['batch_id'] ?? 0);

        if (empty($expense_date)) throw new Exception('تاريخ النفقة مطلوب');
        if (empty($category)) throw new Exception('الفئة مطلوبة');
        if (empty($description)) throw new Exception('الوصف مطلوب');
        if ($amount <= 0) throw new Exception('المبلغ يجب أن يكون أكبر من صفر');
        if ($batch_id <= 0) throw new Exception('يجب اختيار الدفعة');

        // بدء المعاملة
        $db->beginTransaction();

        // إدراج النفقة
        $query = "INSERT INTO expenses (
                    department_id, batch_id, expense_date, category, description, amount, 
                    payment_method, vendor_name, notes, created_by
                  ) VALUES (
                    :dept_id, :batch_id, :expense_date, :category, :description, :amount, 
                    :payment_method, :vendor_name, :notes, :created_by
                  )";
        
        $stmt = $db->prepare($query);
        $user_id = $_SESSION['user_id'];

        $stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
        $stmt->bindParam(':batch_id', $batch_id, PDO::PARAM_INT);
        $stmt->bindParam(':expense_date', $expense_date, PDO::PARAM_STR);
        $stmt->bindParam(':category', $category, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':amount', $amount, PDO::PARAM_STR);
        $stmt->bindParam(':payment_method', $payment_method, PDO::PARAM_STR);
        $stmt->bindParam(':vendor_name', $vendor_name, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
        $stmt->bindParam(':created_by', $user_id, PDO::PARAM_INT);
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
                        }
                    } catch (Exception $e) {
                        error_log("File upload exception: " . $e->getMessage());
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

        $db->commit();

        // تسجيل النشاط
        $auth->logActivity(
            $_SESSION['user_id'], 
            'create_expense', 
            'expenses', 
            $expense_id, 
            "إضافة نفقة: $category"
        );

        header("Location: expenses.php?success=1");
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

// رسالة النجاح
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = 'تم إضافة النفقة بنجاح';
}

// جلب النفقات
try {
    $query = "SELECT e.*, 
                     (SELECT COUNT(*) FROM invoices WHERE expense_id = e.id) as invoice_count,
                     b.batch_name
              FROM expenses e
              LEFT JOIN budget_batches b ON e.batch_id = b.id
              WHERE e.department_id = :dept_id 
              ORDER BY e.expense_date DESC, e.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
    $stmt->execute();
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
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
} catch (PDOException $e) {
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
    <?php include '../includes/header.php'; ?>
    <main class="main-content">
        <div class="container">
            <h1>إدارة المصروفات</h1>

            <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="card">
                <div class="card-header flex-between">
                    <span>إضافة فاتورة جديدة</span>
                    <button class="btn btn-primary" onclick="toggleForm()">+ فاتورة جديدة</button>
                </div>

                <div id="newExpenseForm" style="display:none;padding:1rem;border-top:1px solid #ddd;">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create">

                        <div class="form-group">
                            <label>تاريخ الفاتورة *</label>
                            <input type="date" name="expense_date" required value="<?= date('Y-m-d') ?>" class="form-input">
                        </div>

                        <div class="form-group">
                            <label>العهدة *</label>
                            <select name="batch_id" class="form-select" required>
                                <option value="">اختر العهدة</option>
                                <?php foreach ($batches as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['batch_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>الفئة *</label>
                            <select name="category" class="form-select" required>
                                <option value="">اختر الفئة</option>
                                <option value="مستلزمات مكتبية">مستلزمات مكتبية</option>
                                <option value="صيانة">صيانة</option>
                                <option value="مرافق">مرافق</option>
                                <option value="ضيافة">ضيافة</option>
                                <option value="سفر وتنقل">سفر وتنقل</option>
                                <option value="تدريب">تدريب</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>البيان *</label>
                            <textarea name="description" required class="form-textarea"></textarea>
                        </div>

                        <div class="form-group">
                            <label>المبلغ *</label>
                            <input type="number" step="0.01" name="amount" required class="form-input">
                        </div>

                        <div class="form-group">
                            <label>طريقة الدفع</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">نقداً</option>
                                <option value="bank_transfer">تحويل بنكي</option>
                                <option value="check">شيك</option>
                                <option value="credit_card">بطاقة ائتمان</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>اسم المورد</label>
                            <input type="text" name="vendor_name" class="form-input">
                        </div>

                        <div class="form-group">
                            <label>ملاحظات</label>
                            <textarea name="notes" class="form-textarea"></textarea>
                        </div>

                        <div class="form-group">
                            <label>رفع الفواتير</label>
                            <input type="file" name="invoices[]" multiple accept=".pdf,.jpg,.jpeg,.png">
                        </div>

                        <button type="submit" class="btn btn-success">حفظ الفاتورة</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleForm()">إلغاء</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">قائمة المصروفات</div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>العهدة</th>
                                <th>الفئة</th>
                                <th>البيان</th>
                                <th>المبلغ</th>
                                <th>الفواتير</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($expenses) > 0): ?>
                                <?php foreach ($expenses as $expense): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($expense['expense_date']) ?></td>
                                        <td><?= htmlspecialchars($expense['batch_name'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($expense['category']) ?></td>
                                        <td><?= htmlspecialchars(mb_substr($expense['description'], 0, 40)) ?>...</td>
                                        <td style="color:#ef4444;font-weight:bold;"><?= number_format($expense['amount'],2) ?> ر.س</td>
                                        <td><?= $expense['invoice_count'] > 0 ? $expense['invoice_count'].' 📎' : '—' ?></td>
                                        <td><a href="expense_details.php?id=<?= $expense['id'] ?>" class="btn btn-secondary">التفاصيل</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center">لا توجد مصروفات</td></tr>
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
</script>
</body>
</html>
