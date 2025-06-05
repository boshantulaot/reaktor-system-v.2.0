<?php
// AKTIFKAN DISPLAY ERRORS SETINGGI MUNGKIN
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Memulai Tes Koneksi Database Sederhana...<br><br>";

// 1. Cek apakah file kredensial ada
$path_kredensial = __DIR__ . '/database_credentials.php'; // Karena file ini ada di root htdocs
if (!file_exists($path_kredensial)) {
    die("GAGAL: File database_credentials.php tidak ditemukan pada path: " . htmlspecialchars($path_kredensial));
}
echo "File database_credentials.php ditemukan.<br>";
require_once($path_kredensial);

// 2. Cek apakah konstanta database terdefinisi
if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
    die("GAGAL: Satu atau lebih konstanta database (DB_HOST, DB_NAME, DB_USER, DB_PASS) tidak terdefinisi setelah include database_credentials.php. Cek isi file tersebut.");
}
echo "Konstanta DB_HOST, DB_NAME, DB_USER, DB_PASS terdefinisi.<br>";
echo "DB_HOST: " . htmlspecialchars(DB_HOST) . "<br>";
echo "DB_NAME: " . htmlspecialchars(DB_NAME) . "<br>";
echo "DB_USER: " . htmlspecialchars(DB_USER) . "<br>";
// Jangan tampilkan DB_PASS di browser untuk keamanan, cukup pastikan terdefinisi.
echo "DB_PASS: (terdefinisi, tidak ditampilkan)<br><br>";

// 3. Cek apakah ekstensi pdo_mysql dimuat
if (!extension_loaded('pdo_mysql')) {
    die("GAGAL: Ekstensi PHP 'pdo_mysql' tidak dimuat/aktif. Ini dibutuhkan untuk koneksi ke MySQL dengan PDO.");
}
echo "Ekstensi 'pdo_mysql' aktif.<br><br>";

// 4. Coba koneksi PDO
echo "Mencoba membuat objek PDO...<br>";
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Melempar exception jika error
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Mode fetch default
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Gunakan native prepared statements
    ];

    $test_pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    echo "<b>BERHASIL!</b> Koneksi ke database '" . htmlspecialchars(DB_NAME) . "' di server '" . htmlspecialchars(DB_HOST) . "' berhasil dibuat dengan PDO.<br>";

    // (Opsional) Coba query sederhana
    // echo "<br>Mencoba query sederhana: SHOW TABLES;<br>";
    // $stmt = $test_pdo->query("SHOW TABLES");
    // $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    // if ($tables) {
    //     echo "Tabel yang ditemukan: <pre>" . htmlspecialchars(print_r($tables, true)) . "</pre><br>";
    // } else {
    //     echo "Tidak ada tabel ditemukan atau query gagal (tapi koneksi berhasil).<br>";
    // }

} catch (PDOException $e) {
    echo "<b>GAGAL KONEKSI PDO!</b><br>";
    echo "Pesan Error PDOException: <pre>" . htmlspecialchars($e->getMessage()) . "</pre><br>";
    echo "Kode Error: " . htmlspecialchars($e->getCode()) . "<br>";
    // echo "Trace: <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre><br>"; // Detail trace bisa sangat panjang
    die("Tes koneksi dihentikan karena PDOException.");
} catch (Exception $e) {
    // Menangkap error umum lainnya jika ada
    echo "<b>GAGAL! TERJADI ERROR UMUM (BUKAN PDOException)!</b><br>";
    echo "Pesan Error: <pre>" . htmlspecialchars($e->getMessage()) . "</pre><br>";
    die("Tes koneksi dihentikan karena Exception umum.");
}

echo "<br>...Tes Koneksi Database Sederhana Selesai.<br>";
?>