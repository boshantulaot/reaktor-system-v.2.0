<?php
// File: modules/cabor/detail_cabor.php

// 1. Panggil init_core.php (untuk sesi, DB, path, variabel global)
require_once(__DIR__ . '/../../core/init_core.php');

// --- Inisialisasi Variabel Halaman ---
$page_title_actual = "Detail Cabang Olahraga";
$cabor_detail = null;
$nama_ketua = $nik_ketua = $jabatan_ketua = '-';
$nama_sekretaris = $nik_sekretaris = $jabatan_sekretaris = '-';
$nama_bendahara = $nik_bendahara = $jabatan_bendahara = '-';
$jumlah_klub_cabor = 0;
$jumlah_atlet_cabor = 0;
$jumlah_pelatih_cabor = 0;
$jumlah_wasit_cabor = 0;
$data_terkait_error_messages = [];
$log_pembuatan_cabor = null; // Untuk menyimpan info log pembuatan

// 2. SEMUA LOGIKA BISNIS DAN POTENSI REDIRECT DI SINI
if (isset($_GET['id_cabor'])) {
    $id_cabor_to_view = filter_var($_GET['id_cabor'], FILTER_VALIDATE_INT);
    if ($id_cabor_to_view === false || $id_cabor_to_view <= 0) {
        $_SESSION['pesan_error_global'] = "ID Cabang Olahraga tidak valid.";
        header("Location: " . rtrim($app_base_path, '/') . "/modules/cabor/daftar_cabor.php");
        exit();
    }

    try {
        // Query utama untuk data cabor (Ketua, Sekretaris, Bendahara, Info Updater)
        $sql_cabor = "SELECT
                        co.*, 
                        pengurus_ketua.nama_lengkap AS nama_ketua_db,
                        pengurus_ketua.nik AS nik_ketua_db,
                        anggota_ketua.jabatan AS jabatan_ketua_db,
                        pengurus_sekretaris.nama_lengkap AS nama_sekretaris_db,
                        pengurus_sekretaris.nik AS nik_sekretaris_db,
                        anggota_sekretaris.jabatan AS jabatan_sekretaris_db,
                        pengurus_bendahara.nama_lengkap AS nama_bendahara_db,
                        pengurus_bendahara.nik AS nik_bendahara_db,
                        anggota_bendahara.jabatan AS jabatan_bendahara_db,
                        updater_pengguna.nama_lengkap AS nama_updater_terakhir
                    FROM cabang_olahraga co
                    LEFT JOIN pengguna pengurus_ketua ON co.ketua_cabor_nik = pengurus_ketua.nik AND pengurus_ketua.is_approved = 1
                    LEFT JOIN anggota anggota_ketua ON co.ketua_cabor_nik = anggota_ketua.nik AND anggota_ketua.id_cabor = co.id_cabor AND anggota_ketua.is_verified = 1
                    LEFT JOIN pengguna pengurus_sekretaris ON co.sekretaris_cabor_nik = pengurus_sekretaris.nik AND pengurus_sekretaris.is_approved = 1
                    LEFT JOIN anggota anggota_sekretaris ON co.sekretaris_cabor_nik = anggota_sekretaris.nik AND anggota_sekretaris.id_cabor = co.id_cabor AND anggota_sekretaris.is_verified = 1
                    LEFT JOIN pengguna pengurus_bendahara ON co.bendahara_cabor_nik = pengurus_bendahara.nik AND pengurus_bendahara.is_approved = 1
                    LEFT JOIN anggota anggota_bendahara ON co.bendahara_cabor_nik = anggota_bendahara.nik AND anggota_bendahara.id_cabor = co.id_cabor AND anggota_bendahara.is_verified = 1
                    LEFT JOIN pengguna updater_pengguna ON co.updated_by_nik = updater_pengguna.nik AND updater_pengguna.is_approved = 1
                    WHERE co.id_cabor = :id_cabor";
        
        $stmt_cabor = $pdo->prepare($sql_cabor);
        $stmt_cabor->bindParam(':id_cabor', $id_cabor_to_view, PDO::PARAM_INT);
        $stmt_cabor->execute();
        $cabor_detail = $stmt_cabor->fetch(PDO::FETCH_ASSOC);

        if (!$cabor_detail) {
            $_SESSION['pesan_error_global'] = "Cabang Olahraga dengan ID tersebut tidak ditemukan.";
            header("Location: " . rtrim($app_base_path, '/') . "/modules/cabor/daftar_cabor.php");
            exit();
        }

        $page_title_actual = "Detail: " . htmlspecialchars($cabor_detail['nama_cabor'] ?? 'Tidak Diketahui');

        // Ambil data terkait (jumlah klub, atlet, pelatih, wasit) - sama seperti sebelumnya
        // ... (kode untuk jumlah klub, atlet, pelatih, wasit) ...
        try { $stmt_klub_count = $pdo->prepare("SELECT COUNT(*) FROM klub WHERE id_cabor = :id_cabor AND status_approval_admin = 'disetujui'"); $stmt_klub_count->bindParam(':id_cabor', $id_cabor_to_view, PDO::PARAM_INT); $stmt_klub_count->execute(); $jumlah_klub_cabor = (int)$stmt_klub_count->fetchColumn(); } catch (PDOException $e) { $data_terkait_error_messages[] = "Gagal memuat jumlah klub.";}
        try { $stmt_atlet_count = $pdo->prepare("SELECT COUNT(*) FROM atlet WHERE id_cabor = :id_cabor AND status_pendaftaran = 'disetujui'"); $stmt_atlet_count->bindParam(':id_cabor', $id_cabor_to_view, PDO::PARAM_INT); $stmt_atlet_count->execute(); $jumlah_atlet_cabor = (int)$stmt_atlet_count->fetchColumn(); } catch (PDOException $e) { $data_terkait_error_messages[] = "Gagal memuat jumlah atlet.";}
        try { $stmt_pelatih_count = $pdo->prepare("SELECT COUNT(*) FROM pelatih WHERE id_cabor = :id_cabor AND status_approval = 'disetujui'"); $stmt_pelatih_count->bindParam(':id_cabor', $id_cabor_to_view, PDO::PARAM_INT); $stmt_pelatih_count->execute(); $jumlah_pelatih_cabor = (int)$stmt_pelatih_count->fetchColumn(); } catch (PDOException $e) { $data_terkait_error_messages[] = "Gagal memuat jumlah pelatih.";}
        try { $stmt_wasit_count = $pdo->prepare("SELECT COUNT(*) FROM wasit WHERE id_cabor = :id_cabor AND status_approval = 'disetujui'"); $stmt_wasit_count->bindParam(':id_cabor', $id_cabor_to_view, PDO::PARAM_INT); $stmt_wasit_count->execute(); $jumlah_wasit_cabor = (int)$stmt_wasit_count->fetchColumn(); } catch (PDOException $e) { $data_terkait_error_messages[] = "Gagal memuat jumlah wasit.";}


        // Ambil log pembuatan paling awal dari audit_log
        if ($id_cabor_to_view) {
            try {
                $sql_log_pembuatan = "SELECT
                                        al.waktu_aksi, 
                                        al.aksi AS aksi_log,
                                        al.user_nik AS nik_pelaku_log, 
                                        pelaku_pengguna.nama_lengkap AS nama_pelaku_log
                                    FROM audit_log al
                                    LEFT JOIN pengguna pelaku_pengguna ON al.user_nik = pelaku_pengguna.nik AND pelaku_pengguna.is_approved = 1
                                    WHERE al.tabel_yang_diubah = 'cabang_olahraga' 
                                      AND al.id_data_yang_diubah = :id_cabor_log
                                      AND (al.aksi LIKE '%TAMBAH CABOR%' OR al.aksi LIKE '%CREATE CABOR%' OR al.aksi LIKE '%INSERT CABOR%') /* Sesuaikan dengan string aksi Anda */
                                    ORDER BY al.waktu_aksi ASC 
                                    LIMIT 1"; 
                
                $stmt_log_pembuatan = $pdo->prepare($sql_log_pembuatan);
                $id_cabor_str = strval($id_cabor_to_view);
                $stmt_log_pembuatan->bindParam(':id_cabor_log', $id_cabor_str, PDO::PARAM_STR);
                $stmt_log_pembuatan->execute();
                $log_pembuatan_cabor = $stmt_log_pembuatan->fetch(PDO::FETCH_ASSOC);
                
            } catch (PDOException $e_audit) {
                error_log("DETAIL_CABOR_AUDIT_LOG_PEMBUATAN_ERROR: ID {$id_cabor_to_view}. Pesan: " . $e_audit->getMessage());
                $data_terkait_error_messages[] = "Gagal memuat histori pembuatan cabor: " . $e_audit->getMessage();
            }
        }

        // Siapkan variabel pengurus inti
        $nama_ketua = !empty($cabor_detail['nama_ketua_db']) ? htmlspecialchars($cabor_detail['nama_ketua_db']) : (!empty($cabor_detail['ketua_cabor_nik']) ? 'NIK: ' . htmlspecialchars($cabor_detail['ketua_cabor_nik']) : '-');
        $nik_ketua = !empty($cabor_detail['nik_ketua_db']) ? htmlspecialchars($cabor_detail['nik_ketua_db']) : htmlspecialchars($cabor_detail['ketua_cabor_nik'] ?? '-');
        $jabatan_ketua = !empty($cabor_detail['jabatan_ketua_db']) ? htmlspecialchars($cabor_detail['jabatan_ketua_db']) : '-';
        // ... (ulangi untuk sekretaris dan bendahara)
        $nama_sekretaris = !empty($cabor_detail['nama_sekretaris_db']) ? htmlspecialchars($cabor_detail['nama_sekretaris_db']) : (!empty($cabor_detail['sekretaris_cabor_nik']) ? 'NIK: ' . htmlspecialchars($cabor_detail['sekretaris_cabor_nik']) : '-');
        $nik_sekretaris = !empty($cabor_detail['nik_sekretaris_db']) ? htmlspecialchars($cabor_detail['nik_sekretaris_db']) : htmlspecialchars($cabor_detail['sekretaris_cabor_nik'] ?? '-');
        $jabatan_sekretaris = !empty($cabor_detail['jabatan_sekretaris_db']) ? htmlspecialchars($cabor_detail['jabatan_sekretaris_db']) : '-';

        $nama_bendahara = !empty($cabor_detail['nama_bendahara_db']) ? htmlspecialchars($cabor_detail['nama_bendahara_db']) : (!empty($cabor_detail['bendahara_cabor_nik']) ? 'NIK: ' . htmlspecialchars($cabor_detail['bendahara_cabor_nik']) : '-');
        $nik_bendahara = !empty($cabor_detail['nik_bendahara_db']) ? htmlspecialchars($cabor_detail['nik_bendahara_db']) : htmlspecialchars($cabor_detail['bendahara_cabor_nik'] ?? '-');
        $jabatan_bendahara = !empty($cabor_detail['jabatan_bendahara_db']) ? htmlspecialchars($cabor_detail['jabatan_bendahara_db']) : '-';

        // Logika Logo Cabor (sama seperti sebelumnya)
        $default_placeholder_logo_cabor_path_relative = 'assets/adminlte/dist/img/default-150x150.png';
        $url_logo_cabor_tampil = htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($default_placeholder_logo_cabor_path_relative, '/'));
        $logo_message_detail = '<p class="text-muted text-sm mt-1">Logo belum diupload</p>';
        $path_logo_dari_db_detail = $cabor_detail['logo_cabor'] ?? null;
        if (!empty($path_logo_dari_db_detail)) {
            $server_document_root = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
            $path_aplikasi_di_server = $server_document_root . (substr($app_base_path, 0, 1) === '/' ? '' : '/') . trim($app_base_path, '/\\');
            $path_file_logo_di_server = rtrim($path_aplikasi_di_server, '/') . '/' . ltrim($path_logo_dari_db_detail, '/');
            if (file_exists(preg_replace('/\/+/', '/', $path_file_logo_di_server))) {
                $url_logo_cabor_tampil = htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($path_logo_dari_db_detail, '/'));
                $logo_message_detail = '';
            } else {
                $logo_message_detail = '<p class="text-danger text-sm mt-1">File logo (' . basename(htmlspecialchars($path_logo_dari_db_detail)) . ') tidak ditemukan.</p>';
            }
        }

    } catch (PDOException $e_main_cabor) {
        $_SESSION['pesan_error_global'] = "Terjadi kesalahan saat mengambil data cabor: " . $e_main_cabor->getMessage();
        error_log("DETAIL_CABOR_MAIN_QUERY_ERROR: ID {$id_cabor_to_view}. Pesan: " . $e_main_cabor->getMessage());
        header("Location: " . rtrim($app_base_path, '/') . "/modules/cabor/daftar_cabor.php");
        exit();
    }

} else { 
    $_SESSION['pesan_error_global'] = "ID Cabang Olahraga tidak diberikan.";
    header("Location: " . rtrim($app_base_path, '/') . "/modules/cabor/daftar_cabor.php");
    exit();
}

