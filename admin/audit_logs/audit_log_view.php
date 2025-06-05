<?php
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// File: reaktorsystem/admin/manajemen_data/audit_log_view.php

$page_title = "Lihat Audit Log Sistem";

$additional_css = [
    'assets/adminlte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css',
    'assets/adminlte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css',
    'assets/adminlte/plugins/datatables-buttons/css/buttons.bootstrap4.min.css',
    'assets/adminlte/plugins/daterangepicker/daterangepicker.css',
    'assets/adminlte/plugins/select2/css/select2.min.css',
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/moment/moment.min.js',
    'assets/adminlte/plugins/daterangepicker/daterangepicker.js',
    'assets/adminlte/plugins/select2/js/select2.full.min.js',
    'assets/adminlte/plugins/datatables/jquery.dataTables.min.js',
    'assets/adminlte/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js',
    'assets/adminlte/plugins/datatables-responsive/js/dataTables.responsive.min.js',
    'assets/adminlte/plugins/datatables-buttons/js/dataTables.buttons.min.js',
    'assets/adminlte/plugins/datatables-buttons/js/buttons.bootstrap4.min.js',
    'assets/adminlte/plugins/jszip/jszip.min.js',
    'assets/adminlte/plugins/pdfmake/pdfmake.min.js',
    'assets/adminlte/plugins/pdfmake/vfs_fonts.js',
    'assets/adminlte/plugins/datatables-buttons/js/buttons.html5.min.js',
    'assets/adminlte/plugins/datatables-buttons/js/buttons.print.min.js',
    'assets/adminlte/plugins/datatables-buttons/js/buttons.colVis.min.js',
];

require_once(__DIR__ . '/../../core/header.php'); 

if (!isset($user_nik) || !isset($user_role_utama) || $user_role_utama !== 'super_admin') {
    $_SESSION['pesan_error_global'] = "Akses ditolak. Halaman ini hanya untuk Super Administrator.";
    if (!headers_sent()) {
        header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    } else {
        echo "<div class='alert alert-danger text-center m-3'>Akses ditolak. Harap <a href='" . rtrim($app_base_path, '/') . "/auth/login.php'>login ulang</a> sebagai Super Admin.</div>";
    }
    exit();
}
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['pesan_error_global'] = "Koneksi Database Gagal!";
    echo '<section class="content"><div class="container-fluid"><div class="alert alert-danger text-center"><strong>Koneksi Database Gagal!</strong> Silakan hubungi administrator.</div></div></section>';
    if (file_exists(__DIR__ . '/../../core/footer.php')) { require_once(__DIR__ . '/../../core/footer.php'); }
    exit();
}

// Hapus pesan error lama dari session agar tidak tampil lagi jika sudah resolve
if(isset($_SESSION['pesan_error_global_audit'])) { 
    $pesan_error_sebelumnya = $_SESSION['pesan_error_global_audit']; 
    unset($_SESSION['pesan_error_global_audit']);
}


// --- Logika Filter ---
$filter_daterange = trim($_GET['daterange'] ?? '');
$filter_tanggal_mulai = ''; $filter_tanggal_selesai = '';
if (!empty($filter_daterange)) { 
    $dates_log = explode(' - ', $filter_daterange); 
    if (count($dates_log) == 2) { 
        $filter_tanggal_mulai = trim($dates_log[0]); 
        $filter_tanggal_selesai = trim($dates_log[1]); 
    } 
}
$filter_nik_pelaku_input = trim($_GET['nik_pelaku'] ?? ''); 
$filter_nama_pelaku_input = trim($_GET['nama_pelaku'] ?? ''); 
$filter_aksi_dd_input = trim($_GET['aksi'] ?? ''); 
$filter_tabel_dd_input = trim($_GET['tabel_diubah'] ?? '');

$where_clauses_log_sql_arr = []; 
$params_log_sql_bind_arr = []; 
$query_string_params_log_arr = []; 

