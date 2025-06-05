<?php
// File: public_html/reaktorsystem/profile/profil_saya.php
$page_title = "Profil Saya";

// Asumsi $additional_css dan $additional_js bisa kosong jika tidak ada yang spesifik untuk halaman ini
$additional_css = [];
$additional_js = [];


$additional_js = [
    // ... (JS lain jika ada) ...
    'assets/adminlte/plugins/html2canvas/html2canvas.min.js', // PASTIKAN PATH INI SESUAI
];

if (file_exists(__DIR__ . '/../core/header.php')) {
    require_once(__DIR__ . '/../core/header.php');
} else {
    if(session_status() == PHP_SESSION_NONE) session_start();
    $_SESSION['pesan_error_global'] = "Kesalahan sistem: File header tidak ditemukan.";
    $temp_app_base_path = (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/reaktorsystem/') !== false) ? '/reaktorsystem/' : '/';
    if(!headers_sent()) header("Location: " . rtrim($temp_app_base_path, '/') . "/auth/login.php");
    exit();
}

// Pengecekan variabel inti dari init_core.php (yang dipanggil oleh header.php)
if (!isset($pdo) || !$pdo instanceof PDO) { 
    echo '<section class="content"><div class="container-fluid"><div class="alert alert-danger text-center"><strong>Koneksi Database Gagal!</strong> Halaman profil tidak dapat dimuat.</div></div></section>';
    if (file_exists(__DIR__ . '/../core/footer.php')) { require_once(__DIR__ . '/../core/footer.php'); }
    exit();
}
if (!isset($user_login_status) || $user_login_status !== true || !isset($user_nik)) { 
    if(session_status() == PHP_SESSION_NONE) session_start(); 
    $_SESSION['pesan_error_global'] = "Sesi tidak valid atau Anda belum login.";
    if(!headers_sent()) header("Location: " . rtrim($app_base_path, '/') . "/auth/login.php");
    exit(); 
}

// Ambil data lengkap pengguna
$data_pribadi = null;
try {
    $stmt_pribadi = $pdo->prepare("SELECT * FROM pengguna WHERE nik = :nik");
    $stmt_pribadi->bindParam(':nik', $user_nik, PDO::PARAM_STR);
    $stmt_pribadi->execute();
    $data_pribadi = $stmt_pribadi->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("PROFIL_SAYA_ERROR: Gagal ambil data pribadi NIK {$user_nik}. Pesan: " . $e->getMessage());
    $_SESSION['pesan_error_global'] = "Gagal memuat data profil Anda karena kesalahan database.";
}

if (!$data_pribadi && !isset($_SESSION['pesan_error_global'])) {
    $_SESSION['pesan_error_global'] = "Data profil Anda tidak dapat ditemukan.";
}

// --- LOGIKA PENAMPILAN FOTO PROFIL (Menggunakan variabel dari init_core.php) ---
// $user_foto_profil_path_relative_to_app_root seharusnya sudah diset di init_core.php
// $app_base_path juga dari init_core.php
$url_foto_tampil = htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($user_foto_profil_path_relative_to_app_root, '/'));
$url_foto_tampil = preg_replace('/\/+/', '/', $url_foto_tampil);

$nama_lengkap_profil_display = htmlspecialchars($data_pribadi['nama_lengkap'] ?? ($nama_pengguna ?? 'Pengguna'));
$peran_profil_display = htmlspecialchars(ucwords(str_replace('_', ' ', $user_role_utama)));
$nama_cabor_utama_profil = null; 
if ($user_role_utama == 'pengurus_cabor' && isset($id_cabor_pengurus_utama) && !empty($id_cabor_pengurus_utama) && $pdo) {
    try {
        $stmt_nama_c = $pdo->prepare("SELECT nama_cabor FROM cabang_olahraga WHERE id_cabor = :id_cabor");
        $stmt_nama_c->execute([':id_cabor' => $id_cabor_pengurus_utama]);
        $nama_cabor_utama_profil = $stmt_nama_c->fetchColumn();
    } catch (PDOException $e) { error_log("PROFIL_SAYA_ERROR: Gagal ambil nama cabor pengurus. " . $e->getMessage()); }
}

