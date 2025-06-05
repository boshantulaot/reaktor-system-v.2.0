<?php
// File: public_html/reaktorsystem/dashboard.php

// ========================================================================
// AWAL BAGIAN A: PHP untuk Inisialisasi, Pengambilan Data Awal
// ========================================================================

$page_title = "Dashboard Ringkasan Sistem";
$additional_js = [
    'assets/adminlte/plugins/chart.js/Chart.min.js'
    // Jika Anda ingin menggunakan Sparkline nanti:
    // 'assets/adminlte/plugins/jquery-sparkline/jquery.sparkline.min.js',
];
$additional_css = [
    // 'assets/css/reaktor_dashboard_colors.css' // Buat dan uncomment ini nanti
];

require_once(__DIR__ . '/core/header.php');

// Pastikan variabel inti ada
if (!isset($pdo) || !$pdo instanceof PDO || !isset($user_login_status) || $user_login_status !== true || !isset($user_role_utama) || !isset($app_base_path)) {
    // Jika header.php tidak menghentikan eksekusi, kita hentikan di sini.
    die("Error: Variabel inti sistem tidak termuat dengan benar. Periksa file init_core.php dan header.php.");
}

// Inisialisasi array data utama untuk dashboard
$data_dashboard = [
    'info_boxes_top' => [
        'atlet_aktif'       => ['value' => 0, 'text' => 'Atlet Terdaftar',       'icon' => 'fas fa-running',             'color_class' => 'bg-info',    'link' => 'modules/atlet/daftar_atlet.php?status_approval=disetujui'],
        'pelatih_aktif'     => ['value' => 0, 'text' => 'Pelatih Terdaftar',     'icon' => 'fas fa-chalkboard-teacher',  'color_class' => 'bg-purple',  'link' => 'modules/pelatih/daftar_pelatih.php?status_approval=disetujui_admin'],
        'wasit_aktif'       => ['value' => 0, 'text' => 'Wasit Terdaftar',       'icon' => 'fas fa-gavel',               'color_class' => 'bg-orange',  'link' => 'modules/wasit/daftar_wasit.php?status_approval=disetujui_admin'],
        'cabor_aktif'       => ['value' => 0, 'text' => 'Cabor Aktif',       'icon' => 'fas fa-flag',                'color_class' => 'bg-success', 'link' => 'modules/cabor/daftar_cabor.php'],
    ],
    'monthly_recap' => [
        'chart_labels' => [], 'chart_data_pengajuan_atlet' => [], 'chart_data_pengajuan_klub' => [],
        'progress_groups' => [
            ['text' => 'Verifikasi Atlet', 'current' => 0, 'total' => 1, 'color' => 'bg-primary', 'link' => 'modules/atlet/daftar_atlet.php?status_approval=pending,verifikasi_pengcab', 'link_title' => 'Lihat Atlet Pending Verifikasi'],
            ['text' => 'Validasi Lisensi Pelatih', 'current' => 0, 'total' => 1, 'color' => 'bg-danger', 'link' => 'modules/lisensi_pelatih/daftar_lisensi_pelatih.php?status_approval=pending,disetujui_pengcab', 'link_title' => 'Lihat Lisensi Pelatih Pending'],
            ['text' => 'Verifikasi Klub', 'current' => 0, 'total' => 1, 'color' => 'bg-success', 'link' => 'modules/klub/daftar_klub.php?status_approval_admin=pending', 'link_title' => 'Lihat Klub Pending Verifikasi'],
            ['text' => 'Validasi Prestasi', 'current' => 0, 'total' => 1, 'color' => 'bg-info', 'link' => 'modules/prestasi_atlet/daftar_prestasi_atlet.php?status_approval=pending,disetujui_pengcab', 'link_title' => 'Lihat Prestasi Pending Validasi']
        ],
        'footer_stats' => [
            ['value' => '0', 'text' => 'ATLET BARU (BLN INI)', 'icon' => 'fas fa-arrow-up', 'color' => 'text-success', 'percentage_text' => '0%'],
            ['value' => '0', 'text' => 'KLUB BARU (BLN INI)', 'icon' => 'fas fa-arrow-up', 'color' => 'text-success', 'percentage_text' => '0%'],
            ['value' => '0', 'text' => 'LISENSI BARU (BLN INI)', 'icon' => 'fas fa-arrow-up', 'color' => 'text-success', 'percentage_text' => '0%'],
            ['value' => '0', 'text' => 'PRESTASI BARU (BLN INI)', 'icon' => 'fas fa-arrow-up', 'color' => 'text-success', 'percentage_text' => '0%'],
        ]
    ],
    'info_boxes_side' => [
        'klub_terdaftar'      => ['value' => 0, 'text' => 'Total Klub Terdaftar', 'icon' => 'fas fa-shield-alt',    'color_class' => 'bg-teal',    'link' => 'modules/klub/daftar_klub.php?status_approval_admin=disetujui'],
        'prestasi_valid'      => ['value' => 0, 'text' => 'Total Prestasi Valid', 'icon' => 'fas fa-trophy',          'color_class' => 'bg-maroon',  'link' => 'modules/prestasi_atlet/daftar_prestasi_atlet.php?status_approval=disetujui_admin'],
        'total_lisensi_valid' => ['value' => 0, 'text' => 'Total Lisensi Valid',  'icon' => 'fas fa-id-card-alt',   'color_class' => 'bg-indigo',  'link' => 'modules/lisensi_pelatih/daftar_lisensi_pelatih.php?status_approval=disetujui_admin'],
        'pengguna_pending'    => ['value' => 0, 'text' => 'Akun Baru Pending',  'icon' => 'fas fa-user-clock',    'color_class' => 'bg-pink',    'link' => 'admin/users/daftar_pengguna.php?is_approved=0']
    ],
    'latest_submissions_pending' => [],
    'active_users_for_chat' => [],
    'system_announcements' => [],
    'cabor_composition' => ['labels' => ["Data Kosong"], 'data' => [1], 'colors' => ["#d2d6de"]],
    'cabor_summary_list' => [],
    'latest_activity_logs' => [],
    'top_active_users' => [],
    'top_profile_completion_users' => []
];

// Inisialisasi label bulan untuk grafik
$current_month_loop = (int)date('m'); $current_year_loop = (int)date('Y');
for ($i = 5; $i >= 0; $i--) {
    $month_ts_loop = mktime(0, 0, 0, $current_month_loop - $i, 1, $current_year_loop);
    $data_dashboard['monthly_recap']['chart_labels'][] = date('M Y', $month_ts_loop);
    $data_dashboard['monthly_recap']['chart_data_pengajuan_atlet'][] = 0;
    $data_dashboard['monthly_recap']['chart_data_pengajuan_klub'][] = 0;
}
// ========================================================================
// AKHIR BAGIAN A (Inisialisasi)
// ========================================================================
?>

