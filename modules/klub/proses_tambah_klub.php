<?php
// File: reaktorsystem/modules/klub/proses_tambah_klub.php

// Aktifkan pelaporan error SEMENTARA untuk debugging layar putih
// HAPUS ATAU KOMENTARI BARIS INI DI LINGKUNGAN PRODUKSI!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Sertakan init_core.php untuk sesi, DB, dan fungsi global
if (file_exists(__DIR__ . '/../../core/init_core.php')) {
    require_once(__DIR__ . '/../../core/init_core.php');
} else {
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    $_SESSION['pesan_error_global'] = "Kesalahan konfigurasi sistem inti: init_core.php tidak ditemukan.";
    error_log("PROSES_TAMBAH_KLUB_FATAL: init_core.php tidak ditemukan di " . __DIR__ . '/../../core/init_core.php');
    // Redirect ke halaman utama atau login jika $app_base_path tidak diketahui
    $fallback_redirect = isset($app_base_path) ? rtrim($app_base_path, '/') . '/dashboard.php' : '/';
    header("Location: " . $fallback_redirect);
    exit();
}

// 2. Pengecekan Akses & Sesi
if ($user_login_status !== true || !isset($user_nik) ||
    !in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor'])) {
    $_SESSION['pesan_error_global'] = "Akses ditolak untuk menambah data klub.";
    header("Location: " . rtrim($app_base_path, '/') . "dashboard.php");
    exit();
}

// Pastikan $pdo sudah terdefinisi
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['form_data_klub_tambah'] = $_POST ?? [];
    $_SESSION['errors_klub_tambah'] = ["Koneksi Database Gagal! Tidak dapat memproses penambahan klub."];
    error_log("PROSES_TAMBAH_KLUB_ERROR: PDO tidak valid atau tidak terdefinisi.");
    header("Location: tambah_klub.php" . (isset($_POST['id_cabor']) && $_POST['id_cabor'] ? '?id_cabor_default='.$_POST['id_cabor'] : '' ));
    exit();
}

