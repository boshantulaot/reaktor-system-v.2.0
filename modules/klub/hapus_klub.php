<?php
// File: reaktorsystem/modules/klub/hapus_klub.php

// 1. Sertakan init_core.php untuk sesi, DB, dan fungsi global
require_once(__DIR__ . '/../../core/init_core.php');

// 2. Pengecekan Akses & Session
// (user_nik dan user_role_utama sudah ada dari init_core.php)
if ($user_login_status !== true || !isset($user_nik) ||
    !in_array($user_role_utama, ['super_admin', 'admin_koni'])) { // Hanya Super Admin & Admin KONI yang bisa hapus
    $_SESSION['pesan_error_global'] = "Akses ditolak. Anda tidak memiliki izin untuk menghapus data klub.";
    header("Location: " . rtrim($app_base_path, '/') . "dashboard.php");
    exit();
}

// Pastikan $pdo sudah terdefinisi dari init_core.php
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_global'] = "Koneksi Database Gagal!";
    header("Location: daftar_klub.php");
    exit();
}

$id_cabor_asal_param = ''; // Untuk parameter redirect

if (isset($_GET['id_klub']) && filter_var($_GET['id_klub'], FILTER_VALIDATE_INT)) {
    $id_klub_to_delete = (int)$_GET['id_klub'];

    if ($id_klub_to_delete <= 0) {
        $_SESSION['pesan_error_global'] = "ID Klub tidak valid untuk dihapus.";
        header("Location: daftar_klub.php");
        exit();
    }

    // Ambil data klub yang akan dihapus (termasuk id_cabor untuk redirect dan path file)
    // Lakukan ini SEBELUM transaksi utama untuk mendapatkan info redirect
    $stmt_data_klub = $pdo->prepare("SELECT * FROM klub WHERE id_klub = :id_klub");
    $stmt_data_klub->bindParam(':id_klub', $id_klub_to_delete, PDO::PARAM_INT);
    $stmt_data_klub->execute();
    $data_klub_to_delete = $stmt_data_klub->fetch(PDO::FETCH_ASSOC);

    if (!$data_klub_to_delete) {
        $_SESSION['pesan_error_global'] = "Klub yang akan dihapus tidak ditemukan.";
        header("Location: daftar_klub.php");
        exit();
    }

    if (isset($data_klub_to_delete['id_cabor'])) {
        $id_cabor_asal_param = '?id_cabor=' . $data_klub_to_delete['id_cabor'];
    }

    try {
        $pdo->beginTransaction();

        // Data klub sudah diambil di atas sebagai $data_klub_to_delete

        // Lakukan penghapusan klub
        $stmt_delete_klub = $pdo->prepare("DELETE FROM klub WHERE id_klub = :id_klub");
        $stmt_delete_klub->bindParam(':id_klub', $id_klub_to_delete, PDO::PARAM_INT);
        $stmt_delete_klub->execute();

        if ($stmt_delete_klub->rowCount() > 0) {
            // Hapus file fisik jika ada
            $server_root_path_for_unlink = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/');

            if (!empty($data_klub_to_delete['logo_klub'])) {
                $full_path_logo = $server_root_path_for_unlink . '/' . ltrim($data_klub_to_delete['logo_klub'], '/');
                $full_path_logo = preg_replace('/\/+/', '/', $full_path_logo); // Normalisasi slash
                if (file_exists($full_path_logo)) {
                    @unlink($full_path_logo);
                }
            }
            if (!empty($data_klub_to_delete['path_sk_klub'])) {
                $full_path_sk = $server_root_path_for_unlink . '/' . ltrim($data_klub_to_delete['path_sk_klub'], '/');
                $full_path_sk = preg_replace('/\/+/', '/', $full_path_sk); // Normalisasi slash
                if (file_exists($full_path_sk)) {
                    @unlink($full_path_sk);
                }
            }

            // Update jumlah_klub di tabel cabang_olahraga HANYA JIKA klub yang dihapus statusnya 'disetujui'
            if ($data_klub_to_delete['status_approval_admin'] == 'disetujui') {
                $stmt_update_cabor_count = $pdo->prepare("UPDATE cabang_olahraga SET jumlah_klub = GREATEST(0, jumlah_klub - 1) WHERE id_cabor = :id_cabor");
                $stmt_update_cabor_count->bindParam(':id_cabor', $data_klub_to_delete['id_cabor'], PDO::PARAM_INT);
                $stmt_update_cabor_count->execute();
            }

            // Audit Log
            $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama) VALUES (:user_nik, 'HAPUS KLUB', 'klub', :id_data, :data_lama)");
            $log_stmt->execute([
                ':user_nik' => $user_nik,
                ':id_data' => $id_klub_to_delete,
                ':data_lama' => json_encode($data_klub_to_delete) // Data klub sebelum dihapus
            ]);

            $pdo->commit();
            $_SESSION['pesan_sukses_global'] = "Klub '" . htmlspecialchars($data_klub_to_delete['nama_klub']) . "' berhasil dihapus.";
        } else {
            $pdo->rollBack(); // Rollback jika rowCount() == 0 (misalnya, ID tidak ada saat delete dieksekusi)
            $_SESSION['pesan_error_global'] = "Gagal menghapus Klub. Data mungkin sudah dihapus atau ID tidak valid lagi.";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("PROSES_HAPUS_KLUB_ERROR: " . $e->getMessage());
        if ($e->getCode() == '23000') { // Error foreign key constraint
            $_SESSION['pesan_error_global'] = "Klub '" . htmlspecialchars($data_klub_to_delete['nama_klub'] ?? 'Terpilih') . "' tidak dapat dihapus karena masih memiliki data terkait (misalnya atlet atau pelatih). Harap hapus atau pindahkan data terkait terlebih dahulu.";
        } else {
            $_SESSION['pesan_error_global'] = "Terjadi kesalahan database saat menghapus klub: " . htmlspecialchars($e->getMessage());
        }
    }
    header("Location: daftar_klub.php" . $id_cabor_asal_param);
    exit();

} else {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau ID Klub tidak disediakan untuk dihapus.";
    header("Location: daftar_klub.php");
    exit();
}
?>