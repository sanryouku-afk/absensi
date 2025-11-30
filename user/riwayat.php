<?php
session_start();
include '../koneksi.php';
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit();
}
 $username = $_SESSION['username'];
 $nama_lengkap = $_SESSION['nama_lengkap'] ?? $username;

// Ambil data user untuk foto profil
 $userData = mysqli_query($conn, "SELECT foto FROM users WHERE username='$username' LIMIT 1");
 $userFoto = ($userData && mysqli_num_rows($userData) > 0) ? mysqli_fetch_assoc($userData)['foto'] : 'default.png';

// filter query
 $where = "username='$username'";
if (!empty($_GET['dari'])) {
    $where .= " AND tanggal >= '" . mysqli_real_escape_string($conn, $_GET['dari']) . "'";
}
if (!empty($_GET['sampai'])) {
    $where .= " AND tanggal <= '" . mysqli_real_escape_string($conn, $_GET['sampai']) . "'";
}
if (!empty($_GET['status'])) {
    $where .= " AND status = '" . mysqli_real_escape_string($conn, $_GET['status']) . "'";
}
 $result = mysqli_query($conn, "SELECT * FROM absensi WHERE $where ORDER BY tanggal DESC");

// summary
 $totalHari   = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM absensi WHERE username='$username'"));
 $hadir       = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM absensi WHERE username='$username' AND status='Hadir'"));
 $terlambat   = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM absensi WHERE username='$username' AND status='Terlambat'"));
 $tidakHadir  = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM absensi WHERE username='$username' AND status='Tidak Hadir'"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Absensi</title>
    <link rel="stylesheet" href="../css/riwayat_absensi.css">
