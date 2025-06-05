<?php
// File: reaktorsystem/admin/roles/proses_verifikasi_anggota.php

// 1. Inisialisasi Inti
require_once(__DIR__ . '/../../core/init_core.php');

// 2. Pengecekan Akses & Session (Hanya Super Admin)
if ($user_login_status !== true || $user_role_utama != 'super_admin') {
    $_SESSION['pesan_error_global'] = "Akses ditolak. Operasi ini hanya untuk Super Admin.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}
$user_nik_pelaku_proses = $user_nik; // NIK Super Admin yang melakukan aksi

// Pastikan PDO tersedia
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_global'] = "Koneksi Database Gagal!";
    header("Location: daftar_anggota.php");
    exit();
}

// 3. Validasi Parameter GET
$id_anggota_to_process = $_GET['id_anggota'] ?? null;
$action_to_perform = $_GET['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || 
    !$id_anggota_to_process || !filter_var($id_anggota_to_process, FILTER_VALIDATE_INT) || (int)$id_anggota_to_process <= 0 ||
    !$action_to_perform || !in_array($action_to_perform, ['verify', 'unverify'])) {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau parameter tidak lengkap.";
    header("Location: daftar_anggota.php");
    exit();
}
$id_anggota_to_process = (int)$id_anggota_to_process;

