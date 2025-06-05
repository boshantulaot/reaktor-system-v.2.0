<?php
// File: reaktorsystem/modules/atlet/hapus_atlet.php

// 1. Sertakan init_core.php untuk sesi, DB, dan fungsi global
if (file_exists(__DIR__ . '/../../core/init_core.php')) {
    require_once(__DIR__ . '/../../core/init_core.php');
} else {
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    $_SESSION['pesan_error_global'] = "Kesalahan konfigurasi sistem inti: init_core.php tidak ditemukan.";
    error_log("PROSES_HAPUS_ATLET_FATAL: init_core.php tidak ditemukan.");
    $fallback_redirect = isset($app_base_path) ? rtrim($app_base_path, '/') . '/dashboard.php' : '../../dashboard.php';
    header("Location: " . $fallback_redirect);
    exit();
}

// 2. Pengecekan Akses & Session
if (!isset($user_login_status) || $user_login_status !== true || !isset($user_nik) ||
    !in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    $_SESSION['pesan_error_global'] = "Akses ditolak. Anda tidak memiliki izin untuk menghapus data atlet.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}

// Pastikan $pdo sudah terdefinisi
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_global'] = "Koneksi Database Gagal!";
    header("Location: daftar_atlet.php");
    exit();
}

$id_cabor_asal_redirect_atlet = ''; // Untuk redirect kembali

if (isset($_GET['id_atlet']) && filter_var($_GET['id_atlet'], FILTER_VALIDATE_INT)) {
    $id_atlet_to_delete = (int)$_GET['id_atlet'];

    if ($id_atlet_to_delete <= 0) {
        $_SESSION['pesan_error_global'] = "ID Atlet tidak valid untuk dihapus.";
        header("Location: daftar_atlet.php");
        exit();
    }
    
    // Ambil data lama untuk audit log, path file, dan id_cabor
    $stmt_old_atlet = $pdo->prepare("SELECT a.*, p.nama_lengkap FROM atlet a JOIN pengguna p ON a.nik = p.nik WHERE a.id_atlet = :id_atlet");
    $stmt_old_atlet->bindParam(':id_atlet', $id_atlet_to_delete, PDO::PARAM_INT);
    $stmt_old_atlet->execute();
    $data_lama_atlet = $stmt_old_atlet->fetch(PDO::FETCH_ASSOC);

    if (!$data_lama_atlet) {
        $_SESSION['pesan_error_global'] = "Atlet yang akan dihapus tidak ditemukan.";
        header("Location: daftar_atlet.php");
        exit();
    }

    if (isset($data_lama_atlet['id_cabor'])) {
        $id_cabor_asal_redirect_atlet = '?id_cabor=' . $data_lama_atlet['id_cabor'];
    }
    $nama_atlet_dihapus = $data_lama_atlet['nama_lengkap'] ?? ('NIK ' . $data_lama_atlet['nik']);


    try {
        $pdo->beginTransaction();

        // Lakukan penghapusan dari tabel atlet
        $stmt_delete_atlet = $pdo->prepare("DELETE FROM atlet WHERE id_atlet = :id_atlet");
        $stmt_delete_atlet->bindParam(':id_atlet', $id_atlet_to_delete, PDO::PARAM_INT);
        $stmt_delete_atlet->execute();

        if ($stmt_delete_atlet->rowCount() > 0) {
            // Hapus file-file terkait dari server
            $server_root_path_for_unlink = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/');

            $files_to_delete = [
                $data_lama_atlet['ktp_path'] ?? null,
                $data_lama_atlet['kk_path'] ?? null,
                $data_lama_atlet['pas_foto_path'] ?? null
            ];

            foreach ($files_to_delete as $file_path) {
                if (!empty($file_path)) {
                    $full_path = $server_root_path_for_unlink . '/' . ltrim($file_path, '/');
                    $full_path = preg_replace('/\/+/', '/', $full_path); // Normalisasi slash
                    if (file_exists($full_path)) {
                        @unlink($full_path);
                    }
                }
            }

            // Update jumlah_atlet di tabel cabang_olahraga HANYA JIKA status atlet sebelumnya 'disetujui'
            if ($data_lama_atlet['status_pendaftaran'] == 'disetujui' && !empty($data_lama_atlet['id_cabor'])) {
                $stmt_update_cabor_count = $pdo->prepare("UPDATE cabang_olahraga SET jumlah_atlet = GREATEST(0, jumlah_atlet - 1) WHERE id_cabor = :id_cabor");
                $stmt_update_cabor_count->bindParam(':id_cabor', $data_lama_atlet['id_cabor'], PDO::PARAM_INT);
                $stmt_update_cabor_count->execute();
            }

            // Audit Log
            $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, keterangan) VALUES (:user_nik, 'HAPUS DATA ATLET', 'atlet', :id_data, :data_lama, :keterangan)");
            $log_stmt->execute([
                ':user_nik' => $user_nik, // NIK user yang melakukan aksi
                ':id_data' => $id_atlet_to_delete,
                ':data_lama' => json_encode($data_lama_atlet),
                ':keterangan' => 'Menghapus atlet: ' . $nama_atlet_dihapus . ' (ID Atlet: ' . $id_atlet_to_delete . ')'
            ]);

            $pdo->commit();
            $_SESSION['pesan_sukses_global'] = "Data Atlet '" . htmlspecialchars($nama_atlet_dihapus) . "' berhasil dihapus.";
        } else {
            $pdo->rollBack();
            $_SESSION['pesan_error_global'] = "Gagal menghapus data Atlet. Data mungkin sudah dihapus atau ID tidak valid lagi.";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("PROSES_HAPUS_ATLET_ERROR: " . $e->getMessage());
        // Tidak ada foreign key constraint spesifik untuk atlet yang umumnya menghalangi delete,
        // kecuali jika ada di tabel prestasi dan tidak ON DELETE SET NULL.
        // Jika ada, Anda bisa menambahkan pengecekan $e->getCode() == '23000' seperti di hapus_klub.php
        $_SESSION['pesan_error_global'] = "Terjadi kesalahan database saat menghapus atlet: " . htmlspecialchars($e->getMessage());
    }
    header("Location: daftar_atlet.php" . $id_cabor_asal_redirect_atlet);
    exit();

} else {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau ID Atlet tidak disediakan untuk dihapus.";
    header("Location: daftar_atlet.php");
    exit();
}
?>