// Pastikan ini adalah POST request dan form telah disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_tambah_klub'])) {

    $_SESSION['form_data_klub_tambah'] = $_POST; // Simpan input awal untuk re-populate jika error
    $errors = [];
    $error_fields = [];

    // Ambil dan sanitasi data dari form
    $nama_klub = trim($_POST['nama_klub'] ?? '');
    $id_cabor_from_form = isset($_POST['id_cabor']) ? filter_var($_POST['id_cabor'], FILTER_VALIDATE_INT) : null;
    $ketua_klub = trim($_POST['ketua_klub'] ?? '');
    $alamat_sekretariat = trim($_POST['alamat_sekretariat'] ?? '');
    $kontak_klub = trim($_POST['kontak_klub'] ?? '');
    $email_klub = isset($_POST['email_klub']) ? trim($_POST['email_klub']) : '';
    $nomor_sk_klub = trim($_POST['nomor_sk_klub'] ?? '');
    $tanggal_sk_klub = !empty($_POST['tanggal_sk_klub']) ? trim($_POST['tanggal_sk_klub']) : null;

    // Validasi data
    if (empty($nama_klub)) {
        $errors[] = "Nama Klub wajib diisi.";
        $error_fields['nama_klub'] = true;
    }

    $id_cabor_final = null;
    if ($user_role_utama === 'pengurus_cabor') {
        if (!empty($id_cabor_pengurus_utama)) {
            $id_cabor_final = (int)$id_cabor_pengurus_utama;
        } else {
            $errors[] = "Informasi cabang olahraga Anda tidak valid. Tidak dapat menambahkan klub.";
            // Tidak ada field spesifik untuk ditandai di form, ini masalah konfigurasi user
        }
    } elseif (empty($id_cabor_from_form) || $id_cabor_from_form === 0 || $id_cabor_from_form === false) { // Untuk admin/super_admin
        $errors[] = "Cabang Olahraga Induk wajib dipilih.";
        $error_fields['id_cabor'] = true;
    } else {
        $id_cabor_final = $id_cabor_from_form;
    }

    if (!empty($email_klub) && !filter_var($email_klub, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email klub tidak valid.";
        $error_fields['email_klub'] = true;
    }

    // Cek duplikasi nama klub dalam cabor yang sama
    if (empty($errors) && !empty($nama_klub) && $id_cabor_final !== null && $id_cabor_final > 0) {
        try {
            $stmt_cek_duplikat = $pdo->prepare("SELECT COUNT(*) FROM klub WHERE nama_klub = :nama_klub AND id_cabor = :id_cabor");
            $stmt_cek_duplikat->execute([':nama_klub' => $nama_klub, ':id_cabor' => $id_cabor_final]);
            if ($stmt_cek_duplikat->fetchColumn() > 0) {
                $errors[] = "Klub dengan nama '" . htmlspecialchars($nama_klub) . "' sudah terdaftar di cabang olahraga ini.";
                $error_fields['nama_klub'] = true;
            }
        } catch (PDOException $e) {
            $errors[] = "Gagal memvalidasi duplikasi nama klub.";
            error_log("PROSES_TAMBAH_KLUB_VALIDASI_DUPLIKAT_ERROR: " . $e->getMessage());
        }
    }

    // Proses Upload File (SK Klub dan Logo Klub) - hanya jika tidak ada error validasi teks sebelumnya
    $path_sk_klub_db = null;
    $path_logo_klub_db = null;

    if (empty($errors)) {
        if (isset($_FILES['path_sk_klub']) && $_FILES['path_sk_klub']['error'] == UPLOAD_ERR_OK && $_FILES['path_sk_klub']['size'] > 0) {
            $file_prefix_sk = "skklub_" . preg_replace('/[^a-zA-Z0-9_]/', '_', str_replace(' ', '_', substr($nama_klub, 0, 25))) . "_" . time();
            $path_sk_klub_db = uploadFileGeneral('path_sk_klub', 'sk_klub', $file_prefix_sk, ['pdf', 'jpg', 'jpeg', 'png'], 2, $errors);
            if (!empty($errors) && strpos(end($errors), "Gagal memindahkan file") !== false) { $error_fields['path_sk_klub'] = true; }
        } elseif (isset($_FILES['path_sk_klub']) && $_FILES['path_sk_klub']['error'] != UPLOAD_ERR_NO_FILE && $_FILES['path_sk_klub']['error'] != UPLOAD_ERR_OK) {
            $errors[] = "Terjadi masalah saat upload file SK Klub (Error Code: ".$_FILES['path_sk_klub']['error'].")."; $error_fields['path_sk_klub'] = true;
        }

        if (isset($_FILES['logo_klub']) && $_FILES['logo_klub']['error'] == UPLOAD_ERR_OK && $_FILES['logo_klub']['size'] > 0) {
            $file_prefix_logo = "logoklub_" . preg_replace('/[^a-zA-Z0-9_]/', '_', str_replace(' ', '_', substr($nama_klub, 0, 25))) . "_" . time();
            $path_logo_klub_db = uploadFileGeneral('logo_klub', 'logo_klub', $file_prefix_logo, ['jpg', 'jpeg', 'png'], 1, $errors);
            if (!empty($errors) && strpos(end($errors), "Gagal memindahkan file") !== false) { $error_fields['logo_klub'] = true; }
        } elseif (isset($_FILES['logo_klub']) && $_FILES['logo_klub']['error'] != UPLOAD_ERR_NO_FILE && $_FILES['logo_klub']['error'] != UPLOAD_ERR_OK) {
            $errors[] = "Terjadi masalah saat upload file Logo Klub (Error Code: ".$_FILES['logo_klub']['error'].")."; $error_fields['logo_klub'] = true;
        }
    }

    if (!empty($errors)) {
        $_SESSION['errors_klub_tambah'] = $errors;
        $_SESSION['error_fields_klub_tambah'] = $error_fields;
        // Hapus file yang mungkin terlanjur terupload jika ada error setelah upload salah satu file
        if ($path_sk_klub_db && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . $path_sk_klub_db)) @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . $path_sk_klub_db);
        if ($path_logo_klub_db && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . $path_logo_klub_db)) @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . $path_logo_klub_db);
        header("Location: tambah_klub.php" . ($id_cabor_final ? '?id_cabor_default='.$id_cabor_final : '' ));
        exit();
    }

    // Penentuan status approval dan NIK terkait
    $status_approval_final = 'pending';
    $approved_by_nik_admin_final = null;
    $approval_at_admin_final = null;
    $created_by_nik_pengcab_final = null;
    $current_datetime = date('Y-m-d H:i:s');

    if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
        $status_approval_final = 'disetujui';
        $approved_by_nik_admin_final = $user_nik;
        $approval_at_admin_final = $current_datetime;
    } elseif ($user_role_utama === 'pengurus_cabor') {
        $created_by_nik_pengcab_final = $user_nik;
    }

    try {
        $pdo->beginTransaction();
        $sql_insert_klub = "INSERT INTO klub (
                                nama_klub, id_cabor, alamat_sekretariat, ketua_klub, kontak_klub, email_klub,
                                logo_klub, path_sk_klub, nomor_sk_klub, tanggal_sk_klub,
                                status_approval_admin, approved_by_nik_admin, approval_at_admin,
                                created_by_nik_pengcab, updated_by_nik,
                                created_at, last_updated_process_at, updated_at
                            ) VALUES (
                                :nama_klub, :id_cabor, :alamat, :ketua, :kontak, :email,
                                :logo, :path_sk, :no_sk, :tgl_sk,
                                :status_app, :app_by_admin, :app_at_admin,
                                :created_by_pengcab, :updated_by,
                                :created_at_val, :last_update, :updated_at_val
                            )";
        $stmt_insert = $pdo->prepare($sql_insert_klub);
        $params_insert = [
            ':nama_klub' => $nama_klub, ':id_cabor' => $id_cabor_final,
            ':alamat' => $alamat_sekretariat ?: null, ':ketua' => $ketua_klub ?: null,
            ':kontak' => $kontak_klub ?: null, ':email' => $email_klub ?: null,
            ':logo' => $path_logo_klub_db, ':path_sk' => $path_sk_klub_db,
            ':no_sk' => $nomor_sk_klub ?: null, ':tgl_sk' => $tanggal_sk_klub,
            ':status_app' => $status_approval_final, ':app_by_admin' => $approved_by_nik_admin_final,
            ':app_at_admin' => $approval_at_admin_final, ':created_by_pengcab' => $created_by_nik_pengcab_final,
            ':updated_by' => $user_nik,
            ':created_at_val' => $current_datetime, ':last_update' => $current_datetime, ':updated_at_val' => $current_datetime
        ];
        $stmt_insert->execute($params_insert);
        $lastInsertIdKlub = $pdo->lastInsertId();

        if ($lastInsertIdKlub) {
            if ($status_approval_final === 'disetujui') {
                $pdo->prepare("UPDATE cabang_olahraga SET jumlah_klub = jumlah_klub + 1 WHERE id_cabor = :id_cabor")->execute([':id_cabor' => $id_cabor_final]);
            }
            $aksi_log = ($user_role_utama === 'pengurus_cabor') ? "PENGAJUAN KLUB BARU" : "TAMBAH KLUB BARU (AUTO APPROVE)";
            $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_baru) VALUES (:un, :a, 'klub', :id, :db)");
            $log_stmt->execute([':un' => $user_nik, ':a' => $aksi_log, ':id' => $lastInsertIdKlub, ':db' => json_encode(array_merge(['id_klub' => $lastInsertIdKlub], $params_insert))]);

            $pdo->commit();
            unset($_SESSION['form_data_klub_tambah']);
            $_SESSION['pesan_sukses_global'] = "Klub '" . htmlspecialchars($nama_klub) . "' berhasil ditambahkan.";
            if ($status_approval_final === 'pending') { $_SESSION['pesan_sukses_global'] .= " Pengajuan Anda menunggu approval dari Admin KONI."; }
            header("Location: daftar_klub.php" . ($id_cabor_final ? '?id_cabor=' . $id_cabor_final : ''));
            exit();
        } else {
            $pdo->rollBack();
            if ($path_sk_klub_db && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . $path_sk_klub_db)) @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . $path_sk_klub_db);
            if ($path_logo_klub_db && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . $path_logo_klub_db)) @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . $path_logo_klub_db);
            $_SESSION['errors_klub_tambah'] = ["Gagal menyimpan data klub ke database. Tidak mendapatkan ID baru."];
            header("Location: tambah_klub.php" . ($id_cabor_final ? '?id_cabor_default='.$id_cabor_final : '' ));
            exit();
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($path_sk_klub_db && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . $path_sk_klub_db)) @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . $path_sk_klub_db);
        if ($path_logo_klub_db && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . $path_logo_klub_db)) @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . $path_logo_klub_db);
        error_log("PROSES_TAMBAH_KLUB_DB_ERROR: " . $e->getMessage());
        $_SESSION['errors_klub_tambah'] = ["Terjadi kesalahan database: " . $e->getMessage()];
        header("Location: tambah_klub.php" . ($id_cabor_final ? '?id_cabor_default='.$id_cabor_final : '' ));
        exit();
    }
} else {
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau data form tidak lengkap untuk diproses.";
    header("Location: tambah_klub.php");
    exit();
}
?>