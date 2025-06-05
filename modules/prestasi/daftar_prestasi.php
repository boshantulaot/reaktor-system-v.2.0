<?php
// File: reaktorsystem/modules/prestasi/daftar_prestasi.php

$page_title = "Manajemen Prestasi Atlet";

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

$id_cabor_filter_for_pengurus_prestasi = $id_cabor_pengurus_utama ?? null;
$can_add_prestasi = false;
if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) { $can_add_prestasi = true; }
elseif ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_filter_for_pengurus_prestasi)) { $can_add_prestasi = true; }
elseif ($user_role_utama === 'atlet') { $can_add_prestasi = true; }

$daftar_prestasi_processed = [];
$cabang_olahraga_list_filter_prestasi = [];
$atlet_list_for_filter_prestasi = [];

$filter_id_cabor_get_page_prestasi = isset($_GET['id_cabor']) && filter_var($_GET['id_cabor'], FILTER_VALIDATE_INT) && (int)$_GET['id_cabor'] > 0 ? (int)$_GET['id_cabor'] : null;
$filter_nik_atlet_get_page_prestasi = isset($_GET['nik_atlet']) && preg_match('/^\d{1,16}$/', trim($_GET['nik_atlet'])) ? trim($_GET['nik_atlet']) : null;
$filter_status_approval_get_page_prestasi = isset($_GET['status_approval']) ? trim($_GET['status_approval']) : null;

