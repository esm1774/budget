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

// ----------------------
// CREATE - إضافة نفقة جديدة
// ----------------------
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

// ----------------------
// UPDATE - تحديث نفقة
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $expense_id = intval($_POST['expense_id'] ?? 0);
        $expense_date = $_POST['expense_date'] ?? '';
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $vendor_name = trim($_POST['vendor_name'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $batch_id = intval($_POST['batch_id'] ?? 0);

        if ($expense_id <= 0) throw new Exception('معرف النفقة غير صالح');
        if (empty($expense_date)) throw new Exception('تاريخ النفقة مطلوب');
        if (empty($category)) throw new Exception('الفئة مطلوبة');
        if (empty($description)) throw new Exception('الوصف مطلوب');
        if ($amount <= 0) throw new Exception('المبلغ يجب أن يكون أكبر من صفر');
        if ($batch_id <= 0) throw new Exception('يجب اختيار الدفعة');

        // التحقق من ملكية النفقة للقسم
        $checkQuery = "SELECT id FROM expenses WHERE id = :id AND department_id = :dept_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $expense_id, PDO::PARAM_INT);
        $checkStmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() === 0) {
            throw new Exception('النفقة غير موجودة أو لا تملك صلاحية التعديل عليها');
        }

        $db->beginTransaction();

        // تحديث النفقة
        $query = "UPDATE expenses SET
                    batch_id = :batch_id,
                    expense_date = :expense_date,
                    category = :category,
                    description = :description,
                    amount = :amount,
                    payment_method = :payment_method,
                    vendor_name = :vendor_name,
                    notes = :notes,
                    updated_at = NOW()
                  WHERE id = :id AND department_id = :dept_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $expense_id, PDO::PARAM_INT);
        $stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
        $stmt->bindParam(':batch_id', $batch_id, PDO::PARAM_INT);
        $stmt->bindParam(':expense_date', $expense_date, PDO::PARAM_STR);
        $stmt->bindParam(':category', $category, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':amount', $amount, PDO::PARAM_STR);
        $stmt->bindParam(':payment_method', $payment_method, PDO::PARAM_STR);
        $stmt->bindParam(':vendor_name', $vendor_name, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
        $stmt->execute();

        // رفع فواتير جديدة
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
            'update_expense', 
            'expenses', 
            $expense_id, 
            "تحديث نفقة: $category"
        );

        header("Location: expenses.php?success=2");
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

// ----------------------
// DELETE - حذف نفقة
// ----------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $expense_id = intval($_GET['id']);

        if ($expense_id <= 0) throw new Exception('معرف النفقة غير صالح');

        // التحقق من ملكية النفقة للقسم
        $checkQuery = "SELECT id, category FROM expenses WHERE id = :id AND department_id = :dept_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $expense_id, PDO::PARAM_INT);
        $checkStmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
        $checkStmt->execute();
        
        $expense = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$expense) {
            throw new Exception('النفقة غير موجودة أو لا تملك صلاحية الحذف');
        }

        $db->beginTransaction();

        // جلب الفواتير المرتبطة
        $invoiceQuery = "SELECT file_path FROM invoices WHERE expense_id = :expense_id";
        $invoiceStmt = $db->prepare($invoiceQuery);
        $invoiceStmt->bindParam(':expense_id', $expense_id, PDO::PARAM_INT);
        $invoiceStmt->execute();
        $invoices = $invoiceStmt->fetchAll(PDO::FETCH_ASSOC);

        // حذف الفواتير من النظام
        $fileUpload = new FileUpload();
        foreach ($invoices as $invoice) {
            if (file_exists($invoice['file_path'])) {
                unlink($invoice['file_path']);
            }
        }

        // حذف الفواتير من قاعدة البيانات
        $deleteInvoicesQuery = "DELETE FROM invoices WHERE expense_id = :expense_id";
        $deleteInvoicesStmt = $db->prepare($deleteInvoicesQuery);
        $deleteInvoicesStmt->bindParam(':expense_id', $expense_id, PDO::PARAM_INT);
        $deleteInvoicesStmt->execute();

        // حذف النفقة
        $deleteQuery = "DELETE FROM expenses WHERE id = :id AND department_id = :dept_id";
        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->bindParam(':id', $expense_id, PDO::PARAM_INT);
        $deleteStmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
        $deleteStmt->execute();

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
            'delete_expense', 
            'expenses', 
            $expense_id, 
            "حذف نفقة: {$expense['category']}"
        );

        header("Location: expenses.php?success=3");
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

// ----------------------
// DELETE INVOICE - حذف فاتورة
// ----------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete_invoice' && isset($_GET['invoice_id'])) {
    try {
        $invoice_id = intval($_GET['invoice_id']);

        if ($invoice_id <= 0) throw new Exception('معرف الفاتورة غير صالح');

        // التحقق من ملكية الفاتورة للقسم
        $checkQuery = "SELECT i.id, i.file_path 
                       FROM invoices i 
                       INNER JOIN expenses e ON i.expense_id = e.id 
                       WHERE i.id = :invoice_id AND e.department_id = :dept_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':invoice_id', $invoice_id, PDO::PARAM_INT);
        $checkStmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
        $checkStmt->execute();
        
        $invoice = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$invoice) {
            throw new Exception('الفاتورة غير موجودة أو لا تملك صلاحية الحذف');
        }

        // حذف الملف من النظام
        if (file_exists($invoice['file_path'])) {
            unlink($invoice['file_path']);
        }

        // حذف الفاتورة من قاعدة البيانات
        $deleteQuery = "DELETE FROM invoices WHERE id = :id";
        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->bindParam(':id', $invoice_id, PDO::PARAM_INT);
        $deleteStmt->execute();

        // تسجيل النشاط
        $auth->logActivity(
            $_SESSION['user_id'], 
            'delete_invoice', 
            'invoices', 
            $invoice_id, 
            "حذف فاتورة"
        );

        header("Location: expenses.php?success=4");
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ----------------------
// READ - جلب النفقات للعرض
// ----------------------
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

// ----------------------
// جلب بيانات النفقة للتعديل
// ----------------------
$edit_expense = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    try {
        $expense_id = intval($_GET['id']);
        $query = "SELECT e.*, 
                         (SELECT GROUP_CONCAT(i.id) FROM invoices i WHERE i.expense_id = e.id) as invoice_ids,
                         (SELECT GROUP_CONCAT(i.file_name) FROM invoices i WHERE i.expense_id = e.id) as invoice_names
                  FROM expenses e
                  WHERE e.id = :id AND e.department_id = :dept_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $expense_id, PDO::PARAM_INT);
        $stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
        $stmt->execute();
        $edit_expense = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching expense for edit: " . $e->getMessage());
        $error = 'حدث خطأ أثناء جلب بيانات النفقة';
    }
}

// رسائل النجاح
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1: $message = 'تم إضافة النفقة بنجاح'; break;
        case 2: $message = 'تم تحديث النفقة بنجاح'; break;
        case 3: $message = 'تم حذف النفقة بنجاح'; break;
        case 4: $message = 'تم حذف الفاتورة بنجاح'; break;
    }
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

    
 

