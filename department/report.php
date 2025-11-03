<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireDepartment();

$department_id = $_SESSION['department_id'];

// جلب بيانات القسم
$query = "SELECT * FROM departments WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $department_id);
$stmt->execute();
$department = $stmt->fetch(PDO::FETCH_ASSOC);

// جلب جميع النفقات
$query = "SELECT e.*, 
          (SELECT COUNT(*) FROM invoices WHERE expense_id = e.id) as invoice_count
          FROM expenses e 
          WHERE e.department_id = :dept_id 
          ORDER BY e.expense_date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department_id);
$stmt->execute();
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات حسب الفئة
$query = "SELECT category, SUM(amount) as total, COUNT(*) as count
          FROM expenses 
          WHERE department_id = :dept_id 
          GROUP BY category
          ORDER BY total DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department_id);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تصدير إلى Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="department_report_' . $department['code'] . '_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    
    echo "<html><head><meta charset='utf-8'></head><body>";
    echo "<table border='1'>";
    echo "<tr><th colspan='7' style='text-align:center; font-size:18px;'><b>تقرير نفقات قسم " . htmlspecialchars($department['name_ar']) . "</b></th></tr>";
    echo "<tr><th colspan='7' style='text-align:center;'>تاريخ التقرير: " . date('Y-m-d H:i') . "</th></tr>";
    echo "<tr><td colspan='7'></td></tr>";
    
    echo "<tr><th>الميزانية المخصصة</th><th colspan='6'>" . number_format($department['allocated_budget'], 2) . " ر.س</th></tr>";
    echo "<tr><th>إجمالي المصروفات</th><th colspan='6'>" . number_format($department['spent_amount'], 2) . " ر.س</th></tr>";
    echo "<tr><th>المتبقي</th><th colspan='6'>" . number_format($department['allocated_budget'] - $department['spent_amount'], 2) . " ر.س</th></tr>";
    echo "<tr><td colspan='7'></td></tr>";
    
    echo "<tr><th>التاريخ</th><th>الفئة</th><th>الوصف</th><th>المبلغ</th><th>طريقة الدفع</th><th>المورد</th><th>الملاحظات</th></tr>";
    
    foreach ($expenses as $expense) {
        $payment_methods = [
            'cash' => 'نقداً',
            'bank_transfer' => 'تحويل بنكي',
            'check' => 'شيك',
            'credit_card' => 'بطاقة ائتمان'
        ];
        
        echo "<tr>";
        echo "<td>" . date('Y-m-d', strtotime($expense['expense_date'])) . "</td>";
        echo "<td>" . htmlspecialchars($expense['category']) . "</td>";
        echo "<td>" . htmlspecialchars($expense['description']) . "</td>";
        echo "<td>" . number_format($expense['amount'], 2) . "</td>";
        echo "<td>" . ($payment_methods[$expense['payment_method']] ?? $expense['payment_method']) . "</td>";
        echo "<td>" . htmlspecialchars($expense['vendor_name']) . "</td>";
        echo "<td>" . htmlspecialchars($expense['notes']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table></body></html>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير النفقات</title>
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
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="flex flex-between flex-center mb-3 no-print">
                <h1>تقرير النفقات</h1>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="btn btn-primary">🖨️ طباعة</button>
                    <a href="?export=excel" class="btn btn-success">📊 تصدير Excel</a>
                </div>
            </div>

            <div class="card">
                <div style="text-align: center; padding: 2rem; border-bottom: 2px solid var(--border-color);">
                    <h2 style="color: var(--primary-color); margin-bottom: 0.5rem;">
                        تقرير نفقات قسم <?php echo htmlspecialchars($department['name_ar']); ?>
                    </h2>
                    <p style="color: var(--text-secondary);">الكود: <?php echo htmlspecialchars($department['code']); ?> | تاريخ التقرير: <?php echo date('Y-m-d H:i'); ?></p>
                </div>
                
                <div style="padding: 2rem;">
                    <div class="stats-grid" style="margin-bottom: 2rem;">
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
                            <div class="stat-value"><?php echo count($expenses); ?></div>
                        </div>
                    </div>
                    
                    <?php if (count($categories) > 0): ?>
                    <h3 style="margin: 2rem 0 1rem; color: var(--primary-color);">توزيع المصروفات حسب الفئة</h3>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>الفئة</th>
                                    <th>عدد النفقات</th>
                                    <th>الإجمالي</th>
                                    <th>النسبة من الميزانية</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): 
                                    $percentage = $department['allocated_budget'] > 0 ? ($cat['total'] / $department['allocated_budget']) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($cat['category']); ?></strong></td>
                                        <td><?php echo $cat['count']; ?></td>
                                        <td style="color: #ef4444;"><?php echo number_format($cat['total'], 2); ?> ر.س</td>
                                        <td><?php echo number_format($percentage, 1); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <h3 style="margin: 2rem 0 1rem; color: var(--primary-color);">تفاصيل جميع النفقات</h3>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>التاريخ</th>
                                    <th>الفئة</th>
                                    <th>الوصف</th>
                                    <th>المبلغ</th>
                                    <th>طريقة الدفع</th>
                                    <th>المورد</th>
                                    <th class="no-print">الفواتير</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($expenses) > 0): ?>
                                    <?php foreach ($expenses as $expense): 
                                        $payment_methods = [
                                            'cash' => 'نقداً',
                                            'bank_transfer' => 'تحويل بنكي',
                                            'check' => 'شيك',
                                            'credit_card' => 'بطاقة ائتمان'
                                        ];
                                    ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d', strtotime($expense['expense_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($expense['category']); ?></td>
                                            <td><?php echo htmlspecialchars($expense['description']); ?></td>
                                            <td style="font-weight: bold; color: #ef4444;">
                                                <?php echo number_format($expense['amount'], 2); ?> ر.س
                                            </td>
                                            <td><?php echo $payment_methods[$expense['payment_method']] ?? $expense['payment_method']; ?></td>
                                            <td><?php echo htmlspecialchars($expense['vendor_name']); ?></td>
                                            <td class="no-print">
                                                <?php if ($expense['invoice_count'] > 0): ?>
                                                    <a href="expense_details.php?id=<?php echo $expense['id']; ?>">
                                                        <?php echo $expense['invoice_count']; ?> 📎
                                                    </a>
                                                <?php else: ?>
                                                    -
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