// 4. Ambil Data Anggota Lama (untuk audit dan validasi)
try {
    $stmt_old_data = $pdo->prepare("SELECT ang.id_anggota, ang.nik, ang.jabatan, ang.role, ang.is_verified, p.nama_lengkap 
                                    FROM anggota ang 
                                    JOIN pengguna p ON ang.nik = p.nik
                                    WHERE ang.id_anggota = :id_anggota");
    $stmt_old_data->bindParam(':id_anggota', $id_anggota_to_process, PDO::PARAM_INT);
    $stmt_old_data->execute();
    $data_lama_anggota_db = $stmt_old_data->fetch(PDO::FETCH_ASSOC);

    if (!$data_lama_anggota_db) {
        $_SESSION['pesan_error_global'] = "Peran Anggota dengan ID " . htmlspecialchars($id_anggota_to_process) . " tidak ditemukan.";
        header("Location: daftar_anggota.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Proses Verifikasi Anggota - Gagal ambil data lama: " . $e->getMessage());
    $_SESSION['pesan_error_global'] = "Terjadi kesalahan saat mengambil data peran anggota.";
    header("Location: daftar_anggota.php");
    exit();
}

// 5. Logika Aksi Verifikasi/Pembatalan
$new_is_verified_status = (int)$data_lama_anggota_db['is_verified'];
$new_verified_by_nik = $data_lama_anggota_db['verified_by_nik'];
$new_verified_at = $data_lama_anggota_db['verified_at'];
$current_timestamp_verif = date('Y-m-d H:i:s');
$aksi_log_verif = "";
$pesan_sukses_verif = "";

if ($action_to_perform == 'verify') {
    if ($data_lama_anggota_db['is_verified'] == 1) {
        $_SESSION['pesan_info_global'] = "Peran anggota untuk " . htmlspecialchars($data_lama_anggota_db['nama_lengkap']) . " sudah terverifikasi.";
        header("Location: daftar_anggota.php");
        exit();
    }
    $new_is_verified_status = 1;
    $new_verified_by_nik = $user_nik_pelaku_proses;
    $new_verified_at = $current_timestamp_verif;
    $aksi_log_verif = "VERIFIKASI PERAN ANGGOTA";
    $pesan_sukses_verif = "Peran anggota untuk " . htmlspecialchars($data_lama_anggota_db['nama_lengkap']) . " berhasil diverifikasi.";

} elseif ($action_to_perform == 'unverify') {
    // Super Admin utama tidak bisa di-unverify
    if (defined('NIK_SUPER_ADMIN_UTAMA_ANGGOTA') && $data_lama_anggota_db['nik'] == NIK_SUPER_ADMIN_UTAMA_ANGGOTA && $data_lama_anggota_db['role'] == 'super_admin') {
        $_SESSION['pesan_error_global'] = "Verifikasi peran Super Admin utama tidak dapat dibatalkan.";
        header("Location: daftar_anggota.php");
        exit();
    }
    if ($data_lama_anggota_db['is_verified'] == 0) {
        $_SESSION['pesan_info_global'] = "Peran anggota untuk " . htmlspecialchars($data_lama_anggota_db['nama_lengkap']) . " memang belum terverifikasi.";
        header("Location: daftar_anggota.php");
        exit();
    }
    $new_is_verified_status = 0;
    $new_verified_by_nik = $user_nik_pelaku_proses; // Catat siapa yang membatalkan
    $new_verified_at = $current_timestamp_verif;    // Catat waktu pembatalan
    $aksi_log_verif = "BATALKAN VERIFIKASI PERAN ANGGOTA";
    $pesan_sukses_verif = "Verifikasi peran anggota untuk " . htmlspecialchars($data_lama_anggota_db['nama_lengkap']) . " berhasil dibatalkan.";
}

// 6. Update ke Database
try {
    $pdo->beginTransaction();

    $sql_update_verif = "UPDATE anggota SET 
                            is_verified = :is_verified,
                            verified_by_nik = :verified_by_nik,
                            verified_at = :verified_at
                         WHERE id_anggota = :id_anggota_where";
    $stmt_update_verif = $pdo->prepare($sql_update_verif);
    
    $stmt_update_verif->bindParam(':is_verified', $new_is_verified_status, PDO::PARAM_INT);
    $stmt_update_verif->bindParam(':verified_by_nik', $new_verified_by_nik, $new_verified_by_nik === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt_update_verif->bindParam(':verified_at', $new_verified_at, $new_verified_at === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt_update_verif->bindParam(':id_anggota_where', $id_anggota_to_process, PDO::PARAM_INT);
    
    $stmt_update_verif->execute();

    if ($stmt_update_verif->rowCount() > 0) {
        // 7. Audit Log
        // Ambil data baru setelah update untuk log
        $stmt_new_data_verif = $pdo->prepare("SELECT is_verified, verified_by_nik, verified_at FROM anggota WHERE id_anggota = :id_anggota");
        $stmt_new_data_verif->bindParam(':id_anggota', $id_anggota_to_process, PDO::PARAM_INT);
        $stmt_new_data_verif->execute();
        $data_baru_anggota_for_log = $stmt_new_data_verif->fetch(PDO::FETCH_ASSOC);

        $keterangan_log_verif = $aksi_log_verif . " untuk: " . htmlspecialchars($data_lama_anggota_db['nama_lengkap']) . 
                                " (NIK: " . htmlspecialchars($data_lama_anggota_db['nik']) . 
                                ", Peran: " . htmlspecialchars($data_lama_anggota_db['jabatan']) . 
                                ", ID Anggota: " . $id_anggota_to_process . ").";
        
        $log_stmt_verif = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, data_baru, keterangan) VALUES (:user_nik, :aksi, :tabel, :id_data, :data_lama, :data_baru, :keterangan)");
        $log_stmt_verif->execute([
            ':user_nik' => $user_nik_pelaku_proses,
            ':aksi' => $aksi_log_verif,
            ':tabel' => 'anggota',
            ':id_data' => $id_anggota_to_process,
            ':data_lama' => json_encode(['is_verified' => $data_lama_anggota_db['is_verified'], 'verified_by_nik' => $data_lama_anggota_db['verified_by_nik'], 'verified_at' => $data_lama_anggota_db['verified_at']]),
            ':data_baru' => json_encode($data_baru_anggota_for_log),
            ':keterangan' => $keterangan_log_verif
        ]);

        $pdo->commit();
        $_SESSION['pesan_sukses_global'] = $pesan_sukses_verif;
    } else {
        $pdo->rollBack();
        $_SESSION['pesan_info_global'] = "Tidak ada perubahan status verifikasi untuk peran anggota " . htmlspecialchars($data_lama_anggota_db['nama_lengkap']) . ". Mungkin status sudah sesuai.";
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Proses Verifikasi Anggota - DB Execute Error: " . $e->getMessage());
    $_SESSION['pesan_error_global'] = "Error Database saat memproses verifikasi: Terjadi masalah pada sistem.";
}

// 8. Redirect
header("Location: daftar_anggota.php");
exit();
?>