<?php
// File: public_html/reaktorsystem/public_profile.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (file_exists(__DIR__ . '/core/init_core.php')) {
    require_once(__DIR__ . '/core/init_core.php');
} else {
    header("HTTP/1.1 500 Internal Server Error"); error_log("PUBLIC_PROFILE_FATAL: init_core.php tidak ditemukan.");
    echo "<!DOCTYPE html><html><head><title>Error Sistem</title></head><body><h1>Kesalahan Konfigurasi Sistem Inti.</h1><p>Hubungi administrator.</p></body></html>"; exit;
}

$profil_id_from_url = $_GET['id'] ?? null;
if (empty($profil_id_from_url) || !preg_match('/^[0-9A-Za-z_~\-]+$/', $profil_id_from_url) || strlen($profil_id_from_url) > 50) {
    header("HTTP/1.1 400 Bad Request"); $page_title_error = "ID Tidak Valid";
    echo "<!DOCTYPE html><html lang='id'><head><title>{$page_title_error}</title><link rel='stylesheet' href='" . htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/dist/css/adminlte.min.css') . "'></head><body class='layout-top-nav'><div class='wrapper'><div class='content-wrapper'><section class='content'><div class='container'><div class='error-page' style='margin-top:100px;'><h2 class='headline text-warning'> 400</h2><div class='error-content'><h3><i class='fas fa-exclamation-triangle text-warning'></i> Oops! ID Pengguna Tidak Valid.</h3><p>Parameter ID yang diberikan tidak valid atau kosong. Pastikan QR Code yang Anda scan benar.<br/>Anda bisa kembali ke <a href='" . htmlspecialchars(rtrim($app_base_path, '/')) . "'>halaman utama</a>.</p></div></div></div></section></div></div></body></html>";
    exit;
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    header("HTTP/1.1 503 Service Unavailable"); $page_title_error = "Kesalahan Database"; error_log("PUBLIC_PROFILE_FATAL: Koneksi PDO tidak valid.");
    echo "<!DOCTYPE html><html lang='id'><head><title>{$page_title_error}</title><link rel='stylesheet' href='" . htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/dist/css/adminlte.min.css') . "'></head><body class='layout-top-nav'><div class='wrapper'><div class='content-wrapper'><section class='content'><div class='container'><div class='error-page' style='margin-top:100px;'><h2 class='headline text-danger'> 503</h2><div class='error-content'><h3><i class='fas fa-database text-danger'></i> Oops! Layanan Database Tidak Tersedia.</h3><p>Tidak dapat terhubung ke database untuk menampilkan profil. Silakan coba lagi nanti atau hubungi administrator.</p></div></div></div></section></div></div></body></html>";
    exit;
}

$user_data_public = null; $roles_data_public = []; $prestasi_data_public = [];
$atlet_data_public = null; $pelatih_data_public = null; $wasit_data_public = null;
$peran_utama_display_public = "Anggota Terdaftar";

