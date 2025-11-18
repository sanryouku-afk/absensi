<?php
            include 'koneksi.php';
            $message = '';

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $username = mysqli_real_escape_string($conn, $_POST['username']);
                $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
                $email = mysqli_real_escape_string($conn, $_POST['email']);
                $password = mysqli_real_escape_string($conn, $_POST['password']);
                $konfirmasi = mysqli_real_escape_string($conn, $_POST['konfirmasi']);

                // Validasi
                if ($password !== $konfirmasi) {
                    $message = 'Password dan konfirmasi password tidak cocok!';
                } else {
                    // Hash password
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    $role = 'user';

                    $sql = "INSERT INTO users (username, password, nama_lengkap, email, role, status, created_at) 
                            VALUES ('$username', '$passwordHash', '$nama_lengkap', '$email', '$role', 'Pending', NOW())";

                    if (mysqli_query($conn, $sql)) {
                        $message = '<div style="color:orange;text-align:center;">Registrasi berhasil! Menunggu persetujuan admin.</div>';
                    } else {
                        $message = '<div style="color:red;text-align:center;">Registrasi gagal!</div>';
                    }
                }
            }
            ?>
            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Register - Sistem Absensi</title>
                <link rel="stylesheet" href="./css/style_login.css">
            </head>
            <body>
                <div class="login-container">
                    <div class="login-logo">
                        <span>👥</span>
                    </div>
                    <div class="login-title">Daftar Akun</div>
                    <div class="login-subtitle">Silakan isi data untuk mendaftar</div>
                    <?php if ($message) { echo $message; } ?>
                    <form class="login-form" method="post" action="">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="username" required autocomplete="username">
                        <label for="nama_lengkap">Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" placeholder="nama lengkap" required>
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="email" required autocomplete="email">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="password" required autocomplete="new-password">
                        <label for="konfirmasi">Konfirmasi Password</label>
                        <input type="password" id="konfirmasi" name="konfirmasi" placeholder="konfirmasi password" required autocomplete="new-password">
                        <button type="submit">Register</button>
                    </form>
                    <div style="text-align:center;margin-top:12px;">
                        Sudah punya akun? <a href="login.php" class="link">Login</a>
                    </div>
                </div>
            </body>
            </html>
