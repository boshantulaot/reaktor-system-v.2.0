<?php
// File: reaktorsystem/modules/prestasi/hapus_prestasi.php

// 1. Inisialisasi Inti
require_once(__DIR__ . '/../../core/init_core.php');

// 2. Pengecekan Akses & Session
if ($user_login_status !== true || !in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    $_SESSION['pesan_error_global'] = "Akses ditolak. Anda tidak memiliki izin untuk menghapus data prestasi.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}
$user_nik_pelaku_hapus = $user_nik;

// Pastikan PDO tersedia
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_global'] = "Koneksi Database Gagal!";
    header("Location: daftar_prestasi.php");
    exit();
}

$id_prestasi_to_delete = null;
$id_cabor_asal_redirect = null;
$nik_atlet_asal_redirect = null;

// 3. Validasi ID Prestasi dari GET Request
if (isset($_GET['id_prestasi']) && filter_var($_GET['id_prestasi'], FILTER_VALIDATE_INT) && (int)$_GET['id_prestasi'] > 0) {
    $id_prestasi_to_delete = (int)$_GET['id_prestasi'];

    // 4. Ambil Data Prestasi yang Akan Dihapus (untuk audit dan path file)
    try {
        $stmt_get_data = $pdo->prepare("SELECT * FROM prestasi WHERE id_prestasi = :id_prestasi");
        $stmt_get_data->bindParam(':id_prestasi', $id_prestasi_to_delete, PDO::PARAM_INT);
        $stmt_get_data->execute();
        $data_prestasi_to_delete = $stmt_get_data->fetch(PDO::FETCH_ASSOC);

        if (!$data_prestasi_to_delete) {
            $_SESSION['pesan_error_global'] = "Data prestasi yang akan dihapus tidak ditemukan.";
            header("Location: daftar_prestasi.php");
            exit();
        }
        // Simpan info untuk redirect sebelum data dihapus
        $id_cabor_asal_redirect = $data_prestasi_to_delete['id_cabor'];
        $nik_atlet_asal_redirect = $data_prestasi_to_delete['nik'];

    } catch (PDOException $e) {
        error_log("Hapus Prestasi - Gagal ambil data untuk dihapus: " . $e->getMessage());
        $_SESSION['pesan_error_global'] = "Terjadi kesalahan saat memproses permintaan penghapusan.";
        header("Location: daftar_prestasi.php");
        exit();
    }

    // 5. Proses Penghapusan
    try {
        $pdo->beginTransaction();

        // Hapus record dari tabel prestasi
        $stmt_delete = $pdo->prepare("DELETE FROM prestasi WHERE id_prestasi = :id_prestasi");
        $stmt_delete->bindParam(':id_prestasi', $id_prestasi_to_delete, PDO::PARAM_INT);
        $stmt_delete->execute();

        if ($stmt_delete->rowCount() > 0) {
            // 6. Hapus File Bukti dari Server (jika ada)
            if (!empty($data_prestasi_to_delete['bukti_path'])) {
                $doc_root_delete = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
                $base_path_delete = rtrim($app_base_path, '/');
                $file_bukti_to_delete_server_path = $doc_root_delete . $base_path_delete . '/' . ltrim($data_prestasi_to_delete['bukti_path'], '/');
                $file_bukti_to_delete_server_path = preg_replace('/\/+/', '/', $file_bukti_to_delete_server_path);
                
                if (file_exists($file_bukti_to_delete_server_path)) {
                    if (!@unlink($file_bukti_to_delete_server_path)) {
                        error_log("Hapus Prestasi - Gagal hapus file bukti: " . $file_bukti_to_delete_server_path);
                        // Tidak menghentikan proses jika gagal hapus file, tapi catat error
                    }
                }
            }

            // 7. Audit Log
            $aksi_log_hapus_prestasi = "HAPUS DATA PRESTASI";
            $keterangan_log = "Menghapus prestasi '" . htmlspecialchars($data_prestasi_to_delete['nama_kejuaraan']) . "' (ID: " . $id_prestasi_to_delete . ") untuk atlet NIK: " . htmlspecialchars($data_prestasi_to_delete['nik']);
            
            $log_stmt_hapus = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, keterangan) VALUES (:user_nik, :aksi, :tabel, :id_data, :data_lama, :keterangan)");
            $log_stmt_hapus->execute([
                ':user_nik' => $user_nik_pelaku_hapus,
                ':aksi' => $aksi_log_hapus_prestasi,
                ':tabel' => 'prestasi',
                ':id_data' => $id_prestasi_to_delete,
                ':data_lama' => json_encode($data_prestasi_to_delete), // Simpan semua data yang dihapus
                ':keterangan' => $keterangan_log
            ]);

            $pdo->commit();
            $_SESSION['pesan_sukses_global'] = "Data prestasi '" . htmlspecialchars($data_prestasi_to_delete['nama_kejuaraan']) . "' berhasil dihapus.";
        } else {
            $pdo->rollBack();
            $_SESSION['pesan_error_global'] = "Gagal menghapus data prestasi. Data mungkin sudah dihapus atau ID tidak valid.";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("Hapus Prestasi - DB Execute Error: " . $e->getMessage());
        $_SESSION['pesan_error_global'] = "Error Database saat menghapus prestasi: Terjadi masalah pada sistem.";
    }

    // 8. Redirect
    $redirect_url_hapus_params = [];
    if ($id_cabor_asal_redirect) $redirect_url_hapus_params['id_cabor'] = $id_cabor_asal_redirect;
    if ($nik_atlet_asal_redirect) $redirect_url_hapus_params['nik_atlet'] = $nik_atlet_asal_redirect;
    $query_string_hapus = !empty($redirect_url_hapus_params) ? '?' . http_build_query($redirect_url_hapus_params) : '';
    
    header("Location: daftar_prestasi.php" . $query_string_hapus);
    exit();

} else {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau ID Prestasi tidak disediakan untuk dihapus.";
    header("Location: daftar_prestasi.php");
    exit();
}
?>