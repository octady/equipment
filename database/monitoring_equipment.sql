-- =========================================
-- Equipment Monitoring System Database
-- =========================================

-- Create Database
CREATE DATABASE IF NOT EXISTS monitoring_equipment;
USE monitoring_equipment;

-- =========================================
-- TABLE: users
-- =========================================
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- =========================================
-- Default Users Data
-- Password for admin: admin123 (MD5)
-- Password for user: user123 (MD5)
-- =========================================
INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `role`, `created_at`) VALUES
(1, 'admin', '6408597507d9ffe85f5a02c628e6ed02', 'Administrator', 'admin', '2025-12-30 12:11:54'),
(2, 'user', 'fcd7258d18dd8a4000e648546b8d7e6a', 'User Monitoring', 'user', '2025-12-30 12:11:54');
