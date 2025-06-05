<?php
// File: modules/pelatih/detail_pelatih.php (REVISI dengan Histori Approval Profil)

// ... (Bagian $page_title, $additional_css, $additional_js, require_once header, pengecekan sesi, GET id_pelatih tetap sama) ...
$page_title = "Detail Profil Pelatih";
$additional_css = [
    'assets/adminlte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css',
    'assets/adminlte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/datatables/jquery.dataTables.min.js',
    'assets/adminlte/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js',
    'assets/adminlte/plugins/datatables-responsive/js/dataTables.responsive.min.js',
];

require_once(__DIR__ . '/../../core/header.php');

if (!isset($pdo, $user_role_utama, $user_nik, $app_base_path)) { exit("Sesi tidak valid."); }

if (!isset($_GET['id_pelatih']) || !filter_var($_GET['id_pelatih'], FILTER_VALIDATE_INT) || (int)$_GET['id_pelatih'] <= 0) {
    $_SESSION['pesan_error_global'] = "ID Profil Pelatih tidak valid.";
    header("Location: daftar_pelatih.php");
    exit();
}
$id_pelatih_to_view = (int)$_GET['id_pelatih'];


// 1. Ambil Data Profil Pelatih (Query disempurnakan untuk mengambil nama creator)
$pelatih_profil = null;
try {
    $stmt_pp = $pdo->prepare("SELECT plt.*, 
                                   p.nama_lengkap, p.email, p.tanggal_lahir, p.jenis_kelamin, p.alamat, p.nomor_telepon, p.foto AS foto_pengguna_utama,
                                   approver_profil.nama_lengkap AS nama_approver_profil, 
                                   editor_profil.nama_lengkap AS nama_editor_profil,
                                   creator_profil.nama_lengkap AS nama_creator_profil /* Tambahan untuk nama creator */
                            FROM pelatih plt
                            JOIN pengguna p ON plt.nik = p.nik
                            LEFT JOIN pengguna approver_profil ON plt.approved_by_nik = approver_profil.nik
                            LEFT JOIN pengguna editor_profil ON plt.updated_by_nik = editor_profil.nik
                            LEFT JOIN pengguna creator_profil ON plt.created_by_nik = creator_profil.nik /* Tambahan join */
                            WHERE plt.id_pelatih = :id_pelatih");
    $stmt_pp->bindParam(':id_pelatih', $id_pelatih_to_view, PDO::PARAM_INT);
    $stmt_pp->execute();
    $pelatih_profil = $stmt_pp->fetch(PDO::FETCH_ASSOC);

    if (!$pelatih_profil) { /* ... (error handling tidak ditemukan) ... */ 
        $_SESSION['pesan_error_global'] = "Profil Pelatih tidak ditemukan.";
        header("Location: daftar_pelatih.php");
        exit();
    }
} catch (PDOException $e) { /* ... (error handling PDO) ... */ 
    error_log("Detail Profil Pelatih Error (Profil): " . $e->getMessage());
    $_SESSION['pesan_error_global'] = "Gagal memuat profil pelatih.";
    header("Location: daftar_pelatih.php");
    exit();
}

// 2. Ambil Daftar Lisensi Milik Pelatih Ini (Logika tetap sama seperti sebelumnya)
$daftar_lisensi_pelatih = [];
// ... (kode untuk fetch $daftar_lisensi_pelatih tetap sama) ...
try {
    $stmt_lp = $pdo->prepare("SELECT lp.*, co.nama_cabor,
                                   pengcab_app.nama_lengkap AS nama_approver_lis_pengcab,
                                   admin_app.nama_lengkap AS nama_approver_lis_admin
                             FROM lisensi_pelatih lp
                             JOIN cabang_olahraga co ON lp.id_cabor = co.id_cabor
                             LEFT JOIN pengguna pengcab_app ON lp.approved_by_nik_pengcab = pengcab_app.nik
                             LEFT JOIN pengguna admin_app ON lp.approved_by_nik_admin = admin_app.nik
                             WHERE lp.id_pelatih = :id_pelatih 
                             ORDER BY lp.tanggal_terbit DESC, lp.nama_lisensi_sertifikat ASC");
    $stmt_lp->bindParam(':id_pelatih', $id_pelatih_to_view, PDO::PARAM_INT);
    $stmt_lp->execute();
    $daftar_lisensi_pelatih_raw = $stmt_lp->fetchAll(PDO::FETCH_ASSOC);

    $today_dt_lp_detail = new DateTimeImmutable();
    foreach ($daftar_lisensi_pelatih_raw as $lisensi_item_detail) {
        $lisensi_item_detail['status_validitas_cal_detail'] = 'Tidak Ada Info';
        $lisensi_item_detail['validitas_badge_class_detail'] = 'secondary';
        if ($lisensi_item_detail['tanggal_kadaluarsa']) {
            try {
                $expiryDate_dt_lp_detail = new DateTimeImmutable($lisensi_item_detail['tanggal_kadaluarsa']);
                if ($expiryDate_dt_lp_detail < $today_dt_lp_detail) {
                    $lisensi_item_detail['status_validitas_cal_detail'] = 'Kadaluarsa'; $lisensi_item_detail['validitas_badge_class_detail'] = 'danger';
                } else {
                    $warningDate_dt_lp_detail = $today_dt_lp_detail->modify('+90 days');
                    if ($expiryDate_dt_lp_detail <= $warningDate_dt_lp_detail) {
                        $lisensi_item_detail['status_validitas_cal_detail'] = 'Akan Kadaluarsa'; $lisensi_item_detail['validitas_badge_class_detail'] = 'warning';
                    } else {
                        $lisensi_item_detail['status_validitas_cal_detail'] = 'Aktif'; $lisensi_item_detail['validitas_badge_class_detail'] = 'success';
                    }
                }
            } catch (Exception $ex) { /* Abaikan error parse tanggal */ }
        } else {
             $lisensi_item_detail['status_validitas_cal_detail'] = 'Aktif (Permanen)'; $lisensi_item_detail['validitas_badge_class_detail'] = 'primary';
        }
        $daftar_lisensi_pelatih[] = $lisensi_item_detail;
    }
} catch (PDOException $e) {
    error_log("Detail Profil Pelatih Error (Lisensi): " . $e->getMessage());
    $_SESSION['pesan_error_display_sementara'] = "Gagal memuat daftar lisensi pelatih.";
}


$base_url_img_detail = rtrim($app_base_path, '/');
$can_edit_profil_ini = in_array($user_role_utama, ['super_admin', 'admin_koni']);
$can_add_lisensi_for_this_pelatih = $can_edit_profil_ini || ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_pengurus_utama)) || ($user_role_utama === 'pelatih' && $user_nik == $pelatih_profil['nik']);

