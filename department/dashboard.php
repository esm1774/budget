<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireDepartment();

$department_id = $_SESSION['department_id'];

// جلب بيانات القسم
$query = "SELECT * FROM departments WHERE id = :id AND is_active = 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $department_id);
$stmt->execute();
$department = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$department) {
    die('القسم غير موجود');
}

// جلب الدفعات المستلمة
$query = "SELECT 
          bd.id,
          bd.amount,
          bd.distribution_date,
          bd.notes,
          bb.batch_number,
          bb.batch_name,
          bb.received_date,
          u.full_name as distributed_by
          FROM budget_distributions bd
          LEFT JOIN budget_batches bb ON bd.batch_id = bb.id
          LEFT JOIN users u ON bd.created_by = u.id
          WHERE bd.department_id = :dept_id
          ORDER BY bd.distribution_date DESC, bd.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department_id);
$stmt->execute();
$distributions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// حساب إجمالي الدفعات المستلمة
$total_distributions = 0;
foreach ($distributions as $dist) {
    $total_distributions += $dist['amount'];
}

// جلب النفقات
$query = "SELECT e.*, 
          (SELECT COUNT(*) FROM invoices WHERE expense_id = e.id) as invoice_count
          FROM expenses e 
          WHERE e.department_id = :dept_id 
          ORDER BY e.expense_date DESC, e.created_at DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department_id);
$stmt->execute();
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات
$stats = [
    'total_received' => $department['total_received'],
    'spent' => $department['spent_amount'],
    'remaining' => $department['total_received'] - $department['spent_amount'],
    'distribution_count' => count($distributions)
];

