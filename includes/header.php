<?php
// تأكد من بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تحقق من نوع المستخدم (مثلاً: admin أو department)
$user_role = $_SESSION['role'] ?? 'user'; // قيمة افتراضية "user"

// إذا كان المستخدم أدمن
if ($user_role === 'admin'): ?>
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

<?php else: // غير الأدمن (الأقسام أو المستخدمين الآخرين) ?>
    <header class="header no-print">
        <div class="container">
            <nav class="navbar">
                <div class="logo">💼 <?php echo htmlspecialchars($department['name_ar'] ?? 'القسم'); ?></div>
                <ul class="nav-menu" id="navMenu">
                    <li><a href="dashboard.php">الرئيسية</a></li>
                    <li><a href="expenses.php">المصروفات</a></li>
                    <li><a href="distributions.php">العهد المستلمة</a></li>
                    <li><a href="report.php" class="active">التقرير</a></li>
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
<?php endif; ?>
