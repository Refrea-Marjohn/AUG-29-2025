-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 14, 2025 at 09:19 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lawfirm`
--

-- --------------------------------------------------------

--
-- Table structure for table `user_form`
--

CREATE TABLE `user_form` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('admin','attorney','client','employee') DEFAULT 'client',
  `login_attempts` int(11) DEFAULT 0,
  `last_failed_login` timestamp NULL DEFAULT NULL,
  `account_locked` tinyint(1) DEFAULT 0,
  `lockout_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_form`
--

INSERT INTO `user_form` (`id`, `name`, `profile_image`, `last_login`, `email`, `phone_number`, `password`, `user_type`, `login_attempts`, `last_failed_login`, `account_locked`, `lockout_until`, `created_at`) VALUES
(20, 'Mar John Refrea', 'uploads/admin/20_1755155087.png', '2025-08-14 15:18:14', 'marjohnrefrea123456@gmail.com', '09283262333', '$2y$10$yrs9n1Z/Nrq1d5XLvNihTOeRiq037s.NGo9wtXMjbNOkqlWyLOOwy', 'admin', 0, NULL, 0, NULL, '2025-08-06 11:26:01');

-- --------------------------------------------------------

--
-- Table structure for table `admin_documents`
--

CREATE TABLE `admin_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `upload_date` datetime NOT NULL DEFAULT current_timestamp(),
  `form_number` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `admin_documents_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `user_form` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_document_activity`
--

CREATE TABLE `admin_document_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `form_number` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `admin_document_activity_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `admin_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_document_activity_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_messages`
--

CREATE TABLE `admin_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `recipient_id` (`recipient_id`),
  CONSTRAINT `admin_messages_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attorney_cases`
--

CREATE TABLE `attorney_cases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `attorney_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `client_id` int(11) DEFAULT NULL,
  `case_type` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active',
  `next_hearing` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attorney_id` (`attorney_id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `attorney_cases_ibfk_1` FOREIGN KEY (`attorney_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attorney_cases_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attorney_documents`
--

CREATE TABLE `attorney_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `upload_date` datetime NOT NULL DEFAULT current_timestamp(),
  `case_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `case_id` (`case_id`),
  CONSTRAINT `attorney_documents_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attorney_documents_ibfk_2` FOREIGN KEY (`case_id`) REFERENCES `attorney_cases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attorney_document_activity`
--

CREATE TABLE `attorney_document_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `case_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_name` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `user_id` (`user_id`),
  KEY `case_id` (`case_id`),
  CONSTRAINT `attorney_document_activity_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `attorney_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attorney_document_activity_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attorney_document_activity_ibfk_3` FOREIGN KEY (`case_id`) REFERENCES `attorney_cases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attorney_messages`
--

CREATE TABLE `attorney_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attorney_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `attorney_id` (`attorney_id`),
  KEY `recipient_id` (`recipient_id`),
  CONSTRAINT `attorney_messages_ibfk_1` FOREIGN KEY (`attorney_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attorney_messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `case_schedules`
--

CREATE TABLE `case_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `case_id` int(11) DEFAULT NULL,
  `attorney_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `type` enum('Hearing','Appointment','Free Legal Advice') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `created_by_employee_id` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Scheduled',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_created_by_employee` (`created_by_employee_id`),
  CONSTRAINT `case_schedules_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `attorney_cases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `case_schedules_ibfk_2` FOREIGN KEY (`attorney_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  CONSTRAINT `case_schedules_ibfk_3` FOREIGN KEY (`client_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  CONSTRAINT `case_schedules_ibfk_4` FOREIGN KEY (`created_by_employee_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_cases`
--

CREATE TABLE `client_cases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `client_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `attorney_id` int(11) DEFAULT NULL,
  `case_type` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `next_hearing` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `attorney_id` (`attorney_id`),
  CONSTRAINT `client_cases_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  CONSTRAINT `client_cases_ibfk_2` FOREIGN KEY (`attorney_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_messages`
--

CREATE TABLE `client_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `recipient_id` (`recipient_id`),
  CONSTRAINT `client_messages_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  CONSTRAINT `client_messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_requests`
--

CREATE TABLE `document_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `case_id` int(11) NOT NULL,
  `attorney_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('Requested','Submitted','Reviewed','Approved','Rejected','Cancelled') DEFAULT 'Requested',
  `attorney_comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `case_id` (`case_id`),
  KEY `attorney_id` (`attorney_id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `document_requests_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `attorney_cases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `document_requests_ibfk_2` FOREIGN KEY (`attorney_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  CONSTRAINT `document_requests_ibfk_3` FOREIGN KEY (`client_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_request_comments`
--

CREATE TABLE `document_request_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `attorney_id` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  CONSTRAINT `document_request_comments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_request_files`
--

CREATE TABLE `document_request_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `document_request_files_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `document_request_files_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_documents`
--

CREATE TABLE `employee_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `upload_date` datetime NOT NULL DEFAULT current_timestamp(),
  `form_number` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `form_number` (`form_number`),
  CONSTRAINT `employee_documents_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `user_form` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_document_activity`
--

CREATE TABLE `employee_document_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `form_number` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `user_id` (`user_id`),
  KEY `form_number` (`form_number`),
  CONSTRAINT `employee_document_activity_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `employee_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_document_activity_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` enum('info','success','warning','error') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_type` enum('admin','attorney','client','employee') NOT NULL,
  `action` varchar(255) NOT NULL,
  `module` varchar(100) NOT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `status` enum('success','failed','warning','info') DEFAULT 'success',
  `priority` enum('low','medium','high','critical') DEFAULT 'low',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `additional_data` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_user_type` (`user_type`),
  KEY `idx_module` (`module`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_status` (`status`),
  CONSTRAINT `audit_trail_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `efiling_history`
--

CREATE TABLE `efiling_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attorney_id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `case_number` varchar(100) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `receiver_email` varchar(255) NOT NULL,
  `message` text,
  `status` enum('Sent','Failed') NOT NULL DEFAULT 'Sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `attorney_id` (`attorney_id`),
  KEY `case_id` (`case_id`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `efiling_history_ibfk_1` FOREIGN KEY (`attorney_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  CONSTRAINT `efiling_history_ibfk_2` FOREIGN KEY (`case_id`) REFERENCES `attorney_cases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

--
-- Employee Schedule System Enhancement
-- Added created_by_employee_id column to case_schedules table
-- This allows employees to create and manage schedules for attorneys and admins
-- Column added: created_by_employee_id int(11) DEFAULT NULL
-- Index added: idx_created_by_employee (created_by_employee_id)
--
