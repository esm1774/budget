<?php
// تضمين ملف العناوين
require_once 'page_titles.php';
?>
    
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>
                        <i class="fas fa-chart-pie"></i>
                        نظام إدارة الميزانيات
                    </h4>
                    <p>نظام متكامل لإدارة الميزانيات والمصروفات بشكل فعال وآمن</p>
                </div>
                
                <div class="footer-section">
                    <h4>روابط سريعة</h4>
                    <ul>
                        <li><a href="dashboard.php"><i class="fas fa-home"></i> الرئيسية</a></li>
                        <li><a href="report.php"><i class="fas fa-chart-bar"></i> التقارير</a></li>
                        <li><a href="profile.php"><i class="fas fa-user"></i> الملف الشخصي</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>الدعم</h4>
                    <ul>
                        <li><a href="#"><i class="fas fa-question-circle"></i> المساعدة</a></li>
                        <li><a href="#"><i class="fas fa-envelope"></i> اتصل بنا</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> نظام إدارة الميزانيات. جميع الحقوق محفوظة.</p>
            </div>
        </div>
    </footer>


</body>
</html>