<?php
// File: reaktorsystem/modules/wasit/detail_wasit.php

$page_title = "Detail Data Wasit";
$additional_css = [];
$additional_js = [];

require_once(__DIR__ . '/../../core/header.php'); 

// Pengecekan sesi & konfigurasi inti
if (!isset($pdo) || !isset($user_role_utama) || !isset($user_nik) || !isset($app_base_path)) {
    echo "<!DOCTYPE html><html><head><title>Error Konfigurasi</title>";
    if (isset($app_base_path)) {
        echo "<link rel='stylesheet' href='" . htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/dist/css/adminlte.min.css') . "'>";
    }
    echo "</head><body class='hold-transition sidebar-mini'><div class='wrapper'><section class='content'><div class='container-fluid'>";
    echo "<div class='alert alert-danger text-center mt-5 p-3'><strong>Error Kritis:</strong> Sesi tidak valid, konfigurasi aplikasi bermasalah, atau koneksi database gagal.<br>Harap hubungi administrator sistem.</div>";
    echo "</div></section></div></body></html>";
    if (file_exists(__DIR__ . '/../../core/footer.php')) {
        $inline_script = $inline_script ?? '';
        require_once(__DIR__ . '/../../core/footer.php');
    }
    exit();
}

$id_wasit_to_view = null; 
$wasit_detail = null; 

if (isset($_GET['id_wasit']) && filter_var($_GET['id_wasit'], FILTER_VALIDATE_INT) && (int)$_GET['id_wasit'] > 0) {
    $id_wasit_to_view = (int)$_GET['id_wasit'];
    try {
        // Query disempurnakan untuk mencakup semua NIK yang relevan untuk history dan alias cabor_id
        $sql_detail_wasit = "SELECT w.*, 
                               p.nama_lengkap, p.tanggal_lahir, p.jenis_kelamin, p.alamat, p.nomor_telepon, p.email, p.foto AS foto_pengguna, 
                               co.nama_cabor, co.id_cabor AS cabor_id_wasit, /* Tambahkan alias cabor_id */
                               approver.nama_lengkap AS nama_approver, w.approved_by_nik, /* Ambil NIK approver */
                               editor.nama_lengkap AS nama_editor_terakhir, w.updated_by_nik /* Ambil NIK editor */
                        FROM wasit w
                        JOIN pengguna p ON w.nik = p.nik
                        JOIN cabang_olahraga co ON w.id_cabor = co.id_cabor
                        LEFT JOIN pengguna approver ON w.approved_by_nik = approver.nik 
                        LEFT JOIN pengguna editor ON w.updated_by_nik = editor.nik 
                        WHERE w.id_wasit = :id_wasit";
        $stmt_detail_wasit = $pdo->prepare($sql_detail_wasit); 
        $stmt_detail_wasit->bindParam(':id_wasit', $id_wasit_to_view, PDO::PARAM_INT); 
        $stmt_detail_wasit->execute(); 
        $wasit_detail = $stmt_detail_wasit->fetch(PDO::FETCH_ASSOC);
        
        if (!$wasit_detail) { 
            $_SESSION['pesan_error_global'] = "Data Wasit dengan ID " . htmlspecialchars($id_wasit_to_view) . " tidak ditemukan."; 
            header("Location: daftar_wasit.php"); 
            exit(); 
        }
    } catch (PDOException $e) { 
        error_log("Error Detail Wasit (ID: " . $id_wasit_to_view . "): " . $e->getMessage());
        $_SESSION['pesan_error_global'] = "Terjadi kesalahan saat mengambil data detail wasit. Silakan coba lagi atau hubungi administrator."; 
        header("Location: daftar_wasit.php"); 
        exit(); 
    }
} else { 
    $_SESSION['pesan_error_global'] = "ID Wasit tidak valid atau tidak disediakan."; 
    header("Location: daftar_wasit.php"); 
    exit(); 
}