?>

<section class="content">
    <div class="container-fluid">
        <?php include(__DIR__ . '/../../core/partials/global_feedback_messages.php'); ?>
        <?php if(isset($_SESSION['pesan_error_display_sementara'])): /* ... */ endif; ?>

        <div class="row">
            <div class="col-md-4">
                <!-- Card Profil Kiri (Foto, Kontak, Info Dasar) -->
                <div class="card card-purple card-outline">
                    <!-- ... (Isi card profil kiri seperti sebelumnya) ... -->
                    <div class="card-body box-profile">
                        <div class="text-center">
                            <?php /* ... (logika foto profil) ... */ 
                                $foto_url_display_detail = $base_url_img_detail . '/assets/adminlte/dist/img/kepitran.jpg';
                                if (!empty($pelatih_profil['foto_pelatih_profil']) && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $base_url_img_detail . '/' . ltrim($pelatih_profil['foto_pelatih_profil'], '/'))) {
                                    $foto_url_display_detail = $base_url_img_detail . '/' . ltrim($pelatih_profil['foto_pelatih_profil'], '/');
                                } elseif (!empty($pelatih_profil['foto_pengguna_utama']) && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $base_url_img_detail . '/' . ltrim($pelatih_profil['foto_pengguna_utama'], '/'))) {
                                    $foto_url_display_detail = $base_url_img_detail . '/' . ltrim($pelatih_profil['foto_pengguna_utama'], '/');
                                }
                            ?>
                            <img class="profile-user-img img-fluid img-circle" src="<?php echo htmlspecialchars($foto_url_display_detail); ?>" alt="Foto Profil" style="width: 100px; height: 100px; object-fit: cover;">
                        </div>
                        <h3 class="profile-username text-center"><?php echo htmlspecialchars($pelatih_profil['nama_lengkap']); ?></h3>
                        <p class="text-muted text-center">Pelatih (NIK: <?php echo htmlspecialchars($pelatih_profil['nik']); ?>)</p>
                        <ul class="list-group list-group-unbordered mb-3">
                            <li class="list-group-item"><b>Kontak Utama</b> <a class="float-right"><?php echo htmlspecialchars($pelatih_profil['nomor_telepon'] ?? '-'); ?></a></li>
                            <li class="list-group-item"><b>Kontak Alternatif</b> <a class="float-right"><?php echo htmlspecialchars($pelatih_profil['kontak_pelatih_alternatif'] ?? '-'); ?></a></li>
                            <li class="list-group-item"><b>Email</b> <a class="float-right"><?php echo htmlspecialchars($pelatih_profil['email'] ?? '-'); ?></a></li>
                        </ul>
                        <?php if ($can_edit_profil_ini): ?>
                        <a href="edit_pelatih.php?id_pelatih=<?php echo $id_pelatih_to_view; ?>" class="btn btn-warning btn-block"><i class="fas fa-edit mr-1"></i> <b>Edit Profil</b></a>
                        <?php endif; ?>
                    </div>
                </div>
                 <div class="card card-purple">
                    <div class="card-header"><h3 class="card-title">Tentang</h3></div>
                    <div class="card-body">
                        <strong><i class="fas fa-birthday-cake mr-1"></i> Tgl Lahir</strong>
                        <p class="text-muted"><?php echo $pelatih_profil['tanggal_lahir'] ? date('d F Y', strtotime($pelatih_profil['tanggal_lahir'])) : '-'; ?></p><hr>
                        <strong><i class="fas fa-venus-mars mr-1"></i> Jenis Kelamin</strong>
                        <p class="text-muted"><?php echo htmlspecialchars($pelatih_profil['jenis_kelamin'] ?? '-'); ?></p><hr>
                        <strong><i class="fas fa-map-marker-alt mr-1"></i> Alamat</strong>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($pelatih_profil['alamat'] ?? '-')); ?></p>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <!-- Card Informasi Approval Profil & Histori -->
                <div class="card card-purple card-outline mb-3">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history mr-1"></i> Status & Histori Profil Pelatih</h3>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Status Profil Saat Ini</dt>
                            <dd class="col-sm-8"><span class="badge badge-<?php echo ($pelatih_profil['status_approval'] == 'disetujui' ? 'success' : ($pelatih_profil['status_approval'] == 'pending' || $pelatih_profil['status_approval'] == 'revisi' ? 'warning' : 'danger')); ?> p-1"><?php echo ucfirst($pelatih_profil['status_approval']); ?></span></dd>

                            <?php if ($pelatih_profil['approved_by_nik']): ?>
                                <dt class="col-sm-4">Diproses (Profil) oleh</dt>
                                <dd class="col-sm-8">
                                    <?php echo htmlspecialchars($pelatih_profil['nama_approver_profil'] ?? ('NIK: ' . $pelatih_profil['approved_by_nik'])); ?>
                                    <?php if ($pelatih_profil['approval_at']): ?>
                                        <small class="text-muted"> (pada <?php echo date('d M Y, H:i', strtotime($pelatih_profil['approval_at'])); ?>)</small>
                                    <?php endif; ?>
                                </dd>
                            <?php endif; ?>

                            <?php if (in_array($pelatih_profil['status_approval'], ['ditolak', 'revisi']) && !empty($pelatih_profil['alasan_penolakan'])): ?>
                                <dt class="col-sm-4">Alasan Penolakan/Revisi Profil</dt>
                                <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($pelatih_profil['alasan_penolakan'])); ?></dd>
                            <?php endif; ?>

                            <dt class="col-sm-4">Profil Dibuat oleh</dt>
                            <dd class="col-sm-8">
                                <?php echo htmlspecialchars($pelatih_profil['nama_creator_profil'] ?? ($pelatih_profil['created_by_nik'] ? 'NIK: ' . $pelatih_profil['created_by_nik'] : 'Sistem')); ?>
                                <small class="text-muted"> (pada <?php echo date('d M Y, H:i', strtotime($pelatih_profil['created_at'])); ?>)</small>
                            </dd>

                            <?php if ($pelatih_profil['updated_by_nik'] && strtotime($pelatih_profil['updated_at']) > strtotime($pelatih_profil['created_at'])): ?>
                                <dt class="col-sm-4">Update Profil Terakhir oleh</dt>
                                <dd class="col-sm-8">
                                    <?php echo htmlspecialchars($pelatih_profil['nama_editor_profil'] ?? ('NIK: ' . $pelatih_profil['updated_by_nik'])); ?>
                                    <small class="text-muted"> (pada <?php echo date('d M Y, H:i', strtotime($pelatih_profil['updated_at'])); ?>)</small>
                                </dd>
                            <?php endif; ?>
                        </dl>
                    </div>
                </div>

                <!-- Card Daftar Lisensi -->
                <div class="card card-purple card-outline">
                    <div class="card-header">
                         <h3 class="card-title"><i class="fas fa-id-badge mr-1"></i> Daftar Lisensi Kepelatihan</h3>
                         <div class="card-tools">
                            <?php if ($can_add_lisensi_for_this_pelatih): ?>
                                <a href="<?php echo $app_base_path; ?>/modules/lisensi_pelatih/tambah_lisensi_pelatih.php?nik_pelatih_default=<?php echo $pelatih_profil['nik']; ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-plus mr-1"></i> Tambah Lisensi
                                </a>
                            <?php endif; ?>
                         </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($daftar_lisensi_pelatih)): ?>
                            <div class="table-responsive">
                                <table id="tabelDetailLisensiPelatih" class="table table-sm table-striped table-bordered" style="width:100%;">
                                    <thead>
                                        <tr class="text-center">
                                            <th style="width:5%;">No.</th>
                                            <th>Nama Lisensi & Cabor</th>
                                            <th>Tingkat & Lembaga</th>
                                            <th style="width:15%;">Berlaku</th>
                                            <th style="width:10%;">Validitas</th>
                                            <th style="width:15%;">Status Lisensi</th>
                                            <th style="width:10%;" class="no-export">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($daftar_lisensi_pelatih as $idx_lp => $lp): ?>
                                        <tr>
                                            <td class="text-center"><?php echo $idx_lp + 1; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($lp['nama_lisensi_sertifikat']); ?></strong>
                                                <br><small class="text-muted">Cabor: <?php echo htmlspecialchars($lp['nama_cabor']); ?></small>
                                                <?php if ($lp['nomor_sertifikat']): ?>
                                                    <br><small class="text-muted">No: <?php echo htmlspecialchars($lp['nomor_sertifikat']); ?></small>
                                                <?php endif; ?>
                                                <?php if ($lp['path_file_sertifikat'] && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $base_url_img_detail . '/' . ltrim($lp['path_file_sertifikat'], '/'))): ?>
                                                    <a href="<?php echo $base_url_img_detail . '/' . ltrim($lp['path_file_sertifikat'], '/'); ?>" target="_blank" title="Lihat File Sertifikat"><i class="fas fa-file-alt fa-xs ml-1 text-primary"></i></a>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($lp['tingkat_lisensi'] ?? '-'); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($lp['lembaga_penerbit'] ?? '-'); ?></small>
                                            </td>
                                            <td class="text-center">
                                                <?php echo $lp['tanggal_terbit'] ? date('d/m/y', strtotime($lp['tanggal_terbit'])) : '-'; ?>
                                                s/d
                                                <?php echo $lp['tanggal_kadaluarsa'] ? date('d/m/y', strtotime($lp['tanggal_kadaluarsa'])) : 'Permanen'; ?>
                                            </td>
                                            <td class="text-center"><span class="badge badge-<?php echo $lp['validitas_badge_class_detail']; ?> p-1"><?php echo htmlspecialchars($lp['status_validitas_cal_detail']); ?></span></td>
                                            <td class="text-center">
                                                <?php
                                                    $status_text_lp_detail = ucfirst(str_replace('_', ' ', $lp['status_approval']));
                                                    $status_badge_lp_detail = 'secondary'; $alasan_lp_detail = '';
                                                    if ($lp['status_approval'] == 'disetujui_admin') { $status_badge_lp_detail = 'success'; }
                                                    elseif ($lp['status_approval'] == 'disetujui_pengcab') { $status_badge_lp_detail = 'primary'; }
                                                    elseif (in_array($lp['status_approval'], ['ditolak_pengcab', 'ditolak_admin'])) { $status_badge_lp_detail = 'danger'; $alasan_lp_detail = $lp['alasan_penolakan_admin'] ?: $lp['alasan_penolakan_pengcab']; }
                                                    elseif (in_array($lp['status_approval'], ['pending', 'revisi'])) { $status_badge_lp_detail = 'warning'; if($lp['status_approval'] == 'revisi') {$alasan_lp_detail = $lp['alasan_penolakan_admin'] ?: $lp['alasan_penolakan_pengcab'];} }
                                                    echo "<span class='badge badge-{$status_badge_lp_detail} p-1'>{$status_text_lp_detail}</span>";
                                                    if(!empty($alasan_lp_detail)) { echo "<br><small class='text-danger' data-toggle='tooltip' title='Alasan/Catatan Lengkap: ".htmlspecialchars($alasan_lp_detail)."'>(Lihat Alasan)</small>"; }
                                                ?>
                                            </td>
                                            <td class="text-center">
                                                <?php
                                                // Tombol Edit Lisensi (mengarahkan ke modul lisensi_pelatih)
                                                // Hak akses edit lisensi akan divalidasi di halaman edit_lisensi_pelatih.php
                                                echo '<a href="'.$app_base_path.'/modules/lisensi_pelatih/edit_lisensi_pelatih.php?id_lisensi='.$lp['id_lisensi_pelatih'].'" class="btn btn-xs btn-warning mb-1" title="Edit Lisensi Ini"><i class="fas fa-edit"></i></a>';
                                                
                                                // Tombol Hapus Lisensi (jika Super Admin/Admin KONI)
                                                if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
                                                    echo ' <a href="'.$app_base_path.'/modules/lisensi_pelatih/hapus_lisensi_pelatih.php?id_lisensi='.$lp['id_lisensi_pelatih'].'&id_pelatih_asal='.$id_pelatih_to_view.'" class="btn btn-xs btn-danger mb-1" title="Hapus Lisensi Ini" onclick="return confirm(\'Yakin ingin menghapus lisensi ini?\')"><i class="fas fa-trash"></i></a>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted"><em>Belum ada data lisensi kepelatihan untuk pelatih ini.</em></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
         <div class="row">
            <div class="col-12 text-center mt-3 mb-3">
                 <a href="daftar_pelatih.php" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Profil Pelatih</a>
            </div>
        </div>
    </div>
</section>

<?php
$inline_script = "
$(function () {
  $('[data-toggle=\"tooltip\"]').tooltip();

  $('#tabelDetailLisensiPelatih').DataTable({
    \"paging\": true,
    \"lengthChange\": false,
    \"searching\": false, // Pencarian bisa di daftar utama lisensi
    \"ordering\": true,
    \"info\": true,
    \"autoWidth\": false,
    \"responsive\": true,
    \"order\": [[ 0, 'asc' ]], // Urutkan berdasarkan No.
    \"language\": { /* Terjemahan Indonesia */ }
  });
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>