-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 03 نوفمبر 2025 الساعة 20:57
-- إصدار الخادم: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `budget`
--

-- --------------------------------------------------------

--
-- بنية الجدول `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `table_name`, `record_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'login', NULL, NULL, 'تسجيل دخول ناجح', '46.153.169.14', '2025-11-03 06:38:12'),
(2, 1, 'create_admin_expense', 'admin_expenses', 1, 'إضافة نفقة إدارية: أخرى', '46.153.169.14', '2025-11-03 06:39:19'),
(3, 1, 'create_department', 'departments', 1, 'إنشاء قسم: ابتدائي ومتوسط بنين', '46.153.169.14', '2025-11-03 06:41:11'),
(4, 2, 'login', NULL, NULL, 'تسجيل دخول ناجح', '46.153.169.14', '2025-11-03 06:41:31'),
(5, 2, 'logout', NULL, NULL, 'تسجيل خروج', '46.153.169.14', '2025-11-03 07:06:04'),
(6, 2, 'login', NULL, NULL, 'تسجيل دخول ناجح', '46.153.169.14', '2025-11-03 07:06:21'),
(7, 2, 'create_expense', 'expenses', 13, 'إضافة نفقة: مستلزمات مكتبية', '46.153.169.14', '2025-11-03 07:10:23'),
(8, 1, 'login', NULL, NULL, 'تسجيل دخول ناجح', '46.153.169.14', '2025-11-03 07:57:11'),
(9, 1, 'logout', NULL, NULL, 'تسجيل خروج', '46.153.169.14', '2025-11-03 08:33:29'),
(10, 1, 'login', NULL, NULL, 'تسجيل دخول ناجح', '46.153.169.14', '2025-11-03 08:37:13'),
(11, 1, 'logout', NULL, NULL, 'تسجيل خروج', '46.153.169.14', '2025-11-03 08:37:47'),
(12, 2, 'logout', NULL, NULL, 'تسجيل خروج', '46.153.169.14', '2025-11-03 08:38:50'),
(13, 2, 'login', NULL, NULL, 'تسجيل دخول ناجح', '46.153.169.14', '2025-11-03 08:39:10'),
(14, 1, 'login', NULL, NULL, 'تسجيل دخول ناجح', '46.153.169.14', '2025-11-03 08:40:22'),
(15, 1, 'login', NULL, NULL, 'تسجيل دخول ناجح', '2a02:cb80:4170:8ffa:8160:96bd:7aa3:3e8a', '2025-11-03 15:01:58'),
(16, 1, 'logout', NULL, NULL, 'تسجيل خروج', '2a02:cb80:4170:8ffa:8160:96bd:7aa3:3e8a', '2025-11-03 15:09:37'),
(17, 1, 'login', NULL, NULL, 'تسجيل دخول ناجح', '2a02:cb80:4170:8ffa:8160:96bd:7aa3:3e8a', '2025-11-03 15:12:08'),
(18, 1, 'logout', NULL, NULL, 'تسجيل خروج', '2a02:cb80:4170:8ffa:8160:96bd:7aa3:3e8a', '2025-11-03 15:12:50'),
(19, 1, 'login', NULL, NULL, 'تسجيل دخول ناجح', '2a02:cb80:4170:8ffa:8160:96bd:7aa3:3e8a', '2025-11-03 15:13:08'),
(20, 1, 'login', NULL, NULL, 'تسجيل دخول ناجح', '::1', '2025-11-03 17:15:02'),
(21, 1, 'create_batch', 'budget_batches', 1, 'إضافة دفعة مالية: الدفعة الأولى', '::1', '2025-11-03 17:15:46'),
(22, 1, 'distribute_batch', 'budget_distributions', 1, 'توزيع دفعة مالية: 1,500.00 ر.س', '::1', '2025-11-03 17:16:02'),
(23, 2, 'login', NULL, NULL, 'تسجيل دخول ناجح', '::1', '2025-11-03 17:19:42');

-- --------------------------------------------------------

--
-- بنية الجدول `admin_expenses`
--

