<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireDepartment();

$department_id = $_SESSION['department_id'];
$selected_batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;

// جلب بيانات القسم (المدرسة)
$query = "SELECT * FROM departments WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $department_id, PDO::PARAM_INT);
$stmt->execute();
$department = $stmt->fetch(PDO::FETCH_ASSOC);

// محاولة جلب اسم المدير من جدول users
try {
    $query = "SELECT full_name FROM users WHERE department_id = :dept_id AND role = 'department' LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
    $stmt->execute();
    $manager = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($manager && !empty($manager['full_name'])) {
        $department['manager_name'] = $manager['full_name'];
    } else {
        $department['manager_name'] = $_SESSION['full_name'];
    }
} catch (PDOException $e) {
    // في حالة حدوث خطأ، استخدم اسم المستخدم الحالي
    $department['manager_name'] = $_SESSION['full_name'];
}

// جلب الدفعات المستلمة
$query = "SELECT 
          bd.id as distribution_id,
          bd.amount,
          bd.distribution_date,
          bd.batch_id,
          bb.batch_number,
          bb.batch_name
          FROM budget_distributions bd
          LEFT JOIN budget_batches bb ON bd.batch_id = bb.id
          WHERE bd.department_id = :dept_id
          ORDER BY bd.distribution_date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
