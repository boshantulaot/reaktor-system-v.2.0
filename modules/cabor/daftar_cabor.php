<?php
// File: reaktorsystem/modules/cabor/daftar_cabor.php

$page_title = "Manajemen Cabang Olahraga";

$additional_css = [
    'assets/adminlte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css',
    'assets/adminlte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css',
    'assets/adminlte/plugins/datatables-buttons/css/buttons.bootstrap4.min.css',
    // 'assets/css/reaktor_custom_colors.css' // File CSS kustom Anda untuk pewarnaan modul
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

// Pengecekan sesi & konfigurasi
if (!isset($pdo) || !$pdo instanceof PDO || !isset($user_role_utama) || !isset($user_nik) || !isset($app_base_path) || !defined('APP_PATH_BASE') || !isset($default_avatar_path_relative)) {
    // ... (Error handling jika variabel inti tidak ada, sama seperti sebelumnya) ...
    $_SESSION['pesan_error_global'] = "Error Kritis: Sesi atau konfigurasi inti bermasalah.";
    $fallback_url_dafcbr_err = isset($app_base_path) ? rtrim($app_base_path, '/') . "/dashboard.php" : "/dashboard.php";
    if (!isset($user_login_status) || $user_login_status !== true) {
         $fallback_url_dafcbr_err = isset($app_base_path) ? rtrim($app_base_path, '/') . "/auth/login.php" : "/auth/login.php";
    }
    if (!headers_sent()) { header("Location: " . $fallback_url_dafcbr_err); }
    else { echo "<div class='alert alert-danger text-center m-3'>Error Kritis. <a href='" . htmlspecialchars($fallback_url_dafcbr_err, ENT_QUOTES, 'UTF-8') . "'>Kembali</a>.</div>"; }
    if (file_exists(__DIR__ . '/../../core/footer.php')) { $inline_script = $inline_script ?? ''; require_once(__DIR__ . '/../../core/footer.php'); }
    exit();
}

// Path untuk logo default cabor (jika logo spesifik tidak ada atau tidak valid)
// Ini akan digunakan jika kolom logo_cabor di DB adalah NULL atau pathnya tidak valid
$path_logo_koni_default_relatif = 'assets/uploads/logos/logo_koni.png'; // Sesuai permintaan Anda

$cabang_olahraga_list_final_view = [];
try {
    // Query dioptimalkan untuk mengambil semua data yang dibutuhkan dalam satu kali jalan
    $sql_get_cabor_view = "SELECT 
                                co.id_cabor, co.nama_cabor, co.kode_cabor, 
                                co.ketua_cabor_nik, p_ketua.nama_lengkap AS nama_ketua,
                                co.sekretaris_cabor_nik, p_sek.nama_lengkap AS nama_sekretaris,
                                co.bendahara_cabor_nik, p_ben.nama_lengkap AS nama_bendahara,
                                co.kontak_cabor, co.logo_cabor,
                                co.status_kepengurusan, co.periode_mulai, co.periode_selesai,
                                co.alamat_sekretariat, co.email_cabor, 
                                co.nomor_sk_provinsi, co.tanggal_sk_provinsi, co.path_file_sk_provinsi,
                                (SELECT COUNT(*) FROM klub k WHERE k.id_cabor = co.id_cabor AND k.status_approval_admin = 'disetujui') AS jumlah_klub_aktif,
                                (SELECT COUNT(DISTINCT a.nik) FROM atlet a WHERE a.id_cabor = co.id_cabor AND a.status_approval = 'disetujui') AS jumlah_atlet_unik_aktif
                           FROM cabang_olahraga co 
                           LEFT JOIN pengguna p_ketua ON co.ketua_cabor_nik = p_ketua.nik AND p_ketua.is_approved = 1
                           LEFT JOIN pengguna p_sek ON co.sekretaris_cabor_nik = p_sek.nik AND p_sek.is_approved = 1
                           LEFT JOIN pengguna p_ben ON co.bendahara_cabor_nik = p_ben.nik AND p_ben.is_approved = 1
                           ORDER BY co.nama_cabor ASC";
    $stmt_cabor_list_view = $pdo->query($sql_get_cabor_view);

    if ($stmt_cabor_list_view) {
        while ($cabor_item_db = $stmt_cabor_list_view->fetch(PDO::FETCH_ASSOC)) {
            // Kalkulasi Progress Kelengkapan Data (Anda bisa menambahkan lebih banyak field di sini)
            $fields_untuk_progress = [
                'nama_cabor', 'kode_cabor', 'ketua_cabor_nik', 'sekretaris_cabor_nik', 'bendahara_cabor_nik',
                'alamat_sekretariat', 'kontak_cabor', 'email_cabor', 'logo_cabor',
                'nomor_sk_provinsi', 'tanggal_sk_provinsi', 'path_file_sk_provinsi',
                'periode_mulai', 'periode_selesai', 'status_kepengurusan'
            ];
            $total_fields_progress = count($fields_untuk_progress);
            $fields_terisi_progress = 0;
            foreach ($fields_untuk_progress as $field_prog) {
                $nilai_field_prog = $cabor_item_db[$field_prog] ?? null;
                if ($nilai_field_prog !== null) {
                    if (is_string($nilai_field_prog)) {
                        if (trim($nilai_field_prog) !== '' && strtolower(trim($nilai_field_prog)) !== strtolower(trim($path_logo_koni_default_relatif))) { // Jangan hitung default logo sebagai "terisi penuh"
                            $fields_terisi_progress++;
                        } elseif (trim($nilai_field_prog) !== '' && $field_prog !== 'logo_cabor'){ // Jika bukan logo, hitung jika tidak kosong
                            $fields_terisi_progress++;
                        }
                    } else { $fields_terisi_progress++; } // Untuk non-string seperti tanggal
                }
            }
            $persen_progress_cbr = ($total_fields_progress > 0) ? round(($fields_terisi_progress / $total_fields_progress) * 100) : 0;
            $cabor_item_db['progress_kelengkapan'] = $persen_progress_cbr;
            if ($persen_progress_cbr < 40) { $cabor_item_db['progress_color'] = 'bg-danger'; } 
            elseif ($persen_progress_cbr < 75) { $cabor_item_db['progress_color'] = 'bg-warning'; } 
            else { $cabor_item_db['progress_color'] = 'bg-success'; }

            // Status Periode
            $status_periode_text = "<em>Belum Diatur</em>"; $status_periode_color = "text-muted";
            if (!empty($cabor_item_db['periode_mulai']) && !empty($cabor_item_db['periode_selesai'])) {
                $today = new DateTime();
                $periode_selesai_dt = new DateTime($cabor_item_db['periode_selesai']);
                $diff = $today->diff($periode_selesai_dt);
                
                if ($periode_selesai_dt < $today) {
                    $status_periode_text = "Telah Berakhir"; $status_periode_color = "text-danger font-weight-bold";
                } elseif ($diff->y == 0 && $diff->m <= 3 && $diff->days <= 90) { // Kurang dari atau sama dengan 3 bulan (sekitar 90 hari)
                    $status_periode_text = "Segera Berakhir (" . $diff->days . " hari lagi)"; $status_periode_color = "text-warning font-weight-bold";
                } else {
                    $status_periode_text = "Aktif hingga " . date('d M Y', strtotime($cabor_item_db['periode_selesai'])); $status_periode_color = "text-success";
                }
            }
            $cabor_item_db['status_periode_info'] = ['text' => $status_periode_text, 'color' => $status_periode_color];
            
            $cabang_olahraga_list_final_view[] = $cabor_item_db;
        }
    }
} catch (PDOException $e_list_cbr_final) {  
    error_log("Error Daftar Cabor (Final View): " . $e_list_cbr_final->getMessage()); 
    $_SESSION['pesan_error_global'] = "Terjadi kesalahan saat memuat data cabang olahraga."; 
}
?>

<div class="content-header">
  <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0"><?php echo htmlspecialchars($page_title); ?></h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="<?php echo rtrim($app_base_path, '/'); ?>/dashboard.php">Home</a></li><li class="breadcrumb-item active">Manajemen Cabor</li></ol></div></div></div>
</div>

<section class="content">
    <div class="container-fluid">
        <?php // Tampilkan pesan global jika ada ?>
        <?php if (isset($_SESSION['pesan_sukses_global'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($_SESSION['pesan_sukses_global']); unset($_SESSION['pesan_sukses_global']); ?><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['pesan_error_global'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($_SESSION['pesan_error_global']); unset($_SESSION['pesan_error_global']); ?><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>
        <?php endif; ?>

        <div class="card card-outline card-success shadow mb-4"> <?php // Warna hijau untuk Cabor (sesuai permintaan pewarnaan modul) ?>
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-flag mr-1"></i> Data Cabang Olahraga Terdaftar</h3>
                <div class="card-tools d-flex align-items-center">
                    <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni'])): ?>
                        <a href="tambah_cabor.php" class="btn btn-success btn-sm"><i class="fas fa-plus mr-1"></i> Tambah Cabor Baru</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm table-striped" id="caborMasterTable" width="100%" cellspacing="0">
                        <thead>
                             <tr class="text-center">
                                <th style="width: 20px;">No.</th>
                                <th style="width: 60px;">Logo</th>
                                <th>Nama Cabor</th>
                                <th>Kode</th>
                                <th>Ketua</th>
                                <th>Sekretaris</th>
                                <th>Bendahara</th>
                                <th style="width: 120px;">Status Periode</th>
                                <th style="width: 100px;">Kelengkapan</th>
                                <th style="width: 130px;" class="text-center no-export">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($cabang_olahraga_list_final_view)): $no_cabor_loop = 1; foreach ($cabang_olahraga_list_final_view as $cabor_item_view): ?>
                                <tr>
                                    <td class="text-center align-middle"><?php echo $no_cabor_loop++; ?></td>
                                    <td class="text-center align-middle">
                                        <?php
                                        $url_logo_cabor_list = rtrim($app_base_path, '/') . '/' . ltrim($path_logo_koni_default_relatif, '/'); // Defaultnya logo KONI
                                        if (!empty($cabor_item_view['logo_cabor']) && $cabor_item_view['logo_cabor'] !== $path_logo_koni_default_relatif) { // Jika ada logo kustom dan BUKAN path default
                                            $path_logo_cbr_rel = ltrim($cabor_item_view['logo_cabor'], '/');
                                            $path_logo_cbr_srv_check = rtrim(APP_PATH_BASE, '/\\') . '/' . $path_logo_cbr_rel;
                                            if (file_exists(preg_replace('/\/+/', '/', $path_logo_cbr_srv_check)) && is_file(preg_replace('/\/+/', '/', $path_logo_cbr_srv_check))) {
                                                $url_logo_cabor_list = rtrim($app_base_path, '/') . '/' . $path_logo_cbr_rel;
                                            } // Jika file kustom tidak ada, tetap pakai default KONI
                                        }
                                        $url_logo_cabor_list = preg_replace('/\/+/', '/', $url_logo_cabor_list);
                                        ?>
                                        <img src="<?php echo htmlspecialchars($url_logo_cabor_list); ?>" 
                                             alt="Logo <?php echo htmlspecialchars($cabor_item_view['nama_cabor']); ?>" 
                                             style="width: 40px; height: 40px; object-fit: contain; border-radius: 4px; background-color: #f8f9fa; padding:2px;">
                                    </td>
                                    <td class="align-middle"><strong><?php echo htmlspecialchars($cabor_item_view['nama_cabor']); ?></strong></td>
                                    <td class="text-center align-middle"><?php echo htmlspecialchars($cabor_item_view['kode_cabor']); ?></td>
                                    <td class="align-middle" title="<?php echo !empty($cabor_item_view['nama_ketua']) ? 'NIK: ' . htmlspecialchars($cabor_item_view['ketua_cabor_nik'] ?? '') : 'Belum diatur'; ?>"><?php echo htmlspecialchars($cabor_item_view['nama_ketua'] ?? '<em>N/A</em>'); ?></td>
                                    <td class="align-middle" title="<?php echo !empty($cabor_item_view['nama_sekretaris']) ? 'NIK: ' . htmlspecialchars($cabor_item_view['sekretaris_cabor_nik'] ?? '') : 'Belum diatur'; ?>"><?php echo htmlspecialchars($cabor_item_view['nama_sekretaris'] ?? '<em>N/A</em>'); ?></td>
                                    <td class="align-middle" title="<?php echo !empty($cabor_item_view['nama_bendahara']) ? 'NIK: ' . htmlspecialchars($cabor_item_view['bendahara_cabor_nik'] ?? '') : 'Belum diatur'; ?>"><?php echo htmlspecialchars($cabor_item_view['nama_bendahara'] ?? '<em>N/A</em>'); ?></td>
                                    <td class="text-center align-middle">
                                        <span class="<?php echo htmlspecialchars($cabor_item_view['status_periode_info']['color']); ?>" data-toggle="tooltip" title="Periode: <?php echo (!empty($cabor_item_view['periode_mulai']) ? date('d M Y', strtotime($cabor_item_view['periode_mulai'])) : 'N/A'); ?> s/d <?php echo (!empty($cabor_item_view['periode_selesai']) ? date('d M Y', strtotime($cabor_item_view['periode_selesai'])) : 'N/A'); ?>">
                                            <?php echo $cabor_item_view['status_periode_info']['text']; ?>
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <div class="progress progress-xs" title="<?php echo $cabor_item_view['progress_kelengkapan']; ?>% Data Lengkap">
                                            <div class="progress-bar <?php echo htmlspecialchars($cabor_item_view['progress_color'] ?? 'bg-secondary'); ?>" role="progressbar" style="width: <?php echo ($cabor_item_view['progress_kelengkapan'] ?? 0); ?>%" aria-valuenow="<?php echo ($cabor_item_view['progress_kelengkapan'] ?? 0); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small class="text-muted d-block text-center"><?php echo ($cabor_item_view['progress_kelengkapan'] ?? 0); ?>%</small>
                                    </td>
                                    <td class="text-center align-middle" style="white-space: nowrap;">
                                        <a href="detail_cabor.php?id_cabor=<?php echo $cabor_item_view['id_cabor']; ?>" class="btn btn-info btn-xs mr-1" title="Detail Cabor"><i class="fas fa-eye"></i></a>
                                        <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni'])): ?>
                                            <a href="edit_cabor.php?id_cabor=<?php echo $cabor_item_view['id_cabor']; ?>" class="btn btn-warning btn-xs mr-1" title="Edit Cabor"><i class="fas fa-edit"></i></a>
                                            <a href="hapus_cabor.php?id_cabor=<?php echo $cabor_item_view['id_cabor']; ?>" class="btn btn-danger btn-xs" title="Hapus Cabor" onclick="return confirm('PERHATIAN! Menghapus cabor akan dicegah jika masih ada data terkait (klub, atlet, peran pengurus, lisensi, sertifikasi).\nYakin ingin menghapus cabor \'<?php echo htmlspecialchars(addslashes($cabor_item_view['nama_cabor'])); ?>\'?');"><i class="fas fa-trash"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="10" class="text-center">Belum ada data cabang olahraga.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$id_tabel_js_cbr_final = "caborMasterTable"; 
$kolom_aksi_cbr_js_flag_final = in_array($user_role_utama, ['super_admin', 'admin_koni']);
$inline_script = "
$(document).ready(function() {
    if ( $.fn.DataTable && $('#" . $id_tabel_js_cbr_final . "').length ) {
        if (!$.fn.DataTable.isDataTable('#" . $id_tabel_js_cbr_final . "')) {
            $('#" . $id_tabel_js_cbr_final . "').DataTable({
                \"responsive\": true, \"lengthChange\": true, \"autoWidth\": false,
                \"buttons\": [
                    { extend: 'copy', text: '<i class=\"fas fa-copy mr-1\"></i> Salin', className: 'btn-sm btn-default', titleAttr: 'Salin', exportOptions: { columns: ':visible:not(.no-export)' } },
                    { extend: 'csv', text: '<i class=\"fas fa-file-csv mr-1\"></i> CSV', className: 'btn-sm btn-default', titleAttr: 'CSV', exportOptions: { columns: ':visible:not(.no-export)' } },
                    { extend: 'excel', text: '<i class=\"fas fa-file-excel mr-1\"></i> Excel', className: 'btn-sm btn-default', titleAttr: 'Excel', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Cabang Olahraga' },
                    { extend: 'pdf', text: '<i class=\"fas fa-file-pdf mr-1\"></i> PDF', className: 'btn-sm btn-default', titleAttr: 'PDF', orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Cabang Olahraga' },
                    { extend: 'print', text: '<i class=\"fas fa-print mr-1\"></i> Cetak', className: 'btn-sm btn-default', titleAttr: 'Cetak', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Cabang Olahraga' },
                    { extend: 'colvis', text: '<i class=\"fas fa-columns mr-1\"></i> Kolom', className: 'btn-sm btn-default', titleAttr: 'Kolom' }
                ],
                \"language\": { /* ... (Bahasa Indonesia seperti sebelumnya) ... */ 
                    \"search\": \"\", \"searchPlaceholder\": \"Cari cabor...\",
                    \"lengthMenu\": \"_MENU_ data/hal\", \"info\": \"Hal _PAGE_ dari _PAGES_ (_START_-_END_ dari _TOTAL_ data)\",
                    \"infoEmpty\": \"Data kosong\", \"infoFiltered\": \"(Total _MAX_ data)\",
                    \"zeroRecords\": \"Data tidak ditemukan\",
                    \"paginate\": { \"first\": \"<<\", \"last\": \">>\", \"next\": \">\", \"previous\": \"<\" }
                },
                \"order\": [[2, 'asc']], // Default order by Nama Cabor
                \"columnDefs\": [ 
                    { \"orderable\": false, \"searchable\": false, \"targets\": [0, 1] }, // No, Logo
                    " . ($kolom_aksi_cbr_js_flag_final ? "{ \"orderable\": false, \"searchable\": false, \"targets\": -1 }" : "") . ", // Kolom Aksi
                    { \"className\": \"text-center align-middle\", \"targets\": [0, 1, 3, 5, 6, 7, 8, 9] } // Meratakan tengah kolom tertentu
                ],
                \"dom\":  \"<'row'<'col-sm-12 col-md-3'l><'col-sm-12 col-md-6 text-center'B><'col-sm-12 col-md-3'f>>\" + \"<'row'<'col-sm-12'tr>>\" + \"<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>\",
                \"initComplete\": function(settings, json) {
                    $('[data-toggle=\"tooltip\"]').tooltip();
                    $('#" . $id_tabel_js_cbr_final . "_filter input').css({'width': '100%'}).addClass('form-control-sm');
                }
            });
        }
    }
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>