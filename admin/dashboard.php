<?php
error_reporting(E_ERROR | E_PARSE);
session_start();
$nama_user = $_SESSION['nama_user'] ?? 'Administrator';
$role_user = $_SESSION['role_user'] ?? 'Admin';

/* Koneksi database */
include '../koneksi.php';

/* Pastikan koneksi database tersedia */
if (!isset($conn) || !$conn) {
    $conn = null;
}

/* Ambil tema dari database (light/dark mode) */
$current_theme = 'light'; /*default*/
if ($conn) {
    $sql = "SELECT tema FROM pengaturan LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $current_theme = $row['tema'] ?? 'light';
    }
}

if (!$conn) {
	/* Fallback jika koneksi database gagal */
	$total_karyawan = 0;
	$hadir_hari_ini = 0;
	$izin_sakit = 0;
	$belum_absen = 0;
} else {
	/* Query statistik dashboard */
	
	/* Hitung total karyawan (role user) */
	$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role='user'");
	$stmt->execute();
	$res = $stmt->get_result();
	$total_karyawan = intval($res->fetch_assoc()['total'] ?? 0);
	$stmt->close();

	/* Hitung yang hadir hari ini (Hadir atau Selesai) */
	$stmt = $conn->prepare("SELECT COUNT(*) as total FROM absensi WHERE tanggal = CURDATE() AND status IN ('Hadir','Selesai')");
	$stmt->execute();
	$res = $stmt->get_result();
	$hadir_hari_ini = intval($res->fetch_assoc()['total'] ?? 0);
	$stmt->close();

	/* Hitung izin hari ini */
	$stmt = $conn->prepare("SELECT COUNT(*) as total FROM izin WHERE tanggal = CURDATE()");
	$stmt->execute();
	$res = $stmt->get_result();
	$izin_today = intval($res->fetch_assoc()['total'] ?? 0);
	$stmt->close();

	/* Hitung sakit hari ini */
	$stmt = $conn->prepare("SELECT COUNT(*) as total FROM sakit WHERE tanggal = CURDATE()");
	$stmt->execute();
	$res = $stmt->get_result();
	$sakit_today = intval($res->fetch_assoc()['total'] ?? 0);
	$stmt->close();

	$izin_sakit = $izin_today + $sakit_today;

	/* Hitung yang belum absen (total - hadir - izin/sakit) */
	$belum_absen = max(0, $total_karyawan - ($hadir_hari_ini + $izin_sakit));
}

/* Ambil 10 aktivitas terbaru untuk ditampilkan di dashboard */
$aktivitas_list = [];