</head>
<body>
    <!-- Hamburger menu for mobile -->
    <button class="hamburger" id="hamburger" aria-label="Menu">
        <span></span>
        <span></span>
        <span></span>
    </button>
    
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
                <li><a href="absensi.php"><span class="icon">🏠</span> Dashboard</a></li>
                <li><a href="riwayat.php" class="active"><span class="icon">📋</span> Riwayat Absensi</a></li>
                <li><a href="profil.php"><span class="icon">👤</span> Profil</a></li>
            </ul>
        </div>
        <a href="../logout.php" class="logout-btn"><span>🚪</span> Logout</a>
    </div>

    <div class="main-content">
        <div class="navbar">
            <div class="navbar-title">
                <h1>Riwayat Absensi</h1>
                <div class="navbar-greeting">Lihat riwayat kehadiran Anda</div>
            </div>
            <div class="navbar-profile">
                <img src="../uploads/<?= htmlspecialchars($userFoto) ?>" alt="Foto Profil" class="profile-avatar-navbar">
                <div>
                    <div class="profile-name-navbar"><?= htmlspecialchars($nama_lengkap) ?></div>
                    <div class="profile-role">User</div>
                </div>
            </div>
        </div>

        <div class="content-area">
            <div class="riwayat-card">
                <!-- Header -->
                <div class="riwayat-header">
                    <h2>Riwayat Absensi</h2>
                </div>

                <!-- Filter -->
                <form method="get" class="filter-section">
                    <div>
                        <label>Dari Tanggal</label><br>
                        <input type="date" name="dari" value="<?= htmlspecialchars($_GET['dari'] ?? '') ?>">
                    </div>
                    <div>
                        <label>Sampai Tanggal</label><br>
                        <input type="date" name="sampai" value="<?= htmlspecialchars($_GET['sampai'] ?? '') ?>">
                    </div>
                    <div>
                        <label>Status</label><br>
                        <select name="status">
                            <option value="">Semua</option>
                            <option value="Hadir" <?= (($_GET['status'] ?? '')=='Hadir'?'selected':'') ?>>Hadir</option>
                            <option value="Terlambat" <?= (($_GET['status'] ?? '')=='Terlambat'?'selected':'') ?>>Terlambat</option>
                            <option value="Izin" <?= (($_GET['status'] ?? '')=='Izin'?'selected':'') ?>>Izin</option>
                            <option value="Sakit" <?= (($_GET['status'] ?? '')=='Sakit'?'selected':'') ?>>Sakit</option>
                            <option value="Tidak Hadir" <?= (($_GET['status'] ?? '')=='Tidak Hadir'?'selected':'') ?>>Tidak Hadir</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn-filter">⏷ Filter</button>
                    </div>
                </form>

                <!-- Summary - Fixed -->
                <div class="summary-section">
                    <div class="summary-box">
                        <div class="summary-value"><?= $totalHari ?></div>
                        <div class="summary-label">Total Hari</div>
                    </div>
                    <div class="summary-box hadir">
                        <div class="summary-value"><?= $hadir ?></div>
                        <div class="summary-label">Hadir</div>
                    </div>
                    <div class="summary-box terlambat">
                        <div class="summary-value"><?= $terlambat ?></div>
                        <div class="summary-label">Terlambat</div>
                    </div>
                    <div class="summary-box tidak-hadir">
                        <div class="summary-value"><?= $tidakHadir ?></div>
                        <div class="summary-label">Tidak Hadir</div>
                    </div>
                </div>

                <!-- Table for Desktop -->
                <div class="table-container">
                    <table class="riwayat-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Jam Masuk</th>
                                <th>Jam Keluar</th>
                                <th>Jam Kerja</th>
                                <th>Status</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Reset the result pointer to use it again for mobile cards
                            mysqli_data_seek($result, 0);
                            
                            while ($row = mysqli_fetch_assoc($result)):
                                // Hitung jam kerja
                                $jamKerja = '-';
                                if (!empty($row['clock_in']) && !empty($row['clock_out'])) {
                                    $in = new DateTime($row['tanggal'] . ' ' . $row['clock_in']);
                                    $out = new DateTime($row['tanggal'] . ' ' . $row['clock_out']);
                                    $interval = $in->diff($out);
                                    $jamKerja = $interval->format('%h jam %i menit');
                                }
                                // Badge status
                                $statusClass = '';
                                $statusLabel = $row['status'];
                                if ($row['status'] === 'Selesai') {
                                    $statusClass = 'status-selesai';
                                    $statusLabel = 'Hadir';
                                } elseif ($row['status'] === 'Pending') {
                                    $statusClass = 'status-pending';
                                } elseif ($row['status'] === 'Terlambat') {
                                    $statusClass = 'status-terlambat';
                                } elseif ($row['status'] === 'Alpha' || $row['status'] === 'Tidak Hadir') {
                                    $statusClass = 'status-alpha';
                                }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($row['tanggal']) ?></td>
                                <td><?= htmlspecialchars($row['clock_in']) ?></td>
                                <td><?= htmlspecialchars($row['clock_out']) ?></td>
                                <td><?= htmlspecialchars($jamKerja) ?></td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                                <td><?= htmlspecialchars($row['keterangan']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="mobile-cards">
                    <h3 style="margin-bottom:15px;font-size:18px;">Detail Kehadiran</h3>
                    <?php 
                    // Reset the result pointer again
                    mysqli_data_seek($result, 0);
                    
                    while ($row = mysqli_fetch_assoc($result)):
                        // Hitung jam kerja
                        $jamKerja = '-';
                        if (!empty($row['clock_in']) && !empty($row['clock_out'])) {
                            $in = new DateTime($row['tanggal'] . ' ' . $row['clock_in']);
                            $out = new DateTime($row['tanggal'] . ' ' . $row['clock_out']);
                            $interval = $in->diff($out);
                            $jamKerja = $interval->format('%h jam %i menit');
                        }
                        // Badge status
                        $statusClass = '';
                        $statusLabel = $row['status'];
                        if ($row['status'] === 'Selesai') {
                            $statusClass = 'status-selesai';
                            $statusLabel = 'Hadir';
                        } elseif ($row['status'] === 'Pending') {
                            $statusClass = 'status-pending';
                        } elseif ($row['status'] === 'Terlambat') {
                            $statusClass = 'status-terlambat';
                        } elseif ($row['status'] === 'Alpha' || $row['status'] === 'Tidak Hadir') {
                            $statusClass = 'status-alpha';
                        }
                    ?>
                    <div class="attendance-card">
                        <div class="card-header">
                            <span class="date"><?= htmlspecialchars($row['tanggal']) ?></span>
                            <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="time-info">
                                <div>
                                    <span class="label">Masuk:</span>
                                    <span><?= htmlspecialchars($row['clock_in']) ?></span>
                                </div>
                                <div>
                                    <span class="label">Keluar:</span>
                                    <span><?= htmlspecialchars($row['clock_out']) ?></span>
                                </div>
                            </div>
                            <div>
                                <span class="label">Jam Kerja:</span>
                                <span><?= htmlspecialchars($jamKerja) ?></span>
                            </div>
                            <div class="keterangan">
                                <span class="label">Keterangan:</span>
                                <span><?= htmlspecialchars($row['keterangan']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Hamburger menu logic
        function isMobile() { return window.innerWidth <= 768; }
        document.addEventListener('DOMContentLoaded', function() {
            var hamburger = document.getElementById('hamburger');
            var sidebar = document.getElementById('sidebar');
            var sidebarClose = document.getElementById('sidebar-close');
            
            function updateHamburger() {
                if (isMobile()) hamburger.style.display = 'block';
                else { 
                    hamburger.style.display = 'none'; 
                    sidebar.classList.remove('show');
                    hamburger.classList.remove('active');
                }
            }
            
            updateHamburger();
            
            // Toggle sidebar saat hamburger diklik
            hamburger.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('show');
                hamburger.classList.toggle('active');
            });
            
            // Tutup sidebar saat tombol close diklik
            sidebarClose.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.remove('show');
                hamburger.classList.remove('active');
            });
            
            // Tutup sidebar saat area di luar diklik
            document.addEventListener('click', function(e) {
                if (isMobile() && sidebar.classList.contains('show')) {
                    if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                        sidebar.classList.remove('show');
                        hamburger.classList.remove('active');
                    }
                }
            });
            
            // Update hamburger saat ukuran layar berubah
            window.addEventListener('resize', updateHamburger);
        });
    </script>
</body>
</html>