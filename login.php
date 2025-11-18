<?php
session_start();
include 'koneksi.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? mysqli_real_escape_string($conn, trim($_POST['email'])) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $sql = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);

        // cek status user
        if ($user['status'] !== 'aktif') {
            $message = '<div style="color:red;text-align:center;">Akun nonaktif, hubungi admin!</div>';
        } else {
            $isValid = false;

            // cek password hashed
            if (password_verify($password, $user['password'])) {
                $isValid = true;
            } 
            // jika password masih polos (belum di-hash)
            elseif ($password === $user['password']) {
                $isValid = true;
                // langsung update ke hash baru
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                mysqli_query($conn, "UPDATE users SET password = '$newHash' WHERE id = {$user['id']}");
            }

            if ($isValid) {
                // regenerate session ID
                session_regenerate_id(true);

                // simpan session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['role'] = $user['role'];

                // redirect sesuai role
                if ($user['role'] === 'admin') {    
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: user/absensi.php");
                }
                exit;
            } else {
                $message = '<div style="color:red;text-align:center;">Password salah!</div>';
            }
        }
    } else {
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
        <?php if ($message) { echo '<div style="color:red;text-align:center;margin-bottom:10px;">'.$message.'</div>'; } ?>
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