if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)) { 
    $where_clauses_log_sql_arr[] = "DATE(al.waktu_aksi) BETWEEN :tanggal_mulai AND :tanggal_selesai"; 
    $params_log_sql_bind_arr[':tanggal_mulai'] = $filter_tanggal_mulai; 
    $params_log_sql_bind_arr[':tanggal_selesai'] = $filter_tanggal_selesai; 
    $query_string_params_log_arr['daterange'] = $filter_daterange; 
}
if (!empty($filter_nik_pelaku_input)) { 
    $where_clauses_log_sql_arr[] = "al.user_nik LIKE :nik_pelaku_log_param"; 
    $params_log_sql_bind_arr[':nik_pelaku_log_param'] = "%" . $filter_nik_pelaku_input . "%"; 
    $query_string_params_log_arr['nik_pelaku'] = $filter_nik_pelaku_input; 
}
if (!empty($filter_nama_pelaku_input)) { 
    $where_clauses_log_sql_arr[] = "p.nama_lengkap LIKE :nama_pelaku_log_param"; 
    $params_log_sql_bind_arr[':nama_pelaku_log_param'] = "%" . $filter_nama_pelaku_input . "%"; 
    $query_string_params_log_arr['nama_pelaku'] = $filter_nama_pelaku_input; 
}
if (!empty($filter_aksi_dd_input)) { 
    $where_clauses_log_sql_arr[] = "al.aksi = :aksi_log_param_dd"; 
    $params_log_sql_bind_arr[':aksi_log_param_dd'] = $filter_aksi_dd_input; 
    $query_string_params_log_arr['aksi'] = $filter_aksi_dd_input; 
}
if (!empty($filter_tabel_dd_input)) { 
    $where_clauses_log_sql_arr[] = "al.tabel_yang_diubah = :tabel_diubah_log_param_dd"; 
    $params_log_sql_bind_arr[':tabel_diubah_log_param_dd'] = $filter_tabel_dd_input; 
    $query_string_params_log_arr['tabel_diubah'] = $filter_tabel_dd_input; 
}
$where_sql_final_log_str = !empty($where_clauses_log_sql_arr) ? " WHERE " . implode(" AND ", $where_clauses_log_sql_arr) : "";

$unique_aksi_options_list_arr = []; $unique_tabel_options_list_arr = [];
try { 
    $stmt_aksi_opts_q = $pdo->query("SELECT DISTINCT aksi FROM audit_log WHERE aksi IS NOT NULL AND aksi != '' ORDER BY aksi ASC");
    if($stmt_aksi_opts_q) $unique_aksi_options_list_arr = $stmt_aksi_opts_q->fetchAll(PDO::FETCH_COLUMN); else $unique_aksi_options_list_arr = [];
    $stmt_tabel_opts_q = $pdo->query("SELECT DISTINCT tabel_yang_diubah FROM audit_log WHERE tabel_yang_diubah IS NOT NULL AND tabel_yang_diubah != '' ORDER BY tabel_yang_diubah ASC");
    if($stmt_tabel_opts_q) $unique_tabel_options_list_arr = $stmt_tabel_opts_q->fetchAll(PDO::FETCH_COLUMN); else $unique_tabel_options_list_arr = [];
} catch (PDOException $e) { error_log("Audit Log View - Gagal ambil opsi filter unik: " . $e->getMessage()); }

$limit_log_page_val = 25; 
$page_log_current_val = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_GET['page'] : 1;
$offset_log_page_val = ($page_log_current_val - 1) * $limit_log_page_val;
$audit_logs_display_data_arr = []; $total_logs_db_count_val = 0;

