<?php
$page_title = "Prestasi Saya";
require_once(__DIR__ . '/../core/header.php'); // PATH BARU

// Pastikan $pdo sudah terdefinisi dan pengguna sudah login
if (!isset($pdo) || $pdo === null) { /* ... (Error Handling Koneksi DB) ... */
    echo '<section class="content"><div class="container-fluid"><div class="alert alert-danger text-center"><strong>Koneksi Database Gagal!</strong></div></div></section>';
    require_once(__DIR__ . '/../core/footer.php'); // PATH BARU
    exit();
}
if (!isset($user_nik)) {
    header("Location: ../auth/login.php");
    exit();
} // PATH BARU

// Halaman ini idealnya hanya untuk pengguna yang memiliki data di tabel 'atlet'
// atau setidaknya memiliki peran 'atlet' di $_SESSION['roles_data']
$is_user_atlet = false;
$atlet_cabor_ids = []; // Untuk menyimpan cabor tempat dia jadi atlet
if (!empty($roles_data_session)) {
    foreach ($roles_data_session as $peran_data_item) {
        if (($peran_data_item['role_spesifik'] ?? $peran_data_item['tipe_peran']) == 'atlet' && isset($peran_data_item['id_cabor'])) {
            $is_user_atlet = true;
            $atlet_cabor_ids[] = $peran_data_item['id_cabor']; // Atlet bisa di banyak cabor (meski jarang)
        }
    }
}
// Jika tidak ada peran atlet, atau untuk lebih pasti, cek juga tabel atlet
if (!$is_user_atlet) {
    $stmt_check_is_atlet_db = $pdo->prepare("SELECT COUNT(*) FROM atlet WHERE nik = :nik_user");
    $stmt_check_is_atlet_db->bindParam(':nik_user', $user_nik);
    $stmt_check_is_atlet_db->execute();
    if ($stmt_check_is_atlet_db->fetchColumn() > 0) {
        $is_user_atlet = true;
        // Jika perlu, ambil cabornya lagi di sini jika tidak ada di roles_data_session
        if (empty($atlet_cabor_ids)) {
            $stmt_cabor_atlet_db = $pdo->prepare("SELECT DISTINCT id_cabor FROM atlet WHERE nik = :nik_user");
            $stmt_cabor_atlet_db->bindParam(':nik_user', $user_nik);
            $stmt_cabor_atlet_db->execute();
            $atlet_cabor_ids = $stmt_cabor_atlet_db->fetchAll(PDO::FETCH_COLUMN);
        }
    }
}

if (!$is_user_atlet && $user_role_utama != 'super_admin' && $user_role_utama != 'admin_koni') { // Admin/SA bisa lihat jika ada param NIK
    if (!isset($_GET['nik_atlet_view']) || $_GET['nik_atlet_view'] != $user_nik) { // Jika bukan lihat profil sendiri
        $_SESSION['pesan_error_global'] = "Halaman ini hanya untuk atlet atau Anda tidak memiliki izin.";
        header("Location: ../dashboard.php"); // PATH BARU
        exit();
    }
}

$nik_to_show_prestasi = $user_nik; // Defaultnya adalah NIK pengguna yang login
$page_subtitle = "";
// Jika Admin/SA melihat prestasi atlet tertentu via parameter
if (isset($_GET['nik_atlet_view']) && preg_match('/^\d{16}$/', $_GET['nik_atlet_view']) && in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
    $nik_to_show_prestasi = $_GET['nik_atlet_view'];
    $stmt_nama_atlet_view = $pdo->prepare("SELECT nama_lengkap FROM pengguna WHERE nik = :nik");
    $stmt_nama_atlet_view->bindParam(':nik', $nik_to_show_prestasi);
    $stmt_nama_atlet_view->execute();
    $nama_atlet_view = $stmt_nama_atlet_view->fetchColumn();
    if ($nama_atlet_view) {
        $page_title = "Prestasi Atlet: " . htmlspecialchars($nama_atlet_view);
        $page_subtitle = " (NIK: " . htmlspecialchars($nik_to_show_prestasi) . ")";
    }
}


$pesan = '';
if (isset($_SESSION['pesan_sukses_prestasi'])) {
    /* ... (Pesan Sukses) ... */
    $pesan = '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['pesan_sukses_prestasi']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>';
    unset($_SESSION['pesan_sukses_prestasi']);
}
if (isset($_SESSION['pesan_error_prestasi'])) {
    /* ... (Pesan Error) ... */
    $pesan = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['pesan_error_prestasi']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div>';
    unset($_SESSION['pesan_error_prestasi']);
}

