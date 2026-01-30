-- phpMyAdmin SQL Dump
-- Schema Only (No Data) - WITHOUT FASILITAS TABLE
-- Database: `monitoring_equipment`
-- Generated: 2026-01-30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- Table structure for table `equipments`
--

CREATE TABLE `equipments` (
  `id` int NOT NULL,
  `section_id` int NOT NULL,
  `lokasi_id` int NOT NULL,
  `nama_peralatan` varchar(200) NOT NULL,
  `jam_operasi_harian` int DEFAULT '24',
  `standard_operasi` int DEFAULT '100',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `monitoring`
--

CREATE TABLE `monitoring` (
  `id` int NOT NULL,
  `equipment_id` int NOT NULL,
  `equipment_name` varchar(255) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `status` enum('O','X','V','-') NOT NULL DEFAULT 'O',
  `keterangan` text,
  `foto` varchar(255) DEFAULT NULL,
  `jam_operasi` int DEFAULT NULL,
  `checked_by` varchar(255) DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `monitoring_personel` (Junction Table - Many-to-Many)
--

CREATE TABLE `monitoring_personel` (
  `id` int NOT NULL,
  `monitoring_id` int NOT NULL,
  `personel_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dokumentasi_masalah`
--

CREATE TABLE `dokumentasi_masalah` (
  `id` int NOT NULL,
  `inspection_id` int NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inspeksi`
--

CREATE TABLE `inspeksi` (
  `id` int NOT NULL,
  `kegiatan` varchar(255) NOT NULL,
  `lokasi` varchar(255) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `hasil` text,
  `catatan` text,
  `foto` varchar(255) DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `laporan_pengukuran`
--

CREATE TABLE `laporan_pengukuran` (
  `id` int NOT NULL,
  `tanggal` date NOT NULL,
  `dibuat_oleh` varchar(255) NOT NULL,
  `jabatan` varchar(255) DEFAULT NULL,
  `tahanan_isolasi_data` json DEFAULT NULL,
  `simulasi_genset_data` json DEFAULT NULL,
  `simulasi_ups_data` json DEFAULT NULL,
  `personel_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lokasi`
--

CREATE TABLE `lokasi` (
  `id` int NOT NULL,
  `nama_lokasi` varchar(100) NOT NULL,
  `kode_lokasi` varchar(20) DEFAULT NULL,
  `deskripsi` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personnel`
--

CREATE TABLE `personnel` (
  `id` int NOT NULL,
  `nama_personnel` varchar(100) NOT NULL,
  `jabatan` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int NOT NULL,
  `parent_category` enum('ELECTRICAL','MECHANICAL') NOT NULL,
  `nama_section` varchar(100) NOT NULL,
  `kode_section` varchar(20) DEFAULT NULL,
  `urutan` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Indexes for dumped tables
--

ALTER TABLE `equipments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `lokasi_id` (`lokasi_id`);

ALTER TABLE `monitoring`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_inspection` (`equipment_id`,`tanggal`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `monitoring_personel`
  ADD PRIMARY KEY (`id`),
  ADD KEY `monitoring_id` (`monitoring_id`),
  ADD KEY `personel_id` (`personel_id`);

ALTER TABLE `dokumentasi_masalah`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inspection_id` (`inspection_id`);

ALTER TABLE `inspeksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `laporan_pengukuran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_laporan_tanggal` (`tanggal`),
  ADD KEY `idx_laporan_personel_id` (`personel_id`);

ALTER TABLE `lokasi`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `personnel`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

-- --------------------------------------------------------

--
-- AUTO_INCREMENT for dumped tables
--

ALTER TABLE `equipments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `monitoring`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `monitoring_personel`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `dokumentasi_masalah`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `inspeksi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `laporan_pengukuran`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `lokasi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `personnel`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `sections`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Constraints for dumped tables
--

ALTER TABLE `equipments`
  ADD CONSTRAINT `equipments_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `equipments_ibfk_2` FOREIGN KEY (`lokasi_id`) REFERENCES `lokasi` (`id`) ON DELETE CASCADE;

ALTER TABLE `monitoring`
  ADD CONSTRAINT `monitoring_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `monitoring_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `monitoring_personel`
  ADD CONSTRAINT `monitoring_personel_ibfk_1` FOREIGN KEY (`monitoring_id`) REFERENCES `monitoring` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `monitoring_personel_ibfk_2` FOREIGN KEY (`personel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE;

ALTER TABLE `dokumentasi_masalah`
  ADD CONSTRAINT `dokumentasi_masalah_ibfk_1` FOREIGN KEY (`inspection_id`) REFERENCES `monitoring` (`id`) ON DELETE CASCADE;

ALTER TABLE `laporan_pengukuran`
  ADD CONSTRAINT `laporan_pengukuran_ibfk_1` FOREIGN KEY (`personel_id`) REFERENCES `personnel` (`id`) ON DELETE SET NULL;

ALTER TABLE `inspeksi`
  ADD CONSTRAINT `inspeksi_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
