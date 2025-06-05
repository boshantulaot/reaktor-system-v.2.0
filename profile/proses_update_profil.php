<?php
// File: public_html/reaktorsystem/profile/proses_update_profil.php

// Aktifkan error reporting untuk debugging tahap ini
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sertakan file inisialisasi inti
// init_core.php akan menangani session_start(), koneksi DB, $app_base_path, $user_nik, dll.
if (file_exists(__DIR__ . '/../core/init_core.php')) {
    require_once(__DIR__ . '/../core/init_core.php');
} else {
    session_start(); // Darurat jika init_core tidak ada
    $_SESSION['pesan_error_profil'] = "Kesalahan sistem inti. Tidak dapat memproses permintaan.";
    header("Location: profil_saya.php#editdata"); // Coba redirect ke tab yang relevan
    exit();
}

// Pengecekan Akses & Sesi (variabel dari init_core.php)
if (!isset($user_login_status) || $user_login_status !== true || !isset($user_nik)) {
    $_SESSION['pesan_error_global'] = "Akses ditolak. Silakan login terlebih dahulu.";
    $login_path = isset($app_base_path) ? rtrim($app_base_path, '/') . '/auth/login.php' : '../auth/login.php';
    header("Location: " . $login_path);
    exit();
}

// Pastikan $pdo sudah terdefinisi dari init_core.php
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_profil'] = "Koneksi Database Gagal saat akan update profil!";
    header("Location: profil_saya.php#editdata");
    exit();
}

