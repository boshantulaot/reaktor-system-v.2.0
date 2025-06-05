<?php
// File: reaktorsystem/modules/wasit/daftar_wasit.php

$page_title = "Manajemen Wasit";

// --- Definisi Aset CSS & JS (mengikuti standar atlet/pelatih) ---
$additional_css = [
    'assets/adminlte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css',
    'assets/adminlte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css',
    'assets/adminlte/plugins/datatables-buttons/css/buttons.bootstrap4.min.css'
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
    'assets/adminlte/plugins/datatables-buttons/js/buttons.colVis.min.js'
];

require_once(__DIR__ . '/../../core/header.php');

// Pengecekan sesi & konfigurasi yang lebih robus
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

// Logika untuk menentukan apakah pengguna bisa menambah wasit
$id_cabor_filter_for_pengurus_wasit = $id_cabor_pengurus_utama ?? null;
$can_add_wasit = false;
if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) { $can_add_wasit = true; }
elseif ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_filter_for_pengurus_wasit)) { $can_add_wasit = true; }

$daftar_wasit_processed = [];
$cabang_olahraga_list_filter_wasit = [];

// Filter GET
$filter_id_cabor_get_page_wasit = isset($_GET['id_cabor']) && filter_var($_GET['id_cabor'], FILTER_VALIDATE_INT) && (int)$_GET['id_cabor'] > 0 ? (int)$_GET['id_cabor'] : null;
$filter_status_approval_get_page_wasit = isset($_GET['status_approval']) ? trim($_GET['status_approval']) : null;