<?php
// ========================================================================
// AWAL BAGIAN C: PHP untuk Pengambilan Data Widget (untuk admin/super_admin)
// ========================================================================
if (isset($user_role_utama) && ($user_role_utama == 'super_admin' || $user_role_utama == 'admin_koni')) {
    try {
        // INFO BOXES (ATAS)
        $data_dashboard['info_boxes_top']['atlet_aktif']['value'] = $pdo->query("SELECT COUNT(DISTINCT nik) FROM atlet WHERE status_approval = 'disetujui'")->fetchColumn() ?: 0;
        $data_dashboard['info_boxes_top']['pelatih_aktif']['value'] = $pdo->query("SELECT COUNT(DISTINCT nik) FROM pelatih WHERE status_approval = 'disetujui_admin'")->fetchColumn() ?: 0;
        $data_dashboard['info_boxes_top']['wasit_aktif']['value'] = $pdo->query("SELECT COUNT(DISTINCT nik) FROM wasit WHERE status_approval = 'disetujui_admin'")->fetchColumn() ?: 0;
        $data_dashboard['info_boxes_top']['cabor_aktif']['value'] = $pdo->query("SELECT COUNT(*) FROM cabang_olahraga WHERE status_kepengurusan = 'Aktif'")->fetchColumn() ?: 0;

        // MONTHLY RECAP - Grafik Line
        $stmt_atlet_monthly_dash = $pdo->prepare("SELECT YEAR(created_at) as tahun, MONTH(created_at) as bulan, COUNT(*) as jumlah FROM atlet WHERE created_at >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01') GROUP BY tahun, bulan ORDER BY tahun, bulan");
        $stmt_atlet_monthly_dash->execute();
        foreach ($stmt_atlet_monthly_dash->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $label_idx = array_search(date('M Y', mktime(0,0,0,$row['bulan'],1,$row['tahun'])), $data_dashboard['monthly_recap']['chart_labels']);
            if ($label_idx !== false) $data_dashboard['monthly_recap']['chart_data_pengajuan_atlet'][$label_idx] = (int)$row['jumlah'];
        }
        $stmt_klub_monthly_dash = $pdo->prepare("SELECT YEAR(created_at) as tahun, MONTH(created_at) as bulan, COUNT(*) as jumlah FROM klub WHERE created_at >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01') GROUP BY tahun, bulan ORDER BY tahun, bulan");
        $stmt_klub_monthly_dash->execute();
        foreach ($stmt_klub_monthly_dash->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $label_idx = array_search(date('M Y', mktime(0,0,0,$row['bulan'],1,$row['tahun'])), $data_dashboard['monthly_recap']['chart_labels']);
            if ($label_idx !== false) $data_dashboard['monthly_recap']['chart_data_pengajuan_klub'][$label_idx] = (int)$row['jumlah'];
        }

        // MONTHLY RECAP - Progress Groups
        $atlet_aktif_val_prog = (int)$data_dashboard['info_boxes_top']['atlet_aktif']['value'];
        $atlet_total_semua_status_verif_prog = $pdo->query("SELECT COUNT(*) FROM atlet WHERE status_approval IN ('pending','verifikasi_pengcab','disetujui')")->fetchColumn() ?: 1;
        $data_dashboard['monthly_recap']['progress_groups'][0]['current'] = $atlet_aktif_val_prog;
        $data_dashboard['monthly_recap']['progress_groups'][0]['total'] = $atlet_total_semua_status_verif_prog;
        
        $lisensi_disetujui_prog = $pdo->query("SELECT COUNT(*) FROM lisensi_pelatih WHERE status_approval = 'disetujui_admin'")->fetchColumn() ?: 0;
        $lisensi_total_proses_prog = $pdo->query("SELECT COUNT(*) FROM lisensi_pelatih WHERE status_approval IN ('pending','disetujui_pengcab','disetujui_admin')")->fetchColumn() ?: 1;
        $data_dashboard['monthly_recap']['progress_groups'][1]['current'] = $lisensi_disetujui_prog;
        $data_dashboard['monthly_recap']['progress_groups'][1]['total'] = $lisensi_total_proses_prog;
        // ... (Lengkapi query untuk Klub dan Prestasi progress)

        // MONTHLY RECAP - Footer Stats
        $awal_bulan_ini_dt_stats = date('Y-m-01 00:00:00');
        $akhir_bulan_ini_dt_stats = date('Y-m-t 23:59:59');
        $data_dashboard['monthly_recap']['footer_stats'][0]['value'] = $pdo->query("SELECT COUNT(*) FROM atlet WHERE created_at BETWEEN '$awal_bulan_ini_dt_stats' AND '$akhir_bulan_ini_dt_stats'")->fetchColumn() ?: 0;
        $data_dashboard['monthly_recap']['footer_stats'][1]['value'] = $pdo->query("SELECT COUNT(*) FROM klub WHERE created_at BETWEEN '$awal_bulan_ini_dt_stats' AND '$akhir_bulan_ini_dt_stats'")->fetchColumn() ?: 0;
        // ... (Lengkapi query untuk Lisensi dan Prestasi baru bulan ini)

        // INFO BOXES STYLE 2 (Kanan Tengah)
        $data_dashboard['info_boxes_side']['klub_terdaftar']['value'] = $pdo->query("SELECT COUNT(*) FROM klub WHERE status_approval_admin = 'disetujui'")->fetchColumn() ?: 0;
        $data_dashboard['info_boxes_side']['prestasi_valid']['value'] = $pdo->query("SELECT COUNT(*) FROM prestasi_atlet WHERE status_approval = 'disetujui_admin'")->fetchColumn() ?: 0;
        $data_dashboard['info_boxes_side']['total_lisensi_valid']['value'] = $pdo->query("SELECT COUNT(*) FROM lisensi_pelatih WHERE status_approval = 'disetujui_admin'")->fetchColumn() ?: 0;
        $data_dashboard['info_boxes_side']['pengguna_pending']['value'] = $pdo->query("SELECT COUNT(*) FROM pengguna WHERE is_approved = 0")->fetchColumn() ?: 0;

        // LATEST SUBMISSIONS PENDING
        $query_pending_dash = "
            (SELECT id_atlet as id_item, nik as diajukan_oleh, 'Pendaftaran Atlet' as jenis_item, status_approval as status_item, created_at as tanggal_diajukan, 'modules/atlet/detail_atlet.php?id=' as link_base, id_cabor FROM atlet WHERE status_approval IN ('pending', 'verifikasi_pengcab') ORDER BY created_at DESC LIMIT 2)
            UNION ALL
            (SELECT id_klub as id_item, created_by_nik_pengcab as diajukan_oleh, CONCAT('Klub: ', nama_klub) as jenis_item, status_approval_admin as status_item, created_at as tanggal_diajukan, 'modules/klub/detail_klub.php?id=' as link_base, id_cabor FROM klub WHERE status_approval_admin = 'pending' ORDER BY created_at DESC LIMIT 2)
            UNION ALL
            (SELECT id_lisensi_pelatih as id_item, nik_pelatih as diajukan_oleh, CONCAT('Lisensi: ', nama_lisensi_sertifikat) as jenis_item, status_approval as status_item, created_at as tanggal_diajukan, 'modules/lisensi_pelatih/detail_lisensi_pelatih.php?id=' as link_base, id_cabor_lisensi as id_cabor FROM lisensi_pelatih WHERE status_approval IN ('pending', 'disetujui_pengcab') ORDER BY created_at DESC LIMIT 2)
            ORDER BY tanggal_diajukan DESC LIMIT 7";
        $stmt_pending_items_dash = $pdo->query($query_pending_dash);
        $data_dashboard['latest_submissions_pending'] = $stmt_pending_items_dash->fetchAll(PDO::FETCH_ASSOC);

        // ACTIVE USERS FOR CHAT
        $stmt_active_users_dash = $pdo->prepare("SELECT nik, nama_lengkap, foto FROM pengguna WHERE nik != :current_user_nik_val AND is_approved = 1 ORDER BY updated_at DESC LIMIT 8");
        $stmt_active_users_dash->bindParam(':current_user_nik_val', $user_nik);
        $stmt_active_users_dash->execute();
        $data_dashboard['active_users_for_chat'] = $stmt_active_users_dash->fetchAll(PDO::FETCH_ASSOC);

        // SYSTEM ANNOUNCEMENTS (Notifikasi dari admin untuk semua atau peran tertentu)
        $stmt_announcements_dash = $pdo->prepare("SELECT judul, isi, tanggal_buat, pembuat_nik, (SELECT nama_lengkap FROM pengguna WHERE pengguna.nik = notifikasi.pembuat_nik LIMIT 1) as nama_pembuat FROM notifikasi WHERE is_aktif = 1 AND (target_tipe = 'semua' OR (target_tipe = 'role' AND target_role = :user_role_val) OR (target_tipe = 'pengguna' AND target_nik = :user_nik_val)) ORDER BY tanggal_buat DESC LIMIT 5");
        $stmt_announcements_dash->bindParam(':user_role_val', $user_role_utama);
        $stmt_announcements_dash->bindParam(':user_nik_val', $user_nik);
        $stmt_announcements_dash->execute();
        $data_dashboard['system_announcements'] = $stmt_announcements_dash->fetchAll(PDO::FETCH_ASSOC);
        
        // CABOR COMPOSITION (Donut Chart)
        $stmt_donut_cabor_dash = $pdo->query("SELECT c.id_cabor, c.nama_cabor, COUNT(a.id_atlet) as jumlah_atlet FROM cabang_olahraga c LEFT JOIN atlet a ON c.id_cabor = a.id_cabor AND a.status_approval = 'disetujui' WHERE c.status_kepengurusan = 'Aktif' GROUP BY c.id_cabor ORDER BY jumlah_atlet DESC");
        $raw_donut_data_cabor_dash = $stmt_donut_cabor_dash->fetchAll(PDO::FETCH_ASSOC);
        $donut_limit_cabor_dash = 4; $count_donut_cabor_dash = 0; $others_sum_cabor_dash = 0;
        $temp_donut_colors_cabor_dash = ['#f56954', '#00a65a', '#f39c12', '#00c0ef', '#3c8dbc', '#605ca8'];
        $data_dashboard['cabor_composition']['labels'] = []; $data_dashboard['cabor_composition']['data'] = []; $data_dashboard['cabor_composition']['colors'] = [];
        foreach($raw_donut_data_cabor_dash as $idx_donut => $row_donut){
            if($row_donut['jumlah_atlet'] > 0){
                if($count_donut_cabor_dash < $donut_limit_cabor_dash){
                    $data_dashboard['cabor_composition']['labels'][] = $row_donut['nama_cabor'];
                    $data_dashboard['cabor_composition']['data'][] = (int)$row_donut['jumlah_atlet'];
                    $data_dashboard['cabor_composition']['colors'][] = $temp_donut_colors_cabor_dash[$idx_donut % count($temp_donut_colors_cabor_dash)];
                    $count_donut_cabor_dash++;
                } else {
                    $others_sum_cabor_dash += (int)$row_donut['jumlah_atlet'];
                }
            }
        }
        if($others_sum_cabor_dash > 0){ $data_dashboard['cabor_composition']['labels'][] = "Cabor Lain"; $data_dashboard['cabor_composition']['data'][] = $others_sum_cabor_dash; $data_dashboard['cabor_composition']['colors'][] = "#d2d6de"; }
        if(empty($data_dashboard['cabor_composition']['labels'])){ $data_dashboard['cabor_composition']['labels'][] = "Data Kosong"; $data_dashboard['cabor_composition']['data'][] = 1; $data_dashboard['cabor_composition']['colors'][] = "#d2d6de"; }

        // CABOR SUMMARY LIST
        $stmt_cabor_sum_list_dash = $pdo->query("SELECT id_cabor, nama_cabor, logo_cabor, (SELECT COUNT(*) FROM atlet a WHERE a.id_cabor = c.id_cabor AND a.status_approval = 'disetujui') as total_atlet_cabor FROM cabang_olahraga c WHERE c.status_kepengurusan = 'Aktif' ORDER BY nama_cabor ASC LIMIT 4");
        $data_dashboard['cabor_summary_list'] = $stmt_cabor_sum_list_dash->fetchAll(PDO::FETCH_ASSOC);

        // LATEST ACTIVITY LOGS
        $stmt_latest_logs_widget = $pdo->prepare("SELECT al.waktu_aksi, al.aksi, al.keterangan, p.nama_lengkap AS nama_pelaku_log FROM audit_log al LEFT JOIN pengguna p ON al.user_nik = p.nik ORDER BY al.waktu_aksi DESC LIMIT 5");
        $stmt_latest_logs_widget->execute();
        $data_dashboard['latest_activity_logs'] = $stmt_latest_logs_widget->fetchAll(PDO::FETCH_ASSOC);

        // TOP ACTIVE USERS & PROFILE COMPLETION (Placeholder, perlu query kompleks)
        $stmt_top_users_dash = $pdo->query("SELECT nik, nama_lengkap, foto FROM pengguna WHERE is_approved = 1 ORDER BY updated_at DESC LIMIT 4"); // Contoh sementara
        $data_dashboard['top_active_users'] = $stmt_top_users_dash->fetchAll(PDO::FETCH_ASSOC);
        $data_dashboard['top_profile_completion_users'] = $data_dashboard['top_active_users']; // Ganti dengan logika skor kelengkapan

    } catch (PDOException $e) {
        error_log("Dashboard Admin/SA Data Fetch Error (Full V3 - Bagian C): " . $e->getMessage());
        // $_SESSION['pesan_error_global'] sudah di-set di Bagian A jika error terjadi di sana.
        // Jika error di sini, variabel data widget ini akan kosong dan HTML akan menampilkan "Tidak ada data".
    }
}
// ========================================================================
// AKHIR BAGIAN C
// ========================================================================
?>

