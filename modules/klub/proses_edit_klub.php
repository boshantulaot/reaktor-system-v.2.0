<?php
// File: reaktorsystem/modules/klub/proses_edit_klub.php

// 1. Sertakan init_core.php untuk sesi, DB, dan fungsi global
require_once(__DIR__ . '/../../core/init_core.php');

// 2. Pengecekan Akses & Session
if ($user_login_status !== true || !isset($user_nik) ||
    !in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor'])) {
    $_SESSION['pesan_error_global'] = "Akses ditolak untuk memproses data klub.";
    header("Location: " . rtrim($app_base_path, '/') . "dashboard.php");
    exit();
}

// Pastikan $pdo sudah terdefinisi
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_global'] = "Koneksi Database Gagal!";
    $id_klub_redirect = $_POST['id_klub'] ?? ($_GET['id_klub'] ?? null);
    $redirect_url = $id_klub_redirect ? "edit_klub.php?id_klub=" . $id_klub_redirect : "daftar_klub.php";
    header("Location: " . $redirect_url);
    exit();
}

// Tentukan halaman redirect default jika ada error di awal
$default_redirect_error_page = "daftar_klub.php";
if (isset($_POST['id_klub']) && filter_var($_POST['id_klub'], FILTER_VALIDATE_INT)) {
    $default_redirect_error_page = "edit_klub.php?id_klub=" . (int)$_POST['id_klub'];
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_klub'])) {
    $id_klub = filter_var($_POST['id_klub'], FILTER_VALIDATE_INT);

    if ($id_klub === false || $id_klub <= 0) {
        $_SESSION['pesan_error_global'] = "ID Klub tidak valid.";
        header("Location: daftar_klub.php");
        exit();
    }

    // Ambil data lama klub (penting untuk kedua skenario)
    $stmt_old_klub = $pdo->prepare("SELECT * FROM klub WHERE id_klub = :id_klub");
    $stmt_old_klub->bindParam(':id_klub', $id_klub, PDO::PARAM_INT);
    $stmt_old_klub->execute();
    $data_lama_klub = $stmt_old_klub->fetch(PDO::FETCH_ASSOC);

    if (!$data_lama_klub) {
        $_SESSION['pesan_error_global'] = "Klub yang akan diproses tidak ditemukan.";
        header("Location: daftar_klub.php");
        exit();
    }

    // --- SKENARIO 1: PROSES EDIT PENUH DARI FORM edit_klub.php ---
    if (isset($_POST['submit_edit_klub'])) {
        // Pengecekan izin edit untuk Pengurus Cabor (hanya untuk edit penuh)
        if ($user_role_utama == 'pengurus_cabor' && ($_SESSION['id_cabor_pengurus_utama'] ?? null) != $data_lama_klub['id_cabor']) {
            $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk mengedit klub ini.";
            header("Location: daftar_klub.php?id_cabor=" . ($_SESSION['id_cabor_pengurus_utama'] ?? ''));
            exit();
        }

        $_SESSION['form_data_klub_edit'] = $_POST; // Simpan untuk re-populate jika error
        $errors = [];
        $error_fields = [];

        // Ambil Data Baru dari Form Edit Penuh
        $nama_klub_baru = trim($_POST['nama_klub'] ?? '');
        $id_cabor_baru = (in_array($user_role_utama, ['super_admin', 'admin_koni']) && isset($_POST['id_cabor']))
                         ? filter_var($_POST['id_cabor'], FILTER_VALIDATE_INT)
                         : $data_lama_klub['id_cabor'];
        $ketua_klub_baru = trim($_POST['ketua_klub'] ?? '');
        $alamat_sekretariat_baru = trim($_POST['alamat_sekretariat'] ?? '');
        $kontak_klub_baru = trim($_POST['kontak_klub'] ?? '');
        $email_klub_baru = isset($_POST['email_klub']) ? trim($_POST['email_klub']) : '';
        $nomor_sk_klub_baru = trim($_POST['nomor_sk_klub'] ?? '');
        $tanggal_sk_klub_baru = !empty($_POST['tanggal_sk_klub']) ? $_POST['tanggal_sk_klub'] : null;

        $path_sk_klub_lama_dari_form = $_POST['path_sk_klub_lama'] ?? $data_lama_klub['path_sk_klub'];
        $logo_klub_lama_dari_form = $_POST['logo_klub_lama'] ?? $data_lama_klub['logo_klub'];

        // Validasi Input (sama seperti kode Anda sebelumnya)
        if (empty($nama_klub_baru)) { $errors[] = "Nama Klub wajib diisi."; $error_fields['nama_klub'] = true; }
        if (empty($id_cabor_baru) || !filter_var($id_cabor_baru, FILTER_VALIDATE_INT) || $id_cabor_baru <=0) { $errors[] = "Cabang Olahraga wajib dipilih."; $error_fields['id_cabor'] = true; }
        if (!empty($email_klub_baru) && !filter_var($email_klub_baru, FILTER_VALIDATE_EMAIL)) { $errors[] = "Format email klub tidak valid."; $error_fields['email_klub'] = true; }
        if (empty($errors) && ($nama_klub_baru != $data_lama_klub['nama_klub'] || $id_cabor_baru != $data_lama_klub['id_cabor'])) {
            $stmt_cek_duplikat_edit = $pdo->prepare("SELECT COUNT(*) FROM klub WHERE nama_klub = :nama_klub AND id_cabor = :id_cabor AND id_klub != :current_id_klub");
            $stmt_cek_duplikat_edit->execute([':nama_klub' => $nama_klub_baru, ':id_cabor' => $id_cabor_baru, ':current_id_klub' => $id_klub]);
            if ($stmt_cek_duplikat_edit->fetchColumn() > 0) { $errors[] = "Klub dengan nama '".htmlspecialchars($nama_klub_baru)."' sudah ada di cabor ini."; $error_fields['nama_klub'] = true; $error_fields['id_cabor'] = true;}
        }

        // Proses Upload File
        $path_sk_klub_db_final = $path_sk_klub_lama_dari_form;
        $logo_klub_db_final = $logo_klub_lama_dari_form;
        if (empty($errors)) {
            if (isset($_FILES['path_sk_klub']) && $_FILES['path_sk_klub']['error'] == UPLOAD_ERR_OK) {
                $path_sk_klub_db_final = uploadFileGeneral('path_sk_klub', 'sk_klub', 'skklub_' . str_pad($id_klub, 4, '0', STR_PAD_LEFT), ['pdf', 'jpg', 'jpeg', 'png'], 2, $errors, $path_sk_klub_lama_dari_form);
                if (!empty($errors) && strpos(end($errors), "Gagal memindahkan file") !== false) { $error_fields['path_sk_klub'] = true; }
            }
            if (isset($_FILES['logo_klub']) && $_FILES['logo_klub']['error'] == UPLOAD_ERR_OK) {
                $logo_klub_db_final = uploadFileGeneral('logo_klub', 'logo_klub', 'logoklub_' . str_pad($id_klub, 4, '0', STR_PAD_LEFT), ['jpg', 'jpeg', 'png'], 1, $errors, $logo_klub_lama_dari_form);
                if (!empty($errors) && strpos(end($errors), "Gagal memindahkan file") !== false) { $error_fields['logo_klub'] = true; }
            }
        }

        // Logika Status Approval (sama seperti kode Anda sebelumnya)
        $status_approval_admin_final = $data_lama_klub['status_approval_admin'];
        $approved_by_nik_admin_final = $data_lama_klub['approved_by_nik_admin'];
        $approval_at_admin_final = $data_lama_klub['approval_at_admin'];
        $alasan_penolakan_admin_final = $data_lama_klub['alasan_penolakan_admin'];
        $ada_perubahan_data_penting = false;
        if ($user_role_utama == 'pengurus_cabor') { /* ... cek perubahan data penting ... */ if ($nama_klub_baru != $data_lama_klub['nama_klub'] || $ketua_klub_baru != $data_lama_klub['ketua_klub'] || $alamat_sekretariat_baru != $data_lama_klub['alamat_sekretariat'] || $kontak_klub_baru != $data_lama_klub['kontak_klub'] || $email_klub_baru != $data_lama_klub['email_klub'] || $nomor_sk_klub_baru != $data_lama_klub['nomor_sk_klub'] || $tanggal_sk_klub_baru != $data_lama_klub['tanggal_sk_klub'] || $path_sk_klub_db_final != $data_lama_klub['path_sk_klub'] || $logo_klub_db_final != $data_lama_klub['logo_klub']) { $ada_perubahan_data_penting = true; }}
        if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
            if (isset($_POST['status_approval_admin'])) {
                $status_dari_form_edit = $_POST['status_approval_admin'];
                if ($status_dari_form_edit != $data_lama_klub['status_approval_admin'] || $status_dari_form_edit == 'ditolak') {
                    $status_approval_admin_final = $status_dari_form_edit;
                    if ($status_approval_admin_final == 'ditolak') {
                        $alasan_penolakan_baru = trim($_POST['alasan_penolakan_admin'] ?? '');
                        if (empty($alasan_penolakan_baru)) { $errors[] = "Alasan penolakan wajib jika status 'Ditolak'."; $error_fields['alasan_penolakan_admin'] = true; }
                        else { $alasan_penolakan_admin_final = $alasan_penolakan_baru; }
                    } else { $alasan_penolakan_admin_final = null; }
                    if ($status_dari_form_edit != $data_lama_klub['status_approval_admin'] || ($status_dari_form_edit == 'ditolak' && $alasan_penolakan_admin_final != $data_lama_klub['alasan_penolakan_admin'])) {
                        $approved_by_nik_admin_final = $user_nik; $approval_at_admin_final = date('Y-m-d H:i:s');
                    }
                }
            }
        } elseif ($user_role_utama == 'pengurus_cabor' && $ada_perubahan_data_penting) {
            $status_approval_admin_final = 'pending'; $approved_by_nik_admin_final = null; $approval_at_admin_final = null; $alasan_penolakan_admin_final = null;
        }

        if (!empty($errors)) {
            $_SESSION['errors_edit_klub'] = $errors;
            $_SESSION['error_fields_klub_edit'] = $error_fields;
            header("Location: edit_klub.php?id_klub=" . $id_klub);
            exit();
        }

        // Update ke Database (Query dan bindParam sama seperti kode Anda sebelumnya)
        try {
            $pdo->beginTransaction();
            // ... (SQL UPDATE LENGKAP ANDA) ...
            $sql_update_klub = "UPDATE klub SET nama_klub = :nama_klub, id_cabor = :id_cabor, alamat_sekretariat = :alamat, ketua_klub = :ketua, kontak_klub = :kontak, email_klub = :email, logo_klub = :logo, path_sk_klub = :path_sk, nomor_sk_klub = :no_sk, tanggal_sk_klub = :tgl_sk, status_approval_admin = :status_app, approved_by_nik_admin = :app_by_admin, approval_at_admin = :app_at_admin, alasan_penolakan_admin = :alasan_tolak, updated_by_nik = :updated_by, last_updated_process_at = NOW() WHERE id_klub = :id_klub_where";
            $stmt_update = $pdo->prepare($sql_update_klub);
            $stmt_update->bindParam(':nama_klub', $nama_klub_baru); $stmt_update->bindParam(':id_cabor', $id_cabor_baru, PDO::PARAM_INT); /* ... bindParam lainnya ... */
            $stmt_update->bindParam(':alamat', $alamat_sekretariat_baru, $alamat_sekretariat_baru === '' ? PDO::PARAM_NULL : PDO::PARAM_STR); $stmt_update->bindParam(':ketua', $ketua_klub_baru, $ketua_klub_baru === '' ? PDO::PARAM_NULL : PDO::PARAM_STR); $stmt_update->bindParam(':kontak', $kontak_klub_baru, $kontak_klub_baru === '' ? PDO::PARAM_NULL : PDO::PARAM_STR); $stmt_update->bindParam(':email', $email_klub_baru, $email_klub_baru === '' ? PDO::PARAM_NULL : PDO::PARAM_STR); $stmt_update->bindParam(':logo', $logo_klub_db_final, $logo_klub_db_final === null ? PDO::PARAM_NULL : PDO::PARAM_STR); $stmt_update->bindParam(':path_sk', $path_sk_klub_db_final, $path_sk_klub_db_final === null ? PDO::PARAM_NULL : PDO::PARAM_STR); $stmt_update->bindParam(':no_sk', $nomor_sk_klub_baru, $nomor_sk_klub_baru === '' ? PDO::PARAM_NULL : PDO::PARAM_STR); $stmt_update->bindParam(':tgl_sk', $tanggal_sk_klub_baru, $tanggal_sk_klub_baru === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt_update->bindParam(':status_app', $status_approval_admin_final); $stmt_update->bindParam(':app_by_admin', $approved_by_nik_admin_final, $approved_by_nik_admin_final === null ? PDO::PARAM_NULL : PDO::PARAM_STR); $stmt_update->bindParam(':app_at_admin', $approval_at_admin_final, $approval_at_admin_final === null ? PDO::PARAM_NULL : PDO::PARAM_STR); $stmt_update->bindParam(':alasan_tolak', $alasan_penolakan_admin_final, ($alasan_penolakan_admin_final === null || $alasan_penolakan_admin_final === '') ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt_update->bindParam(':updated_by', $user_nik); $stmt_update->bindParam(':id_klub_where', $id_klub, PDO::PARAM_INT);
            $stmt_update->execute();

            // ... (Logika update jumlah_klub di cabang_olahraga Anda) ...
            $status_lama_db_full = $data_lama_klub['status_approval_admin']; $cabor_lama_db_full = $data_lama_klub['id_cabor'];
            if ($status_approval_admin_final == 'disetujui' && $status_lama_db_full != 'disetujui') { $pdo->prepare("UPDATE cabang_olahraga SET jumlah_klub = jumlah_klub + 1 WHERE id_cabor = ?")->execute([$id_cabor_baru]); if ($id_cabor_baru != $cabor_lama_db_full && $status_lama_db_full == 'disetujui') { $pdo->prepare("UPDATE cabang_olahraga SET jumlah_klub = GREATEST(0, jumlah_klub - 1) WHERE id_cabor = ?")->execute([$cabor_lama_db_full]); }}
            elseif ($status_approval_admin_final != 'disetujui' && $status_lama_db_full == 'disetujui') { $pdo->prepare("UPDATE cabang_olahraga SET jumlah_klub = GREATEST(0, jumlah_klub - 1) WHERE id_cabor = ?")->execute([$cabor_lama_db_full]); }
            elseif ($id_cabor_baru != $cabor_lama_db_full && $status_approval_admin_final == 'disetujui' && $status_lama_db_full == 'disetujui') { $pdo->prepare("UPDATE cabang_olahraga SET jumlah_klub = GREATEST(0, jumlah_klub - 1) WHERE id_cabor = ?")->execute([$cabor_lama_db_full]); $pdo->prepare("UPDATE cabang_olahraga SET jumlah_klub = jumlah_klub + 1 WHERE id_cabor = ?")->execute([$id_cabor_baru]); }

            // ... (Audit Log Anda) ...
            $stmt_new_full = $pdo->prepare("SELECT * FROM klub WHERE id_klub = :id_klub"); $stmt_new_full->bindParam(':id_klub', $id_klub, PDO::PARAM_INT); $stmt_new_full->execute(); $data_baru_klub_log_full = $stmt_new_full->fetch(PDO::FETCH_ASSOC);
            $aksi_log_full = "EDIT DATA KLUB";
            if ($status_approval_admin_final == 'pending' && $user_role_utama == 'pengurus_cabor' && $ada_perubahan_data_penting) { $aksi_log_full = "PENGAJUAN PERUBAHAN DATA KLUB"; }
            elseif ($status_approval_admin_final == 'disetujui' && isset($status_dari_form_edit) && $status_dari_form_edit != $data_lama_klub['status_approval_admin']) { $aksi_log_full = "KLUB DISETUJUI (ADMIN)"; }
            elseif ($status_approval_admin_final == 'ditolak' && isset($status_dari_form_edit) && $status_dari_form_edit != $data_lama_klub['status_approval_admin']) { $aksi_log_full = "KLUB DITOLAK (ADMIN)"; }
            $log_stmt_full = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, data_baru) VALUES (:un, :a, 'klub', :id, :dl, :db)");
            $log_stmt_full->execute([':un' => $user_nik, ':a' => $aksi_log_full, ':id' => $id_klub, ':dl' => json_encode($data_lama_klub), ':db' => json_encode($data_baru_klub_log_full)]);

            $pdo->commit();
            unset($_SESSION['form_data_klub_edit']);
            $_SESSION['pesan_sukses_global'] = "Data Klub '" . htmlspecialchars($nama_klub_baru) . "' berhasil diperbarui.";
            if ($status_approval_admin_final == 'pending' && $user_role_utama == 'pengurus_cabor' && $ada_perubahan_data_penting) {
                $_SESSION['pesan_sukses_global'] .= " Perubahan Anda menunggu approval dari Admin KONI.";
            }
            header("Location: daftar_klub.php" . ($id_cabor_baru ? '?id_cabor=' . $id_cabor_baru : ''));
            exit();
        } catch (PDOException $e) { /* ... (Error handling DB dan rollback file) ... */
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            if ($path_sk_klub_db_final !== $path_sk_klub_lama_dari_form && !empty($path_sk_klub_db_final) && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . '/' . ltrim($path_sk_klub_db_final, '/'))) { @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . '/' . ltrim($path_sk_klub_db_final, '/')); }
            if ($logo_klub_db_final !== $logo_klub_lama_dari_form && !empty($logo_klub_db_final) && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . '/' . ltrim($logo_klub_db_final, '/'))) { @unlink(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($app_base_path, '/') . '/' . ltrim($logo_klub_db_final, '/')); }
            error_log("PROSES_EDIT_KLUB_DB_ERROR (Full Edit): " . $e->getMessage());
            $_SESSION['errors_edit_klub'] = ["DB Error: " . $e->getMessage()];
            header("Location: edit_klub.php?id_klub=" . $id_klub); exit();
        }

    // --- SKENARIO 2: PROSES QUICK ACTION (Setujui/Tolak) dari daftar_klub.php ---
    } elseif (isset($_POST['quick_action_approval'], $_POST['status_approval_admin'])) {
        // Pengecekan izin, hanya Admin KONI / Super Admin yang boleh melakukan quick action
        if (!in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
            $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk melakukan aksi approval ini.";
            header("Location: daftar_klub.php" . (isset($_GET['id_cabor']) ? '?id_cabor='.$_GET['id_cabor'] : ''));
            exit();
        }

        $new_status_quick = $_POST['status_approval_admin'];
        $alasan_penolakan_quick = null;

        if (!in_array($new_status_quick, ['disetujui', 'ditolak'])) {
            $_SESSION['pesan_error_global'] = "Status approval tidak valid untuk aksi cepat.";
            header("Location: daftar_klub.php" . (isset($_GET['id_cabor']) ? '?id_cabor='.$_GET['id_cabor'] : ''));
            exit();
        }

        if ($new_status_quick == 'ditolak') {
            $alasan_penolakan_quick = trim($_POST['alasan_penolakan_admin'] ?? '');
            if (empty($alasan_penolakan_quick)) {
                $_SESSION['pesan_error_global'] = "Alasan penolakan wajib diisi jika status 'Ditolak'.";
                 // Redirect kembali ke daftar klub, mungkin dengan filter cabor jika ada
                $redirect_quick_error = "daftar_klub.php";
                $current_cabor_filter_val = $data_lama_klub['id_cabor'] ?? null; // Ambil dari data lama jika perlu
                if ($current_cabor_filter_val) { $redirect_quick_error .= "?id_cabor=" . $current_cabor_filter_val; }
                header("Location: " . $redirect_quick_error);
                exit();
            }
        }

        try {
            $pdo->beginTransaction();
            $sql_quick_update = "UPDATE klub SET
                                    status_approval_admin = :new_status,
                                    approved_by_nik_admin = :approved_by,
                                    approval_at_admin = NOW(),
                                    alasan_penolakan_admin = :alasan,
                                    updated_by_nik = :updated_by, 
                                    last_updated_process_at = NOW()
                                 WHERE id_klub = :id_klub";
            $stmt_quick = $pdo->prepare($sql_quick_update);
            $stmt_quick->execute([
                ':new_status' => $new_status_quick,
                ':approved_by' => $user_nik,
                ':alasan' => ($new_status_quick == 'ditolak' ? $alasan_penolakan_quick : null),
                ':updated_by' => $user_nik,
                ':id_klub' => $id_klub
            ]);

            // Update jumlah_klub di cabang_olahraga
            $status_lama_db_q = $data_lama_klub['status_approval_admin'];
            $id_cabor_klub_q = $data_lama_klub['id_cabor'];
            if ($new_status_quick == 'disetujui' && $status_lama_db_q != 'disetujui') {
                $pdo->prepare("UPDATE cabang_olahraga SET jumlah_klub = jumlah_klub + 1 WHERE id_cabor = ?")->execute([$id_cabor_klub_q]);
            } elseif ($new_status_quick != 'disetujui' && $status_lama_db_q == 'disetujui') {
                $pdo->prepare("UPDATE cabang_olahraga SET jumlah_klub = GREATEST(0, jumlah_klub - 1) WHERE id_cabor = ?")->execute([$id_cabor_klub_q]);
            }

            // Audit Log
            $stmt_new_q = $pdo->prepare("SELECT * FROM klub WHERE id_klub = :id_klub"); $stmt_new_q->bindParam(':id_klub', $id_klub, PDO::PARAM_INT); $stmt_new_q->execute(); $data_baru_klub_log_q = $stmt_new_q->fetch(PDO::FETCH_ASSOC);
            $log_stmt_quick = $pdo->prepare("INSERT INTO audit_log (user_nik, aksi, tabel_yang_diubah, id_data_yang_diubah, data_lama, data_baru) VALUES (:un, :a, 'klub', :id, :dl, :db)");
            $log_stmt_quick->execute([
                ':un' => $user_nik, ':a' => ($new_status_quick == 'disetujui' ? 'QUICK APPROVE KLUB' : 'QUICK REJECT KLUB'), ':id' => $id_klub,
                ':dl' => json_encode(['id_klub' => $id_klub, 'status_approval_admin' => $data_lama_klub['status_approval_admin']]),
                ':db' => json_encode($data_baru_klub_log_q)
            ]);

            $pdo->commit();
            $_SESSION['pesan_sukses_global'] = "Status Klub '" . htmlspecialchars($data_lama_klub['nama_klub']) . "' berhasil diubah menjadi " . ucfirst($new_status_quick) . ".";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log("PROSES_EDIT_KLUB_QUICK_ACTION_DB_ERROR: " . $e->getMessage());
            $_SESSION['pesan_error_global'] = "Gagal mengubah status klub: " . $e->getMessage();
        }
        $id_cabor_redirect = $data_lama_klub['id_cabor'] ?? ($_GET['id_cabor_filter_asal'] ?? null); // Jika ada info cabor filter asal
        header("Location: daftar_klub.php" . ($id_cabor_redirect ? '?id_cabor='.$id_cabor_redirect : '' ));
        exit();

    } else {
        // Jika bukan edit penuh atau quick action yang dikenali
        $_SESSION['pesan_error_global'] = "Parameter aksi tidak lengkap atau tidak dikenal.";
        header("Location: " . $default_redirect_error_page);
        exit();
    }
} else {
    // Jika bukan POST request atau id_klub tidak ada
    $_SESSION['pesan_error_global'] = "Aksi tidak valid atau ID Klub tidak ditemukan untuk diproses.";
    header("Location: daftar_klub.php");
    exit();
}
?>