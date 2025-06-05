<?php
// File: reaktorsystem/modules/klub/daftar_klub.php

$page_title = "Manajemen Klub Olahraga";

// --- Definisi Aset CSS & JS ---
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

// Pastikan variabel global dari init_core.php sudah tersedia
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

$id_cabor_filter_for_pengurus = $id_cabor_pengurus_utama ?? null;
$can_add_klub = false;
if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) { $can_add_klub = true; }
elseif ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_filter_for_pengurus)) { $can_add_klub = true; }

$daftar_klub_processed = [];
$cabang_olahraga_list_filter = []; // Inisialisasi sebagai array kosong
$filter_id_cabor_get_page = isset($_GET['id_cabor']) && filter_var($_GET['id_cabor'], FILTER_VALIDATE_INT) && (int)$_GET['id_cabor'] > 0 ? (int)$_GET['id_cabor'] : null;

try {
    // PERBAIKAN: Ambil daftar cabor untuk filter TERLEBIH DAHULU jika pengguna adalah admin
    if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
        $stmt_cabor_query_for_filter = $pdo->query("SELECT id_cabor, nama_cabor FROM cabang_olahraga ORDER BY nama_cabor ASC");
        if($stmt_cabor_query_for_filter){
            $cabang_olahraga_list_filter = $stmt_cabor_query_for_filter->fetchAll(PDO::FETCH_ASSOC);
        } else {
            error_log("Daftar Klub: Gagal mengambil data cabang_olahraga untuk filter dropdown.");
        }
    }

    // Logika pengambilan data klub
    $sql = "SELECT k.id_klub, k.nama_klub, k.id_cabor, k.ketua_klub, k.alamat_sekretariat, k.kontak_klub, k.email_klub, k.nomor_sk_klub, k.tanggal_sk_klub, k.path_sk_klub, k.logo_klub, k.status_approval_admin, k.alasan_penolakan_admin, co.nama_cabor FROM klub k JOIN cabang_olahraga co ON k.id_cabor = co.id_cabor";
    $conditions = []; $params = [];

    if ($user_role_utama === 'pengurus_cabor' && !empty($id_cabor_filter_for_pengurus)) {
        $conditions[] = "k.id_cabor = :user_id_cabor_role"; $params[':user_id_cabor_role'] = $id_cabor_filter_for_pengurus;
        if ($filter_id_cabor_get_page !== null && $filter_id_cabor_get_page != $id_cabor_filter_for_pengurus) {
            $filter_id_cabor_get_page = $id_cabor_filter_for_pengurus; // Paksa filter ke cabor pengurus jika berbeda
        } elseif ($filter_id_cabor_get_page === null) {
            $filter_id_cabor_get_page = $id_cabor_filter_for_pengurus; // Jika tidak ada filter GET, gunakan cabor pengurus
        }
    }

    // Terapkan filter GET jika ada dan valid (untuk admin atau jika sudah disesuaikan untuk pengurus)
    if ($filter_id_cabor_get_page !== null) {
        $parameter_sudah_ada_untuk_nilai_ini = false;
        foreach (array_values($params) as $param_value) {
            if ($param_value == $filter_id_cabor_get_page) {
                $parameter_sudah_ada_untuk_nilai_ini = true;
                break;
            }
        }
        if (!$parameter_sudah_ada_untuk_nilai_ini) {
            $conditions[] = "k.id_cabor = :filter_id_cabor_get_page_param"; // Nama parameter yang unik
            $params[':filter_id_cabor_get_page_param'] = $filter_id_cabor_get_page;
        }
    }

    if (!empty($conditions)) { $sql .= " WHERE " . implode(" AND ", $conditions); }
    $sql .= " ORDER BY k.nama_klub ASC";

    $stmt = $pdo->prepare($sql); $stmt->execute($params); $daftar_klub_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($daftar_klub_raw as $klub_item_raw) {
        // ... (Logika perhitungan kelengkapan data klub Anda) ...
        $fields_penting_klub = [ 'nama_klub', 'ketua_klub', 'alamat_sekretariat', 'kontak_klub', 'nomor_sk_klub', 'tanggal_sk_klub', 'path_sk_klub', 'logo_klub' ];
        $total_fields_dihitung = count($fields_penting_klub); $fields_terisi_aktual = 0;
        if (!empty(trim($klub_item_raw['nama_klub'] ?? ''))) $fields_terisi_aktual++; if (!empty(trim($klub_item_raw['ketua_klub'] ?? ''))) $fields_terisi_aktual++;
        if (!empty(trim($klub_item_raw['alamat_sekretariat'] ?? ''))) $fields_terisi_aktual++; if (!empty(trim($klub_item_raw['kontak_klub'] ?? ''))) $fields_terisi_aktual++;
        if (!empty(trim($klub_item_raw['nomor_sk_klub'] ?? ''))) $fields_terisi_aktual++; if (!empty($klub_item_raw['tanggal_sk_klub'])) $fields_terisi_aktual++;
        if (!empty($klub_item_raw['path_sk_klub'])) { $sk_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . '/' . ltrim($klub_item_raw['path_sk_klub'], '/'); if (file_exists(preg_replace('/\/+/', '/', $sk_path))) { $fields_terisi_aktual++; } }
        if (!empty($klub_item_raw['logo_klub'])) { $logo_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . rtrim($app_base_path, '/') . '/' . ltrim($klub_item_raw['logo_klub'], '/'); if (file_exists(preg_replace('/\/+/', '/', $logo_path))) { $fields_terisi_aktual++; } }
        $progress_persen = ($total_fields_dihitung > 0) ? round(($fields_terisi_aktual / $total_fields_dihitung) * 100) : 0;
        $klub_item_raw['progress_kelengkapan'] = $progress_persen;
        if ($progress_persen < 40) $klub_item_raw['progress_color'] = 'bg-danger'; elseif ($progress_persen < 75) $klub_item_raw['progress_color'] = 'bg-warning'; else $klub_item_raw['progress_color'] = 'bg-success';
        $daftar_klub_processed[] = $klub_item_raw;
    }

} catch (PDOException $e) { error_log("Error Daftar Klub: " . $e->getMessage()); $_SESSION['pesan_error_global'] = "Terjadi kesalahan fatal saat memuat data klub. Silakan hubungi administrator."; }
?>