$page_title = $page_title_actual; 
require_once(__DIR__ . '/../../core/header.php'); 
?>

    <div class="row">
        <div class="col-md-10 offset-md-1">
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail:
                        <?php echo htmlspecialchars($cabor_detail['nama_cabor'] ?? 'Data Tidak Tersedia'); ?> (Kode:
                        <?php echo htmlspecialchars($cabor_detail['kode_cabor'] ?? '-'); ?>)
                    </h3>
                    <div class="card-tools">
                        <a href="daftar_cabor.php" class="btn btn-sm btn-secondary"> <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar</a>
                        <?php if (isset($user_role_utama) && ($user_role_utama == 'super_admin' || $user_role_utama == 'admin_koni')): ?>
                            <a href="edit_cabor.php?id_cabor=<?php echo htmlspecialchars($cabor_detail['id_cabor'] ?? ''); ?>" class="btn btn-sm btn-warning ml-2"> <i class="fas fa-edit mr-1"></i> Edit Cabor Ini</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if(!empty($data_terkait_error_messages)): ?>
                        <div class="alert alert-warning alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                            <h5><i class="icon fas fa-exclamation-triangle"></i> Perhatian!</h5>
                            <ul><?php foreach($data_terkait_error_messages as $err_msg) echo "<li>".htmlspecialchars($err_msg)."</li>"; ?></ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($cabor_detail): ?>
                        <div class="row mb-3">
                            <div class="col-md-3 text-center">
                                <div style="width: 100%; max-width: 200px; margin-left: auto; margin-right: auto; padding: 5px; border: 1px solid #ced4da; border-radius: .25rem; background-color: #f8f9fa; min-height: 180px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                                    <img src="<?php echo $url_logo_cabor_tampil; ?>" alt="Logo Cabor" class="img-fluid" style="max-height: 150px; max-width: 100%; object-fit: contain; display: block; margin-bottom: <?php echo empty($logo_message_detail) ? '0' : '5px'; ?>;">
                                    <?php echo $logo_message_detail; ?>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <h3><?php echo htmlspecialchars($cabor_detail['nama_cabor'] ?? 'Data Tidak Tersedia'); ?></h3>
                                <p class="text-muted mb-1">Kode: <?php echo htmlspecialchars($cabor_detail['kode_cabor'] ?? '-'); ?></p>
                                <p class="text-muted mb-1"><i class="fas fa-map-marker-alt mr-1 text-secondary"></i> <?php echo nl2br(htmlspecialchars($cabor_detail['alamat_sekretariat'] ?? '-')); ?></p>
                                <p class="text-muted mb-1"><i class="fas fa-phone mr-1 text-secondary"></i> <?php echo htmlspecialchars($cabor_detail['kontak_cabor'] ?? '-'); ?></p>
                                <p class="text-muted mb-0"><i class="fas fa-envelope mr-1 text-secondary"></i> <?php echo htmlspecialchars($cabor_detail['email_cabor'] ?? '-'); ?></p>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="card card-tabs card-info card-outline">
                                    <div class="card-header p-0 pt-1 border-bottom-0">
                                        <ul class="nav nav-tabs" id="cabor-detail-tabs" role="tablist">
                                            <li class="nav-item"><a class="nav-link active" id="kepengurusan-sk-tab" data-toggle="pill" href="#kepengurusan-sk" role="tab" aria-controls="kepengurusan-sk" aria-selected="true">Kepengurusan & SK</a></li>
                                            <li class="nav-item"><a class="nav-link" id="data-terkait-tab" data-toggle="pill" href="#data-terkait" role="tab" aria-controls="data-terkait" aria-selected="false">Data Terkait</a></li>
                                            <li class="nav-item"><a class="nav-link" id="histori-tab" data-toggle="pill" href="#histori" role="tab" aria-controls="histori" aria-selected="false">Status & Histori</a></li>
                                        </ul>
                                    </div>
                                    <div class="card-body">
                                        <div class="tab-content" id="cabor-detail-tabsContent">
                                            <div class="tab-pane fade show active" id="kepengurusan-sk" role="tabpanel" aria-labelledby="kepengurusan-sk-tab">
                                                <h5><i class="fas fa-users mr-1"></i> Kepengurusan Inti</h5>
                                                <dl class="row">
                                                    <dt class="col-sm-3">Ketua</dt>
                                                    <dd class="col-sm-9"><?php echo $nama_ketua; ?> <?php if($nik_ketua !== '-' && $nik_ketua !== '') echo "<br><small class='text-muted'>(NIK: ".$nik_ketua.", Jabatan: ".$jabatan_ketua.")</small>"; ?></dd>
                                                    <dt class="col-sm-3">Sekretaris</dt>
                                                    <dd class="col-sm-9"><?php echo $nama_sekretaris; ?> <?php if($nik_sekretaris !== '-' && $nik_sekretaris !== '') echo "<br><small class='text-muted'>(NIK: ".$nik_sekretaris.", Jabatan: ".$jabatan_sekretaris.")</small>"; ?></dd>
                                                    <dt class="col-sm-3">Bendahara</dt>
                                                    <dd class="col-sm-9"><?php echo $nama_bendahara; ?> <?php if($nik_bendahara !== '-' && $nik_bendahara !== '') echo "<br><small class='text-muted'>(NIK: ".$nik_bendahara.", Jabatan: ".$jabatan_bendahara.")</small>"; ?></dd>
                                                </dl>
                                                <hr>
                                                <h5><i class="fas fa-file-alt mr-1"></i> Informasi SK Provinsi</h5>
                                                <dl class="row">
                                                    <dt class="col-sm-3">Nomor SK</dt>
                                                    <dd class="col-sm-9"><?php echo htmlspecialchars($cabor_detail['nomor_sk_provinsi'] ?? '-'); ?></dd>
                                                    <dt class="col-sm-3">Tanggal SK</dt>
                                                    <dd class="col-sm-9"><?php echo isset($cabor_detail['tanggal_sk_provinsi']) && $cabor_detail['tanggal_sk_provinsi'] ? date('d F Y', strtotime($cabor_detail['tanggal_sk_provinsi'])) : '-'; ?></dd>
                                                    <dt class="col-sm-3">File SK</dt>
                                                    <dd class="col-sm-9">
                                                        <?php
                                                        $path_file_sk_db = $cabor_detail['path_file_sk_provinsi'] ?? null;
                                                        if (!empty($path_file_sk_db)) {
                                                            // ... (logika file SK sama)
                                                            $server_doc_root_sk = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
                                                            $app_path_server_sk = $server_doc_root_sk . (substr($app_base_path, 0, 1) === '/' ? '' : '/') . trim($app_base_path, '/\\');
                                                            $file_sk_server_path = rtrim($app_path_server_sk, '/') . '/' . ltrim($path_file_sk_db, '/');
                                                            if (file_exists(preg_replace('/\/+/', '/', $file_sk_server_path))) {
                                                                $url_file_sk = htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($path_file_sk_db, '/'));
                                                                echo '<a href="' . preg_replace('/\/+/', '/', $url_file_sk) . '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye mr-1"></i> Lihat/Unduh SK (' . htmlspecialchars(basename($path_file_sk_db)) . ')</a>';
                                                            } else {
                                                                echo '<span class="text-danger"><i class="fas fa-times-circle mr-1"></i> File SK (' . basename(htmlspecialchars($path_file_sk_db)) . ') tidak ditemukan di server.</span>';
                                                            }
                                                        } else {
                                                            echo '<span class="text-muted">File SK belum diupload.</span>';
                                                        }
                                                        ?>
                                                    </dd>
                                                </dl>
                                            </div>
                                            <div class="tab-pane fade" id="data-terkait" role="tabpanel" aria-labelledby="data-terkait-tab">
                                                 <h5><i class="fas fa-sitemap mr-1"></i> Data Terkait dengan Cabor Ini</h5>
                                                <ul class="list-group list-group-flush">
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">Jumlah Klub Terdaftar <span class="badge badge-primary badge-pill"><?php echo htmlspecialchars($jumlah_klub_cabor); ?></span></li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">Jumlah Atlet Aktif <span class="badge badge-info badge-pill"><?php echo htmlspecialchars($jumlah_atlet_cabor); ?></span></li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">Jumlah Pelatih Terdaftar <span class="badge badge-warning badge-pill"><?php echo htmlspecialchars($jumlah_pelatih_cabor); ?></span></li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">Jumlah Wasit Terdaftar <span class="badge badge-success badge-pill"><?php echo htmlspecialchars($jumlah_wasit_cabor); ?></span></li>
                                                </ul>
                                            </div>
                                            <div class="tab-pane fade" id="histori" role="tabpanel" aria-labelledby="histori-tab">
                                                <h5><i class="fas fa-info-circle mr-1"></i> Informasi Status & Proses Cabor</h5>
                                                <dl class="row">
                                                    <dt class="col-sm-4 col-lg-3">Status Kepengurusan:</dt>
                                                    <dd class="col-sm-8 col-lg-9">
                                                        <?php
                                                        $status_kep_cabor_detail = $cabor_detail['status_kepengurusan'] ?? 'Belum Ditentukan';
                                                        $badge_class_status_cabor_detail = 'secondary';
                                                        if ($status_kep_cabor_detail == 'Aktif') $badge_class_status_cabor_detail = 'success';
                                                        elseif ($status_kep_cabor_detail == 'Tidak Aktif') $badge_class_status_cabor_detail = 'danger';
                                                        elseif ($status_kep_cabor_detail == 'Masa Tenggang') $badge_class_status_cabor_detail = 'warning';
                                                        elseif ($status_kep_cabor_detail == 'Dibekukan') $badge_class_status_cabor_detail = 'dark';
                                                        ?>
                                                        <span class="badge badge-<?php echo $badge_class_status_cabor_detail; ?> p-1" style="font-size: 0.9em;"><?php echo htmlspecialchars(ucfirst($status_kep_cabor_detail)); ?></span>
                                                    </dd>

                                                    <dt class="col-sm-4 col-lg-3">Informasi Pembuatan Data:</dt>
                                                    <dd class="col-sm-8 col-lg-9">
                                                        <?php
                                                        if ($log_pembuatan_cabor) {
                                                            $nama_pembuat_log = !empty($log_pembuatan_cabor['nama_pelaku_log']) ? htmlspecialchars($log_pembuatan_cabor['nama_pelaku_log']) : 'Tidak diketahui';
                                                            $waktu_pembuatan_log = isset($log_pembuatan_cabor['waktu_aksi']) ? date('d M Y, H:i:s', strtotime($log_pembuatan_cabor['waktu_aksi'])) : '-';
                                                            echo "Dibuat oleh: <strong>" . $nama_pembuat_log . "</strong><br>";
                                                            echo "<small class='text-muted'>Pada: " . $waktu_pembuatan_log . " (berdasarkan log sistem)</small>";
                                                        } else {
                                                            echo "<span class='text-muted'><em>Informasi pembuatan spesifik tidak tercatat di log atau tidak ditemukan.</em></span>";
                                                        }
                                                        ?>
                                                    </dd>
                                                    
                                                    <dt class="col-sm-4 col-lg-3">Update Terakhir Data Cabor:</dt>
                                                    <dd class="col-sm-8 col-lg-9">
                                                        <?php 
                                                        $nama_updater_info = !empty($cabor_detail['nama_updater_terakhir']) ? htmlspecialchars($cabor_detail['nama_updater_terakhir']) : null;
                                                        $timestamp_update_info = $cabor_detail['last_updated_process_at'] ?? null;

                                                        if ($nama_updater_info && $timestamp_update_info): ?>
                                                            Oleh: <strong><?php echo $nama_updater_info; ?></strong><br>
                                                            <small class="text-muted">Pada: <?php echo date('d M Y, H:i:s', strtotime($timestamp_update_info)); ?></small>
                                                        <?php elseif ($timestamp_update_info && $cabor_detail['updated_by_nik']): // Jika ada NIK updater tapi nama tidak ketemu ?>
                                                            Oleh NIK: <strong><?php echo htmlspecialchars($cabor_detail['updated_by_nik']); ?></strong> (Nama tidak ditemukan)<br>
                                                            <small class="text-muted">Pada: <?php echo date('d M Y, H:i:s', strtotime($timestamp_update_info)); ?></small>
                                                        <?php elseif ($timestamp_update_info): // Jika hanya waktu update, tapi NIK updater tidak ada/kosong ?>
                                                             <span class="text-muted"><em>Data cabor diupdate pada <?php echo date('d M Y, H:i:s', strtotime($timestamp_update_info)); ?> (Pengguna updater tidak tercatat).</em></span>
                                                        <?php else: ?>
                                                            <span class="text-muted"><em>Belum ada informasi update pada data cabor ini.</em></span>
                                                        <?php endif; ?>
                                                    </dd>
                                                </dl>
                                                </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                         <div class="alert alert-danger text-center">
                            <h5><i class="icon fas fa-ban"></i> Error!</h5>
                            Data detail cabang olahraga tidak dapat ditampilkan.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-right">
                    <a href="daftar_cabor.php" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Cabor</a>
                </div>
            </div>
        </div>
    </div>

<?php
require_once(__DIR__ . '/../../core/footer.php');
?>