<?php
// File: modules/lisensi_pelatih/daftar_lisensi_pelatih.php

$page_title = "Manajemen Lisensi Pelatih";

// Definisi Aset CSS & JS untuk DataTables, Select2
$additional_css = [
    'assets/adminlte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css',
    'assets/adminlte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css',
    'assets/adminlte/plugins/datatables-buttons/css/buttons.bootstrap4.min.css',
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/datatables/jquery.dataTables.min.js',
    'assets/adminlte/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js',
    'assets/adminlte/plugins/datatables-responsive/js/dataTables.responsive.min.js',
    'assets/adminlte/plugins/datatables-responsive/js/responsive.bootstrap4.min.js',
    'assets/adminlte/plugins/datatables-buttons/js/dataTables.buttons.min.js',
    'assets/adminlte/plugins/datatables-buttons/js/buttons.bootstrap4.min.js',
    'assets/adminlte/plugins/jszip/jszip.min.js',
    'assets/adminlte/plugins/pdfmake/pdfmake.min.js',
    'assets/adminlte/plugins/pdfmake/vfs_fonts.js',
    'assets/adminlte/plugins/datatables-buttons/js/buttons.html5.min.js',
    'assets/adminlte/plugins/datatables-buttons/js/buttons.print.min.js',
    'assets/adminlte/plugins/datatables-buttons/js/buttons.colVis.min.js',
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
];

require_once(__DIR__ . '/../../core/header.php');

// 1. Pengecekan Sesi & Hak Akses Pengguna
if (!isset($user_nik, $user_role_utama, $app_base_path, $pdo)) {
    $_SESSION['pesan_error_global'] = "Sesi tidak valid atau konfigurasi inti bermasalah.";
    header("Location: " . ($app_base_path ?? '.') . "/auth/login.php");
    exit();
}

$can_add_lisensi = false;
$can_edit_lisensi_global = false;
$can_delete_lisensi_global = false;
$can_approve_pengcab = false;
$can_approve_admin = false;
$id_cabor_pengurus_session = $id_cabor_pengurus_utama ?? null; // Ambil dari sesi

if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    $can_add_lisensi = true;
    $can_edit_lisensi_global = true;
    $can_delete_lisensi_global = true;
    $can_approve_admin = true;
    $can_approve_pengcab = true; // Admin juga bisa bertindak sebagai pengcab jika perlu
} elseif ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_pengurus_session)) {
    $can_add_lisensi = true;
    $can_approve_pengcab = true;
} elseif ($user_role_utama === 'pelatih') {
    $can_add_lisensi = true;
}

// 2. Pengambilan Data untuk Filter Dropdown
$cabor_list_filter_lp = [];
if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    try {
        $stmt_c = $pdo->query("SELECT id_cabor, nama_cabor FROM cabang_olahraga ORDER BY nama_cabor ASC");
        $cabor_list_filter_lp = $stmt_c->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log("Error fetch cabor list for filter: " . $e->getMessage()); }
}

$allowed_statuses_approval_lp = ['pending','disetujui_pengcab','disetujui_admin','ditolak_pengcab','ditolak_admin','revisi'];
$allowed_statuses_validitas_lp = ['aktif', 'akan_kadaluarsa', 'kadaluarsa', 'permanen', 'tidak_ada_info'];


// 3. Pengambilan Parameter Filter dari GET Request
$filter_id_cabor_lp = isset($_GET['id_cabor']) && filter_var($_GET['id_cabor'], FILTER_VALIDATE_INT) ? (int)$_GET['id_cabor'] : null;
$filter_nik_pelatih_lp = isset($_GET['nik_pelatih']) && preg_match('/^\d{1,16}$/', $_GET['nik_pelatih']) ? $_GET['nik_pelatih'] : null;
$filter_status_approval_lp = isset($_GET['status_approval']) && in_array($_GET['status_approval'], $allowed_statuses_approval_lp) ? $_GET['status_approval'] : null;
$filter_status_validitas_lp = isset($_GET['status_validitas']) && in_array($_GET['status_validitas'], $allowed_statuses_validitas_lp) ? $_GET['status_validitas'] : null;
$filter_search_text_lp = isset($_GET['search_text']) ? trim($_GET['search_text']) : null;