$stmt->execute();
$distributions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب النفقات (حسب الدفعة إذا تم اختيارها)
if ($selected_batch_id > 0) {
    // جلب النفقات المرتبطة بالدفعة
    $query = "SELECT e.*, 
              (SELECT COUNT(*) FROM invoices WHERE expense_id = e.id) as invoice_count
              FROM expenses e 
              WHERE e.department_id = :dept_id 
              AND e.batch_id = :batch_id
              ORDER BY e.expense_date DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
    $stmt->bindParam(':batch_id', $selected_batch_id, PDO::PARAM_INT);
} else {
    // جلب جميع النفقات
    $query = "SELECT e.*, 
              (SELECT COUNT(*) FROM invoices WHERE expense_id = e.id) as invoice_count
              FROM expenses e 
              WHERE e.department_id = :dept_id 
              ORDER BY e.expense_date DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
}
$stmt->execute();
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات حسب الفئة
if ($selected_batch_id > 0) {
    $query = "SELECT category, SUM(amount) as total, COUNT(*) as count
              FROM expenses 
              WHERE department_id = :dept_id AND batch_id = :batch_id
              GROUP BY category
              ORDER BY total DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
    $stmt->bindParam(':batch_id', $selected_batch_id, PDO::PARAM_INT);
} else {
    $query = "SELECT category, SUM(amount) as total, COUNT(*) as count
              FROM expenses 
              WHERE department_id = :dept_id 
              GROUP BY category
              ORDER BY total DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
}
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تصدير إلى Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    
    // جلب معلومات الدفعة المحددة
    $batch_info = null;
    if ($selected_batch_id > 0) {
        $query = "SELECT bb.*, bd.amount as distributed_amount, bd.distribution_date
                  FROM budget_batches bb
                  LEFT JOIN budget_distributions bd ON bb.id = bd.batch_id AND bd.department_id = :dept_id
                  WHERE bb.id = :batch_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':batch_id', $selected_batch_id, PDO::PARAM_INT);
        $stmt->bindParam(':dept_id', $department_id, PDO::PARAM_INT);
        $stmt->execute();
        $batch_info = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // حساب الإجماليات
    $total_expenses = array_sum(array_column($expenses, 'amount'));
    $batch_amount = $batch_info ? $batch_info['distributed_amount'] : $department['total_received'];
    $remaining = $batch_amount - $total_expenses;
    
    // إعداد Headers لملف Excel
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    $filename = 'expense_report_' . $department['code'] . '_' . date('Y-m-d') . '.xls';
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // UTF-8 BOM
    echo "\xEF\xBB\xBF";
    
    ?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Arial', sans-serif; direction: rtl; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 8px; text-align: center; font-size: 12pt; }
        .no-border { border: none; }
        .title { font-size: 18pt; font-weight: bold; text-align: center; padding: 15px; }
        .header-info { text-align: right; font-weight: bold; border: none; padding: 5px; }
        .data-header { background-color: #90ee90; font-weight: bold; }
        .total-row { background-color: #d0f0c0; font-weight: bold; font-size: 14pt; }
        .signature-header { background-color: #e8f5e9; font-weight: bold; }
    </style>
</head>
<body>

<table border="0" width="100%">
    <!-- معلومات العهدة في الأعلى -->
    <tr>
        <td class="header-info" colspan="5">
            رقم العهدة: <?php echo $batch_info ? htmlspecialchars($batch_info['batch_name']) : '___________'; ?>
        </td>
    </tr>
    <tr>
        <td class="header-info" colspan="5">
            القسم: <?php echo htmlspecialchars($department['name_ar']); ?>
        </td>
    </tr>
    <tr>
        <td class="header-info" colspan="5">
            التاريخ: <?php echo date('Y/m/d'); ?>
        </td>
    </tr>
    
    <!-- فراغ -->
    <tr>
        <td colspan="5" class="no-border" style="height: 20px;"></td>
    </tr>
    
    <!-- العنوان -->
    <tr>
        <td colspan="5" class="title">طلب تسوية عهدة</td>
    </tr>
    
    <!-- فراغ -->
    <tr>
        <td colspan="5" class="no-border" style="height: 10px;"></td>
    </tr>
</table>

<!-- جدول البيانات -->
<table border="1" cellpadding="8" cellspacing="0" width="100%">
    <!-- رأس الجدول -->
    <tr class="data-header">
        <td style="width: 8%;">#</td>
        <td style="width: 12%;">التاريخ</td>
        <td style="width: 15%;">المبلغ</td>
        <td style="width: 35%;">البيان</td>
        <td style="width: 30%;">المناسبة / الفاعلية</td>
    </tr>
    
    <!-- صفوف البيانات -->
    <?php 
    $counter = 1;
    $total_amount = 0;
    
    // إضافة صفوف فارغة إذا كان عدد النفقات أقل من 15
    $rows_to_display = max(15, count($expenses));
    
    for ($i = 0; $i < $rows_to_display; $i++): 
        if (isset($expenses[$i])):
            $expense = $expenses[$i];
            $total_amount += $expense['amount'];
    ?>
    <tr>
        <td><?php echo $counter++; ?></td>
        <td><?php echo date('Y/m/d', strtotime($expense['expense_date'])); ?></td>
        <td><?php echo number_format($expense['amount'], 2); ?></td>
        <td style="text-align: right; padding-right: 10px;">
            <?php echo htmlspecialchars($expense['description']); ?>
        </td>
        <td style="text-align: right; padding-right: 10px;">
            <?php echo htmlspecialchars($expense['notes'] ?: '-'); ?>
        </td>
    </tr>
    <?php 
        else:
    ?>
    <tr>
        <td><?php echo $counter++; ?></td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
    </tr>
    <?php 
        endif;
    endfor; 
    ?>
    
    <!-- صف الإجمالي -->
    <tr class="total-row">
        <td colspan="2">الإجمالي</td>
        <td><?php echo number_format($total_amount, 2); ?></td>
        <td colspan="2"></td>
    </tr>
</table>

<!-- فراغ -->
<table border="0" width="100%">
    <tr>
        <td colspan="3" class="no-border" style="height: 30px;"></td>
    </tr>
</table>

<!-- جدول التوقيعات -->
<table border="0" cellpadding="10" cellspacing="0" width="100%">
    <!-- صف العناوين -->
    <tr class="signature-header">
        <td class="no-border" style="width: 33.33%;">مسؤول العهدة</td>
        <td class="no-border" style="width: 33.33%;"></td>
        <td class="no-border" style="width: 33.33%;">مدير المدرسة</td>
        <td class="no-border" style="width: 33.33%;"></td>
        <td class="no-border" style="width: 33.33%;">مدير المجمع</td>
    </tr>
    
    <!-- صف الأسماء -->
    <tr>
        <td class="no-border" style="height: 50px;">
            <?php echo htmlspecialchars($department['manager_name'] ?? '___________________'); ?>
        </td>
        <td class="no-border"></td>
        <td class="no-border" style="height: 50px;">
            <?php echo htmlspecialchars($_SESSION['full_name']); ?>
        </td>
        <td class="no-border"></td>
        <td class="no-border" style="height: 50px;">
            سعد بن عبدالله القرني
        </td>
    </tr>
    

</table>

</body>
</html>
    <?php
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
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
    <?php include '../includes/header.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="flex flex-between flex-center mb-3 no-print">
                <h1>تقرير النفقات</h1>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="btn btn-primary">🖨️ طباعة</button>
                    <a href="?export=excel<?php echo $selected_batch_id > 0 ? '&batch_id=' . $selected_batch_id : ''; ?>" class="btn btn-success">📊 تصدير Excel</a>
                </div>
            </div>

            <!-- اختيار الدفعة -->
            <div class="card no-print">
                <div class="card-header">فلترة التقرير</div>
                <div style="padding: 1.5rem;">
                    <form method="GET" action="">
                        <div class="form-group">
                            <label class="form-label">اختر الدفعة (اختياري)</label>
                            <select name="batch_id" class="form-select" onchange="this.form.submit()">
                                <option value="0">جميع الدفعات</option>
                                <?php foreach ($distributions as $dist): ?>
                                    <option value="<?php echo $dist['batch_id']; ?>" 
                                            <?php echo $selected_batch_id == $dist['batch_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dist['batch_number'] . ' - ' . $dist['batch_name']); ?>
                                        (<?php echo number_format($dist['amount'], 2); ?> ر.س)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div style="text-align: center; padding: 2rem; border-bottom: 2px solid var(--border-color);">
                    <h2 style="color: var(--primary-color); margin-bottom: 0.5rem;">
                        تقرير نفقات قسم <?php echo htmlspecialchars($department['name_ar']); ?>
                    </h2>
                    <p style="color: var(--text-secondary);">
                        الكود: <?php echo htmlspecialchars($department['code']); ?> | 
                        تاريخ التقرير: <?php echo date('Y-m-d H:i'); ?>
                        <?php if ($selected_batch_id > 0 && isset($batch_info)): ?>
                            <br>الدفعة: <?php echo htmlspecialchars($batch_info['batch_number'] . ' - ' . $batch_info['batch_name']); ?>
                        <?php endif; ?>
                    </p>
                </div>
                
                <div style="padding: 2rem;">
                    <div class="stats-grid" style="margin-bottom: 2rem;">
                        <div class="stat-card success">
                            <div class="stat-label">
                                <?php echo $selected_batch_id > 0 ? 'قيمة الدفعة' : 'إجمالي المبالغ المستلمة'; ?>
                            </div>
                            <div class="stat-value">
                                <?php 
                                if ($selected_batch_id > 0 && isset($batch_info)) {
                                    echo number_format($batch_info['distributed_amount'], 2);
                                } else {
                                    echo number_format($department['total_received'], 2);
                                }
                                ?> ر.س
                            </div>
                        </div>
                        
                        <div class="stat-card danger">
                            <div class="stat-label">إجمالي المصروفات</div>
                            <div class="stat-value"><?php echo number_format(array_sum(array_column($expenses, 'amount')), 2); ?> ر.س</div>
                        </div>
                        
                        <div class="stat-card warning">
                            <div class="stat-label">المتبقي</div>
                            <div class="stat-value">
                                <?php 
                                $received = $selected_batch_id > 0 && isset($batch_info) 
                                    ? $batch_info['distributed_amount'] 
                                    : $department['total_received'];
                                $spent = array_sum(array_column($expenses, 'amount'));
                                echo number_format($received - $spent, 2);
                                ?> ر.س
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-label">عدد النفقات</div>
                            <div class="stat-value"><?php echo count($expenses); ?></div>
                        </div>
                    </div>
                    
                    <!-- الدفعات المستلمة -->
                    <?php if (count($distributions) > 0 && $selected_batch_id == 0): ?>
                    <div class="card">
                        <div class="card-header" style="background: #10b981; color: white;">
                            💰 سجل الدفعات المالية المستلمة
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead style="background: #f0fdf4;">
                                    <tr>
                                        <th>#</th>
                                        <th>رقم الدفعة</th>
                                        <th>اسم الدفعة</th>
                                        <th>المبلغ</th>
                                        <th>تاريخ الاستلام</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_dist = 0;
                                    $counter = 1;
                                    foreach ($distributions as $dist): 
                                        $total_dist += $dist['amount'];
                                    ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><strong><?php echo htmlspecialchars($dist['batch_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($dist['batch_name']); ?></td>
                                            <td style="color: #10b981; font-weight: bold;">
                                                <?php echo number_format($dist['amount'], 2); ?> ر.س
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($dist['distribution_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot style="background: #f0fdf4; font-weight: bold;">
                                    <tr>
                                        <td colspan="3">إجمالي الدفعات المستلمة</td>
                                        <td style="color: #15803d; font-size: 1.25rem;">
                                            <?php echo number_format($total_dist, 2); ?> ر.س
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (count($categories) > 0): ?>
                    <h3 style="margin: 2rem 0 1rem; color: var(--primary-color);">توزيع المصروفات حسب الفئة</h3>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>الفئة</th>
                                    <th>عدد النفقات</th>
                                    <th>الإجمالي</th>
                                    <th>النسبة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_cat = array_sum(array_column($categories, 'total'));
                                foreach ($categories as $cat): 
                                    $percentage = $total_cat > 0 ? ($cat['total'] / $total_cat) * 100 : 0;
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
                    
                    <h3 style="margin: 2rem 0 1rem; color: var(--primary-color);">تفاصيل النفقات</h3>
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
                                    <?php 
                                    $payment_methods = [
                                        'cash' => 'نقداً',
                                        'bank_transfer' => 'تحويل بنكي',
                                        'check' => 'شيك',
                                        'credit_card' => 'بطاقة ائتمان'
                                    ];
                                    foreach ($expenses as $expense): 
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