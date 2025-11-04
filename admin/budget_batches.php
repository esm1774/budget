<?php
// تفعيل عرض الأخطاء للتطوير (احذفها في الإنتاج)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireAdmin();

$message = '';
$error = '';

/* ==============================
   🟢 إضافة دفعة مالية جديدة
   ============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_batch') {
    $batch_number = trim($_POST['batch_number'] ?? '');
    $batch_name   = trim($_POST['batch_name'] ?? '');
    $amount       = floatval($_POST['amount'] ?? 0);
    $received_date = !empty($_POST['received_date']) ? $_POST['received_date'] : date('Y-m-d');
    $notes        = trim($_POST['notes'] ?? '');

    if (empty($batch_number) || empty($batch_name) || $amount <= 0) {
        $error = 'يرجى ملء جميع الحقول المطلوبة';
    } else {
        try {
            $db->beginTransaction();

            $query = "INSERT INTO budget_batches 
                      (batch_number, batch_name, amount, received_date, distributed_amount, remaining_amount, status, notes, created_by)
                      VALUES (:batch_number, :batch_name, :amount, :received_date, :distributed_amount, :remaining_amount, :status, :notes, :created_by)";
            $stmt = $db->prepare($query);
            
            $distributed_amount = 0.00;
            $remaining_amount = $amount;
            $status = 'active';

            $stmt->bindParam(':batch_number', $batch_number, PDO::PARAM_STR);
            $stmt->bindParam(':batch_name', $batch_name, PDO::PARAM_STR);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':received_date', $received_date, PDO::PARAM_STR);
            $stmt->bindParam(':distributed_amount', $distributed_amount);
            $stmt->bindParam(':remaining_amount', $remaining_amount);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
            $stmt->bindParam(':created_by', $_SESSION['user_id'], PDO::PARAM_INT);
            
            $stmt->execute();
            $batch_id = $db->lastInsertId();
            
            $db->commit();
            
            $auth->logActivity($_SESSION['user_id'], 'create_batch', 'budget_batches', $batch_id, "إضافة دفعة مالية: $batch_name");
            
            header("Location: budget_batches.php?success=created");
            exit;
            
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Create batch error: " . $e->getMessage());
            
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = '⚠️ رقم الدفعة مستخدم بالفعل';
            } else {
                $error = '❌ حدث خطأ أثناء إضافة الدفعة المالية: ' . $e->getMessage();
            }
        }
    }
}

/* ==============================
   🟠 توزيع الدفعة على الأقسام
   ============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'distribute') {
    $batch_id = intval($_POST['batch_id'] ?? 0);
    $distributions = $_POST['distributions'] ?? [];

    if ($batch_id <= 0) {
        $error = 'معرف الدفعة غير صحيح';
    } elseif (empty($distributions)) {
        $error = 'يرجى اختيار قسم واحد على الأقل';
    } else {
        try {
            $db->beginTransaction();

            // التحقق من وجود الدفعة والمبلغ المتبقي
            $query = "SELECT id, batch_name, amount, distributed_amount, remaining_amount 
                      FROM budget_batches 
                      WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$batch_id]);
            $batch = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$batch) {
                throw new Exception('الدفعة غير موجودة');
            }

            $total_distributed = 0;
            $valid_distributions = [];

            // التحقق من صحة البيانات أولاً
            foreach ($distributions as $dist) {
                $dept_id = intval($dist['department_id'] ?? 0);
                $amount = floatval($dist['amount'] ?? 0);

                if ($dept_id > 0 && $amount > 0) {
                    $total_distributed += $amount;
                    $valid_distributions[] = [
                        'department_id' => $dept_id,
                        'amount' => $amount
                    ];
                }
            }

            if (empty($valid_distributions)) {
                throw new Exception('لا توجد توزيعات صحيحة');
            }

            if ($total_distributed > $batch['remaining_amount']) {
                throw new Exception(sprintf(
                    'إجمالي المبالغ الموزعة (%.2f ر.س) أكبر من المبلغ المتبقي (%.2f ر.س)',
                    $total_distributed,
                    $batch['remaining_amount']
                ));
            }

            // تنفيذ التوزيعات
            foreach ($valid_distributions as $dist) {
                // إدخال التوزيع
                $query = "INSERT INTO budget_distributions 
                          (batch_id, department_id, amount, distribution_date, created_by)
                          VALUES (?, ?, ?, NOW(), ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $batch_id,
                    $dist['department_id'],
                    $dist['amount'],
                    $_SESSION['user_id']
                ]);

                // تحديث ميزانية القسم
                $query = "UPDATE departments 
                          SET allocated_budget = allocated_budget + ?,
                              total_received = total_received + ?,
                              last_distribution_date = NOW()
                          WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $dist['amount'],
                    $dist['amount'],
                    $dist['department_id']
                ]);

                error_log("Distributed {$dist['amount']} to department {$dist['department_id']}");
            }

            // تحديث معلومات الدفعة
            $new_distributed = $batch['distributed_amount'] + $total_distributed;
            $new_remaining = $batch['amount'] - $new_distributed;

            $query = "UPDATE budget_batches 
                      SET distributed_amount = ?,
                          remaining_amount = ?
                      WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $new_distributed,
                $new_remaining,
                $batch_id
            ]);

            $db->commit();
            
            $auth->logActivity(
                $_SESSION['user_id'], 
                'distribute_batch', 
                'budget_distributions', 
                $batch_id, 
                "توزيع دفعة مالية: " . number_format($total_distributed, 2) . " ر.س"
            );
            
            header("Location: budget_batches.php?success=distributed");
            exit;
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Distribute error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $error = '❌ ' . $e->getMessage();
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Distribute PDO error: " . $e->getMessage());
            $error = '❌ حدث خطأ في قاعدة البيانات: ' . $e->getMessage();
        }
    }
}

// رسائل النجاح
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $message = '✅ تم إضافة الدفعة المالية بنجاح';
            break;
        case 'distributed':
            $message = '✅ تم توزيع الدفعة على الأقسام بنجاح';
            break;
    }
}

/* ==============================
   🔵 جلب البيانات
   ============================== */
