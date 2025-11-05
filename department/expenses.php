
    <?php 
    require_once '../includes/init.php';
    // التحقق من المصادقة
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit();
    }

    // تضمين الدوال المطلوبة
    // require_once '../includes/functions/expense_functions.php';
    // require_once '../includes/utils/validation.php';
    // require_once '../includes/utils/upload.php';

    
     ?>
    <main class="main-content">
        <div class="container">
            <h1>إدارة المصروفات - <?= htmlspecialchars($department['name_ar']) ?></h1>

            <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="card">
                <div class="card-header flex flex-between">
                    <span><?= $edit_expense ? 'تعديل النفقة' : 'إضافة نفقة جديدة' ?></span>
                    <button class="btn btn-primary" onclick="toggleForm()">
                        <i class="fas fa-<?= $edit_expense ? 'times' : 'plus' ?>"></i>
                        <?= $edit_expense ? ' إلغاء التعديل' : ' نفقة جديدة' ?>
                    </button>
                </div>

                <div id="newExpenseForm" style="display:<?= $edit_expense ? 'block' : 'none' ?>;padding:1rem;border-top:1px solid #ddd;">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="<?= $edit_expense ? 'update' : 'create' ?>">
                        <?php if ($edit_expense): ?>
                            <input type="hidden" name="expense_id" value="<?= $edit_expense['id'] ?>">
                        <?php endif; ?>

                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-alt"></i> تاريخ النفقة *</label>
                                <input type="date" name="expense_date" required 
                                       value="<?= $edit_expense ? htmlspecialchars($edit_expense['expense_date']) : date('Y-m-d') ?>" 
                                       class="form-input">
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-file-invoice-dollar"></i> العهدة *</label>
                                <select name="batch_id" class="form-select" required>
                                    <option value="">اختر العهدة</option>
                                    <?php foreach ($batches as $b): ?>
                                        <option value="<?= $b['id'] ?>" 
                                            <?= ($edit_expense && $edit_expense['batch_id'] == $b['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($b['batch_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-tags"></i> الفئة *</label>
                                <select name="category" class="form-select" required>
                                    <option value="">اختر الفئة</option>
                                    <option value="مستلزمات مكتبية" <?= ($edit_expense && $edit_expense['category'] == 'مستلزمات مكتبية') ? 'selected' : '' ?>>مستلزمات مكتبية</option>
                                    <option value="صيانة" <?= ($edit_expense && $edit_expense['category'] == 'صيانة') ? 'selected' : '' ?>>صيانة</option>
                                    <option value="مرافق" <?= ($edit_expense && $edit_expense['category'] == 'مرافق') ? 'selected' : '' ?>>مرافق</option>
                                    <option value="ضيافة" <?= ($edit_expense && $edit_expense['category'] == 'ضيافة') ? 'selected' : '' ?>>ضيافة</option>
                                    <option value="سفر وتنقل" <?= ($edit_expense && $edit_expense['category'] == 'سفر وتنقل') ? 'selected' : '' ?>>سفر وتنقل</option>
                                    <option value="تدريب" <?= ($edit_expense && $edit_expense['category'] == 'تدريب') ? 'selected' : '' ?>>تدريب</option>
                                    <option value="أخرى" <?= ($edit_expense && $edit_expense['category'] == 'أخرى') ? 'selected' : '' ?>>أخرى</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-money-bill-wave"></i> المبلغ *</label>
                                <input type="number" step="0.01" name="amount" required 
                                       value="<?= $edit_expense ? htmlspecialchars($edit_expense['amount']) : '' ?>" 
                                       class="form-input">
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-file-alt"></i> البيان *</label>
                            <textarea name="description" required class="form-textarea"><?= $edit_expense ? htmlspecialchars($edit_expense['description']) : '' ?></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-credit-card"></i> طريقة الدفع</label>
                                <select name="payment_method" class="form-select">
                                    <option value="cash" <?= ($edit_expense && $edit_expense['payment_method'] == 'cash') ? 'selected' : '' ?>>نقداً</option>
                                    <option value="bank_transfer" <?= ($edit_expense && $edit_expense['payment_method'] == 'bank_transfer') ? 'selected' : '' ?>>تحويل بنكي</option>
                                    <option value="check" <?= ($edit_expense && $edit_expense['payment_method'] == 'check') ? 'selected' : '' ?>>شيك</option>
                                    <option value="credit_card" <?= ($edit_expense && $edit_expense['payment_method'] == 'credit_card') ? 'selected' : '' ?>>بطاقة ائتمان</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-store"></i> اسم المورد</label>
                                <input type="text" name="vendor_name" 
                                       value="<?= $edit_expense ? htmlspecialchars($edit_expense['vendor_name']) : '' ?>" 
                                       class="form-input">
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-sticky-note"></i> ملاحظات</label>
                            <textarea name="notes" class="form-textarea"><?= $edit_expense ? htmlspecialchars($edit_expense['notes']) : '' ?></textarea>
                        </div>

                        <?php if ($edit_expense && $edit_expense['invoice_ids']): ?>
                        <div class="existing-invoices">
                            <label><i class="fas fa-paperclip"></i> الفواتير الحالية:</label>
                            <?php 
                            $invoice_ids = explode(',', $edit_expense['invoice_ids']);
                            $invoice_names = explode(',', $edit_expense['invoice_names']);
                            for ($i = 0; $i < count($invoice_ids); $i++): 
                            ?>
                                <div class="invoice-item">
                                    <span><i class="fas fa-file-pdf"></i> <?= htmlspecialchars($invoice_names[$i]) ?></span>
                                    <a href="?action=delete_invoice&invoice_id=<?= $invoice_ids[$i] ?>" 
                                       class="btn-icon btn-delete" 
                                       onclick="return confirm('هل أنت متأكد من حذف هذه الفاتورة؟')"
                                       title="حذف الفاتورة">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label><i class="fas fa-upload"></i> <?= $edit_expense ? 'إضافة فواتير جديدة' : 'رفع الفواتير' ?></label>
                            <input type="file" name="invoices[]" multiple accept=".pdf,.jpg,.jpeg,.png">
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-<?= $edit_expense ? 'edit' : 'save' ?>"></i>
                            <?= $edit_expense ? ' تحديث النفقة' : ' حفظ النفقة' ?>
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="toggleForm()">
                            <i class="fas fa-times"></i> إلغاء
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i> قائمة المصروفات (<?= count($expenses) ?>)
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-calendar-alt"></i> التاريخ</th>
                                <th><i class="fas fa-file-invoice-dollar"></i> العهدة</th>
                                <th><i class="fas fa-tags"></i> الفئة</th>
                                <th><i class="fas fa-file-alt"></i> البيان</th>
                                <th><i class="fas fa-money-bill-wave"></i> المبلغ</th>
                                <th><i class="fas fa-paperclip"></i> الفواتير</th>
                                <th><i class="fas fa-cogs"></i> الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($expenses) > 0): ?>
                                <?php foreach ($expenses as $expense): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($expense['expense_date']) ?></td>
                                        <td><?= htmlspecialchars($expense['batch_name'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($expense['category']) ?></td>
                                        <td><?= htmlspecialchars(mb_substr($expense['description'], 0, 40)) ?>...</td>
                                        <td style="color:#ef4444;font-weight:bold;"><?= number_format($expense['amount'],2) ?> ر.س</td>
                                        <td>
                                            <?php if ($expense['invoice_count'] > 0): ?>
                                                <span class="btn-icon btn-invoice" title="<?= $expense['invoice_count'] ?> فاتورة">
                                                    <i class="fas fa-paperclip"></i> <?= $expense['invoice_count'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color:#6b7280;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?action=edit&id=<?= $expense['id'] ?>" 
                                                   class="btn-icon btn-edit" 
                                                   title="تعديل النفقة">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="expense_details.php?id=<?= $expense['id'] ?>" 
                                                   class="btn-icon btn-view" 
                                                   title="عرض التفاصيل">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?action=delete&id=<?= $expense['id'] ?>" 
                                                   class="btn-icon btn-delete" 
                                                   onclick="return confirm('هل أنت متأكد من حذف هذه النفقة؟')"
                                                   title="حذف النفقة">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <i class="fas fa-inbox" style="font-size: 48px; color: #6b7280; margin: 20px 0;"></i>
                                        <br>
                                        لا توجد مصروفات
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

<script>
function toggleForm() {
    const form = document.getElementById('newExpenseForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    
    // إذا كان النموذج مخفيًا، إعادة التوجيه لإزالة معاملات التعديل من الرابط
    if (form.style.display === 'none' && window.location.search.includes('action=edit')) {
        window.location.href = 'expenses.php';
    }
}

// إظهار النموذج تلقائيًا إذا كان في وضع التعديل
<?php if ($edit_expense): ?>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('newExpenseForm').style.display = 'block';
    });
<?php endif; ?>
</script>
<?php include '../includes/footer.php'; ?>