try {
    // Ambil daftar cabor untuk filter jika pengguna adalah admin
    if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
        $stmt_cabor_filter_query_wasit = $pdo->query("SELECT id_cabor, nama_cabor FROM cabang_olahraga ORDER BY nama_cabor ASC");
        if($stmt_cabor_filter_query_wasit){
            $cabang_olahraga_list_filter_wasit = $stmt_cabor_filter_query_wasit->fetchAll(PDO::FETCH_ASSOC);
        } else {
            error_log("Daftar Wasit: Gagal mengambil data cabang_olahraga untuk filter dropdown.");
        }
    }

    // Logika pengambilan data wasit
    $sql_wasit = "SELECT w.id_wasit, p.nama_lengkap, p.nik, p.jenis_kelamin, p.tanggal_lahir,
                         co.nama_cabor, co.id_cabor AS wasit_id_cabor, 
                         w.nomor_lisensi, w.status_approval, w.alasan_penolakan,
                         w.path_file_lisensi, w.foto_wasit, w.ktp_path, w.kk_path
                  FROM wasit w
                  JOIN pengguna p ON w.nik = p.nik
                  JOIN cabang_olahraga co ON w.id_cabor = co.id_cabor";
    $conditions_wasit_sql = []; $params_wasit_sql = [];

    // Filter untuk pengurus cabor
    if ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_filter_for_pengurus_wasit)) {
        $conditions_wasit_sql[] = "w.id_cabor = :user_id_cabor_role_wasit"; 
        $params_wasit_sql[':user_id_cabor_role_wasit'] = $id_cabor_filter_for_pengurus_wasit;
        if ($filter_id_cabor_get_page_wasit !== null && $filter_id_cabor_get_page_wasit != $id_cabor_filter_for_pengurus_wasit) {
            $filter_id_cabor_get_page_wasit = $id_cabor_filter_for_pengurus_wasit; 
        } elseif ($filter_id_cabor_get_page_wasit === null) {
            $filter_id_cabor_get_page_wasit = $id_cabor_filter_for_pengurus_wasit;
        }
    }

    // Terapkan filter GET Cabor
    if ($filter_id_cabor_get_page_wasit !== null) {
        if (!isset($params_wasit_sql[':user_id_cabor_role_wasit']) || $params_wasit_sql[':user_id_cabor_role_wasit'] != $filter_id_cabor_get_page_wasit) {
            $conditions_wasit_sql[] = "w.id_cabor = :filter_id_cabor_wasit_param";
            $params_wasit_sql[':filter_id_cabor_wasit_param'] = $filter_id_cabor_get_page_wasit;
        }
    }
    
    // Terapkan filter GET Status Approval
    $allowed_statuses_wasit = ['pending', 'disetujui', 'ditolak', 'revisi']; // Sesuaikan jika status wasit berbeda
    if ($filter_status_approval_get_page_wasit !== null && in_array($filter_status_approval_get_page_wasit, $allowed_statuses_wasit)) {
        $conditions_wasit_sql[] = "w.status_approval = :filter_status_approval_param_w";
        $params_wasit_sql[':filter_status_approval_param_w'] = $filter_status_approval_get_page_wasit;
    }

    if (!empty($conditions_wasit_sql)) { $sql_wasit .= " WHERE " . implode(" AND ", $conditions_wasit_sql); }
    $sql_wasit .= " ORDER BY p.nama_lengkap ASC";

    $stmt_wasit = $pdo->prepare($sql_wasit); 
    $stmt_wasit->execute($params_wasit_sql); 
    $daftar_wasit_raw = $stmt_wasit->fetchAll(PDO::FETCH_ASSOC);

    // Pemrosesan tambahan (kelengkapan berkas untuk wasit)
    $doc_root_w = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
    $base_path_for_file_check_w = rtrim($app_base_path, '/');

    foreach ($daftar_wasit_raw as $wasit_item_raw) {
        $fields_penting_wasit = ['nik', 'id_cabor', 'nomor_lisensi', 'path_file_lisensi', 'foto_wasit', 'ktp_path', 'kk_path'];
        $total_fields_dihitung_wasit = count($fields_penting_wasit);
        $fields_terisi_aktual_wasit = 0;

        if (!empty(trim($wasit_item_raw['nik'] ?? ''))) $fields_terisi_aktual_wasit++;
        if (!empty($wasit_item_raw['wasit_id_cabor'])) $fields_terisi_aktual_wasit++;
        if (!empty(trim($wasit_item_raw['nomor_lisensi'] ?? ''))) $fields_terisi_aktual_wasit++;
        
        if (!empty($wasit_item_raw['path_file_lisensi'])) { $f_path = $doc_root_w . $base_path_for_file_check_w . '/' . ltrim($wasit_item_raw['path_file_lisensi'], '/'); if (file_exists(preg_replace('/\/+/', '/', $f_path))) { $fields_terisi_aktual_wasit++; } }
        if (!empty($wasit_item_raw['foto_wasit'])) { $f_path = $doc_root_w . $base_path_for_file_check_w . '/' . ltrim($wasit_item_raw['foto_wasit'], '/'); if (file_exists(preg_replace('/\/+/', '/', $f_path))) { $fields_terisi_aktual_wasit++; } }
        if (!empty($wasit_item_raw['ktp_path'])) { $f_path = $doc_root_w . $base_path_for_file_check_w . '/' . ltrim($wasit_item_raw['ktp_path'], '/'); if (file_exists(preg_replace('/\/+/', '/', $f_path))) { $fields_terisi_aktual_wasit++; } }
        if (!empty($wasit_item_raw['kk_path'])) { $f_path = $doc_root_w . $base_path_for_file_check_w . '/' . ltrim($wasit_item_raw['kk_path'], '/'); if (file_exists(preg_replace('/\/+/', '/', $f_path))) { $fields_terisi_aktual_wasit++; } }

        $progress_persen_wasit = ($total_fields_dihitung_wasit > 0) ? round(($fields_terisi_aktual_wasit / $total_fields_dihitung_wasit) * 100) : 0;
        $wasit_item_raw['progress_kelengkapan_wasit'] = $progress_persen_wasit;
        if ($progress_persen_wasit < 40) $wasit_item_raw['progress_color_wasit'] = 'bg-danger';
        elseif ($progress_persen_wasit < 75) $wasit_item_raw['progress_color_wasit'] = 'bg-warning';
        else $wasit_item_raw['progress_color_wasit'] = 'bg-success';
        
        $daftar_wasit_processed[] = $wasit_item_raw;
    }

} catch (PDOException $e) { 
    error_log("Error Daftar Wasit: " . $e->getMessage()); 
    $_SESSION['pesan_error_global'] = "Terjadi kesalahan fatal saat memuat data wasit. Silakan hubungi administrator."; 
}
?>

