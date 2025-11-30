<?php
session_start();
date_default_timezone_set('Asia/Makassar'); // Set timezone WITA untuk Kalimantan Selatan
include '../koneksi.php';

// Cek apakah user sudah login
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit();
}

// Ambil data user dari session
$username = $_SESSION['username'];
$nama_lengkap = $_SESSION['nama_lengkap'] ?? $username;
$message = "";

// Fungsi untuk membuat tabel alpha_checks jika belum ada
function createAlphaChecksTable($conn) {
    $query = "CREATE TABLE IF NOT EXISTS alpha_checks (
        id INT(11) PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) NOT NULL,
        tanggal DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_date (username, tanggal)
    )";
    return mysqli_query($conn, $query);
}

// Fungsi untuk membuat tabel izin_checkout jika belum ada
function createIzinCheckoutTable($conn) {
    $query = "CREATE TABLE IF NOT EXISTS izin_checkout (
        id INT(11) PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) NOT NULL,
        tanggal DATE NOT NULL,
        jam_pengajuan TIME NOT NULL,
        keterangan TEXT,
        status VARCHAR(20) NOT NULL DEFAULT 'Pending'
    )";
    return mysqli_query($conn, $query);
}

// Pastikan tabel izin_checkout ada (untuk checkout lebih awal)
if (!createIzinCheckoutTable($conn)) {
    $message = '<div class="error">Gagal membuat tabel izin_checkout: ' . mysqli_error($conn) . '</div>';
}

/* Fungsi untuk auto-check Alpha
 * Jika user belum absen sampai jam 16:00, otomatis tandai sebagai Alpha
 */
function checkAlphaStatus($conn, $username, $nama_lengkap) {
    // Pastikan tabel alpha_checks ada
    if (!createAlphaChecksTable($conn)) {
        return '<div class="error">Gagal membuat tabel alpha_checks: ' . mysqli_error($conn) . '</div>';
    }
    
    // Cek apakah sudah ada pengecekan hari ini
    $cekAlpha = mysqli_query($conn, "SELECT id FROM alpha_checks WHERE username='" . mysqli_real_escape_string($conn, $username) . "' AND tanggal=CURDATE()");
    if (!$cekAlpha) {
        return '<div class="error">Database error: ' . mysqli_error($conn) . '</div>';
    }
    
    if (mysqli_num_rows($cekAlpha) == 0) {
        // Cek waktu sekarang
        $current_time = new DateTime();
        $end_time = new DateTime('today 16:00:00');
        
        // Jika sudah lewat jam 16:00 dan belum absen, tandai sebagai Alpha
        if ($current_time > $end_time) {
            // Tandai user yang tidak absen hari ini sebagai Alpha
            $cekAbsensi = mysqli_query($conn, "SELECT id FROM absensi WHERE username='" . mysqli_real_escape_string($conn, $username) . "' AND tanggal=CURDATE()");
            if ($cekAbsensi && mysqli_num_rows($cekAbsensi) == 0) {
                $sql = "INSERT INTO absensi (username, nama_lengkap, tanggal, status, keterangan) 
                        VALUES ('" . mysqli_real_escape_string($conn, $username) . "', '" . mysqli_real_escape_string($conn, $nama_lengkap) . "', CURDATE(), 'Alpha', 'Tanpa Kehadiran')";
                if (!mysqli_query($conn, $sql)) {
                    return '<div class="error">Gagal menandai alpha: ' . mysqli_error($conn) . '</div>';
                }
            }
        }
        
        // Catat bahwa sudah dilakukan pengecekan hari ini
        $insertCheck = mysqli_query($conn, "INSERT INTO alpha_checks (username, tanggal) VALUES ('" . mysqli_real_escape_string($conn, $username) . "', CURDATE())");
        if (!$insertCheck) {
            return '<div class="error">Gagal mencatat pengecekan alpha: ' . mysqli_error($conn) . '</div>';
        }
    }
    
    return '';
}

/* Fungsi untuk update status checkout yang sudah di-ACC admin
 * Kalau admin approve, status absensi otomatis jadi 'Hadir'
 */