// 4. Query Utama untuk Mengambil Daftar Lisensi Pelatih
$daftar_lisensi_processed = [];
try {
    $sql_lp = "SELECT lp.*, 
                      plt.id_pelatih, /* Ambil id_pelatih dari tabel pelatih */
                      p.nama_lengkap AS nama_pelatih, 
                      co.nama_cabor,
                      pengcab_app.nama_lengkap AS nama_approver_pengcab,
                      admin_app.nama_lengkap AS nama_approver_admin,
                      creator.nama_lengkap AS nama_creator_lisensi
               FROM lisensi_pelatih lp
               JOIN pelatih plt ON lp.id_pelatih = plt.id_pelatih
               JOIN pengguna p ON lp.nik_pelatih = p.nik
               JOIN cabang_olahraga co ON lp.id_cabor = co.id_cabor
               LEFT JOIN pengguna pengcab_app ON lp.approved_by_nik_pengcab = pengcab_app.nik
               LEFT JOIN pengguna admin_app ON lp.approved_by_nik_admin = admin_app.nik
               LEFT JOIN pengguna creator ON lp.created_by_nik = creator.nik";
    
    $conditions_lp = [];
    $params_lp = [];

    if ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_pengurus_session)) {
        $conditions_lp[] = "lp.id_cabor = :user_id_cabor_session";
        $params_lp[':user_id_cabor_session'] = $id_cabor_pengurus_session;
        if ($filter_id_cabor_lp && $filter_id_cabor_lp != $id_cabor_pengurus_session) {
            $filter_id_cabor_lp = $id_cabor_pengurus_session;
        } elseif (!$filter_id_cabor_lp) {
            $filter_id_cabor_lp = $id_cabor_pengurus_session;
        }
    } elseif ($user_role_utama === 'pelatih') {
        $conditions_lp[] = "lp.nik_pelatih = :user_nik_login_session";
        $params_lp[':user_nik_login_session'] = $user_nik;
    }

    if ($filter_id_cabor_lp) {
        if (!isset($params_lp[':user_id_cabor_session']) || $params_lp[':user_id_cabor_session'] != $filter_id_cabor_lp) {
            $conditions_lp[] = "lp.id_cabor = :filter_id_cabor";
            $params_lp[':filter_id_cabor'] = $filter_id_cabor_lp;
        }
    }
    if ($filter_nik_pelatih_lp) {
        $conditions_lp[] = "lp.nik_pelatih = :filter_nik_pelatih";
        $params_lp[':filter_nik_pelatih'] = $filter_nik_pelatih_lp;
    }
    if ($filter_status_approval_lp) {
        $conditions_lp[] = "lp.status_approval = :filter_status_approval";
        $params_lp[':filter_status_approval'] = $filter_status_approval_lp;
    }
    if ($filter_search_text_lp) {
        $conditions_lp[] = "(p.nama_lengkap LIKE :search_text OR lp.nama_lisensi_sertifikat LIKE :search_text OR lp.nomor_sertifikat LIKE :search_text OR lp.lembaga_penerbit LIKE :search_text OR lp.tingkat_lisensi LIKE :search_text)";
        $params_lp[':search_text'] = "%" . $filter_search_text_lp . "%";
    }
    
    if (!empty($conditions_lp)) {
        $sql_lp .= " WHERE " . implode(" AND ", $conditions_lp);
    }
    $sql_lp .= " ORDER BY lp.created_at DESC, p.nama_lengkap ASC"; // Urutkan berdasarkan terbaru dulu

    $stmt_lp = $pdo->prepare($sql_lp);
    $stmt_lp->execute($params_lp);
    $daftar_lisensi_raw = $stmt_lp->fetchAll(PDO::FETCH_ASSOC);

    $today_dt_lp = new DateTimeImmutable(); // Use Immutable for safety
    foreach ($daftar_lisensi_raw as $lisensi_item) {
        $lisensi_item['status_validitas_cal'] = 'Tidak Ada Info';
        $lisensi_item['validitas_badge_class'] = 'secondary';

        if ($lisensi_item['tanggal_kadaluarsa']) {
            try {
                $expiryDate_dt_lp = new DateTimeImmutable($lisensi_item['tanggal_kadaluarsa']);
                 if ($expiryDate_dt_lp < $today_dt_lp) {
                    $lisensi_item['status_validitas_cal'] = 'Kadaluarsa';
                    $lisensi_item['validitas_badge_class'] = 'danger';
                } else {
                    $warningDate_dt_lp = $today_dt_lp->modify('+90 days');
                    if ($expiryDate_dt_lp <= $warningDate_dt_lp) {
                        $lisensi_item['status_validitas_cal'] = 'Akan Kadaluarsa';
                        $lisensi_item['validitas_badge_class'] = 'warning';
                    } else {
                        $lisensi_item['status_validitas_cal'] = 'Aktif';
                        $lisensi_item['validitas_badge_class'] = 'success';
                    }
                }
            } catch (Exception $ex) { /* Log date parse error */ }
        } else {
             $lisensi_item['status_validitas_cal'] = 'Aktif (Permanen)';
             $lisensi_item['validitas_badge_class'] = 'primary'; // Warna beda untuk permanen
        }
        
        if ($filter_status_validitas_lp) {
            $target_status_validitas = '';
            if ($filter_status_validitas_lp === 'aktif' && $lisensi_item['status_validitas_cal'] === 'Aktif') { $target_status_validitas = 'cocok'; }
            elseif ($filter_status_validitas_lp === 'permanen' && $lisensi_item['status_validitas_cal'] === 'Aktif (Permanen)') { $target_status_validitas = 'cocok'; }
            elseif ($filter_status_validitas_lp === 'akan_kadaluarsa' && $lisensi_item['status_validitas_cal'] === 'Akan Kadaluarsa') { $target_status_validitas = 'cocok'; }
            elseif ($filter_status_validitas_lp === 'kadaluarsa' && $lisensi_item['status_validitas_cal'] === 'Kadaluarsa') { $target_status_validitas = 'cocok'; }
             elseif ($filter_status_validitas_lp === 'tidak_ada_info' && $lisensi_item['status_validitas_cal'] === 'Tidak Ada Info') { $target_status_validitas = 'cocok'; }
            
            if ($target_status_validitas === 'cocok') {
                 $daftar_lisensi_processed[] = $lisensi_item;
            }
        } else {
            $daftar_lisensi_processed[] = $lisensi_item;
        }
    }

} catch (PDOException $e) {
    error_log("PDOError Daftar Lisensi Pelatih: " . $e->getMessage());
    $_SESSION['pesan_error_global'] = "Terjadi kesalahan database saat memuat data lisensi.";
} catch (Exception $e) {
    error_log("GeneralError Daftar Lisensi Pelatih: " . $e->getMessage());
    $_SESSION['pesan_error_global'] = "Terjadi kesalahan sistem saat memproses data lisensi.";
}

