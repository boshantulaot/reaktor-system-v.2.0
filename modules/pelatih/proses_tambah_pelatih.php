<?php
// File: modules/pelatih/proses_tambah_pelatih.php
error_reporting(E_ALL); // Aktifkan ini untuk melihat error jika ada SEBELUM init_core
ini_set('display_errors', 1); // Aktifkan ini

// Hanya include init_core.php dan echo pesan sukses jika init_core berhasil dimuat
if (file_exists(__DIR__ . '/../../core/init_core.php')) {
    require_once(__DIR__ . '/../../core/init_core.php');
    echo "PROSES_TAMBAH_PELATIH: init_core.php berhasil dimuat sepenuhnya!";
    exit;
} else {
    echo "PROSES_TAMBAH_PELATIH_ERROR: init_core.php TIDAK DITEMUKAN.";
    exit;
}
?>