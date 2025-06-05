<?php
// File: reaktorsystem/modules/cabor/proses_tambah_cabor.php

// 1. Inisialisasi Inti
require_once(__DIR__ . '/../../core/header.php'); 

// 2. Pengecekan Akses & Session
if (!isset($user_login_status) || $user_login_status !== true || 
    !isset($user_role_utama) || !in_array($user_role_utama, ['super_admin', 'admin_koni']) ||
    !isset($user_nik) || !isset($app_base_path) || !isset($pdo) || !$pdo instanceof PDO ||
    !defined('MAX_FILE_SIZE_SK_CABOR_BYTES') || !defined('MAX_FILE_SIZE_LOGO_BYTES') || 
    !defined('APP_PATH_BASE') || !isset($default_avatar_path_relative) ) { // $default_avatar_path_relative digunakan untuk path logo KONI default
    
    $_SESSION['pesan_error_global'] = "Akses ditolak, sesi tidak valid, atau konfigurasi inti sistem bermasalah.";
    $fallback_login_url_ptc_proc = (defined('APP_URL_BASE') ? rtrim(APP_URL_BASE, '/') : rtrim($app_base_path ?? '/', '/')) . "/auth/login.php?reason=invalid_session_or_config_ptc_proc";
    if (!headers_sent()) { header("Location: " . $fallback_login_url_ptc_proc); }
    else { echo "<script type='text/javascript'>window.location.href = '" . htmlspecialchars($fallback_login_url_ptc_proc, ENT_QUOTES, 'UTF-8') . "';</script><noscript><p>Error. <a href='" . htmlspecialchars($fallback_login_url_ptc_proc, ENT_QUOTES, 'UTF-8') . "'>Login ulang</a>.</p></noscript>"; }
    exit();
}
$user_nik_pelaku_cabor_proc = $user_nik;
$form_cabor_redirect_page_proc = "tambah_cabor.php";
$daftar_cabor_redirect_page_proc = "daftar_cabor.php";
$path_logo_koni_default_for_cabor = $default_avatar_path_relative; // Menggunakan variabel yang sama untuk path logo KONI default

// Fungsi generateKodeCaborOtomatis (dipertahankan seperti sebelumnya)
if (!function_exists('generateKodeCaborOtomatis')) {
    function generateKodeCaborOtomatis(PDO $pdo, string $nama_cabor): string {
        // ... (isi fungsi generateKodeCaborOtomatis Anda yang sudah baik) ...
        $nama_cabor_clean = preg_replace('/[^a-zA-Z\s]/', '', $nama_cabor);
        $nama_cabor_clean = strtoupper(trim($nama_cabor_clean));
        if (empty($nama_cabor_clean)) return "XXX01";
        $basis_kode = ""; $huruf_diambil_count = 0; $konsonan_target_count = 2; $konsonan_ditemukan_count = 0;
        $vowels_arr = ['A', 'E', 'I', 'O', 'U'];
        if (strlen($nama_cabor_clean) > 0) { $basis_kode .= $nama_cabor_clean[0]; $huruf_diambil_count++; }
        for ($i = 1; $i < strlen($nama_cabor_clean) && $konsonan_ditemukan_count < $konsonan_target_count; $i++) {
            $char_val = $nama_cabor_clean[$i]; if ($char_val === ' ') continue;
            if (!in_array($char_val, $vowels_arr)) { $basis_kode .= $char_val; $konsonan_ditemukan_count++; $huruf_diambil_count++; }
        }
        if ($huruf_diambil_count < 3) {
            for ($i = $huruf_diambil_count; $i < strlen($nama_cabor_clean) && $huruf_diambil_count < 3; $i++) {
                 $char_val_fill = $nama_cabor_clean[$i]; if ($char_val_fill === ' ') continue;
                 if (strpos($basis_kode, $char_val_fill) === false) { $basis_kode .= $char_val_fill; $huruf_diambil_count++; }
            }
        }
        while (strlen($basis_kode) < 3) { $basis_kode .= "X"; }
        $basis_kode = substr($basis_kode, 0, 3);
        $nomor_urut_final_kode = 1; $kode_cabor_final_gen = "";
        do {
            $kode_cabor_final_gen = $basis_kode . sprintf('%02d', $nomor_urut_final_kode);
            $stmt_cek_kode_gen = $pdo->prepare("SELECT id_cabor FROM cabang_olahraga WHERE kode_cabor = :kode_cabor_check_val");
            $stmt_cek_kode_gen->bindParam(':kode_cabor_check_val', $kode_cabor_final_gen); $stmt_cek_kode_gen->execute();
            if ($stmt_cek_kode_gen->fetch()) { $nomor_urut_final_kode++;
                if ($nomor_urut_final_kode > 99) { $basis_kode = chr(rand(65,90)) . chr(rand(65,90)) . chr(rand(65,90)); $nomor_urut_final_kode = 1; }
            } else { break; }
        } while (true);
        return $kode_cabor_final_gen;
    }
}

