<?php
// koneksi.php

 $host = 'localhost';
 $user = 'dbuser';
 $pass = '12345';
 $db   = 'absensi';

 $conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>