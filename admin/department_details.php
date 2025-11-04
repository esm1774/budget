<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireAdmin();

$department_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($department_id <= 0) {
    header('Location: departments.php');
    exit();
}

// جلب بيانات القسم
$query = "SELECT * FROM departments WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $department_id);
$stmt->execute();
$department = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$department) {
    die('القسم غير موجود');
}

// جلب المستخدمين
$query = "SELECT * FROM users WHERE department_id = :dept_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department_id);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب النفقات
$query = "SELECT e.*, u.full_name as created_by_name,
          (SELECT COUNT(*) FROM invoices WHERE expense_id = e.id) as invoice_count
          FROM expenses e
          LEFT JOIN users u ON e.created_by = u.id
          WHERE e.department_id = :dept_id
          ORDER BY e.expense_date DESC, e.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department_id);
$stmt->execute();
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات
$query = "SELECT 
          COUNT(*) as total_expenses,
          SUM(amount) as total_amount,
          MIN(expense_date) as first_expense,
          MAX(expense_date) as last_expense
          FROM expenses 
          WHERE department_id = :dept_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department_id);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل القسم - <?php echo htmlspecialchars($department['name_ar']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="flex flex-between flex-center mb-3">
                <h1>تفاصيل قسم: <?php echo htmlspecialchars($department['name_ar']); ?></h1>
                <a href="departments.php" class="btn btn-secondary">← العودة للأقسام</a>
            </div>

            <!-- معلومات القسم -->
            <div class="card">
                <div class="card-header">معلومات القسم</div>
                <div style="padding: 1.5rem;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                        <div>
                            <strong>اسم القسم (عربي):</strong>
                            <p><?php echo htmlspecialchars($department['name_ar']); ?></p>
                        </div>
                        <div>
                            <strong>اسم القسم (إنجليزي):</strong>
                            <p><?php echo htmlspecialchars($department['name_en']); ?></p>
                        </div>
                        <div>
                            <strong>كود القسم:</strong>
                            <p><span style="background: #dbeafe; padding: 0.25rem 0.75rem; border-radius: 4px; font-weight: bold;"><?php echo htmlspecialchars($department['code']); ?></span></p>
                        </div>
                        <div>
                            <strong>تاريخ الإنشاء:</strong>
                            <p><?php echo date('Y-m-d', strtotime($department['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <?php if ($department['description']): ?>
                    <div style="margin-top: 1.5rem;">
                        <strong>الوصف:</strong>
                        <p style="color: #6b7280;"><?php echo nl2br(htmlspecialchars($department['description'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- الإحصائيات -->
            <div class="stats-grid">
                <div class="stat-card success">
                    <div class="stat-label">الميزانية المخصصة</div>
                    <div class="stat-value"><?php echo number_format($department['allocated_budget'], 2); ?> ر.س</div>
                </div>
                
                <div class="stat-card danger">
                    <div class="stat-label">إجمالي المصروفات</div>
                    <div class="stat-value"><?php echo number_format($department['spent_amount'], 2); ?> ر.س</div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-label">المتبقي</div>
                    <div class="stat-value"><?php echo number_format($department['allocated_budget'] - $department['spent_amount'], 2); ?> ر.س</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">عدد النفقات</div>
                    <div class="stat-value"><?php echo $stats['total_expenses']; ?></div>
                </div>
            </div>

            <!-- المستخدمون -->
            <div class="card">
                <div class="card-header">مستخدمو القسم</div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>اسم المستخدم</th>
                                <th>الاسم الكامل</th>
                                <th>الحالة</th>
                                <th>آخر دخول</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users) > 0): ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td>
                                            <?php if ($user['is_active']): ?>
                                                <span style="color: #10b981;">● نشط</span>
                                            <?php else: ?>
                                                <span style="color: #ef4444;">● غير نشط</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'لم يسجل دخول'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">لا يوجد مستخدمون</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- النفقات -->
            <div class="card">
                <div class="card-header">سجل النفقات</div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>الفئة</th>
                                <th>الوصف</th>
                                <th>المبلغ</th>
                                <th>المستخدم</th>
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
                                        <td><?php echo htmlspecialchars($expense['created_by_name']); ?></td>
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
                                            <button onclick="viewExpenseDetails(<?php echo $expense['id']; ?>)" 
                                                    class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                                التفاصيل
                                            </button>
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

    <script src="../assets/js/main.js"></script>
    <script>
        function viewExpenseDetails(expenseId) {
            window.location.href = `expense_view.php?id=${expenseId}&dept_id=<?php echo $department_id; ?>`;
        }
    </script>
</body>
</html>