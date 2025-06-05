<?php
// File: reaktorsystem/admin/users/proses_approve_pengguna.php

// 1. Inisialisasi Inti
require_once(__DIR__ . '/../../core/init_core.php');

// 2. Pengecekan Akses & Session
if ($user_login_status !== true || !in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    $_SESSION['pesan_error_global'] = "Akses ditolak. Anda tidak memiliki izin untuk memproses status pengguna.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}
$user_nik_pelaku_proses = $user_nik;

if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_global'] = "Koneksi Database Gagal!";
    header("Location: daftar_pengguna.php");
    exit();
}

// 3. Ambil dan Validasi Parameter GET
$nik_to_process = $_GET['nik'] ?? null;
$action_to_perform = $_GET['action'] ?? null;

if (!$nik_to_process || !preg_match('/^\d{1,16}$/', $nik_to_process) || !$action_to_perform || !in_array($action_to_perform, ['approve', 'suspend'])) {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau NIK pengguna tidak disediakan dengan benar.";
    header("Location: daftar_pengguna.php");
    exit();
}

// Super Admin tidak bisa menangguhkan akunnya sendiri, Admin KONI tidak bisa approve/suspend diri sendiri.
if ($nik_to_process == $user_nik_pelaku_proses && !($user_role_utama == 'super_admin' && $action_to_perform == 'approve')) {
    // Super Admin bisa approve dirinya sendiri jika sebelumnya pending (skenario langka)
    // tapi tidak bisa suspend dirinya sendiri. Admin KONI tidak bisa keduanya pada diri sendiri.
    if (!($user_role_utama == 'super_admin' && $action_to_perform == 'approve')) {
        $_SESSION['pesan_error_global'] = "Anda tidak dapat mengubah status approval akun Anda sendiri dengan cara ini.";
        header("Location: daftar_pengguna.php");
        exit();
    }
}


try {
    // 4. Ambil Data Pengguna Lama
    $stmt_old_data = $pdo->prepare("SELECT nik, nama_lengkap, email, is_approved FROM pengguna WHERE nik = :nik");
    $stmt_old_data->bindParam(':nik', $nik_to_process, PDO::PARAM_STR);
    $stmt_old_data->execute();
    $data_lama_pengguna_db = $stmt_old_data->fetch(PDO::FETCH_ASSOC);

    if (!$data_lama_pengguna_db) {
        $_SESSION['pesan_error_global'] = "Pengguna dengan NIK " . htmlspecialchars($nik_to_process) . " tidak ditemukan.";
        header("Location: daftar_pengguna.php");
        exit();
    }

    $new_is_approved_status = (int)$data_lama_pengguna_db['is_approved'];
    $aksi_log_detail = "";
    $pesan_sukses = "";

    if ($action_to_perform == 'approve') {
        if ($data_lama_pengguna_db['is_approved'] == 1) {
            $_SESSION['pesan_info_global'] = "Pengguna " . htmlspecialchars($data_lama_pengguna_db['nama_lengkap']) . " sudah disetujui sebelumnya.";
            header("Location: daftar_pengguna.php");
            exit();
        }
        $new_is_approved_status = 1;
        $aksi_log_detail = "SETUJUI AKUN PENGGUNA";
        $pesan_sukses = "Pengguna " . htmlspecialchars($data_lama_pengguna_db['nama_lengkap']) . " berhasil disetujui.";
    } elseif ($action_to_perform == 'suspend') {
        if ($data_lama_pengguna_db['is_approved'] == 0) {
            $_SESSION['pesan_info_global'] = "Pengguna " . htmlspecialchars($data_lama_pengguna_db['nama_lengkap']) . " sudah dalam status pending/ditangguhkan.";
            header("Location: daftar_pengguna.php");
            exit();
        }
        $new_is_approved_status = 0;
        $aksi_log_detail = "TANGGUHKAN AKUN PENGGUNA";
        $pesan_sukses = "Akun pengguna " . htmlspecialchars($data_lama_pengguna_db['nama_lengkap']) . " berhasil ditangguhkan.";
    }

    // 5. Update Status Pengguna
    $pdo->beginTransaction();

    $sql_update_status = "UPDATE pengguna SET is_approved = :is_approved, updated_at = NOW() WHERE nik = :nik";
    $stmt_update = $pdo->prepare($sql_update_status);
    $stmt_update->bindParam(':is_approved', $new_is_approved_status, PDO::PARAM_INT);
    $stmt_update->bindParam(':nik', $nik_to_process, PDO::PARAM_STR);
    $stmt_update->execute();

    if ($stmt_update->rowCount() > 0) {
        // 6. Audit Log
        // Ambil data baru setelah update untuk perbandingan yang akurat di log
        $stmt_new_data_log = $pdo->prepare("SELECT nik, nama_lengkap, email, is_approved FROM pengguna WHERE nik = :nik");
        $stmt_new_data_log->bindParam(':nik', $nik_to_process, PDO::PARAM_STR);
        $stmt_new_data_log->execute();
        $data_baru_pengguna_for_log = $stmt_new_data_log->fetch(PDO::FETCH_ASSOC);

        $keterangan_log = $aksi_log_detail . " untuk " . htmlspecialchars($data_lama_pengguna_db['nama_lengkap']) . " (NIK: " . htmlspecialchars($nik_to_process) . ").";

        $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, data_baru, keterangan) VALUES (:user_nik, :aksi, :tabel, :id_data, :data_lama, :data_baru, :keterangan)");
        $log_stmt->execute([
            ':user_nik' => $user_nik_pelaku_proses,
            ':aksi' => $aksi_log_detail,
            ':tabel' => 'pengguna',
            ':id_data' => $nik_to_process,
            ':data_lama' => json_encode($data_lama_pengguna_db), // Hanya field yang relevan
            ':data_baru' => json_encode($data_baru_pengguna_for_log), // Hanya field yang relevan
            ':keterangan' => $keterangan_log
        ]);

        $pdo->commit();
        $_SESSION['pesan_sukses_global'] = $pesan_sukses;
    } else {
        $pdo->rollBack();
        $_SESSION['pesan_info_global'] = "Tidak ada perubahan status untuk NIK " . htmlspecialchars($nik_to_process) . ". Mungkin status sudah sesuai.";
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Proses Approve/Suspend Pengguna - DB Error: " . $e->getMessage());
    $_SESSION['pesan_error_global'] = "Terjadi kesalahan database saat memproses permintaan.";
}

// 7. Redirect
header("Location: daftar_pengguna.php");
exit();
?>