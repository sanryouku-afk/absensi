<?php
include '../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    $hapusList = $_POST['hapus'];

    foreach ($hapusList as $username) {
        $username_safe = mysqli_real_escape_string($conn, $username);

        // ambil role biar kalau mau ada pembatasan bisa dicek
        $cek = mysqli_query($conn, "SELECT role FROM users WHERE username = '$username_safe'");
        $user = mysqli_fetch_assoc($cek);

        // kalau semua boleh dihapus, langsung hapus:
        mysqli_query($conn, "DELETE FROM absensi WHERE username = '$username_safe'");
        mysqli_query($conn, "DELETE FROM users WHERE username = '$username_safe'");
    }

    header("Location: laporan.php");
    exit;
} else {
    header("Location: laporan.php");
    exit;
}