// Mengambil nama pelatih untuk filter NIK jika ada, untuk ditampilkan di header
$nama_pelatih_filtered_lp = '';
if ($filter_nik_pelatih_lp && in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor'])) {
    try {
        $stmt_nama_p = $pdo->prepare("SELECT nama_lengkap FROM pengguna WHERE nik = :nik");
        $stmt_nama_p->bindParam(':nik', $filter_nik_pelatih_lp);
        $stmt_nama_p->execute();
        $nama_pelatih_filtered_lp = $stmt_nama_p->fetchColumn();
    } catch (PDOException $e) { /* Abaikan jika gagal ambil nama */ }
}

?>

<section class="content">
    <div class="container-fluid">
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

        <div class="card card-purple card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-id-badge mr-1"></i> 
                    <?php echo $page_title; ?>
                    <?php
                    if ($filter_id_cabor_lp) {
                        $nama_cabor_f = '';
                        if (!empty($cabor_list_filter_lp)) { foreach($cabor_list_filter_lp as $cfl){ if($cfl['id_cabor'] == $filter_id_cabor_lp){ $nama_cabor_f = $cfl['nama_cabor']; break; } } }
                        elseif ($user_role_utama === 'pengurus_cabor' && $id_cabor_pengurus_session == $filter_id_cabor_lp) {
                            // Ambil nama cabor pengurus jika tidak dari admin list
                            $stmt_cn = $pdo->prepare("SELECT nama_cabor FROM cabang_olahraga WHERE id_cabor = :id"); $stmt_cn->execute([':id' => $id_cabor_pengurus_session]); $nama_cabor_f = $stmt_cn->fetchColumn();
                        }
                        if($nama_cabor_f) echo " <small class='text-muted font-weight-normal'>- Cabor: " . htmlspecialchars($nama_cabor_f) . "</small>";
                    }
                    if ($filter_nik_pelatih_lp) { echo " <small class='text-muted font-weight-normal'>- Pelatih: " . htmlspecialchars($nama_pelatih_filtered_lp ?: $filter_nik_pelatih_lp) . "</small>"; }
                    if ($filter_status_approval_lp) { echo " <small class='text-muted font-weight-normal'>- Approval: " . htmlspecialchars(ucfirst(str_replace('_', ' ', $filter_status_approval_lp))) . "</small>"; }
                    if ($filter_status_validitas_lp) { echo " <small class='text-muted font-weight-normal'>- Validitas: " . htmlspecialchars(ucfirst(str_replace('_', ' ', $filter_status_validitas_lp))) . "</small>"; }
                    if ($filter_search_text_lp) { echo " <small class='text-muted font-weight-normal'>- Cari: \"" . htmlspecialchars($filter_search_text_lp) . "\"</small>"; }
                    ?>
                </h3>
                <div class="card-tools d-flex align-items-center">
                    <?php if ($can_add_lisensi): ?>
                        <a href="tambah_lisensi_pelatih.php<?php
                            $params_add_lp = [];
                            if($user_role_utama === 'pengurus_cabor' && $id_cabor_pengurus_session) { $params_add_lp['id_cabor_default'] = $id_cabor_pengurus_session;}
                            elseif($filter_id_cabor_lp) {$params_add_lp['id_cabor_default'] = $filter_id_cabor_lp;}
                            if($user_role_utama === 'pelatih') { $params_add_lp['nik_pelatih_default'] = $user_nik; }
                            elseif($filter_nik_pelatih_lp) {$params_add_lp['nik_pelatih_default'] = $filter_nik_pelatih_lp;}
                            echo !empty($params_add_lp) ? '?' . http_build_query($params_add_lp) : '';
                        ?>" class="btn btn-success btn-sm mr-2">
                            <i class="fas fa-plus mr-1"></i> Tambah Lisensi
                        </a>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="form-inline">
                        <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni'])): ?>
                            <select name="id_cabor" class="form-control form-control-sm mr-1 select2bs4-filter-header" style="min-width: 150px;" data-placeholder="Filter Cabor">
                                <option value=""></option>
                                <?php foreach ($cabor_list_filter_lp as $cabor_f_lp): ?>
                                    <option value="<?php echo $cabor_f_lp['id_cabor']; ?>" <?php echo ($filter_id_cabor_lp == $cabor_f_lp['id_cabor']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cabor_f_lp['nama_cabor']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        
                        <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor'])): ?>
                             <input type="text" name="nik_pelatih" class="form-control form-control-sm mr-1" placeholder="Filter NIK Pelatih" value="<?php echo htmlspecialchars($filter_nik_pelatih_lp ?? ''); ?>" style="width: 130px;">
                        <?php endif; ?>

                        <select name="status_approval" class="form-control form-control-sm mr-1 select2bs4-filter-header" data-placeholder="Filter Approval" style="min-width: 130px;">
                            <option value=""></option>
                            <?php foreach ($allowed_statuses_approval_lp as $status_app_opt): ?>
                                <option value="<?php echo $status_app_opt; ?>" <?php echo ($filter_status_approval_lp == $status_app_opt) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status_app_opt))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="status_validitas" class="form-control form-control-sm mr-1 select2bs4-filter-header" data-placeholder="Filter Validitas" style="min-width: 120px;">
                            <option value=""></option>
                            <?php foreach ($allowed_statuses_validitas_lp as $status_val_opt): ?>
                                <option value="<?php echo $status_val_opt; ?>" <?php echo ($filter_status_validitas_lp == $status_val_opt) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status_val_opt))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="input-group input-group-sm" style="width: 180px;">
                            <input type="text" name="search_text" class="form-control" placeholder="Cari..." value="<?php echo htmlspecialchars($filter_search_text_lp ?? ''); ?>">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-default" title="Terapkan Filter"><i class="fas fa-search"></i></button>
                            </div>
                        </div>
                        <?php if ($filter_id_cabor_lp || $filter_nik_pelatih_lp || $filter_status_approval_lp || $filter_status_validitas_lp || $filter_search_text_lp): ?>
                            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-sm btn-outline-secondary ml-1" title="Reset Semua Filter"><i class="fas fa-times-circle"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tabelLisensiPelatih" class="table table-bordered table-hover table-striped table-sm">
                        <thead>
                            <tr class="text-center">
                                <th>No.</th>
                                <th>Nama Pelatih</th>
                                <th>NIK</th>
                                <th>Cabor Lisensi</th>
                                <th>Nama Lisensi</th>
                                <th>No. Sertifikat</th>
                                <th>Tingkat</th>
                                <th>Lembaga</th>
                                <th>Tgl Terbit</th>
                                <th>Tgl Kadaluarsa</th>
                                <th>Validitas</th>
                                <th>Status Approval</th>
                                <th class="no-export">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($daftar_lisensi_processed)): ?>
                                <?php foreach ($daftar_lisensi_processed as $index => $lisensi): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $index + 1; ?></td>
                                        <td><a href="<?php echo $app_base_path; ?>/modules/pelatih/detail_pelatih.php?id_pelatih=<?php echo $lisensi['id_pelatih']; ?>" title="Lihat Profil Pelatih"><?php echo htmlspecialchars($lisensi['nama_pelatih']); ?></a></td>
                                        <td class="text-center"><?php echo htmlspecialchars($lisensi['nik_pelatih']); ?></td>
                                        <td><?php echo htmlspecialchars($lisensi['nama_cabor']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($lisensi['nama_lisensi_sertifikat']); ?>
                                            <?php if ($lisensi['path_file_sertifikat'] && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $app_base_path . '/' . ltrim($lisensi['path_file_sertifikat'], '/'))): ?>
                                                <a href="<?php echo $app_base_path . '/' . ltrim($lisensi['path_file_sertifikat'], '/'); ?>" target="_blank" title="Lihat File Sertifikat"><i class="fas fa-file-alt fa-xs ml-1 text-primary"></i></a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($lisensi['nomor_sertifikat'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($lisensi['tingkat_lisensi'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($lisensi['lembaga_penerbit'] ?? '-'); ?></td>
                                        <td class="text-center"><?php echo $lisensi['tanggal_terbit'] ? date('d-m-Y', strtotime($lisensi['tanggal_terbit'])) : '-'; ?></td>
                                        <td class="text-center"><?php echo $lisensi['tanggal_kadaluarsa'] ? date('d-m-Y', strtotime($lisensi['tanggal_kadaluarsa'])) : '-'; ?></td>
                                        <td class="text-center"><span class="badge badge-<?php echo $lisensi['validitas_badge_class']; ?> p-1"><?php echo htmlspecialchars($lisensi['status_validitas_cal']); ?></span></td>
                                        <td class="text-center">
                                            <?php
                                                $status_text_lp = ucfirst(str_replace('_', ' ', $lisensi['status_approval']));
                                                $status_badge_lp = 'secondary'; $alasan_lp = '';
                                                if ($lisensi['status_approval'] == 'disetujui_admin') { $status_badge_lp = 'success'; }
                                                elseif ($lisensi['status_approval'] == 'disetujui_pengcab') { $status_badge_lp = 'primary'; }
                                                elseif (in_array($lisensi['status_approval'], ['ditolak_pengcab', 'ditolak_admin'])) { $status_badge_lp = 'danger'; $alasan_lp = $lisensi['alasan_penolakan_admin'] ?: $lisensi['alasan_penolakan_pengcab']; }
                                                elseif (in_array($lisensi['status_approval'], ['pending', 'revisi'])) { $status_badge_lp = 'warning'; if($lisensi['status_approval'] == 'revisi') {$alasan_lp = $lisensi['alasan_penolakan_admin'] ?: $lisensi['alasan_penolakan_pengcab'];} }
                                                echo "<span class='badge badge-{$status_badge_lp} p-1'>{$status_text_lp}</span>";
                                                if(!empty($alasan_lp)) { echo "<i class='fas fa-info-circle ml-1 text-danger' data-toggle='tooltip' title='Alasan/Catatan: ".htmlspecialchars($alasan_lp)."'></i>"; }
                                            ?>
                                        </td>
                                        <td class="text-center project-actions">
                                            <?php
                                            $can_edit_this_one = false;
                                            if ($can_edit_lisensi_global) { $can_edit_this_one = true; }
                                            elseif ($user_role_utama === 'pengurus_cabor' && $lisensi['id_cabor'] == $id_cabor_pengurus_session && in_array($lisensi['status_approval'], ['pending', 'revisi', 'ditolak_pengcab', 'ditolak_admin'])) { $can_edit_this_one = true; }
                                            elseif ($user_role_utama === 'pelatih' && $lisensi['nik_pelatih'] == $user_nik && in_array($lisensi['status_approval'], ['pending', 'revisi', 'ditolak_pengcab', 'ditolak_admin'])) { $can_edit_this_one = true; }
                                            if ($can_edit_this_one) {
                                                echo '<a href="edit_lisensi_pelatih.php?id_lisensi=' . $lisensi['id_lisensi_pelatih'] . '" class="btn btn-xs btn-warning mr-1 mb-1" title="Edit Lisensi"><i class="fas fa-edit"></i></a>';
                                            }

                                            if ($can_approve_pengcab && ($user_role_utama === 'admin_koni' || $user_role_utama === 'super_admin' || ($user_role_utama === 'pengurus_cabor' && $lisensi['id_cabor'] == $id_cabor_pengurus_session)) && in_array($lisensi['status_approval'], ['pending', 'revisi'])) {
                                                echo '<button type="button" class="btn btn-xs btn-primary mr-1 mb-1" title="Setujui (Pengcab)" onclick="handleLisensiApproval(' . $lisensi['id_lisensi_pelatih'] . ', \'' . htmlspecialchars(addslashes($lisensi['nama_lisensi_sertifikat'])) . '\', \'disetujui_pengcab\')"><i class="fas fa-check"></i> Pengcab</button>';
                                                echo '<button type="button" class="btn btn-xs btn-outline-danger mr-1 mb-1" title="Tolak (Pengcab)" onclick="handleLisensiApproval(' . $lisensi['id_lisensi_pelatih'] . ', \'' . htmlspecialchars(addslashes($lisensi['nama_lisensi_sertifikat'])) . '\', \'ditolak_pengcab\')"><i class="fas fa-times"></i> Pengcab</button>';
                                            }

                                            if ($can_approve_admin && in_array($lisensi['status_approval'], ['pending', 'disetujui_pengcab', 'revisi'])) {
                                                echo '<button type="button" class="btn btn-xs btn-success mr-1 mb-1" title="Setujui Final (Admin)" onclick="handleLisensiApproval(' . $lisensi['id_lisensi_pelatih'] . ', \'' . htmlspecialchars(addslashes($lisensi['nama_lisensi_sertifikat'])) . '\', \'disetujui_admin\')"><i class="fas fa-user-check"></i> Admin</button>';
                                                if ($lisensi['status_approval'] != 'ditolak_admin' && $lisensi['status_approval'] != 'revisi'){
                                                    echo '<button type="button" class="btn btn-xs btn-danger mr-1 mb-1" title="Tolak Final (Admin)" onclick="handleLisensiApproval(' . $lisensi['id_lisensi_pelatih'] . ', \'' . htmlspecialchars(addslashes($lisensi['nama_lisensi_sertifikat'])) . '\', \'ditolak_admin\')"><i class="fas fa-user-times"></i> Admin</button>';
                                                }
                                                if ($lisensi['status_approval'] != 'revisi'){
                                                     echo '<button type="button" class="btn btn-xs btn-secondary mr-1 mb-1" title="Minta Revisi (Admin)" onclick="handleLisensiApproval(' . $lisensi['id_lisensi_pelatih'] . ', \'' . htmlspecialchars(addslashes($lisensi['nama_lisensi_sertifikat'])) . '\', \'revisi\')"><i class="fas fa-undo"></i> Revisi</button>';
                                                }
                                            }
                                            
                                            if ($can_delete_lisensi_global) {
                                                echo '<a href="hapus_lisensi_pelatih.php?id_lisensi=' . $lisensi['id_lisensi_pelatih'] . '" class="btn btn-xs btn-dark mb-1" title="Hapus Lisensi" onclick="return confirm(\'PERHATIAN! Anda akan menghapus data lisensi ini secara permanen. Lanjutkan?\')"><i class="fas fa-trash"></i></a>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="13" class="text-center"><em>Tidak ada data lisensi pelatih yang sesuai dengan filter Anda, atau belum ada data sama sekali.</em></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$inline_script = "
$(function () {
    $('#tabelLisensiPelatih').DataTable({
        \"responsive\": true, \"lengthChange\": true, \"autoWidth\": false,
        \"buttons\": [
            { extend: 'copy', text: '<i class=\"fas fa-copy mr-1\"></i> Salin', className: 'btn-sm btn-default', titleAttr: 'Salin ke clipboard', exportOptions: { columns: ':visible:not(.no-export)' } },
            { extend: 'csv', text: '<i class=\"fas fa-file-csv mr-1\"></i> CSV', className: 'btn-sm btn-default', titleAttr: 'Ekspor ke CSV', exportOptions: { columns: ':visible:not(.no-export)' } },
            { extend: 'excel', text: '<i class=\"fas fa-file-excel mr-1\"></i> Excel', className: 'btn-sm btn-default', titleAttr: 'Ekspor ke Excel', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Lisensi Pelatih' },
            { extend: 'pdf', text: '<i class=\"fas fa-file-pdf mr-1\"></i> PDF', className: 'btn-sm btn-default', titleAttr: 'Ekspor ke PDF', orientation: 'landscape', pageSize: 'LEGAL', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Lisensi Pelatih' },
            { extend: 'print', text: '<i class=\"fas fa-print mr-1\"></i> Cetak', className: 'btn-sm btn-default', titleAttr: 'Cetak Tabel', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Lisensi Pelatih' },
            { extend: 'colvis', text: '<i class=\"fas fa-columns mr-1\"></i> Kolom', className: 'btn-sm btn-default', titleAttr: 'Tampilkan/Sembunyikan Kolom' }
        ],
        \"language\": {
            \"search\": \"\", \"searchPlaceholder\": \"Cari di tabel...\",
            \"lengthMenu\": \"Tampilkan _MENU_ entri\", \"info\": \"Menampilkan _START_ sampai _END_ dari _TOTAL_ lisensi\",
            \"infoEmpty\": \"Tidak ada lisensi ditemukan\", \"infoFiltered\": \"(difilter dari _MAX_ total lisensi)\",
            \"zeroRecords\": \"Tidak ada data lisensi yang cocok dengan pencarian Anda\",
            \"paginate\": { \"first\": \"<<\", \"last\": \">>\", \"next\": \">\", \"previous\": \"<\" },
            \"buttons\": { \"copyTitle\": 'Data Disalin', \"copySuccess\": { _: '%d baris disalin', 1: '1 baris disalin' }}
        },
        \"order\": [[1, 'asc']], 
        \"columnDefs\": [ 
            { \"orderable\": false, \"targets\": [0, 12] }, 
            { \"searchable\": false, \"targets\": [0, 10, 11, 12] } 
        ],
        \"dom\":  \"<'row'<'col-sm-12 col-md-auto'l><'col-sm-12 col-md'B><'col-sm-12 col-md-auto'f>>\" + \"<'row'<'col-sm-12'tr>>\" + \"<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>\",
        \"initComplete\": function(settings, json) { 
            $('[data-toggle=\"tooltip\"]').tooltip();
            $('#tabelLisensiPelatih_filter input').addClass('form-control-sm'); // Membuat search box lebih kecil
        }
    });

    $('.select2bs4-filter-header').select2({ 
        theme: 'bootstrap4', 
        allowClear: true, 
        placeholder: $(this).data('placeholder') || 'Semua',
        width: 'resolve' // atau 'style' jika ingin mengikuti style width dari elemen select
    }).on('change', function() {
        // Jika ingin auto-submit form filter saat select2 berubah
        // $(this).closest('form').submit();
    });
    
    // Hapus filter search bawaan DataTables jika sudah ada form filter sendiri di header
    // $('.dataTables_filter').hide(); 


    window.handleLisensiApproval = function(idLisensi, namaLisensi, newStatus) {
        // ... (Kode fungsi handleLisensiApproval dari respons sebelumnya sudah cukup baik) ...
        // Pastikan `alasanKey` diisi dengan benar ('alasan_penolakan_pengcab' atau 'alasan_penolakan_admin')
        // dan form action mengarah ke proses yang tepat (misal, proses_edit_lisensi_pelatih.php)
        let message = '';
        let alasanKey = '';
        let promptMessage = '';

        if (newStatus === 'disetujui_pengcab') { message = `Setujui lisensi \"\${namaLisensi}\" (Pengcab)?`; }
        else if (newStatus === 'ditolak_pengcab') { 
            message = `Tolak lisensi \"\${namaLisensi}\" (Pengcab)?`;
            promptMessage = `Berikan alasan penolakan (Pengcab) untuk lisensi \"\${namaLisensi}\":`;
            alasanKey = 'alasan_penolakan_pengcab';
        }
        else if (newStatus === 'disetujui_admin') { message = `Setujui lisensi \"\${namaLisensi}\" (Admin KONI)?`; }
        else if (newStatus === 'ditolak_admin') { 
            message = `Tolak lisensi \"\${namaLisensi}\" (Admin KONI)?`;
            promptMessage = `Berikan alasan penolakan (Admin KONI) untuk lisensi \"\${namaLisensi}\":`;
            alasanKey = 'alasan_penolakan_admin';
        }
        else if (newStatus === 'revisi') { 
            message = `Minta revisi untuk lisensi \"\${namaLisensi}\"?`;
            promptMessage = `Berikan catatan untuk revisi lisensi \"\${namaLisensi}\" (oleh Admin):`;
            alasanKey = 'alasan_penolakan_admin'; 
        }
        else { console.error('Status approval tidak dikenal:', newStatus); return false; }

        let alasan = '';
        if (promptMessage) {
            alasan = prompt(promptMessage);
            if (alasan === null) { return false; }
            if (alasan.trim() === '') { alert('Alasan/catatan tidak boleh kosong untuk tindakan ini.'); return false; }
        }

        if (confirm(message)) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'proses_edit_lisensi_pelatih.php'; 
            
            var idInput = document.createElement('input'); idInput.type = 'hidden'; idInput.name = 'id_lisensi_pelatih'; idInput.value = idLisensi; form.appendChild(idInput);
            var statusInput = document.createElement('input'); statusInput.type = 'hidden'; statusInput.name = 'status_approval'; statusInput.value = newStatus; form.appendChild(statusInput);
            var quickActionInput = document.createElement('input'); quickActionInput.type = 'hidden'; quickActionInput.name = 'quick_action_approval_lisensi'; quickActionInput.value = '1'; form.appendChild(quickActionInput);
            
            if (alasanKey && alasan.trim() !== '') { 
                var alasanInput = document.createElement('input'); alasanInput.type = 'hidden'; alasanInput.name = alasanKey; alasanInput.value = alasan; form.appendChild(alasanInput); 
            }
            
            document.body.appendChild(form);
            form.submit();
        }
        return false;
    };
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>