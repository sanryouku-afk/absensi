<?php
// --- AKTIFKAN INI UNTUK MELIHAT ERROR ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Memulai session. WAJIB DI BARIS PALING ATAS!
session_start();

// Sertakan file koneksi database
include 'koneksi.php'; // Sesuaikan path jika perlu, misalnya '../koneksi.php'

// Cek apakah form sudah di-submit (tombol login ditekan)
if (isset($_POST['login'])) {

    // Ambil data dari form login
    $username = $_POST['username'];
    $password = $_POST['password']; // Sebaiknya gunakan password_hash di database

    // Lindungi dari SQL Injection
    $username = mysqli_real_escape_string($conn, $username);

    // Query ke database untuk mencari user berdasarkan username
    $query = "SELECT * FROM users WHERE username = '$username'";
    $result = mysqli_query($conn, $query);

    // Cek apakah user ditemukan
    if ($result && mysqli_num_rows($result) > 0) {
        
        // Ambil data user
        $user_data = mysqli_fetch_assoc($result);

        // --- VERIFIKASI PASSWORD ---
        // PENTING: Jika password di database di-hash, gunakan password_verify()
        // Contoh: if (password_verify($password, $user_data['password'])) { ... }
        // Jika tidak di-hash (tidak disarankan), Anda bisa bandingkan langsung
        if ($password == $user_data['password']) { 

            // --- INI ADALAH BAGIAN TERPENTING ---
            // Jika login berhasil, buat session
            $_SESSION['username'] = $user_data['username'];
            $_SESSION['nama_lengkap'] = $user_data['nama_lengkap'];
            $_SESSION['role'] = $user_data['role']; // 'admin' atau 'user'
            $_SESSION['login_success'] = true;

            // Arahkan user ke dashboard yang sesuai dengan role-nya
            if ($user_data['role'] == 'admin') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: user/absensi_user.php"); // Sesuaikan dengan nama file dashboard user
            }
            exit(); // Hentikan eksekusi script

        } else {
            // Jika password salah
            $_SESSION['login_error'] = "Password salah!";
            header("Location: index.php"); // Kembali ke halaman login
            exit();
        }

    } else {
        // Jika username tidak ditemukan
        $_SESSION['login_error'] = "Username tidak terdaftar!";
        header("Location: index.php"); // Kembali ke halaman login
        exit();
    }

} else {
    // Jika user mencoba mengakses file ini langsung tanpa melalui form
    header("Location: index.php");
    exit();
}
?>