if ($conn) {
    $sql_aktivitas = "
        SELECT 'absensi' AS tipe, a.nama_lengkap,
            CONCAT(a.tanggal, ' ', IF(a.clock_in <> '', a.clock_in, a.clock_out)) AS waktu,
            CASE WHEN a.clock_in <> '' AND (a.clock_out IS NULL OR a.clock_out = '') THEN 'Check In' ELSE 'Check Out' END AS judul,
            '' AS deskripsi,
            IF(a.clock_in <> '', a.clock_in, a.clock_out) AS jam,
            'absensi' AS jenis
        FROM absensi a
        WHERE ( (a.clock_in IS NOT NULL AND a.clock_in <> '') OR (a.clock_out IS NOT NULL AND a.clock_out <> '') )
          AND a.tanggal = CURDATE()

        UNION ALL

        SELECT 'izin' AS tipe, u.nama_lengkap,
            CONCAT(i.tanggal, ' 00:00:00') AS waktu,
            'Pengajuan Izin' AS judul,
            i.keterangan AS deskripsi,
            '00:00:00' AS jam,
            'izin' AS jenis
        FROM izin i
        JOIN users u ON i.username = u.username
        WHERE i.tanggal = CURDATE()

        UNION ALL

        SELECT 'sakit' AS tipe, u.nama_lengkap,
            CONCAT(s.tanggal, ' 00:00:00') AS waktu,
            'Pengajuan Sakit' AS judul,
            s.keterangan AS deskripsi,
            '00:00:00' AS jam,
            'sakit' AS jenis
        FROM sakit s
        JOIN users u ON s.username = u.username
        WHERE s.tanggal = CURDATE()

        UNION ALL

        SELECT 'checkout' AS tipe, u.nama_lengkap,
            CONCAT(ic.tanggal, ' ', COALESCE(ic.jam_pengajuan, '00:00:00')) AS waktu,
            'Permintaan Checkout' AS judul,
            ic.keterangan AS deskripsi,
            COALESCE(ic.jam_pengajuan, '00:00:00') AS jam,
            'checkout' AS jenis
        FROM izin_checkout ic
        JOIN users u ON ic.username = u.username
        WHERE ic.tanggal = CURDATE()

        ORDER BY waktu DESC
        LIMIT 10
    ";

    $res = mysqli_query($conn, $sql_aktivitas);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            /* Pilih emoji berdasarkan jenis aktivitas */
            $emoji = '🔔';
            if ($r['jenis'] === 'absensi') $emoji = '✅';
            elseif ($r['jenis'] === 'izin') $emoji = '📩';
            elseif ($r['jenis'] === 'sakit') $emoji = '🤒';
            elseif ($r['jenis'] === 'checkout') $emoji = '🏃';

            /* Format waktu untuk ditampilkan (HH:MM) */
            $waktu_display = '';
            if (!empty($r['waktu']) && strtotime($r['waktu']) !== false) {
                $waktu_display = date('H:i', strtotime($r['waktu']));
            }

            if ($r['jenis'] === 'absensi') {
                $deskripsi = trim($r['nama_lengkap'] . ' melakukan ' . strtolower($r['judul']) . ' pada ' . $waktu_display);
            } elseif ($r['jenis'] === 'checkout') {
                $deskripsi = trim($r['nama_lengkap'] . ' meminta checkout: ' . ($r['deskripsi'] ?: 'tanpa keterangan'));
            } else {
                $deskripsi = trim($r['nama_lengkap'] . ' - ' . ($r['deskripsi'] ?: $r['judul']));
            }

            $aktivitas_list[] = [
                'emoji' => $emoji,
                'judul' => $r['judul'],
                'deskripsi' => $deskripsi,
                'waktu' => $waktu_display
            ];
        }
    } else {
        $aktivitas_list = [];
    }
} else {
    $aktivitas_list = [];
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= htmlspecialchars($current_theme) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Admin - Absensi System</title>
  <link rel="stylesheet" href="../admin_css/dashboard.css">
</head>
<body>
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header"><span class="sidebar-title">Absensi System</span></div>
    <nav class="sidebar-menu">
      <a href="dashboard.php" class="active"><span class="icon">🏠</span> Dashboard</a>
      <a href="kelola_user.php"><span class="icon">👥</span> Kelola User</a>
      <a href="absensi.php"><span class="icon">📅</span> Absensi Hari Ini</a>
      <a href="laporan.php"><span class="icon">📊</span> Laporan</a>
      <a href="pengaturan.php"><span class="icon">⚙️</span> Pengaturan</a>
    </nav>
    <a href="keluar.php" class="sidebar-logout"><span class="icon">🔴</span> Logout</a>
  </aside>
  <div class="sidebar-overlay" id="sidebar-overlay"></div>

  <!-- Main Wrapper -->
  <div class="main-wrapper">
    <!-- Navbar -->
    <nav class="navbar">
      <div class="navbar-left">
        <button class="hamburger" id="hamburger" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
          <span></span><span></span><span></span>
        </button>
        <div class="navbar-title">
          <h1>Dashboard</h1>
          <div class="navbar-greeting">Selamat datang kembali, <?php echo htmlspecialchars($nama_user); ?>! 👋</div>
        </div>
      </div>
      <div class="navbar-profile">
        <div class="profile-img"><?php echo strtoupper(substr($nama_user, 0, 1)); ?></div>
        <div class="profile-info">
          <div class="profile-name"><?php echo htmlspecialchars($nama_user); ?></div>
          <div class="profile-role"><?php echo htmlspecialchars($role_user); ?></div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon blue">👥</div>
          <div class="stat-info">
            <h3><?php echo (int)$total_karyawan; ?></h3>
            <p>Total Karyawan</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">✓</div>
          <div class="stat-info">
            <h3><?php echo (int)$hadir_hari_ini; ?></h3>
            <p>Hadir Hari Ini</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon purple">📋</div>
          <div class="stat-info">
            <h3><?php echo (int)$izin_sakit; ?></h3>
            <p>Izin/Sakit</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange">⚠️</div>
          <div class="stat-info">
            <h3><?php echo (int)$belum_absen; ?></h3>
            <p>Belum Absen</p>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="card">
        <div class="card-header">
          <span>Quick Actions</span>
        </div>
        <div class="quick-actions">
          <a href="kelola_user.php?action=tambah" class="quick-action-btn qa-blue" aria-label="Tambah User baru">
            <div class="qa-icon">＋</div>
            <div class="qa-text">
              <span class="qa-title">Tambah User</span>
              <span class="qa-sub">Buat akun karyawan</span>
            </div>
          </a>
          <a href="absensi.php" class="quick-action-btn qa-green" aria-label="Menuju Absensi Hari Ini">
            <div class="qa-icon">✓</div>
            <div class="qa-text">
              <span class="qa-title">Absensi Hari Ini</span>
              <span class="qa-sub">Cek & kelola</span>
            </div>
          </a>
          <a href="laporan.php" class="quick-action-btn qa-purple" aria-label="Lihat Laporan Absensi">
            <div class="qa-icon">📊</div>
            <div class="qa-text">
              <span class="qa-title">Lihat Laporan</span>
              <span class="qa-sub">Rekap & unduh</span>
            </div>
          </a>
          <a href="pengaturan.php" class="quick-action-btn qa-slate" aria-label="Buka Pengaturan">
            <div class="qa-icon">⚙️</div>
            <div class="qa-text">
              <span class="qa-title">Pengaturan</span>
              <span class="qa-sub">Preferensi sistem</span>
            </div>
          </a>
        </div>
      </div>

      <!-- Aktivitas Terbaru -->
      <div class="card">
        <div class="card-header">
          <span>Aktivitas Terbaru</span>
        </div>
        <div class="activity-list">
          <?php foreach ($aktivitas_list as $a): ?>
            <div class="activity-item">
              <div class="activity-icon"><?php echo $a['emoji']; ?></div>
              <div class="activity-content">
                <h4><?php echo htmlspecialchars($a['judul']); ?></h4>
                <p><?php echo htmlspecialchars($a['deskripsi']); ?></p>
              </div>
              <div class="activity-time"><?php echo htmlspecialchars($a['waktu']); ?></div>
            </div>
          <?php endforeach; ?>
          <?php if (empty($aktivitas_list)): ?>
            <div class="empty-state">Belum ada aktivitas.</div>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

  <!--JAVASCRIPT - Sidebar & Interaksi-->
  <script>
  /* Toggle sidebar untuk mobile */
  document.addEventListener('DOMContentLoaded', function () {
    var hamburger = document.getElementById('hamburger');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebar-overlay');
    if (!hamburger || !sidebar || !overlay) return;

    function toggleSidebar(){
      var active = sidebar.classList.toggle('active');
      overlay.classList.toggle('active');
      hamburger.setAttribute('aria-expanded', active ? 'true' : 'false');
      document.body.classList.toggle('sidebar-open', active);
    }
    function closeSidebar(){
      sidebar.classList.remove('active');
      overlay.classList.remove('active');
      hamburger.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('sidebar-open');
    }

    hamburger.addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', closeSidebar);
    overlay.addEventListener('touchstart', function(e){ e.preventDefault(); closeSidebar(); });

    // Ripple ringan untuk Quick Actions
    document.querySelectorAll('.quick-action-btn').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const r = document.createElement('span');
        r.className = 'qa-ripple';
        btn.appendChild(r);
        requestAnimationFrame(()=> r.classList.add('show'));
        setTimeout(()=> r.remove(), 400);
      });
    });
  });
  </script>

  <style>
    /* Ripple minimal */
    .qa-ripple{position:absolute;inset:0;border-radius:inherit;background:rgba(108,99,255,.08);opacity:0;transition:opacity .35s ease}
    .qa-ripple.show{opacity:1}
  </style>
</body>
</html>