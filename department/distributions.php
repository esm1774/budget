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

// جلب جميع الدفعات المستلمة
$query = "SELECT 
          bd.id,
          bd.amount,
          bd.distribution_date,
          bd.notes,
          bb.batch_number,
          bb.batch_name,
          bb.received_date,
          bb.amount as batch_total_amount,
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

// حساب الإحصائيات
$total_received = 0;
$distributions_by_month = [];

foreach ($distributions as $dist) {
    $total_received += $dist['amount'];
    $month = date('Y-m', strtotime($dist['distribution_date']));
    if (!isset($distributions_by_month[$month])) {
        $distributions_by_month[$month] = 0;
    }
    $distributions_by_month[$month] += $dist['amount'];
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الدفعات المستلمة</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="main-content">
        <div class="container">
            <h1 class="mb-3">الدفعات المالية المستلمة</h1>

            <!-- الإحصائيات -->
            <div class="stats-grid">
                <div class="stat-card success">
                    <div class="stat-label">إجمالي المبالغ المستلمة</div>
                    <div class="stat-value"><?php echo number_format($total_received, 2); ?> ر.س</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">عدد الدفعات</div>
                    <div class="stat-value"><?php echo count($distributions); ?></div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-label">متوسط الدفعة</div>
                    <div class="stat-value">
                        <?php echo count($distributions) > 0 ? number_format($total_received / count($distributions), 2) : '0.00'; ?> ر.س
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">آخر دفعة</div>
                    <div class="stat-value" style="font-size: 1.25rem;">
                        <?php 
                        if (count($distributions) > 0) {
                            echo date('Y-m-d', strtotime($distributions[0]['distribution_date']));
                        } else {
                            echo '-';
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- جدول الدفعات -->
            <div class="card">
                <div class="card-header">سجل جميع الدفعات المستلمة</div>
                
                <?php if (count($distributions) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>رقم الدفعة</th>
                                <th>اسم الدفعة</th>
                                <th>المبلغ المستلم</th>
                                <th>تاريخ التوزيع</th>
                                <th>تاريخ استلام الإدارة</th>
                                <th>تم بواسطة</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            foreach ($distributions as $dist): 
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <strong style="color: #2563eb; font-size: 1.125rem;">
                                            <?php echo htmlspecialchars($dist['batch_number']); ?>
                                        </strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($dist['batch_name']); ?></td>
                                    <td style="font-weight: bold; color: #10b981; font-size: 1.25rem;">
                                        <?php echo number_format($dist['amount'], 2); ?> ر.س
                                    </td>
                                    <td>
                                        <span style="background: #dbeafe; color: #1e40af; padding: 0.25rem 0.75rem; border-radius: 4px;">
                                            <?php echo date('Y-m-d', strtotime($dist['distribution_date'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($dist['received_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($dist['distributed_by']); ?></td>
                                    <td>
                                        <?php 
                                        if ($dist['notes']) {
                                            echo htmlspecialchars(mb_substr($dist['notes'], 0, 50));
                                            if (strlen($dist['notes']) > 50) echo '...';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot style="background: #f0fdf4; font-weight: bold;">
                            <tr>
                                <td colspan="3">الإجمالي الكلي</td>
                                <td style="color: #15803d; font-size: 1.5rem;">
                                    <?php echo number_format($total_received, 2); ?> ر.س
                                </td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <div style="padding: 4rem; text-align: center;">
                    <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;">📭</div>
                    <h3 style="color: #6b7280; margin-bottom: 0.5rem;">لا توجد دفعات مستلمة</h3>
                    <p style="color: #9ca3af;">سيتم عرض الدفعات المالية هنا عند توزيعها من قبل السكرتير</p>
                </div>
                <?php endif; ?>
            </div>

            <?php if (count($distributions_by_month) > 0): ?>
            <!-- توزيع الدفعات حسب الشهر -->
            <div class="card">
                <div class="card-header">توزيع الدفعات حسب الشهر</div>
                <div style="padding: 2rem;">
                    <div style="display: grid; gap: 1rem;">
                        <?php 
                        krsort($distributions_by_month); // ترتيب من الأحدث
                        foreach ($distributions_by_month as $month => $amount): 
                            $percentage = $total_received > 0 ? ($amount / $total_received) * 100 : 0;
                            $month_name = date('F Y', strtotime($month . '-01'));
                        ?>
                        <div style="background: #f9fafb; padding: 1rem; border-radius: 6px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <strong><?php echo $month_name; ?></strong>
                                <span style="color: #10b981; font-weight: bold;">
                                    <?php echo number_format($amount, 2); ?> ر.س
                                </span>
                            </div>
                            <div style="background: #e5e7eb; height: 10px; border-radius: 5px; overflow: hidden;">
                                <div style="width: <?php echo $percentage; ?>%; height: 100%; background: linear-gradient(90deg, #10b981 0%, #059669 100%);"></div>
                            </div>
                            <div style="text-align: left; font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem;">
                                <?php echo number_format($percentage, 1); ?>% من الإجمالي
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
<!-- رسم بياني بسيط -->
            <?php if (count($distributions) >= 3): ?>
            <div class="card">
                <div class="card-header">الاتجاه الزمني للدفعات</div>
                <div style="padding: 2rem;">
                    <div style="display: flex; align-items: flex-end; justify-content: space-around; height: 300px; border-bottom: 2px solid #e5e7eb; gap: 1rem;">
                        <?php 
                        $recent_dists = array_slice(array_reverse($distributions), 0, 10);
                        $max_amount = max(array_column($recent_dists, 'amount'));
                        
                        foreach ($recent_dists as $dist):
                            $height = $max_amount > 0 ? ($dist['amount'] / $max_amount) * 100 : 0;
                        ?>
                        <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                            <div style="width: 100%; background: linear-gradient(180deg, #10b981 0%, #059669 100%); border-radius: 8px 8px 0 0; position: relative; height: <?php echo $height; ?>%; min-height: 30px;">
                                <div style="position: absolute; top: -25px; left: 50%; transform: translateX(-50%); font-size: 0.75rem; font-weight: bold; white-space: nowrap;">
                                    <?php echo number_format($dist['amount'], 0); ?>
                                </div>
                            </div>
                            <div style="margin-top: 0.5rem; font-size: 0.75rem; text-align: center; color: #6b7280;">
                                <?php echo date('m/d', strtotime($dist['distribution_date'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="text-align: center; margin-top: 1rem; color: #6b7280; font-size: 0.875rem;">
                        آخر <?php echo count($recent_dists); ?> دفعات
                    </div>
                </div>
            </div>
            <?php endif; ?>

    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>