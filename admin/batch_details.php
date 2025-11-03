<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireAdmin();

$batch_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($batch_id <= 0) {
    header('Location: budget_batches.php');
    exit();
}

// جلب بيانات الدفعة
$query = "SELECT b.*, u.full_name as created_by_name
          FROM budget_batches b
          LEFT JOIN users u ON b.created_by = u.id
          WHERE b.id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $batch_id);
$stmt->execute();
$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    die('الدفعة غير موجودة');
}

// جلب التوزيعات
$query = "SELECT bd.*, d.name_ar as department_name, d.code as department_code, u.full_name as distributed_by
          FROM budget_distributions bd
          LEFT JOIN departments d ON bd.department_id = d.id
          LEFT JOIN users u ON bd.created_by = u.id
          WHERE bd.batch_id = :batch_id
          ORDER BY bd.distribution_date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':batch_id', $batch_id);
$stmt->execute();
$distributions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الدفعة</title>
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
                    <a href="../logout.php" class="btn btn-danger">خروج </a>
                </div>
            </nav>
        </div>
    </header>
<main class="main-content">
    <div class="container">
        <div class="flex flex-between flex-center mb-3">
            <h1>تفاصيل الدفعة المالية</h1>
            <a href="budget_batches.php" class="btn btn-secondary">← العودة للدفعات</a>
        </div>

        <!-- معلومات الدفعة -->
        <div class="card">
            <div class="card-header">معلومات الدفعة</div>
            <div style="padding: 2rem;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                    <div>
                        <strong style="color: #6b7280; display: block; margin-bottom: 0.5rem;">رقم الدفعة:</strong>
                        <p style="font-size: 1.25rem; font-weight: bold; color: #2563eb;">
                            <?php echo htmlspecialchars($batch['batch_number']); ?>
                        </p>
                    </div>
                    
                    <div>
                        <strong style="color: #6b7280; display: block; margin-bottom: 0.5rem;">اسم الدفعة:</strong>
                        <p style="font-size: 1.125rem;"><?php echo htmlspecialchars($batch['batch_name']); ?></p>
                    </div>
                    
                    <div>
                        <strong style="color: #6b7280; display: block; margin-bottom: 0.5rem;">تاريخ الاستلام:</strong>
                        <p style="font-size: 1.125rem;"><?php echo date('Y-m-d', strtotime($batch['received_date'])); ?></p>
                    </div>
                    
                    <div>
                        <strong style="color: #6b7280; display: block; margin-bottom: 0.5rem;">تم الإنشاء بواسطة:</strong>
                        <p style="font-size: 1.125rem;"><?php echo htmlspecialchars($batch['created_by_name']); ?></p>
                    </div>
                </div>
                
                <?php if ($batch['notes']): ?>
                <div style="margin-top: 2rem;">
                    <strong style="color: #6b7280; display: block; margin-bottom: 0.5rem;">ملاحظات:</strong>
                    <p style="background: #f9fafb; padding: 1rem; border-radius: 6px;">
                        <?php echo nl2br(htmlspecialchars($batch['notes'])); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- إحصائيات الدفعة -->
        <div class="stats-grid">
            <div class="stat-card success">
                <div class="stat-label">المبلغ المستلم</div>
                <div class="stat-value"><?php echo number_format($batch['amount'], 2); ?> ر.س</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">الموزع على الأقسام</div>
                <div class="stat-value"><?php echo number_format($batch['distributed_amount'], 2); ?> ر.س</div>
                <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                    <?php 
                    $percentage = $batch['amount'] > 0 ? ($batch['distributed_amount'] / $batch['amount']) * 100 : 0;
                    echo number_format($percentage, 1) . '% من الإجمالي';
                    ?>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-label">المتبقي للتوزيع</div>
                <div class="stat-value"><?php echo number_format($batch['remaining_amount'], 2); ?> ر.س</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">عدد التوزيعات</div>
                <div class="stat-value"><?php echo count($distributions); ?></div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="card">
            <div class="card-header">نسبة التوزيع</div>
            <div style="padding: 2rem;">
                <div style="background: #e5e7eb; height: 30px; border-radius: 15px; overflow: hidden; position: relative;">
                    <div style="width: <?php echo min($percentage, 100); ?>%; height: 100%; background: linear-gradient(90deg, #10b981 0%, #059669 100%); transition: width 0.3s;"></div>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: bold; color: <?php echo $percentage > 50 ? 'white' : '#111827'; ?>;">
                        <?php echo number_format($percentage, 1); ?>%
                    </div>
                </div>
            </div>
        </div>

        <!-- جدول التوزيعات -->
        <div class="card">
            <div class="card-header">سجل التوزيعات على الأقسام</div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>القسم</th>
                            <th>الكود</th>
                            <th>المبلغ</th>
                            <th>تم التوزيع بواسطة</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($distributions) > 0): ?>
                            <?php foreach ($distributions as $dist): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d', strtotime($dist['distribution_date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($dist['department_name']); ?></strong></td>
                                    <td>
                                        <span style="background: #dbeafe; padding: 0.25rem 0.75rem; border-radius: 4px;">
                                            <?php echo htmlspecialchars($dist['department_code']); ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: bold; color: #10b981;">
                                        <?php echo number_format($dist['amount'], 2); ?> ر.س
                                    </td>
                                    <td><?php echo htmlspecialchars($dist['distributed_by']); ?></td>
                                    <td><?php echo $dist['notes'] ? htmlspecialchars($dist['notes']) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">لم يتم توزيع هذه الدفعة بعد</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if (count($distributions) > 0): ?>
                    <tfoot style="background: #f9fafb; font-weight: bold;">
                        <tr>
                            <td colspan="3">الإجمالي</td>
                            <td style="color: #10b981;"><?php echo number_format($batch['distributed_amount'], 2); ?> ر.س</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
