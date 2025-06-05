<?php
// File: reaktorsystem/modules/atlet/edit_atlet.php

$page_title = "Edit Data Atlet";
$current_page_is_edit_atlet = true;

$additional_css = [
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
    'assets/adminlte/plugins/bs-custom-file-input/bs-custom-file-input.min.js',
];

require_once(__DIR__ . '/../../core/header.php');

// Pengecekan sesi & konfigurasi inti
if (!isset($pdo) || !$pdo instanceof PDO || !isset($user_nik) || !isset($user_role_utama) || !isset($app_base_path) || !isset($default_avatar_path_relative) ) {
    $_SESSION['pesan_error_global'] = "Akses ditolak atau terjadi masalah konfigurasi sistem.";
    error_log("EDIT_ATLET_FATAL: Variabel inti tidak terdefinisi.");
    header("Location: " . ($app_base_path ?? '../../') . "dashboard.php");
    exit();
}

$id_atlet_to_edit = null;
$atlet_data = null; 

if (isset($_GET['id_atlet']) && filter_var($_GET['id_atlet'], FILTER_VALIDATE_INT) && (int)$_GET['id_atlet'] > 0) {
    $id_atlet_to_edit = (int)$_GET['id_atlet'];
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, 
                   p.nama_lengkap, p.email, p.tanggal_lahir, p.jenis_kelamin, p.alamat, p.nomor_telepon, p.foto AS foto_profil_pengguna,
                   co.nama_cabor, kl.nama_klub
            FROM atlet a
            JOIN pengguna p ON a.nik = p.nik
            JOIN cabang_olahraga co ON a.id_cabor = co.id_cabor
            LEFT JOIN klub kl ON a.id_klub = kl.id_klub
            WHERE a.id_atlet = :id_atlet
        ");
        $stmt->bindParam(':id_atlet', $id_atlet_to_edit, PDO::PARAM_INT);
        $stmt->execute();
        $atlet_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$atlet_data) {
            $_SESSION['pesan_error_global'] = "Data Atlet dengan ID " . htmlspecialchars($id_atlet_to_edit) . " tidak ditemukan.";
            header("Location: daftar_atlet.php");
            exit();
        }

        if ($user_role_utama == 'pengurus_cabor' && ($_SESSION['id_cabor_pengurus_utama'] ?? null) != $atlet_data['id_cabor']) {
            $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk mengedit atlet dari cabang olahraga lain.";
            header("Location: daftar_atlet.php" . (isset($_SESSION['id_cabor_pengurus_utama']) ? '?id_cabor=' . $_SESSION['id_cabor_pengurus_utama'] : ''));
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['pesan_error_global'] = "Gagal mengambil data atlet: " . htmlspecialchars($e->getMessage());
        error_log("EDIT_ATLET_FETCH_ERROR: " . $e->getMessage());
        header("Location: daftar_atlet.php");
        exit();
    }
} else {
    $_SESSION['pesan_error_global'] = "ID Atlet tidak valid atau tidak disertakan.";
    header("Location: daftar_atlet.php");
    exit();
}
if (!$atlet_data) {
    $_SESSION['pesan_error_global'] = "Terjadi kesalahan saat memuat data atlet yang akan diedit.";
    header("Location: daftar_atlet.php");
    exit();
}

$cabor_options_edit_atlet = [];
if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    try {
        $stmt_cabor_options_ae = $pdo->query("SELECT id_cabor, nama_cabor FROM cabang_olahraga WHERE status_kepengurusan = 'Aktif' ORDER BY nama_cabor ASC");
        if($stmt_cabor_options_ae) $cabor_options_edit_atlet = $stmt_cabor_options_ae->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log("EDIT_ATLET_CABOR_OPTIONS_ERROR: " . $e->getMessage()); }
}

$klub_options_edit_atlet_initial = []; 
if ($atlet_data['id_cabor']) {
    try {
        $stmt_klub_opt_ae_init = $pdo->prepare("SELECT id_klub, nama_klub FROM klub WHERE id_cabor = :id_cabor AND status_approval_admin = 'disetujui' ORDER BY nama_klub ASC");
        $stmt_klub_opt_ae_init->bindParam(':id_cabor', $atlet_data['id_cabor'], PDO::PARAM_INT);
        $stmt_klub_opt_ae_init->execute();
        $klub_options_edit_atlet_initial = $stmt_klub_opt_ae_init->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log("EDIT_ATLET_KLUB_OPTIONS_ERROR: " . $e->getMessage()); }
}