<section class="content">
    <div class="container-fluid">
        <?php // ... (Blok Pesan Feedback Anda yang sudah ada) ... ?>

        <div class="card card-outline card-primary shadow mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-shield-alt mr-1"></i>
                    Data Klub Olahraga
                    <?php
                    if ($filter_id_cabor_get_page && !empty($cabang_olahraga_list_filter)) { // Pastikan $cabang_olahraga_list_filter ada isinya
                        $nama_cabor_filtered_header = '';
                        foreach($cabang_olahraga_list_filter as $cfl_h_item){ if($cfl_h_item['id_cabor'] == $filter_id_cabor_get_page){ $nama_cabor_filtered_header = $cfl_h_item['nama_cabor']; break; } }
                        if(!empty($nama_cabor_filtered_header)){ echo " <small class='text-muted'>- Cabor: " . htmlspecialchars($nama_cabor_filtered_header) . "</small>"; }
                    }
                    ?>
                </h3>
                <div class="card-tools d-flex align-items-center">
                    <?php // KONDISI UNTUK MENAMPILKAN FILTER CABOR ?>
                    <?php if (in_array($user_role_utama, ['super_admin', 'admin_koni']) && !empty($cabang_olahraga_list_filter)): ?>
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="form-inline <?php if ($can_add_klub) echo 'mr-2'; ?>">
                        <label for="filter_id_cabor_klub_page" class="mr-2 text-sm font-weight-normal">Filter Cabor:</label>
                        <select name="id_cabor" id="filter_id_cabor_klub_page" class="form-control form-control-sm mr-2" style="max-width: 200px;" onchange="this.form.submit()">
                            <option value="">Semua Cabor</option>
                            <?php foreach ($cabang_olahraga_list_filter as $cabor_filter_item_page): ?>
                                <option value="<?php echo $cabor_filter_item_page['id_cabor']; ?>" <?php echo ($filter_id_cabor_get_page == $cabor_filter_item_page['id_cabor']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cabor_filter_item_page['nama_cabor']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($filter_id_cabor_get_page): ?>
                            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                        <?php endif; ?>
                    </form>
                    <?php elseif (in_array($user_role_utama, ['super_admin', 'admin_koni']) && empty($cabang_olahraga_list_filter)): ?>
                        <span class="text-muted text-sm mr-2 font-weight-normal">Filter Cabor tidak tersedia.</span>
                    <?php endif; ?>

                    <?php if ($can_add_klub): ?>
                        <a href="tambah_klub.php<?php
                            $id_cabor_default_for_add_button = $filter_id_cabor_get_page ?? ($id_cabor_pengurus_utama ?? null);
                            if ($id_cabor_default_for_add_button) echo '?id_cabor_default=' . $id_cabor_default_for_add_button;
                        ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-plus mr-1"></i> Tambah Klub Baru
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm table-striped" id="klubMasterTable" width="100%" cellspacing="0">
                        <thead>
                             <tr class="text-center">
                                <th style="width: 20px;">No.</th>
                                <th>Nama Klub</th>
                                <th>Cabang Olahraga</th>
                                <th>Ketua Klub</th>
                                <th style="width: 15%;">Kelengkapan Data</th>
                                <th style="width: 120px;">Status Approval</th>
                                <th style="width: 180px;" class="text-center no-export">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($daftar_klub_processed)): ?>
                                <?php $nomor = 1; ?>
                                <?php foreach ($daftar_klub_processed as $klub_item_data): ?>
                                    <tr>
                                        <!-- ... (Isi baris tabel Anda dengan tombol aksi yang sudah dirapikan) ... -->
                                        <td class="text-center"><?php echo $nomor++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($klub_item_data['nama_klub']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($klub_item_data['nama_cabor']); ?></td>
                                        <td><?php echo htmlspecialchars($klub_item_data['ketua_klub'] ?? '-'); ?></td>
                                        <td>
                                            <div class="progress progress-xs" title="<?php echo $klub_item_data['progress_kelengkapan']; ?>% Lengkap">
                                                <div class="progress-bar <?php echo htmlspecialchars($klub_item_data['progress_color']); ?>" role="progressbar" style="width: <?php echo $klub_item_data['progress_kelengkapan']; ?>%" aria-valuenow="<?php echo $klub_item_data['progress_kelengkapan']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <small class="text-muted d-block text-center"><?php echo $klub_item_data['progress_kelengkapan']; ?>%</small>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $status_text_item = ucfirst(str_replace('_', ' ', $klub_item_data['status_approval_admin'] ?? 'N/A'));
                                            $status_badge_item = 'secondary';
                                            if ($klub_item_data['status_approval_admin'] == 'disetujui') { $status_badge_item = 'success'; }
                                            elseif ($klub_item_data['status_approval_admin'] == 'ditolak') { $status_badge_item = 'danger'; }
                                            elseif ($klub_item_data['status_approval_admin'] == 'pending') { $status_badge_item = 'warning'; $status_text_item = "Pending"; }
                                            echo "<span class='badge badge-{$status_badge_item} p-1'>{$status_text_item}</span>";
                                            if ($klub_item_data['status_approval_admin'] == 'ditolak' && !empty($klub_item_data['alasan_penolakan_admin'])): ?>
                                                <i class="fas fa-info-circle ml-1 text-danger" data-toggle="tooltip" data-placement="top" title="Alasan: <?php echo htmlspecialchars($klub_item_data['alasan_penolakan_admin']); ?>"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center" style="white-space: nowrap; vertical-align: middle;">
                                            <?php
                                            $tombol_aksi_html_output = '';
                                            $tombol_aksi_html_output .= '<a href="detail_klub.php?id_klub=' . $klub_item_data['id_klub'] . '" class="btn btn-info btn-xs mr-1" title="Detail Klub"><i class="fas fa-eye"></i></a>';
                                            $can_edit_current_item_loop = false;
                                            if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) { $can_edit_current_item_loop = true; }
                                            elseif ($user_role_utama == 'pengurus_cabor' && isset($id_cabor_filter_for_pengurus) && $id_cabor_filter_for_pengurus == $klub_item_data['id_cabor']) { $can_edit_current_item_loop = true; }
                                            if ($can_edit_current_item_loop) { $tombol_aksi_html_output .= '<a href="edit_klub.php?id_klub=' . $klub_item_data['id_klub'] . '" class="btn btn-warning btn-xs mr-1" title="Edit Klub"><i class="fas fa-edit"></i></a>'; }
                                            if (in_array($user_role_utama, ['super_admin', 'admin_koni']) && $klub_item_data['status_approval_admin'] === 'pending') {
                                                $tombol_aksi_html_output .= '<button type="button" class="btn btn-success btn-xs mr-1" title="Setujui Klub" onclick="window.handleApproval(\'' . $klub_item_data['id_klub'] . '\', \'' . htmlspecialchars(addslashes($klub_item_data['nama_klub'])) . '\', \'disetujui\')"><i class="fas fa-check"></i></button>';
                                                $tombol_aksi_html_output .= '<button type="button" class="btn btn-danger btn-xs mr-1" title="Tolak Klub" onclick="window.handleApproval(\'' . $klub_item_data['id_klub'] . '\', \'' . htmlspecialchars(addslashes($klub_item_data['nama_klub'])) . '\', \'ditolak\')"><i class="fas fa-times"></i></button>';
                                            }
                                            if ($can_edit_current_item_loop && in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
                                                $tombol_aksi_html_output .= '<a href="hapus_klub.php?id_klub=' . $klub_item_data['id_klub'] . '" class="btn btn-danger btn-xs" title="Hapus Klub" onclick="return confirm(\'PERHATIAN! Menghapus klub ini akan mempengaruhi data terkait.\\nApakah Anda yakin ingin menghapus klub ' . htmlspecialchars(addslashes($klub_item_data['nama_klub'])) . '?\');"><i class="fas fa-trash"></i></a>';
                                            }
                                            if (substr($tombol_aksi_html_output, -7) === ' mr-1"') { $tombol_aksi_html_output = substr($tombol_aksi_html_output, 0, -7) . '"';} // Hapus margin dari tombol terakhir
                                            echo $tombol_aksi_html_output;
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center">Belum ada data klub yang cocok dengan filter Anda, atau belum ada data klub sama sekali.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$id_tabel_untuk_js = "klubMasterTable";