try {
    $count_sql_query_str = "SELECT COUNT(al.id_log) FROM audit_log al LEFT JOIN pengguna p ON al.user_nik = p.nik " . $where_sql_final_log_str;
    $stmt_total_log_exec_q = $pdo->prepare($count_sql_query_str);
    $stmt_total_log_exec_q->execute($params_log_sql_bind_arr);
    $total_logs_db_count_val = (int) $stmt_total_log_exec_q->fetchColumn();

    if ($total_logs_db_count_val > 0) {
        $sql_fetch_log_data_str = "SELECT al.*, p.nama_lengkap AS nama_pelaku_display, ang.role AS peran_pelaku_display
                    FROM audit_log al
                    LEFT JOIN pengguna p ON al.user_nik = p.nik
                    LEFT JOIN anggota ang ON al.user_nik = ang.nik AND ang.is_verified = 1 " 
            . $where_sql_final_log_str .
            " ORDER BY al.waktu_aksi DESC
                    LIMIT :limit_log_page_param OFFSET :offset_log_page_param";
        
        $stmt_fetch_log_exec_q = $pdo->prepare($sql_fetch_log_data_str);
        
        $execute_params_fetch_arr = $params_log_sql_bind_arr; 
        $execute_params_fetch_arr[':limit_log_page_param'] = $limit_log_page_val; 
        $execute_params_fetch_arr[':offset_log_page_param'] = $offset_log_page_val; 
        
        $stmt_fetch_log_exec_q->execute($execute_params_fetch_arr);
        $audit_logs_display_data_arr = $stmt_fetch_log_exec_q->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) { 
    $_SESSION['pesan_error_global_audit'] = "Terjadi kesalahan saat mengambil data audit log.";
    error_log("Audit Log View - Error DB Fetch: " . $e->getMessage() . " SQL (count): " . $count_sql_query_str . " SQL (fetch): " . ($sql_fetch_log_data_str ?? 'N/A'));
}
$total_pages_display_val = $total_logs_db_count_val > 0 ? ceil($total_logs_db_count_val / $limit_log_page_val) : 0;
$base_pagination_url_display_val = 'audit_log_view.php?' . http_build_query($query_string_params_log_arr) . (empty($query_string_params_log_arr) ? '' : '&');
?>

<style>
    #auditLogTable th,
    #auditLogTable td {
        white-space: nowrap; 
        vertical-align: middle !important; 
        font-size: 0.85rem; 
    }
    #auditLogTable td.wrap-text,
    #auditLogTable th.wrap-text { 
        white-space: normal;
        word-break: break-word;
    }
    #auditLogTable th:nth-child(1) { width: 5%; }  /* ID */
    #auditLogTable th:nth-child(2) { width: 13%; min-width: 140px;} /* Waktu Aksi */
    #auditLogTable th:nth-child(3) { width: 17%; min-width: 180px;} /* Pelaku */
    #auditLogTable th:nth-child(4) { width: 12%; min-width: 130px;} /* Peran */
    #auditLogTable th:nth-child(5) { width: 15%; min-width: 170px;} /* Aksi */
    #auditLogTable th:nth-child(6) { width: 10%; min-width: 100px;} /* Tabel */
    #auditLogTable th:nth-child(7) { width: 8%;  min-width: 70px;}  /* ID Data */
    #auditLogTable th:nth-child(8) { width: 12%; min-width: 200px;} /* Keterangan */
    #auditLogTable th:nth-child(9) { width: 10%; min-width: 110px;} /* Detail */
</style>

