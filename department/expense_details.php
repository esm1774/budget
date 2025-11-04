<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireDepartment();

$expense_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$department_id = $_SESSION['department_id'];

if ($expense_id <= 0) {
    header('Location: expenses.php');
    exit();
}

// جلب بيانات النفقة (التحقق من الصلاحيات)
$query = "SELECT e.*, u.full_name as created_by_name
          FROM expenses e
          LEFT JOIN users u ON e.created_by = u.id
          WHERE e.id = :id AND e.department_id = :dept_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $expense_id);
$stmt->bindParam(':dept_id', $department_id);
$stmt->execute();
$expense = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$expense) {
    die('النفقة غير موجودة أو ليس لديك صلاحية لعرضها');
}

// جلب الفواتير
$query = "SELECT * FROM invoices WHERE expense_id = :expense_id ORDER BY uploaded_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':expense_id', $expense_id);
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب اسم القسم
$query = "SELECT name_ar FROM departments WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $department_id);
$stmt->execute();
$department = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل النفقة</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="flex flex-between flex-center mb-3">
                <h1>تفاصيل النفقة</h1>
                <a href="expenses.php" class="btn btn-secondary">← العودة للنفقات</a>
            </div>

            <div class="card">
                <div class="card-header">معلومات النفقة</div>
                <div style="padding: 2rem;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                        <div>
                            <strong style="color: #6b7280; display: block; margin-bottom: 0.5rem;">تاريخ النفقة:</strong>
                            <p style="font-size: 1.125rem;"><?php echo date('Y-m-d', strtotime($expense['expense_date'])); ?></p>
                        </div>
                        
                        <div>
                            <strong style="color: #6b7280; display: block; margin-bottom: 0.5rem;">الفئة:</strong>
                            <p style="font-size: 1.125rem;">
                                <span style="background: #dbeafe; padding: 0.25rem 0.75rem; border-radius: 4px;">
                                    <?php echo htmlspecialchars($expense['category']); ?>
                                </span>
                            </p>
                        </div>
                        
                        <div>
                            <strong style="color: #6b7280; display: block; margin-bottom: 0.5rem;">المبلغ:</strong>
                            <p style="font-size: 1.5rem; font-weight: bold; color: #ef4444;">
                                <?php echo number_format($expense['amount'], 2); ?> ر.س
                            </p>
                        </div>
                        
                        <div>
                            <strong style="color: #6b7280; display: block; margin-bottom: 0.5rem;">طريقة الدفع:</strong>
                            <p style="font-size: 1.125rem;">
                                <?php 
                                $payment_methods = [
                                    'cash' => 'نقداً',
                                    'bank_transfer' => 'تحويل بنكي',
                                    'check' => 'شيك',
                                    'credit_card' => 'بطاقة ائتمان'
                                ];
                                echo $payment_methods[$expense['payment_method']] ?? $expense['payment_method'];
                                ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($expense['vendor_name']): ?>
                    <div style="margin-top: 2rem;">
                        <strong style="color: #6b7280; display: block; margin-bottom: 0.5rem;">المورد:</strong>
                        <p style="font-size: 1.125rem;"><?php echo htmlspecialchars($expense['vendor_name']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 2rem;">
                        <strong style="color: #6b7280; display: block; margin-bottom: 0.5rem;">الوصف:</strong>
                        <p style="font-size: 1rem; line-height: 1.8; background: #f9fafb; padding: 1rem; border-radius: 6px;">
                            <?php echo nl2br(htmlspecialchars($expense['description'])); ?>
                        </p>
                    </div>
                    
                    <?php if ($expense['notes']): ?>
                    <div style="margin-top: 2rem;">
                        <strong style="color: #6b7280; display: block; margin-bottom: 0.5rem;">ملاحظات:</strong>
                        <p style="font-size: 1rem; line-height: 1.8; background: #fef3c7; padding: 1rem; border-radius: 6px;">
                            <?php echo nl2br(htmlspecialchars($expense['notes'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 0.875rem;">
                        <p>تم الإضافة بواسطة: <?php echo htmlspecialchars($expense['created_by_name']); ?></p>
                        <p>تاريخ الإضافة: <?php echo date('Y-m-d H:i', strtotime($expense['created_at'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- الفواتير المرفقة -->
            <div class="card">
                <div class="card-header">الفواتير المرفقة (<?php echo count($invoices); ?>)</div>
                <div style="padding: 1.5rem;">
                    <?php if (count($invoices) > 0): ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem;">
                            <?php foreach ($invoices as $invoice): 
                                $file_extension = strtolower(pathinfo($invoice['file_name'], PATHINFO_EXTENSION));
                                $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif']);
                            ?>
                                <div style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; background: #f9fafb;">
                                    <?php if ($is_image): ?>
                                        <a href="../<?php echo htmlspecialchars($invoice['file_path']); ?>" target="_blank">
                                            <img src="../<?php echo htmlspecialchars($invoice['file_path']); ?>" 
                                                 alt="Invoice" 
                                                 style="width: 100%; height: 150px; object-fit: cover; border-radius: 6px; margin-bottom: 0.5rem;">
                                        </a>
                                    <?php else: ?>
                                        <div style="width: 100%; height: 150px; background: #dbeafe; border-radius: 6px; display: flex; align-items: center; justify-content: center; margin-bottom: 0.5rem;">
                                            <span style="font-size: 3rem;">📄</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <p style="font-size: 0.875rem; margin-bottom: 0.5rem; word-break: break-all;">
                                        <?php echo htmlspecialchars($invoice['file_name']); ?>
                                    </p>
                                    <p style="font-size: 0.75rem; color: #6b7280; margin-bottom: 0.5rem;">
                                        <?php echo number_format($invoice['file_size'] / 1024, 2); ?> KB
                                    </p>
                                    <a href="../<?php echo htmlspecialchars($invoice['file_path']); ?>" 
                                       download 
                                       class="btn btn-primary" 
                                       style="width: 100%; padding: 0.5rem; font-size: 0.875rem; text-align: center;">
                                        تحميل
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #6b7280; padding: 2rem;">
                            لا توجد فواتير مرفقة لهذه النفقة
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>