$inline_script = "
window.handleApproval = function(idKlub, namaKlub, newStatus) {
    console.log('Global window.handleApproval function called with:', idKlub, namaKlub, newStatus);
    let message = ''; let alasan = '';
    if (newStatus === 'disetujui') { message = `Apakah Anda yakin ingin MENYETUJUI klub \\\"\${namaKlub}\\\"?`; }
    else if (newStatus === 'ditolak') {
        message = `Apakah Anda yakin ingin MENOLAK klub \\\"\${namaKlub}\\\"?`;
        alasan = prompt(`Mohon berikan alasan penolakan untuk klub \\\"\${namaKlub}\\\":`);
        if (alasan === null) { return false; }
        if (alasan.trim() === '') { alert('Alasan penolakan tidak boleh kosong.'); return false; }
    } else { console.error('Status approval tidak dikenal:', newStatus); return false; }
    if (confirm(message)) {
        var form = document.createElement('form'); form.method = 'POST'; form.action = 'proses_edit_klub.php';
        var idInput = document.createElement('input'); idInput.type = 'hidden'; idInput.name = 'id_klub'; idInput.value = idKlub; form.appendChild(idInput);
        var statusInput = document.createElement('input'); statusInput.type = 'hidden'; statusInput.name = 'status_approval_admin'; statusInput.value = newStatus; form.appendChild(statusInput);
        var quickActionInput = document.createElement('input'); quickActionInput.type = 'hidden'; quickActionInput.name = 'quick_action_approval'; quickActionInput.value = '1'; form.appendChild(quickActionInput);
        if (newStatus === 'ditolak') { var alasanInput = document.createElement('input'); alasanInput.type = 'hidden'; alasanInput.name = 'alasan_penolakan_admin'; alasanInput.value = alasan; form.appendChild(alasanInput); }
        document.body.appendChild(form);
        console.log('Form akan disubmit ke proses_edit_klub.php');
        form.submit();
    }
    return false;
};

