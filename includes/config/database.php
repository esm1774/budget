<?php
// class Database {
//     private $host = 'localhost';
//     private $db_name = 'budget';
//     private $username = 'root';
//     private $password = '';
//     private $conn;

//     public function getConnection() {
//         $this->conn = null;
//         try {
//             $this->conn = new PDO(
//                 "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
//                 $this->username,
//                 $this->password,
//                 array(
//                     PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
//                     PDO::ATTR_EMULATE_PREPARES => false,
//                     PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
//                 )
//             );
//         } catch(PDOException $e) {
//             error_log("Connection error: " . $e->getMessage());
//             die("فشل الاتصال بقاعدة البيانات");
//         }
//         return $this->conn;
//     }
// }


 
// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_NAME', 'budget');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// إعدادات التطبيق
define('SITE_NAME', 'نظام إدارة الميزانيات');
define('UPLOAD_PATH', '../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
?> 