// --- AWAL LOGIKA UNTUK ID CARD DIGITAL ---
$qr_library_available = false; 
if (file_exists(__DIR__ . '/../phpqrcode/qrlib.php')) {
    require_once(__DIR__ . '/../phpqrcode/qrlib.php');
    if (class_exists('QRcode')) { 
        $qr_library_available = true;
    } else {
         if (!defined('QR_CLASS_MISSING_LOGGED_PROFIL')) { error_log("PROFIL_SAYA_QR_ERROR: Class QRcode tidak ditemukan setelah include qrlib.php."); define('QR_CLASS_MISSING_LOGGED_PROFIL', true); }
    }
} else {
    if (!defined('QR_LIB_FILE_MISSING_LOGGED_PROFIL')) { error_log("PROFIL_SAYA_QR_ERROR: Library phpqrcode/qrlib.php tidak ditemukan di " . __DIR__ . '/../phpqrcode/qrlib.php'); define('QR_LIB_FILE_MISSING_LOGGED_PROFIL', true); }
}

$qr_image_url_for_idcard = ''; 
$qr_data_url_for_idcard = '';  
$qr_file_path_server_for_idcard = ''; 
$qr_temp_base_dir_app_relative = 'assets/uploads/temp_qr/'; 

if ($qr_library_available && isset($user_nik) && !empty($user_nik) && isset($app_base_path) && $data_pribadi) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $host = $_SERVER['HTTP_HOST'];
    $qr_data_url_for_idcard = rtrim($protocol . "://" . $host . $app_base_path, '/') . '/public_profile.php?id=' . urlencode($user_nik);
    
    $qr_temp_dir_server = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . '/' . ltrim($qr_temp_base_dir_app_relative, '/');
    $qr_temp_dir_server = preg_replace('/\/+/', '/', rtrim($qr_temp_dir_server, '/')) . '/';

    $qr_file_name_for_idcard = 'qr_user_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $user_nik) . '.png';
    $qr_file_path_server_for_idcard = $qr_temp_dir_server . $qr_file_name_for_idcard; 
    
    $qr_image_url_for_idcard = rtrim($app_base_path, '/') . '/' . $qr_temp_base_dir_app_relative . $qr_file_name_for_idcard; 
    $qr_image_url_for_idcard = preg_replace('/\/+/', '/', $qr_image_url_for_idcard);

    if (!file_exists($qr_temp_dir_server)) {
        if (!@mkdir($qr_temp_dir_server, 0755, true)) {
            error_log("PROFIL_SAYA_QR_ERROR: Gagal membuat direktori temp_qr di '" . $qr_temp_dir_server . "'. Periksa izin parent folder.");
            $qr_library_available = false; 
        }
    }

    if ($qr_library_available && is_writable($qr_temp_dir_server)) {
        $regenerate_qr_now = false;
        if (!file_exists($qr_file_path_server_for_idcard)) { $regenerate_qr_now = true; } 
        elseif (file_exists($qr_file_path_server_for_idcard) && (time() - @filemtime($qr_file_path_server_for_idcard) > 3600) ) { $regenerate_qr_now = true; }

        if ($regenerate_qr_now) {
            try {
                if (file_exists($qr_file_path_server_for_idcard)) { @unlink($qr_file_path_server_for_idcard); }
                QRcode::png($qr_data_url_for_idcard, $qr_file_path_server_for_idcard, QR_ECLEVEL_L, 6, 2);
                if (!file_exists($qr_file_path_server_for_idcard)) {
                     error_log("PROFIL_SAYA_QR_ERROR: QRcode::png dipanggil tetapi file '{$qr_file_name_for_idcard}' tidak berhasil dibuat di '{$qr_temp_dir_server}'.");
                     $qr_library_available = false; 
                }
            } catch (Exception $e) { 
                error_log("PROFIL_SAYA_QR_EXCEPTION: Exception saat generate QR Code: " . $e->getMessage());
                $qr_library_available = false;
            }
        }
    } elseif ($qr_library_available) {
        error_log("PROFIL_SAYA_QR_ERROR: Direktori temp_qr ('" . $qr_temp_dir_server . "') tidak dapat ditulis.");
        $qr_library_available = false; 
    }
} else {
    if (!$qr_library_available && !defined('QR_INIT_FAIL_LOGGED_PROFIL_V2')) { 
        error_log("PROFIL_SAYA_QR_INIT_FAIL: Library tidak tersedia atau data NIK/app_base_path/data_pribadi kurang untuk generate QR.");
        define('QR_INIT_FAIL_LOGGED_PROFIL_V2', true); 
    }
     $qr_library_available = false; 
}
// --- AKHIR LOGIKA UNTUK ID CARD DIGITAL ---