// عدد النفقات
$query = "SELECT COUNT(*) as total FROM expenses WHERE department_id = :dept_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department_id);
$stmt->execute();
$stats['expense_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - <?php echo htmlspecialchars($department['name_ar']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="main-content">
        <div class="container">
            <h1 class="mb-3">لوحة التحكم</h1>

            <!-- بطاقة معلومات القسم -->
            <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 2rem;">
                <div style="padding: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($department['name_ar']); ?></h2>
                            <p style="opacity: 0.9;">الكود: <?php echo htmlspecialchars($department['code']); ?></p>
                        </div>
                        <?php if ($department['last_distribution_date']): ?>
                        <div style="text-align: left;">
                            <div style="font-size: 0.875rem; opacity: 0.9;">آخر دفعة مستلمة</div>
                            <div style="font-size: 1.25rem; font-weight: bold;">
                                📅 <?php echo date('Y-m-d', strtotime($department['last_distribution_date'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

<!-- آخر 3 دفعات -->
            <?php if (count($distributions) > 0): ?>
            <div class="card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; margin-bottom: 2rem;">
                <div class="card-header" style="border-bottom-color: rgba(255,255,255,0.2); color: white;">
                    <div class="flex flex-between flex-center">
                        <span>💰 آخر الدفعات المستلمة</span>
                        <a href="distributions.php" class="btn" style="background: white; color: #10b981; padding: 0.5rem 1rem;">
                            عرض الكل
                        </a>
                    </div>
                </div>
                <div style="padding: 1.5rem;">
                    <?php 
                    $recent_distributions = array_slice($distributions, 0, 3);
                    foreach ($recent_distributions as $dist): 
                    ?>
                    <div style="background: rgba(255,255,255,0.1); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; backdrop-filter: blur(10px);">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                            <div>
                                <div style="font-weight: bold; margin-bottom: 0.25rem;">
                                    <?php echo htmlspecialchars($dist['batch_name']); ?>
                                </div>
                                <div style="font-size: 0.875rem; opacity: 0.9;">
                                    رقم: <?php echo htmlspecialchars($dist['batch_number']); ?> | 
                                    <?php echo date('Y-m-d', strtotime($dist['distribution_date'])); ?>
                                </div>
                            </div>
                            <div style="font-size: 1.5rem; font-weight: bold;">
                                <?php echo number_format($dist['amount'], 2); ?> ر.س
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- الإحصائيات -->
            <div class="stats-grid">
                <div class="stat-card success">
                    <div class="stat-label">إجمالي المستلم</div>
                    <div class="stat-value"><?php echo number_format($stats['total_received'], 2); ?> ر.س</div>
                    <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                        عدد الدفعات: <?php echo $stats['distribution_count']; ?>
                    </div>
                </div>
                
                <div class="stat-card danger">
                    <div class="stat-label">المصروف</div>
                    <div class="stat-value"><?php echo number_format($stats['spent'], 2); ?> ر.س</div>
                    <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                        عدد النفقات: <?php echo $stats['expense_count']; ?>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-label">المتبقي</div>
                    <div class="stat-value"><?php echo number_format($stats['remaining'], 2); ?> ر.س</div>
                    <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                        <?php 
                        $percentage_used = $stats['total_received'] > 0 ? ($stats['spent'] / $stats['total_received']) * 100 : 0;
                        echo number_format($percentage_used, 1) . '% مستخدم';
                        ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">نسبة الصرف</div>
                    <div class="stat-value"><?php echo number_format($percentage_used, 1); ?>%</div>
                    <div style="margin-top: 0.5rem;">
                        <div style="background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                            <div style="width: <?php echo min($percentage_used, 100); ?>%; height: 100%; background: <?php echo $percentage_used > 90 ? '#ef4444' : ($percentage_used > 70 ? '#f59e0b' : '#10b981'); ?>;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($stats['remaining'] < ($stats['total_received'] * 0.1) && $stats['total_received'] > 0): ?>
                <div class="alert alert-warning">
                    ⚠️ تحذير: المبلغ المتبقي أقل من 10% من إجمالي المبالغ المستلمة
                </div>
            <?php endif; ?>

            <!-- الدفعات المستلمة -->
            <div class="card">
                <div class="card-header flex flex-between flex-center">
                    <span>💰 الدفعات المستلمة من الإدارة</span>
                    <span style="background: #10b981; color: white; padding: 0.5rem 1rem; border-radius: 6px; font-weight: bold;">
                        <?php echo count($distributions); ?> دفعة
                    </span>
                </div>
                
                <?php if (count($distributions) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>رقم الدفعة</th>
                                <th>اسم الدفعة</th>
                                <th>المبلغ المستلم</th>
                                <th>تاريخ التوزيع</th>
                                <th>تاريخ استلام الإدارة</th>
                                <th>تم التوزيع بواسطة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($distributions as $dist): ?>
                                <tr>
                                    <td>
                                        <strong style="color: #2563eb;">
                                            <?php echo htmlspecialchars($dist['batch_number']); ?>
                                        </strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($dist['batch_name']); ?></td>
                                    <td style="font-weight: bold; color: #10b981; font-size: 1.125rem;">
                                        <?php echo number_format($dist['amount'], 2); ?> ر.س
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($dist['distribution_date'])); ?></td>
                                    <td>
                                        <span style="background: #fef3c7; color: #92400e; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.875rem;">
                                            <?php echo date('Y-m-d', strtotime($dist['received_date'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($dist['distributed_by']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot style="background: #f9fafb; font-weight: bold;">
                            <tr>
                                <td colspan="2">الإجمالي</td>
                                <td style="color: #10b981; font-size: 1.25rem;">
                                    <?php echo number_format($total_distributions, 2); ?> ر.س
                                </td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <div style="padding: 3rem; text-align: center; color: #6b7280;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                    <p style="font-size: 1.125rem;">لم يتم استلام أي دفعات بعد</p>
                    <p style="font-size: 0.875rem;">سيتم عرض الدفعات المالية هنا عند توزيعها من قبل السكرتير</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- آخر النفقات -->
            <div class="card">
                <div class="card-header flex flex-between flex-center">
                    <span>📝 آخر النفقات المسجلة</span>
                    <a href="expenses.php?action=new" class="btn btn-primary">+ إضافة نفقة جديدة</a>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>الفئة</th>
                                <th>الوصف</th>
                                <th>المبلغ</th>
                                <th>الفواتير</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($expenses) > 0): ?>
                                <?php foreach ($expenses as $expense): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d', strtotime($expense['expense_date'])); ?></td>
                                        <td>
                                            <span style="background: #dbeafe; color: #1e40af; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.875rem;">
                                                <?php echo htmlspecialchars($expense['category']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(mb_substr($expense['description'], 0, 50)); ?>...</td>
                                        <td style="font-weight: bold; color: #ef4444;">
                                            <?php echo number_format($expense['amount'], 2); ?> ر.س
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
                                    <td colspan="6" class="text-center">لا توجد نفقات مسجلة</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (count($expenses) > 0): ?>
                    <div style="padding: 1rem; text-align: center; border-top: 1px solid #e5e7eb;">
                        <a href="expenses.php" class="btn btn-primary">عرض جميع النفقات (<?php echo $stats['expense_count']; ?>)</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ملخص سريع -->
            <div class="card">
                <div class="card-header">ملخص الحالة المالية</div>
                <div style="padding: 2rem;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem;">
                        <div style="text-align: center; padding: 1.5rem; background: #f0fdf4; border-radius: 8px;">
                            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">💰</div>
                            <div style="font-size: 0.875rem; color: #166534; margin-bottom: 0.5rem;">إجمالي المستلم</div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #15803d;">
                                <?php echo number_format($stats['total_received'], 2); ?> ر.س
                            </div>
                        </div>
                        
                        <div style="text-align: center; padding: 1.5rem; background: #fef2f2; border-radius: 8px;">
                            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">💸</div>
                            <div style="font-size: 0.875rem; color: #991b1b; margin-bottom: 0.5rem;">إجمالي المصروف</div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #dc2626;">
                                <?php echo number_format($stats['spent'], 2); ?> ر.س
                            </div>
                        </div>
                        
                        <div style="text-align: center; padding: 1.5rem; background: #fffbeb; border-radius: 8px;">
                            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">💵</div>
                            <div style="font-size: 0.875rem; color: #92400e; margin-bottom: 0.5rem;">المتبقي للصرف</div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #d97706;">
                                <?php echo number_format($stats['remaining'], 2); ?> ر.س
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>