try {
    $stmt_user = $pdo->prepare("SELECT nik, nama_lengkap, email, foto, tanggal_lahir, jenis_kelamin, nomor_telepon, alamat, is_approved FROM pengguna WHERE nik = :profil_id AND is_approved = 1");
    $stmt_user->bindParam(':profil_id', $profil_id_from_url, PDO::PARAM_STR);
    $stmt_user->execute();
    $user_data_public = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user_data_public) {
        header("HTTP/1.1 404 Not Found"); $page_title_error = "Profil Tidak Ditemukan";
        echo "<!DOCTYPE html><html lang='id'><head><title>{$page_title_error}</title><link rel='stylesheet' href='" . htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/dist/css/adminlte.min.css') . "'></head><body class='layout-top-nav'><div class='wrapper'><div class='content-wrapper'><section class='content'><div class='container'><div class='error-page' style='margin-top:100px;'><h2 class='headline text-info'> 404</h2><div class='error-content'><h3><i class='fas fa-search text-info'></i> Oops! Profil Tidak Ditemukan.</h3><p>Pengguna dengan ID tersebut tidak ditemukan atau akun tidak aktif.<br/>Anda bisa kembali ke <a href='" . htmlspecialchars(rtrim($app_base_path, '/')) . "'>halaman utama</a>.</p></div></div></div></section></div></div></body></html>";
        exit;
    }

    // Pengambilan peran dari tabel 'anggota' dan penentuan peran utama
    $role_priority_map = ['super_admin' => 5, 'admin_koni' => 4, 'pengurus_cabor' => 3];
    $highest_structural_priority = -1;
    $highest_structural_role_info = null;
    // ... (Logika pengambilan peran anggota dan penentuan $peran_utama_display_public serta $roles_data_public seperti di versi sebelumnya yang berfungsi) ...
    // Ini adalah contoh ringkas, pastikan Anda menggunakan logika lengkap dari versi sebelumnya yang sudah benar untuk bagian ini.
    try {
        $stmt_roles_anggota = $pdo->prepare("SELECT a.role, c.nama_cabor, a.jabatan FROM anggota a LEFT JOIN cabang_olahraga c ON a.id_cabor = c.id_cabor WHERE a.nik = :profil_id AND a.is_verified = 1 ORDER BY FIELD(a.role, 'super_admin', 'admin_koni', 'pengurus_cabor') DESC, a.id_anggota ASC");
        $stmt_roles_anggota->bindParam(':profil_id', $profil_id_from_url, PDO::PARAM_STR);
        $stmt_roles_anggota->execute();
        $db_roles_anggota_public = $stmt_roles_anggota->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($db_roles_anggota_public)) {
            $first_role_org = $db_roles_anggota_public[0];
            $peran_utama_display_public = ucwords(str_replace('_', ' ', $first_role_org['role']));
            if ($first_role_org['role'] == 'pengurus_cabor' && !empty($first_role_org['nama_cabor'])) {
                $peran_utama_display_public .= " (" . htmlspecialchars($first_role_org['nama_cabor']) . ")";
            }
            foreach ($db_roles_anggota_public as $role_item) {
                $display_text = htmlspecialchars(ucwords(str_replace('_', ' ', $role_item['role'])));
                if (!empty($role_item['nama_cabor'])) $display_text .= " - " . htmlspecialchars($role_item['nama_cabor']);
                if (!empty($role_item['jabatan'])) $display_text .= " (" . htmlspecialchars($role_item['jabatan']) . ")";
                if(!in_array($display_text, $roles_data_public)) $roles_data_public[] = $display_text;
            }
        }
    } catch (PDOException $e_anggota) { error_log("PUBLIC_PROFILE_DB_ERROR (Anggota) ID '{$profil_id_from_url}': " . $e_anggota->getMessage()); }

    // Pengambilan data Atlet, Pelatih, Wasit, Prestasi
    // ... (Gunakan logika lengkap dari versi sebelumnya yang sudah mengambil data ini dengan benar dan mengisi $atlet_data_public, $pelatih_data_public, $wasit_data_public, $prestasi_data_public, dan menambahkan ke $roles_data_public) ...
    // Ini adalah contoh ringkas, pastikan Anda menggunakan logika lengkap dari versi sebelumnya yang sudah benar untuk bagian ini.
    try {
        $stmt_atlet = $pdo->prepare("SELECT atl.*, c.nama_cabor, k.nama_klub FROM atlet atl LEFT JOIN cabang_olahraga c ON atl.id_cabor = c.id_cabor LEFT JOIN klub k ON atl.id_klub = k.id_klub WHERE atl.nik = :profil_id AND atl.status_pendaftaran = 'disetujui'");
        $stmt_atlet->execute([':profil_id' => $profil_id_from_url]);
        $atlet_data_public = $stmt_atlet->fetch(PDO::FETCH_ASSOC);
        if ($atlet_data_public) {
            $display_atlet_text = "Atlet - " . htmlspecialchars($atlet_data_public['nama_cabor'] ?? 'Cbr Blm Ada') . (!empty($atlet_data_public['nama_klub']) ? " (Klub: ".htmlspecialchars($atlet_data_public['nama_klub']).")" : "");
            if(!in_array($display_atlet_text, $roles_data_public)) $roles_data_public[] = $display_atlet_text;
            // (Logika update $peran_utama_display_public jika perlu)
            $stmt_prestasi = $pdo->prepare("SELECT nama_kejuaraan, tingkat_kejuaraan, tahun_perolehan AS tahun_prestasi, medali_juara_peringkat AS medali_juara FROM prestasi WHERE nik = :profil_id AND status_approval = 'disetujui_admin' ORDER BY tahun_perolehan DESC LIMIT 5");
            $stmt_prestasi->execute([':profil_id' => $profil_id_from_url]);
            $prestasi_data_public = $stmt_prestasi->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e_atlet) { error_log("PUBLIC_PROFILE_DB_ERROR (Atlet) ID '{$profil_id_from_url}': " . $e_atlet->getMessage()); }
    // (Lakukan hal serupa untuk Pelatih dan Wasit)

    $roles_data_public = array_unique($roles_data_public);

} catch (PDOException $e_main) { /* ... (Error handling utama) ... */ }

