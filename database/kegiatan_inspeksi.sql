-- Table untuk menyimpan kegiatan inspeksi & pengujian
CREATE TABLE IF NOT EXISTS `kegiatan_inspeksi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kegiatan` varchar(255) NOT NULL,
  `lokasi` varchar(255) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `hasil` text,
  `catatan` text,
  `foto` varchar(255) DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tanggal` (`tanggal`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
