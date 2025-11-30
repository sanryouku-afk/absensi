<?php
session_start();
date_default_timezone_set('Asia/Makassar');
include '../koneksi.php';

/* Cek koneksi database */
if (!$conn) {
    die("Koneksi database gagal! Periksa file koneksi.php.");
}

/* Cek apakah admin sudah login */
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header("Location: ../index.php");
    exit;
}

/* Ambil pengaturan tema (light/dark mode) */
$tema = 'light';
$sql_tema = "SELECT tema FROM pengaturan LIMIT 1";
$result_tema = mysqli_query($conn, $sql_tema);
if ($result_tema && mysqli_num_rows($result_tema) > 0) {
    $row_tema = mysqli_fetch_assoc($result_tema);
    $tema = $row_tema['tema'] ?? 'light';
}

$role = $_SESSION['role'] ?? 'user';
$usernameLogin = $_SESSION['username'] ?? '';
$namaLogin = $_SESSION['nama_lengkap'] ?? $usernameLogin;
$tanggal = date('Y-m-d');
$jam = date('H:i:s');

/* Proses approve/reject checkout lebih awal */
if (isset($_GET['approve_checkout'])) {
    $id = $_GET['approve_checkout'];
    $stmt = $conn->prepare("SELECT * FROM izin_checkout WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $username = $data['username'];
        $tanggal_izin = $data['tanggal'];
        $jam_pengajuan = $data['jam_pengajuan'];
        $keterangan_izin = $data['keterangan'];
        
        $update_absensi = $conn->prepare("UPDATE absensi SET clock_out=?, status='Selesai', keterangan=? WHERE username=? AND tanggal=?");
        $update_absensi->bind_param("ssss", $jam_pengajuan, $keterangan_izin, $username, $tanggal_izin);
        
        if ($update_absensi->execute()) {
            $update_izin = $conn->prepare("UPDATE izin_checkout SET status='Disetujui' WHERE id=?");
            $update_izin->bind_param("i", $id);
            $update_izin->execute();
            $_SESSION['absen_success'] = "Checkout berhasil disetujui!";
        } else {
            $_SESSION['absen_error'] = "Gagal update absensi.";
        }
    } else {
        $_SESSION['absen_error'] = "Data tidak ditemukan!";
    }
    header("Location: absensi.php"); exit;
}

if (isset($_GET['reject_checkout'])) {
    $id = $_GET['reject_checkout'];
    $stmt = $conn->prepare("SELECT * FROM izin_checkout WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $username = $data['username'];
        $tanggal_izin = $data['tanggal'];
        
        $update_absensi = $conn->prepare("UPDATE absensi SET status='Hadir' WHERE username=? AND tanggal=?");
        $update_absensi->bind_param("ss", $username, $tanggal_izin);
        
        if ($update_absensi->execute()) {
            $update_izin = $conn->prepare("UPDATE izin_checkout SET status='Ditolak' WHERE id=?");
            $update_izin->bind_param("i", $id);
            $update_izin->execute();
            $_SESSION['absen_success'] = "Checkout berhasil ditolak!";
        } else {
            $_SESSION['absen_error'] = "Gagal update absensi.";
        }
    } else {
        $_SESSION['absen_error'] = "Data tidak ditemukan!";
    }
    header("Location: absensi.php"); exit;
}

