<?php
session_start();
include '../koneksi.php';

// ===== CEK LOGIN =====
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header("Location: ../index.php");
    exit;
}

// Ambil tema dari pengaturan
$tema = 'light'; // default
$sql_tema = "SELECT tema FROM pengaturan LIMIT 1";
$result_tema = mysqli_query($conn, $sql_tema);
if ($result_tema && mysqli_num_rows($result_tema) > 0) {
    $row_tema = mysqli_fetch_assoc($result_tema);
    $tema = $row_tema['tema'] ?? 'light';
}

$role          = $_SESSION['role'] ?? 'user';
$usernameLogin = $_SESSION['username'] ?? '';
$namaLogin     = $_SESSION['nama_lengkap'] ?? $usernameLogin;

// ===== QUERY LAPORAN =====
$query = "
    SELECT 
        u.username, u.nama_lengkap, u.role,
        COALESCE(SUM(IF((a.status IN ('Hadir','Selesai')) 
            AND (a.keterangan='Tepat Waktu' OR a.keterangan='' 
            OR (a.status='Selesai' AND a.keterangan NOT IN ('Terlambat','Izin','Sakit','Alpha'))), 1, 0)), 0) AS hadir,
        COALESCE(SUM(IF((a.status IN ('Terlambat','Selesai')) 
            AND (a.keterangan='Terlambat'), 1, 0)), 0) AS terlambat,
        COALESCE(SUM(IF((a.status IN ('Izin','Selesai')) 
            AND (a.keterangan='Izin'), 1, 0)), 0) AS izin,
        COALESCE(SUM(IF((a.status IN ('Sakit','Selesai')) 
            AND (a.keterangan='Sakit'), 1, 0)), 0) AS sakit,
        COALESCE(SUM(IF(a.status IN ('Alpha','Tidak Hadir'), 1, 0)), 0) AS alpha
    FROM users u
    LEFT JOIN absensi a ON u.username = a.username
    GROUP BY u.username, u.nama_lengkap, u.role
    ORDER BY u.nama_lengkap
";
$result = mysqli_query($conn, $query) or die("Query gagal: " . mysqli_error($conn));

$laporan = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['hadir']      = (int)$row['hadir'];
    $row['terlambat']  = (int)$row['terlambat'];
    $row['izin']       = (int)$row['izin'];
    $row['sakit']      = (int)$row['sakit'];
    $row['alpha']      = (int)$row['alpha'];
    $row['total']      = $row['hadir'] + $row['terlambat'] + $row['izin'] + $row['sakit'] + $row['alpha'];
    $laporan[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= htmlspecialchars($tema) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Laporan Absensi</title>
  <link rel="stylesheet" href="../admin_css/laporan.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.1/xlsx.full.min.js"></script>
</head>
<body>
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header"><span class="sidebar-title">Absensi System</span></div>
    <nav class="sidebar-menu">
      <a href="dashboard.php"><span>🏠</span> Dashboard</a>
      <a href="kelola_user.php"><span>👥</span> Kelola User</a>
      <a href="absensi.php"><span>📅</span> Absensi Hari Ini</a>
      <a href="laporan.php" class="active"><span>📊</span> Laporan</a>
      <a href="pengaturan.php"><span>⚙️</span> Pengaturan</a>
    </nav>
    <a href="keluar.php" class="sidebar-logout"><span>🔴</span> Logout</a>
  </aside>

  <div class="main-wrapper">
    <!-- NAVBAR -->
    <div class="navbar">
      <div class="navbar-left">
        <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
        <div class="navbar-title"><h1>Laporan Absensi</h1></div>
      </div>
      <div class="navbar-profile">
        <img src="../assets/admin.jpg" alt="admin" class="profile-img">
        <div class="profile-info">
          <span class="profile-name"><?= htmlspecialchars($namaLogin) ?></span>
          <span class="profile-role"><?= htmlspecialchars(ucfirst($role)) ?></span>
        </div>
      </div>
    </div>

    <main class="main-content">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Rekap Absensi Karyawan</span>
          <div class="export-buttons">
            <button id="btnExport" class="export-btn">📥 Export CSV</button>
            <button id="btnExportExcel" class="export-btn">📥 Export Excel</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="absensi-table" id="laporanTable">
            <thead>
              <tr>
                <th>No</th><th>Nama</th><th>Role</th><th>Hadir</th><th>Terlambat</th>
                <th>Izin</th><th>Sakit</th><th>Alpha</th><th>Total</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($laporan): $no=1; foreach($laporan as $row): ?>
              <tr>
                <td><?= $no++ ?></td>
                <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                <td><?= htmlspecialchars($row['role']) ?></td>
                <td><span class="badge status-hadir"><?= $row['hadir'] ?></span></td>
                <td><span class="badge status-terlambat"><?= $row['terlambat'] ?></span></td>
                <td><span class="badge status-izin"><?= $row['izin'] ?></span></td>
                <td><span class="badge status-sakit"><?= $row['sakit'] ?></span></td>
                <td><span class="badge status-alpha"><?= $row['alpha'] ?></span></td>
                <td><b><?= $row['total'] ?></b></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="9" class="empty-state">Belum ada data absensi</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <!-- SCRIPT -->
  <script>
    // Sidebar
    document.addEventListener('DOMContentLoaded', function(){
      const s = document.getElementById('sidebar');
      const o = document.getElementById('sidebarOverlay');
      const h = document.getElementById('hamburger');
      h.addEventListener('click', ()=>{ s.classList.toggle('active'); o.classList.toggle('active'); });
      o.addEventListener('click', ()=>{ s.classList.remove('active'); o.classList.remove('active'); });
    });

    // ===== EXPORT CSV =====
    (function(){
      const btnCSV = document.getElementById('btnExport');
      const table = document.getElementById('laporanTable');

      btnCSV.addEventListener('click', () => {
        let csv = [];
        const rows = table.querySelectorAll('tr');

        rows.forEach(row => {
          let cols = row.querySelectorAll('td, th');
          let csvRow = [];
          cols.forEach(col => csvRow.push(col.innerText));
          csv.push(csvRow.join(","));
        });

        const csvFile = new Blob([csv.join("\n")], { type: 'text/csv' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(csvFile);
        link.download = 'laporan_absensi.csv';
        link.click();
      });
    })();

    // ===== EXPORT EXCEL =====
    (function(){
      const btnExcel = document.getElementById('btnExportExcel');
      const table = document.getElementById('laporanTable');

      btnExcel.addEventListener('click', () => {
        const wb = XLSX.utils.table_to_book(table, {sheet: 'Laporan Absensi'});

        // Generate Excel file content
        const wbout = XLSX.write(wb, {bookType: 'xlsx', type: 'binary'});

        // Create Blob object with Excel content
        const blob = new Blob([s2ab(wbout)], {type: 'application/octet-stream'});

        // Use FileSaver.js to trigger file download
        saveAs(blob, 'laporan_absensi.xlsx');
      });

      // Helper function to convert string to array buffer
      function s2ab(s) {
        const buf = new ArrayBuffer(s.length);
        const view = new Uint8Array(buf);
        for (let i = 0; i !== s.length; ++i) view[i] = s.charCodeAt(i) & 0xFF;
        return buf;
      }
    })();
  </script>
</body>
</html>