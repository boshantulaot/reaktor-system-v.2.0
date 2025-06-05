<?php
// File: reaktorsystem/admin/roles/proses_tambah_anggota.php

// 1. Inisialisasi Inti
require_once(__DIR__ . '/../../core/init_core.php');

// 2. Pengecekan Akses & Session
if ($user_login_status !== true || $user_role_utama != 'super_admin') {
    $_SESSION['pesan_error_global'] = "Akses ditolak. Anda tidak memiliki izin untuk melakukan tindakan ini.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}
$user_nik_pelaku_proses = $user_nik;

if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_global'] = "Koneksi Database Gagal!";
    header("Location: tambah_anggota.php");
    exit();
}

// Hanya proses jika metode adalah POST dan tombol submit ditekan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_tambah_anggota'])) {
    
    // 3. Ambil dan Sanitasi Data Form
    $nik_input_anggota = trim($_POST['nik'] ?? '');
    $jabatan_input = trim($_POST['jabatan'] ?? '');
    $role_input = trim($_POST['role'] ?? '');
    $id_cabor_input = ($role_input == 'pengurus_cabor' && !empty($_POST['id_cabor'])) ? filter_var($_POST['id_cabor'], FILTER_SANITIZE_NUMBER_INT) : null;
    $tingkat_pengurus_input = trim($_POST['tingkat_pengurus'] ?? 'Kabupaten');
    $is_verified_input = isset($_POST['is_verified']) ? 1 : 0;

    $_SESSION['form_data_anggota_tambah'] = $_POST;
    $errors_agt_tambah = [];
    $error_fields_agt_tambah = [];

    // 4. Validasi Server-Side
    if (empty($nik_input_anggota) || !preg_match('/^\d{16}$/', $nik_input_anggota)) { 
        $errors_agt_tambah[] = "Pengguna (NIK) wajib dipilih dan harus valid."; $error_fields_agt_tambah[] = 'nik';
    }
    if (empty($jabatan_input)) { 
        $errors_agt_tambah[] = "Jabatan wajib diisi."; $error_fields_agt_tambah[] = 'jabatan';
    }
    // Peran yang diizinkan untuk ditambahkan melalui form ini
    $allowed_roles_for_add = ['admin_koni', 'pengurus_cabor', 'view_only', 'guest'];
    if (empty($role_input) || !in_array($role_input, $allowed_roles_for_add)) {
        $errors_agt_tambah[] = "Peran Sistem tidak valid."; $error_fields_agt_tambah[] = 'role';
    }
    if ($role_input == 'pengurus_cabor' && (empty($id_cabor_input) || !filter_var($id_cabor_input, FILTER_VALIDATE_INT))) {
        $errors_agt_tambah[] = "Cabang Olahraga wajib dipilih untuk peran Pengurus Cabor."; $error_fields_agt_tambah[] = 'id_cabor';
    }
    if (empty($tingkat_pengurus_input) || !in_array($tingkat_pengurus_input, ['Kabupaten','Provinsi','Pusat'])) {
        $errors_agt_tambah[] = "Tingkat Pengurus tidak valid."; $error_fields_agt_tambah[] = 'tingkat_pengurus';
    }

    // Validasi Lanjutan: Cek apakah NIK valid di tabel pengguna & belum ada di tabel anggota
    if (empty($error_fields_agt_tambah['nik'])) { // Hanya jika format NIK benar
        try {
            // Cek apakah NIK ada dan aktif di tabel pengguna
            $stmt_cek_pengguna = $pdo->prepare("SELECT nik FROM pengguna WHERE nik = :nik AND is_approved = 1");
            $stmt_cek_pengguna->bindParam(':nik', $nik_input_anggota, PDO::PARAM_STR);
            $stmt_cek_pengguna->execute();
            if (!$stmt_cek_pengguna->fetch()) {
                $errors_agt_tambah[] = "Pengguna dengan NIK " . htmlspecialchars($nik_input_anggota) . " tidak ditemukan atau belum aktif.";
                $error_fields_agt_tambah[] = 'nik';
            } else {
                // Cek apakah NIK sudah memiliki peran di tabel anggota (Satu NIK satu peran utama)
                $stmt_cek_nik_di_anggota = $pdo->prepare("SELECT nik FROM anggota WHERE nik = :nik_check");
                $stmt_cek_nik_di_anggota->bindParam(':nik_check', $nik_input_anggota, PDO::PARAM_STR);
                $stmt_cek_nik_di_anggota->execute();
                if ($stmt_cek_nik_di_anggota->fetch()) {
                    $errors_agt_tambah[] = "Pengguna dengan NIK " . htmlspecialchars($nik_input_anggota) . " sudah memiliki peran di sistem. Satu pengguna hanya boleh memiliki satu peran utama struktural.";
                    $error_fields_agt_tambah[] = 'nik';
                }
            }
        } catch (PDOException $e) {
            error_log("Proses Tambah Anggota - DB Cek Pengguna/Anggota Error: " . $e->getMessage());
            $errors_agt_tambah[] = "Terjadi kesalahan saat memvalidasi data pengguna.";
        }
    }
    
    // Cek duplikasi spesifik untuk peran (nik, role, id_cabor) - ini sudah ditangani oleh UNIQUE KEY di DB,
    // tapi validasi di sini memberikan pesan error yang lebih baik sebelum mencoba insert.
    // Namun, karena kita sudah membatasi satu NIK satu peran di anggota, cek ini mungkin redundan jika validasi di atas sudah ketat.
    // Saya akan membiarkan validasi NIK di atas sebagai yang utama untuk "satu NIK, satu peran".

    if (!empty($errors_agt_tambah)) {
        $_SESSION['errors_anggota_tambah'] = $errors_agt_tambah;
        $_SESSION['error_fields_anggota_tambah'] = array_unique($error_fields_agt_tambah);
        header("Location: tambah_anggota.php");
        exit();
    }

    // 5. Simpan ke Database
    try {
        $pdo->beginTransaction();
        $current_timestamp_db_agt = date('Y-m-d H:i:s');

        $verified_by_val_agt = $is_verified_input ? $user_nik_pelaku_proses : null;
        $verified_at_val_agt = $is_verified_input ? $current_timestamp_db_agt : null;

        $sql_insert_anggota = "INSERT INTO anggota (
                            nik, jabatan, role, id_cabor, tingkat_pengurus, 
                            is_verified, verified_by_nik, verified_at
                        ) VALUES (
                            :nik, :jabatan, :role, :id_cabor, :tingkat_pengurus,
                            :is_verified, :verified_by_nik, :verified_at
                        )";
        $stmt_insert_agt = $pdo->prepare($sql_insert_anggota);

        $stmt_insert_agt->bindParam(':nik', $nik_input_anggota, PDO::PARAM_STR);
        $stmt_insert_agt->bindParam(':jabatan', $jabatan_input, PDO::PARAM_STR);
        $stmt_insert_agt->bindParam(':role', $role_input, PDO::PARAM_STR);
        $stmt_insert_agt->bindParam(':id_cabor', $id_cabor_input, $id_cabor_input === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt_insert_agt->bindParam(':tingkat_pengurus', $tingkat_pengurus_input, PDO::PARAM_STR);
        $stmt_insert_agt->bindParam(':is_verified', $is_verified_input, PDO::PARAM_INT);
        $stmt_insert_agt->bindParam(':verified_by_nik', $verified_by_val_agt, $verified_by_val_agt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_insert_agt->bindParam(':verified_at', $verified_at_val_agt, $verified_at_val_agt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        
        $stmt_insert_agt->execute();
        $new_anggota_id_db = $pdo->lastInsertId();

        // 6. Audit Log
        $stmt_nama_user_log_agt = $pdo->prepare("SELECT nama_lengkap FROM pengguna WHERE nik = :nik");
        $stmt_nama_user_log_agt->bindParam(':nik', $nik_input_anggota, PDO::PARAM_STR);
        $stmt_nama_user_log_agt->execute();
        $nama_user_for_log_agt = $stmt_nama_user_log_agt->fetchColumn();

        $data_baru_log_array_agt = [
            'id_anggota' => $new_anggota_id_db, 'nik_anggota' => $nik_input_anggota,
            'nama_anggota' => $nama_user_for_log_agt, 'jabatan' => $jabatan_input,
            'role' => $role_input, 'id_cabor' => $id_cabor_input,
            'tingkat_pengurus' => $tingkat_pengurus_input,
            'status_verifikasi' => $is_verified_input ? 'Terverifikasi' : 'Pending'
        ];
        
        $log_stmt_agt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_baru, keterangan) VALUES (:user_nik, :aksi, :tabel, :id_data, :data_baru, :keterangan)");
        $log_stmt_agt->execute([
            ':user_nik' => $user_nik_pelaku_proses,
            ':aksi' => 'TAMBAH PERAN ANGGOTA',
            ':tabel' => 'anggota',
            ':id_data' => $new_anggota_id_db,
            ':data_baru' => json_encode($data_baru_log_array_agt),
            ':keterangan' => 'Menambahkan peran ' . htmlspecialchars($role_input) . ' (' . htmlspecialchars($jabatan_input) . ') untuk ' . htmlspecialchars($nama_user_for_log_agt)
        ]);

        $pdo->commit();
        unset($_SESSION['form_data_anggota_tambah']);
        $_SESSION['pesan_sukses_global'] = "Peran '" . htmlspecialchars($jabatan_input) . "' untuk " . htmlspecialchars($nama_user_for_log_agt) . " berhasil ditambahkan.";
        
        header("Location: daftar_anggota.php");
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("Proses Tambah Anggota - DB Execute Error: " . $e->getMessage());
        $_SESSION['errors_anggota_tambah'] = ["Terjadi kesalahan teknis saat menyimpan data peran anggota."];
        if (strpos(strtolower($e->getMessage()), 'duplicate entry') !== false && strpos(strtolower($e->getMessage()), 'unik_nik_role_cabor') !== false) { // Sesuaikan dengan nama unique key Anda
            $_SESSION['errors_anggota_tambah'] = ["Kombinasi NIK, Peran, dan Cabor (jika ada) ini sudah terdaftar."];
        }
        header("Location: tambah_anggota.php");
        exit();
    }
} else {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau permintaan tidak sesuai.";
    header("Location: tambah_anggota.php");
    exit();
}
?>