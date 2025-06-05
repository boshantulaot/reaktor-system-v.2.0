<?php
// File: public_html/reaktorsystem/core/audit_helper.php

if (!defined('INIT_CORE_LOADED')) {
    // Mencegah akses langsung ke file ini jika init_core.php belum dimuat
    // Meskipun jika di-include dari init_core, ini mungkin tidak terlalu krusial, tapi praktik yang baik.
    die("Direct access to this file is not permitted.");
}

/**
 * Mencatat aktivitas pengguna ke dalam audit log.
 *
 * @param PDO $pdo Instance koneksi PDO yang sudah ada.
 * @param string|null $user_nik NIK pengguna yang melakukan aksi. Bisa null untuk aksi sistem.
 * @param string $aksi Deskripsi aksi yang dilakukan (misalnya, 'TAMBAH_CABOR', 'LOGIN_GAGAL').
 * @param string|null $tabel_diubah Nama tabel database yang terpengaruh.
 * @param mixed $id_data_diubah ID dari record yang diubah/ditambah/dihapus. Bisa string atau integer.
 * @param string|null $data_lama_json Data lama dalam format JSON string (untuk aksi EDIT atau HAPUS). Null jika TAMBAH.
 * @param string|null $data_baru_json Data baru dalam format JSON string (untuk aksi TAMBAH atau EDIT). Null jika HAPUS.
 * @param string|null $keterangan Catatan tambahan mengenai aksi tersebut.
 * @return bool True jika berhasil mencatat, false jika gagal.
 */
if (!function_exists('catatAuditLog')) {
    function catatAuditLog(PDO $pdo, $user_nik, string $aksi, $tabel_diubah = null, $id_data_diubah = null, $data_lama_json = null, $data_baru_json = null, $keterangan = null) {
        try {
            $sql = "INSERT INTO audit_log
                        (user_nik, waktu_aksi, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, data_baru, keterangan)
                    VALUES
                        (:user_nik, NOW(), :aksi, :tabel_diubah, :id_data_diubah, :data_lama, :data_baru, :keterangan)";
            
            $stmt = $pdo->prepare($sql);

            $stmt->bindParam(':user_nik', $user_nik, $user_nik === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':aksi', $aksi, PDO::PARAM_STR);
            $stmt->bindParam(':tabel_diubah', $tabel_diubah, $tabel_diubah === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            
            // Mengasumsikan id_data_diubah bisa berupa string atau integer
            $stmt->bindParam(':id_data_diubah', $id_data_diubah, $id_data_diubah === null ? PDO::PARAM_NULL : PDO::PARAM_STR); 
            
            $stmt->bindParam(':data_lama', $data_lama_json, $data_lama_json === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':data_baru', $data_baru_json, $data_baru_json === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':keterangan', $keterangan, $keterangan === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            
            return $stmt->execute();

        } catch (PDOException $e) {
            $log_message = "AUDIT_LOG_PDO_ERROR: Gagal mencatat audit. Pesan: " . $e->getMessage();
            $log_message .= " | Input: user_nik={$user_nik}, aksi={$aksi}, tabel={$tabel_diubah}, id_data={$id_data_diubah}";
            error_log($log_message);
            
            // Di lingkungan development, mungkin ingin menampilkan error atau detail lebih lanjut
            // if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            //     // trigger_error("Gagal mencatat audit log (PDO): " . $e->getMessage(), E_USER_WARNING);
            // }
            return false;
        } catch (Exception $e) { // Menangkap exception umum lainnya
            error_log("AUDIT_LOG_EXCEPTION: Gagal mencatat audit. Pesan: " . $e->getMessage());
            return false;
        }
    }
}

// JANGAN ADA TAG PHP PENUTUP DI AKHIR FILE INI JIKA INI ADALAH FILE YANG HANYA BERISI KODE PHP
// JANGAN ADA SPASI ATAU TEKS APAPUN SETELAH BARIS INI