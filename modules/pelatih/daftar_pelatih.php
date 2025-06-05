<?php
// File: modules/pelatih/daftar_pelatih.php (REVISI dengan Header Simpel & Persiapan Filter DT)

$page_title = "Manajemen Profil Pelatih";

// ... (Aset CSS & JS tetap sama seperti sebelumnya) ...
$additional_css = [
    'assets/adminlte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css',
    'assets/adminlte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css',
    'assets/adminlte/plugins/datatables-buttons/css/buttons.bootstrap4.min.css',
    'assets/adminlte/plugins/select2/css/select2.min.css', // Tetap ada jika ingin custom filter Select2
    'assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
];
$additional_js = [
    'assets/adminlte/plugins/datatables/jquery.dataTables.min.js',
    'assets/adminlte/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js',
    'assets/adminlte/plugins/datatables-responsive/js/dataTables.responsive.min.js',
    'assets/adminlte/plugins/datatables-buttons/js/dataTables.buttons.min.js',
    'assets/adminlte/plugins/jszip/jszip.min.js',
    'assets/adminlte/plugins/pdfmake/pdfmake.min.js',
    'assets/adminlte/plugins/pdfmake/vfs_fonts.js',
    'assets/adminlte/plugins/datatables-buttons/js/buttons.html5.min.js',
    'assets/adminlte/plugins/datatables-buttons/js/buttons.print.min.js',
    'assets/adminlte/plugins/datatables-buttons/js/buttons.colVis.min.js',
    'assets/adminlte/plugins/select2/js/select2.full.min.js', // Tetap ada
];

require_once(__DIR__ . '/../../core/header.php');

// ... (Pengecekan sesi, hak akses, pengambilan data $cabor_list_for_filter tetap sama) ...
if (!isset($pdo, $user_role_utama, $user_nik, $app_base_path)) { exit("Error konfigurasi."); }
$can_add_profil_pelatih = in_array($user_role_utama, ['super_admin', 'admin_koni']);
// ...

// Filter GET yang mungkin masih relevan untuk query awal (opsional, bisa juga full client-side)
$filter_status_approval_profil_get = isset($_GET['status_approval_profil']) ? trim($_GET['status_approval_profil']) : null;
$allowed_statuses_profil = ['pending', 'disetujui', 'ditolak', 'revisi'];