$page_title_public = "Profil Publik: " . htmlspecialchars($user_data_public['nama_lengkap']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title_public; ?> - Reaktor System</title>
    <link rel="icon" href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/uploads/logos/logo_koni.png'); ?>" type="image/png">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/plugins/fontawesome-free/css/all.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/dist/css/adminlte.min.css'); ?>">
    <style>
        body { background-color: #f4f6f9; /* Warna background AdminLTE */ }
        .profile-public-card-wrapper { padding-top: 20px; padding-bottom: 20px; }
        .profile-public-card {
            background-color: #fff;
            border-radius: .5rem; /* Sedikit lebih besar dari default AdminLTE */
            box-shadow: 0 0 1px rgba(0,0,0,.125),0 1px 3px rgba(0,0,0,.2); /* Shadow AdminLTE */
            margin-bottom: 1rem;
        }
        .profile-header-banner {
            background: linear-gradient(to right, #007bff, #0056b3);
            height: 120px; /* Tinggi banner */
            border-top-left-radius: .5rem;
            border-top-right-radius: .5rem;
        }
        .profile-image-container {
            margin-top: -60px; /* Setengah dari tinggi gambar + border agar foto overlay banner */
            margin-bottom: 1rem;
        }
        .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 5px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
            object-fit: cover; /* Pastikan gambar terisi tanpa distorsi */
            background-color: #e9ecef; /* Warna fallback jika foto tidak ada */
        }
        .profile-info-top { padding-top: 0; } /* Hapus padding atas jika foto di atas */
        .profile-name { font-weight: 600; font-size: 1.5rem; color: #333; }
        .profile-role-main { color: #6c757d; font-size: 1rem; margin-bottom: 1.25rem; }
        .list-group-item strong { min-width: 100px; display: inline-block; }
        .section-title { font-size: 1.1rem; font-weight: 500; color: #007bff; margin-top: 1.25rem; margin-bottom: 0.5rem; border-bottom: 1px solid #dee2e6; padding-bottom: 0.3rem; }
        .prestasi-item::before { content: "\f091"; font-family: "Font Awesome 5 Free"; font-weight: 900; color: #ffc107; margin-right: 0.5rem; }
    </style>
</head>
<body class="layout-top-nav">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand-md navbar-light navbar-white">
        <div class="container">
            <a href="<?php echo htmlspecialchars(rtrim($app_base_path, '/')); ?>" class="navbar-brand">
                <img src="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/uploads/logos/logo_koni.png'); ?>" alt="Logo KONI" class="brand-image img-circle elevation-3" style="opacity: .8; max-height:33px;">
                <span class="brand-text font-weight-light">Reaktor System</span> <small>KONI Serdang Bedagai</small>
            </a>
            <ul class="navbar-nav ml-auto"><li class="nav-item"><span class="navbar-text"><i class="fas fa-check-circle text-success"></i> Profil Terverifikasi</span></li></ul>
        </div>
    </nav>

    <div class="content-wrapper">
        <div class="content profile-public-card-wrapper">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8 col-md-10">
                        <div class="card profile-public-card">
                            <div class="profile-header-banner">
                                <!-- Banner Biru -->
                            </div>
                            <div class="card-body profile-info-top">
                                <div class="text-center profile-image-container">
                                    <?php
                                    $foto_url_publik_display = rtrim($app_base_path, '/') . '/' . ltrim($default_avatar_path, '/');
                                    if (!empty($user_data_public['foto'])) {
                                        $path_foto_db_publik_display = $user_data_public['foto'];
                                        if (isset($_SERVER['DOCUMENT_ROOT'])) {
                                            $server_doc_root_publik_display = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
                                            $app_path_server_publik_display = $server_doc_root_publik_display . rtrim($app_base_path, '/\\');
                                            $file_foto_server_publik_display = rtrim($app_path_server_publik_display, '/') . '/' . ltrim($path_foto_db_publik_display, '/');
                                            if (file_exists(preg_replace('/\/+/', '/', $file_foto_server_publik_display))) {
                                                $foto_url_publik_display = rtrim($app_base_path, '/') . '/' . ltrim($path_foto_db_publik_display, '/');
                                            }
                                        }
                                    }
                                    ?>
                                    <img class="profile-image" src="<?php echo htmlspecialchars(preg_replace('/\/+/', '/', $foto_url_publik_display)); ?>" alt="Foto Profil <?php echo htmlspecialchars($user_data_public['nama_lengkap']); ?>">
                                </div>

                                <div class="text-center">
                                    <h3 class="profile-name"><?php echo htmlspecialchars($user_data_public['nama_lengkap']); ?></h3>
                                    <p class="profile-role-main"><?php echo htmlspecialchars($peran_utama_display_public); ?></p>
                                </div>

                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">
                                        <strong><i class="fas fa-id-card mr-2 text-muted"></i>NIK</strong> <span class="float-right"><?php echo htmlspecialchars($user_data_public['nik']); ?></span>
                                    </li>
                                    <?php if(!empty($user_data_public['email'])): ?>
                                    <li class="list-group-item">
                                        <strong><i class="fas fa-envelope mr-2 text-muted"></i>Email</strong> <span class="float-right"><?php echo htmlspecialchars($user_data_public['email']); ?></span>
                                    </li>
                                    <?php endif; ?>
                                    <?php if(!empty($user_data_public['nomor_telepon'])): ?>
                                    <li class="list-group-item">
                                        <strong><i class="fas fa-phone mr-2 text-muted"></i>No. Telepon</strong> <span class="float-right"><?php echo htmlspecialchars($user_data_public['nomor_telepon']); ?></span>
                                    </li>
                                    <?php endif; ?>
                                </ul>

                                <?php if (!empty($roles_data_public)): ?>
                                    <h5 class="section-title"><i class="fas fa-sitemap mr-1"></i> Rincian Peran & Keanggotaan</h5>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($roles_data_public as $role_text) : ?>
                                            <li class="list-group-item"><i class="fas fa-check text-primary mr-2"></i><?php echo $role_text; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                
                                <?php if ($atlet_data_public): ?>
                                    <h5 class="section-title"><i class="fas fa-running mr-1"></i> Informasi Atlet</h5>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item"><strong>Cabang Olahraga</strong> <span class="float-right"><?php echo htmlspecialchars($atlet_data_public['nama_cabor'] ?? '-'); ?></span></li>
                                        <li class="list-group-item"><strong>Klub</strong> <span class="float-right"><?php echo htmlspecialchars($atlet_data_public['nama_klub'] ?? 'Tidak Terdaftar'); ?></span></li>
                                    </ul>
                                    <?php if (!empty($prestasi_data_public)): ?>
                                        <h6 class="mt-3 mb-2 font-weight-bold"><i class="text-warning"></i>Prestasi Unggulan:</h6>
                                        <ul class="list-unstyled">
                                            <?php foreach ($prestasi_data_public as $pres): ?>
                                                <li class="prestasi-item mb-1">
                                                    <strong><?php echo htmlspecialchars($pres['medali_juara'] ?? 'Partisipasi'); ?></strong> - <?php echo htmlspecialchars($pres['nama_kejuaraan']); ?>
                                                    <small class="text-muted d-block">(<?php echo htmlspecialchars($pres['tingkat_kejuaraan']); ?> - <?php echo htmlspecialchars($pres['tahun_prestasi']); ?>)</small>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                         <p class="text-muted"><small>Belum ada data prestasi terverifikasi.</small></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <!-- Tambahkan blok untuk Pelatih dan Wasit jika datanya ada -->

                                <div class="text-center mt-4 pt-3 border-top">
                                    <img src="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/uploads/logos/logo_koni.png'); ?>" alt="Logo KONI" style="width: 35px; opacity:0.7; margin-bottom: 5px;"><br>
                                    <small class="text-muted">ID Card Digital Terverifikasi oleh Reaktor System<br>KONI Kabupaten Serdang Bedagai © <?php echo date("Y"); ?></small>
                                </div>
                            </div> 
                        </div> 
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="main-footer" style="margin-left: 0 !important; text-align:center;">
        <small>Powered by Reaktor System</small>
    </footer>
</div>
<script src="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/plugins/jquery/jquery.min.js'); ?>"></script>
<script src="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
<script src="<?php echo htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/dist/js/adminlte.min.js'); ?>"></script>
</body>
</html>