$(document).ready(function() {
    console.log('Dokumen siap. jQuery version:', $.fn.jquery);
    console.log('Mencoba inisialisasi DataTables untuk #" . $id_tabel_untuk_js . "');
    if (typeof $.fn.DataTable === 'undefined' || typeof $.fn.DataTable.Buttons === 'undefined') {
        console.error('Plugin DataTables atau Buttons TIDAK dimuat.');
        if (!$('#" . $id_tabel_untuk_js . "').length) { console.error('Elemen tabel #" . $id_tabel_untuk_js . " juga TIDAK ditemukan di DOM.'); }
        return;
    }
    if ($('#" . $id_tabel_untuk_js . "').length) {
        console.log('Elemen tabel #" . $id_tabel_untuk_js . " ditemukan. Menginisialisasi...');
        $('#" . $id_tabel_untuk_js . "').DataTable({
            \"responsive\": true, \"lengthChange\": true, \"autoWidth\": false,
            \"buttons\": [
                { extend: 'copy', text: '<i class=\"fas fa-copy\"></i> Salin', className: 'btn-sm btn-default', titleAttr: 'Salin', exportOptions: { columns: ':visible:not(.no-export)' } },
                { extend: 'csv', text: '<i class=\"fas fa-file-csv\"></i> CSV', className: 'btn-sm btn-default', titleAttr: 'CSV', exportOptions: { columns: ':visible:not(.no-export)' } },
                { extend: 'excel', text: '<i class=\"fas fa-file-excel\"></i> Excel', className: 'btn-sm btn-default', titleAttr: 'Excel', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Klub Olahraga' },
                { extend: 'pdf', text: '<i class=\"fas fa-file-pdf\"></i> PDF', className: 'btn-sm btn-default', titleAttr: 'PDF', orientation: 'landscape', pageSize: 'LEGAL', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Klub Olahraga' },
                { extend: 'print', text: '<i class=\"fas fa-print\"></i> Cetak', className: 'btn-sm btn-default', titleAttr: 'Cetak', exportOptions: { columns: ':visible:not(.no-export)' }, title: 'Daftar Klub Olahraga' },
                { extend: 'colvis', text: '<i class=\"fas fa-columns\"></i> Kolom', className: 'btn-sm btn-default', titleAttr: 'Kolom' }
            ],
            \"language\": {
                \"search\": \"\", \"searchPlaceholder\": \"Ketik untuk mencari klub...\",
                \"lengthMenu\": \"Tampilkan _MENU_ klub\", \"info\": \"Menampilkan _START_ s/d _END_ dari _TOTAL_ klub\",
                \"infoEmpty\": \"Tidak ada klub tersedia\", \"infoFiltered\": \"(difilter dari _MAX_ total klub)\",
                \"zeroRecords\": \"Tidak ada data klub yang cocok\",
                \"paginate\": { \"first\": \"<i class='fas fa-angle-double-left'></i>\", \"last\": \"<i class='fas fa-angle-double-right'></i>\", \"next\": \"<i class='fas fa-angle-right'></i>\", \"previous\": \"<i class='fas fa-angle-left'></i>\" },
                \"buttons\": { \"copyTitle\": 'Data Disalin', \"copySuccess\": { _: '%d baris disalin', 1: '1 baris disalin' }, \"colvis\": 'Tampilkan Kolom'}
            },
            \"order\": [[1, 'asc']],
            \"columnDefs\": [ { \"orderable\": false, \"targets\": [0, 4, 5, 6] }, { \"searchable\": false, \"targets\": [0, 4, 5] } ],
            \"dom\":  \"<'row'<'col-sm-12 col-md-3'l><'col-sm-12 col-md-6 text-center'B><'col-sm-12 col-md-3'f>>\" + \"<'row'<'col-sm-12'tr>>\" + \"<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>\",
            \"initComplete\": function(settings, json) {
                console.log('Inisialisasi DataTables untuk #" . $id_tabel_untuk_js . " SELESAI.');
                $('[data-toggle=\"tooltip\"]').tooltip();
            }
        });
    } else { console.error('Elemen tabel #" . $id_tabel_untuk_js . " TIDAK ditemukan di DOM.'); }
});
";

require_once(__DIR__ . '/../../core/footer.php');
?>