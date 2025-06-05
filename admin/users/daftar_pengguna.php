<?php
// File: reaktorsystem/admin/users/daftar_pengguna.php
$page_title = "Manajemen Pengguna Sistem";

// --- Definisi Aset CSS & JS Tambahan ---
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

require_once(__DIR__ . '/../../core/header.php');

// Pengecekan sesi & konfigurasi (standar)
if (!isset($pdo) || !isset($user_role_utama) || !isset($user_nik) || !isset($app_base_path)) {
    echo "<!DOCTYPE html><html><head><title>Error Konfigurasi</title>"; 
    if (isset($app_base_path)) {
        echo "<link rel='stylesheet' href='" . htmlspecialchars(rtrim($app_base_path, '/') . '/assets/adminlte/dist/css/adminlte.min.css') . "'>";
    }
    echo "</head><body class='hold-transition sidebar-mini'><div class='wrapper'><section class='content'><div class='container-fluid'>";
    echo "<div class='alert alert-danger text-center mt-5 p-3'><strong>Error Kritis:</strong> Sesi tidak valid, konfigurasi aplikasi bermasalah, atau koneksi database gagal.<br>Harap hubungi administrator sistem.</div>";
    echo "</div></section></div></body></html>";
    if (file_exists(__DIR__ . '/../../core/footer.php')) { $inline_script = $inline_script ?? ''; require_once(__DIR__ . '/../../core/footer.php'); }
    exit();
}

// Pengecekan peran pengguna
if (!in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    $_SESSION['pesan_error_global'] = "Anda tidak memiliki izin untuk mengakses halaman Manajemen Pengguna.";
    header("Location: " . rtrim($app_base_path, '/') . "/dashboard.php");
    exit();
}

// --- PENGAMBILAN DATA PENGGUNA DARI DATABASE ---
$pengguna_list_data = [];
try {
    $sql_pengguna = "SELECT p.nik, p.nama_lengkap, p.email, p.nomor_telepon, p.foto, p.is_approved, p.created_at, 
                            (SELECT GROUP_CONCAT(ag.role SEPARATOR ', ') FROM anggota ag WHERE ag.nik = p.nik) AS roles_anggota
                     FROM pengguna p
                     ORDER BY p.nama_lengkap ASC"; 

    $stmt_pengguna_list = $pdo->query($sql_pengguna);
    $pengguna_list_data = $stmt_pengguna_list->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Daftar Pengguna Error: " . $e->getMessage());
    $_SESSION['pesan_error_global'] = "Terjadi kesalahan saat mengambil data pengguna.";
}

$doc_root_users = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
$base_path_url_users = rtrim($app_base_path, '/'); // $app_base_path dari init_core.php, contoh: "/" atau "/reaktorsystem" (tanpa slash di akhir)
?>

