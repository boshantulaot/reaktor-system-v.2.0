<?php
// File: reaktorsystem/admin/roles/daftar_anggota.php
$page_title = "Manajemen Peran Anggota Sistem";

// --- Definisi Aset CSS & JS ---
$additional_css = [
    'assets/adminlte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css',
    'assets/adminlte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css',
    'assets/adminlte/plugins/datatables-buttons/css/buttons.bootstrap4.min.css',
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
];

require_once(__DIR__ . '/../../core/header.php'); // Ini akan memuat init_core.php juga

// Pengecekan sesi & konfigurasi (standar)
// Pastikan variabel $default_avatar_path_relative dan APP_PATH_BASE ada dari init_core.php
if (!isset($pdo) || !isset($user_role_utama) || !isset($user_nik) || !isset($app_base_path) || 
    !defined('APP_PATH_BASE') || !isset($default_avatar_path_relative) ) {
    
    echo "<!DOCTYPE html><html><head><title>Error Konfigurasi</title>"; 
    if (isset($app_base_path)) { echo "<link rel='stylesheet' href='" . htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/dist/css/adminlte.min.css') . "'>"; }
    echo "</head><body class='hold-transition sidebar-mini'><div class='wrapper'><section class='content'><div class='container-fluid'>";
    echo "<div class='alert alert-danger text-center mt-5 p-3'><strong>Error Kritis:</strong> Sesi tidak valid, konfigurasi aplikasi bermasalah, atau koneksi database gagal.<br>Harap hubungi administrator sistem.</div>";
    echo "</div></section></div></body></html>";
    if (file_exists(__DIR__ . '/../../core/footer.php')) { $inline_script = $inline_script ?? ''; require_once(__DIR__ . '/../../core/footer.php'); }
    exit();
}

// Hanya Super Admin yang boleh akses penuh modul ini
if ($user_role_utama != 'super_admin') {
    $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk mengakses halaman ini.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}

// Definisikan NIK Super Admin Utama di sini jika belum global (lebih baik di init_core.php)
if (!defined('NIK_SUPER_ADMIN_UTAMA_ANGGOTA')) {
    define('NIK_SUPER_ADMIN_UTAMA_ANGGOTA', '0000000000000001'); // GANTI DENGAN NIK SA UTAMA ANDA
}

// --- Pengambilan Data Anggota dari Database ---
$anggota_list_data_final = [];
try {
    $sql_anggota_list = "SELECT ang.id_anggota, p.nik, p.nama_lengkap, p.foto AS foto_pengguna, 
                           ang.jabatan, ang.role, ang.id_cabor, co.nama_cabor, 
                           ang.tingkat_pengurus, ang.is_verified, ang.verified_by_nik, ang.verified_at,
                           verifier.nama_lengkap AS nama_verifier 
                    FROM anggota ang
                    JOIN pengguna p ON ang.nik = p.nik
                    LEFT JOIN cabang_olahraga co ON ang.id_cabor = co.id_cabor
                    LEFT JOIN pengguna verifier ON ang.verified_by_nik = verifier.nik
                    ORDER BY p.nama_lengkap ASC, ang.role ASC";
    
    $stmt_anggota_list_exec = $pdo->query($sql_anggota_list);
    $anggota_list_data_final = $stmt_anggota_list_exec->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e_list_agt) {
    error_log("Daftar Anggota Error: " . $e_list_agt->getMessage());
    $_SESSION['pesan_error_global'] = "Terjadi kesalahan saat mengambil data peran anggota.";
    // Tidak redirect dari sini, biarkan halaman tampil dengan pesan error jika ada
}
?>

<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0"><?php echo htmlspecialchars($page_title); ?></h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="<?php echo rtrim($app_base_path, '/'); ?>/dashboard.php">Home</a></li>
          <li class="breadcrumb-item"><a href="<?php echo rtrim($app_base_path, '/'); ?>/admin/users/daftar_pengguna.php">Manajemen Pengguna</a></li>
          <li class="breadcrumb-item active">Peran Anggota</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="card card-outline card-primary shadow mb-4"> <?php // Warna utama modul pengguna/admin ?>
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-address-book mr-1"></i> <?php echo htmlspecialchars($page_title); ?></h3>
                <div class="card-tools">
                    <a href="tambah_anggota.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-user-tag mr-1"></i> Tambah Peran Anggota
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm table-striped" id="anggotaMasterTable" width="100%" cellspacing="0">
                        <thead>
                             <tr class="text-center">
                                <th style="width: 20px;">No.</th>
                                <th style="width: 50px;">Foto</th>
                                <th>NIK</th>
                                <th>Nama Lengkap</th>
                                <th>Jabatan</th>
                                <th>Peran Sistem</th>
                                <th>Cabor Terkait</th>
                                <th>Tingkat</th>
                                <th style="width: 120px;">Verifikasi</th>
                                <th style="width: 150px;" class="no-export">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($anggota_list_data_final)): $nomor_urut_agt_loop = 1; ?>
                                <?php foreach ($anggota_list_data_final as $agt_item_loop): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $nomor_urut_agt_loop++; ?></td>
                                        <td class="text-center">
                                            <?php
                                            // ========================================================================
                                            // AWAL PERBAIKAN LOGIKA PATH FOTO
                                            // ========================================================================
                                            $url_foto_profil_agt = rtrim($app_base_path, '/') . '/' . ltrim($default_avatar_path_relative, '/'); // Default

                                            if (!empty($agt_item_loop['foto_pengguna'])) {
                                                $path_foto_relatif_pengguna_agt = ltrim($agt_item_loop['foto_pengguna'], '/');
                                                $path_foto_server_pengguna_agt = rtrim(APP_PATH_BASE, '/\\') . '/' . $path_foto_relatif_pengguna_agt;
                                                $path_foto_server_pengguna_agt = preg_replace('/\/+/', '/', $path_foto_server_pengguna_agt);

                                                if (file_exists($path_foto_server_pengguna_agt) && is_file($path_foto_server_pengguna_agt)) {
                                                    $url_foto_profil_agt = rtrim($app_base_path, '/') . '/' . $path_foto_relatif_pengguna_agt;
                                                }
                                            }
                                            $url_foto_profil_agt = preg_replace('/\/+/', '/', $url_foto_profil_agt);
                                            // ========================================================================
                                            // AKHIR PERBAIKAN LOGIKA PATH FOTO
                                            // ========================================================================
                                            ?>
                                            <img src="<?php echo htmlspecialchars($url_foto_profil_agt); ?>" 
                                                 alt="Foto <?php echo htmlspecialchars($agt_item_loop['nama_lengkap']); ?>" 
                                                 class="img-circle img-size-32 elevation-1" 
                                                 style="object-fit: cover; width: 32px; height: 32px;">
                                        </td>
                                        <td><?php echo htmlspecialchars($agt_item_loop['nik']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($agt_item_loop['nama_lengkap']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($agt_item_loop['jabatan']); ?></td>
                                        <td class="text-center">
                                            <?php 
                                            $role_text_agt = str_replace('_', ' ', $agt_item_loop['role']);
                                            $role_badge_agt = 'secondary'; // Default badge
                                            if ($agt_item_loop['role'] == 'super_admin') $role_badge_agt = 'danger';
                                            elseif ($agt_item_loop['role'] == 'admin_koni') $role_badge_agt = 'warning';
                                            elseif ($agt_item_loop['role'] == 'pengurus_cabor') $role_badge_agt = 'info';
                                            elseif ($agt_item_loop['role'] == 'atlet') $role_badge_agt = 'primary';
                                            elseif ($agt_item_loop['role'] == 'pelatih') $role_badge_agt = 'purple'; // Contoh warna kustom
                                            elseif ($agt_item_loop['role'] == 'wasit') $role_badge_agt = 'orange'; // Contoh warna kustom
                                            ?>
                                            <span class="badge badge-<?php echo $role_badge_agt; ?>"><?php echo htmlspecialchars(ucwords($role_text_agt)); ?></span>
                                        </td>
                                        <td><?php echo $agt_item_loop['nama_cabor'] ? htmlspecialchars($agt_item_loop['nama_cabor']) : '<em>N/A</em>'; ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars(ucfirst($agt_item_loop['tingkat_pengurus'] ?? '-')); ?></td>
                                        <td class="text-center">
                                            <?php
                                            $verifikasi_badge_agt = ($agt_item_loop['is_verified'] == 1) ? 'success' : 'warning';
                                            $verifikasi_text_agt = ($agt_item_loop['is_verified'] == 1) ? 'Terverifikasi' : 'Pending';
                                            $tooltip_verif_text_agt = '';
                                            if($agt_item_loop['is_verified'] == 1 && !empty($agt_item_loop['verified_at'])) {
                                                $nama_verifier_agt_display = !empty($agt_item_loop['nama_verifier']) ? $agt_item_loop['nama_verifier'] : (!empty($agt_item_loop['verified_by_nik']) ? 'NIK: ' . $agt_item_loop['verified_by_nik'] : 'Sistem');
                                                $tooltip_verif_text_agt = "Diverifikasi oleh " . htmlspecialchars($nama_verifier_agt_display) . " pada " . date('d M Y, H:i', strtotime($agt_item_loop['verified_at']));
                                            } elseif (($agt_item_loop['is_verified'] ?? 0) == 0) {
                                                $tooltip_verif_text_agt = "Peran ini menunggu verifikasi.";
                                            }
                                            ?>
                                            <span class="badge badge-<?php echo $verifikasi_badge_agt; ?> p-1" <?php if(!empty($tooltip_verif_text_agt)) echo 'data-toggle="tooltip" title="' . $tooltip_verif_text_agt . '"'; ?> >
                                                <?php echo htmlspecialchars($verifikasi_text_agt); ?>
                                            </span>
                                        </td>
                                        
                                        <td class="text-center" style="white-space: nowrap; vertical-align: middle;">
                                            <?php 
                                            $id_anggota_item_aksi_loop = $agt_item_loop['id_anggota'];
                                            $jabatan_item_aksi_loop = htmlspecialchars(addslashes($agt_item_loop['jabatan']));
                                            $nama_lengkap_item_aksi_loop = htmlspecialchars(addslashes($agt_item_loop['nama_lengkap']));
                                            $is_main_sa_role_item_aksi = ($agt_item_loop['nik'] == NIK_SUPER_ADMIN_UTAMA_ANGGOTA && $agt_item_loop['role'] == 'super_admin');
                                            ?>
                                            <a href="detail_anggota.php?id_anggota=<?php echo $id_anggota_item_aksi_loop; ?>" class="btn btn-info btn-xs mr-1" title="Lihat Detail Peran Anggota"><i class="fas fa-eye"></i></a>
                                            <a href="edit_anggota.php?id_anggota=<?php echo $id_anggota_item_aksi_loop; ?>" class="btn btn-warning btn-xs mr-1" title="Edit Peran Anggota"><i class="fas fa-edit"></i></a>
                                            
                                            <?php if (!$is_main_sa_role_item_aksi): // Peran Super Admin utama tidak bisa diverifikasi/dihapus dari sini ?>
                                                <?php if (($agt_item_loop['is_verified'] ?? 0) == 0): ?>
                                                    <a href="proses_verifikasi_anggota.php?id_anggota=<?php echo $id_anggota_item_aksi_loop; ?>&action=verify" 
                                                       class="btn btn-success btn-xs mr-1" title="Verifikasi Peran Ini" 
                                                       onclick="return confirm('Verifikasi peran \'<?php echo $jabatan_item_aksi_loop; ?>\' untuk <?php echo $nama_lengkap_item_aksi_loop; ?>?');">
                                                       <i class="fas fa-check-circle"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="proses_verifikasi_anggota.php?id_anggota=<?php echo $id_anggota_item_aksi_loop; ?>&action=unverify" 
                                                       class="btn btn-secondary btn-xs mr-1" title="Batalkan Verifikasi Peran Ini"
                                                       onclick="return confirm('Batalkan verifikasi peran \'<?php echo $jabatan_item_aksi_loop; ?>\' untuk <?php echo $nama_lengkap_item_aksi_loop; ?>?');">
                                                       <i class="fas fa-times-circle"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <a href="hapus_anggota.php?id_anggota=<?php echo $id_anggota_item_aksi_loop; ?>" 
                                                   class="btn btn-danger btn-xs" title="Hapus Peran Anggota Ini" 
                                                   onclick="return confirm('PERHATIAN! Yakin ingin menghapus peran \'<?php echo $jabatan_item_aksi_loop; ?>\' (<?php echo htmlspecialchars(addslashes($agt_item_loop['role'])); ?>) untuk <?php echo $nama_lengkap_item_aksi_loop; ?>? Ini hanya menghapus penetapan peran ini, bukan akun pengguna.');">
                                                   <i class="fas fa-user-times"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="10" class="text-center">Belum ada data peran anggota yang ditambahkan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$id_tabel_js_anggota_final = "anggotaMasterTable";
// JavaScript untuk DataTables (tidak ada perubahan signifikan, sudah baik)
$inline_script = "
$(document).ready(function() {
    if ( $.fn.DataTable && $('#" . $id_tabel_js_anggota_final . "').length ) {
        if (!$.fn.DataTable.isDataTable('#" . $id_tabel_js_anggota_final . "')) {
            var anggotaTableDT = $('#" . $id_tabel_js_anggota_final . "').DataTable({
                \"responsive\": true, \"lengthChange\": true, \"autoWidth\": false,
                \"buttons\": [
                    { extend: 'copy', text: '<i class=\"fas fa-copy mr-1\"></i> Salin', exportOptions: { columns: ':visible:not(.no-export)' }, className: 'btn-sm' },
                    { extend: 'csv', text: '<i class=\"fas fa-file-csv mr-1\"></i> CSV', exportOptions: { columns: ':visible:not(.no-export)' }, className: 'btn-sm' },
                    { extend: 'excel', text: '<i class=\"fas fa-file-excel mr-1\"></i> Excel', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Peran Anggota Sistem', className: 'btn-sm' },
                    { extend: 'pdf', text: '<i class=\"fas fa-file-pdf mr-1\"></i> PDF', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Peran Anggota Sistem', orientation: 'landscape', pageSize: 'LEGAL', className: 'btn-sm' },
                    { extend: 'print', text: '<i class=\"fas fa-print mr-1\"></i> Cetak', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Peran Anggota Sistem', className: 'btn-sm' },
                    { extend: 'colvis', text: '<i class=\"fas fa-columns mr-1\"></i> Kolom', className: 'btn-sm' }
                ],
                \"language\": {
                    \"search\": \"\", 
                    \"searchPlaceholder\": \"Ketik untuk mencari...\",
                    \"lengthMenu\": \"Tampilkan _MENU_ data per halaman\", 
                    \"info\": \"Menampilkan _START_ sampai _END_ dari _TOTAL_ data\",
                    \"infoEmpty\": \"Menampilkan 0 sampai 0 dari 0 data\", 
                    \"infoFiltered\": \"(difilter dari _MAX_ total data)\",
                    \"zeroRecords\": \"Tidak ditemukan data yang cocok\",
                    \"paginate\": { \"first\": \"<i class='fas fa-angle-double-left'></i>\", \"last\": \"<i class='fas fa-angle-double-right'></i>\", \"next\": \"<i class='fas fa-angle-right'></i>\", \"previous\": \"<i class='fas fa-angle-left'></i>\" }
                },
                \"order\": [[3, 'asc']], 
                \"columnDefs\": [ 
                    { \"orderable\": false, \"targets\": [0, 1, 9] }, 
                    { \"searchable\": false, \"targets\": [0, 1] },   
                    { \"className\": \"text-center align-middle\", \"targets\": [0, 1, 5, 7, 8, 9] },
                    { \"className\": \"align-middle\", \"targets\": [2, 3, 4, 6] } 
                ],
                \"dom\":  \"<'row'<'col-sm-12 col-md-3'l><'col-sm-12 col-md-6 text-center'B><'col-sm-12 col-md-3'f>>\" + \"<'row'<'col-sm-12'tr>>\" + \"<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>\",
                \"initComplete\": function(settings, json) {
                    $('[data-toggle=\"tooltip\"]').tooltip();
                    $('#" . $id_tabel_js_anggota_final . "_filter input').css({'width': '100%', 'margin-left': '0px'}).addClass('form-control-sm').attr('placeholder', 'Cari di semua kolom...');
                }
            });
        }
    }
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>