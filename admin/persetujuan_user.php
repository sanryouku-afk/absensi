<?php
include '../koneksi.php';
// Proses persetujuan user baru
if (isset($_POST['approve']) && !empty($_POST['username'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    mysqli_query($conn, "UPDATE users SET status='Active' WHERE username='$username'");
}
if (isset($_POST['reject']) && !empty($_POST['username'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    mysqli_query($conn, "DELETE FROM users WHERE username='$username'");
}
// Ambil user pending
$result = mysqli_query($conn, "SELECT * FROM users WHERE status='Pending' ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Persetujuan User Baru - Admin</title>
    <link rel="stylesheet" href="../admin_css/laporan.css">
</head>
<body>
    <div class="main-wrapper">
        <header class="navbar">
            <div class="navbar-title">
                <h1>Persetujuan User Baru</h1>
                <div class="navbar-greeting">Daftar pendaftar akun yang menunggu persetujuan admin</div>
            </div>
        </header>
        <aside class="sidebar">
            <div class="sidebar-header">
                <span class="sidebar-title">Absensi System</span>
            </div>
            <nav class="sidebar-menu">
                <a href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
                <a href="kelola_user.php"><span class="icon">👥</span> Kelola User</a>
                <a href="absensi.php"><span class="icon">🗓️</span> Absensi Hari Ini</a>
                <a href="laporan.php"><span class="icon">📄</span> Laporan</a>
                <a href="pengaturan.php"><span class="icon">⚙️</span> Pengaturan</a>
            </nav>
            <a href="keluar.php" class="sidebar-logout"><span class="icon">🔴</span> Logout</a>
        </aside>
        <main class="main-content">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">User Pending</span>
                </div>
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Email</th>
                            <th>Tanggal Daftar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no=1; while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="username" value="<?= htmlspecialchars($row['username']) ?>">
                                    <button type="submit" name="approve" style="background:#10b981;color:#fff;padding:6px 14px;border:none;border-radius:6px;cursor:pointer;">Setujui</button>
                                </form>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="username" value="<?= htmlspecialchars($row['username']) ?>">
                                    <button type="submit" name="reject" style="background:#ef4444;color:#fff;padding:6px 14px;border:none;border-radius:6px;cursor:pointer;">Tolak</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>