/* Proses approve/reject izin */
if (isset($_GET['approve_izin'])) {
    $id = $_GET['approve_izin'];
    $stmt = $conn->prepare("UPDATE izin SET status='Disetujui' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['absen_success'] = "Izin berhasil disetujui!";
    header("Location: absensi.php"); exit;
}
if (isset($_GET['reject_izin'])) {
    $id = $_GET['reject_izin'];
    $stmt = $conn->prepare("UPDATE izin SET status='Ditolak' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['absen_success'] = "Izin berhasil ditolak!";
    header("Location: absensi.php"); exit;
}

/* Proses approve/reject sakit */
if (isset($_GET['approve_sakit'])) {
    $id = $_GET['approve_sakit'];
    $stmt = $conn->prepare("UPDATE sakit SET status='Disetujui' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['absen_success'] = "Sakit berhasil disetujui!";
    header("Location: absensi.php"); exit;
}
if (isset($_GET['reject_sakit'])) {
    $id = $_GET['reject_sakit'];
    $stmt = $conn->prepare("UPDATE sakit SET status='Ditolak' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['absen_success'] = "Sakit berhasil ditolak!";
    header("Location: absensi.php"); exit;
}

/* Proses absen manual (clock in/out, izin, sakit) */
if (isset($_POST['absen'])) {
    $username = isset($_POST['username']) ? $_POST['username'] : $usernameLogin;
    $status = $_POST['status'];
    $keterangan = $_POST['keterangan'];
    
    $qNama = $conn->prepare("SELECT nama_lengkap FROM users WHERE username=? LIMIT 1");
    $qNama->bind_param("s", $username);
    $qNama->execute();
    $resultNama = $qNama->get_result();
    $nama_lengkap = $resultNama->num_rows > 0 ? $resultNama->fetch_assoc()['nama_lengkap'] : $username;

    $sql = null;
    if ($status == 'clockin') {
        $sql = $conn->prepare("INSERT INTO absensi (username, nama_lengkap, tanggal, clock_in, status, keterangan) VALUES (?, ?, ?, ?, 'Hadir', ?)");
        $sql->bind_param("sssss", $username, $nama_lengkap, $tanggal, $jam, $keterangan);
    } elseif ($status == 'clockout') {
        $sql = $conn->prepare("UPDATE absensi SET clock_out=?, status='Selesai', keterangan=? WHERE username=? AND tanggal=?");
        $sql->bind_param("ssss", $jam, $keterangan, $username, $tanggal);
    } elseif ($status == 'izin') {
        $sql = $conn->prepare("INSERT INTO izin (username, nama_lengkap, tanggal, keterangan) VALUES (?, ?, ?, ?)");
        $sql->bind_param("ssss", $username, $nama_lengkap, $tanggal, $keterangan);
    } elseif ($status == 'sakit') {
        $sql = $conn->prepare("INSERT INTO sakit (username, nama_lengkap, tanggal, keterangan) VALUES (?, ?, ?, ?)");
        $sql->bind_param("ssss", $username, $nama_lengkap, $tanggal, $keterangan);
    }

    if ($sql && $sql->execute()) {
        $_SESSION['absen_success'] = 'Data berhasil disimpan!';
    } else {
        $_SESSION['absen_error'] = 'Gagal menyimpan data! Mungkin sudah ada.';
    }
    header('Location: absensi.php'); exit;
}

/* Hapus data absensi */
if (isset($_GET['hapus_absensi']) && is_numeric($_GET['hapus_absensi'])) {
    $id = intval($_GET['hapus_absensi']);
    $stmt = $conn->prepare("DELETE FROM absensi WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['absen_success'] = 'Absensi berhasil dihapus!';
    header('Location: absensi.php'); exit;
}

/* Ambil data untuk ditampilkan di halaman */
/* Data absensi hari ini */
$stmt = $conn->prepare("SELECT * FROM absensi WHERE tanggal = CURDATE() ORDER BY id ASC");
$stmt->execute();
$absensi_today = $stmt->get_result();

/* Data checkout pending (menunggu approval) */
$stmt = $conn->prepare("SELECT ic.*, u.nama_lengkap FROM izin_checkout ic JOIN users u ON ic.username = u.username WHERE ic.tanggal = CURDATE() AND ic.status = 'Pending' ORDER BY ic.id DESC");
$stmt->execute();
$checkout_pending = $stmt->get_result();

$stmt = $conn->prepare("SELECT i.*, u.nama_lengkap FROM izin i JOIN users u ON i.username = u.username WHERE i.tanggal = CURDATE() AND i.status = 'Pending' ORDER BY i.id DESC");
$stmt->execute();
$izin_pending = $stmt->get_result();

$stmt = $conn->prepare("SELECT s.*, u.nama_lengkap FROM sakit s JOIN users u ON s.username = u.username WHERE s.tanggal = CURDATE() AND s.status = 'Pending' ORDER BY s.id DESC");
$stmt->execute();
$sakit_pending = $stmt->get_result();

$stmt = $conn->prepare("SELECT ic.*, u.nama_lengkap FROM izin_checkout ic JOIN users u ON ic.username = u.username WHERE ic.tanggal = CURDATE() ORDER BY ic.id DESC");
$stmt->execute();
$checkout_riwayat = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= htmlspecialchars($tema) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi Hari Ini</title>
    <link rel="stylesheet" href="../admin_css/absensi.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header"><span class="sidebar-title">Absensi System</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
            <a href="kelola_user.php"><span class="icon">👥</span> Kelola User</a>
            <a href="absensi.php" class="active"><span class="icon">📅</span> Absensi Hari Ini</a>
            <a href="laporan.php"><span class="icon">📊</span> Laporan</a>
            <a href="pengaturan.php"><span class="icon">⚙️</span> Pengaturan</a>
        </nav>
        <a href="keluar.php" class="sidebar-logout"><span class="icon">🔴</span> Logout</a>
    </aside>

    <!-- Navbar -->
    <div class="navbar">
        <div style="display: flex; align-items: center;">
            <button class="hamburger" id="hamburger" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
            <div class="navbar-title"><h1>Absensi Hari Ini</h1></div>
        </div>
        <div class="navbar-profile">
            <img src="../assets/admin.jpg" class="profile-img" alt="admin">
            <div class="profile-info">
                <span class="profile-name"><?= htmlspecialchars($namaLogin) ?></span>
                <span class="profile-role"><?= htmlspecialchars(ucfirst($role)) ?></span>
            </div>
        </div>
    </div>
    
    <main class="main-content">
        <?php if (!empty($_SESSION['absen_error'])): ?>
            <div class="notification error"><?= htmlspecialchars($_SESSION['absen_error']); unset($_SESSION['absen_error']); ?></div>
        <?php elseif (!empty($_SESSION['absen_success'])): ?>
            <div class="notification success"><?= htmlspecialchars($_SESSION['absen_success']); unset($_SESSION['absen_success']); ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-label">Total Karyawan</div><div class="stat-value"><?php $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role='user'"); $stmt->execute(); echo $stmt->get_result()->fetch_assoc()['total']; ?></div></div>
            <div class="stat-card"><div class="stat-label">Hadir</div><div class="stat-value"><?php $stmt = $conn->prepare("SELECT COUNT(*) as total FROM absensi WHERE tanggal=CURDATE() AND status='Hadir'"); $stmt->execute(); echo $stmt->get_result()->fetch_assoc()['total']; ?></div></div>
            <div class="stat-card"><div class="stat-label">Terlambat</div><div class="stat-value"><?php $stmt = $conn->prepare("SELECT COUNT(*) as total FROM absensi WHERE tanggal=CURDATE() AND status='Terlambat'"); $stmt->execute(); echo $stmt->get_result()->fetch_assoc()['total']; ?></div></div>
            <div class="stat-card"><div class="stat-label">Pending</div><div class="stat-value"><?= $checkout_pending->num_rows + $izin_pending->num_rows + $sakit_pending->num_rows ?></div></div>
        </div>

        <div class="card">
            <div class="card-header"><span>Detail Absensi Hari Ini</span><button onclick="document.getElementById('absenModal').style.display='block'">Absen</button></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>No</th><th>Nama</th><th>Check In</th><th>Check Out</th><th>Status</th><th>Keterangan</th><?php if ($role === 'admin'): ?><th>Aksi</th><?php endif; ?></tr></thead>
                        <tbody>
                            <?php if ($absensi_today && $absensi_today->num_rows > 0): $no=1; while($row = $absensi_today->fetch_assoc()): $status_class = 'status-' . strtolower(str_replace(' ', '-', $row['status'])); ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                    <td><?= $row['clock_in'] ?: '-' ?></td>
                                    <td><?= $row['clock_out'] ?: '-' ?></td>
                                    <td><span class="<?= $status_class ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                                    <td><?= htmlspecialchars($row['keterangan']) ?></td>
                                    <?php if ($role === 'admin'): ?><td><a href="?hapus_absensi=<?= $row['id'] ?>" onclick="return confirm('Yakin hapus?')" style="color:#ef4444;">Hapus</a></td><?php endif; ?>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="7" class="empty-state">Belum ada data</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($role === 'admin'): ?>
            <!-- Tabel Checkout Pending -->
            <div class="card">
                <div class="card-header"><span>Permintaan Check Out Lebih Awal</span><span class="status-pending"><?= $checkout_pending->num_rows ?> pending</span></div>
                <div class="card-body">
                    <?php if ($checkout_pending->num_rows > 0): ?>
                    <table class="table">
                        <thead><tr><th>No</th><th>Nama</th><th>Jam Pengajuan</th><th>Keterangan</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php $no=1; while($row = $checkout_pending->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                    <td><?= htmlspecialchars($row['jam_pengajuan']) ?></td>
                                    <td><?= htmlspecialchars($row['keterangan']) ?></td>
                                    <td><a href="?approve_checkout=<?= $row['id'] ?>" class="btn-approve">✔</a> <a href="?reject_checkout=<?= $row['id'] ?>" class="btn-reject">✖</a></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">Tidak ada permintaan</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabel Izin Pending -->
            <div class="card">
                <div class="card-header"><span>Permintaan Izin</span><span class="status-pending"><?= $izin_pending->num_rows ?> pending</span></div>
                <div class="card-body">
                    <?php if ($izin_pending->num_rows > 0): ?>
                    <table class="table">
                        <thead><tr><th>No</th><th>Nama</th><th>Keterangan</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php $no=1; while($row = $izin_pending->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                    <td><?= htmlspecialchars($row['keterangan']) ?></td>
                                    <td><a href="?approve_izin=<?= $row['id'] ?>" class="btn-approve">✔</a> <a href="?reject_izin=<?= $row['id'] ?>" class="btn-reject">✖</a></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">Tidak ada permintaan</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabel Sakit Pending -->
            <div class="card">
                <div class="card-header"><span>Permintaan Sakit</span><span class="status-pending"><?= $sakit_pending->num_rows ?> pending</span></div>
                <div class="card-body">
                    <?php if ($sakit_pending->num_rows > 0): ?>
                    <table class="table">
                        <thead><tr><th>No</th><th>Nama</th><th>Keterangan</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php $no=1; while($row = $sakit_pending->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                    <td><?= htmlspecialchars($row['keterangan']) ?></td>
                                    <td><a href="?approve_sakit=<?= $row['id'] ?>" class="btn-approve">✔</a> <a href="?reject_sakit=<?= $row['id'] ?>" class="btn-reject">✖</a></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">Tidak ada permintaan</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

<!-- Modal Absen -->
<div id="absenModal" class="modal-absen">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('absenModal').style.display='none'">&times;</span>
        <h2>Form Absen</h2>
        <form method="post">
            <input type="hidden" name="absen" value="1">
            <?php if ($role === 'admin'): ?>
                <label>Pilih User:</label>
                <select name="username" required>
                    <?php $stmt = $conn->prepare("SELECT username, nama_lengkap FROM users ORDER BY nama_lengkap ASC"); $stmt->execute(); $users = $stmt->get_result(); while($u = $users->fetch_assoc()) { echo "<option value='".htmlspecialchars($u['username'])."'>".htmlspecialchars($u['nama_lengkap'])."</option>"; } ?>
                </select>
            <?php endif; ?>
            <label>Status:</label>
            <select name="status" required>
                <option value="clockin">Clock In</option>
                <option value="clockout">Clock Out</option>
                <option value="izin">Izin</option>
                <option value="sakit">Sakit</option>
            </select>
            <label>Keterangan:</label>
            <input type="text" name="keterangan" placeholder="Keterangan (opsional)">
            <button type="submit">Simpan</button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var hamburger = document.getElementById('hamburger');
        var sidebar = document.getElementById('sidebar');
        var body = document.body;
        
        if (hamburger && sidebar) {
            hamburger.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('active');
                body.classList.toggle('sidebar-open');
            });
            
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                    if (!sidebar.contains(event.target) && !hamburger.contains(event.target)) {
                        sidebar.classList.remove('active');
                        body.classList.remove('sidebar-open');
                    }
                }
            });
        }
    });
    
    window.onclick = function(e) { 
        var modal = document.getElementById('absenModal'); 
        if (e.target == modal) { 
            modal.style.display = "none"; 
        } 
    }
</script>
</body>
</html>