<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi Hari Ini</title>
    <link rel="stylesheet" href="../admin_css/dashboard.css">
    <link rel="stylesheet" href="../admin_css/data_absensi.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <span class="sidebar-title">Absensi System</span>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
            <a href="kelola_user.php"><span class="icon">👥</span> Kelola User</a>
            <a href="data_absensi.php" class="active"><span class="icon">🗓️</span> Absensi Hari Ini</a>
            <a href="laporan.php"><span class="icon">📄</span> Laporan</a>
            <a href="pengaturan.php"><span class="icon">⚙️</span> Pengaturan</a>
        </nav>
        <a href="keluar.php" class="sidebar-logout"><span class="icon">🔴</span> Logout</a>
    </aside>

    <div class="main-wrapper">
        <header class="navbar">
            <div class="navbar-title">
                <h1>Absensi Hari Ini</h1>
                <div class="navbar-greeting">Rekap absensi karyawan hari ini</div>
            </div>
            <div class="navbar-profile">
                <img src="../assets/admin.jpg" alt="admin" class="profile-img">
                <div class="profile-info">
                    <span class="profile-name">admin</span>
                    <span class="profile-role">Admin</span>
                </div>
            </div>
        </header>

        <main class="main-content">
            <div class="card absensi-card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <?php
                    // --- Tanggal otomatis Indonesia ---
                    setlocale(LC_TIME, 'id_ID.UTF-8', 'Indonesian_indonesia.1252'); 
                    $tanggal_hari_ini = strftime('%A, %d %B %Y');
                    $tanggal_hari_ini = ucfirst($tanggal_hari_ini);
                    ?>
                    <span class="card-title">Absensi Hari Ini - <?= $tanggal_hari_ini ?></span>
                </div>

                <?php
                include '../koneksi.php';
                $today = date("Y-m-d");

                // Hitung ringkasan
                $hadir = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as jml FROM absensi WHERE tanggal='$today' AND status='Hadir'"))['jml'];
                $terlambat = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as jml FROM absensi WHERE tanggal='$today' AND status='Terlambat'"))['jml'];
                $izin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as jml FROM absensi WHERE tanggal='$today' AND status='Izin'"))['jml'];
                $sakit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as jml FROM absensi WHERE tanggal='$today' AND status='Sakit'"))['jml'];
                ?>

                <!-- Ringkasan Absensi -->
                <div class="stats-container">
                    <div class="stat-box hadir">
                        <h3>Hadir</h3>
                        <p><?= $hadir ?></p>
                    </div>
                    <div class="stat-box terlambat">
                        <h3>Terlambat</h3>
                        <p><?= $terlambat ?></p>
                    </div>
                    <div class="stat-box izin">
                        <h3>Izin</h3>
                        <p><?= $izin ?></p>
                    </div>
                    <div class="stat-box sakit">
                        <h3>Sakit</h3>
                        <p><?= $sakit ?></p>
                    </div>
                </div>

                <!-- Tabel Absensi -->
                <table class="absensi-table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Query absensi hari ini
                        $absensi_today = mysqli_query($conn, "SELECT * FROM absensi WHERE tanggal = CURDATE() ORDER BY id ASC");

                        if ($absensi_today && mysqli_num_rows($absensi_today) > 0) {
                            while ($row = mysqli_fetch_assoc($absensi_today)) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($row['nama_lengkap']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['clock_in'] ?? '-') . '</td>';
                                echo '<td>' . htmlspecialchars($row['clock_out'] ?? '-') . '</td>';

                                // status badge
                                $statusClass = '';
                                if ($row['status'] === 'Hadir') $statusClass = 'status-hadir';
                                elseif ($row['status'] === 'Terlambat') $statusClass = 'status-terlambat';
                                elseif ($row['status'] === 'Alpha' || $row['status'] === 'Tidak Hadir') $statusClass = 'status-tidak-hadir';
                                elseif ($row['status'] === 'Izin') $statusClass = 'status-terlambat';
                                elseif ($row['status'] === 'Sakit') $statusClass = 'status-tidak-hadir';

                                echo '<td><span class="badge ' . $statusClass . '">' . htmlspecialchars($row['status']) . '</span></td>';
                                echo '<td><span class="aksi aksi-view"></span> <span class="aksi aksi-edit"></span></td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="5" style="text-align:center;color:#64748b;font-style:italic;">Belum ada data absensi hari ini</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
