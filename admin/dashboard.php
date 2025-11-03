<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireAdmin();

// جلب إحصائيات الدفعات المالية
$query = "SELECT 
          SUM(amount) as total_received,
          SUM(distributed_amount) as total_distributed,
          SUM(remaining_amount) as total_remaining
          FROM budget_batches WHERE status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$batch_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// جلب الإحصائيات
$stats = [];

// إجمالي الأقسام
$query = "SELECT COUNT(*) as total FROM departments WHERE is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_departments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// إجمالي الميزانية المخصصة
$query = "SELECT SUM(allocated_budget) as total FROM departments WHERE is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_budget'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// إجمالي المصروفات
$query = "SELECT SUM(spent_amount) as total FROM departments WHERE is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_spent'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// المتبقي
$stats['remaining_budget'] = $stats['total_budget'] - $stats['total_spent'];

// مصروفات السكرتير
$query = "SELECT SUM(amount) as total FROM admin_expenses";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['admin_expenses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// جلب بيانات الأقسام
$query = "SELECT d.*,
          (SELECT COUNT(*) FROM expenses WHERE department_id = d.id) as expense_count,
          (d.allocated_budget - d.spent_amount) as remaining
          FROM departments d
          WHERE d.is_active = 1
          ORDER BY d.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - السكرتير</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Header -->
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
                    <a href="../logout.php" class="btn btn-danger">خروج</a>
                </div>
                <div class="menu-toggle" onclick="toggleMenu()">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <h1 class="mb-3">لوحة التحكم</h1>
               
            <!-- إحصائيات الدفعات المالية -->
            <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 2rem;">
                <div class="card-header" style="border-bottom-color: rgba(255,255,255,0.2); color: white;">
                    <div class="flex flex-between flex-center">
                        <span>💰 المبالغ المستلمة من الشركة الرئيسية</span>
                        <a href="budget_batches.php" class="btn" style="background: white; color: #667eea;">
                            إدارة الدفعات
                        </a>
                    </div>
                </div>
                <div style="padding: 2rem;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem;">
                        <div style="text-align: center;">
                            <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">إجمالي المستلم</div>
                            <div style="font-size: 2rem; font-weight: bold;">
                                <?php echo number_format($batch_stats['total_received'] ?? 0, 2); ?> ر.س
                            </div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">الموزع على الأقسام</div>
                            <div style="font-size: 2rem; font-weight: bold;">
                                <?php echo number_format($batch_stats['total_distributed'] ?? 0, 2); ?> ر.س
                            </div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">المتبقي للتوزيع</div>
                            <div style="font-size: 2rem; font-weight: bold;">
                                <?php echo number_format($batch_stats['total_remaining'] ?? 0, 2); ?> ر.س
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">إجمالي الأقسام</div>
                    <div class="stat-value"><?php echo $stats['total_departments']; ?></div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-label">إجمالي الميزانية</div>
                    <div class="stat-value"><?php echo number_format($stats['total_budget'], 2); ?> ر.س</div>
                </div>
                
                <div class="stat-card danger">
                    <div class="stat-label">إجمالي المصروفات</div>
                    <div class="stat-value"><?php echo number_format($stats['total_spent'], 2); ?> ر.س</div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-label">المتبقي</div>
                    <div class="stat-value"><?php echo number_format($stats['remaining_budget'], 2); ?> ر.س</div>
                </div>
            </div>

            <!-- مصروفات الإدارة -->
            <div class="card">
                <div class="card-header flex flex-between flex-center">
                    <span>مصروفات الإدارة</span>
                    <span style="font-size: 1.5rem; font-weight: bold; color: #ef4444;">
                        <?php echo number_format($stats['admin_expenses'], 2); ?> ر.س
                    </span>
                </div>
            </div>

            <!-- جدول الأقسام -->
            <div class="card">
                <div class="card-header flex flex-between flex-center">
                    <span>الأقسام</span>
                    <a href="departments.php?action=new" class="btn btn-primary">+ إضافة قسم جديد</a>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>اسم القسم</th>
                                <th>الكود</th>
                                <th>الميزانية المخصصة</th>
                                <th>المصروف</th>
                                <th>المتبقي</th>
                                <th>عدد النفقات</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($departments) > 0): ?>
                                <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dept['name_ar']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($dept['code']); ?></strong></td>
                                        <td><?php echo number_format($dept['allocated_budget'], 2); ?> ر.س</td>
                                        <td style="color: #ef4444;"><?php echo number_format($dept['spent_amount'], 2); ?> ر.س</td>
                                        <td style="color: #10b981;"><?php echo number_format($dept['remaining'], 2); ?> ر.س</td>
                                        <td><?php echo $dept['expense_count']; ?></td>
                                        <td>
                                            <a href="department_details.php?id=<?php echo $dept['id']; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                                عرض التفاصيل
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">لا توجد أقسام مسجلة</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>