try {
    if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
        $stmt_cabor_filter_prestasi = $pdo->query("SELECT id_cabor, nama_cabor FROM cabang_olahraga ORDER BY nama_cabor ASC");
        if($stmt_cabor_filter_prestasi){
            $cabang_olahraga_list_filter_prestasi = $stmt_cabor_filter_prestasi->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    $id_cabor_for_atlet_filter_dropdown = $filter_id_cabor_get_page_prestasi ?? $id_cabor_filter_for_pengurus_prestasi;
    if ($id_cabor_for_atlet_filter_dropdown && $user_role_utama !== 'atlet') { // Atlet tidak perlu filter dropdown atlet lain
        $stmt_atlet_filter_prestasi = $pdo->prepare("SELECT p.nik, p.nama_lengkap FROM atlet a JOIN pengguna p ON a.nik = p.nik WHERE a.id_cabor = :id_cabor AND a.status_pendaftaran = 'disetujui' ORDER BY p.nama_lengkap ASC");
        $stmt_atlet_filter_prestasi->bindParam(':id_cabor', $id_cabor_for_atlet_filter_dropdown, PDO::PARAM_INT);
        $stmt_atlet_filter_prestasi->execute();
        $atlet_list_for_filter_prestasi = $stmt_atlet_filter_prestasi->fetchAll(PDO::FETCH_ASSOC);
    }

    $sql_prestasi = "SELECT pr.id_prestasi, p.nama_lengkap AS nama_atlet, pr.nik AS nik_atlet,
                           co.nama_cabor, co.id_cabor AS prestasi_id_cabor,
                           pr.nama_kejuaraan, pr.tingkat_kejuaraan, pr.tahun_perolehan, pr.medali_peringkat, 
                           pr.status_approval, pr.alasan_penolakan_pengcab, pr.alasan_penolakan_admin,
                           pr.bukti_path 
                  FROM prestasi pr
                  JOIN pengguna p ON pr.nik = p.nik
                  JOIN cabang_olahraga co ON pr.id_cabor = co.id_cabor";
    $conditions_prestasi_sql = []; $params_prestasi_sql = [];

    if ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_filter_for_pengurus_prestasi)) {
        $conditions_prestasi_sql[] = "pr.id_cabor = :user_id_cabor_role_prestasi"; 
        $params_prestasi_sql[':user_id_cabor_role_prestasi'] = $id_cabor_filter_for_pengurus_prestasi;
        if ($filter_id_cabor_get_page_prestasi === null) { // Auto-apply filter cabor pengurus jika tidak ada filter cabor dari GET
            $filter_id_cabor_get_page_prestasi = $id_cabor_filter_for_pengurus_prestasi;
        }
    }
    elseif ($user_role_utama === 'atlet') {
        $conditions_prestasi_sql[] = "pr.nik = :user_nik_prestasi";
        $params_prestasi_sql[':user_nik_prestasi'] = $user_nik;
        if ($filter_nik_atlet_get_page_prestasi === null){ // Auto-apply filter NIK atlet jika tidak ada filter NIK dari GET
            $filter_nik_atlet_get_page_prestasi = $user_nik;
        }
    }

    if ($filter_id_cabor_get_page_prestasi !== null) {
        if (!isset($params_prestasi_sql[':user_id_cabor_role_prestasi']) || $params_prestasi_sql[':user_id_cabor_role_prestasi'] != $filter_id_cabor_get_page_prestasi) {
            $conditions_prestasi_sql[] = "pr.id_cabor = :filter_id_cabor_prestasi_param";
            $params_prestasi_sql[':filter_id_cabor_prestasi_param'] = $filter_id_cabor_get_page_prestasi;
        }
    }
    
    if ($filter_nik_atlet_get_page_prestasi !== null) {
         if (!isset($params_prestasi_sql[':user_nik_prestasi']) || $params_prestasi_sql[':user_nik_prestasi'] != $filter_nik_atlet_get_page_prestasi) {
            $conditions_prestasi_sql[] = "pr.nik = :filter_nik_atlet_prestasi_param";
            $params_prestasi_sql[':filter_nik_atlet_prestasi_param'] = $filter_nik_atlet_get_page_prestasi;
        }
    }

    $allowed_statuses_prestasi = ['pending', 'disetujui_pengcab', 'disetujui_admin', 'ditolak_pengcab', 'ditolak_admin', 'revisi'];
    if ($filter_status_approval_get_page_prestasi !== null && in_array($filter_status_approval_get_page_prestasi, $allowed_statuses_prestasi)) {
        $conditions_prestasi_sql[] = "pr.status_approval = :filter_status_approval_prestasi_param";
        $params_prestasi_sql[':filter_status_approval_prestasi_param'] = $filter_status_approval_get_page_prestasi;
    }

    if (!empty($conditions_prestasi_sql)) { $sql_prestasi .= " WHERE " . implode(" AND ", array_unique($conditions_prestasi_sql)); } // array_unique untuk hindari duplikasi kondisi
    $sql_prestasi .= " ORDER BY pr.tahun_perolehan DESC, p.nama_lengkap ASC";

    $stmt_prestasi = $pdo->prepare($sql_prestasi); 
    $stmt_prestasi->execute($params_prestasi_sql); 
    $daftar_prestasi_raw = $stmt_prestasi->fetchAll(PDO::FETCH_ASSOC);

    $doc_root = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
    $base_path_for_file_check = rtrim($app_base_path, '/');

    foreach ($daftar_prestasi_raw as $prestasi_item_raw) {
        $fields_penting_prestasi = ['nama_kejuaraan', 'tingkat_kejuaraan', 'tahun_perolehan', 'medali_peringkat', 'bukti_path'];
        $total_fields_dihitung_prestasi = count($fields_penting_prestasi);
        $fields_terisi_aktual_prestasi = 0;

        if (!empty(trim($prestasi_item_raw['nama_kejuaraan'] ?? ''))) $fields_terisi_aktual_prestasi++;
        if (!empty(trim($prestasi_item_raw['tingkat_kejuaraan'] ?? ''))) $fields_terisi_aktual_prestasi++;
        if (!empty(trim($prestasi_item_raw['tahun_perolehan'] ?? ''))) $fields_terisi_aktual_prestasi++;
        if (!empty(trim($prestasi_item_raw['medali_peringkat'] ?? ''))) $fields_terisi_aktual_prestasi++;
        
        if (!empty($prestasi_item_raw['bukti_path'])) { 
            $bukti_full_path = $doc_root . $base_path_for_file_check . '/' . ltrim($prestasi_item_raw['bukti_path'], '/'); 
            if (file_exists(preg_replace('/\/+/', '/', $bukti_full_path))) { 
                $fields_terisi_aktual_prestasi++; 
            } 
        }

        $progress_persen_prestasi = ($total_fields_dihitung_prestasi > 0) ? round(($fields_terisi_aktual_prestasi / $total_fields_dihitung_prestasi) * 100) : 0;
        $prestasi_item_raw['progress_kelengkapan_prestasi'] = $progress_persen_prestasi;
        if ($progress_persen_prestasi < 50) $prestasi_item_raw['progress_color_prestasi'] = 'bg-danger';
        elseif ($progress_persen_prestasi < 85) $prestasi_item_raw['progress_color_prestasi'] = 'bg-warning';
        else $prestasi_item_raw['progress_color_prestasi'] = 'bg-success';
        
        $daftar_prestasi_processed[] = $prestasi_item_raw;
    }

} catch (PDOException $e) { 
    error_log("Error Daftar Prestasi: " . $e->getMessage()); 
    $_SESSION['pesan_error_global'] = "Terjadi kesalahan fatal saat memuat data prestasi."; 
}
?>