try {
    // Query utama disederhanakan, filter lebih lanjut bisa oleh DataTables
    $sql_profil_pelatih = "SELECT plt.id_pelatih, p.nama_lengkap, p.nik, p.foto AS foto_pengguna_utama,
                                  plt.foto_pelatih_profil, plt.kontak_pelatih_alternatif, 
                                  plt.status_approval, plt.alasan_penolakan,
                                  approver.nama_lengkap as nama_approver_profil
                           FROM pelatih plt
                           JOIN pengguna p ON plt.nik = p.nik
                           LEFT JOIN pengguna approver ON plt.approved_by_nik = approver.nik";
    
    $conditions_profil_p = [];
    $params_profil_p = [];

    // Contoh filter awal dari GET jika masih ingin dipertahankan di sisi server
    if ($filter_status_approval_profil_get && in_array($filter_status_approval_profil_get, $allowed_statuses_profil)) {
        $conditions_profil_p[] = "plt.status_approval = :status_profil_get";
        $params_profil_p[':status_profil_get'] = $filter_status_approval_profil_get;
    }
    // Filter cabor dilatih akan lebih kompleks jika dari server, mungkin lebih baik client-side atau filter terpisah

    if (!empty($conditions_profil_p)) {
        $sql_profil_pelatih .= " WHERE " . implode(" AND ", $conditions_profil_p);
    }
    $sql_profil_pelatih .= " ORDER BY p.nama_lengkap ASC";

    $stmt_profil_p = $pdo->prepare($sql_profil_pelatih);
    $stmt_profil_p->execute($params_profil_p);
    $daftar_pelatih_raw_profil = $stmt_profil_p->fetchAll(PDO::FETCH_ASSOC);

    // ... (Logika pengambilan cabor_dilatih_map dan pemrosesan $daftar_pelatih_final tetap sama) ...
    $cabor_dilatih_map = []; /* ... */ $daftar_pelatih_final = []; /* ... */
    // (Salin dari kode sebelumnya)
    if (!empty($daftar_pelatih_raw_profil)) {
        $pelatih_ids = array_column($daftar_pelatih_raw_profil, 'id_pelatih');
        if (!empty($pelatih_ids)) {
            $placeholders = implode(',', array_fill(0, count($pelatih_ids), '?'));
            $sql_cabor_latih = "SELECT lp.id_pelatih, GROUP_CONCAT(DISTINCT co.nama_cabor ORDER BY co.nama_cabor SEPARATOR ', ') AS daftar_cabor_dilatih
                                FROM lisensi_pelatih lp JOIN cabang_olahraga co ON lp.id_cabor = co.id_cabor
                                WHERE lp.id_pelatih IN ($placeholders) AND lp.status_approval = 'disetujui_admin' GROUP BY lp.id_pelatih";
            $stmt_cabor_latih = $pdo->prepare($sql_cabor_latih);
            $stmt_cabor_latih->execute($pelatih_ids);
            $cabor_dilatih_map = $stmt_cabor_latih->fetchAll(PDO::FETCH_KEY_PAIR);
        }
    }
    $base_url_for_image_check_rev = rtrim($app_base_path, '/');
    $doc_root_check_rev = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
    foreach ($daftar_pelatih_raw_profil as $pelatih_item) {
        $pelatih_item['cabor_dilatih_display'] = $cabor_dilatih_map[$pelatih_item['id_pelatih']] ?? '<em>Belum ada lisensi cabor aktif</em>';
        $fields_penting_profil = 1; $fields_terisi_profil = 1; 
        if (!empty(trim($pelatih_item['kontak_pelatih_alternatif'] ?? ''))) { $fields_terisi_profil++; } $fields_penting_profil++; 
        $ada_foto_valid_profil = false;
        if (!empty($pelatih_item['foto_pelatih_profil']) && file_exists($doc_root_check_rev . $base_url_for_image_check_rev . '/' . ltrim($pelatih_item['foto_pelatih_profil'], '/'))) { $ada_foto_valid_profil = true; }
        elseif (!empty($pelatih_item['foto_pengguna_utama']) && file_exists($doc_root_check_rev . $base_url_for_image_check_rev . '/' . ltrim($pelatih_item['foto_pengguna_utama'], '/'))) { $ada_foto_valid_profil = true; }
        if ($ada_foto_valid_profil) { $fields_terisi_profil++; } $fields_penting_profil++;
        $pelatih_item['progress_kelengkapan'] = ($fields_penting_profil > 0) ? round(($fields_terisi_profil / $fields_penting_profil) * 100) : 0;
        if ($pelatih_item['progress_kelengkapan'] < 50) { $pelatih_item['progress_color'] = 'bg-danger'; }
        elseif ($pelatih_item['progress_kelengkapan'] < 85) { $pelatih_item['progress_color'] = 'bg-warning'; }
        else { $pelatih_item['progress_color'] = 'bg-success'; }
        $daftar_pelatih_final[] = $pelatih_item;
    }


} catch (PDOException $e) { /* ... (error handling) ... */ }
?>

