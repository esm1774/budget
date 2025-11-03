
<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireAdmin();

// جلب البيانات للتقرير
$report_data = [];

// إجمالي الميزانيات
$query = "SELECT 
          SUM(allocated_budget) as total_budget,
          SUM(spent_amount) as total_spent
          FROM departments WHERE is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$totals = $stmt->fetch(PDO::FETCH_ASSOC);
// جلب معلومات الدفعات
$query = "SELECT 
          COUNT(*) as batch_count,
          SUM(amount) as total_received,
          SUM(distributed_amount) as total_distributed,
          SUM(remaining_amount) as total_remaining
          FROM budget_batches";
$stmt = $db->prepare($query);
$stmt->execute();
$batch_summary = $stmt->fetch(PDO::FETCH_ASSOC);

// بيانات الأقسام
$query = "SELECT 
          d.id, d.name_ar, d.code, d.allocated_budget, d.spent_amount,
          (d.allocated_budget - d.spent_amount) as remaining,
          COUNT(e.id) as expense_count
          FROM departments d
          LEFT JOIN expenses e ON e.department_id = d.id
          WHERE d.is_active = 1
          GROUP BY d.id
          ORDER BY d.name_ar";
$stmt = $db->prepare($query);
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// نفقات الإدارة
$query = "SELECT SUM(amount) as total FROM admin_expenses";
$stmt = $db->prepare($query);
$stmt->execute();
$admin_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// تصدير إلى Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="financial_report_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    
    echo "<html><head><meta charset='utf-8'></head><body>";
    echo "<table border='1'>";
    echo "<tr><th colspan='6' style='text-align:center; font-size:18px;'><b>التقرير المالي الشامل</b></th></tr>";
    echo "<tr><th colspan='6' style='text-align:center;'>تاريخ التقرير: " . date('Y-m-d H:i') . "</th></tr>";
    echo "<tr><td colspan='6'></td></tr>";
    
    echo "<tr><th>اسم القسم</th><th>الكود</th><th>الميزانية المخصصة</th><th>المصروف</th><th>المتبقي</th><th>عدد النفقات</th></tr>";
    
    foreach ($departments as $dept) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($dept['name_ar']) . "</td>";
        echo "<td>" . htmlspecialchars($dept['code']) . "</td>";
        echo "<td>" . number_format($dept['allocated_budget'], 2) . "</td>";
        echo "<td>" . number_format($dept['spent_amount'], 2) . "</td>";
        echo "<td>" . number_format($dept['remaining'], 2) . "</td>";
        echo "<td>" . $dept['expense_count'] . "</td>";
        echo "</tr>";
    }
    
    echo "<tr><td colspan='6'></td></tr>";
    echo "<tr><th>الإجماليات</th><th></th><th>" . number_format($totals['total_budget'], 2) . "</th><th>" . number_format($totals['total_spent'], 2) . "</th><th>" . number_format($totals['total_budget'] - $totals['total_spent'], 2) . "</th><th></th></tr>";
    echo "<tr><th>نفقات الإدارة</th><th colspan='5'>" . number_format($admin_total, 2) . " ر.س</th></tr>";
    echo "<tr><th>الإجمالي الكلي</th><th colspan='5'>" . number_format($totals['total_spent'] + $admin_total, 2) . " ر.س</th></tr>";
    
    echo "</table></body></html>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقارير المالية</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            .header { position: static; box-shadow: none; }
            body { background: white; }
        }
    </style>
