-- ============================================
-- REAKTOR SYSTEM V2.0 - DATABASE SCHEMA (FIXED)
-- Compatible dengan MySQL 5.7 / MariaDB 10.x
-- ============================================

-- Create database
CREATE DATABASE IF NOT EXISTS u258794476_dbreaktorsys 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE u258794476_dbreaktorsys;

-- ============================================
-- 1. TABEL ROLES
-- ============================================
CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    level INT NOT NULL DEFAULT 0,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default roles
INSERT INTO roles (name, display_name, level, description) VALUES
('SUPER_ADMIN', 'Super Administrator', 10, 'Akses penuh ke seluruh sistem'),
('ADMIN_KONI', 'Administrator KONI', 8, 'Mengelola data KONI dan cabor'),
('PENGURUS_CABOR', 'Pengurus Cabang Olahraga', 6, 'Mengelola data cabor tertentu'),
('PELATIH', 'Pelatih', 4, 'Akses data atlet dan jadwal latihan'),
('WASIT', 'Wasit/Juri', 4, 'Akses data pertandingan dan penilaian'),
('ATLET', 'Atlet', 3, 'Akses data pribadi dan prestasi'),
('VIEW_ONLY', 'View Only', 1, 'Hanya dapat melihat data'),
('GUEST', 'Guest', 0, 'Akses terbatas untuk pengunjung');

