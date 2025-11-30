<?php
// Mulai session
session_start();

// Memanggil file koneksi database
include 'koneksi.php';

// Variabel untuk menampung pesan error/sukses
$message = '';

// Mengecek apakah form login dikirimkan menggunakan metode POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Mengambil email dari form + amankan dengan mysqli_real_escape_string
    $email = isset($_POST['email']) ? mysqli_real_escape_string($koneksi, trim($_POST['email'])) : '';

    // Mengambil password (tidak bisa di-escape karena butuh bentuk asli)
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Query untuk mencari user berdasarkan email
    $sql = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
    $result = mysqli_query($koneksi, $sql);

    // Jika user ditemukan
    if ($result && mysqli_num_rows($result) === 1) {

        // Mengubah data user menjadi array
        $user = mysqli_fetch_assoc($result);

        // Mengecek status akun apakah aktif
        if ($user['status'] !== 'aktif') {
            $message = '<div style="color:red;text-align:center;">Akun nonaktif, hubungi admin!</div>';
        } else {

            $isValid = false; // Penanda apakah password benar

            // Jika password telah di-hash
            if (password_verify($password, $user['password'])) {
                $isValid = true;
            }
            // Jika password masih polos (belum di-hash) → sistem upgrade otomatis
            elseif ($password === $user['password']) {
                $isValid = true;

                // Hash password baru
                $newHash = password_hash($password, PASSWORD_DEFAULT);

                // Update password di database
                mysqli_query($koneksi, "UPDATE users SET password = '$newHash' WHERE id = {$user['id']}");
            }

            // Jika password benar
            if ($isValid) {

                // Mengamankan session ID agar tidak mudah dicuri
                session_regenerate_id(true);

                // Menyimpan data user dalam session
                $_SESSION['user_id']       = $user['id'];
                $_SESSION['username']      = $user['username'];
                $_SESSION['nama_lengkap']  = $user['nama_lengkap'];
                $_SESSION['role']          = $user['role'];

                // Redirect sesuai role user
                if ($user['role'] === 'admin') {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: user/absensi.php");
                }
                exit; // Hentikan script setelah redirect
            } else {

                // Jika password salah
                $message = '<div style="color:red;text-align:center;">Password salah!</div>';
            }
        }

    } else {

        // Jika email tidak ditemukan di database
        $message = '<div style="color:red;text-align:center;">Email tidak ditemukan!</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Absensi</title>
    <link rel="stylesheet" href="css/style_login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <span>👥</span>
        </div>

        <div class="login-title">Sistem Absensi</div>
        <div class="login-subtitle">Silakan login untuk melanjutkan</div>

        <!-- Menampilkan pesan error jika ada -->
        <?php 
        if ($message) { 
            echo '<div style="margin-bottom:10px;">'.$message.'</div>'; 
        } 
        ?>

        <!-- Form Login -->
        <form class="login-form" method="post" action="">
            <label for="email">Email</label>
            <input type="text" id="email" name="email" placeholder="Email" required autocomplete="username">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Password" required autocomplete="current-password">

            <button type="submit">Login</button>
        </form>

        <div style="text-align:center;margin-top:12px;">
            Belum punya akun? <a href="register.php" class="link">Register</a>
        </div>
    </div>
</body>
</html>
