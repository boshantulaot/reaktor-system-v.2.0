<?php
// File: public_html/reaktorsystem/core/header.php

// Sertakan file inisialisasi inti yang berisi semua logika dasar
if (file_exists(__DIR__ . '/init_core.php')) {
    require_once(__DIR__ . '/init_core.php');
} else {
    // Ini adalah error fatal, aplikasi tidak bisa berjalan tanpanya.
    $error_msg_header = "FATAL ERROR di header.php: File init_core.php tidak ditemukan di " . __DIR__ . "/init_core.php. Aplikasi tidak bisa berjalan.";
    error_log($error_msg_header); // Catat ke log server
    die("Kesalahan konfigurasi sistem inti (HEADER_INIT_CORE_MISSING). Silakan hubungi administrator."); // Tampilkan pesan ke pengguna
}

// --- SEMUA VARIABEL GLOBAL PENTING SUDAH TERSEDIA DARI init_core.php ---
// Seperti: $pdo, $user_nik, $nama_pengguna, $user_role_utama, $id_cabor_pengurus_utama,
// $app_base_path, $user_login_status, $user_foto_profil_path_relative_to_app_root,
// APP_URL_BASE, MAX_SESSION_INACTIVITY_SECONDS, $cookie_path_setting (jika ada dari init_core.php), dll.


// ========================================================================
// AWAL: Logika Timeout Sesi PHP-Side dan Pengecekan Status Login
// Ini adalah penjaga utama di sisi server untuk keamanan dan validitas sesi.
// ========================================================================

