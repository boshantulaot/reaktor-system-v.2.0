<?php
// File: reaktorsystem/admin/roles/proses_edit_anggota.php

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
    header("Location: daftar_anggota.php");
    exit();
}

// Hanya proses jika metode POST, tombol submit ditekan, dan id_anggota ada
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_edit_anggota']) && isset($_POST['id_anggota']) && filter_var($_POST['id_anggota'], FILTER_VALIDATE_INT)) {
    
    $id_anggota_to_edit_proc = (int)$_POST['id_anggota'];
    // NIK tidak diubah, diambil dari hidden input untuk validasi dan referensi
    $nik_anggota_form_hidden = trim($_POST['nik'] ?? ''); 

    // 3. Ambil Data Anggota Lama dari Database
    try {
        $stmt_old_agt = $pdo->prepare("SELECT * FROM anggota WHERE id_anggota = :id_anggota AND nik = :nik");
        $stmt_old_agt->bindParam(':id_anggota', $id_anggota_to_edit_proc, PDO::PARAM_INT);
        $stmt_old_agt->bindParam(':nik', $nik_anggota_form_hidden, PDO::PARAM_STR);
        $stmt_old_agt->execute();
        $data_lama_anggota_db = $stmt_old_agt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Proses Edit Anggota - Gagal ambil data lama: " . $e->getMessage());
        $_SESSION['pesan_error_global'] = "Gagal mengambil data peran anggota yang akan diedit.";
        header("Location: daftar_anggota.php");
        exit();
    }

    if (!$data_lama_anggota_db) {
        $_SESSION['pesan_error_global'] = "Peran Anggota yang akan diedit tidak ditemukan atau NIK tidak cocok.";
        header("Location: daftar_anggota.php");
        exit();
    }

    // 4. Ambil dan Sanitasi Data Baru dari Form
    $jabatan_input = trim($_POST['jabatan'] ?? '');
    $role_input = trim($_POST['role'] ?? '');
    $id_cabor_input = ($role_input == 'pengurus_cabor' && isset($_POST['id_cabor']) && !empty(trim($_POST['id_cabor']))) ? filter_var($_POST['id_cabor'], FILTER_SANITIZE_NUMBER_INT) : null;
    $tingkat_pengurus_input = trim($_POST['tingkat_pengurus'] ?? 'Kabupaten');
    $is_verified_input = isset($_POST['is_verified']) ? 1 : 0;

    $_SESSION['form_data_anggota_edit'] = $_POST;
    $errors_edit_agt = [];
    $error_fields_edit_agt = [];

    // 5. Validasi Server-Side
    if (empty($jabatan_input)) { $errors_edit_agt[] = "Jabatan wajib diisi."; $error_fields_edit_agt[] = 'jabatan'; }
    
    $allowed_roles_edit = ['admin_koni', 'pengurus_cabor', 'view_only', 'guest', 'super_admin']; // Super admin bisa diedit jika bukan SA utama
    if (empty($role_input) || !in_array($role_input, $allowed_roles_edit)) {
        $errors_edit_agt[] = "Peran Sistem tidak valid."; $error_fields_edit_agt[] = 'role';
    }
    // Mencegah perubahan peran Super Admin utama
    if (defined('NIK_SUPER_ADMIN_UTAMA') && $data_lama_anggota_db['nik'] == NIK_SUPER_ADMIN_UTAMA && $data_lama_anggota_db['role'] == 'super_admin' && $role_input != 'super_admin') {
        $errors_edit_agt[] = "Peran Super Admin utama tidak dapat diubah."; $error_fields_edit_agt[] = 'role';
        $role_input = 'super_admin'; // Paksa kembali
    }
    // Mencegah NIK lain diubah menjadi Super Admin jika sudah ada SA utama (kecuali jika NIK tersebut memang SA utama)
    elseif ($role_input == 'super_admin' && (!defined('NIK_SUPER_ADMIN_UTAMA') || $nik_anggota_form_hidden != NIK_SUPER_ADMIN_UTAMA)) {
        $errors_edit_agt[] = "Peran Super Admin hanya bisa ditetapkan untuk NIK Super Admin Utama."; $error_fields_edit_agt[] = 'role';
    }


    if ($role_input == 'pengurus_cabor' && (empty($id_cabor_input) || !filter_var($id_cabor_input, FILTER_VALIDATE_INT))) {
        $errors_edit_agt[] = "Cabang Olahraga wajib dipilih untuk peran Pengurus Cabor."; $error_fields_edit_agt[] = 'id_cabor';
    }
    if (empty($tingkat_pengurus_input) || !in_array($tingkat_pengurus_input, ['Kabupaten','Provinsi','Pusat'])) {
        $errors_edit_agt[] = "Tingkat Pengurus tidak valid."; $error_fields_edit_agt[] = 'tingkat_pengurus';
    }
    // Super Admin utama tidak bisa di-unverify
    if (defined('NIK_SUPER_ADMIN_UTAMA') && $data_lama_anggota_db['nik'] == NIK_SUPER_ADMIN_UTAMA && $data_lama_anggota_db['role'] == 'super_admin' && $is_verified_input == 0) {
         $errors_edit_agt[] = "Super Admin utama tidak dapat di-unverify."; $error_fields_edit_agt[] = 'is_verified';
         $is_verified_input = 1; // Paksa kembali
    }


    // Cek duplikasi peran (NIK, role, id_cabor) jika ada perubahan signifikan
    if (empty($errors_edit_agt) && ($role_input != $data_lama_anggota_db['role'] || $id_cabor_input != $data_lama_anggota_db['id_cabor'])) {
        try {
            $sql_cek_duplikat_edit = "SELECT id_anggota FROM anggota WHERE nik = :nik AND role = :role";
            $params_cek_edit = [':nik' => $nik_anggota_form_hidden, ':role' => $role_input];
            if ($role_input == 'pengurus_cabor') {
                $sql_cek_duplikat_edit .= " AND id_cabor = :id_cabor";
                $params_cek_edit[':id_cabor'] = $id_cabor_input;
            } elseif (in_array($role_input, ['admin_koni', 'super_admin', 'view_only', 'guest'])) {
                $sql_cek_duplikat_edit .= " AND id_cabor IS NULL";
            }
            $sql_cek_duplikat_edit .= " AND id_anggota != :current_id_anggota"; // Abaikan record yang sedang diedit
            $params_cek_edit[':current_id_anggota'] = $id_anggota_to_edit_proc;

            $stmt_cek_duplikat_edit = $pdo->prepare($sql_cek_duplikat_edit);
            $stmt_cek_duplikat_edit->execute($params_cek_edit);
            if ($stmt_cek_duplikat_edit->fetch()) {
                $errors_edit_agt[] = "Pengguna ini sudah memiliki penetapan peran yang sama persis (atau kombinasi peran & cabor yang sama).";
                $error_fields_edit_agt[] = 'role';
                if ($role_input == 'pengurus_cabor') $error_fields_edit_agt[] = 'id_cabor';
            }
        } catch (PDOException $e) {
            error_log("Proses Edit Anggota - DB Cek Duplikat Error: " . $e->getMessage());
            $errors_edit_agt[] = "Terjadi kesalahan saat memvalidasi duplikasi peran.";
        }
    }

    if (!empty($errors_edit_agt)) {
        $_SESSION['errors_anggota_edit'] = $errors_edit_agt;
        $_SESSION['error_fields_anggota_edit'] = array_unique($error_fields_edit_agt);
        header("Location: edit_anggota.php?id_anggota=" . $id_anggota_to_edit_proc);
        exit();
    }

    // 6. Update ke Database
    try {
        $pdo->beginTransaction();
        $current_timestamp_update_agt = date('Y-m-d H:i:s');

        $verified_by_val_edit_agt = $data_lama_anggota_db['verified_by_nik'];
        $verified_at_val_edit_agt = $data_lama_anggota_db['verified_at'];

        if ($is_verified_input == 1 && $data_lama_anggota_db['is_verified'] == 0) { // Baru diverifikasi
            $verified_by_val_edit_agt = $user_nik_pelaku_proses;
            $verified_at_val_edit_agt = $current_timestamp_update_agt;
        } elseif ($is_verified_input == 0 && $data_lama_anggota_db['is_verified'] == 1) { // Verifikasi dicabut
            $verified_by_val_edit_agt = $user_nik_pelaku_proses; // Catat siapa yang mencabut
            $verified_at_val_edit_agt = $current_timestamp_update_agt; // Catat waktu pencabutan
        }
        // Jika status verifikasi tidak berubah, biarkan nilai verified_by dan verified_at yang lama

        $sql_update_anggota = "UPDATE anggota SET 
                                jabatan = :jabatan, role = :role, id_cabor = :id_cabor, 
                                tingkat_pengurus = :tingkat_pengurus, is_verified = :is_verified, 
                                verified_by_nik = :verified_by_nik, verified_at = :verified_at
                                -- NIK tidak diubah
                            WHERE id_anggota = :id_anggota_where AND nik = :nik_where";
        $stmt_update_agt = $pdo->prepare($sql_update_anggota);
        
        $stmt_update_agt->bindParam(':jabatan', $jabatan_input, PDO::PARAM_STR);
        $stmt_update_agt->bindParam(':role', $role_input, PDO::PARAM_STR);
        $stmt_update_agt->bindParam(':id_cabor', $id_cabor_input, $id_cabor_input === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt_update_agt->bindParam(':tingkat_pengurus', $tingkat_pengurus_input, PDO::PARAM_STR);
        $stmt_update_agt->bindParam(':is_verified', $is_verified_input, PDO::PARAM_INT);
        $stmt_update_agt->bindParam(':verified_by_nik', $verified_by_val_edit_agt, $verified_by_val_edit_agt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_update_agt->bindParam(':verified_at', $verified_at_val_edit_agt, $verified_at_val_edit_agt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_update_agt->bindParam(':id_anggota_where', $id_anggota_to_edit_proc, PDO::PARAM_INT);
        $stmt_update_agt->bindParam(':nik_where', $nik_anggota_form_hidden, PDO::PARAM_STR);
        
        $stmt_update_agt->execute();

        // 7. Audit Log
        $stmt_new_data_agt = $pdo->prepare("SELECT ang.*, p.nama_lengkap FROM anggota ang JOIN pengguna p ON ang.nik = p.nik WHERE ang.id_anggota = :id_anggota");
        $stmt_new_data_agt->bindParam(':id_anggota', $id_anggota_to_edit_proc, PDO::PARAM_INT);
        $stmt_new_data_agt->execute();
        $data_baru_anggota_for_log = $stmt_new_data_agt->fetch(PDO::FETCH_ASSOC);
        
        $keterangan_log_edit_agt = 'Mengubah data peran untuk: ' . htmlspecialchars($data_lama_anggota_db['nik']) . ' (' . htmlspecialchars($data_lama_anggota_db['jabatan'] ?? 'N/A') . ')';
        
        $log_stmt_edit_agt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, data_baru, keterangan) VALUES (:user_nik, :aksi, :tabel, :id_data, :data_lama, :data_baru, :keterangan)");
        $log_stmt_edit_agt->execute([
            ':user_nik' => $user_nik_pelaku_proses,
            ':aksi' => 'EDIT PERAN ANGGOTA',
            ':tabel' => 'anggota',
            ':id_data' => $id_anggota_to_edit_proc,
            ':data_lama' => json_encode($data_lama_anggota_db),
            ':data_baru' => json_encode($data_baru_anggota_for_log),
            ':keterangan' => $keterangan_log_edit_agt
        ]);

        $pdo->commit();
        unset($_SESSION['form_data_anggota_edit']);
        $_SESSION['pesan_sukses_global'] = "Peran anggota untuk " . htmlspecialchars($data_baru_anggota_for_log['nama_lengkap'] ?? $nik_anggota_form_hidden) . " berhasil diperbarui.";
        
        header("Location: daftar_anggota.php");
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("Proses Edit Anggota - DB Execute Error: " . $e->getMessage());
        $_SESSION['errors_anggota_edit'] = ["Terjadi kesalahan teknis saat memperbarui data peran anggota."];
        if (strpos(strtolower($e->getMessage()), 'duplicate entry') !== false && strpos(strtolower($e->getMessage()), 'unik_nik_role_cabor') !== false) {
            $_SESSION['errors_anggota_edit'] = ["Kombinasi NIK, Peran, dan Cabor (jika ada) ini sudah ada untuk entri peran lain."];
        }
        header("Location: edit_anggota.php?id_anggota=" . $id_anggota_to_edit_proc);
        exit();
    }
} else {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau data tidak lengkap untuk memproses perubahan.";
    header("Location: daftar_anggota.php");
    exit();
}
?>