<section class="content">
    <div class="container-fluid">
        <?php 
        if (isset($pesan_error_sebelumnya)) { 
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($pesan_error_sebelumnya) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>';
        }
        ?>
        <div class="card card-outline card-primary shadow mb-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history mr-1"></i> <?php echo htmlspecialchars($page_title); ?></h3>
                <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div>
            </div>
            <div class="card-body">
                <form method="get" action="audit_log_view.php" class="mb-3">
                    <div class="row">
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="daterange_filter_log">Rentang Tanggal:</label>
                                <input type="text" name="daterange" id="daterange_filter_log" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_daterange); ?>" placeholder="Pilih rentang...">
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="nik_pelaku_filter_log">NIK Pelaku:</label>
                                <input type="text" name="nik_pelaku" id="nik_pelaku_filter_log" class="form-control form-control-sm" placeholder="Ketik NIK..." value="<?php echo htmlspecialchars($filter_nik_pelaku_input); ?>">
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="nama_pelaku_filter_log">Nama Pelaku:</label>
                                <input type="text" name="nama_pelaku" id="nama_pelaku_filter_log" class="form-control form-control-sm" placeholder="Ketik Nama..." value="<?php echo htmlspecialchars($filter_nama_pelaku_input); ?>">
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="aksi_filter_log_dd">Aksi:</label>
                                <select name="aksi" id="aksi_filter_log_dd" class="form-control custom-select custom-select-sm select2bs4-filter" data-placeholder="Semua Aksi">
                                    <option value=""></option>
                                    <?php foreach ($unique_aksi_options_list_arr as $aksi_opt_item): ?>
                                        <option value="<?php echo htmlspecialchars($aksi_opt_item); ?>" <?php if ($filter_aksi_dd_input == $aksi_opt_item) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($aksi_opt_item); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="tabel_diubah_filter_log_dd">Tabel Diubah:</label>
                                <select name="tabel_diubah" id="tabel_diubah_filter_log_dd" class="form-control custom-select custom-select-sm select2bs4-filter" data-placeholder="Semua Tabel">
                                    <option value=""></option>
                                    <?php foreach ($unique_tabel_options_list_arr as $tabel_opt_item): ?>
                                        <option value="<?php echo htmlspecialchars($tabel_opt_item); ?>" <?php if ($filter_tabel_dd_input == $tabel_opt_item) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($tabel_opt_item); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-12 d-flex align-items-end">
                            <div class="form-group w-100">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Filter</button>
                                    <?php if (!empty($query_string_params_log_arr)): ?>
                                        <a href="audit_log_view.php" class="btn btn-sm btn-secondary ml-1"><i class="fas fa-times"></i> Reset</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                <hr class="mt-0">
                
                <div id="auditLogTable_wrapper_parent" class="mb-2"> <?php // Wrapper untuk elemen DataTables atas (length, buttons) ?>
                    <?php // Info jumlah entri dari server-side akan ditampilkan di bawah jika DataTables info:false ?>
                </div>

                <div class="table-responsive">
                    <table id="auditLogTable" class="table table-bordered table-hover table-sm table-striped" style="width:100%;">
                        <thead>
                            <tr class="text-center">
                                <th style="width: 5%;">ID</th>
                                <th style="width: 13%;">Waktu Aksi</th>
                                <th style="width: 17%;">Pelaku</th>
                                <th style="width: 12%;">Peran</th>
                                <th style="width: 15%;">Aksi</th>
                                <th style="width: 10%;">Tabel</th>
                                <th style="width: 8%;">ID Data</th>
                                <th class="wrap-text" style="width: 12%;">Keterangan</th>
                                <th style="width: 10%;" class="no-export">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($audit_logs_display_data_arr)): ?>
                                <?php foreach ($audit_logs_display_data_arr as $log_item_row_data): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $log_item_row_data['id_log']; ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars(date('d M Y, H:i:s', strtotime($log_item_row_data['waktu_aksi']))); ?></td>
                                        <td>
                                            <?php
                                            $nama_pelaku_disp = htmlspecialchars($log_item_row_data['nama_pelaku_display'] ?? $log_item_row_data['user_nik']);
                                            $peran_pelaku_log_item = $log_item_row_data['peran_pelaku_display'] ?? '';
                                            $text_color_pelaku_final = '';
                                            if ($peran_pelaku_log_item === 'super_admin') $text_color_pelaku_final = 'text-primary font-weight-bold';
                                            elseif ($peran_pelaku_log_item === 'admin_koni') $text_color_pelaku_final = 'text-indigo';
                                            elseif ($peran_pelaku_log_item === 'pengurus_cabor') $text_color_pelaku_final = 'text-success';
                                            elseif (in_array($peran_pelaku_log_item, ['atlet', 'Atlet'])) $text_color_pelaku_final = 'text-info';
                                            elseif (in_array($peran_pelaku_log_item, ['pelatih', 'Pelatih'])) $text_color_pelaku_final = 'text-purple';
                                            elseif (in_array($peran_pelaku_log_item, ['wasit', 'Wasit'])) $text_color_pelaku_final = 'text-danger';
                                            echo '<span class="' . $text_color_pelaku_final . '">' . $nama_pelaku_disp . '</span>';
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                            if (!empty($peran_pelaku_log_item)) {
                                                $badge_peran_color_final = 'secondary';
                                                if ($peran_pelaku_log_item === 'super_admin') $badge_peran_color_final = 'primary';
                                                elseif ($peran_pelaku_log_item === 'admin_koni') $badge_peran_color_final = 'indigo';
                                                elseif ($peran_pelaku_log_item === 'pengurus_cabor') $badge_peran_color_final = 'success';
                                                elseif (in_array($peran_pelaku_log_item, ['atlet', 'Atlet'])) $badge_peran_color_final = 'info';
                                                elseif (in_array($peran_pelaku_log_item, ['pelatih', 'Pelatih'])) $badge_peran_color_final = 'purple';
                                                elseif (in_array($peran_pelaku_log_item, ['wasit', 'Wasit'])) $badge_peran_color_final = 'danger';
                                                echo '<span class="badge bg-' . $badge_peran_color_final . '">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $peran_pelaku_log_item))) . '</span>';
                                            } else { echo '-'; }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                             $badge_color_act = 'light'; $aksi_text_act = strtoupper($log_item_row_data['aksi'] ?? ''); 
                                             if (strpos($aksi_text_act, 'TAMBAH') !== false || strpos($aksi_text_act, 'PENGAJUAN') !== false) $badge_color_act = 'success';
                                             elseif (strpos($aksi_text_act, 'EDIT') !== false || strpos($aksi_text_act, 'UPDATE') !== false || strpos($aksi_text_act, 'UBAH') !== false) $badge_color_act = 'warning';
                                             elseif (strpos($aksi_text_act, 'HAPUS') !== false || strpos($aksi_text_act, 'DELETE') !== false) $badge_color_act = 'danger';
                                             elseif (strpos($aksi_text_act, 'LOGIN') !== false) $badge_color_act = 'info';
                                             elseif (strpos($aksi_text_act, 'APPROVE') !== false || strpos($aksi_text_act, 'SETUJUI') !== false || strpos($aksi_text_act, 'VERIFIKASI') !== false) $badge_color_act = 'primary';
                                             elseif (strpos($aksi_text_act, 'TOLAK') !== false || strpos($aksi_text_act, 'REJECT') !== false) $badge_color_act = 'danger';
                                             elseif (strpos($aksi_text_act, 'REVISI') !== false) $badge_color_act = 'secondary';
                                             echo '<span class="badge bg-'.$badge_color_act.'">'.htmlspecialchars($log_item_row_data['aksi']).'</span>';
                                            ?>
                                        </td>
                                        <td class="text-center"><?php echo htmlspecialchars($log_item_row_data['tabel_yang_diubah'] ?? '-'); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($log_item_row_data['id_data_yang_diubah'] ?? '-'); ?></td>
                                        <td class="wrap-text"><?php echo nl2br(htmlspecialchars($log_item_row_data['keterangan'] ?? '-')); ?></td>
                                        <td class="text-center">
                                            <?php if (!empty($log_item_row_data['data_lama'])): ?>
                                                <button type="button" class="btn btn-xs btn-outline-secondary mb-1 w-100" data-toggle="modal" data-target="#modalDataLama_<?php echo $log_item_row_data['id_log']; ?>">Data Lama</button>
                                                <div class="modal fade" id="modalDataLama_<?php echo $log_item_row_data['id_log']; ?>" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Data Lama (Log ID: <?php echo $log_item_row_data['id_log']; ?>)</h5><button type="button" class="close" data-dismiss="modal"><span>×</span></button></div><div class="modal-body"><pre style="white-space: pre-wrap; word-break: break-all;"><?php echo htmlspecialchars(json_encode(json_decode($log_item_row_data['data_lama']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre></div></div></div></div>
                                            <?php endif; ?>
                                            <?php if (!empty($log_item_row_data['data_baru'])): ?>
                                                <button type="button" class="btn btn-xs btn-outline-info w-100" data-toggle="modal" data-target="#modalDataBaru_<?php echo $log_item_row_data['id_log']; ?>">Data Baru</button>
                                                 <div class="modal fade" id="modalDataBaru_<?php echo $log_item_row_data['id_log']; ?>" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Data Baru (Log ID: <?php echo $log_item_row_data['id_log']; ?>)</h5><button type="button" class="close" data-dismiss="modal"><span>×</span></button></div><div class="modal-body"><pre style="white-space: pre-wrap; word-break: break-all;"><?php echo htmlspecialchars(json_encode(json_decode($log_item_row_data['data_baru']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre></div></div></div></div>
                                            <?php endif; ?>
                                             <?php if (empty($log_item_row_data['data_lama']) && empty($log_item_row_data['data_baru'])): echo '-'; endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php elseif ($total_logs_db_count_val > 0 && empty($audit_logs_display_data_arr)): ?>
                                <tr><td colspan="9" class="text-center">Tidak ada data audit log yang cocok dengan filter yang diterapkan untuk halaman ini.</td></tr>
                            <?php else: ?>
                                <tr><td colspan="9" class="text-center">Belum ada data audit log di sistem.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div> 
                
                <?php if ($total_pages_display_val > 1): ?>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div id="auditLogTable_info_server_side" class="text-muted">
                            <?php // Info ini akan digantikan oleh DataTables jika DataTables.info=true ?>
                            Menampilkan <?php echo count($audit_logs_display_data_arr); ?> dari <?php echo $total_logs_db_count_val; ?> total log. (Halaman <?php echo $page_log_current_val; ?> dari <?php echo $total_pages_display_val; ?>)
                        </div>
                        <nav aria-label="Page navigation Log">
                            <ul class="pagination mb-0">
                                <?php if ($page_log_current_val > 1): ?> <li class="page-item"><a class="page-link" href="<?php echo $base_pagination_url_display_val . 'page=' . ($page_log_current_val - 1); ?>">«</a></li> <?php endif; ?>
                                <?php $num_links_pag_val = 2; $start_pag_val = max(1, $page_log_current_val - $num_links_pag_val); $end_pag_val = min($total_pages_display_val, $page_log_current_val + $num_links_pag_val);
                                if ($start_pag_val > 1) echo '<li class="page-item"><a class="page-link" href="'.$base_pagination_url_display_val.'page=1">1</a></li><li class="page-item disabled"><span class="page-link">...</span></li>';
                                for ($i_pag_val = $start_pag_val; $i_pag_val <= $end_pag_val; $i_pag_val++): ?> <li class="page-item <?php if ($i_pag_val == $page_log_current_val) echo 'active'; ?>"><a class="page-link" href="<?php echo $base_pagination_url_display_val . 'page=' . $i_pag_val; ?>"><?php echo $i_pag_val; ?></a></li> <?php endfor; 
                                if ($end_pag_val < $total_pages_display_val) echo '<li class="page-item disabled"><span class="page-link">...</span></li><li class="page-item"><a class="page-link" href="'.$base_pagination_url_display_val.'page='.$total_pages_display_val.'">'.$total_pages_display_val.'</a></li>';
                                if ($page_log_current_val < $total_pages_display_val): ?> <li class="page-item"><a class="page-link" href="<?php echo $base_pagination_url_display_val . 'page=' . ($page_log_current_val + 1); ?>">»</a></li> <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</section>

<?php
$inline_script = "
$(function () {
    $('#daterange_filter_log').daterangepicker({ autoUpdateInput: false, locale: { cancelLabel: 'Clear', applyLabel: 'Terapkan', format: 'YYYY-MM-DD' }});
    $('#daterange_filter_log').on('apply.daterangepicker', function(ev, picker) { $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD')); });
    $('#daterange_filter_log').on('cancel.daterangepicker', function(ev, picker) { $(this).val(''); });
    var initialDateRangeLogVal = '" . htmlspecialchars($filter_daterange) . "';
    if (initialDateRangeLogVal) { var datesLogVal = initialDateRangeLogVal.split(' - '); if (datesLogVal.length === 2) { $('#daterange_filter_log').data('daterangepicker').setStartDate(datesLogVal[0]); $('#daterange_filter_log').data('daterangepicker').setEndDate(datesLogVal[1]); $('#daterange_filter_log').val(initialDateRangeLogVal); } }

    $('.select2bs4-filter').select2({ theme: 'bootstrap4', allowClear: true, placeholder: $(this).data('placeholder') || '-- Pilih --' });

    if ($('#auditLogTable').length && typeof $.fn.DataTable !== 'undefined' && " . json_encode(!empty($audit_logs_display_data_arr)) . " ) {
        var auditTableInstanceDt = $('#auditLogTable').DataTable({
            \"responsive\": false, 
            \"scrollX\": true,    
            \"autoWidth\": false,   
            \"searching\": false, // Filter utama server-side
            \"ordering\": true,   // Biarkan sorting sisi klien jika mau
            
            // --- PENYESUAIAN UNTUK LENGTH MENU & INFO ---
            \"lengthChange\": true, 
            \"lengthMenu\": [ [10, 25, 50, 100, -1], [10, 25, 50, 100, \"Semua\"] ],
            \"pageLength\": " . $limit_log_page_val . ", // Default sesuai PHP
            \"paging\": false,    // Paginasi utama dihandle server-side HTML
            \"info\": true,       // Tampilkan info 'Menampilkan X dari Y' dari DataTables
            // -------------------------------------------
            
            \"buttons\": [
                { extend: 'copy', text: '<i class=\"fas fa-copy\"></i> Salin', className: 'btn-sm', titleAttr: 'Salin', exportOptions: { columns: ':visible:not(.no-export)' } },
                { extend: 'csv', text: '<i class=\"fas fa-file-csv\"></i> CSV', className: 'btn-sm', titleAttr: 'CSV', exportOptions: { columns: ':visible:not(.no-export)' } },
                { extend: 'excel', text: '<i class=\"fas fa-file-excel\"></i> Excel', className: 'btn-sm', titleAttr: 'Excel', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Audit Log Sistem' },
                { extend: 'pdf', text: '<i class=\"fas fa-file-pdf\"></i> PDF', className: 'btn-sm', titleAttr: 'PDF', orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Audit Log Sistem' },
                { extend: 'print', text: '<i class=\"fas fa-print\"></i> Cetak', className: 'btn-sm', titleAttr: 'Cetak', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Audit Log Sistem' },
                { extend: 'colvis', text: '<i class=\"fas fa-columns\"></i> Kolom', className: 'btn-sm', titleAttr: 'Kolom' }
            ],
            \"language\": { 
                \"zeroRecords\": \"Tidak ada data log yang cocok dengan filter yang diterapkan.\",
                \"info\": \"Menampilkan _START_ hingga _END_ dari _TOTAL_ entri log\",
                \"infoEmpty\": \"Menampilkan 0 hingga 0 dari 0 entri log\",
                \"infoFiltered\": \"(difilter dari _MAX_ total entri log)\",
                \"lengthMenu\": \"Tampilkan _MENU_ entri\",
                \"buttons\": { \"copyTitle\": 'Data Disalin', \"copySuccess\": { _: '%d baris disalin', 1: '1 baris disalin' } }
            },
            \"order\": [], // Tidak ada order default client-side, biarkan server-side (waktu_aksi DESC)
            \"columnDefs\": [ 
                { \"orderable\": false, \"targets\": 'no-export' } // Kolom dengan class 'no-export' tidak bisa diorder
            ],
            // PENYESUAIAN DOM DataTables
            \"dom\":  \"<'row'<'col-sm-12 col-md-auto'l><'col-sm-12 col-md'B><'col-sm-12 col-md-auto'>>\" + // l (length) dan B (buttons) di atas
                      \"<'row'<'col-sm-12'tr>>\" + // Tabel
                      \"<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>\" // i (info) dan p (pagination DataTables, akan kosong karena paging:false)
        });
        // Penempatan tombol sedikit berbeda untuk layout ini
        // auditTableInstanceDt.buttons().container().appendTo('#auditLogTable_wrapper .col-md-6:eq(0)');
        // Jika menggunakan DOM di atas, tombol akan otomatis ditempatkan oleh 'B'.
        // Anda bisa menyesuaikan '#auditLogTable_buttons_container' jika ingin penempatan yang lebih spesifik.
        // Jika DOM di atas sudah cukup, baris appendTo manual bisa dihapus atau dikomentari.
    }

    // Handle perubahan length menu DataTables untuk reload halaman dengan parameter baru
    $('#auditLogTable').on('length.dt', function (e, settings, len) {
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('length', len);
        currentUrl.searchParams.set('page', 1); // Selalu kembali ke halaman 1
        window.location.href = currentUrl.toString();
    });
});
";

require_once(__DIR__ . '/../../core/footer.php');
?>