<section class="content">
    <div class="container-fluid">
        <?php include(__DIR__ . '/../../core/partials/global_feedback_messages.php'); ?>

        <div class="card card-purple card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chalkboard-teacher mr-1"></i> <?php echo $page_title; ?>
                </h3>
                <div class="card-tools">
                    <?php if ($can_add_profil_pelatih): ?>
                        <a href="tambah_pelatih.php" class="btn btn-success btn-sm">
                            <i class="fas fa-user-plus mr-1"></i> Tambah Profil Pelatih
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php // Filter custom bisa diletakkan di sini, di luar card-tools DataTables jika diinginkan ?>
                <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni'])): ?>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="customFilterCaborLatih" class="text-sm">Filter Cabor Dilatih:</label>
                        <select id="customFilterCaborLatih" class="form-control form-control-sm select2bs4-custom-filter" data-column-index="4"> <?php // Indeks kolom Cabor Dilatih ?>
                            <option value="">Semua Cabor</option>
                            <?php foreach ($cabor_list_for_filter as $cabor_f): ?>
                                <option value="<?php echo htmlspecialchars($cabor_f['nama_cabor']); ?>"><?php echo htmlspecialchars($cabor_f['nama_cabor']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="customFilterStatusProfil" class="text-sm">Filter Status Profil:</label>
                        <select id="customFilterStatusProfil" class="form-control form-control-sm select2bs4-custom-filter" data-column-index="6"> <?php // Indeks kolom Status Profil ?>
                            <option value="">Semua Status</option>
                            <?php foreach ($allowed_statuses_profil as $status_opt_f): ?>
                                <option value="<?php echo htmlspecialchars(ucfirst($status_opt_f)); ?>"><?php echo htmlspecialchars(ucfirst($status_opt_f)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm table-striped" id="tabelProfilPelatih">
                        <thead>
                             <tr class="text-center">
                                <th>No.</th>
                                <th>Foto</th>
                                <th>Nama Lengkap</th>
                                <th>NIK</th>
                                <th>Cabor Dilatih (Lisensi Aktif)</th>
                                <th>Kelengkapan Profil</th>
                                <th>Status Profil</th>
                                <th class="no-export">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($daftar_pelatih_final)): ?>
                                <?php foreach ($daftar_pelatih_final as $index => $pelatih): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $index + 1; ?></td>
                                        <td class="text-center">
                                            <?php 
                                            $base_url_img_rev = rtrim($app_base_path, '/');
                                            $default_img_url_rev = $base_url_img_rev . '/assets/adminlte/dist/img/kepitran.jpg';
                                            $foto_url_to_display_rev = $default_img_url_rev;
                                            if (!empty($pelatih['foto_pelatih_profil'])) {
                                                $path_rel_pel_rev = '/' . ltrim($pelatih['foto_pelatih_profil'], '/');
                                                if (file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $base_url_img_rev . $path_rel_pel_rev)) {
                                                    $foto_url_to_display_rev = $base_url_img_rev . $path_rel_pel_rev;
                                                }
                                            } 
                                            elseif (!empty($pelatih['foto_pengguna_utama'])) {
                                                $path_rel_peng_rev = '/' . ltrim($pelatih['foto_pengguna_utama'], '/');
                                                if (file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $base_url_img_rev . $path_rel_peng_rev)) {
                                                    $foto_url_to_display_rev = $base_url_img_rev . $path_rel_peng_rev;
                                                }
                                            }
                                            ?>
                                            <img src="<?php echo htmlspecialchars($foto_url_to_display_rev); ?>" 
                                                 alt="Foto <?php echo htmlspecialchars($pelatih['nama_lengkap']); ?>" 
                                                 class="img-circle img-size-32 elevation-1" 
                                                 style="object-fit: cover; width: 32px; height: 32px;">
                                        </td>
                                        <td><strong><a href="detail_pelatih.php?id_pelatih=<?php echo $pelatih['id_pelatih']; ?>"><?php echo htmlspecialchars($pelatih['nama_lengkap']); ?></a></strong></td>
                                        <td class="text-center"><?php echo htmlspecialchars($pelatih['nik']); ?></td>
                                        <td><?php echo $pelatih['cabor_dilatih_display']; ?></td>
                                        <td> 
                                            <div class="progress progress-xs" title="<?php echo $pelatih['progress_kelengkapan']; ?>% Profil Lengkap">
                                                <div class="progress-bar <?php echo htmlspecialchars($pelatih['progress_color']); ?>" role="progressbar" style="width: <?php echo $pelatih['progress_kelengkapan']; ?>%"></div>
                                            </div>
                                            <small class="text-muted d-block text-center"><?php echo $pelatih['progress_kelengkapan']; ?>%</small>
                                        </td>
                                        <td class="text-center">
                                            <?php /* ... logika badge status profil ... */ 
                                                $status_text_profil_rev = ucfirst($pelatih['status_approval']);
                                                $status_badge_profil_rev = 'secondary';
                                                if ($pelatih['status_approval'] == 'disetujui') { $status_badge_profil_rev = 'success'; }
                                                elseif ($pelatih['status_approval'] == 'ditolak') { $status_badge_profil_rev = 'danger'; }
                                                elseif (in_array($pelatih['status_approval'], ['pending', 'revisi'])) { $status_badge_profil_rev = 'warning'; }
                                                echo "<span class='badge badge-{$status_badge_profil_rev} p-1'>{$status_text_profil_rev}</span>";
                                                if (!empty($pelatih['alasan_penolakan']) && in_array($pelatih['status_approval'], ['ditolak', 'revisi'])) {
                                                    echo "<i class='fas fa-info-circle ml-1 text-danger' data-toggle='tooltip' title='Alasan: ".htmlspecialchars($pelatih['alasan_penolakan'])."'></i>";
                                                }
                                            ?>
                                        </td>
                                        <td class="text-center project-actions">
                                            <?php /* ... logika tombol aksi ... */ 
                                                echo '<a href="detail_pelatih.php?id_pelatih='.$pelatih['id_pelatih'].'" class="btn btn-xs btn-primary mr-1 mb-1" title="Detail"><i class="fas fa-eye"></i></a>';
                                                if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
                                                    echo '<a href="edit_pelatih.php?id_pelatih='.$pelatih['id_pelatih'].'" class="btn btn-xs btn-info mr-1 mb-1" title="Edit Profil"><i class="fas fa-pencil-alt"></i></a>';
                                                    if (in_array($pelatih['status_approval'], ['pending', 'revisi'])) {
                                                        echo '<button type="button" class="btn btn-xs btn-success mr-1 mb-1" title="Setujui Profil" onclick="handleProfilPelatihApproval('.$pelatih['id_pelatih'].', \''.htmlspecialchars(addslashes($pelatih['nama_lengkap'])).'\', \'disetujui\')"><i class="fas fa-check"></i></button>';
                                                    }
                                                    if (in_array($pelatih['status_approval'], ['pending', 'disetujui', 'revisi'])) {
                                                        echo '<button type="button" class="btn btn-xs btn-danger mr-1 mb-1" title="Tolak Profil" onclick="handleProfilPelatihApproval('.$pelatih['id_pelatih'].', \''.htmlspecialchars(addslashes($pelatih['nama_lengkap'])).'\', \'ditolak\')"><i class="fas fa-times"></i></button>';
                                                        if ($pelatih['status_approval'] != 'revisi') {
                                                            echo '<button type="button" class="btn btn-xs btn-warning mr-1 mb-1" title="Minta Revisi" onclick="handleProfilPelatihApproval('.$pelatih['id_pelatih'].', \''.htmlspecialchars(addslashes($pelatih['nama_lengkap'])).'\', \'revisi\')"><i class="fas fa-undo"></i></button>';
                                                        }
                                                    }
                                                }
                                                if ($user_role_utama === 'super_admin') {
                                                    echo '<a href="hapus_pelatih.php?id_pelatih='.$pelatih['id_pelatih'].'" class="btn btn-xs btn-dark mb-1" title="Hapus Profil" onclick="return confirm(\'Yakin hapus profil ini?\')"><i class="fas fa-trash"></i></a>';
                                                }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center"><em>Belum ada data profil pelatih.</em></td></tr>
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
    var tabelProfilPelatih = $('#tabelProfilPelatih').DataTable({
        \"responsive\": true, \"lengthChange\": true, \"autoWidth\": false,
        \"buttons\": [
            { extend: 'copy', text: '<i class=\"fas fa-copy mr-1\"></i> Salin', className: 'btn-sm btn-default', titleAttr: 'Salin', exportOptions: { columns: ':visible:not(.no-export)' } },
            { extend: 'csv', text: '<i class=\"fas fa-file-csv mr-1\"></i> CSV', className: 'btn-sm btn-default', titleAttr: 'CSV', exportOptions: { columns: ':visible:not(.no-export)' } },
            { extend: 'excel', text: '<i class=\"fas fa-file-excel mr-1\"></i> Excel', className: 'btn-sm btn-default', titleAttr: 'Excel', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Profil Pelatih' },
            { extend: 'pdf', text: '<i class=\"fas fa-file-pdf mr-1\"></i> PDF', className: 'btn-sm btn-default', titleAttr: 'PDF', orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Profil Pelatih' },
            { extend: 'print', text: '<i class=\"fas fa-print mr-1\"></i> Cetak', className: 'btn-sm btn-default', titleAttr: 'Cetak', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Profil Pelatih' },
            { extend: 'colvis', text: '<i class=\"fas fa-columns mr-1\"></i> Kolom', className: 'btn-sm btn-default', titleAttr: 'Kolom' }
        ],
        \"language\": {
            \"search\": \"\", \"searchPlaceholder\": \"Cari Nama/NIK...\",
            \"lengthMenu\": \"Tampilkan _MENU_ entri\", \"info\": \"Menampilkan _START_ s.d _END_ dari _TOTAL_ profil\",
            \"infoEmpty\": \"Tidak ada profil\", \"infoFiltered\": \"(difilter dari _MAX_ total profil)\",
            \"zeroRecords\": \"Tidak ada profil yang cocok\",
            \"paginate\": { \"first\": \"<<\", \"last\": \">>\", \"next\": \">\", \"previous\": \"<\" }
        },
        \"order\": [[2, 'asc']], 
        \"columnDefs\": [ 
            { \"orderable\": false, \"targets\": [0, 1, 4, 5, 7] }, // Sesuaikan target yang tidak bisa diorder
            { \"searchable\": true, \"targets\": [2, 3] }    
        ],
        \"dom\":  \"<'row'<'col-sm-12 col-md-3'l><'col-sm-12 col-md-6 text-center'B><'col-sm-12 col-md-3'f>>\" +
                  \"<'row'<'col-sm-12'tr>>\" +
                  \"<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>\",
        \"initComplete\": function(settings, json) { 
            $('[data-toggle=\"tooltip\"]').tooltip(); 
            $('#tabelProfilPelatih_filter input').addClass('form-control-sm');
        }
    });

    // Inisialisasi Select2 untuk filter custom
    $('.select2bs4-custom-filter').select2({ 
        theme: 'bootstrap4', 
        allowClear: true, 
        placeholder: $(this).data('placeholder') || 'Semua',
        width: '100%' // Agar mengisi lebar kolomnya
    });

    // Event handler untuk filter custom
    $('#customFilterCaborLatih, #customFilterStatusProfil').on('change', function () {
        varcolumnIndex = parseInt($(this).data('column-index'));
        var val = $.fn.dataTable.util.escapeRegex($(this).val());
        tabelProfilPelatih.column(columnIndex)
            .search(val ? '^' + val + '$' : '', true, false) // Cari exact match, atau kosongkan jika ''
            .draw();
    });


    window.handleProfilPelatihApproval = function(idPelatih, namaPelatih, newStatus) {
        // ... (kode JS handleProfilPelatihApproval dari respons sebelumnya, pastikan action ke proses_edit_pelatih.php) ...
    };
});
";
require_once(__DIR__ . '/../../core/footer.php');
?>