CREATE TABLE `admin_expenses` (
  `id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('cash','bank_transfer','check','credit_card') DEFAULT 'cash',
  `vendor_name` varchar(200) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `admin_expenses`
--

INSERT INTO `admin_expenses` (`id`, `expense_date`, `category`, `description`, `amount`, `payment_method`, `vendor_name`, `notes`, `created_at`) VALUES
(1, '2025-11-03', 'أخرى', 'ضيافة', 120.00, 'cash', 'مطاعم النافورة', '', '2025-11-03 06:39:19');

-- --------------------------------------------------------

--
-- بنية الجدول `admin_invoices`
--

CREATE TABLE `admin_invoices` (
  `id` int(11) NOT NULL,
  `admin_expense_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `admin_invoices`
--

INSERT INTO `admin_invoices` (`id`, `admin_expense_id`, `file_name`, `file_path`, `file_type`, `file_size`, `uploaded_at`) VALUES
(1, 1, 'WhatsApp Image 2025-11-01 at 9.30.47 PM.jpeg', 'uploads/admin_invoices/69084e17d662f_1762151959.jpeg', 'image/jpeg', 183515, '2025-11-03 06:39:19');

-- --------------------------------------------------------

--
-- بنية الجدول `budget_batches`
--

CREATE TABLE `budget_batches` (
  `id` int(11) NOT NULL,
  `batch_number` varchar(50) NOT NULL,
  `batch_name` varchar(200) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `received_date` date NOT NULL,
  `distributed_amount` decimal(15,2) DEFAULT 0.00,
  `remaining_amount` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `budget_batches`
--

INSERT INTO `budget_batches` (`id`, `batch_number`, `batch_name`, `amount`, `received_date`, `distributed_amount`, `remaining_amount`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'BTH001', 'الدفعة الأولى', 15000.00, '2025-09-03', 1500.00, 13500.00, 'active', '', 1, '2025-11-03 17:15:46', '2025-11-03 17:16:02');

-- --------------------------------------------------------

--
-- بنية الجدول `budget_distributions`
--

CREATE TABLE `budget_distributions` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `distribution_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `budget_distributions`
--

INSERT INTO `budget_distributions` (`id`, `batch_id`, `department_id`, `amount`, `distribution_date`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 1, 1500.00, '2025-11-03', NULL, 1, '2025-11-03 17:16:02');

-- --------------------------------------------------------

--
-- بنية الجدول `budget_periods`
--

CREATE TABLE `budget_periods` (
  `id` int(11) NOT NULL,
  `period_name` varchar(200) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_budget` decimal(15,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(200) NOT NULL,
  `name_en` varchar(200) NOT NULL,
  `code` varchar(50) NOT NULL,
  `allocated_budget` decimal(15,2) DEFAULT 0.00,
  `total_received` decimal(15,2) DEFAULT 0.00,
  `spent_amount` decimal(15,2) DEFAULT 0.00,
  `last_distribution_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `manager_name` varchar(255) DEFAULT NULL,
  `custody_officer` varchar(255) DEFAULT NULL,
  `custody_officer_name` varchar(255) DEFAULT NULL COMMENT 'اسم مسؤول العهدة',
  `director_name` varchar(255) DEFAULT NULL COMMENT 'اسم مدير المدرسة'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `departments`
--

INSERT INTO `departments` (`id`, `name_ar`, `name_en`, `code`, `allocated_budget`, `total_received`, `spent_amount`, `last_distribution_date`, `description`, `is_active`, `created_at`, `updated_at`, `manager_name`, `custody_officer`, `custody_officer_name`, `director_name`) VALUES
(1, 'ابتدائي ومتوسط بنين', '', 'SCH001', 3000.00, 1500.00, 20.00, '2025-11-03', '', 1, '2025-11-03 06:41:11', '2025-11-03 17:16:02', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('cash','bank_transfer','check','credit_card') DEFAULT 'cash',
  `vendor_name` varchar(200) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `batch_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `expenses`
--

INSERT INTO `expenses` (`id`, `department_id`, `expense_date`, `category`, `description`, `amount`, `payment_method`, `vendor_name`, `notes`, `created_by`, `created_at`, `updated_at`, `batch_id`) VALUES
(13, 1, '2025-11-03', 'مستلزمات مكتبية', 'أقلام', 20.00, 'cash', 'مكتبة الإشراق', '', 2, '2025-11-03 07:10:23', '2025-11-03 07:10:23', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `expense_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `invoices`
--

INSERT INTO `invoices` (`id`, `expense_id`, `file_name`, `file_path`, `file_type`, `file_size`, `uploaded_at`) VALUES
(8, 13, 'WhatsApp Image 2025-11-01 at 9.32.59 PM (1).jpeg', 'uploads/invoices/6908555f60c9c_1762153823.jpeg', 'image/jpeg', 101306, '2025-11-03 07:10:23');

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `role` enum('admin','department') DEFAULT 'department',
  `department_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL COMMENT 'المسمى الوظيفي'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `role`, `department_id`, `is_active`, `created_at`, `last_login`, `phone`, `position`) VALUES
(1, 'admin', '$2y$10$8K9t1U5nE0fm4dZUian1Vez53YRlCw2cSCjxXaKA0H1P8tnH9wOce', 'مدير النظام', 'admin', NULL, 1, '2025-11-03 04:41:12', '2025-11-03 17:15:02', NULL, NULL),
(2, 'fahad', '$2y$10$SXC.S8HUaqcttTGeF5cYjOp.VqSpDmvIxQjkro4ssuSeieyv/Fcs2', 'فهد ابا الحسن', 'department', 1, 1, '2025-11-03 06:41:11', '2025-11-03 17:19:42', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `admin_expenses`
--
ALTER TABLE `admin_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`expense_date`);

--
-- Indexes for table `admin_invoices`
--
ALTER TABLE `admin_invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_expense` (`admin_expense_id`);

--
-- Indexes for table `budget_batches`
--
ALTER TABLE `budget_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batch_number` (`batch_number`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_batch_number` (`batch_number`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `budget_distributions`
--
ALTER TABLE `budget_distributions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_batch` (`batch_id`),
  ADD KEY `idx_department` (`department_id`);

--
-- Indexes for table `budget_periods`
--
ALTER TABLE `budget_periods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_department` (`department_id`),
  ADD KEY `idx_date` (`expense_date`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expense` (`expense_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `admin_expenses`
--
ALTER TABLE `admin_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_invoices`
--
ALTER TABLE `admin_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `budget_batches`
--
ALTER TABLE `budget_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `budget_distributions`
--
ALTER TABLE `budget_distributions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `budget_periods`
--
ALTER TABLE `budget_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- قيود الجداول المُلقاة.
--

--
-- قيود الجداول `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- قيود الجداول `admin_invoices`
--
ALTER TABLE `admin_invoices`
  ADD CONSTRAINT `admin_invoices_ibfk_1` FOREIGN KEY (`admin_expense_id`) REFERENCES `admin_expenses` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `budget_batches`
--
ALTER TABLE `budget_batches`
  ADD CONSTRAINT `budget_batches_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- قيود الجداول `budget_distributions`
--
ALTER TABLE `budget_distributions`
  ADD CONSTRAINT `budget_distributions_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `budget_batches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `budget_distributions_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `budget_distributions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- قيود الجداول `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `expenses_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `budget_batches` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