// 1. Pengecekan Timeout Sesi (hanya jika pengguna sudah login)
if (isset($user_login_status) && $user_login_status === true && isset($user_nik)) {
    $session_timeout_duration = defined('MAX_SESSION_INACTIVITY_SECONDS') ? MAX_SESSION_INACTIVITY_SECONDS : (30 * 60); // Default 30 menit

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout_duration)) {
        $nama_pengguna_logout_header = $_SESSION['nama_pengguna'] ?? 'Pengguna';
        
        // Path cookie harus konsisten dengan yang diatur saat login/init
        $path_for_cookie_logout = isset($cookie_path_setting) ? $cookie_path_setting : ($app_base_path ?? '/');
        if (isset($_COOKIE['remember_me_reaktor'])) { // Pastikan nama cookie ini konsisten jika Anda menggunakannya
            setcookie('remember_me_reaktor', '', time() - 3600, $path_for_cookie_logout, "", (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'), true);
        }
        
        session_unset();     // Hapus semua variabel sesi
        session_destroy();   // Hancurkan data sesi di server
        
        session_start(); // Mulai sesi baru untuk menyimpan pesan error
        $_SESSION['login_error'] = "Sesi untuk " . htmlspecialchars($nama_pengguna_logout_header) . " telah berakhir karena tidak ada aktivitas. Silakan login kembali.";
        
        $login_url_on_timeout = (defined('APP_URL_BASE') ? rtrim(APP_URL_BASE, '/') : '') . '/auth/login.php?reason=inactive_timeout_server_header';
        header("Location: " . $login_url_on_timeout);
        exit();
    }
    // Perbarui waktu aktivitas terakhir jika sesi masih valid
    $_SESSION['last_activity'] = time();
}

// 2. Pengecekan Apakah Pengguna Harus Login (untuk halaman yang dilindungi)
if (basename($_SERVER['PHP_SELF']) != 'login.php' && basename($_SERVER['PHP_SELF']) != 'proses_login.php') {
    if (!isset($user_login_status) || $user_login_status !== true || !isset($user_nik)) {
        if (!isset($_SESSION['login_error'])) { // Hanya set pesan jika belum ada (misal, dari timeout)
            $_SESSION['login_error'] = "Anda harus login untuk mengakses halaman ini.";
        }
        $login_url_on_force = (defined('APP_URL_BASE') ? rtrim(APP_URL_BASE, '/') : '') . '/auth/login.php?reason=login_required';
        header("Location: " . $login_url_on_force);
        exit();
    }
}
// ========================================================================
// AKHIR: Logika Timeout Sesi dan Pengecekan Status Login
// ========================================================================


// Array untuk item menu, akan diisi berdasarkan peran
$menu_items = [];
// $current_request_uri_for_menu seharusnya sudah didefinisikan di init_core.php atau di sini
$current_request_uri_for_menu = $_SERVER['REQUEST_URI'] ?? '';


// --- AWAL LOGIKA DEFINISI $menu_items BERDASARKAN PERAN ---
if (isset($user_login_status) && $user_login_status === true && isset($user_nik) && basename($_SERVER['PHP_SELF']) != 'login.php') {
    
    // Menu Dashboard selalu ada untuk pengguna yang login (kecuali guest mungkin punya tampilan beda)
    if (isset($user_role_utama) && $user_role_utama != 'guest') {
        $menu_items['dashboard'] = ['link' => 'dashboard.php', 'text' => 'Dashboard', 'icon' => 'nav-icon fas fa-tachometer-alt'];
    }

    // Menu untuk Super Admin DAN Admin KONI
    if (isset($user_role_utama) && ($user_role_utama == 'super_admin' || $user_role_utama == 'admin_koni')) {
        $menu_items['manajemen_cabor'] = ['link' => 'modules/cabor/daftar_cabor.php', 'text' => 'Manajemen Cabor', 'icon' => 'nav-icon fas fa-flag'];
        $menu_items['manajemen_klub'] = ['link' => 'modules/klub/daftar_klub.php', 'text' => 'Manajemen Klub', 'icon' => 'nav-icon fas fa-shield-alt'];
        
        $menu_items['grup_atlet_prestasi'] = [
            'text' => 'Manajemen Atlet', 'icon' => 'nav-icon fas fa-users', // Mengganti ikon grup
            'link' => '#',
            'submenu' => [
                'profil_atlet' => ['link' => 'modules/atlet/daftar_atlet.php', 'text' => 'Profil Atlet', 'icon' => 'nav-icon fas fa-running'],
                'prestasi_atlet' => ['link' => 'modules/prestasi/daftar_prestasi.php', 'text' => 'Data Prestasi Atlet', 'icon' => 'nav-icon fas fa-trophy']
            ]
        ];
        
        $menu_items['grup_pelatih'] = [
            'text' => 'Manajemen Pelatih', 'icon' => 'nav-icon fas fa-chalkboard-teacher', 'link' => '#',
            'submenu' => [
                'profil_pelatih' => ['link' => 'modules/pelatih/daftar_pelatih.php', 'text' => 'Profil Pelatih', 'icon' => 'nav-icon far fa-id-badge'], // Mungkin lebih cocok
                'lisensi_pelatih' => ['link' => 'modules/lisensi_pelatih/daftar_lisensi_pelatih.php', 'text' => 'Lisensi Kepelatihan', 'icon' => 'nav-icon fas fa-award']
            ]
        ];
        $menu_items['grup_wasit'] = [
            'text' => 'Manajemen Wasit/Juri', 'icon' => 'nav-icon fas fa-gavel', 'link' => '#',
            'submenu' => [
                'profil_wasit' => ['link' => 'modules/wasit/daftar_wasit.php', 'text' => 'Profil Wasit/Juri', 'icon' => 'nav-icon far fa-id-card'], // Mungkin lebih cocok
                'sertifikasi_wasit' => ['link' => 'modules/sertifikasi_wasit/daftar_sertifikasi_wasit.php', 'text' => 'Sertifikasi Perwasitan', 'icon' => 'nav-icon fas fa-medal']
            ]
        ];
        
        $menu_items['grup_admin_user'] = [
            'text' => 'Adm. Pengguna Sistem', 'icon' => 'nav-icon fas fa-cogs', 'link' => '#',
            'submenu' => [
                'manajemen_pengguna' => ['link' => 'admin/users/daftar_pengguna.php', 'text' => 'Manajemen Akun Pengguna', 'icon' => 'nav-icon fas fa-users-cog'],
                'manajemen_anggota' => ['link' => 'admin/roles/daftar_anggota.php', 'text' => 'Manajemen Peran Anggota', 'icon' => 'nav-icon fas fa-address-book']
            ]
        ];
        $menu_items['manajemen_notifikasi_admin'] = ['link' => 'admin/notifikasi/daftar_notifikasi_admin.php', 'text' => 'Manajemen Notifikasi', 'icon' => 'nav-icon fas fa-bullhorn'];
        
        if ($user_role_utama == 'admin_koni') {
             $menu_items['adminkoni_profil'] = ['link' => 'profile/profil_saya.php', 'text' => 'Profil Saya', 'icon' => 'nav-icon fas fa-user-shield']; // Ikon berbeda
        }
    }

    // Menu KHUSUS untuk Super Admin
    if (isset($user_role_utama) && $user_role_utama == 'super_admin') {
        $menu_items['manajemen_data_sistem'] = ['link' => 'admin/manajemen_data/manajemen_data_index.php', 'text' => 'Manajemen Data Sistem', 'icon' => 'nav-icon fas fa-database'];
        $menu_items['audit_log'] = ['link' => 'admin/audit_logs/audit_log_view.php', 'text' => 'Audit Log Sistem', 'icon' => 'nav-icon fas fa-history'];
        if (!isset($menu_items['superadmin_profil'])) $menu_items['superadmin_profil'] = ['link' => 'profile/profil_saya.php', 'text' => 'Profil Saya', 'icon' => 'nav-icon fas fa-user-secret'];
    } 
    
    // Menu untuk Pengurus Cabor
    elseif (isset($user_role_utama) && $user_role_utama == 'pengurus_cabor') {
        if (isset($id_cabor_pengurus_utama) && !empty($id_cabor_pengurus_utama)) {
            $menu_items['cabor_detail'] = ['link' => 'modules/cabor/detail_cabor.php?id=' . $id_cabor_pengurus_utama, 'text' => 'Info Cabor Saya', 'icon' => 'nav-icon fas fa-info-circle'];
            $menu_items['cabor_manajemen_klub'] = ['link' => 'modules/klub/daftar_klub.php?id_cabor_filter=' . $id_cabor_pengurus_utama, 'text' => 'Klub Cabor Ini', 'icon' => 'nav-icon fas fa-shield-alt']; // id_cabor_filter atau nama parameter yang sesuai
            $menu_items['cabor_manajemen_atlet'] = ['link' => 'modules/atlet/daftar_atlet.php?id_cabor_filter=' . $id_cabor_pengurus_utama, 'text' => 'Atlet Cabor Ini', 'icon' => 'nav-icon fas fa-running'];
            $menu_items['cabor_lisensi_pelatih'] = ['link' => 'modules/lisensi_pelatih/daftar_lisensi_pelatih.php?id_cabor_filter=' . $id_cabor_pengurus_utama, 'text' => 'Lisensi Pelatih Cabor Ini', 'icon' => 'nav-icon fas fa-id-card-alt'];
            $menu_items['cabor_sertifikasi_wasit'] = ['link' => 'modules/sertifikasi_wasit/daftar_sertifikasi_wasit.php?id_cabor_filter=' . $id_cabor_pengurus_utama, 'text' => 'Sertifikasi Wasit Cabor Ini', 'icon' => 'nav-icon fas fa-medal'];
            $menu_items['cabor_manajemen_prestasi'] = ['link' => 'modules/prestasi/daftar_prestasi.php?id_cabor_filter=' . $id_cabor_pengurus_utama, 'text' => 'Prestasi Cabor Ini', 'icon' => 'nav-icon fas fa-trophy'];
        }
        $menu_items['cabor_profil_saya'] = ['link' => 'profile/profil_saya.php', 'text' => 'Profil Saya', 'icon' => 'nav-icon fas fa-user-tie'];
    } 
    
    // Menu untuk Atlet
    elseif (isset($user_role_utama) && $user_role_utama == 'atlet') {
        $menu_items['atlet_profil_idcard'] = ['link' => 'profile/profil_saya.php', 'text' => 'Profil & ID Card Saya', 'icon' => 'nav-icon fas fa-id-card'];
        $menu_items['atlet_prestasi_saya'] = ['link' => 'profile/prestasi_saya.php', 'text' => 'Prestasi Saya', 'icon' => 'nav-icon fas fa-award'];
        $menu_items['atlet_tambah_prestasi'] = ['link' => 'modules/prestasi/tambah_prestasi.php', 'text' => 'Ajukan Data Prestasi', 'icon' => 'nav-icon fas fa-plus-circle'];
    } 
    // Menu untuk Pelatih
    elseif (isset($user_role_utama) && $user_role_utama == 'pelatih') {
        $menu_items['pelatih_profil_idcard'] = ['link' => 'profile/profil_saya.php', 'text' => 'Profil & ID Card Saya', 'icon' => 'nav-icon fas fa-id-card'];
        $menu_items['pelatih_lisensi_saya'] = ['link' => 'profile/lisensi_saya.php', 'text' => 'Data Lisensi Saya', 'icon' => 'nav-icon fas fa-address-card'];
        $menu_items['pelatih_tambah_lisensi'] = ['link' => 'modules/lisensi_pelatih/tambah_lisensi_pelatih.php', 'text' => 'Ajukan Data Lisensi', 'icon' => 'nav-icon fas fa-plus-circle'];
    } 
    // Menu untuk Wasit
    elseif (isset($user_role_utama) && $user_role_utama == 'wasit') {
        $menu_items['wasit_profil_idcard'] = ['link' => 'profile/profil_saya.php', 'text' => 'Profil & ID Card Saya', 'icon' => 'nav-icon fas fa-id-card'];
        $menu_items['wasit_sertifikasi_saya'] = ['link' => 'profile/sertifikasi_saya.php', 'text' => 'Data Sertifikasi Saya', 'icon' => 'nav-icon fas fa-medal'];
        $menu_items['wasit_tambah_sertifikasi'] = ['link' => 'modules/sertifikasi_wasit/tambah_sertifikasi_wasit.php', 'text' => 'Ajukan Data Sertifikasi', 'icon' => 'nav-icon fas fa-plus-circle'];
    }
    // Menu untuk View Only
    elseif (isset($user_role_utama) && $user_role_utama == 'view_only') {
        $menu_items['viewonly_profil_saya'] = ['link' => 'profile/profil_saya.php', 'text' => 'Profil Saya', 'icon' => 'nav-icon fas fa-user-circle'];
        $menu_items['viewonly_daftar_cabor'] = ['link' => 'modules/cabor/daftar_cabor.php', 'text' => 'Lihat Daftar Cabor', 'icon' => 'nav-icon fas fa-list-alt'];
        $menu_items['viewonly_daftar_atlet'] = ['link' => 'modules/atlet/daftar_atlet.php', 'text' => 'Lihat Daftar Atlet', 'icon' => 'nav-icon fas fa-list-ul'];
        // Tambahkan menu view-only lainnya yang relevan
    }

    // Menu Logout selalu ada di paling bawah jika pengguna login dan bukan guest
    if (isset($user_role_utama) && $user_role_utama != 'guest') {
        if (!isset($menu_items['logout'])) { // Hindari duplikasi jika sudah ada dari logika lain
            $menu_items['logout'] = ['link' => 'logout.php', 'text' => 'Logout', 'icon' => 'nav-icon fas fa-sign-out-alt'];
        }
    }
}
// --- AKHIR LOGIKA DEFINISI $menu_items ---


// Fungsi isActivePath (Tidak ada perubahan signifikan, pastikan $app_base_path benar)
if (!function_exists('isActivePath')) {
    function isActivePath($target_path_from_app_root, $current_request_uri_for_menu) {
        global $app_base_path;
        if (empty($target_path_from_app_root) || $target_path_from_app_root == '#' || empty($current_request_uri_for_menu)) return false;
        
        $normalized_app_base = rtrim($app_base_path ?? '/', '/') . '/';
        if ($normalized_app_base === '//' || empty(trim($normalized_app_base, '/'))) $normalized_app_base = '/';

        $target_web_absolute = $normalized_app_base . ltrim($target_path_from_app_root, '/');
        $target_web_absolute = preg_replace('/\/+/', '/', $target_web_absolute);
        if ($normalized_app_base === '/' && strpos($target_web_absolute, '//') === 0) {
            $target_web_absolute = '/' . ltrim($target_web_absolute, '/');
        }

        $current_path_part = strtok($current_request_uri_for_menu, '?');
        $target_path_part = strtok($target_web_absolute, '?');

        if (rtrim($current_path_part, '/') == rtrim($target_path_part, '/')) {
            $target_query_string = parse_url($target_web_absolute, PHP_URL_QUERY);
            if (empty($target_query_string)) return true;
            
            $current_query_string = parse_url($current_request_uri_for_menu, PHP_URL_QUERY);
            if (empty($current_query_string)) return false; 
            
            parse_str($target_query_string, $target_params);
            parse_str($current_query_string, $current_params);
            
            foreach ($target_params as $key => $value) {
                if (!isset($current_params[$key]) || $current_params[$key] != $value) return false;
            }
            return true;
        }
        return false;
    }
}

// Judul Halaman dan Situs
$site_title_full = "Reaktor: Sistem Manajemen Data Olahraga KONI Serdang Bedagai";
$site_title_short = "Reaktor System";
$page_title_default = (isset($user_login_status) && $user_login_status === true && isset($user_role_utama) && $user_role_utama !== 'guest') ? "Dashboard" : "Login Area";
$page_title_display = isset($page_title) && !empty($page_title) ? htmlspecialchars($page_title) : $page_title_default;

// Output Buffering
if (ob_get_level() == 0 && php_sapi_name() !== 'cli') { ob_start(); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title_display; ?> | <?php echo htmlspecialchars($site_title_short); ?></title>
    <link rel="icon" href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/uploads/logos/logo_koni.png'); ?>" type="image/png">
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/plugins/fontawesome-free/css/all.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/plugins/overlayScrollbars/css/OverlayScrollbars.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/dist/css/adminlte.min.css'); ?>">
    
    <?php if (isset($additional_css) && is_array($additional_css)): foreach ($additional_css as $css_file): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($css_file, '/')); ?>">
    <?php endforeach; endif; ?>

    <style>
        .nav-sidebar .nav-item > .nav-link.active, 
        .nav-treeview > .nav-item > .nav-link.active { 
            background-color: #007bff !important; 
            color: #fff !important; 
        }
        /* Style untuk submenu yang parent-nya aktif (jika diperlukan AdminLTE tidak otomatis) */
        .nav-item.menu-is-opening.menu-open > .nav-link {
             background-color: rgba(255,255,255,.1) !important; /* Warna sedikit beda untuk parent aktif */
             color: #c2c7d0 !important; /* Atau warna teks default sidebar */
        }
        .nav-item.menu-is-opening.menu-open > .nav-link.active { /* Jika parent link juga halaman aktif */
            background-color: #007bff !important;
            color: #fff !important;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed <?php if (basename($_SERVER['PHP_SELF']) == 'login.php') echo 'login-page'; ?>">
    <div class="wrapper <?php if (basename($_SERVER['PHP_SELF']) == 'login.php') echo 'login-box'; ?>">
        
        <?php // Tampilkan Navigasi dan Sidebar hanya jika pengguna login dan bukan di halaman login ?>
        <?php if (isset($user_login_status) && $user_login_status === true && isset($user_nik) && isset($user_role_utama) && $user_role_utama != 'guest' && basename($_SERVER['PHP_SELF']) != 'login.php' && basename($_SERVER['PHP_SELF']) != 'proses_login.php'): ?>
            
            <nav class="main-header navbar navbar-expand navbar-white navbar-light">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li>
                    <li class="nav-item d-none d-sm-inline-block"><a href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/dashboard.php'); ?>" class="nav-link">Home</a></li>
                    <?php // Tambahan: breadcrumb bisa dimulai dari sini atau di content-header halaman ?>
                </ul>
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item"><a class="nav-link" data-widget="fullscreen" href="#" role="button"><i class="fas fa-expand-arrows-alt"></i></a></li>
                    <li class="nav-item dropdown user-menu">
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                            <img src="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($user_foto_profil_path_relative_to_app_root, '/')); ?>" class="user-image img-circle elevation-2" alt="Foto Pengguna">
                            <span class="d-none d-md-inline"><?php echo htmlspecialchars($nama_pengguna ?? 'Pengguna'); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                            <li class="user-header bg-primary">
                                <img src="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($user_foto_profil_path_relative_to_app_root, '/')); ?>" class="img-circle elevation-2" alt="Foto Pengguna Dropdown">
                                <p>
                                    <?php echo htmlspecialchars($nama_pengguna ?? 'Pengguna'); ?> - <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user_role_utama ?? 'Tamu'))); ?>
                                    <?php if (isset($user_created_at_session) && !empty($user_created_at_session)): ?><small>Terdaftar sejak: <?php echo date('M Y', strtotime($user_created_at_session)); ?></small><?php endif; ?>
                                </p>
                            </li>
                            <li class="user-footer">
                                <a href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/profile/profil_saya.php'); ?>" class="btn btn-default btn-flat">Profil Saya</a>
                                <a href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/logout.php'); ?>" class="btn btn-default btn-flat float-right">Logout</a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </nav>

            <aside class="main-sidebar sidebar-dark-primary elevation-4">
                <a href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/dashboard.php'); ?>" class="brand-link">
                    <img src="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/uploads/logos/logo_koni.png'); ?>" alt="Logo KONI" class="brand-image img-circle elevation-3" style="opacity: .8">
                    <span class="brand-text font-weight-light"><?php echo htmlspecialchars($site_title_short); ?></span>
                </a>
                <div class="sidebar">
                    <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                        <div class="image">
                            <img src="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($user_foto_profil_path_relative_to_app_root, '/')); ?>" class="img-circle elevation-2" alt="Foto Panel">
                        </div>
                        <div class="info">
                            <a href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/profile/profil_saya.php'); ?>" class="d-block"><?php echo htmlspecialchars($nama_pengguna ?? 'Pengguna'); ?></a>
                        </div>
                    </div>
                    
                    <nav class="mt-2">
                        <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent nav-legacy" data-widget="treeview" role="menu" data-accordion="false">
                            <?php
                            if (!empty($menu_items)):
                                foreach ($menu_items as $menu_key => $item):
                                    if (!is_array($item) || !isset($item['link']) || !isset($item['text'])) {
                                        error_log("HEADER_MENU_ITEM_ERROR: Struktur item menu tidak valid untuk kunci '$menu_key'.");
                                        continue;
                                    }
                                    $item_link_processed = ($item['link'] == '#') ? '#' : htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($item['link'], '/'));
                                    $item_link_processed = preg_replace('/\/+/', '/', $item_link_processed);
                                    
                                    $has_submenu = isset($item['submenu']) && is_array($item['submenu']) && !empty($item['submenu']);
                                    $is_active_parent_flag = false; // Flag untuk parent menu
                                    $menu_open_class_flag = '';

                                    if ($has_submenu) {
                                        foreach ($item['submenu'] as $sub_check_item) {
                                            if (is_array($sub_check_item) && isset($sub_check_item['link']) && isActivePath($sub_check_item['link'], $current_request_uri_for_menu)) {
                                                $is_active_parent_flag = true;
                                                $menu_open_class_flag = 'menu-is-opening menu-open';
                                                break;
                                            }
                                        }
                                    } else {
                                        $is_active_parent_flag = isActivePath($item['link'], $current_request_uri_for_menu);
                                    }
                                    ?>
                                    <li class="nav-item <?php echo $menu_open_class_flag; ?>">
                                        <a href="<?php echo $item_link_processed; ?>" class="nav-link <?php if ($is_active_parent_flag) echo 'active'; ?>">
                                            <i class="<?php echo htmlspecialchars($item['icon'] ?? 'far fa-circle nav-icon'); ?>"></i>
                                            <p>
                                                <?php echo htmlspecialchars($item['text']); ?>
                                                <?php if ($has_submenu): ?><i class="right fas fa-angle-left"></i><?php endif; ?>
                                            </p>
                                        </a>
                                        <?php if ($has_submenu): ?>
                                            <ul class="nav nav-treeview">
                                                <?php foreach ($item['submenu'] as $sub_item_key_loop => $sub_item_loop): 
                                                    if (!is_array($sub_item_loop) || !isset($sub_item_loop['link']) || !isset($sub_item_loop['text'])) {
                                                        error_log("HEADER_SUBMENU_ITEM_ERROR: Struktur submenu '$sub_item_key_loop' tidak valid untuk parent '$menu_key'.");
                                                        continue;
                                                    }
                                                    $sub_link_processed_loop = ($sub_item_loop['link'] == '#') ? '#' : htmlspecialchars(rtrim($app_base_path, '/') . '/' . ltrim($sub_item_loop['link'], '/'));
                                                    $sub_link_processed_loop = preg_replace('/\/+/', '/', $sub_link_processed_loop);
                                                    $is_sub_active_loop = isActivePath($sub_item_loop['link'], $current_request_uri_for_menu);
                                                ?>
                                                    <li class="nav-item">
                                                        <a href="<?php echo $sub_link_processed_loop; ?>" class="nav-link <?php if ($is_sub_active_loop) echo 'active'; ?>">
                                                            <i class="<?php echo htmlspecialchars($sub_item_loop['icon'] ?? 'far fa-dot-circle nav-icon'); ?>"></i>
                                                            <p><?php echo htmlspecialchars($sub_item_loop['text']); ?></p>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </li>
                            <?php endforeach; 
                            else: 
                                if (isset($user_login_status) && $user_login_status === true && isset($user_role_utama) && $user_role_utama != 'guest'): ?>
                                    <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon fas fa-exclamation-triangle text-warning"></i><p>Menu tidak tersedia.</p></a></li>
                                <?php endif; ?>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </aside>
            
            <div class="content-wrapper">
                <?php // content-header SEBAIKNYA DIBUAT OLEH MASING-MASING HALAMAN MODUL ?>
                <?php // Ini memberikan fleksibilitas lebih untuk judul dan breadcrumb per halaman. ?>
                <?php // Contoh di dashboard.php: ?>
                <?php /*
                <div class="content-header">
                    <div class="container-fluid">
                        <div class="row mb-2">
                            <div class="col-sm-6"><h1 class="m-0"><?php echo $page_title_display; ?></h1></div>
                            <div class="col-sm-6">
                                <ol class="breadcrumb float-sm-right">
                                    <li class="breadcrumb-item"><a href="...">Home</a></li>
                                    <li class="breadcrumb-item active">Dashboard</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                */ ?>
                <section class="content"> <?php // Pembuka section.content ?>
                    <div class="container-fluid"> <?php // Pembuka container-fluid ?>
                        <?php // Pesan Sukses/Error Global (Flash Messages) ?>
                        <?php if (isset($_SESSION['pesan_sukses_global'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($_SESSION['pesan_sukses_global']); unset($_SESSION['pesan_sukses_global']); ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['pesan_error_global'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($_SESSION['pesan_error_global']); unset($_SESSION['pesan_error_global']); ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>
                            </div>
                        <?php endif; ?>
                        <?php // Konten utama halaman akan dimulai setelah ini oleh file pemanggil ?>

        <?php else: // Kondisi untuk halaman login atau jika pengguna adalah guest ?>
            <div class="<?php if (basename($_SERVER['PHP_SELF']) == 'login.php') echo 'login-box'; else echo 'container pt-3'; ?>">
            <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php' && isset($user_login_status) && $user_login_status === true && isset($user_role_utama) && $user_role_utama == 'guest'): ?>
                <div class="alert alert-info text-center mt-5">
                    <h4>Area Tamu</h4>
                    <p>Selamat datang di Reaktor System. Anda login sebagai tamu.<br>
                    Silakan hubungi administrator jika Anda seharusnya memiliki peran lain atau ingin mendaftar.</p>
                    <p><a href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/logout.php'); ?>" class="btn btn-primary btn-block">Logout</a></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
<!-- Tag penutup akan ada di footer.php -->