$form_data = $_SESSION['form_data_atlet_edit'] ?? $atlet_data;
$form_errors = $_SESSION['errors_edit_atlet'] ?? [];
$form_error_fields = $_SESSION['error_fields_edit_atlet'] ?? []; 
unset($_SESSION['form_data_atlet_edit'], $_SESSION['errors_edit_atlet'], $_SESSION['error_fields_edit_atlet']);

$nik_atlet_diedit_js = $atlet_data['nik'] ?? '';
$id_atlet_diedit_js = $id_atlet_to_edit ?? 0;
$id_cabor_asli_atlet_js = $atlet_data['id_cabor'] ?? 0;
$path_foto_default_edit_js = $default_avatar_path_relative ?? 'assets/adminlte/dist/img/kepitran.jpg';
?>

<section class="content">
    <div class="container-fluid">
        <?php 
            if (isset($_SESSION['pesan_sukses_global'])){ echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['pesan_sukses_global']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>'; unset($_SESSION['pesan_sukses_global']); }
            if (isset($_SESSION['pesan_error_global'])){ echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['pesan_error_global']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>'; unset($_SESSION['pesan_error_global']); }
            if (!empty($form_errors)){ echo '<div class="alert alert-danger alert-dismissible fade show" role="alert"><strong><i class="icon fas fa-ban"></i> Gagal Memperbarui Data:</strong><ul>'; foreach ($form_errors as $error_msg_item){ echo '<li>' . htmlspecialchars($error_msg_item) . '</li>'; } echo '</ul><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>'; }
        ?>

        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="card card-warning shadow"> 
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-edit mr-1"></i> <?php echo htmlspecialchars($page_title); ?>:
                            <strong><?php echo htmlspecialchars($atlet_data['nama_lengkap']); ?></strong>
                            <small class="text-muted">(NIK: <?php echo htmlspecialchars($atlet_data['nik']); ?>)</small>
                        </h3>
                        <div class="card-tools">
                             <a href="daftar_atlet.php<?php echo $atlet_data['id_cabor'] ? '?id_cabor=' . $atlet_data['id_cabor'] : ''; ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar
                            </a>
                            <?php 
                            $can_add_prestasi_on_edit_page = false;
                            if (in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor']) || $user_nik == $atlet_data['nik']) { $can_add_prestasi_on_edit_page = true; }
                            if ($can_add_prestasi_on_edit_page): ?>
                                 <a href="../prestasi/tambah_prestasi.php?id_atlet=<?php echo $atlet_data['id_atlet']; ?>&nik_atlet=<?php echo $atlet_data['nik']; ?>&id_cabor_atlet=<?php echo $atlet_data['id_cabor']; ?>" class="btn btn-sm btn-success ml-2">
                                     <i class="fas fa-trophy mr-1"></i> Tambah Prestasi
                                 </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <form id="formEditAtlet" action="proses_edit_atlet.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="id_atlet" value="<?php echo htmlspecialchars($id_atlet_to_edit); ?>">
                        <input type="hidden" name="nik_atlet_lama" value="<?php echo htmlspecialchars($atlet_data['nik']); ?>">
                        <input type="hidden" name="ktp_path_lama" value="<?php echo htmlspecialchars($atlet_data['ktp_path'] ?? ''); ?>">
                        <input type="hidden" name="kk_path_lama" value="<?php echo htmlspecialchars($atlet_data['kk_path'] ?? ''); ?>">
                        <input type="hidden" name="pas_foto_path_lama" value="<?php echo htmlspecialchars($atlet_data['pas_foto_path'] ?? ''); ?>">

                        <div class="card-body">
                            <h5 class="mt-1 mb-3 text-olive"><i class="fas fa-user-tag mr-1"></i> Informasi Pengguna Terkait (Read-only)</h5>
                            <div class="row mb-4">
                                <div class="col-md-3 text-center align-self-start">
                                    <?php
                                    // Logika Foto Atlet
                                    $foto_url_display_form_edit = APP_URL_BASE . '/' . ltrim($path_foto_default_edit_js, '/');
                                    $pesan_foto_form_edit = "Pas foto atlet belum diupload.";
                                    if (!empty($atlet_data['pas_foto_path'])) { $path_fisik_pas_foto = APP_PATH_BASE . '/' . ltrim($atlet_data['pas_foto_path'], '/'); if (file_exists(preg_replace('/\/+/', '/', $path_fisik_pas_foto)) && is_file(preg_replace('/\/+/', '/', $path_fisik_pas_foto))) { $foto_url_display_form_edit = APP_URL_BASE . '/' . ltrim($atlet_data['pas_foto_path'], '/'); $pesan_foto_form_edit = ""; } else { $pesan_foto_form_edit = "File pas foto atlet (".basename(htmlspecialchars($atlet_data['pas_foto_path'])).") tidak ditemukan."; } }
                                    elseif (!empty($atlet_data['foto_profil_pengguna'])) { $path_fisik_foto_pgn = APP_PATH_BASE . '/' . ltrim($atlet_data['foto_profil_pengguna'], '/'); if (file_exists(preg_replace('/\/+/', '/', $path_fisik_foto_pgn)) && is_file(preg_replace('/\/+/', '/', $path_fisik_foto_pgn))) { $foto_url_display_form_edit = APP_URL_BASE . '/' . ltrim($atlet_data['foto_profil_pengguna'], '/'); $pesan_foto_form_edit = "Pas foto atlet belum ada, foto profil pengguna ditampilkan."; } else { $pesan_foto_form_edit = "Pas foto atlet & foto profil pengguna (".basename(htmlspecialchars($atlet_data['foto_profil_pengguna'])).") tidak ditemukan."; } }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($foto_url_display_form_edit); ?>"
                                         alt="Foto <?php echo htmlspecialchars($atlet_data['nama_lengkap']); ?>"
                                         class="img-fluid img-circle elevation-2 mb-2" 
                                         style="width: 150px; height: 150px; display: block; margin-left: auto; margin-right: auto; object-fit: cover; border: 5px solid #dee2e6; padding: 5px;"
                                         onerror="this.onerror=null; this.src='<?php echo htmlspecialchars(APP_URL_BASE . '/' . ltrim($path_foto_default_edit_js, '/')); ?>';">
                                    <?php if (!empty($pesan_foto_form_edit)): ?>
                                        <p class="text-muted text-sm mt-1"><?php echo htmlspecialchars($pesan_foto_form_edit); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9">
                                    <h4 class="mb-3"><?php echo htmlspecialchars($atlet_data['nama_lengkap']); ?></h4>
                                    <p class="mb-1"><i class="fas fa-id-card fa-fw mr-2 text-muted"></i>NIK: <strong><?php echo htmlspecialchars($atlet_data['nik']); ?></strong></p>
                                    <p class="mb-1"><i class="fas fa-envelope fa-fw mr-2 text-muted"></i>Email: <?php echo htmlspecialchars($atlet_data['email'] ?? '<em>Tidak Ada</em>'); ?></p>
                                    <p class="mb-1"><i class="fas fa-phone fa-fw mr-2 text-muted"></i>No. Telepon: <?php echo htmlspecialchars($atlet_data['nomor_telepon'] ?? '<em>Tidak Ada</em>'); ?></p>
                                    <p class="mb-1"><i class="fas fa-birthday-cake fa-fw mr-2 text-muted"></i>Tanggal Lahir: <?php echo $atlet_data['tanggal_lahir'] ? date('d F Y', strtotime($atlet_data['tanggal_lahir'])) : '<em>Tidak Ada</em>'; ?></p>
                                    <p class="mb-1"><i class="fas fa-venus-mars fa-fw mr-2 text-muted"></i>Jenis Kelamin: <?php echo htmlspecialchars($atlet_data['jenis_kelamin'] ?? '<em>Tidak Ada</em>'); ?></p>
                                    <p class="mb-0"><i class="fas fa-map-marker-alt fa-fw mr-2 text-muted"></i>Alamat: <?php echo !empty(trim($atlet_data['alamat'] ?? '')) ? nl2br(htmlspecialchars($atlet_data['alamat'])) : '<em>Tidak Ada</em>'; ?></p>
                                    <small class="d-block mt-2 text-muted">Untuk mengubah data pengguna di atas, silakan edit melalui Manajemen Pengguna.</small>
                                </div>
                            </div>
                            
                            <hr>
                            <h5 class="mt-3 mb-3 text-olive"><i class="fas fa-running mr-1"></i> Data Keatletan</h5>
                             <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="id_cabor_atlet_edit">Cabang Olahraga <span class="text-danger">*</span></label>
                                        <?php // Mengisi kembali HTML dropdown Cabor dari kode Anda sebelumnya
                                        if (in_array($user_role_utama, ['super_admin', 'admin_koni'])): ?>
                                            <select class="form-control select2bs4edit <?php if(in_array('id_cabor', $form_error_fields)) echo 'is-invalid'; ?>" id="id_cabor_atlet_edit" name="id_cabor" style="width: 100%;" required data-placeholder="-- Pilih Cabang Olahraga --">
                                                <option value=""></option>
                                                <?php foreach ($cabor_options_edit_atlet as $cabor_opt): ?>
                                                    <option value="<?php echo htmlspecialchars($cabor_opt['id_cabor']); ?>" <?php echo ($form_data['id_cabor'] == $cabor_opt['id_cabor']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($cabor_opt['nama_cabor']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($atlet_data['nama_cabor']); ?>" readonly>
                                            <input type="hidden" name="id_cabor" value="<?php echo htmlspecialchars($atlet_data['id_cabor']); ?>">
                                        <?php endif; ?>
                                        <div id="cabor_edit_feedback" class="mt-1"></div>
                                        <?php if(in_array('id_cabor', $form_error_fields) && isset($form_errors[array_search('id_cabor', $form_error_fields)])): ?><span class="invalid-feedback d-block"><?php echo htmlspecialchars($form_errors[array_search('id_cabor', $form_error_fields)]); ?></span><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="id_klub_atlet_edit">Klub Afiliasi (Opsional)</label>
                                        <?php // Mengisi kembali HTML dropdown Klub dari kode Anda sebelumnya ?>
                                        <select class="form-control select2bs4edit <?php if(in_array('id_klub', $form_error_fields)) echo 'is-invalid'; ?>" id="id_klub_atlet_edit" name="id_klub" style="width: 100%;" data-placeholder="-- Pilih Klub (jika ada) --">
                                            <option value=""></option>
                                            <?php foreach ($klub_options_edit_atlet_initial as $klub_opt): ?>
                                                <option value="<?php echo htmlspecialchars($klub_opt['id_klub']); ?>" <?php echo (isset($form_data['id_klub']) && $form_data['id_klub'] == $klub_opt['id_klub']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($klub_opt['nama_klub']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni']) && empty($klub_options_edit_atlet_initial) && !empty($form_data['id_cabor']) && $form_data['id_cabor'] == $atlet_data['id_cabor']): ?>
                                            <small class="form-text text-warning">Tidak ada klub yang disetujui untuk cabor atlet saat ini.</small>
                                        <?php endif; ?>
                                        <?php if(in_array('id_klub', $form_error_fields) && isset($form_errors[array_search('id_klub', $form_error_fields)])): ?><span class="invalid-feedback d-block"><?php echo htmlspecialchars($form_errors[array_search('id_klub', $form_error_fields)]); ?></span><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            <h5 class="mt-3 mb-3 text-olive"><i class="fas fa-folder-open mr-1"></i> Berkas Pendukung (Upload baru jika ingin mengganti)</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="pas_foto_path_edit">Pas Foto Baru<?php if(empty($atlet_data['pas_foto_path'])) echo ' <span class="text-danger">*</span>'; ?></label>
                                        <?php // Mengisi kembali HTML Input Pas Foto & Tampilan Lama dari kode Anda sebelumnya ?>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input <?php if(in_array('pas_foto_path', $form_error_fields)) echo 'is-invalid'; ?>" id="pas_foto_path_edit" name="pas_foto_path" accept=".jpg,.jpeg,.png,.gif" <?php if(empty($atlet_data['pas_foto_path'])) echo 'required'; ?>>
                                            <label class="custom-file-label" for="pas_foto_path_edit">Pilih file (Max: <?php echo MAX_FILE_SIZE_FOTO_PROFIL_MB; ?>MB)</label>
                                        </div>
                                        <?php $current_pas_foto = $atlet_data['pas_foto_path'] ?? ''; if (!empty($current_pas_foto)) { $pas_foto_url = $app_base_path . ltrim($current_pas_foto, '/'); if (file_exists(APP_PATH_BASE . '/' . ltrim($current_pas_foto, '/'))) { echo '<div class="mt-2"><img src="'.htmlspecialchars($pas_foto_url).'" alt="Pas Foto Lama" style="max-height: 70px; border: 1px solid #ddd; padding: 2px; border-radius: .25rem;"><br><small class="text-muted">Pas Foto saat ini: '.basename($current_pas_foto).'</small></div>'; } else { echo '<small class="text-danger mt-1">File pas foto lama tidak ditemukan.</small>'; } } else { echo '<small class="form-text text-warning mt-1">Pas foto belum diupload.</small>';} ?>
                                        <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah. <?php if(empty($atlet_data['pas_foto_path'])) echo 'Wajib diisi jika belum ada.'; ?></small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="ktp_path_edit">Scan KTP Baru</label>
                                        <?php // Mengisi kembali HTML Input KTP & Tampilan Lama dari kode Anda sebelumnya ?>
                                        <div class="custom-file"> <input type="file" class="custom-file-input <?php if(in_array('ktp_path', $form_error_fields)) echo 'is-invalid'; ?>" id="ktp_path_edit" name="ktp_path" accept=".pdf,.jpg,.jpeg,.png"> <label class="custom-file-label" for="ktp_path_edit">Pilih file (Max: <?php echo MAX_FILE_SIZE_KTP_KK_MB; ?>MB)</label> </div> <?php $current_ktp = $atlet_data['ktp_path'] ?? ''; if(!empty($current_ktp)){ $ktp_url = $app_base_path . ltrim($current_ktp, '/'); if(file_exists(APP_PATH_BASE . '/' . ltrim($current_ktp, '/'))){ echo '<small class="form-text text-muted mt-1">KTP Saat Ini: <a href="'.htmlspecialchars($ktp_url).'" target="_blank">'.basename($current_ktp).'</a></small>'; } else { echo '<small class="text-danger mt-1">File KTP lama tidak ditemukan.</small>'; } } else { echo '<small class="text-muted mt-1">KTP belum diupload.</small>'; } ?> <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah.</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="kk_path_edit">Scan KK Baru</label>
                                        <?php // Mengisi kembali HTML Input KK & Tampilan Lama dari kode Anda sebelumnya ?>
                                        <div class="custom-file"> <input type="file" class="custom-file-input <?php if(in_array('kk_path', $form_error_fields)) echo 'is-invalid'; ?>" id="kk_path_edit" name="kk_path" accept=".pdf,.jpg,.jpeg,.png"> <label class="custom-file-label" for="kk_path_edit">Pilih file (Max: <?php echo MAX_FILE_SIZE_KTP_KK_MB; ?>MB)</label> </div> <?php $current_kk = $atlet_data['kk_path'] ?? ''; if(!empty($current_kk)){ $kk_url = $app_base_path . ltrim($current_kk, '/'); if(file_exists(APP_PATH_BASE . '/' . ltrim($current_kk, '/'))){ echo '<small class="form-text text-muted mt-1">KK Saat Ini: <a href="'.htmlspecialchars($kk_url).'" target="_blank">'.basename($current_kk).'</a></small>'; } else { echo '<small class="text-danger mt-1">File KK lama tidak ditemukan.</small>'; } } else { echo '<small class="text-muted mt-1">KK belum diupload.</small>'; } ?> <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah.</small>
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <h5 class="mt-3 mb-3 text-olive"><i class="fas fa-tasks mr-1"></i> Status Pendaftaran Atlet</h5>
                            <div class="form-group">
                                <label>Status Saat Ini:</label>
                                <?php // Mengisi kembali HTML Tampilan Status Saat Ini dari kode Anda sebelumnya ?>
                                <p><span class="badge badge-<?php echo ($atlet_data['status_pendaftaran'] == 'disetujui' ? 'success' : (($atlet_data['status_pendaftaran'] == 'pending' || $atlet_data['status_pendaftaran'] == 'verifikasi_pengcab' || $atlet_data['status_pendaftaran'] == 'revisi') ? 'warning' : 'danger')); ?> p-1"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $atlet_data['status_pendaftaran']))); ?></span> <?php if($atlet_data['status_pendaftaran'] == 'ditolak_pengcab' && !empty($atlet_data['alasan_penolakan_pengcab'])) { echo '<br><small class="text-danger">Alasan Pengcab: '.htmlspecialchars($atlet_data['alasan_penolakan_pengcab']).'</small>'; } elseif(($atlet_data['status_pendaftaran'] == 'ditolak_admin' || $atlet_data['status_pendaftaran'] == 'revisi') && !empty($atlet_data['alasan_penolakan_admin'])) { echo '<br><small class="text-danger">Alasan Admin: '.htmlspecialchars($atlet_data['alasan_penolakan_admin']).'</small>'; } ?> </p>
                            </div>
                            <?php // Mengisi kembali Blok PHP dan HTML untuk Dropdown Ubah Status dan Textarea Alasan dari kode Anda sebelumnya ?>
                            <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni'])): ?>
                                <div class="form-group"> <label for="status_pendaftaran_edit_admin">Ubah Status (Admin)</label> <select class="form-control select2bs4edit" id="status_pendaftaran_edit_admin" name="status_pendaftaran"> <option value="">-- Biarkan Status Saat Ini --</option> <option value="pending" <?php echo ($form_data['status_pendaftaran'] == 'pending') ? 'selected' : ''; ?>>Pending</option> <option value="verifikasi_pengcab" <?php echo ($form_data['status_pendaftaran'] == 'verifikasi_pengcab') ? 'selected' : ''; ?>>Verifikasi Pengcab</option> <option value="disetujui" <?php echo ($form_data['status_pendaftaran'] == 'disetujui') ? 'selected' : ''; ?>>Setujui</option> <option value="ditolak_admin" <?php echo ($form_data['status_pendaftaran'] == 'ditolak_admin') ? 'selected' : ''; ?>>Ditolak Admin</option> <option value="revisi" <?php echo ($form_data['status_pendaftaran'] == 'revisi') ? 'selected' : ''; ?>>Revisi</option> <?php if ($user_role_utama == 'super_admin'): ?><option value="ditolak_pengcab" <?php echo ($form_data['status_pendaftaran'] == 'ditolak_pengcab') ? 'selected' : ''; ?>>Ditolak Pengcab (SA)</option><?php endif; ?> </select> </div>
                                <div class="form-group alasan-group-admin" style="display:none;"> <label for="alasan_penolakan_admin_edit">Alasan Admin</label> <textarea class="form-control <?php if(isset($form_error_fields['alasan_penolakan_admin'])) echo 'is-invalid'; ?>" id="alasan_penolakan_admin_edit" name="alasan_penolakan_admin" rows="2"><?php echo htmlspecialchars($form_data['alasan_penolakan_admin'] ?? ''); ?></textarea> </div>
                                <?php if ($user_role_utama == 'super_admin'): ?> <div class="form-group alasan-group-sa-pengcab" style="display:none;"> <label for="alasan_penolakan_pengcab_edit">Alasan Pengcab (SA)</label> <textarea class="form-control <?php if(isset($form_error_fields['alasan_penolakan_pengcab'])) echo 'is-invalid'; ?>" id="alasan_penolakan_pengcab_edit" name="alasan_penolakan_pengcab" rows="2"><?php echo htmlspecialchars($form_data['alasan_penolakan_pengcab'] ?? ''); ?></textarea> </div> <?php else: ?> <input type="hidden" name="alasan_penolakan_pengcab" value="<?php echo htmlspecialchars($form_data['alasan_penolakan_pengcab'] ?? ''); ?>"> <?php endif; ?>
                            <?php elseif ($user_role_utama == 'pengurus_cabor'): ?>
                                <div class="form-group"> <label for="status_pendaftaran_edit_pengcab">Ubah Status (Pengcab)</label> <select class="form-control select2bs4edit" id="status_pendaftaran_edit_pengcab" name="status_pendaftaran" <?php if (in_array($atlet_data['status_pendaftaran'], ['disetujui', 'ditolak_admin'])) echo 'disabled'; ?>> <option value="">-- Biarkan Status Saat Ini --</option> <option value="pending" <?php echo ($form_data['status_pendaftaran'] == 'pending' || ($atlet_data['status_pendaftaran'] == 'revisi' && empty($form_data['status_pendaftaran']))) ? 'selected' : ''; ?>>Pending/Revisi</option> <option value="verifikasi_pengcab" <?php echo ($form_data['status_pendaftaran'] == 'verifikasi_pengcab') ? 'selected' : ''; ?>>Verifikasi</option> <option value="ditolak_pengcab" <?php echo ($form_data['status_pendaftaran'] == 'ditolak_pengcab') ? 'selected' : ''; ?>>Ditolak</option> </select> <?php if (in_array($atlet_data['status_pendaftaran'], ['disetujui', 'ditolak_admin'])): ?><small class="form-text text-muted">Status final oleh Admin.</small><?php endif; ?> </div>
                                <div class="form-group alasan-group-pengcab" style="display:none;"> <label for="alasan_penolakan_pengcab_edit_pc">Alasan Pengcab</label> <textarea class="form-control <?php if(isset($form_error_fields['alasan_penolakan_pengcab'])) echo 'is-invalid'; ?>" id="alasan_penolakan_pengcab_edit_pc" name="alasan_penolakan_pengcab" rows="2"><?php echo htmlspecialchars($form_data['alasan_penolakan_pengcab'] ?? ''); ?></textarea> </div> <input type="hidden" name="alasan_penolakan_admin" value="<?php echo htmlspecialchars($form_data['alasan_penolakan_admin'] ?? ''); ?>">
                            <?php endif; ?>
                            
                            <p class="text-muted text-sm mt-4"><small>* Wajib diisi jika field relevan atau ada perubahan status.</small></p>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="submit_edit_atlet" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                            <a href="daftar_atlet.php<?php echo $atlet_data['id_cabor'] ? '?id_cabor=' . $atlet_data['id_cabor'] : ''; ?>" class="btn btn-secondary float-right"><i class="fas fa-times mr-1"></i> Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// JavaScript Anda yang sudah ada dan berjalan
$inline_script = "
$(function () {
  if (typeof bsCustomFileInput !== 'undefined' && $.isFunction(bsCustomFileInput.init)) {
    bsCustomFileInput.init();
  }
  if (typeof $.fn.select2 === 'function') {
    $('.select2bs4edit').select2({
      theme: 'bootstrap4',
      allowClear: true,
      placeholder: $(this).data('placeholder') || '-- Pilih --'
    });
  }

  function initializeAlasanToggle(statusSelectSelector, alasanGroupSelector, triggerStatusesString) {
    var statusSelect = $(statusSelectSelector);
    var alasanGroup = $(alasanGroupSelector);
    var triggerStatuses = triggerStatusesString.split(',');
    function toggleAlasan() {
        if (statusSelect.length && alasanGroup.length) {
            var selectedStatusVal = statusSelect.val();
            alasanGroup.toggle(triggerStatuses.includes(selectedStatusVal));
        }
    }
    if (statusSelect.length) { statusSelect.on('change', toggleAlasan); toggleAlasan(); }
  }
  initializeAlasanToggle('#status_pendaftaran_edit_admin', '.alasan-group-admin', 'ditolak_admin,revisi');
  initializeAlasanToggle('#status_pendaftaran_edit_pengcab', '.alasan-group-pengcab', 'ditolak_pengcab');

  var isAdminOrSuperAdminCanEditCabor = " . json_encode(in_array($user_role_utama, ['super_admin', 'admin_koni'])) . ";
  var nikAtletSaatIni = '" . addslashes($nik_atlet_diedit_js) . "';
  var idAtletSaatIni = parseInt('" . addslashes($id_atlet_diedit_js) . "') || 0;
  var idCaborAsliAtlet = parseInt('" . addslashes($id_cabor_asli_atlet_js) . "') || 0;
  var submitButtonEditForm = $('#formEditAtlet button[name=\"submit_edit_atlet\"]');

  function cekDuplikasiCaborAtletEdit(selectedCaborId) {
    var caborDropdown = $('#id_cabor_atlet_edit');
    var caborFeedbackElement = $('#cabor_edit_feedback'); 
    
    caborFeedbackElement.html('').removeClass('text-danger text-warning text-success text-muted');
    submitButtonEditForm.prop('disabled', false).removeClass('disabled btn-secondary').addClass('btn-warning');

    if (!selectedCaborId || !nikAtletSaatIni || !isAdminOrSuperAdminCanEditCabor) { return; }
    if (parseInt(selectedCaborId) === idCaborAsliAtlet) { return; }
    
    caborFeedbackElement.html('<em class=\"text-muted\">Memeriksa ketersediaan cabor...</em>');
    $.ajax({
        url: '" . rtrim(APP_URL_BASE, '/') . "/ajax/cek_pengguna_by_nik.php',
        type: 'POST',
        data: { nik: nikAtletSaatIni, id_cabor: selectedCaborId, id_atlet_edit: idAtletSaatIni, context: 'cek_edit_atlet_cabor' },
        dataType: 'json',
        success: function(response) {
            caborFeedbackElement.empty(); 
            if (response.status === 'success' && response.data_pengguna) {
                if (response.data_pengguna.is_atlet_selected_cabor) {
                    caborFeedbackElement.html('<small class=\"form-text text-danger\"><i class=\"fas fa-exclamation-triangle\"></i> Atlet ini sudah terdaftar di Cabor ini. Pilih cabor lain.</small>');
                    submitButtonEditForm.prop('disabled', true).removeClass('btn-warning').addClass('btn-secondary disabled');
                } else {
                     caborFeedbackElement.html('<small class=\"form-text text-success\"><i class=\"fas fa-check-circle\"></i> Cabor tersedia.</small>');
                }
            } else if (response.message) {
                caborFeedbackElement.html('<small class=\"form-text text-warning\">Info: ' + response.message + '</small>');
            }
        },
        error: function() {
            caborFeedbackElement.html('<small class=\"form-text text-danger\">Gagal validasi cabor.</small>');
        }
    });
  }

  if (isAdminOrSuperAdminCanEditCabor) {
    $('#id_cabor_atlet_edit').on('change', function () {
        var selectedCaborId = $(this).val();
        cekDuplikasiCaborAtletEdit(selectedCaborId);
        var klubSelect = $('#id_klub_atlet_edit');
        klubSelect.val(null).trigger('change.select2').empty().append('<option value=\"\"></option>'); 
        if (selectedCaborId) {
            klubSelect.prop('disabled', true).select2({ placeholder: 'Memuat klub...', theme: 'bootstrap4', allowClear: true });
            $.ajax({
                url: '" . rtrim(APP_URL_BASE, '/') . "/ajax/get_klub_by_cabor.php',
                type: 'POST', data: { id_cabor: selectedCaborId }, dataType: 'json',
                success: function(response) {
                    klubSelect.prop('disabled', false).append('<option value=\"\">-- Pilih Klub --</option>');
                    if (response.status === 'success' && response.klub_list && response.klub_list.length > 0) {
                        $.each(response.klub_list, function(i, klub) { klubSelect.append(new Option(klub.nama_klub, klub.id_klub)); });
                    } else { klubSelect.append('<option value=\"\" disabled>Tidak ada klub</option>'); }
                    klubSelect.select2({ placeholder: '-- Pilih Klub --', theme: 'bootstrap4', allowClear: true });
                    var klubLamaEdit = '" . ($form_data['id_klub'] ?? '') . "';
                    if(klubLamaEdit && $('#id_cabor_atlet_edit').val() == '" . ($atlet_data['id_cabor'] ?? '') . "') {
                        if(klubSelect.find('option[value=\"' + klubLamaEdit + '\"]').length > 0) { klubSelect.val(klubLamaEdit).trigger('change.select2');}
                    }
                },
                error: function() { klubSelect.prop('disabled', false).select2({ placeholder: 'Error muat klub', theme: 'bootstrap4', allowClear: true });}
            });
        } else { klubSelect.prop('disabled', false).select2({ placeholder: '-- Pilih Klub --', theme: 'bootstrap4', allowClear: true });}
    });
    var initialCaborIdForEdit = $('#id_cabor_atlet_edit').val();
    if (initialCaborIdForEdit && parseInt(initialCaborIdForEdit) !== idCaborAsliAtlet) {
        cekDuplikasiCaborAtletEdit(initialCaborIdForEdit);
    }
    if (initialCaborIdForEdit) { $('#id_cabor_atlet_edit').trigger('change'); }
  }
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>