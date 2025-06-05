<?php
// File: reaktorsystem/admin/users/proses_edit_pengguna.php

// 1. Inisialisasi Inti
require_once(__DIR__ . '/../../core/header.php'); // header.php sudah me-require init_core.php

// 2. Pengecekan Akses & Session
if (!isset($user_login_status) || $user_login_status !== true || 
    !isset($user_role_utama) || !in_array($user_role_utama, ['super_admin', 'admin_koni']) ||
    !isset($user_nik) || !isset($app_base_path) || !isset($pdo) || !$pdo instanceof PDO ||
    !defined('MAX_FILE_SIZE_FOTO_PROFIL_BYTES') || !isset($default_avatar_path_relative)) {
    
    if (!isset($_SESSION['pesan_error_global'])) {
        $_SESSION['pesan_error_global'] = "Akses ditolak, sesi tidak valid, atau konfigurasi inti sistem bermasalah.";
    }
    $fallback_login_url_proses_edit = (defined('APP_URL_BASE') ? rtrim(APP_URL_BASE, '/') : rtrim($app_base_path ?? '/', '/')) . "/auth/login.php?reason=invalid_session_or_config_pep";
    if (!headers_sent()) { header("Location: " . $fallback_login_url_proses_edit); }
    else { echo "<script type='text/javascript'>window.location.href = '" . htmlspecialchars($fallback_login_url_proses_edit, ENT_QUOTES, 'UTF-8') . "';</script><noscript><p>Error: Sesi tidak valid. <a href='" . htmlspecialchars($fallback_login_url_proses_edit, ENT_QUOTES, 'UTF-8') . "'>Login ulang</a>.</p></noscript>"; }
    exit();
}
$user_nik_pelaku_proses_edit = $user_nik;
$daftar_pengguna_redirect_url = "daftar_pengguna.php"; // URL relatif untuk kembali ke daftar


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_edit_pengguna']) && isset($_POST['nik_original']) && !empty(trim($_POST['nik_original']))) {
    $nik_to_edit_val = trim($_POST['nik_original']);

    // 3. Ambil Data Pengguna Lama dari Database
    try {
        $stmt_old_data = $pdo->prepare("SELECT * FROM pengguna WHERE nik = :nik_val_param");
        $stmt_old_data->bindParam(':nik_val_param', $nik_to_edit_val, PDO::PARAM_STR);
        $stmt_old_data->execute();
        $data_lama_pengguna_from_db = $stmt_old_data->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e_fetch_old) {
        error_log("Proses Edit Pengguna - Gagal ambil data lama pengguna: " . $e_fetch_old->getMessage());
        $_SESSION['pesan_error_global'] = "Gagal mengambil data pengguna yang akan diedit.";
        header("Location: " . $daftar_pengguna_redirect_url);
        exit();
    }

    if (!$data_lama_pengguna_from_db) {
        $_SESSION['pesan_error_global'] = "Pengguna dengan NIK " . htmlspecialchars($nik_to_edit_val) . " tidak ditemukan untuk diedit.";
        header("Location: " . $daftar_pengguna_redirect_url);
        exit();
    }

    // 4. Ambil dan Sanitasi Data Baru dari Form
    $nama_lengkap_form_input = trim($_POST['nama_lengkap'] ?? '');
    $email_form_input = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
    $password_plain_form_input = $_POST['password'] ?? '';
    $konfirmasi_password_form_input = $_POST['konfirmasi_password'] ?? '';
    $tanggal_lahir_form_input = trim($_POST['tanggal_lahir'] ?? '');
    $jenis_kelamin_form_input = trim($_POST['jenis_kelamin'] ?? '');
    $alamat_form_input = trim($_POST['alamat'] ?? '');
    $nomor_telepon_form_input = trim($_POST['nomor_telepon'] ?? '');
    $hapus_foto_current_flag = isset($_POST['hapus_foto_current']) && $_POST['hapus_foto_current'] == '1';
    
    $is_approved_form_input = $data_lama_pengguna_from_db['is_approved']; 
    if ($user_role_utama == 'super_admin' && $nik_to_edit_val != $user_nik_pelaku_proses_edit && isset($_POST['is_approved'])) {
        $is_approved_form_input = ($_POST['is_approved'] === '1' || $_POST['is_approved'] === 1) ? 1 : 0;
    }

    $_SESSION['form_data_pengguna_edit'] = $_POST;
    $errors_edit_pgn_arr = [];
    $error_fields_edit_pgn_arr = [];

    // 5. Validasi Server-Side
    if (empty($nama_lengkap_form_input)) { $errors_edit_pgn_arr[] = "Nama Lengkap wajib diisi."; $error_fields_edit_pgn_arr[] = 'nama_lengkap'; }
    if (empty($email_form_input) || !filter_var($email_form_input, FILTER_VALIDATE_EMAIL)) { $errors_edit_pgn_arr[] = "Email wajib diisi dengan format yang valid."; $error_fields_edit_pgn_arr[] = 'email'; }
    
    if (!empty($password_plain_form_input)) {
        if (strlen($password_plain_form_input) < 6) { $errors_edit_pgn_arr[] = "Password Baru minimal 6 karakter."; $error_fields_edit_pgn_arr[] = 'password'; }
        if ($password_plain_form_input !== $konfirmasi_password_form_input) { $errors_edit_pgn_arr[] = "Password Baru dan Konfirmasi Password tidak cocok."; $error_fields_edit_pgn_arr[] = 'konfirmasi_password'; $error_fields_edit_pgn_arr[] = 'password';}
    } elseif (!empty($konfirmasi_password_form_input) && empty($password_plain_form_input) ) { // Jika hanya konfirmasi diisi
        $errors_edit_pgn_arr[] = "Password Baru wajib diisi jika Konfirmasi Password diisi."; $error_fields_edit_pgn_arr[] = 'password';
    }
    
    $tanggal_lahir_db_update_val = $data_lama_pengguna_from_db['tanggal_lahir'];
    if (array_key_exists('tanggal_lahir', $_POST)) { // Cek apakah field dikirim (bisa string kosong)
        if (!empty($tanggal_lahir_form_input)) {
            $d_val_edit = DateTime::createFromFormat('Y-m-d', $tanggal_lahir_form_input);
            if ($d_val_edit && $d_val_edit->format('Y-m-d') === $tanggal_lahir_form_input) { $tanggal_lahir_db_update_val = $tanggal_lahir_form_input; } 
            else { $errors_edit_pgn_arr[] = "Format Tanggal Lahir tidak valid (YYYY-MM-DD)."; $error_fields_edit_pgn_arr[] = 'tanggal_lahir'; }
        } else { $tanggal_lahir_db_update_val = null; } // Jika dikosongkan, set NULL
    }

    $jenis_kelamin_db_update_val = $data_lama_pengguna_from_db['jenis_kelamin'];
     if (array_key_exists('jenis_kelamin', $_POST)) {
        if (!empty($jenis_kelamin_form_input)) {
            if (in_array($jenis_kelamin_form_input, ['Laki-laki', 'Perempuan'])) { $jenis_kelamin_db_update_val = $jenis_kelamin_form_input; } 
            else { $errors_edit_pgn_arr[] = "Jenis Kelamin tidak valid."; $error_fields_edit_pgn_arr[] = 'jenis_kelamin'; }
        } else { $jenis_kelamin_db_update_val = null; }
    }

    if (!empty($nomor_telepon_form_input) && !preg_match('/^[0-9\-\+\s\(\).#\*]{7,20}$/', $nomor_telepon_form_input)) { $errors_edit_pgn_arr[] = "Format Nomor Telepon tidak valid."; $error_fields_edit_pgn_arr[] = 'nomor_telepon'; }

    if (empty($error_fields_edit_pgn_arr['email']) && strtolower($email_form_input) !== strtolower($data_lama_pengguna_from_db['email'] ?? '')) {
        try {
            $stmt_cek_email_edit = $pdo->prepare("SELECT nik FROM pengguna WHERE email = :email_val_param AND nik != :current_nik_param");
            $stmt_cek_email_edit->execute([':email_val_param' => $email_form_input, ':current_nik_param' => $nik_to_edit_val]);
            if ($stmt_cek_email_edit->fetch()) {
                $errors_edit_pgn_arr[] = "Email '" . htmlspecialchars($email_form_input) . "' sudah digunakan pengguna lain."; $error_fields_edit_pgn_arr[] = 'email';
            }
        } catch (PDOException $e_cek_email) { $errors_edit_pgn_arr[] = "Error database saat cek email."; }
    }
    
    // 6. Proses Upload File Foto Baru & Opsi Hapus Foto
    $foto_db_path_final_edit = $data_lama_pengguna_from_db['foto']; // Default ke foto lama
    $temp_new_foto_path_on_server = null;    // Path file baru yang berhasil diupload ke server
    $old_foto_fisik_perlu_dihapus = false;   // Flag jika foto fisik lama perlu dihapus

    if (empty($errors_edit_pgn_arr)) {
        if ($hapus_foto_current_flag) {
            // Jika pengguna mencentang hapus foto, dan foto lama bukan default
            if ($data_lama_pengguna_from_db['foto'] && $data_lama_pengguna_from_db['foto'] !== $default_avatar_path_relative) {
                $old_foto_fisik_perlu_dihapus = true;
            }
            $foto_db_path_final_edit = $default_avatar_path_relative; // Set path DB ke default
        }

        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK && $_FILES['foto']['size'] > 0) {
            // Path foto lama yang akan dilewatkan ke uploadFileGeneral agar bisa dihapus jika ada upload baru
            // Hanya hapus foto lama jika itu bukan default dan jika tidak sama dengan yang baru diupload (meski jarang terjadi)
            $path_foto_lama_untuk_overwrite_edit = ($data_lama_pengguna_from_db['foto'] && $data_lama_pengguna_from_db['foto'] !== $default_avatar_path_relative) ? $data_lama_pengguna_from_db['foto'] : null;

            $uploaded_path_new_edit = uploadFileGeneral('foto', 'foto_profil', 'user_' . $nik_to_edit_val, 
                                                ['jpg', 'jpeg', 'png', 'gif'], MAX_FILE_SIZE_FOTO_PROFIL_BYTES, 
                                                $errors_edit_pgn_arr, 
                                                $path_foto_lama_untuk_overwrite_edit, false);
            
            if ($uploaded_path_new_edit !== null) { // Jika upload sukses
                $foto_db_path_final_edit = $uploaded_path_new_edit;
                $temp_new_foto_path_on_server = $uploaded_path_new_edit;
                // Jika ada upload baru, foto lama (non-default) sudah dihandle (dihapus) oleh uploadFileGeneral.
                // Jadi, flag hapus manual tidak diperlukan lagi jika ada upload baru.
                $old_foto_fisik_perlu_dihapus = false; 
            } elseif (!empty($_FILES['foto']['name'])) { // Jika ada upaya upload tapi gagal
                 $error_fields_edit_pgn_arr[] = 'foto';
                 // Jika upload gagal, $foto_db_path_final_edit akan tetap berisi path foto lama atau path default (jika hapus_foto_current dicentang)
            }
        }
    }
    
    // Jika ada error validasi setelah semua pengecekan
    if (!empty($errors_edit_pgn_arr)) {
        $_SESSION['errors_pengguna_edit'] = $errors_edit_pgn_arr;
        $_SESSION['error_fields_pengguna_edit'] = array_unique($error_fields_edit_pgn_arr);
        // Hapus file baru yang terupload jika ada error validasi (dan itu bukan path default)
        if ($temp_new_foto_path_on_server && $temp_new_foto_path_on_server !== $default_avatar_path_relative && defined('APP_PATH_BASE')) {
            $full_path_temp_new_foto = rtrim(APP_PATH_BASE, '/\\') . '/' . ltrim($temp_new_foto_path_on_server, '/\\');
            if (file_exists($full_path_temp_new_foto)) { @unlink($full_path_temp_new_foto); }
        }
        header("Location: form_edit_pengguna.php?nik=" . urlencode($nik_to_edit_val));
        exit();
    }

    // 7. Update ke Database
    try {
        $pdo->beginTransaction();
        $current_timestamp_db_update = date('Y-m-d H:i:s');

        $sql_update_pengguna_final = "UPDATE pengguna SET
                                nama_lengkap = :nama_lengkap, email = :email,
                                tanggal_lahir = :tanggal_lahir, jenis_kelamin = :jenis_kelamin,
                                alamat = :alamat, nomor_telepon = :nomor_telepon,
                                foto = :foto, is_approved = :is_approved_new, updated_at = :updated_at_new";
        
        $params_update_pgn_final = [
            ':nik_where_update' => $nik_to_edit_val,
            ':nama_lengkap' => $nama_lengkap_form_input,
            ':email' => $email_form_input,
            ':tanggal_lahir' => $tanggal_lahir_db_update_val,
            ':jenis_kelamin' => $jenis_kelamin_db_update_val,
            ':alamat' => empty($alamat_form_input) ? null : $alamat_form_input,
            ':nomor_telepon' => empty($nomor_telepon_form_input) ? null : $nomor_telepon_form_input,
            ':foto' => $foto_db_path_final_edit, // Sudah berisi path baru atau path default atau path lama
            ':is_approved_new' => $is_approved_form_input,
            ':updated_at_new' => $current_timestamp_db_update
        ];

        if (!empty($password_plain_form_input)) {
            $password_hashed_update_val = password_hash($password_plain_form_input, PASSWORD_DEFAULT);
            $sql_update_pengguna_final .= ", password = :password_new";
            $params_update_pgn_final[':password_new'] = $password_hashed_update_val;
        }
        $sql_update_pengguna_final .= " WHERE nik = :nik_where_update";

        $stmt_update_final = $pdo->prepare($sql_update_pengguna_final);
        $stmt_update_final->execute($params_update_pgn_final);

        // Ambil data baru setelah update untuk log
        $stmt_new_data_pgn_log = $pdo->prepare("SELECT * FROM pengguna WHERE nik = :nik_log_param");
        $stmt_new_data_pgn_log->execute([':nik_log_param' => $nik_to_edit_val]);
        $data_baru_pgn_for_log_final = $stmt_new_data_pgn_log->fetch(PDO::FETCH_ASSOC);

        $data_lama_log_cleaned = $data_lama_pengguna_from_db; unset($data_lama_log_cleaned['password']);
        $data_baru_log_cleaned = $data_baru_pgn_for_log_final; if($data_baru_log_cleaned) unset($data_baru_log_cleaned['password']);
        
        $aksi_log_desc_pgn_edit = "EDIT DATA PENGGUNA";
        if ($data_baru_pgn_for_log_final && $data_baru_pgn_for_log_final['is_approved'] != $data_lama_pengguna_from_db['is_approved']) {
            $aksi_log_desc_pgn_edit .= ($data_baru_pgn_for_log_final['is_approved'] == 1) ? " (STATUS: DISETUJUI)" : " (STATUS: PENDING/DITANGGUHKAN)";
        }
        if (!empty($password_plain_form_input)) $aksi_log_desc_pgn_edit .= " (PASSWORD DIUBAH)";
        if ($foto_db_path_final_edit !== $data_lama_pengguna_from_db['foto']) $aksi_log_desc_pgn_edit .= " (FOTO DIUBAH)";


        if (function_exists('catatAuditLog')) {
            catatAuditLog($pdo, $user_nik_pelaku_proses_edit, $aksi_log_desc_pgn_edit, 'pengguna', $nik_to_edit_val, json_encode($data_lama_log_cleaned), json_encode($data_baru_log_cleaned), 'Perubahan data pengguna: ' . htmlspecialchars($nama_lengkap_form_input) . ' (NIK: ' . htmlspecialchars($nik_to_edit_val) . ')');
        }

        $pdo->commit();

        // Hapus file foto lama secara fisik JIKA ditandai untuk dihapus DAN tidak ada foto baru yang diupload (sudah dihandle oleh uploadFileGeneral jika ada upload baru)
        if ($old_foto_fisik_perlu_dihapus && defined('APP_PATH_BASE') && $data_lama_pengguna_from_db['foto'] !== $default_avatar_path_relative) {
            $full_path_old_foto_to_delete_final = rtrim(APP_PATH_BASE, '/\\') . '/' . ltrim($data_lama_pengguna_from_db['foto'], '/\\');
            if (file_exists($full_path_old_foto_to_delete_final) && is_file($full_path_old_foto_to_delete_final)) {
                @unlink($full_path_old_foto_to_delete_final);
            }
        }

        unset($_SESSION['form_data_pengguna_edit']);
        $_SESSION['pesan_sukses_global'] = "Data pengguna " . htmlspecialchars($nama_lengkap_form_input) . " (NIK: " . htmlspecialchars($nik_to_edit_val) . ") berhasil diperbarui.";
        
        if (!headers_sent()) {
            header("Location: " . $daftar_pengguna_redirect_url);
            exit();
        } else {
            echo "<script type='text/javascript'>window.location.href = '" . htmlspecialchars($daftar_pengguna_redirect_url, ENT_QUOTES, 'UTF-8') . "';</script>";
            echo "<noscript><p>Data berhasil diperbarui. <a href='" . htmlspecialchars($daftar_pengguna_redirect_url, ENT_QUOTES, 'UTF-8') . "'>Kembali ke daftar</a>.</p></noscript>";
            exit(); 
        }

    } catch (PDOException $e_update_db) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("Proses Edit Pengguna - DB Execute Update Error: " . $e_update_db->getMessage());
        if ($temp_new_foto_path_on_server && $temp_new_foto_path_on_server !== $default_avatar_path_relative && defined('APP_PATH_BASE')) { // Hanya hapus jika file baru dan bukan default
            $full_path_temp_new_foto_on_error = rtrim(APP_PATH_BASE, '/\\') . '/' . ltrim($temp_new_foto_path_on_server, '/\\');
            if (file_exists($full_path_temp_new_foto_on_error)) { @unlink($full_path_temp_new_foto_on_error); }
        }
        $_SESSION['errors_pengguna_edit'] = ["Terjadi kesalahan teknis saat memperbarui data pengguna."];
        $form_edit_redirect_error = "form_edit_pengguna.php?nik=" . urlencode($nik_to_edit_val);
        if (!headers_sent()) {
            header("Location: " . $form_edit_redirect_error);
            exit();
        } else {
            echo "<script type='text/javascript'>window.location.href = '" . htmlspecialchars($form_edit_redirect_error, ENT_QUOTES, 'UTF-8') . "';</script>";
            echo "<noscript><p>Gagal memperbarui data. <a href='" . htmlspecialchars($form_edit_redirect_error, ENT_QUOTES, 'UTF-8') . "'>Coba lagi</a>.</p></noscript>";
            exit();
        }
    }
} else {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau NIK pengguna tidak disediakan untuk diedit.";
    header("Location: " . $daftar_pengguna_redirect_url);
    exit();
}
?>