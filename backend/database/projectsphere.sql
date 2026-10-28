-- ============================================================
-- ProjectSphere Database Schema & Seed Data
-- Suitable for Diploma Computer Science Final Year Project
-- Compatible with MySQL 5.7+ / MariaDB 10.4+ / XAMPP
-- ============================================================

CREATE DATABASE IF NOT EXISTS `projectsphere` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `projectsphere`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `password_resets`;

-- 1. USERS TABLE (Handles authentication credentials and roles)
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(60) NOT NULL UNIQUE,
  `email` VARCHAR(120) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'student') NOT NULL DEFAULT 'student',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `password_resets` (
  `user_id` INT NOT NULL PRIMARY KEY,
  `code_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. STUDENTS TABLE (Extends user with academic profile)
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `roll_no` VARCHAR(40) NOT NULL UNIQUE,
  `full_name` VARCHAR(120) NOT NULL,
  `department` VARCHAR(100) NOT NULL DEFAULT 'Computer Engineering',
  `semester` VARCHAR(20) NOT NULL DEFAULT '6th Semester',
  `phone` VARCHAR(20) DEFAULT NULL,
  `bio` TEXT DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT 'assets/images/default-avatar.svg',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. ADMINS TABLE (Extends user with faculty/staff profile)
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `full_name` VARCHAR(120) NOT NULL,
  `department` VARCHAR(100) NOT NULL DEFAULT 'Computer Technology',
  `designation` VARCHAR(100) NOT NULL DEFAULT 'Project Evaluator & Coordinator',
  `phone` VARCHAR(20) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_admins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. PROJECT CATEGORIES TABLE
DROP TABLE IF EXISTS `project_categories`;
CREATE TABLE `project_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `slug` VARCHAR(120) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `icon` VARCHAR(50) DEFAULT 'bi-folder',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. PROJECTS TABLE
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(220) NOT NULL UNIQUE,
  `short_description` VARCHAR(300) NOT NULL,
  `problem_statement` TEXT NOT NULL,
  `objectives` TEXT NOT NULL,
  `features` TEXT NOT NULL,
  `technologies` VARCHAR(255) NOT NULL,
  `demo_url` VARCHAR(255) DEFAULT NULL,
  `thumbnail_image` VARCHAR(255) DEFAULT 'assets/images/project-default.svg',
  `documentation_file` VARCHAR(255) DEFAULT NULL,
  `private_source_code_file` VARCHAR(255) DEFAULT NULL,
  `private_source_repo` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` TEXT DEFAULT NULL,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_projects_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projects_category` FOREIGN KEY (`category_id`) REFERENCES `project_categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. PROJECT TEAM MEMBERS TABLE
DROP TABLE IF EXISTS `project_team_members`;
CREATE TABLE `project_team_members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `member_name` VARCHAR(120) NOT NULL,
  `roll_no` VARCHAR(40) NOT NULL,
  `role` VARCHAR(60) NOT NULL DEFAULT 'Member',
  CONSTRAINT `fk_team_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. PROJECT IMAGES / SCREENSHOTS TABLE
DROP TABLE IF EXISTS `project_images`;
CREATE TABLE `project_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `caption` VARCHAR(150) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_images_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. EVALUATIONS TABLE
DROP TABLE IF EXISTS `evaluations`;
CREATE TABLE `evaluations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL UNIQUE,
  `admin_id` INT NOT NULL,
  `innovation_score` DECIMAL(4,1) NOT NULL DEFAULT 0.0,    -- Max 20
  `functionality_score` DECIMAL(4,1) NOT NULL DEFAULT 0.0, -- Max 30
  `ui_design_score` DECIMAL(4,1) NOT NULL DEFAULT 0.0,     -- Max 20
  `tech_usage_score` DECIMAL(4,1) NOT NULL DEFAULT 0.0,    -- Max 15
  `presentation_score` DECIMAL(4,1) NOT NULL DEFAULT 0.0,  -- Max 15
  `total_score` DECIMAL(5,1) NOT NULL DEFAULT 0.0,         -- Max 100
  `feedback_text` TEXT NOT NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `evaluated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_eval_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eval_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. SEPARATE FEEDBACK TABLE (For timeline/multiple remarks or reviews)
DROP TABLE IF EXISTS `feedback`;
CREATE TABLE `feedback` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `admin_id` INT NOT NULL,
  `comment` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_feedback_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_feedback_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. RANKINGS TABLE (Cached and auto-calculated leaderboard table)
DROP TABLE IF EXISTS `rankings`;
CREATE TABLE `rankings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL UNIQUE,
  `category_id` INT NOT NULL,
  `total_score` DECIMAL(5,1) NOT NULL,
  `overall_rank` INT NOT NULL,
  `category_rank` INT NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_rankings_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rankings_category` FOREIGN KEY (`category_id`) REFERENCES `project_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA
-- Default Passwords:
-- Admin: admin123
-- Students: student123
-- ============================================================

-- Insert Users
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `status`) VALUES
(1, 'admin', 'admin@projectsphere.edu', '$2y$10$RVmw5cGLmT.W3VBym272h.Qsn9ahpqp1g8KT94RQJWbRrlKV9gQGW', 'admin', 'active'),
(2, 'rahul_sharma', 'rahul@college.edu', '$2y$10$et6O0C2Kf4K0WPSqc0NI3O0JAUX2xlcKvKL0zMxiPtUj.9u4WE3em', 'student', 'active'),
(3, 'priya_patel', 'priya@college.edu', '$2y$10$et6O0C2Kf4K0WPSqc0NI3O0JAUX2xlcKvKL0zMxiPtUj.9u4WE3em', 'student', 'active'),
(4, 'amit_kumar', 'amit@college.edu', '$2y$10$et6O0C2Kf4K0WPSqc0NI3O0JAUX2xlcKvKL0zMxiPtUj.9u4WE3em', 'student', 'active'),
(5, 'neha_verma', 'neha@college.edu', '$2y$10$et6O0C2Kf4K0WPSqc0NI3O0JAUX2xlcKvKL0zMxiPtUj.9u4WE3em', 'student', 'active'),
(6, 'vikram_singh', 'vikram@college.edu', '$2y$10$et6O0C2Kf4K0WPSqc0NI3O0JAUX2xlcKvKL0zMxiPtUj.9u4WE3em', 'student', 'active');

-- Insert Faculty Admin
INSERT INTO `admins` (`id`, `user_id`, `full_name`, `department`, `designation`, `phone`) VALUES
(1, 1, 'Prof. Arvind Kulkarni', 'Department of Computer Engineering', 'HOD & Project Coordinator', '+91 98765 43210');

-- Insert Students
INSERT INTO `students` (`id`, `user_id`, `roll_no`, `full_name`, `department`, `semester`, `phone`, `bio`) VALUES
(1, 2, 'DCS-2023-01', 'Rahul Sharma', 'Computer Engineering', '6th Semester', '+91 98111 22233', 'Passionate full-stack developer interested in PHP, modern UI frameworks, and scalable web solutions.'),
(2, 3, 'DCS-2023-14', 'Priya Patel', 'Computer Engineering', '6th Semester', '+91 98222 33344', 'Android application enthusiast with an interest in QR code automation and clean UX design.'),
(3, 4, 'DCS-2023-28', 'Amit Kumar', 'Information Technology', '6th Semester', '+91 98333 44455', 'Focusing on database optimization, campus enterprise portals, and student workflow management.'),
(4, 5, 'DCS-2023-35', 'Neha Verma', 'Computer Engineering', '6th Semester', '+91 98444 55566', 'IoT hardware & micro-controller hobbyist with Arduino and sensor-based telemetry solutions.'),
(5, 6, 'DCS-2023-42', 'Vikram Singh', 'Computer Engineering', '6th Semester', '+91 98555 66677', 'Cyber security researcher focusing on network vulnerability assessment and safe protocols.');

-- Insert Categories
INSERT INTO `project_categories` (`id`, `name`, `slug`, `description`, `icon`) VALUES
(1, 'Web Development', 'web-development', 'Dynamic and responsive web portals, full-stack applications, and institutional systems.', 'bi-globe'),
(2, 'Mobile Application', 'mobile-application', 'Native and hybrid mobile applications for Android/iOS with modern user interfaces.', 'bi-phone'),
(3, 'IoT & Embedded Systems', 'iot-embedded-systems', 'Hardware-interfaced automated solutions, smart devices, sensor telemetry and controllers.', 'bi-cpu'),
(4, 'Cyber Security & Networking', 'cyber-security-networking', 'Network security utilities, vulnerability checkers, cryptography tools, and packet analyzers.', 'bi-shield-check'),
(5, 'Cloud & DevOps', 'cloud-devops', 'Cloud resource orchestrators, container automation, and microservice infrastructure.', 'bi-cloud-check'),
(6, 'Data Systems & Automation', 'data-systems-automation', 'Data pipeline tools, automated reporting, inventory analytics and record management.', 'bi-bar-chart-line');

-- Insert Sample Projects
INSERT INTO `projects` (
  `id`, `student_id`, `category_id`, `title`, `slug`, `short_description`, 
  `problem_statement`, `objectives`, `features`, `technologies`, 
  `demo_url`, `thumbnail_image`, `documentation_file`, `private_source_code_file`, `private_source_repo`,
  `status`, `submitted_at`
) VALUES
(
  1, 1, 1, 
  'Smart Library Management System', 
  'smart-library-management-system', 
  'A modern digital cataloging, book checkout tracking, and fine calculation portal with barcode/RFID integration.',
  'Traditional college libraries face long waiting queues, manual register entries, misplaced catalog cards, and frequent delays in fine reconciliation and book tracking.',
  '1. Automate book issue and return processing.\n2. Provide real-time catalog search by ISBN, author, and genre.\n3. Automatically calculate overdue fines based on college rules.\n4. Deliver an intuitive dashboard for librarians and students.',
  '• Real-time ISBN search & availability indicator\n• Student issue history and overdue reminder generation\n• Fine estimation calculator\n• Exportable circulation reports (CSV/PDF)\n• Mobile-friendly responsive catalog view',
  'PHP 8.2, MySQL, HTML5, CSS3, Bootstrap 5, JavaScript, Chart.js',
  'https://www.youtube.com/results?search_query=Smart+Library+Management+System+project+demo',
  'assets/images/project-library.jpg',
  'uploads/documents/smart-library-docs.pdf',
  'uploads/private-code/smart-library-src.zip',
  'https://github.com/rahul-college/private-library-code',
  'approved',
  '2026-09-10 10:30:00'
),
(
  2, 2, 2, 
  'Automated Attendance System using QR Code', 
  'automated-attendance-system-qr-code', 
  'A fast, contactless classroom attendance recording mobile app generating encrypted dynamic QR codes per lecture.',
  'Paper-based attendance sheets consume 10-15 minutes per lecture, suffer from proxy attendance, and require tedious manual entry into the college management ERP.',
  '1. Generate single-use rotating QR codes valid for 15 seconds.\n2. Allow students to authenticate and scan QR securely.\n3. Prevent proxy attendance through geolocation boundaries.\n4. Generate instant percentage calculations and low-attendance alerts.',
  '• Dynamic QR code generator on lecturer screen\n• Geofenced mobile scanner validation\n• Monthly and semester percentage calculator\n• Automated absentee notifications via SMS/Email\n• Export to Excel for departmental records',
  'Android SDK, Java, PHP Backend, MySQL REST API, Bootstrap 5',
  'https://www.youtube.com/results?search_query=QR+Code+Attendance+System+project+demo',
  'assets/images/project-qr.jpg',
  'uploads/documents/qr-attendance-docs.pdf',
  'uploads/private-code/qr-attendance-src.zip',
  'https://github.com/priya-college/private-qr-attendance',
  'approved',
  '2026-09-12 14:15:00'
),
(
  3, 3, 1, 
  'Campus Placement & Internship Tracking Portal', 
  'campus-placement-internship-portal', 
  'A centralized hub connecting training & placement officers, recruiting companies, and eligible final-year students.',
  'Placement notifications sent across multiple WhatsApp groups cause eligible students to miss interview deadlines, while TPO officers struggle to verify CGPA eligibility manually.',
  '1. Provide role-based portal for TPO cell, company HRs, and students.\n2. Enable automatic eligibility filtering based on 10th, 12th, and Diploma aggregate.\n3. Send one-click interview round schedules and hall-ticket downloads.\n4. Present comprehensive placement statistical insights to management.',
  '• Eligibility engine matching company criteria with student marks\n• Resume builder & verified credential repository\n• Round-wise shortlist broadcasting\n• Placement analytics chart for annual college accreditation',
  'PHP, MySQL, Bootstrap 5, Vanilla CSS, JavaScript, AJAX',
  'https://www.youtube.com/results?search_query=Campus+Placement+Internship+Portal+project+demo',
  'assets/images/project-placement.jpg',
  'uploads/documents/placement-portal-docs.pdf',
  'uploads/private-code/placement-portal-src.zip',
  'https://github.com/amit-college/private-placement-code',
  'approved',
  '2026-09-14 09:45:00'
),
(
  4, 4, 3, 
  'IoT Based Smart Agriculture Monitoring System', 
  'iot-smart-agriculture-monitoring', 
  'Soil moisture, ambient temperature, and automated water irrigation control using ESP32 microcontrollers and web telemetry.',
  'Farmers and agricultural institutes lack affordable, real-time ground soil data, leading to excessive water consumption or crop damage due to improper irrigation timing.',
  '1. Gather real-time soil moisture and environmental metrics via IoT sensors.\n2. Transmit sensor packets to central PHP/MySQL server over Wi-Fi.\n3. Automatically activate water solenoid valves when soil drops below threshold.\n4. Offer an accessible web dashboard for monitoring crop health.',
  '• ESP32 microcontroller with DHT22 and capacitive soil sensors\n• Automated relay switch triggering irrigation pump\n• Live data gauges showing moisture %, temperature, and humidity\n• Daily water consumption analytics and threshold configuration',
  'ESP32, C++, PHP, MySQL, CSS Grid, Chart.js, Bootstrap 5',
  'https://www.youtube.com/results?search_query=IoT+Smart+Agriculture+Monitoring+System+project+demo',
  'assets/images/project-iot.jpg',
  'uploads/documents/smart-agriculture-docs.pdf',
  'uploads/private-code/smart-agriculture-src.zip',
  'https://github.com/neha-college/private-iot-code',
  'approved',
  '2026-09-18 16:20:00'
),
(
  5, 5, 4, 
  'Network Packet Sniffer & Vulnerability Analyzer', 
  'network-packet-sniffer-vulnerability-analyzer', 
  'A lightweight educational network auditing utility for analyzing TCP/UDP packet headers and identifying insecure college LAN protocols.',
  'Diploma students often find industrial cybersecurity tools too complex to comprehend, lacking an intuitive educational tool that illustrates packet structures and plain-text risks.',
  '1. Capture and inspect local IPv4 network packets safely.\n2. Detect unencrypted traffic (HTTP, FTP, Telnet) with safety alerts.\n3. Visualize protocol distribution in clean graphical charts.\n4. Generate a simplified security audit score for lab computers.',
  '• Real-time packet parsing (Ethernet, IP, TCP, UDP)\n• Suspicious unencrypted credential flagger\n• Exportable PCAP capture summary\n• College lab security compliance checklist',
  'Python Socket Engine, PHP Dashboard, SQLite/MySQL, Bootstrap 5',
  'https://www.youtube.com/results?search_query=Network+Packet+Sniffer+Vulnerability+Analyzer+project+demo',
  'assets/images/project-network.jpg',
  'uploads/documents/network-sniffer-docs.pdf',
  'uploads/private-code/network-sniffer-src.zip',
  'https://github.com/vikram-college/private-network-code',
  'pending',
  '2026-10-01 11:10:00'
);

-- Insert Team Members
INSERT INTO `project_team_members` (`project_id`, `member_name`, `roll_no`, `role`) VALUES
(1, 'Rahul Sharma', 'DCS-2023-01', 'Team Lead & Backend'),
(1, 'Kavita Nair', 'DCS-2023-02', 'UI/UX Design'),
(1, 'Suresh Reddy', 'DCS-2023-03', 'Database & Testing'),
(2, 'Priya Patel', 'DCS-2023-14', 'Lead App Developer'),
(2, 'Rohan Joshi', 'DCS-2023-15', 'Backend API Integration'),
(3, 'Amit Kumar', 'DCS-2023-28', 'Team Lead & Full Stack'),
(3, 'Ananya Sen', 'DCS-2023-29', 'Documentation & Frontend'),
(4, 'Neha Verma', 'DCS-2023-35', 'Hardware Lead & IoT'),
(4, 'Deepak Soni', 'DCS-2023-36', 'PHP Web Interface'),
(5, 'Vikram Singh', 'DCS-2023-42', 'Team Lead & Security');

-- Insert Evaluations (100 Marks Breakdown: Innovation 20, Functionality 30, UI 20, Tech 15, Presentation 15)
INSERT INTO `evaluations` (
  `id`, `project_id`, `admin_id`, 
  `innovation_score`, `functionality_score`, `ui_design_score`, `tech_usage_score`, `presentation_score`, 
  `total_score`, `feedback_text`, `is_published`, `evaluated_at`
) VALUES
(
  1, 1, 1, 
  19.0, 28.5, 18.5, 14.0, 14.0, 
  94.0, 
  'Outstanding work! The architecture is very clean with modular PHP classes, well-commented code, and exceptional real-time overdue calculations. The documentation is thorough. Recommended for College Annual Project Exhibition.',
  1, '2026-09-15 15:00:00'
),
(
  2, 2, 1, 
  18.0, 27.0, 18.0, 13.5, 13.5, 
  90.0, 
  'Impressive implementation of rotating QR codes with geofence checks. Fast response time on scanning. Minor suggestion: add offline sync for network drops. Overall high quality presentation.',
  1, '2026-09-17 11:30:00'
),
(
  3, 3, 1, 
  17.0, 26.0, 17.5, 13.5, 13.0, 
  87.0, 
  'Very practical and highly relevant for our college TPO workflow. The eligibility filtering logic is well written. UI is clean and responsive on mobile devices.',
  1, '2026-09-20 14:00:00'
),
(
  4, 4, 1, 
  17.0, 24.5, 15.5, 13.0, 12.0, 
  82.0, 
  'Good integration between ESP32 hardware and web database. Telemetry dashboard updates smoothly. Improve wire management in the prototype enclosure for the final diploma presentation.',
  1, '2026-09-22 16:45:00'
);

-- Insert Feedback table entries
INSERT INTO `feedback` (`project_id`, `admin_id`, `comment`, `created_at`) VALUES
(1, 1, 'Final evaluation completed: Marks awarded 94/100. Project approved for departmental showcase.', '2026-09-15 15:00:00'),
(2, 1, 'Evaluated by Faculty Committee: Excellent execution on mobile frontend and backend sync. Marks awarded 90/100.', '2026-09-17 11:30:00'),
(3, 1, 'Evaluated: Very useful tool for college placement coordinators. Marks awarded 87/100.', '2026-09-20 14:00:00'),
(4, 1, 'Evaluated: Successful hardware and sensor test. Marks awarded 82/100.', '2026-09-22 16:45:00');

-- Insert Initial Pre-Calculated Rankings
INSERT INTO `rankings` (`project_id`, `category_id`, `total_score`, `overall_rank`, `category_rank`) VALUES
(1, 1, 94.0, 1, 1),
(2, 2, 90.0, 2, 1),
(3, 1, 87.0, 3, 2),
(4, 3, 82.0, 4, 1);