// Definisikan konstanta ukuran file di sini jika belum ada di init_core.php (tapi sebaiknya di init_core.php)
if (!defined('MAX_FILE_SIZE_FOTO_PROFIL_MB')) { define('MAX_FILE_SIZE_FOTO_PROFIL_MB', 2); } // Contoh 2MB
if (!defined('MAX_FILE_SIZE_FOTO_PROFIL_BYTES')) { define('MAX_FILE_SIZE_FOTO_PROFIL_BYTES', MAX_FILE_SIZE_FOTO_PROFIL_MB * 1024 * 1024); }


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_update_pribadi']) && isset($_POST['nik_profil'])) {
    $nik_profil_to_update = trim($_POST['nik_profil']);

    if ($nik_profil_to_update !== $user_nik) { // Gunakan $user_nik dari sesi
        $_SESSION['pesan_error_profil'] = "Operasi tidak diizinkan (percobaan update profil pengguna lain).";
        header("Location: profil_saya.php#editdata");
        exit();
    }

    $data_lama_user = null;
    try {
        $stmt_old_user = $pdo->prepare("SELECT * FROM pengguna WHERE nik = :nik");
        $stmt_old_user->bindParam(':nik', $nik_profil_to_update, PDO::PARAM_STR);
        $stmt_old_user->execute();
        $data_lama_user = $stmt_old_user->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("PROSES_UPDATE_PROFIL_ERROR: Gagal ambil data lama pengguna NIK {$nik_profil_to_update}. Pesan: " . $e->getMessage());
        $_SESSION['pesan_error_profil'] = "Gagal memuat data pengguna yang ada.";
        header("Location: profil_saya.php#editdata");
        exit();
    }
    
    if (!$data_lama_user) {
        $_SESSION['pesan_error_profil'] = "Data pengguna untuk NIK " . htmlspecialchars($nik_profil_to_update) . " tidak ditemukan.";
        header("Location: profil_saya.php#editdata");
        exit();
    }

    $nama_lengkap_baru = trim($_POST['nama_lengkap'] ?? '');
    $email_baru = trim(strtolower($_POST['email'] ?? '')); // Email biasanya case-insensitive
    $nomor_telepon_baru = trim($_POST['nomor_telepon'] ?? '');
    $alamat_baru = trim($_POST['alamat'] ?? '');

    $foto_profil_db_path_final = $data_lama_user['foto']; 
    $errors_profil = []; 
    $error_fields_profil = [];

    // Validasi Server Side
    if (empty($nama_lengkap_baru)) {
        $errors_profil[] = "Nama Lengkap wajib diisi.";
        $error_fields_profil[] = 'nama_lengkap';
    }
    if (empty($email_baru)) {
        $errors_profil[] = "Email wajib diisi.";
        $error_fields_profil[] = 'email';
    } elseif (!filter_var($email_baru, FILTER_VALIDATE_EMAIL)) {
        $errors_profil[] = "Format email tidak valid.";
        $error_fields_profil[] = 'email';
    } elseif ($email_baru !== $data_lama_user['email']) {
        try {
            $stmt_cek_email = $pdo->prepare("SELECT COUNT(*) FROM pengguna WHERE email = :email AND nik != :nik");
            $stmt_cek_email->bindParam(':email', $email_baru);
            $stmt_cek_email->bindParam(':nik', $nik_profil_to_update);
            $stmt_cek_email->execute();
            if ($stmt_cek_email->fetchColumn() > 0) {
                $errors_profil[] = "Email '" . htmlspecialchars($email_baru) . "' sudah digunakan oleh pengguna lain.";
                $error_fields_profil[] = 'email';
            }
        } catch (PDOException $e) {
            error_log("PROSES_UPDATE_PROFIL_ERROR: Gagal cek duplikasi email. Pesan: " . $e->getMessage());
            $errors_profil[] = "Terjadi kesalahan saat validasi email. Coba lagi.";
        }
    }
    if (!empty($nomor_telepon_baru) && !preg_match('/^[0-9\-\+\s\(\)]*$/', $nomor_telepon_baru)) {
        $errors_profil[] = "Format nomor telepon tidak valid.";
        $error_fields_profil[] = 'nomor_telepon';
    }

    // Proses Upload Foto jika tidak ada error validasi awal dan file diupload
    if (empty($errors_profil) && isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == UPLOAD_ERR_OK && $_FILES['foto_profil']['size'] > 0) {
        // Fungsi uploadFileGeneral dari init_core.php
        $foto_profil_db_path_final = uploadFileGeneral(
            'foto_profil',                      // Nama input file dari form
            'foto_profil',                      // Subdirektori di dalam 'assets/uploads/'
            'profil_' . $nik_profil_to_update,  // Prefix untuk nama file baru
            ['jpg', 'jpeg', 'png', 'gif'],      // Ekstensi yang diizinkan
            MAX_FILE_SIZE_FOTO_PROFIL_MB,       // Ukuran maksimal dalam MB (dari konstanta)
            $errors_profil,                     // Array untuk menampung pesan error dari fungsi upload
            $data_lama_user['foto']             // Path foto lama untuk dihapus jika upload baru berhasil
        );
        if ($foto_profil_db_path_final === false) { // uploadFileGeneral mengembalikan false jika error
            $error_fields_profil[] = 'foto_profil'; // Tandai field foto sebagai error
        }
    }

    if (!empty($errors_profil)) {
        $_SESSION['pesan_error_profil'] = implode("<br>", array_map('htmlspecialchars', $errors_profil));
        $_SESSION['form_data_profil_edit'] = $_POST; // Simpan input form untuk repopulate
        $_SESSION['error_fields_profil_edit'] = array_unique($error_fields_profil);
        header("Location: profil_saya.php#editdata");
        exit();
    }

    try {
        $pdo->beginTransaction(); 

        $sql_update_profil_db = "UPDATE pengguna SET
                        nama_lengkap = :nama_lengkap,
                        email = :email,
                        nomor_telepon = :nomor_telepon,
                        alamat = :alamat,
                        foto = :foto_baru 
                      WHERE nik = :nik_where";
        $stmt_update_db = $pdo->prepare($sql_update_profil_db);

        $stmt_update_db->bindParam(':nama_lengkap', $nama_lengkap_baru);
        $stmt_update_db->bindParam(':email', $email_baru);
        $stmt_update_db->bindParam(':nomor_telepon', $nomor_telepon_baru, $nomor_telepon_baru === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_update_db->bindParam(':alamat', $alamat_baru, $alamat_baru === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_update_db->bindParam(':foto_baru', $foto_profil_db_path_final, ($foto_profil_db_path_final === null || $foto_profil_db_path_final === '') ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_update_db->bindParam(':nik_where', $nik_profil_to_update, PDO::PARAM_STR);

        $stmt_update_db->execute();

        $_SESSION['nama_pengguna'] = $nama_lengkap_baru;
        if ($foto_profil_db_path_final !== $data_lama_user['foto']) {
            $_SESSION['user_foto'] = $foto_profil_db_path_final; 
        }

        // Audit Log
        $data_baru_user_log_raw = ['nama_lengkap' => $nama_lengkap_baru, 'email' => $email_baru, 'nomor_telepon' => $nomor_telepon_baru, 'alamat' => $alamat_baru, 'foto' => $foto_profil_db_path_final];
        $log_keterangan = "Memperbarui profil pribadi untuk NIK: " . htmlspecialchars($nik_profil_to_update);

        $log_stmt_profil = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, data_baru, keterangan) VALUES (:un, :a, :t, :id, :dl, :db, :ket)");
        $log_stmt_profil->execute([
            ':un' => $user_nik, 
            ':a' => 'UPDATE PROFIL PRIBADI', 
            ':t' => 'pengguna', 
            ':id' => $nik_profil_to_update, 
            ':dl' => json_encode($data_lama_user), // Simpan semua data lama untuk perbandingan
            ':db' => json_encode($data_baru_user_log_raw),
            ':ket' => $log_keterangan
        ]);
        
        $pdo->commit(); 

        $_SESSION['pesan_sukses_profil'] = "Profil Anda berhasil diperbarui.";
        unset($_SESSION['form_data_profil_edit']); // Hapus data form dari sesi setelah sukses
        header("Location: profil_saya.php#editdata");
        exit();

    } catch (PDOException $e) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack(); 
        }
        // Hapus foto baru jika DB gagal
        if ($foto_profil_db_path_final !== $data_lama_user['foto'] && !empty($foto_profil_db_path_final)) {
            $full_path_to_unlink = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . '/' . ltrim($foto_profil_db_path_final, '/');
            if (file_exists(preg_replace('/\/+/', '/', $full_path_to_unlink))) {
                @unlink(preg_replace('/\/+/', '/', $full_path_to_unlink));
                 error_log("PROSES_UPDATE_PROFIL_INFO: Foto '{$foto_profil_db_path_final}' dihapus karena DB update gagal.");
            }
        }
        error_log("PROSES_UPDATE_PROFIL_ERROR (Database): NIK {$nik_profil_to_update}. Pesan: " . $e->getMessage());
        $_SESSION['pesan_error_profil'] = "Error Database saat update profil. Silakan coba lagi.";
        header("Location: profil_saya.php#editdata");
        exit();
    }

} else {
    $_SESSION['pesan_error_profil'] = "Aksi tidak valid atau data tidak lengkap untuk update profil.";
    header("Location: profil_saya.php#editdata");
    exit();
}
?>