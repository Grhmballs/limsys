-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 22, 2025 at 04:55 AM
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
-- Database: `limsys`
--

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `is_public` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `visibility` enum('Public','Private') DEFAULT 'Private',
  `extracted_text` longtext DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `title`, `description`, `uploaded_by`, `is_public`, `created_at`, `filename`, `original_filename`, `file_path`, `file_size`, `visibility`, `extracted_text`, `updated_at`) VALUES
(1, 'Ordinance No. 101', 'An ordinance about waste management.', 2, 1, '2025-09-09 13:08:00', '', '', '', 0, 'Public', NULL, '2025-09-16 03:16:01'),
(2, 'Resolution No. 55', 'Resolution for budget allocation.', 2, 0, '2025-09-09 13:08:00', '', '', '', 0, 'Private', NULL, '2025-09-12 00:29:30'),
(3, 'Debug Test Document', 'Test description', 7, 1, '2025-09-12 00:33:59', 'debug_68c36a777d818_1757637239.pdf', 'MUNICIPAL ORDINANCE NO. 03-2021.pdf', 'uploads/debug_68c36a777d818_1757637239.pdf', 379431, 'Private', NULL, '2025-09-12 00:33:59'),
(6, 'MUNICIPAL ORDINANCE NO. 03-2021', 'No Smoking', 7, 1, '2025-09-12 00:40:23', 'doc_68c36bf7c8ccc_1757637623.pdf', 'MUNICIPAL ORDINANCE NO. 03-2021.pdf', 'uploads/doc_68c36bf7c8ccc_1757637623.pdf', 379431, 'Public', '', '2025-09-12 00:40:23'),
(7, 'MUNICIPAL ORDINANCE NO. 08', 'Road closure', 7, 1, '2025-09-12 00:41:20', 'doc_68c36c30a8eb2_1757637680.pdf', 'MUNICIPAL ORDINANCE NO. 08.pdf', 'uploads/doc_68c36c30a8eb2_1757637680.pdf', 333475, 'Public', '', '2025-09-12 00:41:20');

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `email`, `requested_at`, `status`, `admin_notes`, `processed_by`, `processed_at`) VALUES
(1, 5, 'joaquin@limsys.com', '2025-09-12 01:24:28', 'completed', '', 1, '2025-09-12 01:24:57'),
(2, 7, 'abato@limsys.com', '2025-09-12 01:26:54', 'completed', 'As you requested', 1, '2025-09-12 01:27:42'),
(3, 2, 'juan@limsys.com', '2025-09-12 01:28:08', 'completed', '', 1, '2025-09-12 01:28:28');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user','guest') DEFAULT 'guest',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive','suspended') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`, `status`) VALUES
(1, 'System Admin', 'admin@limsys.com', '$2y$10$dj2.3vIFCSi43/PMvFCXMeKYx7dGaRMTOeO9LDPi/eXCaHo1/vwDC', 'admin', '2025-09-09 13:08:00', 'active'),
(2, 'Juan Dela Cruz', 'juan@limsys.com', '$2y$10$Sur9cp71vXkJfstGrndL7OmnmGcWT8LayoWyVQqortqcJEHqTz88y', 'user', '2025-09-09 13:08:00', 'active'),
(3, 'Visitor', 'guest@limsys.com', '$2y$10$E1x9tNCYEGYvzDuhrshZ0e/jPWhEMMQI2MwkMDZ.K.JeQEd8r8qb.', 'guest', '2025-09-09 13:08:00', 'active'),
(5, 'Joaquin Rozano Jr.', 'joaquin@limsys.com', '$2y$10$mP7uSBO0KZ3Zr7XsLyvqveAHqBKSzW2HWVAGNSGoeildZXZVFOnQa', 'user', '2025-09-09 14:16:17', 'active'),
(6, 'Test User', 'testuser@limsys.com', '$2y$10$8pcwqG4R8j8QBqw.rHGeF.HAIPgHYsuGN8YRPL.cxYTs5HB62/cNe', 'user', '2025-09-09 14:21:50', 'active'),
(7, 'Vincent Kenly Abato', 'abato@limsys.com', '$2y$10$Lc0vV3J4Z/Kqt2.ObPZMK.ADBY.Wf24xs6lov8D21L0xErKXFfGvG', 'user', '2025-09-12 00:13:04', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `versions`
--

CREATE TABLE `versions` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `version_number` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ai_detected_changes` text DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `versions`
--

INSERT INTO `versions` (`id`, `document_id`, `version_number`, `filename`, `file_path`, `file_size`, `uploaded_by`, `created_at`, `ai_detected_changes`, `uploaded_at`) VALUES
(1, 1, 1, '', 'uploads/ordinance101_v1.pdf', 0, 0, '2025-09-12 00:39:29', 'Initial version', '2025-09-09 13:08:00'),
(2, 1, 2, '', 'uploads/ordinance101_v2.pdf', 0, 0, '2025-09-12 00:39:29', 'Minor edits to section 2', '2025-09-09 13:08:00'),
(3, 2, 1, '', 'uploads/resolution55_v1.pdf', 0, 0, '2025-09-12 00:39:29', 'First draft', '2025-09-09 13:08:00'),
(4, 6, 1, 'doc_68c36bf7c8ccc_1757637623.pdf', 'uploads/doc_68c36bf7c8ccc_1757637623.pdf', 379431, 7, '2025-09-12 00:40:23', NULL, '2025-09-12 00:40:23'),
(5, 7, 1, 'doc_68c36c30a8eb2_1757637680.pdf', 'uploads/doc_68c36c30a8eb2_1757637680.pdf', 333475, 7, '2025-09-12 00:41:20', NULL, '2025-09-12 00:41:20');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_requested_at` (`requested_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `versions`
--
ALTER TABLE `versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `versions`
--
ALTER TABLE `versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `password_resets_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `versions`
--
ALTER TABLE `versions`
  ADD CONSTRAINT `versions_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
