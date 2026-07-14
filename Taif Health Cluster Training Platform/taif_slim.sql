-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 20 أغسطس 2025 الساعة 01:18
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
-- Database: `taif_slim`
--

-- --------------------------------------------------------

--
-- بنية الجدول `applications`
--

CREATE TABLE `applications` (
  `id` int(10) UNSIGNED NOT NULL,
  `applicant_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `training_duration` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `pref1_training_dept_id` int(10) UNSIGNED NOT NULL,
  `pref2_training_dept_id` int(10) UNSIGNED NOT NULL,
  `routed_pref` tinyint(3) UNSIGNED DEFAULT NULL,
  `stage` enum('first','second','accepted','canceled') NOT NULL DEFAULT 'first',
  `status` enum('pending','in_dept','accepted','canceled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rejected_pref1` tinyint(1) DEFAULT 0 COMMENT 'تم رفض الطلب من الرغبة الأولى',
  `rejected_pref2` tinyint(1) DEFAULT 0 COMMENT 'تم رفض الطلب من الرغبة الثانية'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `applications`
--

INSERT INTO `applications` (`id`, `applicant_id`, `full_name`, `email`, `phone`, `training_duration`, `start_date`, `pref1_training_dept_id`, `pref2_training_dept_id`, `routed_pref`, `stage`, `status`, `notes`, `submitted_at`, `updated_at`, `rejected_pref1`, `rejected_pref2`) VALUES
(1, 28, 'أحمد عبدالله الغامدي', 'ahmed.ghamdi@student.sa', '0551001001', '8 أسابيع', '2025-09-01', 1, 5, 1, 'accepted', 'accepted', 'تم قبوله في قسم علوم الحاسب', '2025-08-10 09:30:00', '2025-08-19 22:12:42', 0, 0),
(2, 29, 'محمد سعد الشهري', 'mohammed.shahri@student.sa', '0551001002', '6 أسابيع', '2025-09-15', 9, 13, 1, 'accepted', 'accepted', 'تم قبوله في قسم الطب', '2025-08-11 10:15:00', '2025-08-19 22:12:42', 0, 0),
(3, 30, 'عبدالرحمن فهد الحربي', 'abdulrahman.harbi@student.sa', '0551001003', '10 أسابيع', '2025-08-25', 3, 6, 2, 'accepted', 'accepted', 'تم قبوله في قسم الأمن السيبراني بعد رفضه من هندسة البرمجيات', '2025-08-12 11:00:00', '2025-08-19 22:12:42', 1, 0),
(4, 31, 'سعود محمد العتيبي', 'saud.otaibi@student.sa', '0551001004', '12 أسبوع', '2025-09-20', 16, 18, 1, 'accepted', 'accepted', 'تم قبوله في قسم إدارة الأعمال', '2025-08-13 14:20:00', '2025-08-19 22:12:42', 0, 0),
(5, 32, 'خالد عبدالعزيز القحطاني', 'khalid.qahtani@student.sa', '0551001005', '8 أسابيع', '2025-09-10', 2, 4, 2, 'accepted', 'accepted', 'تم قبوله في قسم نظم المعلومات', '2025-08-14 15:45:00', '2025-08-19 22:12:42', 1, 0),
(6, 33, 'ناصر عبدالله الزهراني', 'nasser.zahrani@student.sa', '0551001006', '6 أسابيع', '2025-09-05', 12, 14, 1, 'first', 'in_dept', 'قيد المراجعة في قسم الصيدلة', '2025-08-15 08:30:00', '2025-08-19 22:12:42', 0, 0),
(7, 34, 'فهد ناصر المطيري', 'fahad.mutairi@student.sa', '0551001007', '8 أسابيع', '2025-09-12', 7, 8, 2, 'second', 'in_dept', 'تم توجيهه لقسم الهندسة الكهربائية بعد رفضه من الهندسة الصناعية', '2025-08-16 09:45:00', '2025-08-19 22:12:42', 1, 0),
(8, 35, 'تركي سلمان السبيعي', 'turki.subaie@student.sa', '0551001008', '10 أسابيع', '2025-09-18', 11, 15, 1, 'first', 'in_dept', 'قيد المراجعة في قسم العلوم الطبية التطبيقية', '2025-08-17 12:10:00', '2025-08-19 22:12:42', 0, 0),
(9, 36, 'بدر علي العوفي', 'badr.awfi@student.sa', '0551001009', '4 أسابيع', '2025-09-22', 19, 20, 1, 'first', 'in_dept', 'قيد المراجعة في قسم المالية', '2025-08-18 13:25:00', '2025-08-19 22:12:42', 0, 0),
(10, 37, 'سلمان أحمد الدوسري', 'salman.dosari@student.sa', '0551001010', '6 أسابيع', '2025-09-08', 23, 25, 2, 'second', 'in_dept', 'تم توجيهه لقسم الإعلام', '2025-08-19 16:40:00', '2025-08-19 22:12:42', 1, 0),
(11, 38, 'عبدالله خالد العنزي', 'abdullah.anazi@student.sa', '0551001011', '8 أسابيع', '2025-09-14', 10, 17, 1, 'first', 'in_dept', 'قيد المراجعة في قسم التمريض', '2025-08-19 17:55:00', '2025-08-19 22:12:42', 0, 0),
(12, 39, 'مشعل عبدالرحمن الحارثي', 'mishaal.harithi@student.sa', '0551001012', '8 أسابيع', '2025-09-25', 1, 3, 1, 'accepted', 'accepted', 'في انتظار توجيه السكرتير', '2025-08-20 08:15:00', '2025-08-19 23:03:29', 0, 0),
(13, 40, 'راكان محمد البقمي', 'rakan.baqami@student.sa', '0551001013', '10 أسابيع', '2025-09-30', 5, 6, 2, 'canceled', 'canceled', 'في انتظار توجيه السكرتير', '2025-08-20 09:30:00', '2025-08-19 23:08:12', 1, 1),
(14, 41, 'عمر سعد الجهني', 'omar.johani@student.sa', '0551001014', '6 أسابيع', '2025-09-12', 22, 24, NULL, 'first', 'pending', 'في انتظار توجيه السكرتير', '2025-08-20 10:45:00', '2025-08-19 22:12:42', 0, 0),
(15, 42, 'عبدالإله فهد الثقفي', 'abdulilah.thaqafi@student.sa', '0551001015', '12 أسبوع', '2025-10-05', 21, 22, NULL, 'first', 'pending', 'في انتظار إعادة التوجيه بعد رفضه من قسم القانون', '2025-08-20 11:20:00', '2025-08-19 22:12:42', 1, 0),
(16, 43, 'يزيد عبدالعزيز الرشيد', 'yazeed.rasheed@student.sa', '0551001016', '8 أسابيع', '2025-09-15', 4, 5, 2, 'canceled', 'canceled', 'تم إلغاؤه بعد رفضه من كلا القسمين', '2025-08-18 14:00:00', '2025-08-19 22:12:42', 1, 1),
(17, 44, 'صالح ناصر الخالدي', 'saleh.khalidi@student.sa', '0551001017', '6 أسابيع', '2025-09-20', 13, 14, 1, 'canceled', 'canceled', 'اعتذر الطالب عن استكمال التدريب', '2025-08-19 15:30:00', '2025-08-19 22:12:42', 0, 0),
(18, 45, 'إبراهيم سلمان الشمري', 'ibrahim.shamari@student.sa', '0551001018', '10 أسابيع', '2025-09-28', 16, 17, 2, 'canceled', 'canceled', 'تم إلغاؤه لعدم استيفاء الشروط', '2025-08-19 16:15:00', '2025-08-19 22:12:42', 1, 1),
(19, 46, 'حمد عبدالله العسيري', 'hamad.aseeri@student.sa', '0551001019', '8 أسابيع', '2025-10-01', 8, 7, NULL, 'first', 'pending', 'طلب جديد مقدم اليوم', '2025-08-20 18:00:00', '2025-08-19 22:12:42', 0, 0),
(20, 47, 'طلال محمد الفيفي', 'talal.faifi@student.sa', '0551001020', '12 أسبوع', '2025-10-10', 9, 12, NULL, 'first', 'pending', 'طلب جديد مقدم اليوم', '2025-08-20 18:30:00', '2025-08-19 22:12:42', 0, 0),
(21, 49, 'وليد النفيعي', 'waleed@student.com', '+966555000001', '12 أسبوع', '2025-12-12', 23, 19, NULL, 'first', 'pending', NULL, '2025-08-20 02:15:28', '2025-08-19 23:15:28', 0, 0);

-- --------------------------------------------------------

--
-- بنية الجدول `org_departments`
--

CREATE TABLE `org_departments` (
  `id` int(10) UNSIGNED NOT NULL,
  `name_ar` varchar(150) NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `org_departments`
--

INSERT INTO `org_departments` (`id`, `name_ar`, `parent_id`, `created_at`) VALUES
(1, 'إدارة التدريب', NULL, '2025-08-18 15:37:01'),
(2, 'الموارد البشرية', NULL, '2025-08-18 15:37:01'),
(3, 'الخدمات الطبية', NULL, '2025-08-18 15:37:01'),
(4, 'التشغيل', NULL, '2025-08-18 15:37:01'),
(5, 'الشؤون المالية', NULL, '2025-08-18 15:37:01'),
(6, 'الشؤون الرقمية', NULL, '2025-08-18 15:37:01'),
(7, 'إدارة الدعم الفني', NULL, '2025-08-18 15:37:01'),
(8, 'مركز بيانات الموارد البشرية', NULL, '2025-08-18 15:37:01'),
(9, 'مراقبة انتظام الدوام', NULL, '2025-08-18 15:37:01'),
(10, 'التطوع الصحي', NULL, '2025-08-18 15:37:01'),
(11, 'خدمات الموارد البشرية', NULL, '2025-08-18 15:37:01');

-- --------------------------------------------------------

--
-- بنية الجدول `roles`
--

CREATE TABLE `roles` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` enum('A','B','C','D') NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `roles`
--

INSERT INTO `roles` (`id`, `code`, `name_ar`, `created_at`) VALUES
(1, 'A', 'إدارة التدريب', '2025-08-18 15:31:24'),
(2, 'B', 'السكرتارية', '2025-08-18 15:31:24'),
(3, 'C', 'موظفو الأقسام', '2025-08-18 15:31:24'),
(4, 'D', 'طالب متدرب', '2025-08-18 15:31:24'),
(5, '', 'ادمن', '2025-08-19 22:43:52');

-- --------------------------------------------------------

--
-- بنية الجدول `training_departments`
--

CREATE TABLE `training_departments` (
  `id` int(10) UNSIGNED NOT NULL,
  `name_ar` varchar(150) NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `training_departments`
--

INSERT INTO `training_departments` (`id`, `name_ar`, `parent_id`, `created_at`) VALUES
(1, 'علوم الحاسب', NULL, '2025-08-14 11:15:49'),
(2, 'هندسة الحاسب', NULL, '2025-08-14 11:15:49'),
(3, 'هندسة البرمجيات', NULL, '2025-08-14 11:15:49'),
(4, 'نظم المعلومات', NULL, '2025-08-14 11:15:49'),
(5, 'الذكاء الاصطناعي', NULL, '2025-08-14 11:15:49'),
(6, 'الأمن السيبراني', NULL, '2025-08-14 11:15:49'),
(7, 'الهندسة الصناعية', NULL, '2025-08-14 11:15:49'),
(8, 'الهندسة الكهربائية', NULL, '2025-08-14 11:15:49'),
(9, 'طب', NULL, '2025-08-14 11:15:49'),
(10, 'تمريض', NULL, '2025-08-14 11:15:49'),
(11, 'علوم طبية تطبيقية', NULL, '2025-08-14 11:15:49'),
(12, 'صيدلة', NULL, '2025-08-14 11:15:49'),
(13, 'علاج طبيعي', NULL, '2025-08-14 11:15:49'),
(14, 'مختبرات', NULL, '2025-08-14 11:15:49'),
(15, 'صحة عامة', NULL, '2025-08-14 11:15:49'),
(16, 'إدارة أعمال', NULL, '2025-08-14 11:15:49'),
(17, 'موارد بشرية', NULL, '2025-08-14 11:15:49'),
(18, 'محاسبة', NULL, '2025-08-14 11:15:49'),
(19, 'مالية', NULL, '2025-08-14 11:15:49'),
(20, 'اقتصاد', NULL, '2025-08-14 11:15:49'),
(21, 'قانون / أنظمة', NULL, '2025-08-14 11:15:49'),
(22, 'إدارة عامة', NULL, '2025-08-14 11:15:49'),
(23, 'لغة إنجليزية', NULL, '2025-08-14 11:15:49'),
(24, 'خدمة اجتماعية', NULL, '2025-08-14 11:15:49'),
(25, 'إعلام', NULL, '2025-08-14 11:15:49');

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` tinyint(3) UNSIGNED NOT NULL,
  `training_department_id` int(10) UNSIGNED DEFAULT NULL,
  `country_code` char(2) NOT NULL DEFAULT 'SA',
  `date_of_birth` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password_hash`, `role_id`, `training_department_id`, `country_code`, `date_of_birth`, `created_at`) VALUES
(1, 'د. عبدالعزيز السلمي', 'manager@taifhealth.sa', '0501234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL, 'SA', '1975-03-15', '2025-08-19 22:12:42'),
(2, 'أ. فاطمة الغامدي', 'secretary@taifhealth.sa', '0501234568', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, NULL, 'SA', '1985-07-22', '2025-08-19 22:12:42'),
(3, 'د. محمد الأحمدي', 'cs.supervisor@taifhealth.sa', '0501234569', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 1, 'SA', '1980-01-10', '2025-08-19 22:12:42'),
(4, 'م. سعد الحربي', 'ce.supervisor@taifhealth.sa', '0501234570', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 2, 'SA', '1982-05-18', '2025-08-19 22:12:42'),
(5, 'د. خالد العتيبي', 'se.supervisor@taifhealth.sa', '0501234571', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 3, 'SA', '1979-09-25', '2025-08-19 22:12:42'),
(6, 'أ. ناصر الشهري', 'is.supervisor@taifhealth.sa', '0501234572', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 4, 'SA', '1981-12-03', '2025-08-19 22:12:42'),
(7, 'د. عبدالله القحطاني', 'ai.supervisor@taifhealth.sa', '0501234573', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 5, 'SA', '1983-04-14', '2025-08-19 22:12:42'),
(8, 'م. فهد الزهراني', 'cyber.supervisor@taifhealth.sa', '0501234574', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 6, 'SA', '1984-08-07', '2025-08-19 22:12:42'),
(9, 'د. تركي المطيري', 'ie.supervisor@taifhealth.sa', '0501234575', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 7, 'SA', '1978-11-20', '2025-08-19 22:12:42'),
(10, 'م. بدر السبيعي', 'ee.supervisor@taifhealth.sa', '0501234576', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 8, 'SA', '1980-06-30', '2025-08-19 22:12:42'),
(11, 'د. عبدالرحمن العوفي', 'med.supervisor@taifhealth.sa', '0501234577', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 9, 'SA', '1975-02-12', '2025-08-19 22:12:42'),
(12, 'أ. مريم الدوسري', 'nursing.supervisor@taifhealth.sa', '0501234578', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 10, 'SA', '1986-10-05', '2025-08-19 22:12:42'),
(13, 'د. سلمان العنزي', 'medtech.supervisor@taifhealth.sa', '0501234579', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 11, 'SA', '1981-03-28', '2025-08-19 22:12:42'),
(14, 'د. نورا الحارثي', 'pharmacy.supervisor@taifhealth.sa', '0501234580', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 12, 'SA', '1984-07-16', '2025-08-19 22:12:42'),
(15, 'أ. علي البقمي', 'physio.supervisor@taifhealth.sa', '0501234581', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 13, 'SA', '1982-12-09', '2025-08-19 22:12:42'),
(16, 'د. هند الجهني', 'lab.supervisor@taifhealth.sa', '0501234582', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 14, 'SA', '1983-05-21', '2025-08-19 22:12:42'),
(17, 'د. ماجد الثقفي', 'health.supervisor@taifhealth.sa', '0501234583', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 15, 'SA', '1979-09-14', '2025-08-19 22:12:42'),
(18, 'أ. عبدالإله الرشيد', 'business.supervisor@taifhealth.sa', '0501234584', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 16, 'SA', '1985-01-07', '2025-08-19 22:12:42'),
(19, 'أ. سارة الخالدي', 'hr.supervisor@taifhealth.sa', '0501234585', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 17, 'SA', '1987-04-19', '2025-08-19 22:12:42'),
(20, 'أ. إبراهيم الشمري', 'accounting.supervisor@taifhealth.sa', '0501234586', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 18, 'SA', '1983-08-02', '2025-08-19 22:12:42'),
(21, 'د. رانيا العسيري', 'finance.supervisor@taifhealth.sa', '0501234587', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 19, 'SA', '1981-11-25', '2025-08-19 22:12:42'),
(22, 'د. وليد الفيفي', 'economics.supervisor@taifhealth.sa', '0501234588', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 20, 'SA', '1980-06-13', '2025-08-19 22:12:42'),
(23, 'أ. طلال المالكي', 'law.supervisor@taifhealth.sa', '0501234589', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 21, 'SA', '1984-02-28', '2025-08-19 22:12:42'),
(24, 'د. منيرة الصاعدي', 'admin.supervisor@taifhealth.sa', '0501234590', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 22, 'SA', '1982-10-17', '2025-08-19 22:12:42'),
(25, 'أ. عمر الغانمي', 'english.supervisor@taifhealth.sa', '0501234591', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 23, 'SA', '1986-03-11', '2025-08-19 22:12:42'),
(26, 'أ. أمل الزايدي', 'social.supervisor@taifhealth.sa', '0501234592', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 24, 'SA', '1985-07-04', '2025-08-19 22:12:42'),
(27, 'أ. ياسر العمري', 'media.supervisor@taifhealth.sa', '0501234593', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 25, 'SA', '1983-12-22', '2025-08-19 22:12:42'),
(28, 'أحمد عبدالله الغامدي', 'ahmed.ghamdi@student.sa', '0551001001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '2000-01-15', '2025-08-19 22:12:42'),
(29, 'محمد سعد الشهري', 'mohammed.shahri@student.sa', '0551001002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '1999-05-20', '2025-08-19 22:12:42'),
(30, 'عبدالرحمن فهد الحربي', 'abdulrahman.harbi@student.sa', '0551001003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '2001-03-10', '2025-08-19 22:12:42'),
(31, 'سعود محمد العتيبي', 'saud.otaibi@student.sa', '0551001004', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '2000-08-25', '2025-08-19 22:12:42'),
(32, 'خالد عبدالعزيز القحطاني', 'khalid.qahtani@student.sa', '0551001005', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '1999-12-03', '2025-08-19 22:12:42'),
(33, 'ناصر عبدالله الزهراني', 'nasser.zahrani@student.sa', '0551001006', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '2001-06-18', '2025-08-19 22:12:42'),
(34, 'فهد ناصر المطيري', 'fahad.mutairi@student.sa', '0551001007', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '2000-04-07', '2025-08-19 22:12:42'),
(35, 'تركي سلمان السبيعي', 'turki.subaie@student.sa', '0551001008', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '1999-09-14', '2025-08-19 22:12:42'),
(36, 'بدر علي العوفي', 'badr.awfi@student.sa', '0551001009', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '2001-02-28', '2025-08-19 22:12:42'),
(37, 'سلمان أحمد الدوسري', 'salman.dosari@student.sa', '0551001010', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '2000-11-12', '2025-08-19 22:12:42'),
(38, 'عبدالله خالد العنزي', 'abdullah.anazi@student.sa', '0551001011', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '1999-07-05', '2025-08-19 22:12:42'),
(39, 'مشعل عبدالرحمن الحارثي', 'mishaal.harithi@student.sa', '0551001012', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '2001-01-22', '2025-08-19 22:12:42'),
(40, 'راكان محمد البقمي', 'rakan.baqami@student.sa', '0551001013', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '2000-10-08', '2025-08-19 22:12:42'),
(41, 'عمر سعد الجهني', 'omar.johani@student.sa', '0551001014', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '1999-04-16', '2025-08-19 22:12:42'),
(42, 'عبدالإله فهد الثقفي', 'abdulilah.thaqafi@student.sa', '0551001015', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '2001-08-30', '2025-08-19 22:12:42'),
(43, 'يزيد عبدالعزيز الرشيد', 'yazeed.rasheed@student.sa', '0551001016', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '2000-03-19', '2025-08-19 22:12:42'),
(44, 'صالح ناصر الخالدي', 'saleh.khalidi@student.sa', '0551001017', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '1999-11-27', '2025-08-19 22:12:42'),
(45, 'إبراهيم سلمان الشمري', 'ibrahim.shamari@student.sa', '0551001018', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '2001-05-14', '2025-08-19 22:12:42'),
(46, 'حمد عبدالله العسيري', 'hamad.aseeri@student.sa', '0551001019', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '2000-09-02', '2025-08-19 22:12:42'),
(47, 'طلال محمد الفيفي', 'talal.faifi@student.sa', '0551001020', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, NULL, 'SA', '1999-06-11', '2025-08-19 22:12:42'),
(48, 'admin', 'admin@admin.com', '0551111113', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, NULL, 'SA', '2001-08-01', '2025-08-19 22:46:28'),
(49, 'وليد النفيعي', 'waleed@student.com', '+966555000001', '$2y$10$y/80XIYSz1qmf4d5rYKwIO9XS15r08vFx3A7Zzctg6fq70JmWEPsu', 4, NULL, 'SA', '2002-12-12', '2025-08-19 23:15:02');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_applications_base`
-- (See below for the actual view)
--
CREATE TABLE `v_applications_base` (
`id` int(10) unsigned
,`applicant_id` int(10) unsigned
,`full_name` varchar(150)
,`email` varchar(150)
,`phone` varchar(20)
,`training_duration` varchar(50)
,`start_date` date
,`pref1_training_dept_id` int(10) unsigned
,`pref2_training_dept_id` int(10) unsigned
,`routed_pref` tinyint(3) unsigned
,`stage` enum('first','second','accepted','canceled')
,`status` enum('pending','in_dept','accepted','canceled')
,`notes` text
,`submitted_at` datetime
,`updated_at` timestamp
,`rejected_pref1` tinyint(1)
,`rejected_pref2` tinyint(1)
,`applicant_full_name` varchar(150)
,`applicant_email` varchar(150)
,`applicant_phone` varchar(20)
,`pref1_dept_name` varchar(150)
,`pref2_dept_name` varchar(150)
,`applicant_role_name` varchar(100)
,`target_dept_id` decimal(10,0)
,`target_dept_name` varchar(150)
,`rejection_status` varchar(159)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_department_history`
-- (See below for the actual view)
--
CREATE TABLE `v_department_history` (
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_department_inbox`
-- (See below for the actual view)
--
CREATE TABLE `v_department_inbox` (
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_manager_overview`
-- (See below for the actual view)
--
CREATE TABLE `v_manager_overview` (
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_secretary_queue`
-- (See below for the actual view)
--
CREATE TABLE `v_secretary_queue` (
`id` int(10) unsigned
,`applicant_id` int(10) unsigned
,`full_name` varchar(150)
,`email` varchar(150)
,`phone` varchar(20)
,`training_duration` varchar(50)
,`start_date` date
,`pref1_training_dept_id` int(10) unsigned
,`pref2_training_dept_id` int(10) unsigned
,`rejected_pref1` tinyint(1)
,`rejected_pref2` tinyint(1)
,`notes` text
,`submitted_at` datetime
,`applicant_full_name` varchar(150)
,`pref1_dept_name` varchar(150)
,`pref2_dept_name` varchar(150)
,`routing_options` varchar(14)
,`available_options_text` varchar(184)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_users_staff`
-- (See below for the actual view)
--
CREATE TABLE `v_users_staff` (
`id` int(10) unsigned
,`full_name` varchar(150)
,`email` varchar(150)
,`phone` varchar(20)
,`role_id` tinyint(3) unsigned
,`role_code` enum('A','B','C','D')
,`role_name` varchar(100)
,`training_department_id` int(10) unsigned
,`training_department_name` varchar(150)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_users_students`
-- (See below for the actual view)
--
CREATE TABLE `v_users_students` (
`id` int(10) unsigned
,`full_name` varchar(150)
,`email` varchar(150)
,`phone` varchar(20)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Structure for view `v_applications_base`
--
DROP TABLE IF EXISTS `v_applications_base`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_applications_base`  AS SELECT `a`.`id` AS `id`, `a`.`applicant_id` AS `applicant_id`, `a`.`full_name` AS `full_name`, `a`.`email` AS `email`, `a`.`phone` AS `phone`, `a`.`training_duration` AS `training_duration`, `a`.`start_date` AS `start_date`, `a`.`pref1_training_dept_id` AS `pref1_training_dept_id`, `a`.`pref2_training_dept_id` AS `pref2_training_dept_id`, `a`.`routed_pref` AS `routed_pref`, `a`.`stage` AS `stage`, `a`.`status` AS `status`, `a`.`notes` AS `notes`, `a`.`submitted_at` AS `submitted_at`, `a`.`updated_at` AS `updated_at`, `a`.`rejected_pref1` AS `rejected_pref1`, `a`.`rejected_pref2` AS `rejected_pref2`, `u`.`full_name` AS `applicant_full_name`, `u`.`email` AS `applicant_email`, `u`.`phone` AS `applicant_phone`, `t1`.`name_ar` AS `pref1_dept_name`, `t2`.`name_ar` AS `pref2_dept_name`, `r`.`name_ar` AS `applicant_role_name`, CASE WHEN `a`.`routed_pref` = 1 THEN `a`.`pref1_training_dept_id` WHEN `a`.`routed_pref` = 2 THEN `a`.`pref2_training_dept_id` ELSE NULL END AS `target_dept_id`, CASE WHEN `a`.`routed_pref` = 1 THEN `t1`.`name_ar` WHEN `a`.`routed_pref` = 2 THEN `t2`.`name_ar` ELSE NULL END AS `target_dept_name`, CASE WHEN `a`.`rejected_pref1` <> 0 AND `a`.`rejected_pref2` <> 0 THEN 'مرفوض من كلا القسمين' WHEN `a`.`rejected_pref1` THEN concat('مرفوض من ',`t1`.`name_ar`) WHEN `a`.`rejected_pref2` THEN concat('مرفوض من ',`t2`.`name_ar`) ELSE 'لم يتم الرفض' END AS `rejection_status` FROM ((((`applications` `a` join `users` `u` on(`u`.`id` = `a`.`applicant_id`)) join `roles` `r` on(`r`.`id` = `u`.`role_id`)) left join `training_departments` `t1` on(`t1`.`id` = `a`.`pref1_training_dept_id`)) left join `training_departments` `t2` on(`t2`.`id` = `a`.`pref2_training_dept_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_department_history`
--
DROP TABLE IF EXISTS `v_department_history`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_department_history`  AS SELECT `v_applications_base`.`application_id` AS `application_id`, `v_applications_base`.`applicant_id` AS `applicant_id`, `v_applications_base`.`applicant_name` AS `applicant_name`, `v_applications_base`.`applicant_email` AS `applicant_email`, `v_applications_base`.`applicant_phone` AS `applicant_phone`, `v_applications_base`.`app_full_name` AS `app_full_name`, `v_applications_base`.`app_email` AS `app_email`, `v_applications_base`.`app_phone` AS `app_phone`, `v_applications_base`.`training_duration` AS `training_duration`, `v_applications_base`.`start_date` AS `start_date`, `v_applications_base`.`pref1_training_dept_id` AS `pref1_training_dept_id`, `v_applications_base`.`pref1_training_dept_name` AS `pref1_training_dept_name`, `v_applications_base`.`pref2_training_dept_id` AS `pref2_training_dept_id`, `v_applications_base`.`pref2_training_dept_name` AS `pref2_training_dept_name`, `v_applications_base`.`routed_pref` AS `routed_pref`, `v_applications_base`.`target_dept_id` AS `target_dept_id`, `v_applications_base`.`target_dept_name` AS `target_dept_name`, `v_applications_base`.`stage` AS `stage`, `v_applications_base`.`status` AS `status`, `v_applications_base`.`notes` AS `notes`, `v_applications_base`.`submitted_at` AS `submitted_at`, `v_applications_base`.`updated_at` AS `updated_at` FROM `v_applications_base` WHERE `v_applications_base`.`status` in ('accepted','canceled') AND `v_applications_base`.`routed_pref` is not null AND `v_applications_base`.`target_dept_id` is not null ORDER BY `v_applications_base`.`updated_at` DESC, `v_applications_base`.`application_id` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_department_inbox`
--
DROP TABLE IF EXISTS `v_department_inbox`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_department_inbox`  AS SELECT `v_applications_base`.`application_id` AS `application_id`, `v_applications_base`.`applicant_id` AS `applicant_id`, `v_applications_base`.`applicant_name` AS `applicant_name`, `v_applications_base`.`applicant_email` AS `applicant_email`, `v_applications_base`.`applicant_phone` AS `applicant_phone`, `v_applications_base`.`app_full_name` AS `app_full_name`, `v_applications_base`.`app_email` AS `app_email`, `v_applications_base`.`app_phone` AS `app_phone`, `v_applications_base`.`training_duration` AS `training_duration`, `v_applications_base`.`start_date` AS `start_date`, `v_applications_base`.`pref1_training_dept_id` AS `pref1_training_dept_id`, `v_applications_base`.`pref1_training_dept_name` AS `pref1_training_dept_name`, `v_applications_base`.`pref2_training_dept_id` AS `pref2_training_dept_id`, `v_applications_base`.`pref2_training_dept_name` AS `pref2_training_dept_name`, `v_applications_base`.`routed_pref` AS `routed_pref`, `v_applications_base`.`target_dept_id` AS `target_dept_id`, `v_applications_base`.`target_dept_name` AS `target_dept_name`, `v_applications_base`.`stage` AS `stage`, `v_applications_base`.`status` AS `status`, `v_applications_base`.`notes` AS `notes`, `v_applications_base`.`submitted_at` AS `submitted_at`, `v_applications_base`.`updated_at` AS `updated_at` FROM `v_applications_base` WHERE `v_applications_base`.`status` = 'in_dept' AND `v_applications_base`.`routed_pref` is not null AND `v_applications_base`.`target_dept_id` is not null ORDER BY `v_applications_base`.`submitted_at` ASC, `v_applications_base`.`application_id` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_manager_overview`
--
DROP TABLE IF EXISTS `v_manager_overview`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_manager_overview`  AS SELECT `v_applications_base`.`application_id` AS `application_id`, `v_applications_base`.`applicant_id` AS `applicant_id`, `v_applications_base`.`applicant_name` AS `applicant_name`, `v_applications_base`.`applicant_email` AS `applicant_email`, `v_applications_base`.`applicant_phone` AS `applicant_phone`, `v_applications_base`.`app_full_name` AS `app_full_name`, `v_applications_base`.`app_email` AS `app_email`, `v_applications_base`.`app_phone` AS `app_phone`, `v_applications_base`.`training_duration` AS `training_duration`, `v_applications_base`.`start_date` AS `start_date`, `v_applications_base`.`pref1_training_dept_id` AS `pref1_training_dept_id`, `v_applications_base`.`pref1_training_dept_name` AS `pref1_training_dept_name`, `v_applications_base`.`pref2_training_dept_id` AS `pref2_training_dept_id`, `v_applications_base`.`pref2_training_dept_name` AS `pref2_training_dept_name`, `v_applications_base`.`routed_pref` AS `routed_pref`, `v_applications_base`.`target_dept_id` AS `target_dept_id`, `v_applications_base`.`target_dept_name` AS `target_dept_name`, `v_applications_base`.`stage` AS `stage`, `v_applications_base`.`status` AS `status`, `v_applications_base`.`notes` AS `notes`, `v_applications_base`.`submitted_at` AS `submitted_at`, `v_applications_base`.`updated_at` AS `updated_at` FROM `v_applications_base` ORDER BY `v_applications_base`.`submitted_at` DESC, `v_applications_base`.`application_id` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_secretary_queue`
--
DROP TABLE IF EXISTS `v_secretary_queue`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_secretary_queue`  AS SELECT `a`.`id` AS `id`, `a`.`applicant_id` AS `applicant_id`, `a`.`full_name` AS `full_name`, `a`.`email` AS `email`, `a`.`phone` AS `phone`, `a`.`training_duration` AS `training_duration`, `a`.`start_date` AS `start_date`, `a`.`pref1_training_dept_id` AS `pref1_training_dept_id`, `a`.`pref2_training_dept_id` AS `pref2_training_dept_id`, `a`.`rejected_pref1` AS `rejected_pref1`, `a`.`rejected_pref2` AS `rejected_pref2`, `a`.`notes` AS `notes`, `a`.`submitted_at` AS `submitted_at`, `u`.`full_name` AS `applicant_full_name`, `t1`.`name_ar` AS `pref1_dept_name`, `t2`.`name_ar` AS `pref2_dept_name`, CASE WHEN `a`.`rejected_pref1` = 0 AND `a`.`rejected_pref2` = 0 THEN 'both_available' WHEN `a`.`rejected_pref1` = 0 AND `a`.`rejected_pref2` <> 0 THEN 'pref1_only' WHEN `a`.`rejected_pref1` <> 0 AND `a`.`rejected_pref2` = 0 THEN 'pref2_only' ELSE 'none_available' END AS `routing_options`, CASE WHEN `a`.`rejected_pref1` = 0 AND `a`.`rejected_pref2` = 0 THEN 'يمكن التوجيه لأي من الرغبتين' WHEN `a`.`rejected_pref1` = 0 AND `a`.`rejected_pref2` <> 0 THEN concat('يمكن التوجيه للرغبة الأولى فقط (',`t1`.`name_ar`,')') WHEN `a`.`rejected_pref1` <> 0 AND `a`.`rejected_pref2` = 0 THEN concat('يمكن التوجيه للرغبة الثانية فقط (',`t2`.`name_ar`,')') ELSE 'لا توجد خيارات متاحة - يجب الإلغاء' END AS `available_options_text` FROM (((`applications` `a` join `users` `u` on(`u`.`id` = `a`.`applicant_id`)) left join `training_departments` `t1` on(`t1`.`id` = `a`.`pref1_training_dept_id`)) left join `training_departments` `t2` on(`t2`.`id` = `a`.`pref2_training_dept_id`)) WHERE `a`.`status` = 'pending' AND `a`.`routed_pref` is null ;

-- --------------------------------------------------------

--
-- Structure for view `v_users_staff`
--
DROP TABLE IF EXISTS `v_users_staff`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_users_staff`  AS SELECT `u`.`id` AS `id`, `u`.`full_name` AS `full_name`, `u`.`email` AS `email`, `u`.`phone` AS `phone`, `u`.`role_id` AS `role_id`, `r`.`code` AS `role_code`, `r`.`name_ar` AS `role_name`, `u`.`training_department_id` AS `training_department_id`, `td`.`name_ar` AS `training_department_name`, `u`.`created_at` AS `created_at` FROM ((`users` `u` join `roles` `r` on(`r`.`id` = `u`.`role_id`)) left join `training_departments` `td` on(`td`.`id` = `u`.`training_department_id`)) WHERE `u`.`role_id` in (1,2,3) ORDER BY `u`.`role_id` ASC, `td`.`name_ar` ASC, `u`.`full_name` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_users_students`
--
DROP TABLE IF EXISTS `v_users_students`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_users_students`  AS SELECT `u`.`id` AS `id`, `u`.`full_name` AS `full_name`, `u`.`email` AS `email`, `u`.`phone` AS `phone`, `u`.`created_at` AS `created_at` FROM `users` AS `u` WHERE `u`.`role_id` = 4 ORDER BY `u`.`id` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_app_user` (`applicant_id`),
  ADD KEY `idx_app_status` (`status`),
  ADD KEY `idx_app_stage` (`stage`),
  ADD KEY `idx_app_submitted` (`submitted_at`),
  ADD KEY `idx_app_routed` (`routed_pref`),
  ADD KEY `idx_app_pref1_t` (`pref1_training_dept_id`),
  ADD KEY `idx_app_pref2_t` (`pref2_training_dept_id`),
  ADD KEY `idx_rejected_pref1` (`rejected_pref1`),
  ADD KEY `idx_rejected_pref2` (`rejected_pref2`);

--
-- Indexes for table `org_departments`
--
ALTER TABLE `org_departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_org_parent_name` (`parent_id`,`name_ar`),
  ADD KEY `idx_org_parent` (`parent_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `training_departments`
--
ALTER TABLE `training_departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_train_parent_name` (`parent_id`,`name_ar`),
  ADD KEY `idx_train_parent` (`parent_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role_id`),
  ADD KEY `idx_users_train` (`training_department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `org_departments`
--
ALTER TABLE `org_departments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `training_departments`
--
ALTER TABLE `training_departments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- قيود الجداول المُلقاة.
--

--
-- قيود الجداول `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `fk_app_pref1_t` FOREIGN KEY (`pref1_training_dept_id`) REFERENCES `training_departments` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_app_pref2_t` FOREIGN KEY (`pref2_training_dept_id`) REFERENCES `training_departments` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_app_user` FOREIGN KEY (`applicant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `org_departments`
--
ALTER TABLE `org_departments`
  ADD CONSTRAINT `fk_org_parent` FOREIGN KEY (`parent_id`) REFERENCES `org_departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- قيود الجداول `training_departments`
--
ALTER TABLE `training_departments`
  ADD CONSTRAINT `fk_train_parent` FOREIGN KEY (`parent_id`) REFERENCES `training_departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- قيود الجداول `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `fk_users_train` FOREIGN KEY (`training_department_id`) REFERENCES `training_departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
