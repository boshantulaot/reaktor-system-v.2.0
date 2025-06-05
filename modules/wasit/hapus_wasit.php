<?php
// File: reaktorsystem/modules/wasit/hapus_wasit.php

// Inisialisasi inti (termasuk session_start(), koneksi DB, variabel global, fungsi)
require_once(__DIR__ . '/../../core/init_core.php');

// 1. Pengecekan Akses & Session
// Hanya Super Admin & Admin KONI yang bisa hapus secara permanen
if (!isset($user_nik) || !isset($user_role_utama) || 
    !in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    $_SESSION['pesan_error_global'] = "Akses ditolak. Anda tidak memiliki izin untuk menghapus data wasit.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}

// Pastikan $pdo sudah terdefinisi dari init_core.php
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_global'] = "Koneksi Database Gagal! Tidak dapat melanjutkan proses penghapusan.";
    header("Location: daftar_wasit.php");
    exit();
}

$id_cabor_asal_redirect_w = ''; // Untuk redirect kembali ke halaman daftar wasit cabor spesifik

if (isset($_GET['id_wasit']) && filter_var($_GET['id_wasit'], FILTER_VALIDATE_INT) && (int)$_GET['id_wasit'] > 0) {
    $id_wasit_to_delete = (int)$_GET['id_wasit'];

    try {
        // Ambil data lama untuk audit log, penghapusan file, dan update agregat SEBELUM dihapus
        $stmt_old_w_data = $pdo->prepare("SELECT w.*, p.nama_lengkap FROM wasit w JOIN pengguna p ON w.nik = p.nik WHERE w.id_wasit = :id_wasit");
        $stmt_old_w_data->bindParam(':id_wasit', $id_wasit_to_delete, PDO::PARAM_INT);
        $stmt_old_w_data->execute();
        $data_lama_w_for_delete = $stmt_old_w_data->fetch(PDO::FETCH_ASSOC);

        if ($data_lama_w_for_delete) {
            $id_cabor_wasit_lama_del = $data_lama_w_for_delete['id_cabor'];
            $status_approval_lama_w_del = $data_lama_w_for_delete['status_approval'];
            $nik_wasit_deleted = $data_lama_w_for_delete['nik'];
            $nama_wasit_deleted = $data_lama_w_for_delete['nama_lengkap'];

            if ($id_cabor_wasit_lama_del) {
                 $id_cabor_asal_redirect_w = '?id_cabor=' . $id_cabor_wasit_lama_del;
            }

            $pdo->beginTransaction();

            // Lakukan penghapusan dari tabel wasit
            $stmt_delete_w_db = $pdo->prepare("DELETE FROM wasit WHERE id_wasit = :id_wasit");
            $stmt_delete_w_db->bindParam(':id_wasit', $id_wasit_to_delete, PDO::PARAM_INT);
            $stmt_delete_w_db->execute();

            if ($stmt_delete_w_db->rowCount() > 0) {
                // Hapus file-file terkait dari server
                $base_upload_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/');
                
                // Hapus file dengan aman
                $files_to_delete_w = [
                    $data_lama_w_for_delete['ktp_path'] ?? null,
                    $data_lama_w_for_delete['kk_path'] ?? null,
                    $data_lama_w_for_delete['path_file_lisensi'] ?? null,
                    $data_lama_w_for_delete['foto_wasit'] ?? null
                ];

                foreach ($files_to_delete_w as $file_path_w) {
                    if (!empty($file_path_w)) {
                        $full_file_path_w = $base_upload_path . '/' . ltrim($file_path_w, '/');
                        $normalized_path_w = preg_replace('/\/+/', '/', $full_file_path_w); // Normalisasi path
                        if (file_exists($normalized_path_w)) {
                            @unlink($normalized_path_w);
                        }
                    }
                }

                // Update jumlah_wasit di tabel cabang_olahraga HANYA JIKA status wasit sebelumnya 'disetujui'
                if ($status_approval_lama_w_del == 'disetujui' && $id_cabor_wasit_lama_del) {
                    $stmt_update_cabor_count_w = $pdo->prepare("UPDATE cabang_olahraga SET jumlah_wasit = GREATEST(0, jumlah_wasit - 1) WHERE id_cabor = :id_cabor");
                    $stmt_update_cabor_count_w->bindParam(':id_cabor', $id_cabor_wasit_lama_del, PDO::PARAM_INT);
                    $stmt_update_cabor_count_w->execute();
                }

                // Audit Log
                $keterangan_log_del_w = "Menghapus data Wasit: '" . htmlspecialchars($nama_wasit_deleted) . "' (NIK: " . htmlspecialchars($nik_wasit_deleted) . ", ID Wasit: " . $id_wasit_to_delete . ").";
                $log_stmt_del_w = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, keterangan) VALUES (:un, :a, :t, :id, :dl, :ket)");
                $log_stmt_del_w->execute([
                    'un' => $user_nik, 
                    'a' => 'HAPUS DATA WASIT', 
                    't' => 'wasit', 
                    'id' => $id_wasit_to_delete, 
                    'dl' => json_encode($data_lama_w_for_delete),
                    'ket' => $keterangan_log_del_w
                ]);

                $pdo->commit();
                $_SESSION['pesan_sukses_global'] = "Data Wasit '" . htmlspecialchars($nama_wasit_deleted) . "' (NIK: " . htmlspecialchars($nik_wasit_deleted) . ") berhasil dihapus.";
            } else {
                $pdo->rollBack();
                $_SESSION['pesan_error_global'] = "Gagal menghapus data Wasit atau data tidak ditemukan saat akan dihapus (ID: " . htmlspecialchars($id_wasit_to_delete) . ").";
            }
        } else {
            // Tidak perlu rollBack jika transaksi belum dimulai
            $_SESSION['pesan_error_global'] = "Data Wasit yang akan dihapus tidak ditemukan (ID: " . htmlspecialchars($id_wasit_to_delete) . ").";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("Proses Hapus Wasit - Error DB: " . $e->getMessage());
        if ($e->getCode() == '23000') { 
             $_SESSION['pesan_error_global'] = "Wasit tidak dapat dihapus karena masih memiliki data penting terkait di sistem (misalnya, data prestasi atau penugasan). Harap periksa relasi data.";
        } else {
            $_SESSION['pesan_error_global'] = "Error Database saat menghapus data wasit. Silakan coba lagi atau hubungi administrator.";
        }
    }
    header("Location: daftar_wasit.php" . $id_cabor_asal_redirect_w);
    exit();
} else {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau ID Wasit tidak disediakan untuk dihapus.";
    header("Location: daftar_wasit.php");
    exit();
}
?>