</head>
<body>
    <header class="header no-print">
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
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="flex flex-between flex-center mb-3 no-print">
                <h1>التقرير المالي الشامل</h1>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="btn btn-primary">🖨️ طباعة</button>
                    <a href="?export=excel" class="btn btn-success">📊 تصدير Excel</a>
                </div>
            </div>
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-header" style="background: #667eea; color: white;">
                    ملخص الدفعات المالية من الشركة الرئيسية
                </div>
                <div style="padding: 2rem;">
                    <div class="stats-grid">
                        <div class="stat-card success">
                            <div class="stat-label">عدد الدفعات</div>
                            <div class="stat-value"><?php echo $batch_summary['batch_count']; ?></div>
                        </div>
                        <div class="stat-card success">
                            <div class="stat-label">إجمالي المستلم</div>
                            <div class="stat-value"><?php echo number_format($batch_summary['total_received'], 2); ?> ر.س</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">الموزع</div>
                            <div class="stat-value"><?php echo number_format($batch_summary['total_distributed'], 2); ?> ر.س</div>
                        </div>
                        <div class="stat-card warning">
                            <div class="stat-label">المتبقي</div>
                            <div class="stat-value"><?php echo number_format($batch_summary['total_remaining'], 2); ?> ر.س</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div style="text-align: center; padding: 2rem; border-bottom: 2px solid var(--border-color);">
                    <h2 style="color: var(--primary-color); margin-bottom: 0.5rem;">التقرير المالي الشامل</h2>
                    <p style="color: var(--text-secondary);">تاريخ التقرير: <?php echo date('Y-m-d H:i'); ?></p>
                </div>
                
                <div style="padding: 2rem;">
                    <h3 style="margin-bottom: 1.5rem; color: var(--primary-color);">ملخص الميزانيات</h3>
                    
                    <div class="stats-grid" style="margin-bottom: 2rem;">
                        <div class="stat-card success">
                            <div class="stat-label">إجمالي الميزانية المخصصة</div>
                            <div class="stat-value"><?php echo number_format($totals['total_budget'], 2); ?> ر.س</div>
                        </div>
                        
                        <div class="stat-card danger">
                            <div class="stat-label">إجمالي المصروفات</div>
                            <div class="stat-value"><?php echo number_format($totals['total_spent'], 2); ?> ر.س</div>
                        </div>
                        
                        <div class="stat-card warning">
                            <div class="stat-label">المتبقي</div>
                            <div class="stat-value"><?php echo number_format($totals['total_budget'] - $totals['total_spent'], 2); ?> ر.س</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-label">نفقات الإدارة</div>
                            <div class="stat-value"><?php echo number_format($admin_total, 2); ?> ر.س</div>
                        </div>
                    </div>
                    
                    <h3 style="margin: 2rem 0 1rem; color: var(--primary-color);">تفاصيل الأقسام</h3>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr style="background: var(--primary-color); color: white;">
                                    <th>اسم القسم</th>
                                    <th>الكود</th>
                                    <th>الميزانية المخصصة</th>
                                    <th>المصروف</th>
                                    <th>المتبقي</th>
                                    <th>النسبة المصروفة</th>
                                    <th>عدد النفقات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $dept): 
                                    $percentage = $dept['allocated_budget'] > 0 ? ($dept['spent_amount'] / $dept['allocated_budget']) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($dept['name_ar']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($dept['code']); ?></td>
                                        <td><?php echo number_format($dept['allocated_budget'], 2); ?> ر.س</td>
                                        <td style="color: #ef4444;"><?php echo number_format($dept['spent_amount'], 2); ?> ر.س</td>
                                        <td style="color: #10b981;"><?php echo number_format($dept['remaining'], 2); ?> ر.س</td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <div style="flex: 1; background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                                                    <div style="width: <?php echo min($percentage, 100); ?>%; height: 100%; background: <?php echo $percentage > 90 ? '#ef4444' : ($percentage > 70 ? '#f59e0b' : '#10b981'); ?>;"></div>
                                                </div>
                                                <span style="font-size: 0.875rem;"><?php echo number_format($percentage, 1); ?>%</span>
                                            </div>
                                        </td>
                                        <td><?php echo $dept['expense_count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot style="background: #f9fafb; font-weight: bold;">
                                <tr>
                                    <td colspan="2">الإجمالي</td>
                                    <td><?php echo number_format($totals['total_budget'], 2); ?> ر.س</td>
                                    <td style="color: #ef4444;"><?php echo number_format($totals['total_spent'], 2); ?> ر.س</td>
                                    <td style="color: #10b981;"><?php echo number_format($totals['total_budget'] - $totals['total_spent'], 2); ?> ر.س</td>
                                    <td><?php echo $totals['total_budget'] > 0 ? number_format(($totals['total_spent'] / $totals['total_budget']) * 100, 1) : 0; ?>%</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td colspan="2">نفقات الإدارة</td>
                                    <td colspan="5" style="color: #ef4444;"><?php echo number_format($admin_total, 2); ?> رRetryIContinue.س</td>
</tr>
<tr style="background: var(--primary-color); color: white;">
<td colspan="2">الإجمالي الكلي للمصروفات</td>
<td colspan="5"><?php echo number_format($totals['total_spent'] + $admin_total, 2); ?> ر.س</td>
</tr>
</tfoot>
</table>
</div>
</div>
</div>
</div>
</main>
<script>
    function toggleMenu() {
        document.getElementById('navMenu').classList.toggle('active');
    }
</script>
</body>
</html>
