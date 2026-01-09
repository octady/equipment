-- Table for storing Laporan Pengukuran (Measurement Reports)
-- Created: 2026-01-09

CREATE TABLE IF NOT EXISTS laporan_pengukuran (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tanggal DATE NOT NULL,
    dibuat_oleh VARCHAR(255) NOT NULL,
    jabatan VARCHAR(255),
    tahanan_isolasi_data JSON,
    simulasi_genset_data JSON,
    simulasi_ups_data JSON,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Index for faster queries (ignore if already exists)
-- Note: MySQL doesn't support IF NOT EXISTS for CREATE INDEX, so we use this approach
-- If you get "Duplicate key name" error, the indexes already exist and can be ignored
CREATE INDEX idx_laporan_tanggal ON laporan_pengukuran(tanggal);
CREATE INDEX idx_laporan_created_by ON laporan_pengukuran(created_by);

-- Alternative: Drop and recreate (uncomment if needed)
-- DROP INDEX IF EXISTS idx_laporan_tanggal ON laporan_pengukuran;
-- DROP INDEX IF EXISTS idx_laporan_created_by ON laporan_pengukuran;
-- CREATE INDEX idx_laporan_tanggal ON laporan_pengukuran(tanggal);
-- CREATE INDEX idx_laporan_created_by ON laporan_pengukuran(created_by);