<section class="content">
    <div class="container-fluid">
        <div class="card card-outline card-danger shadow mb-4"> <?php // Warna card-danger untuk wasit ?>
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-flag mr-1"></i> <?php // Ikon yang relevan untuk wasit ?>
                    Data Wasit
                    <?php
                    $nama_cabor_filtered_header_wasit = '';
                    if ($filter_id_cabor_get_page_wasit) {
                        if (in_array($user_role_utama, ['super_admin', 'admin_koni']) && !empty($cabang_olahraga_list_filter_wasit)) {
                            foreach($cabang_olahraga_list_filter_wasit as $cfl_h_item_w){ if($cfl_h_item_w['id_cabor'] == $filter_id_cabor_get_page_wasit){ $nama_cabor_filtered_header_wasit = $cfl_h_item_w['nama_cabor']; break; } }
                        } elseif ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_filter_for_pengurus_wasit)) {
                             if(empty($nama_cabor_pengurus_utama_global_session)) { 
                                $stmt_c_peng_w = $pdo->prepare("SELECT nama_cabor FROM cabang_olahraga WHERE id_cabor = :id"); $stmt_c_peng_w->execute([':id' => $id_cabor_filter_for_pengurus_wasit]);
                                $nama_cabor_pengurus_utama_global_session = $stmt_c_peng_w->fetchColumn();
                             }
                             $nama_cabor_filtered_header_wasit = $nama_cabor_pengurus_utama_global_session;
                        }
                        if(!empty($nama_cabor_filtered_header_wasit)){ echo " <small class='text-muted'>- Cabor: " . htmlspecialchars($nama_cabor_filtered_header_wasit) . "</small>"; }
                    }
                    if ($filter_status_approval_get_page_wasit) {
                         echo " <small class='text-muted'>- Status: " . htmlspecialchars(ucfirst(str_replace('_', ' ', $filter_status_approval_get_page_wasit))) . "</small>";
                    }
                    ?>
                </h3>
                <div class="card-tools d-flex align-items-center">
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="form-inline <?php if ($can_add_wasit) echo 'mr-2'; ?>">
                        <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni']) && !empty($cabang_olahraga_list_filter_wasit)): ?>
                            <label for="filter_id_cabor_wasit_page" class="mr-1 text-sm font-weight-normal">Cabor:</label>
                            <select name="id_cabor" id="filter_id_cabor_wasit_page" class="form-control form-control-sm mr-2" style="max-width: 150px;" onchange="this.form.submit()">
                                <option value="">Semua Cabor</option>
                                <?php foreach ($cabang_olahraga_list_filter_wasit as $cabor_filter_item_page_w): ?>
                                    <option value="<?php echo $cabor_filter_item_page_w['id_cabor']; ?>" <?php echo ($filter_id_cabor_get_page_wasit == $cabor_filter_item_page_w['id_cabor']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cabor_filter_item_page_w['nama_cabor']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif (in_array($user_role_utama, ['super_admin', 'admin_koni']) && empty($cabang_olahraga_list_filter_wasit)): ?>
                            <span class="text-muted text-sm mr-2 font-weight-normal">Filter Cabor N/A.</span>
                        <?php endif; ?>
                        
                        <label for="filter_status_approval_page_wasit" class="mr-1 text-sm font-weight-normal">Status:</label>
                        <select name="status_approval" id="filter_status_approval_page_wasit" class="form-control form-control-sm mr-2" style="max-width: 170px;" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <?php foreach ($allowed_statuses_wasit as $status_opt_w): ?>
                                <option value="<?php echo $status_opt_w; ?>" <?php echo ($filter_status_approval_get_page_wasit == $status_opt_w) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status_opt_w))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($filter_id_cabor_get_page_wasit || $filter_status_approval_get_page_wasit): ?>
                            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-sm btn-outline-secondary">Reset Filter</a>
                        <?php endif; ?>
                    </form>

                    <?php if ($can_add_wasit): ?>
                        <a href="tambah_wasit.php<?php
                            $id_cabor_default_for_add_btn_w = $filter_id_cabor_get_page_wasit ?? ($id_cabor_pengurus_utama ?? null);
                            echo $id_cabor_default_for_add_btn_w ? '?id_cabor_default=' . $id_cabor_default_for_add_btn_w : '';
                        ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-user-plus mr-1"></i> Tambah Wasit Baru
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm table-striped" id="wasitMasterTable" width="100%" cellspacing="0">
                        <thead>
                             <tr class="text-center">
                                <th style="width: 20px;">No.</th>
                                <th style="width: 50px;">Foto</th>
                                <th>Nama Lengkap</th>
                                <th>NIK</th>
                                <th>Cabang Olahraga</th>
                                <th>No. Lisensi</th>
                                <th style="width: 15%;">Kelengkapan Berkas</th>
                                <th style="width: 130px;">Status Approval</th>
                                <th style="width: 180px;" class="text-center no-export">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($daftar_wasit_processed)): ?>
                                <?php $nomor_wasit = 1; ?>
                                <?php foreach ($daftar_wasit_processed as $wasit_item_data): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $nomor_wasit++; ?></td>
                                        <td class="text-center">
                                            <?php 
                                            $foto_display_path_w = !empty($wasit_item_data['foto_wasit']) && file_exists($doc_root_w . $base_path_for_file_check_w . '/' . ltrim($wasit_item_data['foto_wasit'], '/'))
                                                                ? htmlspecialchars(trim($app_base_path, '/') . '/' . ltrim($wasit_item_data['foto_wasit'], '/'))
                                                                : trim($app_base_path, '/') . '/assets/adminlte/dist/img/avatar.png';
                                            ?>
                                            <img src="<?php echo $foto_display_path_w; ?>" alt="Foto <?php echo htmlspecialchars($wasit_item_data['nama_lengkap']); ?>" class="img-circle img-size-32 elevation-1" style="object-fit: cover; width: 32px; height: 32px;">
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($wasit_item_data['nama_lengkap']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($wasit_item_data['nik']); ?></td>
                                        <td><?php echo htmlspecialchars($wasit_item_data['nama_cabor']); ?></td>
                                        <td><?php echo htmlspecialchars($wasit_item_data['nomor_lisensi'] ?? '-'); ?></td>
                                        <td> 
                                            <div class="progress progress-xs" title="<?php echo $wasit_item_data['progress_kelengkapan_wasit']; ?>% Berkas Lengkap">
                                                <div class="progress-bar <?php echo htmlspecialchars($wasit_item_data['progress_color_wasit']); ?>" role="progressbar" style="width: <?php echo $wasit_item_data['progress_kelengkapan_wasit']; ?>%" aria-valuenow="<?php echo $wasit_item_data['progress_kelengkapan_wasit']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <small class="text-muted d-block text-center"><?php echo $wasit_item_data['progress_kelengkapan_wasit']; ?>%</small>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $status_text_item_w = ucfirst(str_replace('_', ' ', $wasit_item_data['status_approval'] ?? 'N/A'));
                                            $status_badge_item_w = 'secondary';
                                            $alasan_penolakan_w = $wasit_item_data['alasan_penolakan'] ?? '';

                                            if ($wasit_item_data['status_approval'] == 'disetujui') { $status_badge_item_w = 'success'; }
                                            elseif ($wasit_item_data['status_approval'] == 'ditolak') { $status_badge_item_w = 'danger'; }
                                            elseif (in_array($wasit_item_data['status_approval'], ['pending', 'revisi'])) { $status_badge_item_w = 'warning'; }
                                            
                                            echo "<span class='badge badge-{$status_badge_item_w} p-1'>{$status_text_item_w}</span>";
                                            if (!empty($alasan_penolakan_w) && ($wasit_item_data['status_approval'] == 'ditolak' || $wasit_item_data['status_approval'] == 'revisi')): ?>
                                                <i class="fas fa-info-circle ml-1 text-danger" data-toggle="tooltip" data-placement="top" title="Alasan: <?php echo htmlspecialchars($alasan_penolakan_w); ?>"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center" style="white-space: nowrap; vertical-align: middle;">
                                            <?php 
                                            $tombol_aksi_html_w = '';
                                            $tombol_aksi_html_w .= '<a href="detail_wasit.php?id_wasit=' . $wasit_item_data['id_wasit'] . '" class="btn btn-info btn-xs mr-1" title="Detail Wasit"><i class="fas fa-eye"></i></a>';
                                            
                                            $can_edit_current_wasit = false;
                                            if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) { $can_edit_current_wasit = true; }
                                            elseif ($user_role_utama == 'pengurus_cabor' && isset($id_cabor_filter_for_pengurus_wasit) && $id_cabor_filter_for_pengurus_wasit == $wasit_item_data['wasit_id_cabor']) { $can_edit_current_wasit = true; }
                                            
                                            if ($can_edit_current_wasit) { 
                                                $tombol_aksi_html_w .= '<a href="edit_wasit.php?id_wasit=' . $wasit_item_data['id_wasit'] . '" class="btn btn-warning btn-xs mr-1" title="Edit Wasit"><i class="fas fa-edit"></i></a>'; 
                                            }

                                            if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
                                                if ($wasit_item_data['status_approval'] === 'pending' || $wasit_item_data['status_approval'] === 'revisi') {
                                                    $tombol_aksi_html_w .= '<button type="button" class="btn btn-primary btn-xs mr-1" title="Setujui Wasit" onclick="window.handleWasitApproval(\'' . $wasit_item_data['id_wasit'] . '\', \'' . htmlspecialchars(addslashes($wasit_item_data['nama_lengkap'])) . '\', \'disetujui\')"><i class="fas fa-user-check"></i></button>';
                                                }
                                                 if (in_array($wasit_item_data['status_approval'], ['pending','disetujui'])) { 
                                                    $tombol_aksi_html_w .= '<button type="button" class="btn btn-danger btn-xs mr-1" title="Tolak Wasit" onclick="window.handleWasitApproval(\'' . $wasit_item_data['id_wasit'] . '\', \'' . htmlspecialchars(addslashes($wasit_item_data['nama_lengkap'])) . '\', \'ditolak\')"><i class="fas fa-user-times"></i></button>';
                                                    if ($wasit_item_data['status_approval'] !== 'revisi' && $wasit_item_data['status_approval'] !== 'pending'){
                                                      $tombol_aksi_html_w .= '<button type="button" class="btn btn-secondary btn-xs mr-1" title="Minta Revisi" onclick="window.handleWasitApproval(\'' . $wasit_item_data['id_wasit'] . '\', \'' . htmlspecialchars(addslashes($wasit_item_data['nama_lengkap'])) . '\', \'revisi\')"><i class="fas fa-undo"></i></button>';
                                                    }
                                                }
                                            }
                                            
                                            if ($user_role_utama === 'super_admin') {
                                                $tombol_aksi_html_w .= '<a href="hapus_wasit.php?id_wasit=' . $wasit_item_data['id_wasit'] . '" class="btn btn-dark btn-xs" title="Hapus Permanen" onclick="return confirm(\'PERHATIAN! Menghapus wasit ini bersifat permanen.\\nApakah Anda yakin ingin menghapus wasit ' . htmlspecialchars(addslashes($wasit_item_data['nama_lengkap'])) . '?\');"><i class="fas fa-trash-alt"></i></a>';
                                            }
                                            
                                            if (substr($tombol_aksi_html_w, -7) === ' mr-1"') { $tombol_aksi_html_w = substr($tombol_aksi_html_w, 0, -7) . '"';}
                                            echo !empty(trim($tombol_aksi_html_w)) ? $tombol_aksi_html_w : '<span class="text-muted">-</span>';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9" class="text-center">Belum ada data wasit yang cocok dengan filter Anda, atau belum ada data wasit sama sekali.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$id_tabel_untuk_js_wasit = "wasitMasterTable";

