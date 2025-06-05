<?php
// File: reaktorsystem/modules/prestasi/detail_prestasi.php

$page_title = "Detail Data Prestasi Atlet";
$additional_css = [];
$additional_js = [];

require_once(__DIR__ . '/../../core/header.php'); 

if (!isset($pdo) || !isset($user_role_utama) || !isset($user_nik) || !isset($app_base_path)) {
    // ... (error handling konfigurasi seperti sebelumnya) ...
    exit();
}

$id_prestasi_to_view = null; 
$prestasi_detail = null; 

if (isset($_GET['id_prestasi']) && filter_var($_GET['id_prestasi'], FILTER_VALIDATE_INT) && (int)$_GET['id_prestasi'] > 0) {
    $id_prestasi_to_view = (int)$_GET['id_prestasi'];
    try {
        // Query mengambil detail prestasi + info atlet + info cabor + info approver/editor
        $sql_detail_prestasi = "SELECT pr.*, 
                               p_atlet.nama_lengkap AS nama_atlet_prestasi, p_atlet.foto AS foto_profil_atlet, 
                               p_atlet.tanggal_lahir AS tanggal_lahir_atlet, p_atlet.jenis_kelamin AS jenis_kelamin_atlet,
                               p_atlet.alamat AS alamat_atlet, p_atlet.nomor_telepon AS telepon_atlet, p_atlet.email AS email_atlet,
                               co.nama_cabor, co.id_cabor AS cabor_id_prestasi,
                               p_pengcab.nama_lengkap AS nama_approver_pengcab, 
                               p_admin.nama_lengkap AS nama_approver_admin,
                               p_editor.nama_lengkap AS nama_editor_terakhir
                        FROM prestasi pr
                        JOIN pengguna p_atlet ON pr.nik = p_atlet.nik
                        JOIN cabang_olahraga co ON pr.id_cabor = co.id_cabor
                        LEFT JOIN pengguna p_pengcab ON pr.approved_by_nik_pengcab = p_pengcab.nik 
                        LEFT JOIN pengguna p_admin ON pr.approved_by_nik_admin = p_admin.nik 
                        LEFT JOIN pengguna p_editor ON pr.updated_by_nik = p_editor.nik 
                        WHERE pr.id_prestasi = :id_prestasi";
        $stmt_detail_prestasi = $pdo->prepare($sql_detail_prestasi); 
        $stmt_detail_prestasi->bindParam(':id_prestasi', $id_prestasi_to_view, PDO::PARAM_INT); 
        $stmt_detail_prestasi->execute(); 
        $prestasi_detail = $stmt_detail_prestasi->fetch(PDO::FETCH_ASSOC);
        
        if (!$prestasi_detail) { 
            $_SESSION['pesan_error_global'] = "Data Prestasi dengan ID " . htmlspecialchars($id_prestasi_to_view) . " tidak ditemukan."; 
            header("Location: daftar_prestasi.php"); 
            exit(); 
        }
    } catch (PDOException $e) { 
        error_log("Error Detail Prestasi (ID: " . $id_prestasi_to_view . "): " . $e->getMessage());
        $_SESSION['pesan_error_global'] = "Error mengambil data detail prestasi."; 
        header("Location: daftar_prestasi.php"); 
        exit(); 
    }
} else { 
    $_SESSION['pesan_error_global'] = "ID Prestasi tidak valid atau tidak disediakan."; 
    header("Location: daftar_prestasi.php"); 
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
                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-trophy mr-1"></i> Detail Prestasi: <?php echo htmlspecialchars($prestasi_detail['nama_kejuaraan']); ?></h3>
                            <div class="card-tools">
                                <a href="daftar_prestasi.php?id_cabor=<?php echo $prestasi_detail['cabor_id_prestasi']; ?>&nik_atlet=<?php echo $prestasi_detail['nik']; ?>"
                                    class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a> <?php
                                $can_edit_this_prestasi_detail = false;
                                if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) { $can_edit_this_prestasi_detail = true; } 
                                elseif ($user_role_utama == 'pengurus_cabor' && ($id_cabor_pengurus_utama ?? null) == $prestasi_detail['id_cabor']) { $can_edit_this_prestasi_detail = true; } 
                                elseif ($user_role_utama == 'atlet' && $user_nik == $prestasi_detail['nik']) {
                                    if (in_array($prestasi_detail['status_approval'], ['pending', 'revisi', 'ditolak_pengcab', 'ditolak_admin'])) { $can_edit_this_prestasi_detail = true; }
                                }
                                if ($can_edit_this_prestasi_detail): ?>
                                    <a href="edit_prestasi.php?id_prestasi=<?php echo $prestasi_detail['id_prestasi']; ?>"
                                        class="btn btn-sm btn-warning ml-2"><i class="fas fa-edit mr-1"></i> Edit Prestasi Ini</a> 
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="mt-1 mb-3 text-success"><i class="fas fa-user-shield mr-1"></i> Informasi Atlet Peraih Prestasi</h5>
                            <div class="row mb-3">
                                <div class="col-md-3 text-center align-self-start">
                                    <?php
                                    // Logika untuk menampilkan foto atlet (dari tabel atlet atau fallback ke pengguna)
                                    $foto_atlet_url = $base_path_url . '/assets/adminlte/dist/img/avatar.png'; // Default
                                    $pesan_foto_detail_prestasi = "Foto atlet belum tersedia.";

                                    // Prioritaskan foto dari tabel atlet (jika ada kolom foto_pas_atlet di prestasi_detail dari join dengan atlet)
                                    // atau foto profil pengguna atlet
                                    $path_foto_to_check = $prestasi_detail['foto_profil_atlet'] ?? null; // Ambil dari join dengan pengguna

                                    if (!empty($path_foto_to_check)) {
                                        $full_path_foto_fisik = $base_path_fs . '/' . ltrim($path_foto_to_check, '/');
                                        if (file_exists(preg_replace('/\/+/', '/', $full_path_foto_fisik))) {
                                            $foto_atlet_url = $base_path_url . '/' . ltrim($path_foto_to_check, '/');
                                            $pesan_foto_detail_prestasi = ""; 
                                        } else {
                                            $pesan_foto_detail_prestasi = "File foto atlet tidak ditemukan.";
                                        }
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($foto_atlet_url); ?>"
                                        alt="Foto <?php echo htmlspecialchars($prestasi_detail['nama_atlet_prestasi']); ?>"
                                        class="img-fluid img-thumbnail mb-2"
                                        style="max-height: 180px; max-width:150px; object-fit: cover;">
                                    <?php if (!empty($pesan_foto_detail_prestasi)): ?>
                                        <p class="text-muted text-sm"><?php echo htmlspecialchars($pesan_foto_detail_prestasi); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9">
                                    <h4><?php echo htmlspecialchars($prestasi_detail['nama_atlet_prestasi']); ?></h4>
                                    <p class="text-muted mb-1">NIK: <strong><?php echo htmlspecialchars($prestasi_detail['nik']); ?></strong></p>
                                    <p class="text-muted mb-1">Cabang Olahraga: <strong><?php echo htmlspecialchars($prestasi_detail['nama_cabor']); ?></strong></p>
                                    <hr class="my-2 d-md-none">
                                    <p class="mb-1 mt-md-2"><i class="fas fa-birthday-cake mr-2 text-muted"></i> <?php echo $prestasi_detail['tanggal_lahir_atlet'] ? date('d F Y', strtotime($prestasi_detail['tanggal_lahir_atlet'])) : '<em>Tidak ada data</em>'; ?></p>
                                    <p class="mb-1"><i class="fas fa-venus-mars mr-2 text-muted"></i> <?php echo htmlspecialchars($prestasi_detail['jenis_kelamin_atlet'] ?? '<em>Tidak ada data</em>'); ?></p>
                                    <p class="mb-1"><i class="fas fa-phone mr-2 text-muted"></i> <?php echo htmlspecialchars($prestasi_detail['telepon_atlet'] ?? '<em>Tidak ada data</em>'); ?></p>
                                    <p class="mb-1"><i class="fas fa-envelope mr-2 text-muted"></i> <?php echo htmlspecialchars($prestasi_detail['email_atlet'] ?? '<em>Tidak ada data</em>'); ?></p>
                                    <p class="mb-0"><i class="fas fa-map-marker-alt mr-2 text-muted"></i> <?php echo nl2br(htmlspecialchars($prestasi_detail['alamat_atlet'] ?? '<em>Tidak ada data</em>')); ?></p>
                                </div>
                            </div>
                            
                            <hr>
                            <h5 class="mt-3 mb-3 text-success"><i class="fas fa-medal mr-1"></i> Rincian Prestasi</h5>
                            <dl class="row">
                                <dt class="col-sm-4">Nama Kejuaraan:</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($prestasi_detail['nama_kejuaraan']); ?></dd>
                                <dt class="col-sm-4">Tingkat Kejuaraan:</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars(ucfirst($prestasi_detail['tingkat_kejuaraan'] ?? '-')); ?></dd>
                                <dt class="col-sm-4">Tahun Perolehan:</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($prestasi_detail['tahun_perolehan'] ?? '-'); ?></dd>
                                <dt class="col-sm-4">Medali / Peringkat:</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($prestasi_detail['medali_peringkat'] ?? '-'); ?></dd>
                            </dl>

                            <hr>
                            <h5 class="mt-3 mb-3 text-success"><i class="fas fa-file-alt mr-1"></i> Bukti Pendukung</h5>
                            <dl class="row">
                                <dt class="col-sm-4">File Bukti:</dt>
                                <dd class="col-sm-8">
                                    <?php if (!empty($prestasi_detail['bukti_path'])): 
                                        $full_path_bukti_detail = $base_path_fs . '/' . ltrim($prestasi_detail['bukti_path'], '/');
                                        if (file_exists(preg_replace('/\/+/', '/', $full_path_bukti_detail))): ?>
                                            <a href="<?php echo htmlspecialchars($base_path_url . '/' . ltrim($prestasi_detail['bukti_path'], '/')); ?>" target="_blank" class="btn btn-xs btn-outline-info"><i class="fas fa-search-plus mr-1"></i> Lihat Bukti</a>
                                        <?php else: ?>
                                            <span class="text-danger"><em>File bukti (<?php echo htmlspecialchars(basename($prestasi_detail['bukti_path'])); ?>) tidak ditemukan.</em></span>
                                        <?php endif; ?>
                                    <?php else: ?> 
                                        <span class="text-muted">Tidak ada file bukti yang diupload.</span>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                            
                            <hr>
                            <h5 class="mt-3 mb-3 text-success"><i class="fas fa-clipboard-check mr-1"></i> Status & Histori Approval</h5>
                            <dl class="row">
                                <dt class="col-sm-4">Status Approval Saat Ini:</dt>
                                <dd class="col-sm-8"><span class="badge badge-<?php 
                                    $status_badge_prestasi_detail = 'secondary';
                                    if ($prestasi_detail['status_approval'] == 'disetujui_admin') $status_badge_prestasi_detail = 'success';
                                    elseif (in_array($prestasi_detail['status_approval'], ['ditolak_pengcab', 'ditolak_admin'])) $status_badge_prestasi_detail = 'danger';
                                    elseif (in_array($prestasi_detail['status_approval'], ['pending', 'disetujui_pengcab', 'revisi'])) $status_badge_prestasi_detail = 'warning';
                                    echo $status_badge_prestasi_detail; 
                                ?> p-1"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $prestasi_detail['status_approval'] ?? 'N/A'))); ?></span></dd>
                                
                                <?php if ($prestasi_detail['approved_by_nik_pengcab']): ?>
                                    <dt class="col-sm-4">Diproses Pengcab oleh:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($prestasi_detail['nama_approver_pengcab'] ?? ('NIK: ' . $prestasi_detail['approved_by_nik_pengcab'])); ?> <?php if ($prestasi_detail['approval_at_pengcab']): ?><small class="text-muted">(pada <?php echo date('d M Y, H:i', strtotime($prestasi_detail['approval_at_pengcab'])); ?>)</small><?php endif; ?></dd>
                                <?php endif; ?>
                                <?php if ($prestasi_detail['status_approval'] == 'ditolak_pengcab' && !empty($prestasi_detail['alasan_penolakan_pengcab'])): ?>
                                    <dt class="col-sm-4">Alasan Penolakan Pengcab:</dt><dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($prestasi_detail['alasan_penolakan_pengcab'])); ?></dd>
                                <?php endif; ?>
                                <?php if ($prestasi_detail['approved_by_nik_admin']): ?>
                                    <dt class="col-sm-4">Diproses Admin KONI oleh:</dt><dd class="col-sm-8"><?php echo htmlspecialchars($prestasi_detail['nama_approver_admin'] ?? ('NIK: ' . $prestasi_detail['approved_by_nik_admin'])); ?> <?php if ($prestasi_detail['approval_at_admin']): ?><small class="text-muted">(pada <?php echo date('d M Y, H:i', strtotime($prestasi_detail['approval_at_admin'])); ?>)</small><?php endif; ?></dd>
                                <?php endif; ?>
                                <?php if ($prestasi_detail['status_approval'] == 'ditolak_admin' && !empty($prestasi_detail['alasan_penolakan_admin'])): ?>
                                    <dt class="col-sm-4">Alasan Penolakan Admin KONI:</dt><dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($prestasi_detail['alasan_penolakan_admin'])); ?></dd>
                                <?php endif; ?>
                                <?php if ($prestasi_detail['status_approval'] == 'revisi' && (!empty($prestasi_detail['alasan_penolakan_admin']) || !empty($prestasi_detail['alasan_penolakan_pengcab']))): ?>
                                    <dt class="col-sm-4">Catatan untuk Revisi:</dt><dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($prestasi_detail['alasan_penolakan_admin'] ?: $prestasi_detail['alasan_penolakan_pengcab'])); ?></dd>
                                <?php endif; ?>
                                <?php 
                                $waktu_referensi_update = $prestasi_detail['approval_at_admin'] ?? ($prestasi_detail['approval_at_pengcab'] ?? ($prestasi_detail['created_at'] ?? null));
                                if (isset($prestasi_detail['updated_by_nik']) && $prestasi_detail['updated_by_nik'] !== null && isset($prestasi_detail['last_updated_process_at']) && $prestasi_detail['last_updated_process_at'] !== null) {
                                    $tampilkan_editor = true;
                                    if ($waktu_referensi_update !== null && strtotime($prestasi_detail['last_updated_process_at']) <= strtotime($waktu_referensi_update)) {
                                        if(isset($prestasi_detail['approved_by_nik_admin']) && $prestasi_detail['updated_by_nik'] == $prestasi_detail['approved_by_nik_admin'] && $prestasi_detail['last_updated_process_at'] == $prestasi_detail['approval_at_admin']) $tampilkan_editor = false;
                                        if(isset($prestasi_detail['approved_by_nik_pengcab']) && $prestasi_detail['updated_by_nik'] == $prestasi_detail['approved_by_nik_pengcab'] && $prestasi_detail['last_updated_process_at'] == $prestasi_detail['approval_at_pengcab']) $tampilkan_editor = false;
                                        if(isset($prestasi_detail['created_at']) && $prestasi_detail['updated_by_nik'] == $prestasi_detail['nik'] && $prestasi_detail['last_updated_process_at'] == $prestasi_detail['created_at']) $tampilkan_editor = false;
                                    }
                                    if ($tampilkan_editor): ?>
                                    <dt class="col-sm-4">Perubahan Terakhir oleh:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($prestasi_detail['nama_editor_terakhir'] ?? ('NIK: ' . $prestasi_detail['updated_by_nik'])); ?> <small class="text-muted">(pada <?php echo date('d M Y, H:i', strtotime($prestasi_detail['last_updated_process_at'])); ?>)</small></dd>
                                <?php endif; } ?>
                            </dl>
                        </div>
                        <div class="card-footer text-center">
                            <a href="daftar_prestasi.php?id_cabor=<?php echo $prestasi_detail['cabor_id_prestasi']; ?>&nik_atlet=<?php echo $prestasi_detail['nik']; ?>"
                                class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Prestasi</a> 
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php
require_once(__DIR__ . '/../../core/footer.php');
?>