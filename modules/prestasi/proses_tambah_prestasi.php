<?php
// File: reaktorsystem/modules/prestasi/proses_tambah_prestasi.php

// 1. Inisialisasi Inti (sudah menangani sesi, DB, path, dll.)
require_once(__DIR__ . '/../../core/init_core.php');

// 2. Pengecekan Akses & Session
// Variabel $user_nik, $user_role_utama, $app_base_path, $pdo sudah tersedia dari init_core.php
if ($user_login_status !== true || !in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor', 'atlet'])) {
    $_SESSION['pesan_error_global'] = "Akses ditolak atau sesi tidak valid untuk menambah prestasi.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}
$user_nik_pelaku_proses = $user_nik; // NIK pengguna yang melakukan aksi

// Pastikan PDO tersedia
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_global'] = "Koneksi Database Gagal!";
    header("Location: tambah_prestasi.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_tambah_prestasi'])) {
    // 3. Ambil dan Bersihkan Data Form
    $nik_atlet_form = trim($_POST['nik'] ?? '');
    $id_cabor_form = filter_var($_POST['id_cabor'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $nama_kejuaraan = trim($_POST['nama_kejuaraan'] ?? '');
    $tingkat_kejuaraan = trim($_POST['tingkat_kejuaraan'] ?? '');
    $tahun_perolehan_form = trim($_POST['tahun_perolehan'] ?? '');
    $medali_peringkat = trim($_POST['medali_peringkat'] ?? '');

    $bukti_path_db_prestasi = null; // Path yang akan disimpan ke DB

    // Simpan data form ke session untuk diisi kembali jika ada error
    $_SESSION['form_data_prestasi_tambah'] = $_POST;
    $errors_prestasi = [];
    $error_fields_prestasi = [];

    // 4. Validasi Server Side yang Komprehensif
    if (empty($nik_atlet_form)) { 
        $errors_prestasi[] = "Atlet wajib dipilih/diisi."; 
        $error_fields_prestasi[] = 'nik';
    } elseif (!preg_match('/^\d{16}$/', $nik_atlet_form)) {
        $errors_prestasi[] = "Format NIK Atlet tidak valid (harus 16 digit angka).";
        $error_fields_prestasi[] = 'nik';
    }

    if (empty($id_cabor_form) || !filter_var($id_cabor_form, FILTER_VALIDATE_INT)) { 
        $errors_prestasi[] = "Cabang Olahraga wajib dipilih/terdefinisi."; 
        $error_fields_prestasi[] = 'id_cabor';
    }

    if (empty($nama_kejuaraan)) { $errors_prestasi[] = "Nama Kejuaraan wajib diisi."; $error_fields_prestasi[] = 'nama_kejuaraan'; }
    if (empty($tingkat_kejuaraan) || !in_array($tingkat_kejuaraan, ['Kabupaten','Provinsi','Nasional','Internasional'])) { 
        $errors_prestasi[] = "Tingkat Kejuaraan wajib dipilih dan valid."; $error_fields_prestasi[] = 'tingkat_kejuaraan'; 
    }
    if (empty($tahun_perolehan_form) || !filter_var($tahun_perolehan_form, FILTER_VALIDATE_INT) || $tahun_perolehan_form < 1900 || $tahun_perolehan_form > (date('Y') + 5)) { // Beri toleransi 5 tahun ke depan
        $errors_prestasi[] = "Tahun Perolehan tidak valid."; $error_fields_prestasi[] = 'tahun_perolehan'; 
    }
    if (empty($medali_peringkat)) { $errors_prestasi[] = "Medali/Peringkat wajib diisi."; $error_fields_prestasi[] = 'medali_peringkat'; }

    // Validasi NIK Atlet lebih lanjut: apakah terdaftar sebagai pengguna dan atlet aktif di cabor tersebut
    $id_atlet_prestasi_db = null;
    if (empty($error_fields_prestasi['nik']) && empty($error_fields_prestasi['id_cabor'])) { // Hanya jika NIK & Cabor formatnya benar
        try {
            $stmt_cek_atlet = $pdo->prepare("SELECT a.id_atlet FROM pengguna p JOIN atlet a ON p.nik = a.nik WHERE p.nik = :nik_atlet AND p.is_approved = 1 AND a.id_cabor = :id_cabor AND a.status_pendaftaran = 'disetujui'");
            $stmt_cek_atlet->bindParam(':nik_atlet', $nik_atlet_form, PDO::PARAM_STR);
            $stmt_cek_atlet->bindParam(':id_cabor', $id_cabor_form, PDO::PARAM_INT);
            $stmt_cek_atlet->execute();
            $atlet_data_cek = $stmt_cek_atlet->fetch(PDO::FETCH_ASSOC);

            if (!$atlet_data_cek) {
                $errors_prestasi[] = "Atlet dengan NIK " . htmlspecialchars($nik_atlet_form) . " tidak terdaftar sebagai atlet aktif yang disetujui pada cabang olahraga yang dipilih.";
                $error_fields_prestasi[] = 'nik'; // Tandai error pada NIK atau Cabor
                $error_fields_prestasi[] = 'id_cabor';
            } else {
                $id_atlet_prestasi_db = $atlet_data_cek['id_atlet'];
            }
        } catch (PDOException $e) {
            error_log("Proses Tambah Prestasi - Validasi Atlet DB Error: " . $e->getMessage());
            $errors_prestasi[] = "Terjadi kesalahan saat memvalidasi data atlet.";
        }
    }
    
    // 5. Proses Upload File Bukti (jika tidak ada error validasi sebelumnya)
    $temp_bukti_path_prestasi = null;
    if (empty($errors_prestasi) && isset($_FILES['bukti_path']) && $_FILES['bukti_path']['error'] == UPLOAD_ERR_OK && $_FILES['bukti_path']['size'] > 0) {
        // Menggunakan konstanta yang sudah didefinisikan
        $allowed_ext_bukti = ['pdf', 'jpg', 'jpeg', 'png'];
        $max_size_bukti = MAX_FILE_SIZE_BUKTI_PRESTASI_MB; // Dalam MB
        
        $temp_bukti_path_prestasi = uploadFileGeneral(
            'bukti_path',               // Nama input file
            'bukti_prestasi',           // Subdirektori di dalam assets/uploads/
            'bukti_' . $nik_atlet_form, // Awalan nama file
            $allowed_ext_bukti,
            $max_size_bukti,
            $errors_prestasi            // Array untuk menampung error upload
        );
        if ($temp_bukti_path_prestasi === null && !empty($_FILES['bukti_path']['name'])) { // Jika upload gagal tapi file diinput
             $error_fields_prestasi[] = 'bukti_path'; // Tandai field file sebagai error
        }
        $bukti_path_db_prestasi = $temp_bukti_path_prestasi; // Path yang akan disimpan ke DB
    }


    // Jika ada error validasi setelah semua dicek (termasuk upload)
    if (!empty($errors_prestasi)) {
        $_SESSION['errors_prestasi_tambah'] = $errors_prestasi;
        $_SESSION['error_fields_prestasi_tambah'] = array_unique($error_fields_prestasi); 
        
        // Hapus file yang mungkin sudah terlanjur terupload jika ada error lain
        if ($temp_bukti_path_prestasi && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . '/' . $temp_bukti_path_prestasi)) {
            @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . '/' . $temp_bukti_path_prestasi);
        }
        
        // Bangun URL redirect dengan parameter GET jika ada
        $redirect_params_tambah = [];
        if (isset($_POST['id_cabor_default'])) $redirect_params_tambah['id_cabor_default'] = filter_var($_POST['id_cabor_default'], FILTER_SANITIZE_NUMBER_INT);
        if (isset($_POST['nik_atlet_default'])) $redirect_params_tambah['nik_atlet_default'] = htmlspecialchars($_POST['nik_atlet_default']);
        $query_string_redirect_tambah = !empty($redirect_params_tambah) ? '?' . http_build_query($redirect_params_tambah) : '';
        
        header("Location: tambah_prestasi.php" . $query_string_redirect_tambah);
        exit();
    }

    // 6. Logika Status Approval Awal & Data untuk Disimpan
    $status_approval_awal_prestasi = 'pending';
    $approved_by_nik_pengcab_val_prestasi = null;
    $approval_at_pengcab_val_prestasi = null;
    $approved_by_nik_admin_val_prestasi = null;
    $approval_at_admin_val_prestasi = null;
    $current_timestamp_proc_prestasi = date('Y-m-d H:i:s');

    if ($user_role_utama == 'pengurus_cabor') {
        $status_approval_awal_prestasi = 'disetujui_pengcab';
        $approved_by_nik_pengcab_val_prestasi = $user_nik_pelaku_proses;
        $approval_at_pengcab_val_prestasi = $current_timestamp_proc_prestasi;
    } elseif (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
        $status_approval_awal_prestasi = 'disetujui_admin';
        // Admin/SA langsung approve, jadi set juga approval pengcab (oleh admin itu sendiri)
        $approved_by_nik_pengcab_val_prestasi = $user_nik_pelaku_proses;
        $approval_at_pengcab_val_prestasi = $current_timestamp_proc_prestasi;
        $approved_by_nik_admin_val_prestasi = $user_nik_pelaku_proses;
        $approval_at_admin_val_prestasi = $current_timestamp_proc_prestasi;
    }
    // Jika Atlet, status_approval_awal tetap 'pending'

    // 7. Simpan ke Database
    try {
        $pdo->beginTransaction();
        $sql_insert_prestasi = "INSERT INTO prestasi (
                            nik, id_cabor, id_atlet, nama_kejuaraan, tingkat_kejuaraan, tahun_perolehan, 
                            medali_peringkat, bukti_path, status_approval, 
                            approved_by_nik_pengcab, approval_at_pengcab, 
                            approved_by_nik_admin, approval_at_admin,
                            updated_by_nik, last_updated_process_at
                            -- alasan_penolakan diisi saat proses edit/approval, bukan saat tambah
                        ) VALUES (
                            :nik, :id_cabor, :id_atlet, :nama_kejuaraan, :tingkat_kejuaraan, :tahun_perolehan,
                            :medali_peringkat, :bukti_path, :status_approval,
                            :approved_by_nik_pengcab, :approval_at_pengcab,
                            :approved_by_nik_admin, :approval_at_admin,
                            :updated_by_nik, :last_updated_process_at
                        )";
        $stmt_insert_prestasi = $pdo->prepare($sql_insert_prestasi);

        $stmt_insert_prestasi->bindParam(':nik', $nik_atlet_form, PDO::PARAM_STR);
        $stmt_insert_prestasi->bindParam(':id_cabor', $id_cabor_form, PDO::PARAM_INT);
        $stmt_insert_prestasi->bindParam(':id_atlet', $id_atlet_prestasi_db, $id_atlet_prestasi_db === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt_insert_prestasi->bindParam(':nama_kejuaraan', $nama_kejuaraan, PDO::PARAM_STR);
        $stmt_insert_prestasi->bindParam(':tingkat_kejuaraan', $tingkat_kejuaraan, PDO::PARAM_STR);
        $stmt_insert_prestasi->bindParam(':tahun_perolehan', $tahun_perolehan_form, PDO::PARAM_INT);
        $stmt_insert_prestasi->bindParam(':medali_peringkat', $medali_peringkat, PDO::PARAM_STR);
        $stmt_insert_prestasi->bindParam(':bukti_path', $bukti_path_db_prestasi, $bukti_path_db_prestasi === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_insert_prestasi->bindParam(':status_approval', $status_approval_awal_prestasi, PDO::PARAM_STR);
        
        $stmt_insert_prestasi->bindParam(':approved_by_nik_pengcab', $approved_by_nik_pengcab_val_prestasi, $approved_by_nik_pengcab_val_prestasi === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_insert_prestasi->bindParam(':approval_at_pengcab', $approval_at_pengcab_val_prestasi, $approval_at_pengcab_val_prestasi === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_insert_prestasi->bindParam(':approved_by_nik_admin', $approved_by_nik_admin_val_prestasi, $approved_by_nik_admin_val_prestasi === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_insert_prestasi->bindParam(':approval_at_admin', $approval_at_admin_val_prestasi, $approval_at_admin_val_prestasi === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        
        $stmt_insert_prestasi->bindParam(':updated_by_nik', $user_nik_pelaku_proses, PDO::PARAM_STR);
        $stmt_insert_prestasi->bindParam(':last_updated_process_at', $current_timestamp_proc_prestasi, PDO::PARAM_STR);

        $stmt_insert_prestasi->execute();
        $new_prestasi_id_db = $pdo->lastInsertId();

        // 8. Audit Log
        $aksi_log_detail = "PENGAJUAN PRESTASI (OLEH " . ucfirst(str_replace('_', ' ', $user_role_utama)) . ")";
        if ($status_approval_awal_prestasi == 'disetujui_admin') {
            $aksi_log_detail = "TAMBAH & SETUJUI PRESTASI (OLEH " . ucfirst(str_replace('_', ' ', $user_role_utama)) . ")";
        }

        $data_baru_log_prestasi = json_encode([
            'id_prestasi' => $new_prestasi_id_db,
            'nik_atlet' => $nik_atlet_form,
            'kejuaraan' => $nama_kejuaraan,
            'status_awal' => $status_approval_awal_prestasi,
            'bukti' => $bukti_path_db_prestasi
        ]);

        $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_baru) VALUES (:user_nik, :aksi, :tabel, :id_data, :data_baru)");
        $log_stmt->execute([
            ':user_nik' => $user_nik_pelaku_proses,
            ':aksi' => $aksi_log_detail,
            ':tabel' => 'prestasi',
            ':id_data' => $new_prestasi_id_db,
            ':data_baru' => $data_baru_log_prestasi
        ]);

        $pdo->commit();

        unset($_SESSION['form_data_prestasi_tambah']); // Hapus data form dari session setelah sukses
        $_SESSION['pesan_sukses_global'] = "Data prestasi untuk '" . htmlspecialchars($nama_kejuaraan) . "' berhasil ditambahkan/diajukan.";
        if ($status_approval_awal_prestasi == 'pending') $_SESSION['pesan_sukses_global'] .= " Menunggu verifikasi Pengurus Cabor.";
        elseif ($status_approval_awal_prestasi == 'disetujui_pengcab') $_SESSION['pesan_sukses_global'] .= " Menunggu approval Admin KONI.";

        header("Location: daftar_prestasi.php?id_cabor=" . $id_cabor_form . "&nik_atlet=" . $nik_atlet_form);
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("Proses Tambah Prestasi - DB Execute Error: " . $e->getMessage());
        // Hapus file yang mungkin sudah terlanjur terupload
        if ($temp_bukti_path_prestasi && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . '/' . $temp_bukti_path_prestasi)) {
            @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . '/' . $temp_bukti_path_prestasi);
        }
        
        $_SESSION['errors_prestasi_tambah'] = ["Terjadi kesalahan teknis saat menyimpan data: " . $e->getMessage()];
        // Cek jika error duplikasi untuk pesan lebih spesifik (sesuaikan dengan nama unique key Anda)
        if (strpos(strtolower($e->getMessage()), 'duplicate entry') !== false) {
             $_SESSION['errors_prestasi_tambah'] = ["Prestasi dengan detail serupa (misal, nama kejuaraan, tahun, atlet) mungkin sudah ada."];
        }
        
        $redirect_params_tambah_err = [];
        if (isset($_POST['id_cabor_default'])) $redirect_params_tambah_err['id_cabor_default'] = filter_var($_POST['id_cabor_default'], FILTER_SANITIZE_NUMBER_INT);
        if (isset($_POST['nik_atlet_default'])) $redirect_params_tambah_err['nik_atlet_default'] = htmlspecialchars($_POST['nik_atlet_default']);
        $query_string_redirect_tambah_err = !empty($redirect_params_tambah_err) ? '?' . http_build_query($redirect_params_tambah_err) : '';
        
        header("Location: tambah_prestasi.php" . $query_string_redirect_tambah_err);
        exit();
    }
} else {
    // Jika bukan POST atau tombol submit tidak ditekan
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau permintaan tidak sesuai.";
    header("Location: tambah_prestasi.php");
    exit();
}
?>