$inline_script = "
window.handleWasitApproval = function(idWasit, namaWasit, newStatus) {
    let message = ''; 
    let alasanKey = 'alasan_penolakan'; // Untuk wasit, umumnya satu field alasan
    let promptMessage = '';

    if (newStatus === 'disetujui') { message = `Apakah Anda yakin ingin MENYETUJUI pendaftaran wasit \\\"\${namaWasit}\\\"?`; }
    else if (newStatus === 'ditolak') { 
        message = `Apakah Anda yakin ingin MENOLAK pendaftaran wasit \\\"\${namaWasit}\\\"?`;
        promptMessage = `Mohon berikan alasan penolakan untuk wasit \\\"\${namaWasit}\\\":`;
    } else if (newStatus === 'revisi') {
        message = `Apakah Anda yakin ingin meminta REVISI untuk data wasit \\\"\${namaWasit}\\\"?`;
        promptMessage = `Mohon berikan catatan/alasan untuk revisi data wasit \\\"\${namaWasit}\\\":`;
    }
    else { console.error('Status approval wasit tidak dikenal:', newStatus); return false; }

    let alasan = '';
    if (newStatus === 'ditolak' || newStatus === 'revisi') {
        alasan = prompt(promptMessage);
        if (alasan === null) { return false; } 
        if (alasan.trim() === '') { 
            alert('Alasan/catatan tidak boleh kosong untuk status ini.'); return false; 
        }
    }

    if (confirm(message)) {
        var form = document.createElement('form'); form.method = 'POST'; form.action = 'proses_edit_wasit.php'; // Akan diarahkan ke proses_edit_wasit.php
        
        var idInput = document.createElement('input'); idInput.type = 'hidden'; idInput.name = 'id_wasit'; idInput.value = idWasit; form.appendChild(idInput);
        var statusInput = document.createElement('input'); statusInput.type = 'hidden'; statusInput.name = 'status_approval'; statusInput.value = newStatus; form.appendChild(statusInput);
        var quickActionInput = document.createElement('input'); quickActionInput.type = 'hidden'; quickActionInput.name = 'quick_action_approval_wasit'; quickActionInput.value = '1'; form.appendChild(quickActionInput);
        
        if ((newStatus === 'ditolak' || newStatus === 'revisi') && alasan.trim() !== '') { 
            var alasanInput = document.createElement('input'); alasanInput.type = 'hidden'; alasanInput.name = alasanKey; alasanInput.value = alasan; form.appendChild(alasanInput); 
        }
        
        document.body.appendChild(form);
        form.submit();
    }
    return false;
};

