<?php
// File: reaktorsystem/admin/users/detail_pengguna.php
$page_title = "Detail Data Pengguna";

$additional_css = []; // Tidak ada CSS tambahan spesifik untuk halaman ini
$additional_js = [];  // Tidak ada JS tambahan spesifik untuk halaman ini

require_once(__DIR__ . '/../../core/header.php'); 

// Pengecekan sesi & peran pengguna, serta variabel inti
// Pastikan variabel seperti $default_avatar_path_relative dan APP_PATH_BASE ada dari init_core.php
if (!isset($user_nik) || !isset($user_role_utama) || !isset($app_base_path) || !isset($pdo) || 
    !in_array($user_role_utama, ['super_admin', 'admin_koni']) || !defined('APP_PATH_BASE') || !isset($default_avatar_path_relative)) { 
    
    $_SESSION['pesan_error_global'] = "Akses ditolak, sesi tidak valid, atau konfigurasi inti sistem bermasalah.";
    $redirect_url_detail_err = rtrim($app_base_path ?? '/', '/') . "/dashboard.php";
    if (!isset($user_login_status) || $user_login_status !== true) { // Jika belum login, ke halaman login
        $redirect_url_detail_err = rtrim($app_base_path ?? '/', '/') . "/auth/login.php";
    }

    if (!headers_sent()) { 
        header("Location: " . $redirect_url_detail_err); 
    } else { 
        echo "<div class='alert alert-danger text-center m-3'>Error: Akses ditolak atau konfigurasi bermasalah. Kembali ke <a href='" . htmlspecialchars($redirect_url_detail_err, ENT_QUOTES, 'UTF-8') . "'>halaman sebelumnya</a>.</div>"; 
    }
    // Sertakan footer jika ada, meskipun mungkin tidak akan ter-render jika header() berhasil
    if (file_exists(__DIR__ . '/../../core/footer.php')) { $inline_script = $inline_script ?? ''; require_once(__DIR__ . '/../../core/footer.php'); }
    exit();
}

$nik_to_view_detail_val = null; 
$pengguna_detail_data_arr = null;
$anggota_roles_data_arr = [];
$daftar_pengguna_page_url = "daftar_pengguna.php"; // URL relatif untuk kembali

