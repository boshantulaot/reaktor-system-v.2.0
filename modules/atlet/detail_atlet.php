<?php
// File: reaktorsystem/modules/atlet/detail_atlet.php
$page_title = "Detail Data Atlet";
$current_page_is_detail_atlet = true;

$additional_css = [
    // 'assets/adminlte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css', 
    // 'assets/adminlte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css',
];
$additional_js = [
    // 'assets/adminlte/plugins/datatables/jquery.dataTables.min.js',
    // 'assets/adminlte/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js',
    // 'assets/adminlte/plugins/datatables-responsive/js/dataTables.responsive.min.js',
    // 'assets/adminlte/plugins/datatables-responsive/js/responsive.bootstrap4.min.js',
];

require_once(__DIR__ . '/../../core/header.php'); 

// Pengecekan sesi & konfigurasi inti
if (!isset($pdo) || !$pdo instanceof PDO || !isset($user_nik) || !isset($user_role_utama) || !isset($app_base_path) || !isset($default_avatar_path_relative) ) {
    echo "<!DOCTYPE html><html><head><title>Error Konfigurasi Inti</title>";
    if (isset($app_base_path)) { echo "<link rel='stylesheet' href='" . htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/dist/css/adminlte.min.css') . "'>"; }
    echo "</head><body class='hold-transition sidebar-mini'><div class='wrapper'><section class='content'><div class='container-fluid'>";
    echo "<div class='alert alert-danger text-center mt-5 p-3'><strong>Error Kritis Sistem:</strong> Konfigurasi inti bermasalah. Harap hubungi administrator.</div>";
    echo "</div></section></div></body></html>";
    if (file_exists(__DIR__ . '/../../core/footer.php')) { $inline_script = $inline_script ?? ''; require_once(__DIR__ . '/../../core/footer.php'); }
    exit();
}

$id_atlet_to_view = null; 
$atlet_data_detail = null; 
$prestasi_atlet_list_detail = [];
$path_foto_default_display = $default_avatar_path_relative ?? 'assets/adminlte/dist/img/kepitran.jpg';

if (isset($_GET['id_atlet']) && filter_var($_GET['id_atlet'], FILTER_VALIDATE_INT) && (int)$_GET['id_atlet'] > 0) {
    $id_atlet_to_view = (int)$_GET['id_atlet'];
    try {
        $sql_detail = "SELECT 
                        a.id_atlet, a.nik, a.id_cabor, a.id_klub, 
                        a.status_pendaftaran, 
                        a.approved_by_nik_pengcab, a.approval_at_pengcab, a.alasan_penolakan_pengcab,
                        a.approved_by_nik_admin, a.approval_at_admin, a.alasan_penolakan_admin,
                        a.ktp_path, a.kk_path, a.pas_foto_path,
                        a.created_at AS atlet_created_at, a.updated_at AS atlet_updated_at, 
                        a.created_by_nik AS atlet_created_by_nik, a.updated_by_nik AS atlet_updated_by_nik,
                        p.nama_lengkap, p.tanggal_lahir, p.jenis_kelamin, p.alamat, p.nomor_telepon, p.email, 
                        p.foto AS foto_profil_pengguna, 
                        co.nama_cabor, 
                        kl.nama_klub,
                        pengcab_approver.nama_lengkap AS nama_pengcab_approver,
                        admin_approver.nama_lengkap AS nama_admin_approver,
                        creator_atlet.nama_lengkap AS nama_creator_atlet, 
                        updater.nama_lengkap AS nama_updater_atlet
                      FROM atlet a
                      JOIN pengguna p ON a.nik = p.nik
                      JOIN cabang_olahraga co ON a.id_cabor = co.id_cabor
                      LEFT JOIN klub kl ON a.id_klub = kl.id_klub
                      LEFT JOIN pengguna pengcab_approver ON a.approved_by_nik_pengcab = pengcab_approver.nik
                      LEFT JOIN pengguna admin_approver ON a.approved_by_nik_admin = admin_approver.nik
                      LEFT JOIN pengguna creator_atlet ON a.created_by_nik = creator_atlet.nik
                      LEFT JOIN pengguna updater ON a.updated_by_nik = updater.nik
                      WHERE a.id_atlet = :id_atlet";
        $stmt_detail = $pdo->prepare($sql_detail); 
        $stmt_detail->bindParam(':id_atlet', $id_atlet_to_view, PDO::PARAM_INT); 
        $stmt_detail->execute(); 
        $atlet_data_detail = $stmt_detail->fetch(PDO::FETCH_ASSOC);
        
        if (!$atlet_data_detail) { 
            $_SESSION['pesan_error_global'] = "Data Atlet dengan ID " . htmlspecialchars($id_atlet_to_view) . " tidak ditemukan."; 
            header("Location: daftar_atlet.php"); 
            exit(); 
        }

        // PERBAIKAN QUERY PRESTASI
        $stmt_prestasi_detail = $pdo->prepare("
            SELECT pr.*, c_pres.nama_cabor AS nama_cabor_prestasi
            FROM prestasi pr
            JOIN cabang_olahraga c_pres ON pr.id_cabor = c_pres.id_cabor
            WHERE pr.nik = :nik_atlet AND pr.id_cabor = :id_cabor_profil_atlet 
            ORDER BY pr.tahun_perolehan DESC, pr.nama_kejuaraan ASC
        ");
        $stmt_prestasi_detail->bindParam(':nik_atlet', $atlet_data_detail['nik'], PDO::PARAM_STR); 
        $stmt_prestasi_detail->bindParam(':id_cabor_profil_atlet', $atlet_data_detail['id_cabor'], PDO::PARAM_INT);
        $stmt_prestasi_detail->execute();
        $prestasi_atlet_list_detail = $stmt_prestasi_detail->fetchAll(PDO::FETCH_ASSOC);
        // AKHIR PERBAIKAN QUERY PRESTASI

    } catch (PDOException $e) { 
        error_log("Detail Atlet Error (ID: " . $id_atlet_to_view . "): " . $e->getMessage());
        $_SESSION['pesan_error_global'] = "Error mengambil data detail atlet."; 
        header("Location: daftar_atlet.php"); 
        exit(); 
    }
} else { 
    $_SESSION['pesan_error_global'] = "ID Atlet tidak valid atau tidak disediakan."; 
    header("Location: daftar_atlet.php"); 
    exit(); 
}
if (!$atlet_data_detail) { 
    $_SESSION['pesan_error_global'] = "Gagal memuat data atlet."; 
    header("Location: daftar_atlet.php"); 
    exit(); 
}

$doc_root_detail_atlet = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
$base_path_url_detail_atlet = rtrim($app_base_path, '/');
$base_path_fs_detail_atlet = $doc_root_detail_atlet . $base_path_url_detail_atlet;
?>

    <section class="content">
        <div class="container-fluid">
            <?php 
            if (isset($_SESSION['pesan_sukses_global'])) { echo '<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button><h5><i class="icon fas fa-check"></i> Sukses!</h5>' . htmlspecialchars($_SESSION['pesan_sukses_global']) . '</div>'; unset($_SESSION['pesan_sukses_global']);}
            if (isset($_SESSION['pesan_error_global'])) { echo '<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button><h5><i class="icon fas fa-ban"></i> Gagal!</h5>' . htmlspecialchars($_SESSION['pesan_error_global']) . '</div>'; unset($_SESSION['pesan_error_global']);}
            ?>
            <div class="row">
                <div class="col-md-9 offset-md-1"> 
                    <div class="card card-purple shadow"> 
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-id-badge mr-1"></i> <?php echo htmlspecialchars($page_title); ?>: <strong><?php echo htmlspecialchars($atlet_data_detail['nama_lengkap']); ?></strong></h3>
                            <div class="card-tools">
                                <a href="daftar_atlet.php<?php echo $atlet_data_detail['id_cabor'] ? '?id_cabor=' . $atlet_data_detail['id_cabor'] : ''; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a> 
                                <?php $can_edit_this_atlet_detail = false; if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) { $can_edit_this_atlet_detail = true; } elseif ($user_role_utama == 'pengurus_cabor' && ($id_cabor_pengurus_utama ?? null) == $atlet_data_detail['id_cabor']) { $can_edit_this_atlet_detail = true; } if ($can_edit_this_atlet_detail): ?><a href="edit_atlet.php?id_atlet=<?php echo $atlet_data_detail['id_atlet']; ?>" class="btn btn-sm btn-warning ml-2"><i class="fas fa-edit mr-1"></i> Edit Profil</a><?php endif; ?>
                                <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor']) || $user_nik == $atlet_data_detail['nik']): ?><a href="../prestasi/tambah_prestasi.php?id_atlet=<?php echo $atlet_data_detail['id_atlet']; ?>&nik_atlet=<?php echo $atlet_data_detail['nik']; ?>&id_cabor_atlet=<?php echo $atlet_data_detail['id_cabor']; ?>" class="btn btn-sm btn-success ml-2"><i class="fas fa-trophy mr-1"></i> Tambah Prestasi</a><?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="mt-1 mb-3 text-purple"><i class="fas fa-user-circle mr-1"></i> Informasi Dasar Atlet</h5>
                            <div class="row mb-3">
                                <div class="col-md-3 text-center align-self-start">
                                    <?php
                                    // Logika Foto Atlet (Sama seperti kode Anda yang sudah baik)
                                    $foto_url_tampil_detail = APP_URL_BASE . '/' . ltrim($path_foto_default_display, '/');
                                    $pesan_foto_detail = "Pas foto atlet belum diupload.";
                                    if (!empty($atlet_data_detail['pas_foto_path'])) { $path_fisik_pas_foto_detail = APP_PATH_BASE . '/' . ltrim($atlet_data_detail['pas_foto_path'], '/'); if (file_exists(preg_replace('/\/+/', '/', $path_fisik_pas_foto_detail)) && is_file(preg_replace('/\/+/', '/', $path_fisik_pas_foto_detail))) { $foto_url_tampil_detail = APP_URL_BASE . '/' . ltrim($atlet_data_detail['pas_foto_path'], '/'); $pesan_foto_detail = ""; } else { $pesan_foto_detail = "File pas foto atlet (".basename(htmlspecialchars($atlet_data_detail['pas_foto_path'])).") tidak ditemukan."; } }
                                    elseif (!empty($atlet_data_detail['foto_profil_pengguna'])) { $path_fisik_foto_pgn_detail = APP_PATH_BASE . '/' . ltrim($atlet_data_detail['foto_profil_pengguna'], '/'); if (file_exists(preg_replace('/\/+/', '/', $path_fisik_foto_pgn_detail)) && is_file(preg_replace('/\/+/', '/', $path_fisik_foto_pgn_detail))) { $foto_url_tampil_detail = APP_URL_BASE . '/' . ltrim($atlet_data_detail['foto_profil_pengguna'], '/'); $pesan_foto_detail = "Pas foto atlet belum ada, foto profil pengguna ditampilkan."; } else { $pesan_foto_detail = "Pas foto atlet & foto profil pengguna (".basename(htmlspecialchars($atlet_data_detail['foto_profil_pengguna'])).") tidak ditemukan."; } }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($foto_url_tampil_detail); ?>"
                                         alt="Foto <?php echo htmlspecialchars($atlet_data_detail['nama_lengkap']); ?>"
                                         class="img-fluid img-circle elevation-2 mb-2"
                                         style="width: 150px; height: 150px; display: block; margin-left: auto; margin-right: auto; object-fit: cover; border: 5px solid #dee2e6; padding: 5px;"
                                         onerror="this.onerror=null; this.src='<?php echo htmlspecialchars(APP_URL_BASE . '/' . ltrim($path_foto_default_display, '/')); ?>';">
                                    <?php if (!empty($pesan_foto_detail)): ?>
                                        <p class="text-muted text-sm mt-1"><?php echo htmlspecialchars($pesan_foto_detail); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9">
                                    <h4 class="mb-3"><?php echo htmlspecialchars($atlet_data_detail['nama_lengkap']); ?></h4>
                                    <p class="mb-1"><i class="fas fa-id-card fa-fw mr-2 text-muted"></i>NIK: <strong><?php echo htmlspecialchars($atlet_data_detail['nik']); ?></strong></p>
                                    <p class="mb-1"><i class="fas fa-flag fa-fw mr-2 text-muted"></i>Cabang Olahraga: <strong><?php echo htmlspecialchars($atlet_data_detail['nama_cabor']); ?></strong></p>
                                    <p class="mb-1"><i class="fas fa-shield-alt fa-fw mr-2 text-muted"></i>Klub Afiliasi: <?php echo htmlspecialchars($atlet_data_detail['nama_klub'] ?? '<em>Tidak Ada</em>'); ?></p>
                                    <p class="mb-1"><i class="fas fa-birthday-cake fa-fw mr-2 text-muted"></i>Tanggal Lahir: <?php echo $atlet_data_detail['tanggal_lahir'] ? date('d F Y', strtotime($atlet_data_detail['tanggal_lahir'])) : '<em>Tidak Ada</em>'; ?></p>
                                    <p class="mb-1"><i class="fas fa-venus-mars fa-fw mr-2 text-muted"></i>Jenis Kelamin: <?php echo htmlspecialchars($atlet_data_detail['jenis_kelamin'] ?? '<em>Tidak Ada</em>'); ?></p>
                                    <p class="mb-1"><i class="fas fa-phone fa-fw mr-2 text-muted"></i>No. Telepon: <?php echo htmlspecialchars($atlet_data_detail['nomor_telepon'] ?? '<em>Tidak Ada</em>'); ?></p>
                                    <p class="mb-1"><i class="fas fa-envelope fa-fw mr-2 text-muted"></i>Email: <?php echo htmlspecialchars($atlet_data_detail['email'] ?? '<em>Tidak Ada</em>'); ?></p>
                                    <p class="mb-0"><i class="fas fa-map-marker-alt fa-fw mr-2 text-muted"></i>Alamat: <?php echo !empty(trim($atlet_data_detail['alamat'] ?? '')) ? nl2br(htmlspecialchars($atlet_data_detail['alamat'])) : '<em>Tidak Ada</em>'; ?></p>
                                </div>
                            </div>
                            
                            <hr>
                            <h5 class="mt-3 mb-3 text-purple"><i class="fas fa-folder-open mr-1"></i> Berkas Pendukung</h5>
                            <dl class="row">
                                <dt class="col-sm-4">Scan KTP:</dt>
                                <dd class="col-sm-8">
                                    <?php if (!empty($atlet_data_detail['ktp_path']) && file_exists($base_path_fs_detail_atlet . '/' . ltrim($atlet_data_detail['ktp_path'], '/'))): ?>
                                        <a href="<?php echo $base_path_url_detail_atlet . '/' . ltrim($atlet_data_detail['ktp_path'], '/'); ?>" target="_blank" class="btn btn-xs btn-outline-info"><i class="fas fa-id-card mr-1"></i> Lihat KTP</a>
                                    <?php elseif (!empty($atlet_data_detail['ktp_path'])): ?>
                                        <span class="text-danger font-italic"><small>File KTP (<?php echo basename(htmlspecialchars($atlet_data_detail['ktp_path']));?>) tidak ditemukan.</small></span>
                                    <?php else: ?> 
                                        <span class="text-muted"><em>KTP belum diupload.</em></span>
                                    <?php endif; ?>
                                </dd>
                                <dt class="col-sm-4">Scan Kartu Keluarga:</dt>
                                <dd class="col-sm-8">
                                    <?php if (!empty($atlet_data_detail['kk_path']) && file_exists($base_path_fs_detail_atlet . '/' . ltrim($atlet_data_detail['kk_path'], '/'))): ?>
                                        <a href="<?php echo $base_path_url_detail_atlet . '/' . ltrim($atlet_data_detail['kk_path'], '/'); ?>" target="_blank" class="btn btn-xs btn-outline-info"><i class="fas fa-users mr-1"></i> Lihat KK</a>
                                    <?php elseif (!empty($atlet_data_detail['kk_path'])): ?>
                                        <span class="text-danger font-italic"><small>File KK (<?php echo basename(htmlspecialchars($atlet_data_detail['kk_path']));?>) tidak ditemukan.</small></span>
                                    <?php else: ?> 
                                        <span class="text-muted"><em>KK belum diupload.</em></span>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                            
                            <hr>
                            <h5 class="mt-3 mb-3 text-purple"><i class="fas fa-clipboard-check mr-1"></i> Status & Histori Pendaftaran</h5>
                            <dl class="row">
                                <dt class="col-sm-4">Status Pendaftaran:</dt>
                                <dd class="col-sm-8">
                                    <?php $status_text_detail = ucfirst(str_replace('_', ' ', $atlet_data_detail['status_pendaftaran'] ?? 'N/A')); $status_badge_detail = 'secondary'; if ($atlet_data_detail['status_pendaftaran'] == 'disetujui') { $status_badge_detail = 'success'; } elseif (in_array($atlet_data_detail['status_pendaftaran'], ['pending', 'verifikasi_pengcab', 'revisi'])) { $status_badge_detail = 'warning'; } elseif (in_array($atlet_data_detail['status_pendaftaran'], ['ditolak_pengcab', 'ditolak_admin'])) { $status_badge_detail = 'danger'; } ?>
                                    <span class="badge badge-<?php echo $status_badge_detail; ?> p-1"><?php echo htmlspecialchars($status_text_detail); ?></span>
                                </dd>
                                <dt class="col-sm-4">Didaftarkan pada:</dt>
                                <dd class="col-sm-8"><?php echo date('d F Y, H:i', strtotime($atlet_data_detail['atlet_created_at'])); ?> 
                                    <?php if($atlet_data_detail['nama_creator_atlet']) echo "oleh " . htmlspecialchars($atlet_data_detail['nama_creator_atlet']); elseif($atlet_data_detail['atlet_created_by_nik']) echo "oleh NIK: ".htmlspecialchars($atlet_data_detail['atlet_created_by_nik']); else echo "oleh Sistem";?>
                                </dd>
                                <?php if ($atlet_data_detail['approved_by_nik_pengcab']): ?>
                                    <dt class="col-sm-4">Diproses Pengcab oleh:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($atlet_data_detail['nama_pengcab_approver'] ?? ('NIK: ' . $atlet_data_detail['approved_by_nik_pengcab'])); ?> <?php if ($atlet_data_detail['approval_at_pengcab']): ?><small class="text-muted">(pada <?php echo date('d M Y, H:i', strtotime($atlet_data_detail['approval_at_pengcab'])); ?>)</small><?php endif; ?></dd>
                                <?php endif; ?>
                                <?php if ($atlet_data_detail['status_pendaftaran'] == 'ditolak_pengcab' && !empty($atlet_data_detail['alasan_penolakan_pengcab'])): ?>
                                    <dt class="col-sm-4">Alasan Penolakan Pengcab:</dt><dd class="col-sm-8"><span class="text-danger"><?php echo nl2br(htmlspecialchars($atlet_data_detail['alasan_penolakan_pengcab'])); ?></span></dd>
                                <?php endif; ?>
                                <?php if ($atlet_data_detail['approved_by_nik_admin']): ?>
                                    <dt class="col-sm-4">Diproses Admin KONI oleh:</dt><dd class="col-sm-8"><?php echo htmlspecialchars($atlet_data_detail['nama_admin_approver'] ?? ('NIK: ' . $atlet_data_detail['approved_by_nik_admin'])); ?> <?php if ($atlet_data_detail['approval_at_admin']): ?><small class="text-muted">(pada <?php echo date('d M Y, H:i', strtotime($atlet_data_detail['approval_at_admin'])); ?>)</small><?php endif; ?></dd>
                                <?php endif; ?>
                                <?php if (($atlet_data_detail['status_pendaftaran'] == 'ditolak_admin' || $atlet_data_detail['status_pendaftaran'] == 'revisi') && !empty($atlet_data_detail['alasan_penolakan_admin'])): ?>
                                    <dt class="col-sm-4">Catatan/Alasan Admin KONI:</dt><dd class="col-sm-8"><span class="text-danger"><?php echo nl2br(htmlspecialchars($atlet_data_detail['alasan_penolakan_admin'])); ?></span></dd>
                                <?php endif; ?>
                                <?php if ($atlet_data_detail['updated_by_nik'] && $atlet_data_detail['atlet_updated_at'] && (strtotime($atlet_data_detail['atlet_updated_at']) > strtotime($atlet_data_detail['atlet_created_at'])) && $atlet_data_detail['updated_by_nik'] != ($atlet_data_detail['approved_by_nik_admin'] ?? '') && $atlet_data_detail['updated_by_nik'] != ($atlet_data_detail['approved_by_nik_pengcab'] ?? '') ): ?>
                                    <dt class="col-sm-4">Update Terakhir Profil oleh:</dt><dd class="col-sm-8"><?php echo htmlspecialchars($atlet_data_detail['nama_updater_atlet'] ?? ('NIK: ' . $atlet_data_detail['updated_by_nik'])); ?> <small class="text-muted">(pada <?php echo date('d M Y, H:i', strtotime($atlet_data_detail['atlet_updated_at'])); ?>)</small></dd>
                                <?php endif; ?>
                            </dl>

                            <hr>
                            <h5 class="mt-3 mb-3 text-purple"><i class="fas fa-trophy mr-1"></i> Daftar Prestasi (Cabor Profil: <?php echo htmlspecialchars($atlet_data_detail['nama_cabor']);?>)</h5>
                            <?php if (!empty($prestasi_atlet_list_detail)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-hover" id="tabelPrestasiAtletDetail">
                                        <thead>
                                            <tr class="text-center">
                                                <th style="width:10px;">No.</th>
                                                <th>Nama Kejuaraan</th><th>Tahun</th><th>Tingkat</th>
                                                <th>Disiplin/Nomor Lomba</th><th>Medali/Peringkat</th>
                                                <th>Cabor Saat Prestasi</th><th>Status Persetujuan</th>
                                                <?php $show_aksi_prestasi_detail_ftr = in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor']) || $user_nik == $atlet_data_detail['nik']; if ($show_aksi_prestasi_detail_ftr): ?><th class="no-export text-center" style="width:80px;">Aksi</th><?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $no_prestasi_detail_loop = 1; foreach ($prestasi_atlet_list_detail as $pres_item_loop): ?>
                                            <tr>
                                                <td class="text-center"><?php echo $no_prestasi_detail_loop++; ?></td>
                                                <td><?php echo htmlspecialchars($pres_item_loop['nama_kejuaraan']); ?></td>
                                                <td class="text-center"><?php echo htmlspecialchars($pres_item_loop['tahun_perolehan']); ?></td>
                                                <td class="text-center"><?php echo htmlspecialchars(ucfirst($pres_item_loop['tingkat_kejuaraan'])); ?></td>
                                                <td><?php echo htmlspecialchars($pres_item_loop['nomor_lomba_atau_disiplin'] ?? '-'); ?></td>
                                                <td class="text-center"><?php echo htmlspecialchars($pres_item_loop['medali_peringkat'] ?? '-'); ?></td>
                                                <td class="text-center"><?php echo htmlspecialchars($pres_item_loop['nama_cabor_prestasi']); ?></td>
                                                <td class="text-center">
                                                    <?php $s_pres_badge_detail = 'secondary'; $s_pres_text_detail = ucfirst(str_replace('_',' ',$pres_item_loop['status_approval'] ?? 'N/A')); if ($pres_item_loop['status_approval'] == 'disetujui_admin') $s_pres_badge_detail = 'success'; elseif (in_array($pres_item_loop['status_approval'], ['pending', 'disetujui_pengcab', 'revisi'])) $s_pres_badge_detail = 'warning'; elseif (in_array($pres_item_loop['status_approval'], ['ditolak_pengcab', 'ditolak_admin'])) $s_pres_badge_detail = 'danger'; ?>
                                                    <span class="badge badge-<?php echo $s_pres_badge_detail; ?> p-1"><?php echo htmlspecialchars($s_pres_text_detail); ?></span>
                                                </td>
                                                <?php if ($show_aksi_prestasi_detail_ftr): ?>
                                                <td class="text-center">
                                                    <a href="../prestasi/edit_prestasi.php?id_prestasi=<?php echo $pres_item_loop['id_prestasi']; ?>&id_atlet=<?php echo $id_atlet_to_view; ?>" class="btn btn-xs btn-warning" title="Edit Prestasi Ini"><i class="fas fa-edit"></i></a>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="callout callout-info">
                                    <p class="mb-0"><i class="fas fa-info-circle mr-1"></i> Belum ada data prestasi yang dicatat untuk atlet ini pada cabang olahraga profil '<?php echo htmlspecialchars($atlet_data_detail['nama_cabor']);?>'.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer text-center">
                            <a href="daftar_atlet.php<?php echo $atlet_data_detail['id_cabor'] ? '?id_cabor=' . $atlet_data_detail['id_cabor'] : ''; ?>"
                                class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Atlet</a> 
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php
$inline_script = "$(function () { $('[data-toggle=\"tooltip\"]').tooltip(); });";
require_once(__DIR__ . '/../../core/footer.php');
?>