$(document).ready(function() {
    if ($('#" . $id_tabel_untuk_js_wasit . "').length) {
        $('#" . $id_tabel_untuk_js_wasit . "').DataTable({
            \"responsive\": true, \"lengthChange\": true, \"autoWidth\": false,
            \"buttons\": [
                { extend: 'copy', text: '<i class=\"fas fa-copy\"></i> Salin', className: 'btn-sm btn-default', titleAttr: 'Salin', exportOptions: { columns: ':visible:not(.no-export)' } },
                { extend: 'csv', text: '<i class=\"fas fa-file-csv\"></i> CSV', className: 'btn-sm btn-default', titleAttr: 'CSV', exportOptions: { columns: ':visible:not(.no-export)' } },
                { extend: 'excel', text: '<i class=\"fas fa-file-excel\"></i> Excel', className: 'btn-sm btn-default', titleAttr: 'Excel', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Wasit' },
                { extend: 'pdf', text: '<i class=\"fas fa-file-pdf\"></i> PDF', className: 'btn-sm btn-default', titleAttr: 'PDF', orientation: 'landscape', pageSize: 'LEGAL', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Wasit' },
                { extend: 'print', text: '<i class=\"fas fa-print\"></i> Cetak', className: 'btn-sm btn-default', titleAttr: 'Cetak', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Wasit' },
                { extend: 'colvis', text: '<i class=\"fas fa-columns\"></i> Kolom', className: 'btn-sm btn-default', titleAttr: 'Kolom' }
            ],
            \"language\": {
                \"search\": \"\", \"searchPlaceholder\": \"Ketik untuk mencari wasit...\",
                \"lengthMenu\": \"Tampilkan _MENU_ wasit\", \"info\": \"Menampilkan _START_ s/d _END_ dari _TOTAL_ wasit\",
                \"infoEmpty\": \"Tidak ada wasit tersedia\", \"infoFiltered\": \"(difilter dari _MAX_ total wasit)\",
                \"zeroRecords\": \"Tidak ada data wasit yang cocok\",
                \"paginate\": { \"first\": \"<i class='fas fa-angle-double-left'></i>\", \"last\": \"<i class='fas fa-angle-double-right'></i>\", \"next\": \"<i class='fas fa-angle-right'></i>\", \"previous\": \"<i class='fas fa-angle-left'></i>\" },
                \"buttons\": { \"copyTitle\": 'Data Disalin', \"copySuccess\": { _: '%d baris disalin', 1: '1 baris disalin' }, \"colvis\": 'Tampilkan Kolom'}
            },
            \"order\": [[2, 'asc']], 
            \"columnDefs\": [ 
                { \"orderable\": false, \"targets\": [0, 1, 6, 7, 8] }, 
                { \"searchable\": false, \"targets\": [0, 1, 6, 7] }  
            ],
            \"dom\":  \"<'row'<'col-sm-12 col-md-3'l><'col-sm-12 col-md-6 text-center'B><'col-sm-12 col-md-3'f>>\" + \"<'row'<'col-sm-12'tr>>\" + \"<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>\",
            \"initComplete\": function(settings, json) {
                $('[data-toggle=\"tooltip\"]').tooltip();
                $('#" . $id_tabel_untuk_js_wasit . "_filter input').css('width', '200px').addClass('form-control-sm');
            }
        });
    }
});
";

require_once(__DIR__ . '/../../core/footer.php');
?>