try {
    $query = "SELECT b.*, u.full_name as created_by_name,
              (SELECT COUNT(*) FROM budget_distributions WHERE batch_id = b.id) as distribution_count
              FROM budget_batches b
              LEFT JOIN users u ON b.created_by = u.id
              ORDER BY b.received_date DESC, b.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch batches error: " . $e->getMessage());
    $batches = [];
}

try {
    $query = "SELECT id, name_ar, allocated_budget, spent_amount 
              FROM departments 
              WHERE is_active = 1 
              ORDER BY name_ar";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch departments error: " . $e->getMessage());
    $departments = [];
}

try {
    $query = "SELECT 
              COALESCE(SUM(amount), 0) as total_received,
              COALESCE(SUM(distributed_amount), 0) as total_distributed,
              COALESCE(SUM(remaining_amount), 0) as total_remaining,
              COUNT(*) as batch_count
              FROM budget_batches";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch stats error: " . $e->getMessage());
    $stats = [
        'total_received' => 0,
        'total_distributed' => 0,
        'total_remaining' => 0,
        'batch_count' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الدفعات المالية</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow: auto;
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: white;
            margin: 2rem;
            padding: 0;
            border-radius: 8px;
            width: 100%;
            max-width: 800px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .modal-close {
            font-size: 2rem;
            font-weight: bold;
            color: #6b7280;
            cursor: pointer;
            line-height: 1;
        }
        
        .modal-close:hover {
            color: #ef4444;
        }
        
        .modal form {
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="main-content">
        <div class="container">
            <h1 class="mb-3">إدارة الدفعات المالية من الشركة الرئيسية</h1>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?php echo $message; ?>
                    <button onclick="this.parentElement.style.display='none'" style="float:left;background:none;border:none;font-size:1.2rem;cursor:pointer;">×</button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                    <button onclick="this.parentElement.style.display='none'" style="float:left;background:none;border:none;font-size:1.2rem;cursor:pointer;">×</button>
                </div>
            <?php endif; ?>

            <!-- إحصائيات الدفعات -->
            <div class="stats-grid">
                <div class="stat-card success">
                    <div class="stat-label">إجمالي المبالغ المستلمة</div>
                    <div class="stat-value"><?php echo number_format($stats['total_received'], 2); ?> ر.س</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">المبالغ الموزعة</div>
                    <div class="stat-value"><?php echo number_format($stats['total_distributed'], 2); ?> ر.س</div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-label">المبالغ المتبقية للتوزيع</div>
                    <div class="stat-value"><?php echo number_format($stats['total_remaining'], 2); ?> ر.س</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">عدد الدفعات</div>
                    <div class="stat-value"><?php echo $stats['batch_count']; ?></div>
                </div>
            </div>

            <!-- إضافة دفعة جديدة -->
            <div class="card">
                <div class="card-header flex flex-between flex-center">
                    <span>إضافة دفعة مالية جديدة</span>
                    <button class="btn btn-primary" onclick="toggleBatchForm()">+ دفعة جديدة</button>
                </div>
                
                <div id="newBatchForm" style="display: none; padding: 1.5rem; border-top: 1px solid var(--border-color);">
                    <form method="POST" action="" onsubmit="return validateBatchForm()">
                        <input type="hidden" name="action" value="create_batch">
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                            <div class="form-group">
                                <label class="form-label">رقم الدفعة *</label>
                                <input type="text" name="batch_number" class="form-input" required placeholder="مثال: BTH-001">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">اسم الدفعة *</label>
                                <input type="text" name="batch_name" class="form-input" required placeholder="مثال: دفعة شهر يناير 2025">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">المبلغ (ر.س) *</label>
                                <input type="number" name="amount" class="form-input" step="0.01" required min="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">تاريخ الاستلام *</label>
                                <input type="date" name="received_date" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">ملاحظات</label>
                            <textarea name="notes" class="form-textarea" rows="3"></textarea>
                        </div>
                        
                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-success">حفظ الدفعة</button>
                            <button type="button" class="btn btn-secondary" onclick="toggleBatchForm()">إلغاء</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- قائمة الدفعات -->
            <div class="card">
                <div class="card-header">قائمة الدفعات المالية</div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>رقم الدفعة</th>
                                <th>اسم الدفعة</th>
                                <th>المبلغ المستلم</th>
                                <th>الموزع</th>
                                <th>المتبقي</th>
                                <th>تاريخ الاستلام</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($batches) > 0): ?>
                                <?php foreach ($batches as $batch): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($batch['batch_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($batch['batch_name']); ?></td>
                                        <td style="font-weight: bold; color: #10b981;">
                                            <?php echo number_format($batch['amount'], 2); ?> ر.س
                                        </td>
                                        <td style="color: #2563eb;">
                                            <?php echo number_format($batch['distributed_amount'], 2); ?> ر.س
                                        </td>
                                        <td style="color: <?php echo $batch['remaining_amount'] > 0 ? '#f59e0b' : '#6b7280'; ?>;">
                                            <?php echo number_format($batch['remaining_amount'], 2); ?> ر.س
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($batch['received_date'])); ?></td>
                                        <td>
                                            <?php if ($batch['remaining_amount'] <= 0): ?>
                                                <span style="background: #d1fae5; color: #065f46; padding: 0.25rem 0.75rem; border-radius: 4px;">
                                                    ✓ مكتملة
                                                </span>
                                            <?php else: ?>
                                                <span style="background: #fef3c7; color: #92400e; padding: 0.25rem 0.75rem; border-radius: 4px;">
                                                    ⏳ قيد التوزيع
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($batch['remaining_amount'] > 0): ?>
                                                <button onclick="openDistributeModal(<?php echo $batch['id']; ?>, '<?php echo addslashes(htmlspecialchars($batch['batch_name'])); ?>', <?php echo $batch['remaining_amount']; ?>)" 
                                                        class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                                    توزيع
                                                </button>
                                            <?php endif; ?>
                                            <a href="batch_details.php?id=<?php echo $batch['id']; ?>" 
                                               class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                                التفاصيل
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">لا توجد دفعات مسجلة</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal توزيع الدفعة -->
    <div id="distributeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>توزيع الدفعة المالية</h3>
                <span class="modal-close" onclick="closeDistributeModal()">&times;</span>
            </div>
            <form method="POST" action="" id="distributeForm" onsubmit="return validateDistribution()">
                <input type="hidden" name="action" value="distribute">
                <input type="hidden" name="batch_id" id="distribute_batch_id">
                
                <div style="background: #dbeafe; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem;">
                    <strong>اسم الدفعة:</strong> <span id="modal_batch_name"></span><br>
                    <strong>المبلغ المتبقي:</strong> <span id="modal_remaining" style="color: #f59e0b; font-size: 1.25rem; font-weight: bold;"></span> ر.س
                </div>
                
                <div id="distributionFields">
                    <!-- سيتم إضافة الحقول ديناميكياً -->
                </div>
                
                <button type="button" class="btn btn-secondary" onclick="addDistributionField()" style="margin-bottom: 1rem;">
                    + إضافة قسم آخر
                </button>
                
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-success">توزيع الآن</button>
                    <button type="button" class="btn btn-secondary" onclick="closeDistributeModal()">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const departments = <?php echo json_encode($departments); ?>;
        let distributionCount = 0;
        let remainingAmount = 0;
        
        function toggleBatchForm() {
            const form = document.getElementById('newBatchForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
        
        function validateBatchForm() {
            const amount = document.querySelector('input[name="amount"]').value;
            if (parseFloat(amount) <= 0) {
                alert('المبلغ يجب أن يكون أكبر من صفر');
                return false;
            }
            return true;
        }
        
        function openDistributeModal(batchId, batchName, remaining) {
            document.getElementById('distribute_batch_id').value = batchId;
            document.getElementById('modal_batch_name').textContent = batchName;
            document.getElementById('modal_remaining').textContent = Number(remaining).toFixed(2);
            remainingAmount = parseFloat(remaining);
            
            distributionCount = 0;
            document.getElementById('distributionFields').innerHTML = '';
            addDistributionField();
            
            document.getElementById('distributeModal').classList.add('active');
        }
        
        function closeDistributeModal() {
            document.getElementById('distributeModal').classList.remove('active');
        }
        
        function addDistributionField() {
            const container = document.getElementById('distributionFields');
            const fieldId = distributionCount++;
            
            const fieldHTML = `
                <div class="distribution-field" id="field_${fieldId}" style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 1rem; margin-bottom: 1rem; padding: 1rem; background: #f9fafb; border-radius: 6px;">
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label">القسم</label>
                        <select name="distributions[${fieldId}][department_id]" class="form-select" required>
                            <option value="">اختر القسم</option>
                            ${departments.map(dept => `<option value="${dept.id}">${dept.name_ar}</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label">المبلغ (ر.س)</label>
                        <input type="number" name="distributions[${fieldId}][amount]" class="form-input" step="0.01" min="0.01" required>
                    </div>
                    <div style="display: flex; align-items: flex-end;">
                        <button type="button" class="btn btn-danger" onclick="removeDistributionField(${fieldId})" style="padding: 0.75rem;">
                            🗑️
                        </button>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', fieldHTML);
        }
        
        function removeDistributionField(fieldId) {
            const field = document.getElementById(`field_${fieldId}`);
            if (field) {
                field.remove();
            }
        }
        
        function validateDistribution() {
            const form = document.getElementById('distributeForm');
            const amountInputs = form.querySelectorAll('input[name*="[amount]"]');
            let total = 0;
            
            amountInputs.forEach(input => {
                if (input.value) {
                    total += parseFloat(input.value);
                }
            });
            
            if (total > remainingAmount) {
                alert(`إجمالي المبالغ (${total.toFixed(2)} ر.س) أكبر من المبلغ المتبقي (${remainingAmount.toFixed(2)} ر.س)`);
                return false;
            }
            
            if (total <= 0) {
                alert('يجب إدخال مبلغ واحد على الأقل');
                return false;
            }
            
            return confirm(`هل أنت متأكد من توزيع ${total.toFixed(2)} ر.س؟`);
        }
        
        function toggleMenu() {
            document.getElementById('navMenu').classList.toggle('active');
        }
        
        // إخفاء الرسائل تلقائياً بعد 5 ثواني
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.style.display !== 'none') {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s';
                    setTimeout(() => alert.style.display = 'none', 500);
                }
            });
        }, 5000);
    </script>
</body>
</html>