<section class="content">
    <div class="container-fluid">
        <div class="card card-outline card-primary shadow mb-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-users-cog mr-1"></i> <?php echo htmlspecialchars($page_title); ?></h3>
                <div class="card-tools">
                    <a href="form_tambah_pengguna.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-user-plus mr-1"></i> Tambah Pengguna Baru
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm table-striped" id="penggunaMasterTable" width="100%" cellspacing="0">
                        <thead>
                             <tr class="text-center">
                                 <th style="width: 20px;">No.</th>
                                 <th style="width: 50px;">Foto</th>
                                 <th>NIK</th>
                                 <th>Nama Lengkap</th>
                                 <th>Email</th>
                                 <th>Peran Sistem</th>
                                 <th style="width: 100px;">Status Akun</th>
                                 <th style="width: 160px;" class="no-export">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($pengguna_list_data)): $nomor_urut_pgn = 1; ?>
                                <?php foreach ($pengguna_list_data as $pgn_item): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $nomor_urut_pgn++; ?></td>
                                        <td class="text-center">
                                            <?php
                                            // Variabel $doc_root_users dan $base_path_url_users sudah didefinisikan di atas loop.
                                            // Variabel $default_avatar_path juga sudah tersedia dari init_core.php 
                                            // (misalnya 'assets/adminlte/dist/img/kepitran.jpg')

                                            $path_untuk_url_gambar_final = ''; // Inisialisasi variabel untuk path URL gambar

                                            if (!empty($pgn_item['foto'])) {
                                                // Path absolut di server untuk memeriksa keberadaan file
                                                $path_file_di_server_untuk_cek = $doc_root_users . $base_path_url_users . '/' . ltrim($pgn_item['foto'], '/');
                                                $path_file_di_server_untuk_cek = preg_replace('/\/+/', '/', $path_file_di_server_untuk_cek); // Normalisasi slash

                                                if (file_exists($path_file_di_server_untuk_cek)) {
                                                    // Jika foto pengguna ada dan file-nya ditemukan, gunakan path foto pengguna
                                                    $path_untuk_url_gambar_final = $base_path_url_users . '/' . ltrim($pgn_item['foto'], '/');
                                                } else {
                                                    // Jika foto pengguna ada di DB tapi file-nya tidak ditemukan di server, gunakan foto default
                                                    $path_untuk_url_gambar_final = $base_path_url_users . '/' . ltrim($default_avatar_path, '/');
                                                }
                                            } else {
                                                // Jika tidak ada data foto pengguna di DB, gunakan foto default
                                                $path_untuk_url_gambar_final = $base_path_url_users . '/' . ltrim($default_avatar_path, '/');
                                            }
                                            
                                            // Normalisasi slash untuk URL final
                                            $path_untuk_url_gambar_final = preg_replace('/\/+/', '/', $path_untuk_url_gambar_final);
                                            ?>
                                            <img src="<?php echo htmlspecialchars($path_untuk_url_gambar_final); ?>" alt="Foto <?php echo htmlspecialchars($pgn_item['nama_lengkap']); ?>" class="img-circle img-size-32 elevation-1" style="object-fit: cover; width: 32px; height: 32px;">
                                        </td>
                                        <td><?php echo htmlspecialchars($pgn_item['nik']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($pgn_item['nama_lengkap']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($pgn_item['email'] ?? '-'); ?></td>
                                        <td><?php echo !empty($pgn_item['roles_anggota']) ? htmlspecialchars(ucwords(str_replace('_', ' ', $pgn_item['roles_anggota']))) : '<span class="text-muted"><em>Belum ada peran</em></span>'; ?></td>
                                        <td class="text-center">
                                            <?php
                                            $status_badge_pgn_tbl = ($pgn_item['is_approved'] == 1) ? 'success' : 'warning';
                                            $status_text_pgn_tbl = ($pgn_item['is_approved'] == 1) ? 'Disetujui' : 'Pending';
                                            ?>
                                            <span class="badge badge-<?php echo $status_badge_pgn_tbl; ?> p-1"><?php echo htmlspecialchars($status_text_pgn_tbl); ?></span>
                                        </td>
                                        <td class="text-center" style="white-space: nowrap;">
                                            
                                            <a href="detail_pengguna.php?nik=<?php echo htmlspecialchars($pgn_item['nik']); ?>" class="btn btn-info btn-xs mr-1" title="Detail Pengguna"><i class="fas fa-eye"></i></a>
                                            
                                            <a href="form_edit_pengguna.php?nik=<?php echo htmlspecialchars($pgn_item['nik']); ?>" class="btn btn-warning btn-xs mr-1" title="Edit Pengguna"><i class="fas fa-edit"></i></a>
                                            <?php if ($pgn_item['is_approved'] == 0): ?>
                                                <a href="proses_approve_pengguna.php?nik=<?php echo htmlspecialchars($pgn_item['nik']); ?>&action=approve" class="btn btn-success btn-xs mr-1" title="Setujui Akun Pengguna" onclick="return confirm('Apakah Anda yakin ingin menyetujui pengguna ini: <?php echo htmlspecialchars(addslashes($pgn_item['nama_lengkap'])); ?>?');"><i class="fas fa-user-check"></i></a>
                                            <?php else: ?>
                                                <?php if ($user_role_utama == 'super_admin' && $pgn_item['nik'] != $user_nik): ?>
                                                <a href="proses_approve_pengguna.php?nik=<?php echo htmlspecialchars($pgn_item['nik']); ?>&action=suspend" class="btn btn-secondary btn-xs mr-1" title="Tangguhkan Akun Pengguna" onclick="return confirm('Apakah Anda yakin ingin menangguhkan pengguna ini: <?php echo htmlspecialchars(addslashes($pgn_item['nama_lengkap'])); ?>?');"><i class="fas fa-user-lock"></i></a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($user_role_utama == 'super_admin' && $pgn_item['nik'] != $user_nik): ?>
                                                <a href="proses_hapus_pengguna.php?nik=<?php echo htmlspecialchars($pgn_item['nik']); ?>" class="btn btn-danger btn-xs" title="Hapus Pengguna" onclick="return confirm('PERHATIAN! Menghapus pengguna ini bersifat permanen dan akan mempengaruhi semua data terkait. Yakin ingin menghapus pengguna <?php echo htmlspecialchars(addslashes($pgn_item['nama_lengkap'])); ?>?');"><i class="fas fa-trash-alt"></i></a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center">Belum ada pengguna terdaftar.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$id_tabel_js_pengguna = "penggunaMasterTable";
$inline_script = "
$(document).ready(function() {
    if ( $.fn.DataTable && $('#" . $id_tabel_js_pengguna . "').length ) {
        if (!$.fn.DataTable.isDataTable('#" . $id_tabel_js_pengguna . "')) {
            var penggunaTable = $('#" . $id_tabel_js_pengguna . "').DataTable({
                \"responsive\": true, 
                \"lengthChange\": true, 
                \"autoWidth\": false,
                \"buttons\": [
                    { extend: 'copy', text: '<i class=\"fas fa-copy\"></i> Salin', exportOptions: { columns: ':visible:not(.no-export)' } },
                    { extend: 'csv', text: '<i class=\"fas fa-file-csv\"></i> CSV', exportOptions: { columns: ':visible:not(.no-export)' } },
                    { extend: 'excel', text: '<i class=\"fas fa-file-excel\"></i> Excel', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Pengguna Sistem' },
                    { extend: 'pdf', text: '<i class=\"fas fa-file-pdf\"></i> PDF', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Pengguna Sistem', orientation: 'landscape' },
                    { extend: 'print', text: '<i class=\"fas fa-print\"></i> Cetak', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Pengguna Sistem' },
                    { extend: 'colvis', text: '<i class=\"fas fa-columns\"></i> Kolom' }
                ],
                \"language\": {
                    \"search\": \"\", 
                    \"searchPlaceholder\": \"Ketik untuk mencari pengguna...\",
                    \"lengthMenu\": \"Tampilkan _MENU_ pengguna\", 
                    \"info\": \"Menampilkan _START_ s/d _END_ dari _TOTAL_ pengguna\",
                    \"infoEmpty\": \"Tidak ada pengguna\", 
                    \"infoFiltered\": \"(difilter dari _MAX_ total pengguna)\",
                    \"zeroRecords\": \"Tidak ada pengguna yang cocok dengan pencarian\",
                    \"paginate\": { 
                        \"first\":    \"<i class='fas fa-angle-double-left'></i>\",
                        \"last\":     \"<i class='fas fa-angle-double-right'></i>\",
                        \"next\":     \"<i class='fas fa-angle-right'></i>\",
                        \"previous\": \"<i class='fas fa-angle-left'></i>\"
                    }
                },
                \"order\": [[3, 'asc']], // Default order by Nama Lengkap (indeks 3)
                \"columnDefs\": [ 
                    { \"orderable\": false, \"targets\": [0, 1, 7] }, 
                    { \"searchable\": false, \"targets\": [0, 1] }   
                ],
                \"dom\":  \"<'row'<'col-sm-12 col-md-3'l><'col-sm-12 col-md-6 text-center'B><'col-sm-12 col-md-3'f>>\" +
                          \"<'row'<'col-sm-12'tr>>\" +
                          \"<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>\",
                \"initComplete\": function(settings, json) {
                    $('[data-toggle=\"tooltip\"]').tooltip();
                        $('#" . $id_tabel_js_pengguna . "_filter input')
    .css({
        'width': '100%',
        'margin-left': '0px' 
    })
    .addClass('form-control-sm')
    .attr('placeholder', 'Ketik untuk mencari pengguna...'); 

                    // --- PENAMBAHAN: Custom Filter untuk Status Akun ---
                    var statusColumn = penggunaTable.column(6); // Kolom Status Akun (indeks 6)
                    var selectStatus = $('<select class=\"form-control form-control-sm ml-2\" style=\"width: auto;\"><option value=\"\">Semua Status</option><option value=\"Disetujui\">Disetujui</option><option value=\"Pending\">Pending</option></select>')
                        .appendTo( $('#" . $id_tabel_js_pengguna . "_filter').parent() ) 
                        .on( 'change', function () {
                            var val = $.fn.dataTable.util.escapeRegex(
                                $(this).val()
                            );
                            statusColumn
                                .search( val ? '^'+val+'$' : '', true, false ) 
                                .draw();
                        } );
                }
            });
        }
    }
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>