<?php
// ========================================================================
// AWAL BAGIAN B & D (HTML digabung dan disesuaikan)
// ========================================================================
?>
<?php // CONTENT HEADER KHUSUS DASHBOARD ?>
<div class="content-header">
  <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0"><?php echo htmlspecialchars($page_title); ?></h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="<?php echo rtrim($app_base_path, '/'); ?>/dashboard.php">Home</a></li><li class="breadcrumb-item active">Dashboard</li></ol></div></div></div>
</div>

<section class="content">
  <div class="container-fluid">
    <?php // Pesan Global (dari sesi) ?>
    <?php if (isset($_SESSION['pesan_sukses_global'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($_SESSION['pesan_sukses_global']); unset($_SESSION['pesan_sukses_global']); ?><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['pesan_error_global'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($_SESSION['pesan_error_global']); unset($_SESSION['pesan_error_global']); ?><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>
    <?php endif; ?>

    <?php if (isset($user_role_utama) && ($user_role_utama == 'super_admin' || $user_role_utama == 'admin_koni')): ?>
        <!-- Baris 1: Info boxes (Small Boxes) -->
        <div class="row">
          <?php foreach ($data_dashboard['info_boxes_top'] as $key_ib_top => $box_ib_top): ?>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3 <?php echo htmlspecialchars($box_ib_top['color_class']); /* Ini bg-info dll */ ?>">
              <span class="info-box-icon elevation-1"><i class="<?php echo htmlspecialchars($box_ib_top['icon']); ?>"></i></span>
              <div class="info-box-content">
                <span class="info-box-text"><?php echo htmlspecialchars($box_ib_top['text']); ?></span>
                <span class="info-box-number"><?php echo htmlspecialchars((string)$box_ib_top['value']); ?></span>
              </div>
              <?php if(!empty($box_ib_top['link'])): ?>
                <a href="<?php echo rtrim($app_base_path, '/'); ?>/<?php echo htmlspecialchars($box_ib_top['link']); ?>" class="small-box-footerstretched-link internal-link <?php echo (strpos($box_ib_top['color_class'], 'warning') !== false) ? 'text-dark' : ''; // Penyesuaian warna teks link jika bg terang/gelap ?>">
                    Lihat Detail <i class="fas fa-arrow-circle-right"></i>
                </a>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($key_ib_top === 'pelatih_aktif'): ?><div class="clearfix hidden-md-up"></div><?php endif; ?>
          <?php endforeach; ?>
        </div>
        <!-- /.row -->

        <!-- Baris 2: Monthly Recap Report Card -->
        <div class="row">
          <div class="col-md-12">
            <div class="card card-outline card-reaktor-primary"> <?php // Warna utama dashboard ?>
              <div class="card-header"><h5 class="card-title">Laporan Rekap Bulanan</h5><div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button><button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fas fa-times"></i></button></div></div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-8"><p class="text-center"><strong>Tren Pengajuan Baru: <?php echo $data_dashboard['monthly_recap']['chart_labels'][0] ?? 'N/A'; ?> - <?php echo end($data_dashboard['monthly_recap']['chart_labels']) ?? 'N/A'; ?></strong></p><div class="chart"><canvas id="monthlyRecapChart" height="180" style="height: 180px;"></canvas></div></div>
                  <div class="col-md-4"><p class="text-center"><strong>Progres Verifikasi & Validasi</strong></p>
                    <?php foreach($data_dashboard['monthly_recap']['progress_groups'] as $pg_item_data_html): $percentage_pg_html = ($pg_item_data_html['total'] > 0) ? round(($pg_item_data_html['current'] / $pg_item_data_html['total']) * 100) : 0; ?>
                    <div class="progress-group"><a href="<?php echo isset($pg_item_data_html['link']) ? htmlspecialchars(rtrim($app_base_path, '/') . '/' .$pg_item_data_html['link']) : '#'; ?>" class="text-dark progress-text" title="<?php echo htmlspecialchars($pg_item_data_html['link_title'] ?? $pg_item_data_html['text']); ?>"><?php echo htmlspecialchars($pg_item_data_html['text']); ?></a><span class="float-right"><b><?php echo $pg_item_data_html['current']; ?></b>/<?php echo $pg_item_data_html['total']; ?></span><div class="progress progress-sm"><div class="progress-bar <?php echo htmlspecialchars($pg_item_data_html['color']); ?>" style="width: <?php echo $percentage_pg_html; ?>%"></div></div></div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
              <div class="card-footer"><div class="row">
                  <?php foreach($data_dashboard['monthly_recap']['footer_stats'] as $fs_item_data_html): ?>
                  <div class="col-sm-3 col-6"><div class="description-block border-right"><span class="description-percentage <?php echo htmlspecialchars($fs_item_data_html['color']); ?>"><i class="<?php echo htmlspecialchars($fs_item_data_html['icon']); ?>"></i> <?php echo htmlspecialchars($fs_item_data_html['percentage_text']); ?></span><h5 class="description-header"><?php echo htmlspecialchars((string)$fs_item_data_html['value']); ?></h5><span class="description-text"><?php echo htmlspecialchars($fs_item_data_html['text']); ?></span></div></div>
                  <?php endforeach; ?>
              </div></div>
            </div>
          </div>
        </div>
        <!-- /.row -->

        <!-- Baris 3: Kolom Kiri dan Kanan -->
        <div class="row">
          <!-- Kolom Kiri (col-md-8) -->
          <div class="col-md-8">
            <!-- CARD 1: Pengajuan Terbaru Menunggu Tindakan -->
            <div class="card card-outline card-reaktor-danger">
                <div class="card-header border-transparent"><h3 class="card-title"><i class="fas fa-clipboard-check mr-1"></i>Pengajuan Terbaru Menunggu Tindakan</h3><div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button><button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fas fa-times"></i></button></div></div>
                <div class="card-body p-0"><div class="table-responsive" style="max-height: 280px; overflow-y: auto;"><table class="table table-sm m-0 table-hover"><thead><tr><th>ID/Nama</th><th>Jenis</th><th>Status</th><th>Tanggal</th></tr></thead><tbody>
                <?php if(!empty($data_dashboard['latest_submissions_pending'])): foreach($data_dashboard['latest_submissions_pending'] as $sub_item_html): ?>
                <tr><td><a href="<?php echo rtrim($app_base_path, '/'); ?>/<?php echo htmlspecialchars($sub_item_html['link_base'] . $sub_item_html['id_item']); ?>"><?php echo htmlspecialchars(substr($sub_item_html['diajukan_oleh'] ?? $sub_item_html['id_item'],0,20)); ?><?php if(strlen($sub_item_html['diajukan_oleh'] ?? $sub_item_html['id_item']) > 20) echo "...";?></a></td><td><?php echo htmlspecialchars($sub_item_html['jenis_item']); ?></td><td><span class="badge badge-<?php echo (strpos($sub_item_html['status_item'], 'pending') !== false || strpos($sub_item_html['status_item'], 'verifikasi') !== false) ? 'warning' : 'info'; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_',' ',$sub_item_html['status_item']))); ?></span></td><td><?php echo date('d M Y', strtotime($sub_item_html['tanggal_diajukan'])); ?></td></tr>
                <?php endforeach; else: ?><tr><td colspan="4" class="text-center p-3 text-muted"><i>Tidak ada pengajuan menunggu tindakan.</i></td></tr><?php endif; ?>
                </tbody></table></div></div>
                <div class="card-footer clearfix"><a href="<?php echo rtrim($app_base_path, '/'); ?>/admin/dashboard_pending_all.php" class="btn btn-sm btn-secondary float-right">Lihat Semua Pengajuan Pending</a></div>
            </div>

            <!-- CARD 2: Log Aktivitas Terbaru Sistem -->
            <div class="card card-outline card-reaktor-secondary">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-1"></i>Log Aktivitas Terbaru</h3><div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button><button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fas fa-times"></i></button></div></div>
                <div class="card-body p-0" style="max-height: 280px; overflow-y: auto;">
                    <?php if(!empty($data_dashboard['latest_activity_logs'])): ?>
                    <ul class="products-list product-list-in-card pl-2 pr-2">
                        <?php foreach($data_dashboard['latest_activity_logs'] as $log_act_item): $log_style_item = function_exists('getLogActionStyle') ? getLogActionStyle($log_act_item['aksi']) : ['icon' => 'fas fa-info-circle', 'badge_color' => 'bg-secondary']; ?>
                        <li class="item"><div class="product-img"><img src="<?php echo empty($log_act_item['foto_pelaku_log']) ? rtrim($app_base_path, '/') . '/' . $default_avatar_path_relative : rtrim($app_base_path, '/') . '/' . htmlspecialchars($log_act_item['foto_pelaku_log']); ?>" alt="User" class="img-circle img-size-32"></div><div class="product-info"><span class="product-title"><?php echo htmlspecialchars($log_act_item['nama_pelaku_log'] ?? 'Sistem'); ?> <span class="badge <?php echo $log_style_item['badge_color']; ?> float-right"><?php echo date('H:i', strtotime($log_act_item['waktu_aksi'])); ?></span></span><span class="product-description"><?php echo htmlspecialchars(strtolower($log_act_item['aksi'])); ?>: <?php echo htmlspecialchars(substr($log_act_item['keterangan'] ?? 'N/A', 0, 60)); ?><?php if(strlen($log_act_item['keterangan'] ?? '') > 60) echo "..."; ?></span></div></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?><p class="text-center p-3 text-muted"><i>Tidak ada aktivitas terbaru.</i></p><?php endif; ?>
                </div>
                <div class="card-footer text-center"><a href="<?php echo rtrim($app_base_path, '/'); ?>/admin/audit_logs/audit_log_view.php" class="uppercase">Lihat Semua Log</a></div>
            </div>
            
            <!-- CARD 3 & 4: Pengumuman & Pengguna Aktif (Sebelah Menyebelah) -->
            <div class="row">
                <div class="col-lg-6">
                    <!-- Pengumuman Sistem (To-Do) -->
                    <div class="card card-outline card-reaktor-info">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-bullhorn mr-1"></i> Pengumuman Sistem</h3><div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div></div>
                        <div class="card-body" style="max-height: 250px; overflow-y: auto;">
                            <?php if(!empty($data_dashboard['system_announcements'])): ?>
                            <ul class="todo-list" data-widget="todo-list">
                                <?php foreach($data_dashboard['system_announcements'] as $ann_item_data): ?>
                                <li class="p-1 border-bottom"><span class="text"><small class="badge badge-info"><i class="far fa-clock"></i> <?php echo date('d M', strtotime($ann_item_data['tanggal_buat'])); ?></small> <strong><?php echo htmlspecialchars($ann_item_data['judul']); ?></strong></span><div class="text-muted_light text-xs pl-1"><?php echo nl2br(htmlspecialchars(substr($ann_item_data['isi'], 0, 100) . (strlen($ann_item_data['isi']) > 100 ? '...' : ''))); ?></div></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?><p class="text-center p-3 text-muted"><i>Tidak ada pengumuman.</i></p><?php endif; ?>
                            <?php echo "<!-- KOMENTAR: Fitur To-Do List interaktif memerlukan pengembangan lanjutan. -->"; ?>
                        </div>
                         <?php if($user_role_utama == 'super_admin' || $user_role_utama == 'admin_koni'): ?><div class="card-footer"><a href="<?php echo rtrim($app_base_path, '/'); ?>/admin/notifikasi/form_tambah_notifikasi.php" class="btn btn-primary btn-xs"><i class="fas fa-plus"></i> Buat Baru</a></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <!-- Pengguna Aktif (Placeholder Chat) -->
                    <div class="card direct-chat direct-chat-primary card-outline">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-users mr-1"></i> Pengguna Aktif (Placeholder)</h3><div class="card-tools"><span title="<?php echo count($data_dashboard['active_users_for_chat']); ?> Aktif" class="badge badge-primary"><?php echo count($data_dashboard['active_users_for_chat']); ?></span><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div></div>
                        <div class="card-body"><div class="direct-chat-messages p-1" style="height: 200px; overflow-y:auto;">
                        <?php if(!empty($data_dashboard['active_users_for_chat'])): ?>
                            <?php foreach($data_dashboard['active_users_for_chat'] as $active_user_data): ?>
                            <div class="direct-chat-msg mb-1"><div class="direct-chat-infos clearfix"><span class="direct-chat-name float-left"><a href="<?php echo rtrim($app_base_path, '/'); ?>/profile/profil_pengguna.php?nik=<?php echo htmlspecialchars($active_user_data['nik']); ?>" title="Lihat Profil"><?php echo htmlspecialchars($active_user_data['nama_lengkap']); ?></a></span><span class="direct-chat-timestamp float-right text-success text-xs"><i class="fas fa-circle fa-xs"></i> Online</span></div><img class="direct-chat-img" src="<?php echo empty($active_user_data['foto']) ? rtrim($app_base_path, '/') . '/' . $default_avatar_path_relative : rtrim($app_base_path, '/') . '/' . htmlspecialchars($active_user_data['foto']); ?>" alt="User"><div class="direct-chat-text bg-light text-xs">Status: Aktif (Placeholder)</div></div>
                            <?php endforeach; ?>
                        <?php else: ?><p class="text-center text-muted p-3"><i>Tidak ada pengguna aktif lain.</i></p><?php endif; ?>
                        </div></div>
                        <div class="card-footer"><form action="#" method="post" onsubmit="alert('Fitur chat belum diimplementasikan.'); return false;"><div class="input-group"><input type="text" name="message" placeholder="Ketik Pesan (UI Placeholder)..." class="form-control" disabled><span class="input-group-append"><button type="submit" class="btn btn-primary btn-sm" disabled>Kirim</button></span></div></form></div>
                        <?php echo "<!-- KOMENTAR: Fitur Chat Real-time & Private Messaging memerlukan pengembangan besar. -->"; ?>
                    </div>
                </div>
            </div>

          </div>
          <!-- /.col (Kolom Kiri Utama) -->

          <!-- Kolom Kanan (col-md-4) -->
          <div class="col-md-4">
            <!-- Info Boxes Style 2 -->
            <?php foreach($data_dashboard['info_boxes_side'] as $key_ib_side => $box_ib_side): ?>
            <div class="info-box mb-3 <?php echo htmlspecialchars($box_ib_side['color_class']); ?>">
              <span class="info-box-icon"><i class="<?php echo htmlspecialchars($box_ib_side['icon']); ?>"></i></span>
              <div class="info-box-content"><span class="info-box-text"><?php echo htmlspecialchars($box_ib_side['text']); ?></span><span class="info-box-number"><?php echo htmlspecialchars((string)$box_ib_side['value']); ?></span></div>
              <?php if(!empty($box_ib_side['link'])): ?><a href="<?php echo rtrim($app_base_path, '/'); ?>/<?php echo htmlspecialchars($box_ib_side['link']); ?>" class="small-box-footerstretched-link internal-link text-white">Selengkapnya <i class="fas fa-arrow-circle-right"></i></a><?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <!-- SOROTAN PENGGUNA -->
            <div class="card card-outline card-reaktor-success">
              <div class="card-header"><h3 class="card-title">Sorotan Pengguna</h3><div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div></div>
              <div class="card-body p-0" style="max-height: 350px; overflow-y: auto;">
                <h6 class="p-2 bg-light text-sm border-bottom">Pengguna Paling Aktif (Contoh)</h6>
                <?php if(!empty($data_dashboard['top_active_users'])): ?><ul class="users-list clearfix m-0 p-1">
                    <?php foreach($data_dashboard['top_active_users'] as $top_user_data_html): ?>
                    <li style="width: 23%; margin: 1%;"><img src="<?php echo empty($top_user_data_html['foto']) ? rtrim($app_base_path, '/') . '/' . $default_avatar_path_relative : rtrim($app_base_path, '/') . '/' . htmlspecialchars($top_user_data_html['foto']); ?>" alt="User" title="<?php echo htmlspecialchars($top_user_data_html['nama_lengkap']); ?>" style="width:50px; height:50px; object-fit:cover; border-radius:50%;"><a class="users-list-name mt-1 d-block text-xs" href="<?php echo rtrim($app_base_path, '/'); ?>/profile/profil_pengguna.php?nik=<?php echo htmlspecialchars($top_user_data_html['nik']); ?>"><?php echo htmlspecialchars(explode(' ', $top_user_data_html['nama_lengkap'])[0]); ?></a></li>
                    <?php endforeach; ?></ul>
                <?php else: ?><p class="text-center p-2 text-muted text-sm"><i>Data pengguna aktif belum tersedia.</i></p><?php endif; ?>
                <?php echo "<!-- KOMENTAR: Query 'Pengguna Paling Aktif' perlu logika audit_log. -->"; ?>
                
                <h6 class="p-2 bg-light text-sm border-top border-bottom">Profil Terlengkap (Contoh)</h6>
                 <?php if(!empty($data_dashboard['top_profile_completion_users'])): ?><ul class="users-list clearfix m-0 p-1">
                    <?php foreach($data_dashboard['top_profile_completion_users'] as $comp_user_data_html): ?>
                    <li style="width: 23%; margin: 1%;"><img src="<?php echo empty($comp_user_data_html['foto']) ? rtrim($app_base_path, '/') . '/' . $default_avatar_path_relative : rtrim($app_base_path, '/') . '/' . htmlspecialchars($comp_user_data_html['foto']); ?>" alt="User" title="<?php echo htmlspecialchars($comp_user_data_html['nama_lengkap']); ?>" style="width:50px; height:50px; object-fit:cover; border-radius:50%;"><a class="users-list-name mt-1 d-block text-xs" href="<?php echo rtrim($app_base_path, '/'); ?>/profile/profil_pengguna.php?nik=<?php echo htmlspecialchars($comp_user_data_html['nik']); ?>"><?php echo htmlspecialchars(explode(' ', $comp_user_data_html['nama_lengkap'])[0]); ?></a><span class="users-list-date text-xs"><i class="fas fa-star text-warning"></i>Lengkap</span></li>
                    <?php endforeach; ?></ul>
                <?php else: ?><p class="text-center p-2 text-muted text-sm"><i>Data kelengkapan profil belum tersedia.</i></p><?php endif; ?>
                <?php echo "<!-- KOMENTAR: Logika skor 'Profil Terlengkap' perlu dibuat. -->"; ?>
              </div>
              <div class="card-footer text-center"><a href="<?php echo rtrim($app_base_path, '/'); ?>/admin/users/daftar_pengguna.php">Lihat Semua Pengguna</a></div>
            </div>

            <!-- Komposisi Atlet per Cabor (Donut Chart) -->
            <div class="card card-outline card-reaktor-info-dark">
              <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-pie mr-1"></i>Atlet per Cabor</h3><div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div></div>
              <div class="card-body"><div class="row">
                  <div class="col-md-7 col-7"><div class="chart-responsive"><canvas id="pieChartCabor" height="120" style="min-height: 120px; height: 120px; max-height: 120px; max-width: 100%;"></canvas></div></div>
                  <div class="col-md-5 col-5"><ul class="chart-legend clearfix">
                      <?php foreach($data_dashboard['cabor_composition']['labels'] as $idx_bc_leg => $label_bc_leg): ?>
                      <li><small><i class="far fa-circle" style="color:<?php echo $data_dashboard['cabor_composition']['colors'][$idx_bc_leg] ?? '#000'; ?>;"></i> <?php echo htmlspecialchars($label_bc_leg); ?> (<?php echo $data_dashboard['cabor_composition']['data'][$idx_bc_leg] ?? 0; ?>)</small></li>
                      <?php endforeach; ?>
                  </ul></div>
              </div></div>
            </div>

            <!-- Ringkasan Cabor (Product List Style) -->
            <div class="card card-outline card-reaktor-success-dark">
              <div class="card-header"><h3 class="card-title"><i class="fas fa-tags mr-1"></i>Ringkasan Cabor</h3><div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div></div>
              <div class="card-body p-0" style="max-height: 250px; overflow-y: auto;">
                <?php if(!empty($data_dashboard['cabor_summary_list'])): ?><ul class="products-list product-list-in-card pl-2 pr-2">
                  <?php foreach($data_dashboard['cabor_summary_list'] as $cabor_prod_item_html): ?>
                  <li class="item"><div class="product-img"><img src="<?php echo empty($cabor_prod_item_html['logo_cabor']) ? rtrim($app_base_path, '/') . '/assets/img_default/cabor.png' : rtrim($app_base_path, '/') . '/' . htmlspecialchars($cabor_prod_item_html['logo_cabor']); ?>" alt="Logo" class="img-size-50 img-circle"></div><div class="product-info"><a href="<?php echo rtrim($app_base_path, '/'); ?>/modules/cabor/detail_cabor.php?id=<?php echo $cabor_prod_item_html['id_cabor']; ?>" class="product-title"><?php echo htmlspecialchars($cabor_prod_item_html['nama_cabor']); ?><span class="badge badge-info float-right"><?php echo $cabor_prod_item_html['total_atlet_cabor']; ?> Atlet</span></a><span class="product-description text-sm">Klik untuk detail</span></div></li>
                  <?php endforeach; ?></ul>
                <?php else: ?><p class="text-center p-3 text-muted"><i>Tidak ada data cabor.</i></p><?php endif; ?>
              </div>
              <div class="card-footer text-center"><a href="<?php echo rtrim($app_base_path, '/'); ?>/modules/cabor/daftar_cabor.php" class="uppercase">Semua Cabor</a></div>
            </div>
          </div>
          <!-- /.col (Kolom Kanan Utama) -->
        </div>
        <!-- /.row (Penutup Main row untuk Baris 3) -->

    <?php // Penutup `if admin/super_admin` akan ada di Bagian F ?>
<?php
// ========================================================================
// AKHIR BAGIAN D
// ========================================================================
?>

<?php
// ========================================================================
// AWAL BAGIAN E: JavaScript untuk Inisialisasi Grafik
// ========================================================================
$inline_script = '';
if (isset($user_role_utama) && ($user_role_utama == 'super_admin' || $user_role_utama == 'admin_koni')):
    ob_start();
?>
<script>
$(function () {
  'use strict';
  // Fungsi helper untuk chart jika ada data
  function createChart(canvasId, chartType, chartData, chartOptions) {
      var canvasElement = $('#' + canvasId);
      if (canvasElement.length && typeof Chart !== 'undefined') {
          // Cek apakah ada data valid untuk ditampilkan (selain placeholder "Data Kosong")
          var hasValidData = false;
          if (chartData.datasets && chartData.datasets.length > 0) {
              chartData.datasets.forEach(function(dataset) {
                  if (dataset.data && dataset.data.some(function(value) { return value > 0; })) {
                      hasValidData = true;
                  }
              });
          }
          if (chartData.labels && chartData.labels[0] === "Data Kosong" && chartData.datasets[0].data[0] === 1 && chartData.datasets[0].data.length === 1) {
              hasValidData = false; // Ini adalah placeholder data kosong
          }


          if (hasValidData || chartType === 'line') { // Untuk line chart, tampilkan meski data 0 semua
             try {
                new Chart(canvasElement.get(0).getContext('2d'), {
                    type: chartType,
                    data: chartData,
                    options: chartOptions
                });
             } catch (e) {
                console.error("Error creating chart: " + canvasId, e);
                canvasElement.parent().html("<p class='text-center text-danger p-3'>Gagal memuat grafik.</p>");
             }
          } else {
              canvasElement.parent().html("<p class='text-center text-muted p-3'>Tidak ada data yang cukup untuk menampilkan grafik " + canvasId + ".</p>");
          }
      } else if (canvasElement.length) {
          console.warn("Chart.js tidak termuat atau elemen canvas '" + canvasId + "' tidak ditemukan.");
          canvasElement.parent().html("<p class='text-center text-warning p-3'>Komponen grafik tidak dapat dimuat.</p>");
      }
  }

  // MONTHLY RECAP CHART (LINE CHART)
  var recapChartData = {
    labels: <?php echo json_encode($data_dashboard['monthly_recap']['chart_labels']); ?>,
    datasets: [
      { label: 'Pengajuan Atlet', backgroundColor: 'rgba(60,141,188,0.2)', borderColor: 'rgba(60,141,188,1)', pointRadius: 3, data: <?php echo json_encode($data_dashboard['monthly_recap']['chart_data_pengajuan_atlet']); ?>, tension: 0.3, fill: true, borderWidth: 2 },
      { label: 'Pengajuan Klub', backgroundColor: 'rgba(0,166,90,0.2)', borderColor: 'rgba(0,166,90,1)', pointRadius: 3, data: <?php echo json_encode($data_dashboard['monthly_recap']['chart_data_pengajuan_klub']); ?>, tension: 0.3, fill: true, borderWidth: 2 }
    ]
  };
  var recapChartOptions = { maintainAspectRatio: false, responsive: true, legend: { display: true, position: 'bottom', labels: { fontColor: '#666' } }, scales: { xAxes: [{ gridLines: { display: false }, ticks: { fontColor: '#666' } }], yAxes: [{ gridLines: { display: true, color: "rgba(0, 0, 0, .05)" }, ticks: { beginAtZero: true, callback: function (value) { if (Number.isInteger(value)) { return value; } }, fontColor: '#666'} }] }, tooltips: { mode: 'index', intersect: false, titleFontColor: '#333', bodyFontColor: '#555', backgroundColor: 'rgba(255,255,255,0.95)', borderColor: '#ddd', borderWidth:1, cornerRadius: 3, displayColors: true }, hover: { mode: 'nearest', intersect: true } };
  createChart('monthlyRecapChart', 'line', recapChartData, recapChartOptions);

  // CABOR COMPOSITION (DONUT CHART)
  var pieCaborData = {
    labels: <?php echo json_encode($data_dashboard['cabor_composition']['labels']); ?>,
    datasets: [{ data: <?php echo json_encode($data_dashboard['cabor_composition']['data']); ?>, backgroundColor: <?php echo json_encode($data_dashboard['cabor_composition']['colors']); ?> }]
  };
  var pieCaborOptions = { maintainAspectRatio: false, responsive: true, legend: { display: false }, tooltips: { callbacks: { label: function(tooltipItem, data) { var dataset = data.datasets[tooltipItem.datasetIndex]; var total = dataset.data.reduce(function(a, b) { return a + b; }, 0); var currentValue = dataset.data[tooltipItem.index]; var percentage = total > 0 ? Math.round((currentValue / total) * 100) : 0; return data.labels[tooltipItem.index] + ': ' + currentValue + ' (' + percentage + '%)'; } } } };
  createChart('pieChartCabor', 'doughnut', pieCaborData, pieCaborOptions);

});
</script>
<?php
    $inline_script = ob_get_clean();
endif;
// ========================================================================
// AKHIR BAGIAN E
// ========================================================================
?>

<?php
// ========================================================================
// AWAL BAGIAN F: HTML untuk Dashboard Peran Lain dan Penutup File
// ========================================================================
    // Ini adalah penutup dari blok if ($user_role_utama == 'super_admin' || $user_role_utama == 'admin_koni')
    // yang dimulai di Bagian B dan melingkupi semua widget untuk admin/super_admin.
    ?>
    <?php elseif (isset($user_role_utama) && $user_role_utama == 'pengurus_cabor'): ?>
        <?php // TODO: Implementasi Dashboard Pengurus Cabor ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card card-outline card-reaktor-cabor"> <?php // Warna Cabor ?>
                    <div class="card-header"><h3 class="card-title">Dashboard Pengurus Cabang Olahraga</h3></div>
                    <div class="card-body">
                        <p>Selamat datang, Pengurus Cabor!</p>
                        <p>Statistik dan pengajuan untuk cabor Anda akan ditampilkan di sini.</p>
                        <?php echo "<!-- Contoh: Info Box Atlet Cabor, Klub Cabor, Pengajuan Atlet/Lisensi/Prestasi di Cabor ini yang menunggu verifikasi Pengcab -->"; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif (isset($user_role_utama) && in_array($user_role_utama, ['atlet', 'pelatih', 'wasit'])): ?>
        <?php // TODO: Implementasi Dashboard Atlet, Pelatih, Wasit ?>
        <div class="row">
            <div class="col-md-8">
                <div class="card card-outline card-reaktor-user"> <?php // Warna Pengguna Individu ?>
                    <div class="card-header"><h3 class="card-title">Dashboard <?php echo htmlspecialchars(ucwords(str_replace('_',' ',$user_role_utama))); ?></h3></div>
                    <div class="card-body">
                        <h4>Selamat Datang, <?php echo htmlspecialchars($nama_pengguna ?? 'Pengguna'); ?>!</h4>
                        <p>Gunakan menu di sebelah kiri untuk mengelola data Anda.</p>
                        <hr>
                        <p><a href="<?php echo rtrim($app_base_path, '/'); ?>/profile/profil_saya.php" class="btn btn-lg btn-primary"><i class="fas fa-id-card mr-2"></i>Profil & ID Card Saya</a></p>
                        <?php if ($user_role_utama == 'atlet'): ?>
                            <p><a href="<?php echo rtrim($app_base_path, '/'); ?>/profile/prestasi_saya.php" class="btn btn-info mt-2"><i class="fas fa-award mr-2"></i>Prestasi Saya</a> | <a href="<?php echo rtrim($app_base_path, '/'); ?>/modules/prestasi_atlet/tambah_prestasi_atlet.php" class="btn btn-success mt-2"><i class="fas fa-plus mr-2"></i>Ajukan Prestasi</a></p>
                        <?php elseif ($user_role_utama == 'pelatih'): ?>
                             <p><a href="<?php echo rtrim($app_base_path, '/'); ?>/profile/lisensi_saya.php" class="btn btn-info mt-2"><i class="fas fa-address-card mr-2"></i>Lisensi Saya</a> | <a href="<?php echo rtrim($app_base_path, '/'); ?>/modules/lisensi_pelatih/tambah_lisensi_pelatih.php" class="btn btn-success mt-2"><i class="fas fa-plus mr-2"></i>Ajukan Lisensi</a></p>
                        <?php elseif ($user_role_utama == 'wasit'): ?>
                             <p><a href="<?php echo rtrim($app_base_path, '/'); ?>/profile/sertifikasi_saya.php" class="btn btn-info mt-2"><i class="fas fa-medal mr-2"></i>Sertifikasi Saya</a> | <a href="<?php echo rtrim($app_base_path, '/'); ?>/modules/sertifikasi_wasit/tambah_sertifikasi_wasit.php" class="btn btn-success mt-2"><i class="fas fa-plus mr-2"></i>Ajukan Sertifikasi</a></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                 <div class="card card-outline card-reaktor-info-light">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-bullhorn mr-1"></i> Pengumuman</h3></div>
                    <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                        <?php if(!empty($data_dashboard['system_announcements']) && count($data_dashboard['system_announcements']) > 0): ?>
                            <ul class="todo-list" data-widget="todo-list" style="font-size: 0.9rem;">
                                <?php foreach($data_dashboard['system_announcements'] as $ann_item_usr): ?>
                                <li class="p-2 border-bottom">
                                    <span class="text"><small class="badge badge-info"><i class="far fa-clock"></i> <?php echo date('d M', strtotime($ann_item_usr['tanggal_buat'])); ?></small> <strong><?php echo htmlspecialchars($ann_item_usr['judul']); ?></strong></span>
                                    <div class="text-muted text-xs pl-1"><?php echo nl2br(htmlspecialchars(substr($ann_item_usr['isi'], 0, 100) . (strlen($ann_item_usr['isi']) > 100 ? '...' : ''))); ?></div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-center p-3 text-muted"><i>Tidak ada pengumuman.</i></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php else: // Untuk guest atau peran tidak dikenal ?>
        <div class="alert alert-secondary text-center mt-4">
            <h4>Selamat Datang di Reaktor System</h4>
            <p>Anda tidak memiliki dashboard spesifik untuk peran Anda saat ini, atau Anda belum login.</p>
            <?php if (!isset($user_login_status) || $user_login_status !== true): ?>
                 <p><a href="<?php echo rtrim($app_base_path, '/'); ?>/auth/login.php" class="btn btn-success btn-lg">Login Sekarang</a></p>
            <?php endif; ?>
        </div>
    <?php endif; // Akhir dari blok if/elseif/else untuk peran pengguna utama ?>

  </div><!--/. Akhir container-fluid yang dibuka di awal section content -->
</section><!--/. Akhir section class="content" yang dibuka di awal -->

<?php
// Memastikan variabel $inline_script ada sebelum di-echo oleh footer.php
if (!isset($inline_script)) {
    $inline_script = '';
}
require_once(__DIR__ . '/core/footer.php'); 
?>
<?php
// ========================================================================
// AKHIR BAGIAN F
// ========================================================================
?>