// Logika untuk menentukan tab aktif
$active_tab_profil = 'editdata'; 
if (isset($_SESSION['last_profil_tab']) && in_array($_SESSION['last_profil_tab'], ['editdata', 'ubahpassword', 'infoPeran', 'idcard'])) {
    $active_tab_profil = $_SESSION['last_profil_tab'];
    unset($_SESSION['last_profil_tab']); 
}
?>
    <?php 
    if (isset($_SESSION['pesan_sukses_global'])) { echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['pesan_sukses_global']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>'; unset($_SESSION['pesan_sukses_global']); }
    if (isset($_SESSION['pesan_error_global'])) { echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['pesan_error_global']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>'; unset($_SESSION['pesan_error_global']); }
    ?>
    
    <?php if ($data_pribadi): ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary card-outline">
                <div class="card-body box-profile">
                    <div class="text-center">
                        <img class="profile-user-img img-fluid img-circle" src="<?php echo $url_foto_tampil; ?>" alt="Foto Profil <?php echo $nama_lengkap_profil_display; ?>">
                    </div>
                    <h3 class="profile-username text-center"><?php echo $nama_lengkap_profil_display; ?></h3>
                    <p class="text-muted text-center">
                        <?php echo $peran_profil_display; 
                        if ($user_role_utama == 'pengurus_cabor' && !empty($nama_cabor_utama_profil)) { echo " - " . htmlspecialchars($nama_cabor_utama_profil); } ?>
                    </p>
                </div>
            </div>
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title">Tentang Saya</h3></div>
                <div class="card-body">
                    <strong><i class="fas fa-id-card mr-1"></i> NIK</strong><p class="text-muted"><?php echo htmlspecialchars($data_pribadi['nik']); ?></p><hr>
                    <strong><i class="fas fa-envelope mr-1"></i> Email</strong><p class="text-muted"><?php echo htmlspecialchars($data_pribadi['email'] ?? '-'); ?></p><hr>
                    <strong><i class="fas fa-phone mr-1"></i> No. Telepon</strong><p class="text-muted"><?php echo htmlspecialchars($data_pribadi['nomor_telepon'] ?? '-'); ?></p><hr>
                    <strong><i class="fas fa-map-marker-alt mr-1"></i> Alamat</strong><p class="text-muted"><?php echo nl2br(htmlspecialchars($data_pribadi['alamat'] ?? '-')); ?></p><hr>
                    <strong><i class="fas fa-calendar-alt mr-1"></i> Tanggal Lahir</strong><p class="text-muted"><?php echo $data_pribadi['tanggal_lahir'] ? date('d F Y', strtotime($data_pribadi['tanggal_lahir'])) : '-'; ?></p><hr>
                    <strong><i class="fas fa-venus-mars mr-1"></i> Jenis Kelamin</strong><p class="text-muted"><?php echo htmlspecialchars(ucfirst($data_pribadi['jenis_kelamin'] ?? '-')); ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header p-2">
                    <ul class="nav nav-pills">
                        <li class="nav-item"><a class="nav-link <?php if($active_tab_profil == 'editdata') echo 'active'; ?>" href="#editdata" data-toggle="tab">Edit Data Pribadi</a></li>
                        <li class="nav-item"><a class="nav-link <?php if($active_tab_profil == 'ubahpassword') echo 'active'; ?>" href="#ubahpassword" data-toggle="tab">Ubah Password</a></li>
                        <li class="nav-item"><a class="nav-link <?php if($active_tab_profil == 'infoPeran') echo 'active'; ?>" href="#infoPeran" data-toggle="tab">Informasi Peran</a></li>
                        <li class="nav-item"><a class="nav-link <?php if($active_tab_profil == 'idcard') echo 'active'; ?>" href="#idcard" data-toggle="tab">ID Card Digital</a></li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane <?php if($active_tab_profil == 'editdata') echo 'active show'; ?>" id="editdata">
                            <?php 
                            if (isset($_SESSION['pesan_sukses_profil_editdata'])) { echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['pesan_sukses_profil_editdata']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>'; unset($_SESSION['pesan_sukses_profil_editdata']); }
                            if (isset($_SESSION['pesan_error_profil_editdata'])) { echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['pesan_error_profil_editdata']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>'; unset($_SESSION['pesan_error_profil_editdata']); }
                            ?>
                            <form class="form-horizontal" action="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/profile/proses_update_profil.php'); ?>" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="nik_profil" value="<?php echo htmlspecialchars($data_pribadi['nik']); ?>">
                                <input type="hidden" name="submit_update_pribadi" value="1">
                                <div class="form-group row"><label for="inputNama" class="col-sm-3 col-form-label">Nama Lengkap</label><div class="col-sm-9"><input type="text" class="form-control" id="inputNama" name="nama_lengkap" value="<?php echo htmlspecialchars($data_pribadi['nama_lengkap'] ?? ''); ?>" placeholder="Nama Lengkap" required></div></div>
                                <div class="form-group row"><label for="inputEmail" class="col-sm-3 col-form-label">Email</label><div class="col-sm-9"><input type="email" class="form-control" id="inputEmail" name="email" value="<?php echo htmlspecialchars($data_pribadi['email'] ?? ''); ?>" placeholder="Email" required></div></div>
                                <div class="form-group row"><label for="inputTelepon" class="col-sm-3 col-form-label">No. Telepon</label><div class="col-sm-9"><input type="text" class="form-control" id="inputTelepon" name="nomor_telepon" value="<?php echo htmlspecialchars($data_pribadi['nomor_telepon'] ?? ''); ?>" placeholder="Nomor Telepon"></div></div>
                                <div class="form-group row"><label for="inputAlamat" class="col-sm-3 col-form-label">Alamat</label><div class="col-sm-9"><textarea class="form-control" id="inputAlamat" name="alamat" placeholder="Alamat Lengkap"><?php echo htmlspecialchars($data_pribadi['alamat'] ?? ''); ?></textarea></div></div>
                                <div class="form-group row"><label for="inputFoto" class="col-sm-3 col-form-label">Ganti Foto Profil</label><div class="col-sm-9"><div class="input-group"><div class="custom-file"><input type="file" class="custom-file-input" id="inputFoto" name="foto_profil" accept="image/jpeg,image/png,image/gif"><label class="custom-file-label" for="inputFoto">Pilih foto baru...</label></div></div><small class="form-text text-muted">Format: JPG, PNG, GIF. Max: <?php echo defined('MAX_FILE_SIZE_FOTO_PROFIL_MB') ? MAX_FILE_SIZE_FOTO_PROFIL_MB : 1; ?>MB.</small></div></div>
                                <div class="form-group row"><div class="offset-sm-3 col-sm-9"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button></div></div>
                            </form>
                        </div>

                        <div class="tab-pane <?php if($active_tab_profil == 'ubahpassword') echo 'active show'; ?>" id="ubahpassword">
                            <?php
                            if (isset($_SESSION['pesan_sukses_profil_password'])) { echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['pesan_sukses_profil_password']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>'; unset($_SESSION['pesan_sukses_profil_password']); }
                            if (isset($_SESSION['pesan_error_profil_password'])) { echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['pesan_error_profil_password']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>'; unset($_SESSION['pesan_error_profil_password']); }
                            ?>
                            <form class="form-horizontal" action="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/profile/proses_ubah_password.php'); ?>" method="post">
                                <input type="hidden" name="nik_ubah_pass" value="<?php echo htmlspecialchars($data_pribadi['nik']); ?>">
                                <input type="hidden" name="submit_ubah_password" value="1">
                                <div class="form-group row"><label for="inputPasswordLama" class="col-sm-3 col-form-label">Password Lama <span class="text-danger">*</span></label><div class="col-sm-9"><input type="password" class="form-control" name="password_lama" id="inputPasswordLama" placeholder="Password Lama" required></div></div>
                                <div class="form-group row"><label for="inputPasswordBaru" class="col-sm-3 col-form-label">Password Baru <span class="text-danger">*</span></label><div class="col-sm-9"><input type="password" class="form-control" name="password_baru" id="inputPasswordBaru" placeholder="Password Baru (min. <?php echo defined('MIN_PASSWORD_LENGTH') ? MIN_PASSWORD_LENGTH : 6; ?> karakter)" required minlength="<?php echo defined('MIN_PASSWORD_LENGTH') ? MIN_PASSWORD_LENGTH : 6; ?>"></div></div>
                                <div class="form-group row"><label for="inputKonfirmasiPassword" class="col-sm-3 col-form-label">Konfirmasi Password <span class="text-danger">*</span></label><div class="col-sm-9"><input type="password" class="form-control" name="konfirmasi_password_baru" id="inputKonfirmasiPassword" placeholder="Ulangi Password Baru" required></div></div>
                                <div class="form-group row"><div class="offset-sm-3 col-sm-9"><button type="submit" class="btn btn-danger"><i class="fas fa-key"></i> Ubah Password</button></div></div>
                            </form>
                        </div>

                        <div class="tab-pane <?php if($active_tab_profil == 'infoPeran') echo 'active show'; ?>" id="infoPeran">
                             <h5>Peran dan Keanggotaan Anda:</h5>
                            <?php if (!empty($roles_data_session)): ?>
                                <ul class="list-group list-group-unbordered mb-3">
                                    <?php foreach ($roles_data_session as $peran_item): ?>
                                        <li class="list-group-item">
                                            <b><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $peran_item['role_spesifik'] ?? ($peran_item['tipe_peran'] ?? 'Peran Tidak Diketahui')))); ?></b>
                                            <?php if (!empty($peran_item['nama_cabor'])): ?><span class="float-right">Cabor: <?php echo htmlspecialchars($peran_item['nama_cabor']); ?></span><br><?php endif; ?>
                                            <?php if (isset($peran_item['nama_klub']) && !empty($peran_item['nama_klub'])): ?><small class="text-muted d-block">Klub: <?php echo htmlspecialchars($peran_item['nama_klub']); ?></small><?php endif; ?>
                                            <?php if (!empty($peran_item['detail_jabatan'])): ?><small class="text-muted d-block"><?php echo htmlspecialchars($peran_item['detail_jabatan']); ?></small><?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">Tidak ada data peran spesifik yang tercatat di sesi.</p>
                            <?php endif; ?>
                            <p>Peran utama Anda saat ini adalah: <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user_role_utama))); ?></strong></p>
                        </div>

                        <div class="tab-pane <?php if($active_tab_profil == 'idcard') echo 'active show'; ?>" id="idcard">
                            <h4>ID Card Digital Anda</h4>
                            <?php if ($qr_library_available === false): ?>
                                <div class="alert alert-warning mt-3">Fitur ID Card Digital tidak tersedia karena komponen QR Code belum terpasang/terkonfigurasi dengan benar atau ada masalah izin folder. Harap hubungi administrator.</div>
                            <?php elseif (isset($user_nik) && !empty($user_nik) && $data_pribadi): ?>
                                <div class="row d-flex justify-content-center mt-3">
                                    <div class="col-md-7 col-lg-5 col-xl-4"> 
                                        <h5 class="text-center mb-3 sr-only">Preview ID Card</h5>
                                        <div id="idCardToPrint" style="width: 280px; height: 440px; border: 1px solid #ccc; border-radius: 15px; padding: 0; background-color: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin: 0 auto 15px auto; display: flex; flex-direction: column; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; position: relative; overflow: hidden;">
                                            <div style="background-color: #007bff; color: white; padding: 10px 15px; border-top-left-radius: 14px; border-top-right-radius: 14px; display: flex; align-items: center;">
                                                <img src="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/uploads/logos/logo_koni.png'); ?>" alt="Logo KONI" style="width: 38px; height: 38px; object-fit: contain; margin-right: 12px; background-color: white; border-radius:50%; padding:2px;">
                                                <div style="line-height: 1.2;">
                                                    <h6 style="margin: 0; font-weight: bold; font-size: 0.85em;">KARTU IDENTITAS DIGITAL</h6>
                                                    <p style="margin: 0; font-size: 0.65em;">KONI Kabupaten Serdang Bedagai</p>
                                                </div>
                                            </div>
                                            <div style="text-align: center; padding: 10px 10px; flex-grow: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; margin-top: 10px;">
                                                <img src="<?php echo $url_foto_tampil; ?>" alt="Foto Profil" style="width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 3px solid #007bff; margin-bottom: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                                <h5 style="margin-bottom: 5px; color: #333; font-size: 1.1em; font-weight: bold;"><?php echo $nama_lengkap_profil_display; ?></h5>
                                                <p style="color: #0056b3; font-size: 0.9em; margin-bottom: 5px; font-weight:bold;"><?php echo $peran_profil_display; ?></p>
                                                <?php if ($user_role_utama == 'pengurus_cabor' && !empty($nama_cabor_utama_profil)): ?>
                                                    <p style="color: #555; font-size: 0.75em; margin-top:-3px; margin-bottom: 5px;"><?php echo htmlspecialchars($nama_cabor_utama_profil); ?></p>
                                                <?php endif; ?>
                                                <p style="font-size: 0.8em; margin-bottom: 2px; color: #495057;">NIK: <?php echo htmlspecialchars($data_pribadi['nik'] ?? '-'); ?></p>
                                            </div>
                                            <div style="text-align: center; padding-bottom: 10px; margin-top:auto;">
                                                <?php
                                                $qr_file_exists_on_server = (!empty($qr_file_path_server_for_idcard) && file_exists($qr_file_path_server_for_idcard));
                                                if ($qr_library_available && $qr_file_exists_on_server && !empty($qr_image_url_for_idcard) ):
                                                ?>
                                                    <img src="<?php echo htmlspecialchars($qr_image_url_for_idcard) . '?t=' . time(); ?>" alt="QR Code Profil" style="width: 100px; height: 100px; margin-bottom: 3px; border: 1px solid #ddd;">
                                                <?php else: ?>
                                                    <p class="text-danger mt-2 mb-2" style="font-size:0.7em;">QR Code tidak dapat ditampilkan.</p>
                                                <?php endif; ?>
                                                <p style="font-size: 0.6em; color: #777; margin-bottom: 0; margin-top: 5px;"><i>Reaktor System © <?php echo date("Y"); ?></i></p>
                                            </div>
                                        </div>
                                        <p class="mt-3 text-center">
                                            <button type="button" class="btn btn-sm btn-success" onclick="downloadIdCardAsImage('png');"><i class="fas fa-download"></i> Download PNG</button>
                                            <button type="button" class="btn btn-sm btn-primary" onclick="downloadIdCardAsImage('jpeg');"><i class="fas fa-download"></i> Download JPG</button>
                                            <button type="button" class="btn btn-sm btn-info" onclick="printIdCardOnly();"><i class="fas fa-print"></i> Cetak ID Card</button>
                                        </p>
                                    </div>
                                    <div class="col-md-5 col-lg-4 text-center mb-3 align-self-center">
                                        <?php if ($qr_library_available && !empty($qr_image_url_for_idcard) && !empty($qr_data_url_for_idcard) && $qr_file_exists_on_server): ?>
                                            <h6 class="mt-md-0 mt-3">Scan QR Code Profil Lengkap</h6>
                                            <img src="<?php echo htmlspecialchars($qr_image_url_for_idcard) . '?t=' . time(); ?>" alt="QR Code Profil" class="img-fluid" style="max-width: 160px; border:1px solid #ccc; padding:5px; margin-top:5px;">
                                            <p class="mt-2" style="word-break:break-all; font-size:0.75em;"><small>URL: <?php echo htmlspecialchars($qr_data_url_for_idcard); ?></small></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning mt-3">
                                    Tidak dapat menampilkan ID Card Digital. <?php if (!$data_pribadi) echo "Data pengguna tidak lengkap. "; if ($qr_library_available === false) echo "Komponen QR Code bermasalah. "; ?> Harap hubungi administrator.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-warning text-center">Data profil tidak dapat dimuat saat ini. Silakan coba lagi atau hubungi administrator.</div>
    <?php endif; ?>
    <script> 
    function printIdCardOnly() {
        var idCardElement = document.getElementById('idCardToPrint');
        if (!idCardElement) { alert('Elemen ID Card tidak ditemukan.'); return; }
        var printContents = idCardElement.outerHTML;
        var newWindow = window.open('', '_blank', 'height=600,width=400');
        newWindow.document.write('<html><head><title>Cetak ID Card</title>');
        Array.from(document.styleSheets).forEach(styleSheet => {
            try {
                if (styleSheet.href) { newWindow.document.write('<link rel="stylesheet" href="' + styleSheet.href + '">'); } 
                else if (styleSheet.cssRules) { newWindow.document.write('<style>' + Array.from(styleSheet.cssRules).map(rule => rule.cssText).join('\\n') + '</style>'); }
            } catch (e) { console.warn("Tidak dapat memuat stylesheet: ", styleSheet.href, e); }
        });
        newWindow.document.write(`
            <style> 
                @media print { 
                    body { margin: 0; padding: 0; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; } 
                    @page { size: 53.98mm 85.6mm; margin: 0; } 
                    #idCardToPrint { width: 53.98mm !important; height: 85.68mm !important; border: none !important; box-shadow: none !important; margin: 0 !important; padding: 2.5mm !important; box-sizing: border-box; overflow: hidden; page-break-inside: avoid; display: flex !important; flex-direction: column !important; justify-content: space-between !important; } 
                    #idCardToPrint h5, #idCardToPrint h6 { font-size: 7pt !important; line-height: 1.1 !important; margin-bottom: 1.5px !important; } 
                    #idCardToPrint p { font-size: 5.5pt !important; line-height: 1.1 !important; margin-bottom: 1.5px !important; } 
                    #idCardToPrint img[alt="Logo KONI"] { width: 22px !important; height: 22px !important; margin-right: 4px !important; padding:1px !important;} 
                    #idCardToPrint img[alt="Foto Profil"] { width: 45px !important; height: 45px !important; margin-bottom: 4px !important; border-width: 1px !important;} 
                    #idCardToPrint img[alt="QR Code Profil"] { width: 35px !important; height: 35px !important; margin-bottom: 1px !important;} 
                    #idCardToPrint hr { margin-top: 2px !important; margin-bottom: 4px !important; } 
                } 
                body { display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background-color: #e9ecef; } 
                #idCardToPrint { transform: scale(1.3); } 
            </style>
        `);
        newWindow.document.write('</head><body onload="setTimeout(function() { window.print(); setTimeout(function() { /* window.close(); */ }, 200); }, 1200);">');
        newWindow.document.write(printContents);
        newWindow.document.write('</body></html>');
        newWindow.document.close(); newWindow.focus();
    }
    
    
        function downloadIdCardAsImage(format = 'png') {
        const idCardElement = document.getElementById('idCardToPrint');
        if (!idCardElement) {
            alert('Elemen ID Card tidak ditemukan untuk diunduh.');
            return;
        }

        // Opsi untuk html2canvas
        const options = {
            scale: 2, // Tingkatkan skala untuk kualitas gambar yang lebih baik (misal 2 atau 3)
            useCORS: true, // Jika ada gambar dari domain lain (misal CDN logo)
            logging: true, // Untuk debugging jika ada masalah
            backgroundColor: null // Agar background transparan jika elemennya tidak punya background eksplisit
        };

        html2canvas(idCardElement, options).then(canvas => {
            try {
                let imageType = format === 'jpeg' ? 'image/jpeg' : 'image/png';
                let imageURL = canvas.toDataURL(imageType, format === 'jpeg' ? 0.9 : 1.0); // Kualitas untuk JPEG

                // Buat link sementara untuk download
                let downloadLink = document.createElement('a');
                let fileName = 'ID_Card_<?php echo preg_replace("/[^a-zA-Z0-9_]/", "_", $user_nik ?? "user"); ?>.' + format;
                downloadLink.href = imageURL;
                downloadLink.download = fileName;

                // Klik link secara otomatis
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);

            } catch (e) {
                console.error("Error saat konversi canvas ke gambar atau download:", e);
                alert("Gagal mengunduh ID Card sebagai gambar. Lihat console untuk detail.");
            }
        }).catch(function(error) {
            console.error('Error saat menggunakan html2canvas:', error);
            alert('Terjadi kesalahan saat membuat gambar ID Card. Pastikan semua aset gambar di ID Card dapat diakses.');
        });
    }

    $(function() {
        // ... (logika tab aktif Anda yang sudah ada) ...
    });

    $(function() {
        var hash = window.location.hash;
        var activeTabProfil = '<?php echo $active_tab_profil; ?>';
        if (hash && $('ul.nav-pills a[href="' + hash + '"]').length) {
            $('ul.nav-pills a[href="' + hash + '"]').tab('show');
        } else if (activeTabProfil && $('ul.nav-pills a[href="#' + activeTabProfil + '"]').length) {
            $('ul.nav-pills a[href="#' + activeTabProfil + '"]').tab('show');
        } else {
            $('ul.nav-pills a[href="#editdata"]').tab('show');
        }
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            var newHash = $(e.target).attr('href');
            if(history.pushState) { history.pushState(null, null, newHash); } else { window.location.hash = newHash; }
        });
    });
    </script>
<?php
if (file_exists(__DIR__ . '/../core/footer.php')) {
    require_once(__DIR__ . '/../core/footer.php');
}
?>