-- ============================================
-- 2. TABEL CABOR (Cabang Olahraga)
-- ============================================
CREATE TABLE IF NOT EXISTS cabor (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode VARCHAR(10) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    nama_lengkap VARCHAR(255),
    induk_organisasi VARCHAR(255),
    tahun_berdiri YEAR,
    alamat_sekretariat TEXT,
    no_telp VARCHAR(20),
    email VARCHAR(100),
    website VARCHAR(255),
    logo_path VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kode (kode),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample cabor
INSERT INTO cabor (kode, nama, nama_lengkap) VALUES
('PSSI', 'Sepak Bola', 'Persatuan Sepak Bola Seluruh Indonesia'),
('PBSI', 'Bulu Tangkis', 'Persatuan Bulu Tangkis Seluruh Indonesia'),
('PASI', 'Atletik', 'Persatuan Atletik Seluruh Indonesia'),
('PRSI', 'Renang', 'Persatuan Renang Seluruh Indonesia'),
('PBVSI', 'Bola Voli', 'Persatuan Bola Voli Seluruh Indonesia');

-- ============================================
-- 3. TABEL USERS (Data Pribadi)
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nik VARCHAR(16) NOT NULL UNIQUE,
    nama_lengkap VARCHAR(255) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    password_hash_type VARCHAR(10) DEFAULT 'BCRYPT',
    no_hp VARCHAR(20),
    tempat_lahir VARCHAR(100),
    tanggal_lahir DATE,
    jenis_kelamin ENUM('L', 'P'),
    alamat TEXT,
    kecamatan VARCHAR(100),
    kelurahan VARCHAR(100),
    rt VARCHAR(5),
    rw VARCHAR(5),
    kode_pos VARCHAR(10),
    golongan_darah VARCHAR(5),
    status_pernikahan ENUM('Belum Menikah', 'Menikah', 'Duda', 'Janda'),
    pekerjaan VARCHAR(100),
    pendidikan_terakhir VARCHAR(50),
    foto_profil VARCHAR(255) DEFAULT 'assets/img/kepitran.png',
    foto_ktp VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    is_approved BOOLEAN DEFAULT FALSE,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    profile_completion INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nik (nik),
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_active_approved (is_active, is_approved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. TABEL USER_ROLES (Multi-role Support)
-- ============================================
CREATE TABLE IF NOT EXISTS user_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    cabor_id INT DEFAULT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    assigned_by INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_role_cabor (user_id, role_id, cabor_id),
    INDEX idx_user (user_id),
    INDEX idx_role (role_id),
    INDEX idx_cabor (cabor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5. TABEL ATLET
-- ============================================
CREATE TABLE IF NOT EXISTS atlet (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    cabor_id INT NOT NULL,
    nomor_induk_atlet VARCHAR(50) UNIQUE,
    klub VARCHAR(255),
    kelas_kategori VARCHAR(100),
    tinggi_badan DECIMAL(5,2),
    berat_badan DECIMAL(5,2),
    mulai_bergabung DATE,
    status_atlet ENUM('Aktif', 'Non-Aktif', 'Pensiun', 'Cedera') DEFAULT 'Aktif',
    catatan_kesehatan TEXT,
    nama_pelatih VARCHAR(255),
    kontak_darurat_nama VARCHAR(255),
    kontak_darurat_no VARCHAR(20),
    kontak_darurat_hubungan VARCHAR(50),
    is_approved BOOLEAN DEFAULT FALSE,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_cabor (cabor_id),
    INDEX idx_status (status_atlet),
    INDEX idx_approved (is_approved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 6. TABEL PELATIH
-- ============================================
CREATE TABLE IF NOT EXISTS pelatih (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    cabor_id INT NOT NULL,
    nomor_induk_pelatih VARCHAR(50) UNIQUE,
    level_pelatih ENUM('Pratama', 'Muda', 'Madya', 'Utama') DEFAULT 'Pratama',
    spesialisasi VARCHAR(255),
    klub_tempat_melatih VARCHAR(255),
    pengalaman_tahun INT DEFAULT 0,
    status_pelatih ENUM('Aktif', 'Non-Aktif', 'Cuti') DEFAULT 'Aktif',
    is_approved BOOLEAN DEFAULT FALSE,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_cabor (cabor_id),
    INDEX idx_level (level_pelatih),
    INDEX idx_status (status_pelatih)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 7. TABEL WASIT
-- ============================================
CREATE TABLE IF NOT EXISTS wasit (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    cabor_id INT NOT NULL,
    nomor_induk_wasit VARCHAR(50) UNIQUE,
    level_wasit ENUM('Daerah', 'Provinsi', 'Nasional', 'Internasional') DEFAULT 'Daerah',
    lisensi_aktif_sampai DATE,
    status_wasit ENUM('Aktif', 'Non-Aktif', 'Suspended') DEFAULT 'Aktif',
    is_approved BOOLEAN DEFAULT FALSE,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_cabor (cabor_id),
    INDEX idx_level (level_wasit),
    INDEX idx_status (status_wasit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 8. TABEL PRESTASI_ATLET
-- ============================================
CREATE TABLE IF NOT EXISTS prestasi_atlet (
    id INT PRIMARY KEY AUTO_INCREMENT,
    atlet_id INT NOT NULL,
    nama_kejuaraan VARCHAR(255) NOT NULL,
    tingkat_kejuaraan ENUM('Kabupaten', 'Provinsi', 'Nasional', 'Internasional') NOT NULL,
    tahun YEAR NOT NULL,
    tempat_kejuaraan VARCHAR(255),
    tanggal_mulai DATE,
    tanggal_selesai DATE,
    juara_ke INT,
    medali ENUM('Emas', 'Perak', 'Perunggu', 'Tidak Ada'),
    catatan_prestasi TEXT,
    file_sertifikat VARCHAR(255),
    is_verified BOOLEAN DEFAULT FALSE,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_atlet (atlet_id),
    INDEX idx_tahun (tahun),
    INDEX idx_tingkat (tingkat_kejuaraan),
    INDEX idx_verified (is_verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 9. TABEL LISENSI_PELATIH
-- ============================================
CREATE TABLE IF NOT EXISTS lisensi_pelatih (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pelatih_id INT NOT NULL,
    jenis_lisensi VARCHAR(100) NOT NULL,
    nomor_lisensi VARCHAR(100) UNIQUE,
    level_lisensi VARCHAR(50),
    tanggal_terbit DATE NOT NULL,
    tanggal_kadaluarsa DATE NOT NULL,
    lembaga_penerbit VARCHAR(255),
    file_lisensi VARCHAR(255),
    status_lisensi ENUM('Aktif', 'Kadaluarsa', 'Dicabut') DEFAULT 'Aktif',
    is_verified BOOLEAN DEFAULT FALSE,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pelatih (pelatih_id),
    INDEX idx_status (status_lisensi),
    INDEX idx_kadaluarsa (tanggal_kadaluarsa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 10. TABEL SERTIFIKAT_WASIT
-- ============================================
CREATE TABLE IF NOT EXISTS sertifikat_wasit (
    id INT PRIMARY KEY AUTO_INCREMENT,
    wasit_id INT NOT NULL,
    jenis_sertifikat VARCHAR(100) NOT NULL,
    nomor_sertifikat VARCHAR(100) UNIQUE,
    level_sertifikat VARCHAR(50),
    tanggal_terbit DATE NOT NULL,
    tanggal_kadaluarsa DATE NOT NULL,
    lembaga_penerbit VARCHAR(255),
    file_sertifikat VARCHAR(255),
    status_sertifikat ENUM('Aktif', 'Kadaluarsa', 'Dicabut') DEFAULT 'Aktif',
    is_verified BOOLEAN DEFAULT FALSE,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_wasit (wasit_id),
    INDEX idx_status (status_sertifikat),
    INDEX idx_kadaluarsa (tanggal_kadaluarsa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 11. TABEL DIGITAL_CARDS (ID Card dengan QR)
-- ============================================
CREATE TABLE IF NOT EXISTS digital_cards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    card_type ENUM('ATLET', 'PELATIH', 'WASIT', 'PENGURUS') NOT NULL,
    card_number VARCHAR(50) UNIQUE NOT NULL,
    qr_code VARCHAR(255) NOT NULL,
    issue_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_card_type (card_type),
    INDEX idx_card_number (card_number),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 12. TABEL MESSAGES (Sistem Chat)
-- ============================================
CREATE TABLE IF NOT EXISTS messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT,
    cabor_id INT,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    is_broadcast BOOLEAN DEFAULT FALSE,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sender (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_cabor (cabor_id),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 13. TABEL NOTIFICATIONS
-- ============================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    url VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_type (type),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 14. TABEL AUDIT_LOG
-- ============================================
CREATE TABLE IF NOT EXISTS audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_table (table_name),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 15. TABEL LOGIN_ATTEMPTS (Security)
-- ============================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100),
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_successful BOOLEAN DEFAULT FALSE,
    INDEX idx_username (username),
    INDEX idx_ip (ip_address),
    INDEX idx_time (attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 16. TABEL USER_SESSIONS
-- ============================================
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_session (session_id),
    INDEX idx_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 17. TABEL SYSTEM_SETTINGS
-- ============================================
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(50) DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('site_name', 'Reaktor System', 'Nama aplikasi'),
('site_tagline', 'Sistem Manajemen Data Olahraga KONI Serdang Bedagai', 'Tagline aplikasi'),
('login_max_attempts', '5', 'Maksimal percobaan login'),
('login_lockout_duration', '15', 'Durasi lockout dalam menit'),
('session_timeout', '30', 'Timeout session dalam menit'),
('password_min_length', '8', 'Panjang minimum password'),
('card_validity_years', '2', 'Masa berlaku ID card dalam tahun');

-- ============================================
-- 18. TABEL FILE_UPLOADS
-- ============================================
CREATE TABLE IF NOT EXISTS file_uploads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100),
    related_table VARCHAR(50),
    related_id INT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_type (file_type),
    INDEX idx_related (related_table, related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 19. TABEL PASSWORD_HISTORY
-- ============================================
CREATE TABLE IF NOT EXISTS password_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 20. TABEL EVENTS (Kegiatan/Pertandingan)
-- ============================================
CREATE TABLE IF NOT EXISTS events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cabor_id INT NOT NULL,
    nama_event VARCHAR(255) NOT NULL,
    jenis_event ENUM('Latihan', 'Pertandingan', 'Kejuaraan', 'Seminar', 'Lainnya') NOT NULL,
    tanggal_mulai DATETIME NOT NULL,
    tanggal_selesai DATETIME NOT NULL,
    lokasi VARCHAR(255),
    deskripsi TEXT,
    status ENUM('Terjadwal', 'Berlangsung', 'Selesai', 'Dibatalkan') DEFAULT 'Terjadwal',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cabor (cabor_id),
    INDEX idx_tanggal (tanggal_mulai, tanggal_selesai),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- INSERT SUPER ADMIN DEFAULT
-- ============================================
-- Password: admin123 (di-hash dengan bcrypt)
INSERT INTO users (
    nik, nama_lengkap, username, email, password, 
    password_hash_type, is_active, is_approved, profile_completion
) VALUES (
    '1234567890123456', 
    'Super Administrator', 
    'superadmin', 
    'admin@koniserdangbedagai.or.id',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'BCRYPT',
    TRUE,
    TRUE,
    100
);

-- Assign Super Admin role
INSERT INTO user_roles (user_id, role_id, is_primary) 
VALUES (1, 1, TRUE);

-- ============================================
-- ADD FOREIGN KEYS (Setelah semua tabel dibuat)
-- ============================================
ALTER TABLE users 
ADD CONSTRAINT fk_users_approved_by 
FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE user_roles
ADD CONSTRAINT fk_user_roles_user 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_user_roles_role 
FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_user_roles_cabor 
FOREIGN KEY (cabor_id) REFERENCES cabor(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_user_roles_assigned_by 
FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE atlet
ADD CONSTRAINT fk_atlet_user 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_atlet_cabor 
FOREIGN KEY (cabor_id) REFERENCES cabor(id) ON DELETE RESTRICT,
ADD CONSTRAINT fk_atlet_approved_by 
FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE pelatih
ADD CONSTRAINT fk_pelatih_user 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_pelatih_cabor 
FOREIGN KEY (cabor_id) REFERENCES cabor(id) ON DELETE RESTRICT,
ADD CONSTRAINT fk_pelatih_approved_by 
FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE wasit
ADD CONSTRAINT fk_wasit_user 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_wasit_cabor 
FOREIGN KEY (cabor_id) REFERENCES cabor(id) ON DELETE RESTRICT,
ADD CONSTRAINT fk_wasit_approved_by 
FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE prestasi_atlet
ADD CONSTRAINT fk_prestasi_atlet 
FOREIGN KEY (atlet_id) REFERENCES atlet(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_prestasi_verified_by 
FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE lisensi_pelatih
ADD CONSTRAINT fk_lisensi_pelatih 
FOREIGN KEY (pelatih_id) REFERENCES pelatih(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_lisensi_verified_by 
FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE sertifikat_wasit
ADD CONSTRAINT fk_sertifikat_wasit 
FOREIGN KEY (wasit_id) REFERENCES wasit(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_sertifikat_verified_by 
FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE digital_cards
ADD CONSTRAINT fk_digital_cards_user 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE messages
ADD CONSTRAINT fk_messages_sender 
FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_messages_receiver 
FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_messages_cabor 
FOREIGN KEY (cabor_id) REFERENCES cabor(id) ON DELETE CASCADE;

ALTER TABLE notifications
ADD CONSTRAINT fk_notifications_user 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE audit_log
ADD CONSTRAINT fk_audit_log_user 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE user_sessions
ADD CONSTRAINT fk_user_sessions_user 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE file_uploads
ADD CONSTRAINT fk_file_uploads_user 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE password_history
ADD CONSTRAINT fk_password_history_user 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE events
ADD CONSTRAINT fk_events_cabor 
FOREIGN KEY (cabor_id) REFERENCES cabor(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_events_created_by 
FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT;

-- ============================================
-- CREATE VIEWS
-- ============================================

-- View untuk melihat user dengan role mereka
CREATE OR REPLACE VIEW v_user_roles AS
SELECT 
    u.id,
    u.nik,
    u.nama_lengkap,
    u.username,
    u.email,
    u.is_active,
    u.is_approved,
    r.name as role_name,
    r.display_name as role_display,
    r.level as role_level,
    c.nama as cabor_nama,
    ur.is_primary
FROM users u
JOIN user_roles ur ON u.id = ur.user_id
JOIN roles r ON ur.role_id = r.id
LEFT JOIN cabor c ON ur.cabor_id = c.id
ORDER BY u.id, ur.is_primary DESC, r.level DESC;

-- View untuk statistik per cabor
CREATE OR REPLACE VIEW v_cabor_statistics AS
SELECT 
    c.id,
    c.kode,
    c.nama,
    (SELECT COUNT(*) FROM atlet a WHERE a.cabor_id = c.id AND a.is_approved = TRUE) as total_atlet,
    (SELECT COUNT(*) FROM pelatih p WHERE p.cabor_id = c.id AND p.is_approved = TRUE) as total_pelatih,
    (SELECT COUNT(*) FROM wasit w WHERE w.cabor_id = c.id AND w.is_approved = TRUE) as total_wasit,
    (SELECT COUNT(*) FROM user_roles ur WHERE ur.cabor_id = c.id AND ur.role_id = 3) as total_pengurus
FROM cabor c
WHERE c.is_active = TRUE;

-- ============================================
-- CREATE INDEXES FOR PERFORMANCE
-- ============================================
CREATE INDEX idx_audit_user_action ON audit_log(user_id, action, created_at);
CREATE INDEX idx_login_attempts_cleanup ON login_attempts(attempt_time);
CREATE INDEX idx_notifications_unread ON notifications(user_id, is_read);
CREATE INDEX idx_messages_unread ON messages(receiver_id, is_read);

-- ============================================
-- DATABASE CREATED SUCCESSFULLY!
-- Total: 20 Tables + 2 Views
-- Compatible dengan MySQL 5.7 / MariaDB 10.x
-- ============================================