if (isset($_POST['submit_tambah_cabor'])) {
    // Ambil data dari POST
    $nama_cabor_frm = trim($_POST['nama_cabor'] ?? '');
    $ketua_cabor_nik_frm = trim($_POST['ketua_cabor_nik'] ?? '');
    $sekretaris_cabor_nik_frm = trim($_POST['sekretaris_cabor_nik'] ?? '');
    $bendahara_cabor_nik_frm = trim($_POST['bendahara_cabor_nik'] ?? '');
    // ... (ambil semua field lain dari POST seperti sebelumnya) ...
    $alamat_sekretariat_frm = trim($_POST['alamat_sekretariat'] ?? '');
    $kontak_cabor_frm = trim($_POST['kontak_cabor'] ?? '');
    $email_cabor_frm = filter_var(trim($_POST['email_cabor'] ?? ''), FILTER_SANITIZE_EMAIL);
    $nomor_sk_provinsi_frm = trim($_POST['nomor_sk_provinsi'] ?? '');
    $tanggal_sk_provinsi_frm = !empty($_POST['tanggal_sk_provinsi']) ? trim($_POST['tanggal_sk_provinsi']) : null;
    $periode_mulai_frm = !empty($_POST['periode_mulai']) ? trim($_POST['periode_mulai']) : null;
    $periode_selesai_frm = !empty($_POST['periode_selesai']) ? trim($_POST['periode_selesai']) : null;
    $status_kepengurusan_frm = trim($_POST['status_kepengurusan'] ?? 'Aktif');

    $_SESSION['form_data_cabor_tambah'] = $_POST;
    $errors_cabor_submit = [];

    // Validasi Server-Side
    if (empty($nama_cabor_frm)) $errors_cabor_submit[] = "Nama Cabang Olahraga wajib diisi.";
    // NIK Ketua tidak lagi wajib di sini, tapi jika diisi harus valid NIK dan belum jadi pengurus di cabor lain.
    
    // Validasi unikasi NIK pengurus dalam SATU cabor ini
    $pengurus_niks = array_filter([$ketua_cabor_nik_frm, $sekretaris_cabor_nik_frm, $bendahara_cabor_nik_frm]);
    if (count($pengurus_niks) !== count(array_unique($pengurus_niks))) {
        $errors_cabor_submit[] = "Satu orang tidak boleh menjabat lebih dari satu posisi (Ketua, Sekretaris, Bendahara) dalam cabor yang sama.";
    }

    // Validasi unikasi NIK pengurus LINTAS cabor
    $nik_pengurus_to_check = [];
    if (!empty($ketua_cabor_nik_frm)) $nik_pengurus_to_check['Ketua'] = $ketua_cabor_nik_frm;
    if (!empty($sekretaris_cabor_nik_frm)) $nik_pengurus_to_check['Sekretaris'] = $sekretaris_cabor_nik_frm;
    if (!empty($bendahara_cabor_nik_frm)) $nik_pengurus_to_check['Bendahara'] = $bendahara_cabor_nik_frm;

    foreach ($nik_pengurus_to_check as $jabatan => $nik_check) {
        try {
            $stmt_check_nik_lintas = $pdo->prepare(
                "SELECT c.nama_cabor, 
                        CASE 
                            WHEN ketua_cabor_nik = :nik THEN 'Ketua'
                            WHEN sekretaris_cabor_nik = :nik THEN 'Sekretaris'
                            WHEN bendahara_cabor_nik = :nik THEN 'Bendahara'
                        END as jabatan_existing
                 FROM cabang_olahraga c 
                 WHERE :nik IN (c.ketua_cabor_nik, c.sekretaris_cabor_nik, c.bendahara_cabor_nik)"
            );
            $stmt_check_nik_lintas->execute([':nik' => $nik_check]);
            $existing_role = $stmt_check_nik_lintas->fetch(PDO::FETCH_ASSOC);
            if ($existing_role) {
                $errors_cabor_submit[] = "NIK " . htmlspecialchars($nik_check) . " untuk " . $jabatan . " sudah terdaftar sebagai " . htmlspecialchars($existing_role['jabatan_existing']) . " di Cabor " . htmlspecialchars($existing_role['nama_cabor']) . ". Satu orang hanya bisa menjadi pengurus di satu cabor.";
            }
        } catch (PDOException $e_cek_lintas) {
            $errors_cabor_submit[] = "Gagal memvalidasi NIK Pengurus (" . htmlspecialchars($nik_check) . ").";
            error_log("PROSES_TAMBAH_CABOR_VALIDASI_NIK_LINTAS_ERROR: " . $e_cek_lintas->getMessage());
        }
    }
    
    // ... (validasi lain seperti email, tanggal, periode seperti di draf form_edit_cabor.php) ...
    if (!empty($email_cabor_frm) && !filter_var($email_cabor_frm, FILTER_VALIDATE_EMAIL)) { $errors_cabor_submit[] = "Format Email Cabor tidak valid."; }
    // ... (Tambahkan validasi tanggal SK, periode mulai, periode selesai, dan relasi periode) ...


    if (empty($errors_cabor_submit)) { // Hanya cek nama duplikat jika tidak ada error lain
        try {
            $stmt_cek_nama_cbr_proc = $pdo->prepare("SELECT id_cabor FROM cabang_olahraga WHERE nama_cabor = :nama_cabor_val_param");
            $stmt_cek_nama_cbr_proc->bindParam(':nama_cabor_val_param', $nama_cabor_frm); 
            $stmt_cek_nama_cbr_proc->execute();
            if ($stmt_cek_nama_cbr_proc->fetch()) { $errors_cabor_submit[] = "Nama Cabang Olahraga '" . htmlspecialchars($nama_cabor_frm) . "' sudah terdaftar."; }
        } catch (PDOException $e_cek_nama_cbr_proc) { $errors_cabor_submit[] = "Gagal memvalidasi Nama Cabor."; error_log("PROSES_TAMBAH_CABOR_VALIDASI_NAMA_ERROR: " . $e_cek_nama_cbr_proc->getMessage()); }
    }

    $kode_cabor_generated_proc = '';
    if (empty($errors_cabor_submit)) {
        $kode_cabor_generated_proc = generateKodeCaborOtomatis($pdo, $nama_cabor_frm);
    }

    // Proses Upload File SK
    $path_file_sk_provinsi_final_db_proc = null; $temp_sk_path_upload_proc = null;
    if (empty($errors_cabor_submit) && isset($_FILES['path_file_sk_provinsi']) && $_FILES['path_file_sk_provinsi']['error'] == UPLOAD_ERR_OK && $_FILES['path_file_sk_provinsi']['size'] > 0) {
        $file_prefix_sk_proc = "sk_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $kode_cabor_generated_proc);
        $path_file_sk_provinsi_final_db_proc = uploadFileGeneral('path_file_sk_provinsi', 'sk_cabor', $file_prefix_sk_proc, ['pdf', 'jpg', 'jpeg', 'png'], MAX_FILE_SIZE_SK_CABOR_BYTES, $errors_cabor_proc, null, false);
        if ($path_file_sk_provinsi_final_db_proc !== null) $temp_sk_path_upload_proc = $path_file_sk_provinsi_final_db_proc;
    }

    // Proses Upload Logo Cabor
    $path_logo_cabor_final_db_proc = null; $temp_logo_path_upload_proc = null;
    if (empty($errors_cabor_submit) && isset($_FILES['logo_cabor']) && $_FILES['logo_cabor']['error'] == UPLOAD_ERR_OK && $_FILES['logo_cabor']['size'] > 0) {
        $file_prefix_logo_cbr_proc = "logo_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $kode_cabor_generated_proc);
        $path_logo_cabor_final_db_proc = uploadFileGeneral('logo_cabor', 'logo_cabor', $file_prefix_logo_cbr_proc, ['jpg', 'jpeg', 'png', 'gif'], MAX_FILE_SIZE_LOGO_BYTES, $errors_cabor_proc, null, false);
        if ($path_logo_cabor_final_db_proc !== null) $temp_logo_path_upload_proc = $path_logo_cabor_final_db_proc;
    }
    
    // Jika tidak ada logo diupload DAN tidak ada error sebelumnya, gunakan logo KONI default
    if (empty($errors_cabor_proc) && $path_logo_cabor_final_db_proc === null) {
        $path_logo_cabor_final_db_proc = $path_logo_koni_default_for_cabor; // Path logo KONI dari atas
    }


    if (!empty($errors_cabor_proc)) {
        $_SESSION['errors_tambah_cabor'] = $errors_cabor_proc;
        if ($temp_sk_path_upload_proc) { @unlink(rtrim(APP_PATH_BASE, '/\\') . '/' . ltrim($temp_sk_path_upload_proc, '/\\')); }
        if ($temp_logo_path_upload_proc) { @unlink(rtrim(APP_PATH_BASE, '/\\') . '/' . ltrim($temp_logo_path_upload_proc, '/\\')); }
        header("Location: " . $form_cabor_redirect_page); exit();
    }

    try {
        $pdo->beginTransaction();
        $sql_insert_cbr_final = "INSERT INTO cabang_olahraga (nama_cabor, kode_cabor, ketua_cabor_nik, sekretaris_cabor_nik, bendahara_cabor_nik, alamat_sekretariat, kontak_cabor, email_cabor, logo_cabor, nomor_sk_provinsi, tanggal_sk_provinsi, path_file_sk_provinsi, periode_mulai, periode_selesai, status_kepengurusan, updated_by_nik, created_at, updated_at) VALUES (:nama_cabor, :kode_cabor, :ketua_cabor_nik, :sekretaris_cabor_nik, :bendahara_cabor_nik, :alamat_sekretariat, :kontak_cabor, :email_cabor, :logo_cabor, :nomor_sk_provinsi, :tanggal_sk_provinsi, :path_file_sk_provinsi, :periode_mulai, :periode_selesai, :status_kepengurusan, :updated_by_nik_param, NOW(), NOW())";
        $stmt_insert_cbr_final = $pdo->prepare($sql_insert_cbr_final);
        
        $params_insert_cbr_db_final = [
            ':nama_cabor' => $nama_cabor_frm, ':kode_cabor' => $kode_cabor_generated_proc,
            ':ketua_cabor_nik' => empty($ketua_cabor_nik_frm) ? null : $ketua_cabor_nik_frm,
            ':sekretaris_cabor_nik' => empty($sekretaris_cabor_nik_frm) ? null : $sekretaris_cabor_nik_frm,
            ':bendahara_cabor_nik' => empty($bendahara_cabor_nik_frm) ? null : $bendahara_cabor_nik_frm,
            ':alamat_sekretariat' => empty($alamat_sekretariat_frm) ? null : $alamat_sekretariat_frm,
            ':kontak_cabor' => empty($kontak_cabor_frm) ? null : $kontak_cabor_frm, 
            ':email_cabor' => empty($email_cabor_frm) ? null : $email_cabor_frm, 
            ':logo_cabor' => $path_logo_cabor_final_db_proc, // Akan berisi path upload atau path default KONI
            ':nomor_sk_provinsi' => empty($nomor_sk_provinsi_frm) ? null : $nomor_sk_provinsi_frm, 
            ':tanggal_sk_provinsi' => $tanggal_sk_provinsi_frm,
            ':path_file_sk_provinsi' => $path_file_sk_provinsi_final_db_proc, 
            ':periode_mulai' => $periode_mulai_frm, ':periode_selesai' => $periode_selesai_frm, 
            ':status_kepengurusan' => $status_kepengurusan_frm,
            ':updated_by_nik_param' => $user_nik_pelaku_cabor
        ];
        
        $stmt_insert_cbr_final->execute($params_insert_cbr_db_final);
        $last_inserted_cabor_id_final = $pdo->lastInsertId();

        if ($last_inserted_cabor_id_final) {
            if (function_exists('catatAuditLog')) { /* ... (Logika Audit Log seperti sebelumnya) ... */ }
            $pdo->commit();
            $_SESSION['pesan_sukses_global'] = "Cabor '" . htmlspecialchars($nama_cabor_frm) . "' berhasil ditambahkan.";
            unset($_SESSION['form_data_cabor_tambah']); 
            if (!headers_sent()) { header("Location: " . $daftar_cabor_redirect_page); exit(); } 
            else { echo "<script type='text/javascript'>window.location.href = '" . htmlspecialchars($daftar_cabor_redirect_page, ENT_QUOTES, 'UTF-8') . "';</script><noscript><p>Berhasil. <a href='" . htmlspecialchars($daftar_cabor_redirect_page, ENT_QUOTES, 'UTF-8') . "'>Kembali</a>.</p></noscript>"; exit(); }
        } else { /* ... (Rollback dan error handling jika gagal dapat ID) ... */ }
    } catch (PDOException $e_db_cbr_insert) { /* ... (Rollback, hapus file, error handling, dan redirect) ... */ }
    // Jika ada error yang tidak ditangani redirectnya
    if (!headers_sent()) { header("Location: " . $form_cabor_redirect_page); exit(); }
    else { echo "<script type='text/javascript'>window.location.href = '" . htmlspecialchars($form_cabor_redirect_page, ENT_QUOTES, 'UTF-8') . "';</script><noscript><p>Error. <a href='" . htmlspecialchars($form_cabor_redirect_page, ENT_QUOTES, 'UTF-8') . "'>Kembali</a>.</p></noscript>"; exit(); }
} else {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid.";
    if (!headers_sent()) { header("Location: " . $form_cabor_redirect_page); exit(); }
    else { echo "<script type='text/javascript'>window.location.href = '" . htmlspecialchars($form_cabor_redirect_page, ENT_QUOTES, 'UTF-8') . "';</script><noscript><p>Aksi tidak valid. Kembali ke <a href='" . htmlspecialchars($form_cabor_redirect_page, ENT_QUOTES, 'UTF-8') . "'>form</a>.</p></noscript>"; exit(); }
}
?>