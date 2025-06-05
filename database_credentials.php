<?php
// File: public_html/reaktorsystem/database_credentials.php

// ========================================================================
// PERINGATAN KEAMANAN PENTING!
// ========================================================================
// 1. SEGERA UBAH PASSWORD DATABASE DI BAWAH INI ('DB_PASS')
//    GUNAKAN PASSWORD YANG KUAT DAN UNIK UNTUK LINGKUNGAN PRODUKSI.
// 2. JANGAN PERNAH MEMBAGIKAN KREDENSIAL ASLI DALAM DISKUSI ATAU KODE PUBLIK.
// 3. PASTIKAN FILE INI TIDAK DAPAT DIAKSES LANGSUNG DARI WEB.
//    - Idealnya, letakkan file ini DI LUAR folder public_html (document root).
//    - Jika harus di dalam public_html (misalnya karena batasan open_basedir),
//      pastikan ada file .htaccess di folder ini (atau folder yang melingkupinya
//      seperti 'core/' jika file ini dipindah ke sana) yang berisi:
//      <Files "database_credentials.php">
//          Order Allow,Deny
//          Deny from all
//      </Files>
//      Atau untuk seluruh folder 'core':
//      Deny from all
// ========================================================================

// Kredensial Database MySQL Anda
define('DB_HOST', 'localhost');     // Host database Anda (biasanya 'localhost')
define('DB_NAME', 'u258794476_dbreaktorsys'); // Nama database Anda
define('DB_USER', 'u258794476_reaktor');     // User MySQL Anda
define('DB_PASS', 'R3p4rasiM1mp1');  // !!! GANTI PASSWORD INI SEGERA !!!

// Path ke utilitas command-line MySQL (digunakan untuk fitur backup/restore database)
// Sesuaikan path ini jika 'mysqldump' dan 'mysql' tidak ada di PATH environment server.
// Linux: Biasanya 'mysqldump' atau '/usr/bin/mysqldump'
// Windows (XAMPP): 'C:\xampp\mysql\bin\mysqldump.exe'
define('MYSQLDUMP_PATH', 'mysqldump');
define('MYSQL_PATH', 'mysql');

// Batasan ukuran file untuk fitur manajemen database massal (Super Admin)
define('MAX_SQL_BACKUP_SIZE_MB', 50);      // Ukuran file SQL backup/restore (dalam Megabyte)
define('MAX_FILES_ARCHIVE_SIZE_MB', 500); // Ukuran file ZIP backup berkas uploads (dalam Megabyte)

// ========================================================================
// PENYESUAIAN: Menghapus konstanta ukuran file umum karena sudah ada di init_core.php
// ========================================================================
// Konstanta MAX_FILE_SIZE_GENERAL_MB, MAX_FILE_SIZE_FOTO_MB, MAX_FILE_SIZE_BUKTI_PRESTASI_MB
// telah dipusatkan di init_core.php untuk detail yang lebih spesifik per jenis file.
// Hapus definisi berikut dari sini jika sudah ada di init_core.php:
// define('MAX_FILE_SIZE_GENERAL_MB', 2);
// define('MAX_FILE_SIZE_FOTO_MB', 1);
// define('MAX_FILE_SIZE_BUKTI_PRESTASI_MB', 2);
// ========================================================================
// AKHIR PENYESUAIAN
// ========================================================================


// ========================================================================
// OPSIONAL: Konfigurasi Path dan URL Aplikasi Secara Manual
// ========================================================================
// Jika deteksi otomatis APP_URL_BASE dan APP_WEB_ROOT_PATH di init_core.php
// terbukti tidak stabil untuk lingkungan server Anda, Anda bisa mendefinisikannya
// secara manual di sini dan menggunakannya di init_core.php.
// Uncomment dan sesuaikan baris di bawah ini jika diperlukan.

// define('APP_CONFIG_MANUAL_URL_BASE', 'https://reaktorsystem.koniserdangbedagai.or.id/reaktorsystem'); // Contoh
// define('APP_CONFIG_MANUAL_WEB_ROOT_PATH', '/reaktorsystem/'); // Path dari root domain ke folder aplikasi Anda, contoh: / atau /folderaplikasi/

// ========================================================================
// AKHIR OPSIONAL
// ========================================================================

// JANGAN ADA TAG PHP PENUTUP ATAU SPASI SETELAH BLOK PHP INI