$prestasi_saya_list = [];
try {
    $sql_prestasi_saya = "SELECT pr.*, co.nama_cabor
                        FROM prestasi pr
                        JOIN cabang_olahraga co ON pr.id_cabor = co.id_cabor
                        WHERE pr.nik = :nik_atlet_show
                        ORDER BY pr.tahun_perolehan DESC, pr.nama_kejuaraan ASC";
    $stmt_prestasi_saya = $pdo->prepare($sql_prestasi_saya);
    $stmt_prestasi_saya->bindParam(':nik_atlet_show', $nik_to_show_prestasi, PDO::PARAM_STR);
    $stmt_prestasi_saya->execute();
    $prestasi_saya_list = $stmt_prestasi_saya->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pesan .= '<div class="alert alert-danger">Gagal mengambil data prestasi Anda: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>

    <section class="content">
        <div class="container-fluid">
            <?php echo $pesan; ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <?php echo htmlspecialchars($page_title) . $page_subtitle; ?>
                            </h3>
                            <div class="card-tools">
                                <?php // Tombol tambah hanya jika atlet melihat prestasinya sendiri, atau jika admin/pengcab
                                $can_add_prestasi = false;
                                if ($user_role_utama == 'atlet' && $nik_to_show_prestasi == $user_nik)
                                    $can_add_prestasi = true;
                                elseif (in_array($user_role_utama, ['super_admin', 'admin_koni', 'pengurus_cabor']))
                                    $can_add_prestasi = true;

                                if ($can_add_prestasi):
                                    $url_tambah = "../modules/prestasi/tambah_prestasi.php"; // PATH BARU
                                    $params_tambah = [];
                                    if ($user_role_utama == 'atlet' && !empty($atlet_cabor_ids[0]))
                                        $params_tambah['id_cabor'] = $atlet_cabor_ids[0]; // Cabor utama atlet
                                    if ($nik_to_show_prestasi)
                                        $params_tambah['nik_atlet'] = $nik_to_show_prestasi; // NIK atlet yang sedang dilihat
                                    if (!empty($params_tambah))
                                        $url_tambah .= '?' . http_build_query($params_tambah);
                                    ?>
                                    <a href="<?php echo $url_tambah; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-award mr-1"></i> Ajukan Prestasi Baru
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($prestasi_saya_list)): ?>
                                <table id="tabelPrestasiSaya" class="table table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th style="width: 10px;">No.</th>
                                            <th>Nama Kejuaraan</th>
                                            <th>Cabor</th>
                                            <th>Tingkat</th>
                                            <th>Tahun</th>
                                            <th>Medali/Peringkat</th>
                                            <th>Status Approval</th>
                                            <th style="width: 80px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no_pres_saya = 1;
                                        foreach ($prestasi_saya_list as $pres): ?>
                                            <tr>
                                                <td>
                                                    <?php echo $no_pres_saya++; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($pres['nama_kejuaraan']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($pres['nama_cabor']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars(ucfirst($pres['tingkat_kejuaraan'])); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($pres['tahun_perolehan']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($pres['medali_peringkat']); ?>
                                                </td>
                                                <td>
                                                    <?php /* ... (Logika Badge Status SAMA seperti daftar_prestasi.php) ... */
                                                    $s_app_badge_pres_det = 'secondary';
                                                    $s_app_text_pres_det = ucfirst(str_replace('_', ' ', $pres['status_approval'] ?? 'N/A'));
                                                    if ($pres['status_approval'] == 'disetujui_admin') {
                                                        $s_app_badge_pres_det = 'success';
                                                    } elseif (in_array($pres['status_approval'], ['ditolak_pengcab', 'ditolak_admin'])) {
                                                        $s_app_badge_pres_det = 'danger';
                                                    } elseif (in_array($pres['status_approval'], ['pending', 'disetujui_pengcab', 'revisi'])) {
                                                        $s_app_badge_pres_det = 'warning';
                                                    }
                                                    ?>
                                                    <span class="badge badge-<?php echo $s_app_badge_pres_det; ?> p-1">
                                                        <?php echo htmlspecialchars($s_app_pres_text_det); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $can_edit_this_prestasi_saya = false;
                                                    if (in_array($user_role_utama, ['super_admin', 'admin_koni'])) {
                                                        $can_edit_this_prestasi_saya = true;
                                                    } elseif ($user_role_utama == 'pengurus_cabor' && ($id_cabor_pengurus_utama ?? null) == $pres['id_cabor']) {
                                                        $can_edit_this_prestasi_saya = true;
                                                    } elseif ($user_role_utama == 'atlet' && $user_nik == $pres['nik'] && in_array($pres['status_approval'], ['pending', 'revisi', 'ditolak_pengcab'])) {
                                                        $can_edit_this_prestasi_saya = true;
                                                    }
                                                    ?>
                                                    <a href="../modules/prestasi/detail_prestasi.php?id_prestasi=<?php echo $pres['id_prestasi']; ?>"
                                                        class="btn btn-xs btn-info" title="Lihat Detail"><i
                                                            class="fas fa-eye"></i></a>
                                                    <?php if ($can_edit_this_prestasi_saya): ?>
                                                        <a href="../modules/prestasi/edit_prestasi.php?id_prestasi=<?php echo $pres['id_prestasi']; ?>"
                                                            class="btn btn-xs btn-warning" title="Edit"><i
                                                                class="fas fa-edit"></i></a>
                                                    <?php endif; ?>
                                                    <?php // Tombol hapus biasanya hanya untuk admin ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="alert alert-info">Anda belum memiliki data prestasi yang tercatat. <a
                                        href="<?php echo $url_tambah ?? '../modules/prestasi/tambah_prestasi.php'; ?>">Ajukan
                                        prestasi baru?</a></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php
require_once(__DIR__ . '/../core/footer.php'); // PATH BARU
?>