if (isset($_GET['nik']) && preg_match('/^\d{1,16}$/', trim($_GET['nik']))) {
    $nik_to_view_detail_val = trim($_GET['nik']);
    try {
        $stmt_pengguna_detail_fetch = $pdo->prepare("SELECT * FROM pengguna WHERE nik = :nik_param");
        $stmt_pengguna_detail_fetch->bindParam(':nik_param', $nik_to_view_detail_val, PDO::PARAM_STR); 
        $stmt_pengguna_detail_fetch->execute(); 
        $pengguna_detail_data_arr = $stmt_pengguna_detail_fetch->fetch(PDO::FETCH_ASSOC);
        
        if (!$pengguna_detail_data_arr) { 
            $_SESSION['pesan_error_global'] = "Data Pengguna dengan NIK " . htmlspecialchars($nik_to_view_detail_val) . " tidak ditemukan."; 
            header("Location: " . $daftar_pengguna_page_url); 
            exit(); 
        }

        // Ambil data peran anggota
        $stmt_anggota_roles_fetch = $pdo->prepare("
            SELECT ang.jabatan, ang.role, ang.tingkat_pengurus, ang.is_verified, 
                   ang.verified_at, co.nama_cabor, verifier.nama_lengkap AS nama_verifier
            FROM anggota ang
            LEFT JOIN cabang_olahraga co ON ang.id_cabor = co.id_cabor
            LEFT JOIN pengguna verifier ON ang.verified_by_nik = verifier.nik
            WHERE ang.nik = :nik_param
            ORDER BY ang.role ASC, ang.jabatan ASC
        ");
        $stmt_anggota_roles_fetch->bindParam(':nik_param', $nik_to_view_detail_val, PDO::PARAM_STR);
        $stmt_anggota_roles_fetch->execute();
        $anggota_roles_data_arr = $stmt_anggota_roles_fetch->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e_detail) { 
        error_log("Detail Pengguna Error (NIK: " . $nik_to_view_detail_val . "): " . $e_detail->getMessage());
        $_SESSION['pesan_error_global'] = "Terjadi kesalahan saat mengambil data detail pengguna."; 
        header("Location: " . $daftar_pengguna_page_url); 
        exit(); 
    }
} else { 
    $_SESSION['pesan_error_global'] = "NIK Pengguna tidak valid atau tidak disediakan untuk ditampilkan detailnya."; 
    header("Location: " . $daftar_pengguna_page_url); 
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
          <li class="breadcrumb-item"><a href="<?php echo $daftar_pengguna_page_url; ?>">Manajemen Pengguna</a></li>
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
                <div class="card card-primary card-outline shadow mb-4"> <?php // Menggunakan card-outline agar lebih ringan ?>
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-user-circle mr-1"></i> Detail untuk <strong><?php echo htmlspecialchars($pengguna_detail_data_arr['nama_lengkap']); ?></strong></h3>
                        <div class="card-tools">
                            <a href="<?php echo $daftar_pengguna_page_url; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                            <a href="form_edit_pengguna.php?nik=<?php echo htmlspecialchars($pengguna_detail_data_arr['nik']); ?>" class="btn btn-sm btn-warning ml-2"><i class="fas fa-edit mr-1"></i> Edit Pengguna Ini</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="mt-1 mb-3 text-primary"><i class="fas fa-address-card mr-1"></i> Informasi Akun Pengguna</h5>
                        <div class="row mb-3">
                            <div class="col-md-3 text-center align-self-start">
                                <?php
                                // Logika Path Foto yang sudah disempurnakan
                                $url_foto_profil_detail = rtrim($app_base_path, '/') . '/' . ltrim($default_avatar_path_relative, '/'); // Default dari init_core
                                $pesan_foto_detail_info = "Foto profil default.";

                                if (!empty($pengguna_detail_data_arr['foto'])) {
                                    $path_foto_pengguna_relatif_dtl = ltrim($pengguna_detail_data_arr['foto'], '/');
                                    $path_foto_pengguna_server_dtl = rtrim(APP_PATH_BASE, '/\\') . '/' . $path_foto_pengguna_relatif_dtl;
                                    $path_foto_pengguna_server_dtl = preg_replace('/\/+/', '/', $path_foto_pengguna_server_dtl);

                                    if (file_exists($path_foto_pengguna_server_dtl) && is_file($path_foto_pengguna_server_dtl)) {
                                        $url_foto_profil_detail = rtrim($app_base_path, '/') . '/' . $path_foto_pengguna_relatif_dtl;
                                        $pesan_foto_detail_info = ""; 
                                    } else {
                                        $pesan_foto_detail_info = "File foto profil tidak ditemukan, menampilkan default.";
                                    }
                                } else {
                                    $pesan_foto_detail_info = "Pengguna belum unggah foto profil.";
                                }
                                $url_foto_profil_detail = preg_replace('/\/+/', '/', $url_foto_profil_detail);
                                ?>
                                <img src="<?php echo htmlspecialchars($url_foto_profil_detail); ?>"
                                     alt="Foto <?php echo htmlspecialchars($pengguna_detail_data_arr['nama_lengkap']); ?>"
                                     class="img-fluid img-circle elevation-2 mb-2"
                                     style="width: 150px; height: 150px; display: block; margin-left: auto; margin-right: auto; object-fit: cover; border: 3px solid #adb5bd; padding: 3px;">
                                <?php if (!empty($pesan_foto_detail_info) && ($foto_profil_untuk_ditampilkan_edit ?? '') === $default_avatar_path_relative): // Tampilkan pesan hanya jika itu foto default atau ada masalah file ?>
                                    <p class="text-muted text-xs mt-1"><?php echo htmlspecialchars($pesan_foto_detail_info); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-9">
                                <h4><?php echo htmlspecialchars($pengguna_detail_data_arr['nama_lengkap']); ?></h4>
                                <p class="mb-1"><i class="fas fa-id-badge fa-fw mr-2 text-muted"></i>NIK: <strong><?php echo htmlspecialchars($pengguna_detail_data_arr['nik']); ?></strong></p>
                                <p class="mb-1"><i class="fas fa-envelope fa-fw mr-2 text-muted"></i>Email: <?php echo $pengguna_detail_data_arr['email'] ? "<a href='mailto:" . htmlspecialchars($pengguna_detail_data_arr['email']) . "'>" . htmlspecialchars($pengguna_detail_data_arr['email']) . "</a>" : '<em>Tidak Ada</em>'; ?></p>
                                <p class="mb-1"><i class="fas fa-phone fa-fw mr-2 text-muted"></i>Telepon: <?php echo $pengguna_detail_data_arr['nomor_telepon'] ? htmlspecialchars($pengguna_detail_data_arr['nomor_telepon']) : '<em>Tidak Ada</em>'; ?></p>
                                <p class="mb-1"><i class="fas fa-birthday-cake fa-fw mr-2 text-muted"></i>Tgl Lahir: <?php echo $pengguna_detail_data_arr['tanggal_lahir'] ? date('d F Y', strtotime($pengguna_detail_data_arr['tanggal_lahir'])) : '<em>Tidak Ada</em>'; ?></p>
                                <p class="mb-1"><i class="fas fa-venus-mars fa-fw mr-2 text-muted"></i>Jenis Kelamin: <?php echo $pengguna_detail_data_arr['jenis_kelamin'] ? htmlspecialchars($pengguna_detail_data_arr['jenis_kelamin']) : '<em>Tidak Ada</em>'; ?></p>
                                <p class="mb-0"><i class="fas fa-map-marker-alt fa-fw mr-2 text-muted"></i>Alamat: <?php echo $pengguna_detail_data_arr['alamat'] ? nl2br(htmlspecialchars($pengguna_detail_data_arr['alamat'])) : '<em>Tidak Ada</em>'; ?></p>
                            </div>
                        </div>
                        <hr class="mt-2 mb-3">

                        <h5 class="mt-3 mb-3 text-primary"><i class="fas fa-user-shield fa-fw mr-1"></i> Status Akun & Registrasi</h5>
                        <dl class="row">
                            <dt class="col-sm-4">Status Akun Sistem:</dt>
                            <dd class="col-sm-8">
                                <span class="badge badge-<?php echo ($pengguna_detail_data_arr['is_approved'] ?? 0) ? 'success' : 'warning'; ?> p-1">
                                    <?php echo ($pengguna_detail_data_arr['is_approved'] ?? 0) ? 'Disetujui / Aktif' : 'Pending Persetujuan'; ?>
                                </span>
                            </dd>
                            <dt class="col-sm-4">Tanggal Registrasi Akun:</dt>
                            <dd class="col-sm-8"><?php echo $pengguna_detail_data_arr['created_at'] ? date('d F Y, H:i:s', strtotime($pengguna_detail_data_arr['created_at'])) : '<em>Tidak diketahui</em>'; ?></dd>
                            <dt class="col-sm-4">Update Terakhir Akun:</dt>
                            <dd class="col-sm-8"><?php echo $pengguna_detail_data_arr['updated_at'] ? date('d F Y, H:i:s', strtotime($pengguna_detail_data_arr['updated_at'])) : '<em>Tidak diketahui</em>'; ?></dd>
                        </dl>
                        <hr class="mt-2 mb-3">

                        <h5 class="mt-3 mb-3 text-primary"><i class="fas fa-user-tag fa-fw mr-1"></i> Peran & Jabatan di Sistem</h5>
                        <?php if (!empty($anggota_roles_data_arr)): $role_count_detail = 0; ?>
                            <?php foreach($anggota_roles_data_arr as $peran_item_detail): $role_count_detail++; ?>
                                <div class="callout callout-info mb-3"> <?php // Menggunakan callout-info untuk peran ?>
                                    <h6><strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $peran_item_detail['role']))); ?></strong></h6>
                                    <dl class="row dl-horizontal">
                                        <dt class="col-sm-4">Jabatan:</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($peran_item_detail['jabatan']); ?></dd>
                                        
                                        <?php if ($peran_item_detail['role'] == 'pengurus_cabor' && !empty($peran_item_detail['nama_cabor'])): ?>
                                            <dt class="col-sm-4">Cabang Olahraga:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($peran_item_detail['nama_cabor']); ?></dd>
                                        <?php endif; ?>

                                        <dt class="col-sm-4">Tingkat Pengurus:</dt>
                                        <dd class="col-sm-8"><?php echo $peran_item_detail['tingkat_pengurus'] ? htmlspecialchars(ucfirst($peran_item_detail['tingkat_pengurus'])) : '<em>N/A</em>'; ?></dd>
                                        
                                        <dt class="col-sm-4">Status Verifikasi Peran:</dt>
                                        <dd class="col-sm-8">
                                            <span class="badge badge-<?php echo ($peran_item_detail['is_verified'] ?? 0) ? 'success' : 'warning'; ?> p-1">
                                                <?php echo ($peran_item_detail['is_verified'] ?? 0) ? 'Terverifikasi' : 'Pending'; ?>
                                            </span>
                                            <?php if (($peran_item_detail['is_verified'] ?? 0) == 1 && !empty($peran_item_detail['verified_by_nik'])): ?>
                                                <br><small class="text-muted">Oleh: <?php echo htmlspecialchars($peran_item_detail['nama_verifier'] ?? ('NIK: ' . $peran_item_detail['verified_by_nik'])); ?>
                                                <?php if ($peran_item_detail['verified_at']): ?> (<?php echo date('d M Y, H:i', strtotime($peran_item_detail['verified_at'])); ?>)<?php endif; ?>
                                                </small>
                                            <?php endif; ?>
                                        </dd>
                                    </dl>
                                </div>
                                <?php if ($role_count_detail < count($anggota_roles_data_arr)) echo "<hr class='my-2 border-dashed'>"; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted"><em>Pengguna ini belum memiliki peran struktural yang terdaftar di sistem.</em></p>
                        <?php endif; ?>
                        
                    </div>
                    <div class="card-footer text-center">
                        <a href="<?php echo $daftar_pengguna_page_url; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Pengguna</a> 
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Tidak ada JavaScript spesifik yang kompleks untuk halaman ini,
// jadi $inline_script bisa dibiarkan kosong atau diisi jika ada inisialisasi minor.
$inline_script = $inline_script ?? ''; 
require_once(__DIR__ . '/../../core/footer.php');
?>