<?php
// File: reaktorsystem/admin/roles/detail_anggota.php
$page_title = "Detail Peran Anggota";

$additional_css = [];
$additional_js = [];

require_once(__DIR__ . '/../../core/header.php'); 

// Pengecekan sesi & peran pengguna, serta variabel inti
if (!isset($user_nik) || !isset($user_role_utama) || !isset($app_base_path) || !isset($pdo) || 
    $user_role_utama != 'super_admin' || !defined('APP_PATH_BASE') || !isset($default_avatar_path_relative)) { 
    
    $_SESSION['pesan_error_global'] = "Akses ditolak, sesi tidak valid, atau konfigurasi inti sistem bermasalah.";
    $redirect_url_detail_agt_err = rtrim($app_base_path ?? '/', '/') . "/dashboard.php";
    if (!isset($user_login_status) || $user_login_status !== true) {
        $redirect_url_detail_agt_err = rtrim($app_base_path ?? '/', '/') . "/auth/login.php";
    }
    if (!headers_sent()) { header("Location: " . $redirect_url_detail_agt_err); }
    else { echo "<div class='alert alert-danger text-center m-3'>Error: Akses ditolak. Kembali ke <a href='" . htmlspecialchars($redirect_url_detail_agt_err, ENT_QUOTES, 'UTF-8') . "'>halaman sebelumnya</a>.</div>"; }
    if (file_exists(__DIR__ . '/../../core/footer.php')) { $inline_script = $inline_script ?? ''; require_once(__DIR__ . '/../../core/footer.php'); }
    exit();
}

$id_anggota_to_view = null; 
$anggota_detail_data_view = null; 
$daftar_anggota_page_url_view = "daftar_anggota.php";

if (isset($_GET['id_anggota']) && filter_var($_GET['id_anggota'], FILTER_VALIDATE_INT) && (int)$_GET['id_anggota'] > 0) {
    $id_anggota_to_view = (int)$_GET['id_anggota'];
    try {
        $sql_detail_anggota_view = "SELECT ang.*, 
                               p.nama_lengkap, p.email AS email_akun_pengguna, p.nomor_telepon AS telepon_akun_pengguna, 
                               p.foto AS foto_profil_pengguna, p.tanggal_lahir AS tanggal_lahir_pengguna, 
                               p.jenis_kelamin AS jenis_kelamin_pengguna, p.alamat AS alamat_pengguna,
                               p.created_at AS pengguna_created_at, p.updated_at AS pengguna_updated_at, p.is_approved AS pengguna_is_approved,
                               co.nama_cabor, co.id_cabor AS cabor_id_anggota,
                               verifier.nama_lengkap AS nama_verifier_anggota
                        FROM anggota ang
                        JOIN pengguna p ON ang.nik = p.nik
                        LEFT JOIN cabang_olahraga co ON ang.id_cabor = co.id_cabor
                        LEFT JOIN pengguna verifier ON ang.verified_by_nik = verifier.nik 
                        WHERE ang.id_anggota = :id_anggota_param";
        $stmt_detail_agt_view = $pdo->prepare($sql_detail_anggota_view); 
        $stmt_detail_agt_view->bindParam(':id_anggota_param', $id_anggota_to_view, PDO::PARAM_INT); 
        $stmt_detail_agt_view->execute(); 
        $anggota_detail_data_view = $stmt_detail_agt_view->fetch(PDO::FETCH_ASSOC);
        
        if (!$anggota_detail_data_view) { 
            $_SESSION['pesan_error_global'] = "Data Peran Anggota dengan ID " . htmlspecialchars($id_anggota_to_view) . " tidak ditemukan."; 
            header("Location: " . $daftar_anggota_page_url_view); 
            exit(); 
        }
    } catch (PDOException $e_detail_agt) { 
        error_log("Detail Anggota Error (ID Anggota: " . $id_anggota_to_view . "): " . $e_detail_agt->getMessage());
        $_SESSION['pesan_error_global'] = "Terjadi kesalahan saat mengambil data detail peran anggota."; 
        header("Location: " . $daftar_anggota_page_url_view); 
        exit(); 
    }
} else { 
    $_SESSION['pesan_error_global'] = "ID Peran Anggota tidak valid atau tidak disediakan."; 
    header("Location: " . $daftar_anggota_page_url_view); 
    exit(); 
}
?>