<section class="content">
    <div class="container-fluid">
        <div class="card card-outline card-success shadow mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-trophy mr-1"></i>
                    Data Prestasi Atlet
                    <?php
                    // ... (Kode PHP untuk $nama_filter_display_prestasi tetap sama seperti sebelumnya) ...
                    $nama_filter_display_prestasi = '';
                    if ($filter_id_cabor_get_page_prestasi) {
                        $nama_cabor_header_prestasi = '';
                        if (in_array($user_role_utama, ['super_admin', 'admin_koni']) && !empty($cabang_olahraga_list_filter_prestasi)) {
                            foreach($cabang_olahraga_list_filter_prestasi as $cfl_h_item_prestasi){ if($cfl_h_item_prestasi['id_cabor'] == $filter_id_cabor_get_page_prestasi){ $nama_cabor_header_prestasi = $cfl_h_item_prestasi['nama_cabor']; break; } }
                        } elseif ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_filter_for_pengurus_prestasi)) {
                             if(empty($nama_cabor_pengurus_utama_global_session)) {
                                $stmt_c_peng_pres = $pdo->prepare("SELECT nama_cabor FROM cabang_olahraga WHERE id_cabor = :id"); $stmt_c_peng_pres->execute([':id' => $id_cabor_filter_for_pengurus_prestasi]);
                                $nama_cabor_pengurus_utama_global_session = $stmt_c_peng_pres->fetchColumn();
                             }
                             $nama_cabor_header_prestasi = $nama_cabor_pengurus_utama_global_session;
                        }
                        if(!empty($nama_cabor_header_prestasi)){ $nama_filter_display_prestasi .= " <small class='text-muted'>- Cabor: " . htmlspecialchars($nama_cabor_header_prestasi) . "</small>"; }
                    }
                     if ($filter_nik_atlet_get_page_prestasi) {
                        $nama_atlet_header_prestasi = '';
                        if($user_role_utama === 'atlet' && $filter_nik_atlet_get_page_prestasi === $user_nik) {
                             $nama_atlet_header_prestasi = $user_nama_lengkap; // Asumsi $user_nama_lengkap ada dari header.php
                        } elseif (!empty($atlet_list_for_filter_prestasi) && in_array($filter_nik_atlet_get_page_prestasi, array_column($atlet_list_for_filter_prestasi, 'nik'))){
                            foreach($atlet_list_for_filter_prestasi as $atlet_f_item_pres){ if($atlet_f_item_pres['nik'] == $filter_nik_atlet_get_page_prestasi){ $nama_atlet_header_prestasi = $atlet_f_item_pres['nama_lengkap']; break; } }
                        } else { // Fallback ke DB jika atlet tidak ada di list filter (misal karena cabor filter berubah)
                             try {
                                $stmt_nama_atl_hdr = $pdo->prepare("SELECT nama_lengkap FROM pengguna WHERE nik = :nik");
                                $stmt_nama_atl_hdr->execute([':nik' => $filter_nik_atlet_get_page_prestasi]);
                                $nama_atlet_header_prestasi = $stmt_nama_atl_hdr->fetchColumn();
                             } catch (PDOException $e) { /* abaikan */ }
                        }
                        if(!empty($nama_atlet_header_prestasi)){ $nama_filter_display_prestasi .= " <small class='text-muted'>- Atlet: " . htmlspecialchars($nama_atlet_header_prestasi) . " (NIK: ".htmlspecialchars($filter_nik_atlet_get_page_prestasi).")</small>"; }
                    }

                    if ($filter_status_approval_get_page_prestasi) {
                         $nama_filter_display_prestasi .= " <small class='text-muted'>- Status: " . htmlspecialchars(ucfirst(str_replace('_', ' ', $filter_status_approval_get_page_prestasi))) . "</small>";
                    }
                    echo $nama_filter_display_prestasi;
                    ?>
                </h3>
                <div class="card-tools d-flex align-items-center">
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="form-inline <?php if ($can_add_prestasi) echo 'mr-2'; ?>">
                        <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni'])): ?>
                            <label for="filter_id_cabor_prestasi_page" class="mr-1 text-sm font-weight-normal">Cabor:</label>
                            <select name="id_cabor" id="filter_id_cabor_prestasi_page" class="form-control form-control-sm mr-2" style="max-width: 150px;" onchange="this.form.submit()">
                                <option value="">Semua Cabor</option>
                                <?php foreach ($cabang_olahraga_list_filter_prestasi as $cabor_filter_item_page_pres): ?>
                                    <option value="<?php echo $cabor_filter_item_page_pres['id_cabor']; ?>" <?php echo ($filter_id_cabor_get_page_prestasi == $cabor_filter_item_page_pres['id_cabor']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cabor_filter_item_page_pres['nama_cabor']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        
                        <?php if ($user_role_utama !== 'atlet' && ($id_cabor_for_atlet_filter_dropdown || !empty($atlet_list_for_filter_prestasi))): ?>
                            <label for="filter_nik_atlet_prestasi_page" class="mr-1 text-sm font-weight-normal">Atlet:</label>
                            <select name="nik_atlet" id="filter_nik_atlet_prestasi_page" class="form-control form-control-sm select2bs4 mr-2" style="width: 200px;" onchange="this.form.submit()" data-placeholder="Semua Atlet">
                                <option value=""></option>
                                <?php foreach ($atlet_list_for_filter_prestasi as $atlet_filter_item_page_pres): ?>
                                    <option value="<?php echo $atlet_filter_item_page_pres['nik']; ?>" <?php echo ($filter_nik_atlet_get_page_prestasi == $atlet_filter_item_page_pres['nik']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($atlet_filter_item_page_pres['nama_lengkap'] . ' (' . $atlet_filter_item_page_pres['nik'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <label for="filter_status_approval_prestasi_page" class="mr-1 text-sm font-weight-normal">Status:</label>
                        <select name="status_approval" id="filter_status_approval_prestasi_page" class="form-control form-control-sm mr-2" style="max-width: 170px;" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <?php foreach ($allowed_statuses_prestasi as $status_opt_pres): ?>
                                <option value="<?php echo $status_opt_pres; ?>" <?php echo ($filter_status_approval_get_page_prestasi == $status_opt_pres) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status_opt_pres))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($filter_id_cabor_get_page_prestasi || $filter_status_approval_get_page_prestasi || $filter_nik_atlet_get_page_prestasi): ?>
                            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-sm btn-outline-secondary">Reset Filter</a>
                        <?php endif; ?>
                    </form>

                    <?php if ($can_add_prestasi): ?>
                        <a href="tambah_prestasi.php<?php /* ... (kode PHP untuk parameter tombol tambah tetap sama) ... */ 
                            $params_for_add_button_prestasi = [];
                            $id_cabor_default_add_prestasi = $filter_id_cabor_get_page_prestasi ?? ($id_cabor_pengurus_utama ?? null);
                            if ($id_cabor_default_add_prestasi) $params_for_add_button_prestasi['id_cabor_default'] = $id_cabor_default_add_prestasi;
                            
                            $nik_atlet_default_add_prestasi = $filter_nik_atlet_get_page_prestasi ?? ($user_role_utama === 'atlet' ? $user_nik : null);
                            if ($nik_atlet_default_add_prestasi) $params_for_add_button_prestasi['nik_atlet_default'] = $nik_atlet_default_add_prestasi;
                            
                            echo !empty($params_for_add_button_prestasi) ? '?' . http_build_query($params_for_add_button_prestasi) : '';
                        ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-award mr-1"></i> Tambah Prestasi Baru
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm table-striped" id="prestasiMasterTable" width="100%" cellspacing="0">
                        <thead>
                             <tr class="text-center">
                                <th style="width: 20px;">No.</th>
                                <th>Nama Atlet</th>
                                <th>NIK</th>
                                <th>Cabor</th>
                                <th>Nama Kejuaraan</th>
                                <th>Tingkat</th>
                                <th>Tahun</th>
                                <th>Peringkat/Medali</th>
                                <th style="width: 15%;">Kelengkapan Data</th>
                                <th style="width: 140px;">Status</th>
                                <th style="width: 180px;" class="text-center no-export">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($daftar_prestasi_processed)): ?>
                                <?php $nomor_prestasi = 1; ?>
                                <?php foreach ($daftar_prestasi_processed as $prestasi_item_data): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $nomor_prestasi++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($prestasi_item_data['nama_atlet']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($prestasi_item_data['nik_atlet']); ?></td>
                                        <td><?php echo htmlspecialchars($prestasi_item_data['nama_cabor']); ?></td>
                                        <td><?php echo htmlspecialchars($prestasi_item_data['nama_kejuaraan']); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars(ucfirst($prestasi_item_data['tingkat_kejuaraan'] ?? '-')); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($prestasi_item_data['tahun_perolehan'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($prestasi_item_data['medali_peringkat'] ?? '-'); ?></td>
                                        <td> 
                                            <div class="progress progress-xs" title="<?php echo $prestasi_item_data['progress_kelengkapan_prestasi']; ?>% Data Lengkap">
                                                <div class="progress-bar <?php echo htmlspecialchars($prestasi_item_data['progress_color_prestasi']); ?>" role="progressbar" style="width: <?php echo $prestasi_item_data['progress_kelengkapan_prestasi']; ?>%" aria-valuenow="<?php echo $prestasi_item_data['progress_kelengkapan_prestasi']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <small class="text-muted d-block text-center"><?php echo $prestasi_item_data['progress_kelengkapan_prestasi']; ?>%</small>
                                        </td>
                                        
                                        
                                        <td class="text-center">
                                            <?php
                                            // ... (Logika badge status tetap sama) ...
                                            $status_text_item_prestasi = ucfirst(str_replace('_', ' ', $prestasi_item_data['status_approval'] ?? 'N/A'));
                                            $status_badge_item_prestasi = 'secondary';
                                            $alasan_penolakan_prestasi_combined = '';

                                            if ($prestasi_item_data['status_approval'] == 'disetujui_admin') { $status_badge_item_prestasi = 'success'; }
                                            elseif ($prestasi_item_data['status_approval'] == 'ditolak_pengcab') { 
                                                $status_badge_item_prestasi = 'danger'; 
                                                $alasan_penolakan_prestasi_combined = $prestasi_item_data['alasan_penolakan_pengcab'] ?? '';
                                            }
                                            elseif ($prestasi_item_data['status_approval'] == 'ditolak_admin') { 
                                                $status_badge_item_prestasi = 'danger'; 
                                                $alasan_penolakan_prestasi_combined = $prestasi_item_data['alasan_penolakan_admin'] ?? '';
                                            }
                                            elseif (in_array($prestasi_item_data['status_approval'], ['pending', 'disetujui_pengcab', 'revisi'])) { 
                                                $status_badge_item_prestasi = 'warning'; 
                                            }
                                            echo "<span class='badge badge-{$status_badge_item_prestasi} p-1'>{$status_text_item_prestasi}</span>";
                                            if (!empty($alasan_penolakan_prestasi_combined)): ?>
                                                <i class="fas fa-info-circle ml-1 text-danger" data-toggle="tooltip" data-placement="top" title="Alasan: <?php echo htmlspecialchars($alasan_penolakan_prestasi_combined); ?>"></i>
                                            <?php endif; ?>
                                        </td>
                                        
                                        
                                        <td class="text-center" style="white-space: nowrap; vertical-align: middle;">
                                            <?php 
                                            $tombol_aksi_html_prestasi = '';
                                            $id_prestasi_item = $prestasi_item_data['id_prestasi'];
                                            $nama_kejuaraan_item = htmlspecialchars(addslashes($prestasi_item_data['nama_kejuaraan']));
                                            $nama_atlet_item = htmlspecialchars(addslashes($prestasi_item_data['nama_atlet']));
                                            $status_prestasi_item = $prestasi_item_data['status_approval'];
                                            $id_cabor_prestasi_item = $prestasi_item_data['prestasi_id_cabor'];
                                            $nik_atlet_prestasi_item = $prestasi_item_data['nik_atlet'];
                                        
                                            // 1. Tombol Detail (Asumsi detail_prestasi.php ada dan berfungsi)
                                            // Jika tidak ada detail_prestasi.php, tombol ini bisa dihilangkan atau diarahkan ke edit.
                                            // Untuk sekarang, kita asumsikan ada.
                                            $tombol_aksi_html_prestasi .= '<a href="detail_prestasi.php?id_prestasi=' . $id_prestasi_item . '" class="btn btn-info btn-xs mr-1" title="Detail Prestasi"><i class="fas fa-eye"></i></a>';
                                            
                                            // 2. Tombol Edit
                                            $can_edit_this_specific_prestasi = false;
                                            if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) { 
                                                $can_edit_this_specific_prestasi = true; 
                                            } elseif ($user_role_utama == 'pengurus_cabor' && isset($id_cabor_pengurus_utama) && $id_cabor_pengurus_utama == $id_cabor_prestasi_item) { 
                                                $can_edit_this_specific_prestasi = true; 
                                            } elseif ($user_role_utama == 'atlet' && $user_nik == $nik_atlet_prestasi_item && in_array($status_prestasi_item, ['pending', 'revisi', 'ditolak_pengcab', 'ditolak_admin'])) {
                                                 $can_edit_this_specific_prestasi = true;
                                            }
                                            
                                            if ($can_edit_this_specific_prestasi) { 
                                                $tombol_aksi_html_prestasi .= '<a href="edit_prestasi.php?id_prestasi=' . $id_prestasi_item . '" class="btn btn-warning btn-xs mr-1" title="Edit Prestasi"><i class="fas fa-edit"></i></a>'; 
                                            }
                                        
                                            // 3. Tombol Aksi Cepat untuk Pengurus Cabor
                                            if ($user_role_utama == 'pengurus_cabor' && $id_cabor_pengurus_utama == $id_cabor_prestasi_item) {
                                                if ($status_prestasi_item === 'pending') {
                                                    $tombol_aksi_html_prestasi .= '<button type="button" class="btn btn-success btn-xs mr-1" title="Setujui (Pengcab)" onclick="window.handlePrestasiApproval(\'' . $id_prestasi_item . '\', \'' . $nama_kejuaraan_item . '\', \'disetujui_pengcab\', \'pengcab\')"><i class="fas fa-check-circle"></i></button>';
                                                    $tombol_aksi_html_prestasi .= '<button type="button" class="btn btn-danger btn-xs mr-1" title="Tolak (Pengcab)" onclick="window.handlePrestasiApproval(\'' . $id_prestasi_item . '\', \'' . $nama_kejuaraan_item . '\', \'ditolak_pengcab\', \'pengcab\')"><i class="fas fa-times-circle"></i></button>';
                                                }
                                                // Pengurus Cabor mungkin bisa meminta revisi kembali ke Atlet jika statusnya ditolak_admin? (Opsional, jika alurnya begitu)
                                                // if ($status_prestasi_item === 'ditolak_admin') {
                                                //     $tombol_aksi_html_prestasi .= '<button type="button" class="btn btn-secondary btn-xs mr-1" title="Minta Revisi ke Atlet" onclick="window.handlePrestasiApproval(\'' . $id_prestasi_item . '\', \'' . $nama_kejuaraan_item . '\', \'revisi\', \'pengcab_ke_atlet\')"><i class="fas fa-undo"></i></button>';
                                                // }
                                            }
                                        
                                            // 4. Tombol Aksi Cepat untuk Admin KONI / Super Admin
                                            if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
                                                // Tombol Setujui Admin Final
                                                if (in_array($status_prestasi_item, ['disetujui_pengcab', 'pending', 'revisi'])) { // Admin bisa approve jika sudah diapprove pengcab, masih pending, atau statusnya revisi
                                                    $tombol_aksi_html_prestasi .= '<button type="button" class="btn btn-primary btn-xs mr-1" title="Setujui Final (Admin)" onclick="window.handlePrestasiApproval(\'' . $id_prestasi_item . '\', \'' . $nama_kejuaraan_item . '\', \'disetujui_admin\', \'admin\')"><i class="fas fa-user-check"></i></button>';
                                                }
                                                // Tombol Tolak Admin Final
                                                if (!in_array($status_prestasi_item, ['ditolak_admin'])) { // Bisa tolak selama belum final ditolak admin
                                                    $tombol_aksi_html_prestasi .= '<button type="button" class="btn btn-danger btn-xs mr-1" title="Tolak Final (Admin)" onclick="window.handlePrestasiApproval(\'' . $id_prestasi_item . '\', \'' . $nama_kejuaraan_item . '\', \'ditolak_admin\', \'admin\')"><i class="fas fa-user-times"></i></button>';
                                                }
                                                // Tombol Minta Revisi (ke Pengcab/Atlet)
                                                if ($status_prestasi_item !== 'revisi' && !in_array($status_prestasi_item, ['ditolak_admin', 'ditolak_pengcab'])) { // Jangan minta revisi jika sudah revisi atau sudah ditolak final
                                                    $tombol_aksi_html_prestasi .= '<button type="button" class="btn btn-secondary btn-xs mr-1" title="Minta Revisi" onclick="window.handlePrestasiApproval(\'' . $id_prestasi_item . '\', \'' . $nama_kejuaraan_item . '\', \'revisi\', \'admin\')"><i class="fas fa-undo"></i></button>';
                                                }
                                            }
                                            
                                            // 5. Tombol Hapus (Hanya Super Admin)
                                            if ($user_role_utama === 'super_admin') {
                                                $tombol_aksi_html_prestasi .= '<a href="hapus_prestasi.php?id_prestasi=' . $id_prestasi_item . '" class="btn btn-dark btn-xs" title="Hapus Permanen" onclick="return confirm(\'PERHATIAN! Data prestasi akan dihapus permanen.\\nYakin ingin menghapus prestasi: ' . $nama_kejuaraan_item . ' untuk atlet ' . $nama_atlet_item . '?\');"><i class="fas fa-trash-alt"></i></a>';
                                            }
                                            
                                            // Membersihkan spasi 'mr-1' jika itu yang terakhir
                                            if (substr($tombol_aksi_html_prestasi, -7) === ' mr-1"') { 
                                                $tombol_aksi_html_prestasi = substr($tombol_aksi_html_prestasi, 0, -7) . '"';
                                            } elseif (substr($tombol_aksi_html_prestasi, -6) === ' mr-1 ') { // Jika ada spasi setelahnya
                                                 $tombol_aksi_html_prestasi = rtrim(substr($tombol_aksi_html_prestasi, 0, -6)) . ' ';
                                            }
                                        
                                        
                                            echo !empty(trim($tombol_aksi_html_prestasi)) ? $tombol_aksi_html_prestasi : '<span class="text-muted">-</span>';
                                            ?>
                                        </td>
                                            
                                        
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="11" class="text-center">Belum ada data prestasi yang cocok dengan filter Anda, atau belum ada data prestasi sama sekali.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$id_tabel_untuk_js_prestasi = "prestasiMasterTable";

// PERBAIKAN: Memastikan semua string HTML di dalam JavaScript diapit dengan benar.
$inline_script = "
window.handlePrestasiApproval = function(idPrestasi, namaKejuaraan, newStatus, approvalLevel) {
    let message = ''; 
    let alasanKey = '';
    let promptMessage = '';

    if (newStatus === 'disetujui_pengcab') { message = `Apakah Anda yakin ingin MENYETUJUI prestasi \\\"\${namaKejuaraan}\\\" (oleh Pengcab)?`; }
    else if (newStatus === 'disetujui_admin') { message = `Apakah Anda yakin ingin MENYETUJUI prestasi \\\"\${namaKejuaraan}\\\" (oleh Admin)?`; }
    else if (newStatus === 'ditolak_pengcab') { 
        message = `Apakah Anda yakin ingin MENOLAK prestasi \\\"\${namaKejuaraan}\\\" (oleh Pengcab)?`;
        alasanKey = 'alasan_penolakan_pengcab';
        promptMessage = `Mohon berikan alasan penolakan untuk prestasi \\\"\${namaKejuaraan}\\\" (Pengcab):`;
    } else if (newStatus === 'ditolak_admin') {
        message = `Apakah Anda yakin ingin MENOLAK prestasi \\\"\${namaKejuaraan}\\\" (oleh Admin)?`;
        alasanKey = 'alasan_penolakan_admin';
        promptMessage = `Mohon berikan alasan penolakan untuk prestasi \\\"\${namaKejuaraan}\\\" (Admin):`;
    } else if (newStatus === 'revisi') {
        message = `Apakah Anda yakin ingin meminta REVISI untuk data prestasi \\\"\${namaKejuaraan}\\\"?`;
        alasanKey = (approvalLevel === 'admin') ? 'alasan_penolakan_admin' : 'alasan_penolakan_pengcab';
        promptMessage = `Mohon berikan catatan/alasan untuk revisi data prestasi \\\"\${namaKejuaraan}\\\":`;
    }
    else { console.error('Status approval prestasi tidak dikenal:', newStatus); return false; }

    let alasan = '';
    if (alasanKey) {
        alasan = prompt(promptMessage);
        if (alasan === null) { return false; } 
        if (alasan.trim() === '' && (newStatus.includes('ditolak') || newStatus === 'revisi')) { 
            alert('Alasan/catatan tidak boleh kosong untuk status ini.'); return false; 
        }
    }

    if (confirm(message)) {
        var form = document.createElement('form'); form.method = 'POST'; form.action = 'proses_edit_prestasi.php';
        
        var idInput = document.createElement('input'); idInput.type = 'hidden'; idInput.name = 'id_prestasi'; idInput.value = idPrestasi; form.appendChild(idInput);
        var statusInput = document.createElement('input'); statusInput.type = 'hidden'; statusInput.name = 'status_approval'; statusInput.value = newStatus; form.appendChild(statusInput);
        var quickActionInput = document.createElement('input'); quickActionInput.type = 'hidden'; quickActionInput.name = 'quick_action_approval_prestasi'; quickActionInput.value = '1'; form.appendChild(quickActionInput);
        
        if (alasanKey && alasan.trim() !== '') { 
            var alasanInput = document.createElement('input'); alasanInput.type = 'hidden'; alasanInput.name = alasanKey; alasanInput.value = alasan; form.appendChild(alasanInput); 
        }
        var levelInput = document.createElement('input'); levelInput.type = 'hidden'; levelInput.name = 'approval_level'; levelInput.value = approvalLevel; form.appendChild(levelInput);

        document.body.appendChild(form);
        form.submit();
    }
    return false;
};

$(document).ready(function() {
    if ($.fn.DataTable && $('#" . $id_tabel_untuk_js_prestasi . "').length) { // Periksa DataTable juga
        $('#" . $id_tabel_untuk_js_prestasi . "').DataTable({
            \"responsive\": true, \"lengthChange\": true, \"autoWidth\": false,
            \"buttons\": [
                { extend: 'copy', text: '<i class=\"fas fa-copy\"></i> Salin', className: 'btn-sm btn-default', titleAttr: 'Salin', exportOptions: { columns: ':visible:not(.no-export)' } },
                { extend: 'csv', text: '<i class=\"fas fa-file-csv\"></i> CSV', className: 'btn-sm btn-default', titleAttr: 'CSV', exportOptions: { columns: ':visible:not(.no-export)' } },
                { extend: 'excel', text: '<i class=\"fas fa-file-excel\"></i> Excel', className: 'btn-sm btn-default', titleAttr: 'Excel', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Prestasi Atlet' },
                { extend: 'pdf', text: '<i class=\"fas fa-file-pdf\"></i> PDF', className: 'btn-sm btn-default', titleAttr: 'PDF', orientation: 'landscape', pageSize: 'LEGAL', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Prestasi Atlet' },
                { extend: 'print', text: '<i class=\"fas fa-print\"></i> Cetak', className: 'btn-sm btn-default', titleAttr: 'Cetak', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Prestasi Atlet' },
                { extend: 'colvis', text: '<i class=\"fas fa-columns\"></i> Kolom', className: 'btn-sm btn-default', titleAttr: 'Kolom' }
            ],
            \"language\": {
                \"search\": \"\", 
                \"searchPlaceholder\": \"Ketik untuk mencari prestasi...\",
                \"lengthMenu\": \"Tampilkan _MENU_ prestasi\", 
                \"info\": \"Menampilkan _START_ s/d _END_ dari _TOTAL_ prestasi\",
                \"infoEmpty\": \"Tidak ada prestasi tersedia\", 
                \"infoFiltered\": \"(difilter dari _MAX_ total prestasi)\",
                \"zeroRecords\": \"Tidak ada data prestasi yang cocok\",
                \"paginate\": { 
                    \"first\":    \"<i class='fas fa-angle-double-left'></i>\",
                    \"last\":     \"<i class='fas fa-angle-double-right'></i>\",
                    \"next\":     \"<i class='fas fa-angle-right'></i>\",
                    \"previous\": \"<i class='fas fa-angle-left'></i>\"
                },
                \"buttons\": { 
                    \"copyTitle\": 'Data Disalin', 
                    \"copySuccess\": { \"_\": '%d baris disalin', \"1\": '1 baris disalin' }, 
                    \"colvis\": 'Tampilkan Kolom'
                }
            },
            \"order\": [[6, 'desc'], [1, 'asc']], // Order by Tahun (DESC), lalu Nama Atlet (ASC) - Indeks kolom disesuaikan
            \"columnDefs\": [ 
                { \"orderable\": false, \"targets\": [0, 8, 9, 10] }, 
                { \"searchable\": false, \"targets\": [0, 8, 9] } 
            ],
            \"dom\":  \"<'row'<'col-sm-12 col-md-3'l><'col-sm-12 col-md-6 text-center'B><'col-sm-12 col-md-3'f>>\" +
                      \"<'row'<'col-sm-12'tr>>\" +
                      \"<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>\",
            \"initComplete\": function(settings, json) {
                $('[data-toggle=\"tooltip\"]').tooltip();
                $('#" . $id_tabel_untuk_js_prestasi . "_filter input').css('width', '200px').addClass('form-control-sm');
            }
        });
    } else {
        console.error('DataTables atau elemen tabel #" . $id_tabel_untuk_js_prestasi . " tidak ditemukan.');
    }

    if (typeof $.fn.select2 === 'function' && $('#filter_nik_atlet_prestasi_page').length) {
        $('#filter_nik_atlet_prestasi_page').select2({
            theme: 'bootstrap4',
            placeholder: 'Semua Atlet',
            allowClear: true
        });
    }
});
";

require_once(__DIR__ . '/../../core/footer.php');
?>