function checkApprovedCheckout($conn, $username) {
    // Cek apakah ada izin checkout yang sudah disetujui hari ini
    $cekApproved = mysqli_query($conn, "SELECT * FROM izin_checkout 
                                      WHERE username='" . mysqli_real_escape_string($conn, $username) . "' 
                                      AND tanggal = CURDATE() 
                                      AND status = 'Disetujui'");
    if ($cekApproved && mysqli_num_rows($cekApproved) > 0) {
        $data = mysqli_fetch_assoc($cekApproved);
        // Cek status absensi user hari ini
        $cekAbsensi = mysqli_query($conn, "SELECT status FROM absensi WHERE username='" . mysqli_real_escape_string($conn, $username) . "' AND tanggal = CURDATE()");
        $absensi = $cekAbsensi ? mysqli_fetch_assoc($cekAbsensi) : null;
        if ($absensi && $absensi['status'] === 'Pending') {
            // Update status absensi menjadi Hadir jika di-ACC admin
            $update = "UPDATE absensi SET 
                       status = 'Hadir', 
                       clock_out = '{$data['jam_pengajuan']}' 
                       WHERE username = '" . mysqli_real_escape_string($conn, $username) . "' 
                       AND tanggal = CURDATE()";
            mysqli_query($conn, $update);
            // Update status izin_checkout menjadi Processed agar tidak diproses lagi
            $update_izin = "UPDATE izin_checkout SET status = 'Processed' WHERE id = {$data['id']}";
            mysqli_query($conn, $update_izin);
            return true;
        }
    }
    return false;
}

// Panggil fungsi pengecekan Alpha
 $alphaCheckResult = checkAlphaStatus($conn, $username, $nama_lengkap);
if (!empty($alphaCheckResult)) {
    $message = $alphaCheckResult;
}

// Panggil fungsi untuk mengecek checkout yang sudah disetujui
checkApprovedCheckout($conn, $username);

/* ========================================
 * PROSES CLOCK IN
 * ======================================== */
if (isset($_POST['clock_in'])) {
    $u = mysqli_real_escape_string($conn, $username);
    $nama_esc = mysqli_real_escape_string($conn, $nama_lengkap);
    $cek = mysqli_query($conn, "SELECT id FROM absensi WHERE username='$u' AND tanggal = CURDATE()");
    if (!$cek) {
        $message = '<div class="error">Database error: ' . mysqli_error($conn) . '</div>';
    } elseif (mysqli_num_rows($cek) == 0) {
        // Ambil waktu sekarang dengan format yang benar
        $current_time = date('H:i:s');
        $current_hour = (int)date('H');
        $current_minute = (int)date('i');
        
        // Cek apakah tepat waktu atau terlambat (batas jam 08:00)
        if ($current_hour < 8 || ($current_hour == 8 && $current_minute == 0)) {
            $status = 'Hadir';
            $keterangan = 'Tepat Waktu';
        } else {
            $status = 'Terlambat';
            $keterangan = 'Terlambat';
        }
        
        $sql = "INSERT INTO absensi (username, nama_lengkap, tanggal, clock_in, status, keterangan) 
                VALUES ('$u', '$nama_esc', CURDATE(), '$current_time', '$status', '$keterangan')";
        if (mysqli_query($conn, $sql)) {
            $message = '<div class="success">Clock In berhasil! Status: ' . $keterangan . '</div>';
        } else {
            $message = '<div class="error">Gagal Clock In: ' . mysqli_error($conn) . '</div>';
        }
    } else {
        $message = '<div class="error">Anda sudah Clock In hari ini!</div>';
    }
}

/* ========================================
 * PROSES CLOCK OUT
 * ======================================== */
if (isset($_POST['clock_out'])) {
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan'] ?? '');
    $cek = mysqli_query($conn, "SELECT * FROM absensi 
                                WHERE username='" . mysqli_real_escape_string($conn, $username) . "' 
                                AND tanggal = CURDATE() 
                                AND clock_out IS NULL");
    if (!$cek) {
        $message = '<div class="error">Database error: ' . mysqli_error($conn) . '</div>';
    } elseif (mysqli_num_rows($cek) > 0) {
        // Ambil waktu sekarang dengan format yang benar
        $current_time = date('H:i:s');
        $current_hour = (int)date('H');
        
        // Checkout sebelum jam 16:00 harus minta izin ke admin dulu
        if ($current_hour < 16) {
            // CEK: Apakah user sudah mengirim permintaan checkout hari ini?
            $cekCheckout = mysqli_query($conn, "SELECT id FROM izin_checkout 
                                               WHERE username='" . mysqli_real_escape_string($conn, $username) . "' 
                                               AND tanggal = CURDATE()
                                               AND status IN ('Pending', 'Disetujui')");
            
            if (!$cekCheckout) {
                $message = '<div class="error">Database error: ' . mysqli_error($conn) . '</div>';
            } elseif (mysqli_num_rows($cekCheckout) > 0) {
                // User sudah mengirim permintaan checkout hari ini
                $message = '<div class="error">Anda sudah mengirim permintaan checkout lebih awal hari ini. Tunggu persetujuan admin.</div>';
            } else {
                // Simpan permintaan checkout lebih awal
                $insertCheckout = mysqli_query($conn, "INSERT INTO izin_checkout (username, tanggal, jam_pengajuan, keterangan, status) 
                                                      VALUES ('" . mysqli_real_escape_string($conn, $username) . "', CURDATE(), '$current_time', '$keterangan', 'Pending')");
                if ($insertCheckout) {
                    // Update status absensi menjadi Pending
                    $updateAbsensi = mysqli_query($conn, "UPDATE absensi SET status='Pending', keterangan='$keterangan' 
                                                         WHERE username='" . mysqli_real_escape_string($conn, $username) . "' AND tanggal = CURDATE()");
                    if ($updateAbsensi) {
                        $message = '<div class="success">Pengajuan checkout lebih awal berhasil dikirim! Menunggu persetujuan admin.</div>';
                    } else {
                        $message = '<div class="error">Gagal update status absensi: ' . mysqli_error($conn) . '</div>';
                    }
                } else {
                    $message = '<div class="error">Gagal mengirim pengajuan: ' . mysqli_error($conn) . '</div>';
                }
            }
        } else {
            // Kalau sudah lewat jam 16:00, langsung checkout aja
            $query = "UPDATE absensi SET clock_out = '$current_time', keterangan = '$keterangan', status='Selesai'
                      WHERE username='" . mysqli_real_escape_string($conn, $username) . "' AND tanggal = CURDATE()";
            if (mysqli_query($conn, $query)) {
                $message = '<div class="success">Clock Out berhasil!</div>';
            } else {
                $message = '<div class="error">Gagal Clock Out: ' . mysqli_error($conn) . '</div>';
            }
        }
    } else {
        $message = '<div class="error">Anda belum Clock In atau sudah Clock Out.</div>';
    }
}

/* ========================================
 * PROSES PENGAJUAN IZIN
 * ======================================== */
if (isset($_POST['izin'])) {
    $u = mysqli_real_escape_string($conn, $username);
    $nama_esc = mysqli_real_escape_string($conn, $nama_lengkap);
    $ket = mysqli_real_escape_string($conn, $_POST['izin_ket'] ?? '');
    $cek = mysqli_query($conn, "SELECT id FROM absensi WHERE username='$u' AND tanggal = CURDATE()");
    if (!$cek) {
        $message = '<div class="error">Database error: ' . mysqli_error($conn) . '</div>';
    } elseif (mysqli_num_rows($cek) == 0) {
        // Simpan ke tabel izin dengan status pending
        $sql = "INSERT INTO izin (username, nama_lengkap, tanggal, keterangan, status) 
                VALUES ('$u', '$nama_esc', CURDATE(), '$ket', 'Pending')";
        if (mysqli_query($conn, $sql)) {
            // Simpan juga ke tabel absensi dengan status pending
            $sql_absensi = "INSERT INTO absensi (username, nama_lengkap, tanggal, status, keterangan) 
                           VALUES ('$u', '$nama_esc', CURDATE(), 'Pending', '$ket')";
            if (mysqli_query($conn, $sql_absensi)) {
                $message = '<div class="success">Pengajuan izin berhasil dikirim! Menunggu persetujuan admin.</div>';
            } else {
                $message = '<div class="error">Gagal menyimpan data absensi: ' . mysqli_error($conn) . '</div>';
            }
        } else {
            $message = '<div class="error">Gagal mengajukan izin: ' . mysqli_error($conn) . '</div>';
        }
    } else {
        $message = '<div class="error">Absensi hari ini sudah tercatat.</div>';
    }
}

/* ========================================
 * PROSES LAPOR SAKIT
 * ======================================== */
if (isset($_POST['sakit'])) {
    $u = mysqli_real_escape_string($conn, $username);
    $nama_esc = mysqli_real_escape_string($conn, $nama_lengkap);
    $ket = mysqli_real_escape_string($conn, $_POST['sakit_ket'] ?? '');
    $cek = mysqli_query($conn, "SELECT id FROM absensi WHERE username='$u' AND tanggal = CURDATE()");
    if (!$cek) {
        $message = '<div class="error">Database error: ' . mysqli_error($conn) . '</div>';
    } elseif (mysqli_num_rows($cek) == 0) {
        // Simpan ke tabel sakit dengan status pending
        $sql = "INSERT INTO sakit (username, nama_lengkap, tanggal, keterangan, status) 
                VALUES ('$u', '$nama_esc', CURDATE(), '$ket', 'Pending')";
        if (mysqli_query($conn, $sql)) {
            // Simpan juga ke tabel absensi dengan status pending
            $sql_absensi = "INSERT INTO absensi (username, nama_lengkap, tanggal, status, keterangan) 
                           VALUES ('$u', '$nama_esc', CURDATE(), 'Pending', '$ket')";
            if (mysqli_query($conn, $sql_absensi)) {
                $message = '<div class="success">Lapor sakit berhasil dikirim! Menunggu persetujuan admin.</div>';
            } else {
                $message = '<div class="error">Gagal menyimpan data absensi: ' . mysqli_error($conn) . '</div>';
            }
        } else {
            $message = '<div class="error">Gagal melapor sakit: ' . mysqli_error($conn) . '</div>';
        }
    } else {
        $message = '<div class="error">Absensi hari ini sudah tercatat.</div>';
    }
}

/* ========================================
 * QUERY STATISTIK UNTUK DASHBOARD
 * Ambil semua data untuk ditampilkan di kartu statistik
 * ======================================== */

// Hitung total hari hadir
 $totalHadir = mysqli_query($conn, "SELECT COUNT(*) as total FROM absensi 
                                  WHERE username='" . mysqli_real_escape_string($conn, $username) . "' 
                                  AND status='Hadir'");
if ($totalHadir) {
    $totalHadirData = mysqli_fetch_assoc($totalHadir);
    $totalKehadiran = $totalHadirData['total'];
} else {
    $totalKehadiran = 0;
}

// Hitung total hari terlambat
 $totalTerlambat = mysqli_query($conn, "SELECT COUNT(*) as total FROM absensi 
                                      WHERE username='" . mysqli_real_escape_string($conn, $username) . "' 
                                      AND status='Terlambat'");
if ($totalTerlambat) {
    $totalTerlambatData = mysqli_fetch_assoc($totalTerlambat);
    $totalKeterlambatan = $totalTerlambatData['total'];
} else {
    $totalKeterlambatan = 0;
}

// Hitung total alpha (tanpa kehadiran)
 $totalAlpha = mysqli_query($conn, "SELECT COUNT(*) as total FROM absensi 
                                  WHERE username='" . mysqli_real_escape_string($conn, $username) . "' 
                                  AND status='Alpha'");
if ($totalAlpha) {
    $totalAlphaData = mysqli_fetch_assoc($totalAlpha);
    $totalTanpaKehadiran = $totalAlphaData['total'];
} else {
    $totalTanpaKehadiran = 0;
}

// Hitung total izin yang sudah disetujui
 $totalIzin = mysqli_query($conn, "SELECT COUNT(*) as total FROM absensi 
                                 WHERE username='" . mysqli_real_escape_string($conn, $username) . "' 
                                 AND status='Izin'");
if ($totalIzin) {
    $totalIzinData = mysqli_fetch_assoc($totalIzin);
    $totalIzin = $totalIzinData['total'];
} else {
    $totalIzin = 0;
}

// Hitung total sakit yang sudah disetujui
 $totalSakit = mysqli_query($conn, "SELECT COUNT(*) as total FROM absensi 
                                  WHERE username='" . mysqli_real_escape_string($conn, $username) . "' 
                                  AND status='Sakit'");
if ($totalSakit) {
    $totalSakitData = mysqli_fetch_assoc($totalSakit);
    $totalSakit = $totalSakitData['total'];
} else {
    $totalSakit = 0;
}

// Hitung total yang sudah selesai (sudah checkout)
 $totalSelesai = mysqli_query($conn, "SELECT COUNT(*) as total FROM absensi 
                                    WHERE username='" . mysqli_real_escape_string($conn, $username) . "' 
                                    AND status='Selesai'");
if ($totalSelesai) {
    $totalSelesaiData = mysqli_fetch_assoc($totalSelesai);
    $totalSelesai = $totalSelesaiData['total'];
} else {
    $totalSelesai = 0;
}

// Hitung total hari kerja keseluruhan
 $totalHariKerja = mysqli_query($conn, "SELECT COUNT(*) as total FROM absensi 
                                      WHERE username='" . mysqli_real_escape_string($conn, $username) . "' 
                                      AND status IN ('Hadir', 'Terlambat', 'Alpha', 'Izin', 'Sakit', 'Selesai')");
if ($totalHariKerja) {
    $totalHariKerjaData = mysqli_fetch_assoc($totalHariKerja);
    $totalHariKerja = $totalHariKerjaData['total'];
} else {
    $totalHariKerja = 0;
}

// Hitung persentase kehadiran - Termasuk status Selesai dalam perhitungan
 $persentase = $totalHariKerja > 0 ? round((($totalKehadiran + $totalSelesai) / $totalHariKerja) * 100) : 0;

// Cek status hari ini
 $cekToday = mysqli_query($conn, "SELECT * FROM absensi WHERE username='" . mysqli_real_escape_string($conn, $username) . "' AND tanggal = CURDATE()");
if ($cekToday) {
    $todayData = mysqli_fetch_assoc($cekToday);
} else {
    $todayData = null;
}

// Status hari ini
 $statusHariIni = "Belum Absen";
if ($todayData) {
    if ($todayData['status'] === 'Hadir' && is_null($todayData['clock_out'])) {
        $statusHariIni = "Hadir";
    } elseif ($todayData['status'] === 'Hadir' && !is_null($todayData['clock_out'])) {
        $statusHariIni = "Selesai";
    } elseif ($todayData['status'] === 'Terlambat' && is_null($todayData['clock_out'])) {
        $statusHariIni = "Terlambat";
    } elseif ($todayData['status'] === 'Terlambat' && !is_null($todayData['clock_out'])) {
        $statusHariIni = "Selesai";
    } elseif ($todayData['status'] === 'Pending') {
        $statusHariIni = "Pending";
    } elseif ($todayData['status'] === 'Selesai') {
        $statusHariIni = "Selesai";
    } else {
        $statusHariIni = $todayData['status'];
    }
}

// Cek apakah user sudah mengirim permintaan checkout hari ini
 $cekCheckoutToday = mysqli_query($conn, "SELECT * FROM izin_checkout 
                                        WHERE username='" . mysqli_real_escape_string($conn, $username) . "' 
                                        AND tanggal = CURDATE()");
 $hasCheckoutToday = false;
if ($cekCheckoutToday && mysqli_num_rows($cekCheckoutToday) > 0) {
    while ($row = mysqli_fetch_assoc($cekCheckoutToday)) {
        if (in_array($row['status'], ['Pending', 'Disetujui'])) {
            $hasCheckoutToday = true;
            break;
        }
    }
}

// Ambil data absensi user untuk tabel (10 terbaru)
 $result = mysqli_query($conn, "SELECT * FROM absensi WHERE username='" . mysqli_real_escape_string($conn, $username) . "' ORDER BY tanggal DESC LIMIT 10");

// Ambil data user untuk foto profil
 $userData = mysqli_query($conn, "SELECT foto FROM users WHERE username='" . mysqli_real_escape_string($conn, $username) . "' LIMIT 1");
 $userFoto = ($userData && mysqli_num_rows($userData) > 0) ? mysqli_fetch_assoc($userData)['foto'] : 'default.png';

// DEBUG: Tampilkan waktu server untuk debugging
 $now = new DateTime();
 $start = new DateTime('today 08:00:00');
 $end = new DateTime('today 16:00:00');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard User - Absensi System</title>
    <link rel="stylesheet" href="../css/style_absensi.css">
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <div class="navbar-profile">
            <img src="../uploads/<?= htmlspecialchars($userFoto) ?>" alt="Foto Profil" class="profile-avatar-navbar">
            <span class="profile-name-navbar"><?= htmlspecialchars($nama_lengkap) ?></span>
        </div>
    </div>

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
                <li><a href="absensi.php" class="active">
                    <span class="icon">🏠</span>
                    Dashboard
                </a></li>
                <li><a href="riwayat.php">
                    <span class="icon">📋</span>
                    Riwayat Absensi
                </a></li>
                <li><a href="profil.php">
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
        <div class="content">
            <!-- Notifikasi -->
            <?php if ($message): ?>
            <div class="notification <?php echo strpos($message, 'berhasil') !== false ? 'success' : 'error'; ?>">
                <?= $message; ?>
            </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card status">
                    <div class="stat-header">
                        <div class="stat-title">Status Hari Ini</div>
                        <div class="stat-icon">⏰</div>
                    </div>
                    <div class="stat-value"><?= htmlspecialchars($statusHariIni); ?></div>
                </div>

                <div class="stat-card attendance">
                    <div class="stat-header">
                        <div class="stat-title">Total Kehadiran</div>
                        <div class="stat-icon">✅</div>
                    </div>
                    <div class="stat-value"><?= $totalKehadiran; ?> Hari</div>
                </div>

                <div class="stat-card late">
                    <div class="stat-header">
                        <div class="stat-title">Total Terlambat</div>
                        <div class="stat-icon">⏱️</div>
                    </div>
                    <div class="stat-value"><?= $totalKeterlambatan; ?> Hari</div>
                </div>

                <div class="stat-card alpha">
                    <div class="stat-header">
                        <div class="stat-title">Tanpa Kehadiran</div>
                        <div class="stat-icon">❌</div>
                    </div>
                    <div class="stat-value"><?= $totalTanpaKehadiran; ?> Hari</div>
                </div>

                <div class="stat-card percentage">
                    <div class="stat-header">
                        <div class="stat-title">Persentase</div>
                        <div class="stat-icon">📊</div>
                    </div>
                    <div class="stat-value"><?= $persentase; ?>%</div>
                </div>
            </div>

            <!-- Action Section -->
            <div class="action-section">
                <h2>Aksi Absensi</h2>
                <div class="action-buttons">
<?php
 $jam_sekarang = (int)date('H');
if (!$todayData): ?>
    <!-- Belum absen sama sekali -->
    <form method="post" style="grid-column: 1;">
        <button type="submit" name="clock_in" class="btn btn-checkin">
            <span>→</span>
            Check In
        </button>
    </form>
    <button class="btn btn-checkout" disabled>
        <span>←</span>
        Check Out
    </button>
<?php elseif ($todayData && is_null($todayData['clock_out']) && ($todayData['status'] === 'Hadir' || $todayData['status'] === 'Terlambat')): ?>
    <!-- Sudah check in, belum check out -->
    <button class="btn btn-checkin" disabled>
        <span>✓</span>
        Checked In
    </button>
    <button type="button" id="btnCustomCheckout" class="btn btn-checkout">
        <span>←</span>
        Check Out
    </button>
<?php elseif ($todayData && $todayData['status'] === 'Pending'): ?>
    <!-- Status pending -->
    <button class="btn btn-checkin" disabled>
        <span>✓</span>
        Checked In
    </button>
    <button class="btn btn-checkout" disabled>
        <span>⏱</span>
        Pending
    </button>
<?php elseif ($todayData && $todayData['status'] === 'Selesai'): ?>
    <!-- Status selesai -->
    <button class="btn btn-checkin" disabled>
        <span>✓</span>
        Selesai
    </button>
    <button class="btn btn-checkout" disabled>
        <span>✓</span>
        Selesai
    </button>
<?php else: ?>
    <!-- Sudah check out atau izin/sakit/alpha -->
    <button class="btn btn-checkin" disabled>
        <span>✓</span>
        Selesai
    </button>
    <button class="btn btn-checkout" disabled>
        <span>✓</span>
        Selesai
    </button>
<?php endif; ?>
                </div>

                <!-- Tombol Izin & Sakit -->
                <?php if (!$todayData): ?>
                <div class="special-actions">
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="izin_ket" value="Izin">
                        <button type="submit" name="izin" class="btn btn-izin">
                            📝 Ajukan Izin
                        </button>
                    </form>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="sakit_ket" value="Sakit">
                        <button type="submit" name="sakit" class="btn btn-sakit">
                            🏥 Lapor Sakit
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tombol Ajukan Checkout -->
            <!-- Tampilkan status pengajuan jika ada -->
            <?php
            $izinCheckout = mysqli_query($conn, "SELECT * FROM izin_checkout WHERE username='$username' AND tanggal=CURDATE() AND status IN ('Pending','Disetujui') ORDER BY id DESC LIMIT 1"); 
            if ($izinCheckout && mysqli_num_rows($izinCheckout) > 0 && $todayData && $todayData['status'] === 'Pending' && !empty($todayData['clock_in'])) {
                $izin = mysqli_fetch_assoc($izinCheckout);
                echo '<div class="notification warning">Pengajuan checkout: ' . htmlspecialchars($izin['keterangan']) . ' [' . htmlspecialchars($izin['status']) . ']</div>';
            }
            ?>
            <!-- Popup Ajukan Checkout -->
            <div id="modalCheckout" class="modal-absen" style="display:none;">
              <div class="modal-content">
                <span class="close" onclick="document.getElementById('modalCheckout').style.display='none'">&times;</span>
                <h2>Ajukan Checkout Lebih Awal</h2>
                <form method="post">
                  <label>Alasan keluar sebelum jam pulang:</label><br>
                  <textarea name="alasan_checkout" required style="width:100%;min-height:60px;"></textarea><br><br>
                  <button type="submit" name="ajukan_checkout" class="btn-profile-save">Ajukan</button>
                </form>
              </div>
            </div>
            <script>
            document.getElementById('btnAjukanCheckout')?.addEventListener('click', function() {
              document.getElementById('modalCheckout').style.display = 'block';
            });
            </script>
            <?php
            // Proses pengajuan checkout
            if (isset($_POST['ajukan_checkout']) && !empty($_POST['alasan_checkout'])) {
                $keterangan = mysqli_real_escape_string($conn, $_POST['alasan_checkout']);
                $jam = date('H:i:s');
                $tanggal = date('Y-m-d');
                // Cek sudah pernah ajukan hari ini
                $cek = mysqli_query($conn, "SELECT * FROM izin_checkout WHERE username='$username' AND tanggal='$tanggal'");
                if (mysqli_num_rows($cek) == 0) {
                    // Update absensi: status Pending dan keterangan alasan user
                    mysqli_query($conn, "UPDATE absensi SET status='Pending', keterangan='$keterangan' WHERE username='$username' AND tanggal='$tanggal'");
                    // Insert izin_checkout
                    mysqli_query($conn, "INSERT INTO izin_checkout (username, tanggal, jam_pengajuan, keterangan, status) VALUES ('$username', '$tanggal', '$jam', '$keterangan', 'Pending')");
                }
                echo "<script>location.href='absensi.php';</script>";
                exit;
            }
            ?>

            <!-- History Table -->
            <div class="history-section">
                <h2>Riwayat Absensi Terbaru</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Hari</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Jam Kerja</th>
                                <th>Status</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result && mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    // Hitung jam kerja (fix pakai tanggal + jam)
                                    $jamKerja = '-';
                                    if (!empty($row['clock_in']) && !empty($row['clock_out'])) {
                                        $in = new DateTime($row['tanggal'] . ' ' . $row['clock_in']);
                                        $out = new DateTime($row['tanggal'] . ' ' . $row['clock_out']);
                                        $interval = $in->diff($out);
                                        $jamKerja = $interval->format('%h jam %i menit');
                                    }
                                    
                                    // Format tanggal
                                    $tanggal = date('d-m-Y', strtotime($row['tanggal']));
                                    $hari = date('l', strtotime($row['tanggal']));
                                    $namaHari = array(
                                        'Sunday' => 'Minggu',
                                        'Monday' => 'Senin',
                                        'Tuesday' => 'Selasa',
                                        'Wednesday' => 'Rabu',
                                        'Thursday' => 'Kamis',
                                        'Friday' => 'Jumat',
                                        'Saturday' => 'Sabtu'
                                    );
                                    $hari = $namaHari[$hari];
                                    
                                    // Tambahkan class untuk status
                                    $statusClass = '';
                                    if ($row['status'] === 'Selesai') {
                                        $statusClass = 'status-selesai';
                                    } elseif ($row['status'] === 'Pending') {
                                        $statusClass = 'status-pending';
                                    } elseif ($row['status'] === 'Terlambat') {
                                        $statusClass = 'status-terlambat';
                                    } elseif ($row['status'] === 'Alpha') {
                                        $statusClass = 'status-alpha';
                                    }
                                    
                                    echo "<tr>
                                        <td>" . htmlspecialchars($tanggal) . "</td>
                                        <td>" . htmlspecialchars($hari) . "</td>
                                        <td>" . htmlspecialchars($row['clock_in']) . "</td>
                                        <td>" . htmlspecialchars($row['clock_out']) . "</td>
                                        <td>" . htmlspecialchars($jamKerja) . "</td>
                                        <td><span class='$statusClass'>" . htmlspecialchars($row['status']) . "</span></td>
                                        <td>" . htmlspecialchars($row['keterangan']) . "</td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr>
                                    <td colspan='7' style='text-align: center; color: #64748b; font-style: italic;'>Belum ada data absensi</td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <div class="view-all">
                    <a href="riwayat.php" class="btn btn-view-all">Lihat Semua Riwayat</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Custom Checkout -->
    <div id="modalCustomCheckout" class="modal-absen" style="display:none;z-index:9999;position:fixed;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.3);">
  <div class="modal-content" style="margin:10vh auto;max-width:400px;">
    <span class="close" onclick="document.getElementById('modalCustomCheckout').style.display='none'">&times;</span>
    <h2>Checkout Lebih Awal</h2>
    <form method="post" id="formCustomCheckout">
      <label>Alasan keluar sebelum jam pulang (wajib diisi):</label><br>
      <textarea name="alasan_checkout_custom" required style="width:100%;min-height:60px;"></textarea><br><br>
      <button type="submit" name="ajukan_checkout_custom" class="btn-profile-save">Ajukan</button>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var btn = document.getElementById('btnCustomCheckout');
  if (btn) {
    btn.onclick = function() {
      var now = new Date();
      var jam = now.getHours();
      if (jam < 16) {
        document.getElementById('modalCustomCheckout').style.display = 'block';
      } else {
        var form = document.createElement('form');
        form.method = 'post';
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'clock_out';
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
      }
    }
  }
});
</script>
<?php
if (isset($_POST['ajukan_checkout_custom']) && !empty($_POST['alasan_checkout_custom'])) {
    $keterangan = mysqli_real_escape_string($conn, $_POST['alasan_checkout_custom']);
    $jam = date('H:i:s');
    $tanggal = date('Y-m-d');
    // Update absensi: status Pending dan keterangan alasan user
    mysqli_query($conn, "UPDATE absensi SET status='Pending', keterangan='$keterangan' WHERE username='$username' AND tanggal='$tanggal'");
    // Insert izin_checkout status Pending (menunggu acc admin)
    mysqli_query($conn, "INSERT INTO izin_checkout (username, tanggal, jam_pengajuan, keterangan, status) VALUES ('$username', '$tanggal', '$jam', '$keterangan', 'Pending')");
    echo "<script>location.href='absensi.php';</script>";
    exit;
}
    ?>

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
                mobileToggle.classList.toggle('active'); // Mengaktifkan animasi
            });
            
            // Tutup sidebar saat tombol close diklik
            sidebarClose.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.remove('show');
                mobileToggle.classList.remove('active'); // Menonaktifkan animasi
            });
            
            // Tutup sidebar saat area di luar diklik
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768 && sidebar.classList.contains('show')) {
                    if (!sidebar.contains(e.target) && !mobileToggle.contains(e.target)) {
                        sidebar.classList.remove('show');
                        mobileToggle.classList.remove('active'); // Menonaktifkan animasi
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
            
            // Custom checkout modal
            var btnCustomCheckout = document.getElementById('btnCustomCheckout');
            var modalCustomCheckout = document.getElementById('modalCustomCheckout');
            var span = document.getElementsByClassName("close")[0];
            
            if (btnCustomCheckout) {
                btnCustomCheckout.onclick = function() {
                    var now = new Date();
                    var jam = now.getHours();
                    if (jam < 16) {
                        modalCustomCheckout.style.display = "block";
                    } else {
                        var form = document.createElement('form');
                        form.method = 'post';
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'clock_out';
                        form.appendChild(input);
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            }
            
            if (span) {
                span.onclick = function() {
                    modalCustomCheckout.style.display = "none";
                }
            }
            
            window.onclick = function(event) {
                if (event.target == modalCustomCheckout) {
                    modalCustomCheckout.style.display = "none";
                }
            }
        });
    </script>
</body>
</html>