<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0"><?php echo htmlspecialchars($page_title); ?></h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="<?php echo rtrim($app_base_path, '/'); ?>/dashboard.php">Home</a></li>
          <li class="breadcrumb-item"><a href="<?php echo $daftar_anggota_page_url_view; ?>">Manajemen Peran Anggota</a></li>
          <li class="breadcrumb-item active">Detail</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-9 offset-md-1">
                <div class="card card-primary card-outline shadow mb-4"> <?php // Menggunakan card-outline seperti detail_pengguna.php ?>
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-id-badge mr-1"></i> Detail untuk: <strong><?php echo htmlspecialchars($anggota_detail_data_view['nama_lengkap']); ?></strong> (<?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $anggota_detail_data_view['role']))); ?>)</h3>
                        <div class="card-tools">
                            <a href="<?php echo $daftar_anggota_page_url_view; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                            <a href="edit_anggota.php?id_anggota=<?php echo $anggota_detail_data_view['id_anggota']; ?>" class="btn btn-sm btn-warning ml-2"><i class="fas fa-edit mr-1"></i> Edit Peran Ini</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="mt-1 mb-3 text-primary"><i class="fas fa-user-circle mr-1"></i> Informasi Dasar Pengguna</h5>
                        <div class="row mb-3">
                            <div class="col-md-3 text-center align-self-start">
                                <?php
                                // Logika Path Foto konsisten dengan detail_pengguna.php
                                $url_foto_anggota_detail = rtrim($app_base_path, '/') . '/' . ltrim($default_avatar_path_relative, '/');
                                $pesan_foto_info_agt = "Foto profil default.";

                                if (!empty($anggota_detail_data_view['foto_profil_pengguna'])) {
                                    $path_foto_rel_agt = ltrim($anggota_detail_data_view['foto_profil_pengguna'], '/');
                                    $path_foto_server_agt_cek = rtrim(APP_PATH_BASE, '/\\') . '/' . $path_foto_rel_agt;
                                    $path_foto_server_agt_cek = preg_replace('/\/+/', '/', $path_foto_server_agt_cek);

                                    if (file_exists($path_foto_server_agt_cek) && is_file($path_foto_server_agt_cek)) {
                                        $url_foto_anggota_detail = rtrim($app_base_path, '/') . '/' . $path_foto_rel_agt;
                                        $pesan_foto_info_agt = ""; 
                                    } else {
                                        $pesan_foto_info_agt = "File foto pengguna tidak ditemukan, menampilkan default.";
                                    }
                                } else {
                                    $pesan_foto_info_agt = "Pengguna belum mengunggah foto profil.";
                                }
                                $url_foto_anggota_detail = preg_replace('/\/+/', '/', $url_foto_anggota_detail);
                                ?>
                                <img src="<?php echo htmlspecialchars($url_foto_anggota_detail); ?>"
                                     alt="Foto <?php echo htmlspecialchars($anggota_detail_data_view['nama_lengkap']); ?>"
                                     class="img-fluid img-circle elevation-2 mb-2" <?php // Styling foto disamakan ?>
                                     style="width: 150px; height: 150px; display: block; margin-left: auto; margin-right: auto; object-fit: cover; border: 3px solid #adb5bd; padding: 3px;">
                                <?php if (!empty($pesan_foto_info_agt) && $url_foto_anggota_detail === (rtrim($app_base_path, '/') . '/' . ltrim($default_avatar_path_relative, '/')) ): ?>
                                    <p class="text-muted text-xs mt-1"><?php echo htmlspecialchars($pesan_foto_info_agt); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-9">
                                <h4><?php echo htmlspecialchars($anggota_detail_data_view['nama_lengkap']); ?></h4>
                                <p class="mb-1"><i class="fas fa-id-badge fa-fw mr-2 text-muted"></i>NIK: <strong><?php echo htmlspecialchars($anggota_detail_data_view['nik']); ?></strong></p>
                                <p class="mb-1"><i class="fas fa-envelope fa-fw mr-2 text-muted"></i>Email Akun: <?php echo $anggota_detail_data_view['email_akun_pengguna'] ? "<a href='mailto:" . htmlspecialchars($anggota_detail_data_view['email_akun_pengguna']) . "'>" . htmlspecialchars($anggota_detail_data_view['email_akun_pengguna']) . "</a>" : '<em>Tidak Ada</em>'; ?></p>
                                <p class="mb-1"><i class="fas fa-phone fa-fw mr-2 text-muted"></i>Telepon Akun: <?php echo $anggota_detail_data_view['telepon_akun_pengguna'] ? htmlspecialchars($anggota_detail_data_view['telepon_akun_pengguna']) : '<em>Tidak Ada</em>'; ?></p>
                                <p class="mb-1"><i class="fas fa-birthday-cake fa-fw mr-2 text-muted"></i>Tgl Lahir: <?php echo $anggota_detail_data_view['tanggal_lahir_pengguna'] ? date('d F Y', strtotime($anggota_detail_data_view['tanggal_lahir_pengguna'])) : '<em>Tidak Ada</em>'; ?></p>
                                <p class="mb-1"><i class="fas fa-venus-mars fa-fw mr-2 text-muted"></i>Jenis Kelamin: <?php echo $anggota_detail_data_view['jenis_kelamin_pengguna'] ? htmlspecialchars($anggota_detail_data_view['jenis_kelamin_pengguna']) : '<em>Tidak Ada</em>'; ?></p>
                                <p class="mb-0"><i class="fas fa-map-marker-alt fa-fw mr-2 text-muted"></i>Alamat Akun: <?php echo $anggota_detail_data_view['alamat_pengguna'] ? nl2br(htmlspecialchars($anggota_detail_data_view['alamat_pengguna'])) : '<em>Tidak Ada</em>'; ?></p>
                            </div>
                        </div>
                        <hr class="mt-2 mb-3">

                        <h5 class="mt-3 mb-3 text-primary"><i class="fas fa-user-tag fa-fw mr-1"></i> Informasi Peran & Jabatan Anggota</h5>
                        <dl class="row">
                            <dt class="col-sm-4">ID Entri Peran:</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($anggota_detail_data_view['id_anggota']); ?></dd>

                            <dt class="col-sm-4">Jabatan:</dt>
                            <dd class="col-sm-8"><strong><?php echo htmlspecialchars($anggota_detail_data_view['jabatan']); ?></strong></dd>

                            <dt class="col-sm-4">Peran Sistem:</dt>
                            <dd class="col-sm-8">
                                <?php 
                                $role_text_detail_item = str_replace('_', ' ', $anggota_detail_data_view['role']);
                                $role_badge_detail_item = 'secondary';
                                if ($anggota_detail_data_view['role'] == 'super_admin') $role_badge_detail_item = 'danger';
                                elseif ($anggota_detail_data_view['role'] == 'admin_koni') $role_badge_detail_item = 'warning';
                                elseif ($anggota_detail_data_view['role'] == 'pengurus_cabor') $role_badge_detail_item = 'info';
                                ?>
                                <span class="badge badge-<?php echo $role_badge_detail_item; ?> p-1"><?php echo htmlspecialchars(ucwords($role_text_detail_item)); ?></span>
                            </dd>

                            <?php if ($anggota_detail_data_view['role'] == 'pengurus_cabor' && !empty($anggota_detail_data_view['nama_cabor'])): ?>
                                <dt class="col-sm-4">Cabang Olahraga Terkait:</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($anggota_detail_data_view['nama_cabor']); ?> (ID: <?php echo htmlspecialchars($anggota_detail_data_view['cabor_id_anggota']); ?>)</dd>
                            <?php endif; ?>

                            <dt class="col-sm-4">Tingkat Pengurus:</dt>
                            <dd class="col-sm-8"><?php echo $anggota_detail_data_view['tingkat_pengurus'] ? htmlspecialchars(ucfirst($anggota_detail_data_view['tingkat_pengurus'])) : '<em>N/A</em>'; ?></dd>
                            
                            <dt class="col-sm-4">Status Verifikasi Peran Ini:</dt>
                            <dd class="col-sm-8">
                                <span class="badge badge-<?php echo ($anggota_detail_data_view['is_verified'] ?? 0) ? 'success' : 'warning'; ?> p-1">
                                    <?php echo ($anggota_detail_data_view['is_verified'] ?? 0) ? 'Terverifikasi' : 'Pending Verifikasi'; ?>
                                </span>
                            </dd>
                            <?php if (($anggota_detail_data_view['is_verified'] ?? 0) == 1 && !empty($anggota_detail_data_view['verified_by_nik'])): ?>
                                <dt class="col-sm-4">Diverifikasi oleh:</dt>
                                <dd class="col-sm-8">
                                    <?php echo htmlspecialchars($anggota_detail_data_view['nama_verifier_anggota'] ?? ('NIK: ' . $anggota_detail_data_view['verified_by_nik'])); ?>
                                    <?php if ($anggota_detail_data_view['verified_at']): ?>
                                        <small class="text-muted">(pada <?php echo date('d F Y, H:i', strtotime($anggota_detail_data_view['verified_at'])); ?>)</small>
                                    <?php endif; ?>
                                </dd>
                            <?php endif; ?>
                            
                            <?php // Informasi dari tabel pengguna (status akun umum, tanggal registrasi akun) ?>
                            <dt class="col-sm-4 mt-2 border-top pt-2">Status Akun Sistem (Pengguna):</dt>
                            <dd class="col-sm-8 mt-2 border-top pt-2">
                                <span class="badge badge-<?php echo ($anggota_detail_data_view['pengguna_is_approved'] ?? 0) ? 'success' : 'warning'; ?> p-1">
                                    <?php echo ($anggota_detail_data_view['pengguna_is_approved'] ?? 0) ? 'Disetujui / Aktif' : 'Pending Persetujuan'; ?>
                                </span>
                            </dd>
                            <dt class="col-sm-4">Tgl. Registrasi Akun Pengguna:</dt>
                            <dd class="col-sm-8"><?php echo $anggota_detail_data_view['pengguna_created_at'] ? date('d F Y, H:i', strtotime($anggota_detail_data_view['pengguna_created_at'])) : '<em>N/A</em>'; ?></dd>
                        </dl>
                        
                        <?php // Kolom tambahan dari tabel 'anggota' jika ada dan perlu ditampilkan ?>
                        <?php if (!empty($anggota_detail_data_view['kontak_anggota']) || !empty($anggota_detail_data_view['email_anggota']) || !empty($anggota_detail_data_view['foto_anggota'])): ?>
                        <hr class="mt-2 mb-3">
                        <h5 class="mt-3 mb-3 text-primary"><i class="fas fa-address-book fa-fw mr-1"></i> Kontak & Foto Spesifik Peran Ini</h5>
                        <dl class="row">
                            <?php if (!empty($anggota_detail_data_view['kontak_anggota'])): ?>
                                <dt class="col-sm-4">Kontak Peran:</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($anggota_detail_data_view['kontak_anggota']); ?></dd>
                            <?php endif; ?>
                            <?php if (!empty($anggota_detail_data_view['email_anggota'])): ?>
                                <dt class="col-sm-4">Email Peran:</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($anggota_detail_data_view['email_anggota']); ?></dd>
                            <?php endif; ?>
                            <?php if (!empty($anggota_detail_data_view['foto_anggota'])): 
                                $url_foto_peran_detail = rtrim($app_base_path, '/') . '/' . ltrim($default_avatar_path_relative, '/');
                                $path_foto_peran_server = rtrim(APP_PATH_BASE, '/\\') . '/' . ltrim($anggota_detail_data_view['foto_anggota'], '/\\');
                                if(file_exists(preg_replace('/\/+/', '/', $path_foto_peran_server)) && is_file(preg_replace('/\/+/', '/', $path_foto_peran_server))){
                                    $url_foto_peran_detail = rtrim($app_base_path, '/') . '/' . ltrim($anggota_detail_data_view['foto_anggota'], '/');
                                }
                                $url_foto_peran_detail = preg_replace('/\/+/', '/', $url_foto_peran_detail);
                            ?>
                                <dt class="col-sm-4">Foto Peran:</dt>
                                <dd class="col-sm-8"><img src="<?php echo htmlspecialchars($url_foto_peran_detail); ?>" alt="Foto Peran" style="max-height: 60px; border:1px solid #eee;"></dd>
                            <?php endif; ?>
                        </dl>
                        <?php endif; ?>
                        
                    </div>
                    <div class="card-footer text-center">
                        <a href="<?php echo $daftar_anggota_page_url_view; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Peran</a> 
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$inline_script = $inline_script ?? ''; // Pastikan ada
require_once(__DIR__ . '/../../core/footer.php');
?>