$doc_root = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
$base_path_url = rtrim($app_base_path, '/'); 
$base_path_fs = $doc_root . $base_path_url; 
?>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-10 offset-md-1">
                    
                    <div class="card card-danger"> 
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-flag mr-1"></i> Detail Wasit:
                                <?php echo htmlspecialchars($wasit_detail['nama_lengkap']); ?> (NIK:
                                <?php echo htmlspecialchars($wasit_detail['nik']); ?>)
                            </h3>
                            <div class="card-tools">
                                <a href="daftar_wasit.php<?php echo $wasit_detail['cabor_id_wasit'] ? '?id_cabor=' . $wasit_detail['cabor_id_wasit'] : ''; ?>"
                                    class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a> <?php
                                $can_edit_current_wasit = false;
                                if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) { $can_edit_current_wasit = true; } 
                                elseif ($user_role_utama == 'pengurus_cabor' && ($id_cabor_pengurus_utama ?? null) == $wasit_detail['cabor_id_wasit']) { $can_edit_current_wasit = true; }
                                
                                if ($can_edit_current_wasit): ?>
                                    <a href="edit_wasit.php?id_wasit=<?php echo $wasit_detail['id_wasit']; ?>"
                                        class="btn btn-sm btn-warning ml-2"><i class="fas fa-edit mr-1"></i> Edit Wasit Ini</a> 
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="mt-1 mb-3 text-danger"><i class="fas fa-user-tie mr-1"></i> Informasi Umum & Pribadi Wasit</h5>
                            <div class="row mb-3">
                                <div class="col-md-3 text-center align-self-start">
                                    <?php
                                    $foto_wasit_url = $base_path_url . '/assets/adminlte/dist/img/avatar.png'; 
                                    $pesan_foto_w = "Foto wasit belum diupload.";

                                    if (!empty($wasit_detail['foto_wasit'])) {
                                        $path_fisik_foto_w = $base_path_fs . '/' . ltrim($wasit_detail['foto_wasit'], '/');
                                        if (file_exists(preg_replace('/\/+/', '/', $path_fisik_foto_w))) {
                                            $foto_wasit_url = $base_path_url . '/' . ltrim($wasit_detail['foto_wasit'], '/');
                                            $pesan_foto_w = ""; 
                                        } else {
                                            $pesan_foto_w = "File foto wasit tidak ditemukan di server.";
                                            if (!empty($wasit_detail['foto_pengguna'])) {
                                                $path_fisik_foto_pg_w = $base_path_fs . '/' . ltrim($wasit_detail['foto_pengguna'], '/');
                                                if (file_exists(preg_replace('/\/+/', '/', $path_fisik_foto_pg_w))) {
                                                    $foto_wasit_url = $base_path_url . '/' . ltrim($wasit_detail['foto_pengguna'], '/');
                                                    $pesan_foto_w = "Foto wasit tidak ditemukan, foto profil pengguna ditampilkan.";
                                                }
                                            }
                                        }
                                    } elseif (!empty($wasit_detail['foto_pengguna'])) {
                                        $path_fisik_foto_pg_w = $base_path_fs . '/' . ltrim($wasit_detail['foto_pengguna'], '/');
                                        if (file_exists(preg_replace('/\/+/', '/', $path_fisik_foto_pg_w))) {
                                            $foto_wasit_url = $base_path_url . '/' . ltrim($wasit_detail['foto_pengguna'], '/');
                                            $pesan_foto_w = "Foto wasit belum ada, foto profil pengguna ditampilkan.";
                                        }
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($foto_wasit_url); ?>"
                                        alt="Foto <?php echo htmlspecialchars($wasit_detail['nama_lengkap']); ?>"
                                        class="img-fluid img-thumbnail"
                                        style="max-height: 180px; max-width:150px; object-fit: cover;">
                                    <?php if (!empty($pesan_foto_w)): ?>
                                        <p class="text-<?php echo (strpos($pesan_foto_w, 'ditemukan') !== false) ? 'danger' : ((strpos($pesan_foto_w, 'pengguna ditampilkan') !== false) ? 'warning' : 'muted'); ?> text-sm mt-1"><?php echo htmlspecialchars($pesan_foto_w); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9">
                                    <h4><?php echo htmlspecialchars($wasit_detail['nama_lengkap']); ?></h4>
                                    <p class="text-muted mb-1">NIK: <strong><?php echo htmlspecialchars($wasit_detail['nik']); ?></strong></p>
                                    <p class="text-muted mb-1">Cabang Olahraga: <strong><?php echo htmlspecialchars($wasit_detail['nama_cabor']); ?></strong></p>
                                    <p class="text-muted mb-1">Nomor Lisensi: <strong><?php echo htmlspecialchars($wasit_detail['nomor_lisensi'] ?? '<em>Belum ada</em>'); ?></strong></p>
                                    <hr class="my-2 d-md-none">
                                    <p class="mb-1 mt-md-2"><i class="fas fa-birthday-cake mr-2 text-muted"></i> <?php echo $wasit_detail['tanggal_lahir'] ? date('d F Y', strtotime($wasit_detail['tanggal_lahir'])) : '<em>Tidak ada data</em>'; ?></p>
                                    <p class="mb-1"><i class="fas fa-venus-mars mr-2 text-muted"></i> <?php echo htmlspecialchars($wasit_detail['jenis_kelamin'] ?? '<em>Tidak ada data</em>'); ?></p>
                                    <p class="mb-1"><i class="fas fa-phone mr-2 text-muted"></i> <?php echo htmlspecialchars($wasit_detail['kontak_wasit'] ?? ($wasit_detail['nomor_telepon'] ?? '<em>Tidak ada data</em>')); ?></p>
                                    <p class="mb-1"><i class="fas fa-envelope mr-2 text-muted"></i> <?php echo htmlspecialchars($wasit_detail['email'] ?? '<em>Tidak ada data</em>'); ?></p>
                                    <p class="mb-0"><i class="fas fa-map-marker-alt mr-2 text-muted"></i> <?php echo nl2br(htmlspecialchars($wasit_detail['alamat'] ?? '<em>Tidak ada data</em>')); ?></p>
                                </div>
                            </div>
                            
                            <hr> 
                            <h5 class="mt-3 mb-3 text-danger"><i class="fas fa-folder-open mr-1"></i> Berkas Pendukung</h5>
                            <dl class="row">
                                <dt class="col-sm-4">Scan KTP:</dt>
                                <dd class="col-sm-8">
                                    <?php if (!empty($wasit_detail['ktp_path'])): 
                                        $path_fisik_ktp_w = $base_path_fs . '/' . ltrim($wasit_detail['ktp_path'], '/');
                                        if (file_exists(preg_replace('/\/+/', '/', $path_fisik_ktp_w))): ?>
                                            <a href="<?php echo htmlspecialchars($base_path_url . '/' . ltrim($wasit_detail['ktp_path'], '/')); ?>" target="_blank" class="btn btn-xs btn-outline-info"><i class="fas fa-id-card mr-1"></i> Lihat KTP</a>
                                        <?php else: ?>
                                            <span class="text-danger"><em>File KTP (<?php echo htmlspecialchars(basename($wasit_detail['ktp_path'])); ?>) tidak ditemukan.</em></span>
                                        <?php endif; ?>
                                    <?php else: ?> 
                                        <span class="text-muted">KTP belum diupload</span>
                                    <?php endif; ?>
                                </dd>
                                <dt class="col-sm-4">Scan Kartu Keluarga:</dt>
                                <dd class="col-sm-8">
                                    <?php if (!empty($wasit_detail['kk_path'])): 
                                        $path_fisik_kk_w = $base_path_fs . '/' . ltrim($wasit_detail['kk_path'], '/');
                                        if (file_exists(preg_replace('/\/+/', '/', $path_fisik_kk_w))): ?>
                                            <a href="<?php echo htmlspecialchars($base_path_url . '/' . ltrim($wasit_detail['kk_path'], '/')); ?>" target="_blank" class="btn btn-xs btn-outline-info"><i class="fas fa-users mr-1"></i> Lihat KK</a>
                                        <?php else: ?>
                                            <span class="text-danger"><em>File KK (<?php echo htmlspecialchars(basename($wasit_detail['kk_path'])); ?>) tidak ditemukan.</em></span>
                                        <?php endif; ?>
                                    <?php else: ?> 
                                        <span class="text-muted">KK belum diupload</span>
                                    <?php endif; ?>
                                </dd>
                                <dt class="col-sm-4">File Lisensi:</dt>
                                <dd class="col-sm-8">
                                    <?php if (!empty($wasit_detail['path_file_lisensi'])): 
                                        $path_fisik_lisensi_w = $base_path_fs . '/' . ltrim($wasit_detail['path_file_lisensi'], '/');
                                        if (file_exists(preg_replace('/\/+/', '/', $path_fisik_lisensi_w))): ?>
                                            <a href="<?php echo htmlspecialchars($base_path_url . '/' . ltrim($wasit_detail['path_file_lisensi'], '/')); ?>" target="_blank" class="btn btn-xs btn-outline-info"><i class="fas fa-file-alt mr-1"></i> Lihat File Lisensi</a>
                                        <?php else: ?>
                                            <span class="text-danger"><em>File lisensi (<?php echo htmlspecialchars(basename($wasit_detail['path_file_lisensi'])); ?>) tidak ditemukan.</em></span>
                                        <?php endif; ?>
                                    <?php else: ?> 
                                        <span class="text-muted">File lisensi belum diupload</span>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                            
                            <hr>
                            <h5 class="mt-3 mb-3 text-danger"><i class="fas fa-clipboard-check mr-1"></i> Status & Histori Approval</h5>
                            <dl class="row">
                                <dt class="col-sm-4">Status Approval:</dt>
                                <dd class="col-sm-8"><span class="badge badge-<?php 
                                    $status_badge_w = 'secondary';
                                    if ($wasit_detail['status_approval'] == 'disetujui') $status_badge_w = 'success';
                                    elseif ($wasit_detail['status_approval'] == 'ditolak') $status_badge_w = 'danger';
                                    elseif (in_array($wasit_detail['status_approval'], ['pending', 'revisi'])) $status_badge_w = 'warning';
                                    echo $status_badge_w; 
                                ?> p-1"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $wasit_detail['status_approval'] ?? 'N/A'))); ?></span></dd>
                                
                                <?php if ($wasit_detail['approved_by_nik']): ?>
                                    <dt class="col-sm-4">Diproses oleh:</dt>
                                    <dd class="col-sm-8">
                                        <?php echo htmlspecialchars($wasit_detail['nama_approver'] ?? ('NIK: ' . $wasit_detail['approved_by_nik'])); ?> 
                                        <?php if ($wasit_detail['approval_at']): ?>
                                            <small class="text-muted">(pada <?php echo date('d M Y, H:i', strtotime($wasit_detail['approval_at'])); ?>)</small>
                                        <?php endif; ?>
                                    </dd>
                                <?php endif; ?>

                                <?php if (($wasit_detail['status_approval'] == 'ditolak' || $wasit_detail['status_approval'] == 'revisi') && !empty($wasit_detail['alasan_penolakan'])): ?>
                                    <dt class="col-sm-4">Alasan/Catatan <?php echo ($wasit_detail['status_approval'] == 'ditolak' ? 'Penolakan' : 'Revisi'); ?>:</dt>
                                    <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($wasit_detail['alasan_penolakan'])); ?></dd>
                                <?php endif; ?>
                                
                                <?php 
                                // Asumsikan ada kolom `created_at` di tabel `wasit` (jika tidak, logika ini mungkin perlu disesuaikan)
                                $waktu_proses_ref_w = $wasit_detail['approval_at'] ?? ($wasit_detail['created_at'] ?? null); 
                                if (isset($wasit_detail['updated_by_nik']) && $wasit_detail['updated_by_nik'] !== null && isset($wasit_detail['last_updated_process_at']) && $wasit_detail['last_updated_process_at'] !== null) {
                                    $tampilkan_update_w = true;
                                    if ($waktu_proses_ref_w !== null && strtotime($wasit_detail['last_updated_process_at']) <= strtotime($waktu_proses_ref_w)) {
                                        $tampilkan_update_w = false;
                                    }
                                    if ($tampilkan_update_w):
                                ?>
                                    <dt class="col-sm-4">Update Terakhir oleh:</dt>
                                    <dd class="col-sm-8">
                                        <?php echo htmlspecialchars($wasit_detail['nama_editor_terakhir'] ?? ('NIK: ' . $wasit_detail['updated_by_nik'])); ?> 
                                        <small class="text-muted">(pada <?php echo date('d M Y, H:i', strtotime($wasit_detail['last_updated_process_at'])); ?>)</small>
                                    </dd>
                                <?php 
                                    endif;
                                } 
                                ?>
                            </dl>
                        </div>
                        <div class="card-footer text-center">
                            <a href="daftar_wasit.php<?php echo $wasit_detail['cabor_id_wasit'] ? '?id_cabor=' . $wasit_detail['cabor_id_wasit'] : ''; ?>"
                                class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Wasit</a> 
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php
require_once(__DIR__ . '/../../core/footer.php');
?>