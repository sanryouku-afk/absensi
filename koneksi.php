<?php
$host = "localhost";
$db   = "absensi"; // nama database kamu

// Deteksi OS
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Jika Windows (XAMPP)
    $user = "root";
    $pass = "";
} else {
    // Jika Linux (LAMPP di Fedora)
    $user = "root";
    $pass = "270501";
}

$koneksi = mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}
?>
