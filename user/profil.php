<?php
session_start();
include '../koneksi.php';
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['username'];
$nama_lengkap = $_SESSION['nama_lengkap'] ?? $username;

// --- ambil data user dari database ---
$query = $conn->query("SELECT * FROM users WHERE username='$username' LIMIT 1");
$user = $query->fetch_assoc();

// kalau user tidak ditemukan, isi default kosong
$email      = $user['email'] ?? '';
$telepon    = $user['telepon'] ?? '';
$departemen = $user['departemen'] ?? '';
$alamat     = $user['alamat'] ?? '';
$foto       = $user['foto'] ?? 'default.png';

// --- update data kalau form disubmit ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = $_POST['nama_lengkap'] ?? $nama_lengkap;
    $email        = $_POST['email'] ?? $email;
    $telepon      = $_POST['telepon'] ?? $telepon;
    $departemen   = $_POST['departemen'] ?? $departemen;
    $alamat       = $_POST['alamat'] ?? $alamat;
    $notif = '';

    // upload foto jika ada
    if (!empty($_FILES['foto']['name'])) {
        $targetDir = "../uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileName = time() . "_" . basename($_FILES["foto"]["name"]);
        $targetFile = $targetDir . $fileName;
        if (move_uploaded_file($_FILES["foto"]["tmp_name"], $targetFile)) {
            $foto = $fileName;
        }
    }

    // proses ubah password
    if (!empty($_POST['password_lama']) && !empty($_POST['password_baru']) && !empty($_POST['konfirmasi_password'])) {
        $password_lama = $_POST['password_lama'];
        $password_baru = $_POST['password_baru'];
        $konfirmasi_password = $_POST['konfirmasi_password'];

        $cek = $conn->prepare("SELECT password FROM users WHERE username=? LIMIT 1");
        $cek->bind_param("s", $username);
        $cek->execute();
        $cek->bind_result($hash_lama);
        $cek->fetch();
        $cek->close();

        if (!password_verify($password_lama, $hash_lama)) {
            $notif = '<div class="notification error">Password lama salah!</div>';
        } elseif ($password_baru !== $konfirmasi_password) {
            $notif = '<div class="notification error">Konfirmasi password baru tidak cocok!</div>';
        } else {
            $hash_baru = password_hash($password_baru, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE username=?");
            $stmt->bind_param("ss", $hash_baru, $username);
            $stmt->execute();
            $stmt->close();
            $notif = '<div class="notification success">Password berhasil diubah!</div>';
        }
    }

    // update ke database
    $stmt = $conn->prepare("UPDATE users SET nama_lengkap=?, email=?, telepon=?, departemen=?, alamat=?, foto=? WHERE username=?");
    $stmt->bind_param("sssssss", $nama_lengkap, $email, $telepon, $departemen, $alamat, $foto, $username);
    $stmt->execute();
    $stmt->close();

    $_SESSION['nama_lengkap'] = $nama_lengkap;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil User - Absensi System</title>
    <link rel="stylesheet" href="../css/profile_user.css">
</head>
<body>
    <!-- Mobile Toggle Button - Animasi Hamburger Menu -->
    <button class="mobile-toggle" id="mobileToggle" aria-label="Menu">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <!-- Tombol Close untuk Mobile -->
        <button class="sidebar-close" id="sidebar-close" aria-label="Tutup Sidebar">
            <span>×</span>
        </button>
        
        <div class="sidebar-header">
            <div class="sidebar-logo">A</div>
            <div class="sidebar-title">Absensi System</div>
        </div>
        
        <div class="sidebar-menu">
            <ul>
                <li><a href="absensi.php">
                    <span class="icon">🏠</span>
                    Dashboard
                </a></li>
                <li><a href="riwayat.php">
                    <span class="icon">📋</span>
                    Riwayat Absensi
                </a></li>
                <li><a href="profil.php" class="active">
                    <span class="icon">👤</span>
                    Profil
                </a></li>
            </ul>
        </div>
        
        <a href="../logout.php" class="logout-btn" id="logoutBtn">
            <span>🚪</span>
            Logout
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Navbar -->
        <div class="navbar">
            <div class="navbar-title">
                <h1>Profil User</h1>
            </div>
            <div class="navbar-profile">
                <img src="../uploads/<?= htmlspecialchars($foto) ?>" alt="Foto Profil" class="profile-avatar-navbar">
                <span class="profile-name-navbar"><?= htmlspecialchars($nama_lengkap) ?></span>
                <span class="profile-role">User</span>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <?php if (!empty($notif)) echo $notif; ?>

            <!-- Profile Container -->
            <div class="profile-container">
                <h2>Edit Profil</h2>
                <form method="post" enctype="multipart/form-data" class="profile-form">
                    <div class="form-grid">
                        <div>
                            <label>Nama Lengkap</label><br>
                            <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($nama_lengkap) ?>" class="input-profile" required>
                        </div>
                        <div>
                            <label>Email</label><br>
                            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" class="input-profile">
                        </div>
                        <div>
                            <label>No. Telepon</label><br>
                            <input type="text" name="telepon" value="<?= htmlspecialchars($telepon) ?>" class="input-profile">
                        </div>
                        <div>
                            <label>Departemen</label><br>
                            <input type="text" name="departemen" value="<?= htmlspecialchars($departemen) ?>" class="input-profile">
                        </div>
                        <div class="full-width">
                            <label>Alamat</label><br>
                            <textarea name="alamat" class="input-profile" rows="3"><?= htmlspecialchars($alamat) ?></textarea>
                        </div>
                    </div>

                    <!-- Ubah Password Section -->
                    <div class="password-section">
                        <h3>Ubah Password</h3>
                        <div class="password-grid">
                            <div>
                                <label>Password Lama</label><br>
                                <input type="password" name="password_lama" class="input-profile" placeholder="Kosongkan jika tidak diubah">
                            </div>
                            <div>
                                <label>Password Baru</label><br>
                                <input type="password" name="password_baru" class="input-profile" placeholder="Kosongkan jika tidak diubah">
                            </div>
                            <div>
                                <label>Konfirmasi Password Baru</label><br>
                                <input type="password" name="konfirmasi_password" class="input-profile" placeholder="Kosongkan jika tidak diubah">
                            </div>
                        </div>
                    </div>

                    <!-- Foto Profil Section -->
                    <div class="foto-section">
                        <label>Foto Profil</label><br>
                        <img src="../uploads/<?= htmlspecialchars($foto) ?>" alt="Foto Profil" class="profile-avatar">
                        <input type="file" name="foto" accept="image/*">
                    </div>

                    <div class="button-section">
                        <button type="submit" class="btn-profile-save">
                            💾 Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.getElementById('mobileToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarClose = document.getElementById('sidebar-close');
            const mainContent = document.getElementById('mainContent');
            
            function updateMobileToggle() {
                if (window.innerWidth <= 768) {
                    mobileToggle.style.display = 'flex';
                } else {
                    mobileToggle.style.display = 'none';
                    sidebar.classList.remove('show');
                    mobileToggle.classList.remove('active');
                }
            }
            
            updateMobileToggle();
            
            // Toggle sidebar saat hamburger diklik
            mobileToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('show');
                mobileToggle.classList.toggle('active');
            });
            
            // Tutup sidebar saat tombol close diklik
            sidebarClose.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.remove('show');
                mobileToggle.classList.remove('active');
            });
            
            // Tutup sidebar saat area di luar diklik
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768 && sidebar.classList.contains('show')) {
                    if (!sidebar.contains(e.target) && !mobileToggle.contains(e.target)) {
                        sidebar.classList.remove('show');
                        mobileToggle.classList.remove('active');
                    }
                }
            });
            
            // Update hamburger saat ukuran layar berubah
            window.addEventListener('resize', updateMobileToggle);

            // Notifikasi auto-hide
            const notification = document.querySelector('.notification');
            if (notification) {
                setTimeout(function() {
                    notification.style.opacity = '0';
                    setTimeout(function() {
                        notification.style.display = 'none';
                    }, 300);
                }, 5000);
            }

            // Logout functionality
            document.getElementById('logoutBtn').addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = '../logout.php';
            });
        });
    </script>
</body>
</html>