<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Presensi</title>
    <link rel="stylesheet" href="../admin_css/style_sidebar.css">
    <link rel="stylesheet" href="../admin_css/style_admin.css">
</head>
<body>
    <div class="sidebar">
        <div class="profile">
            <div class="profile-info">
                <span class="profile-role">Admin Panel</span>
            </div>
        </div>
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="data_karyawan.php">Data Karyawan</a>
            <a href="data_presensi.php" class="active">Data Presensi</a>
            <a href="laporan.php">Laporan</a>
            <a href="pengaturan.php">Pengaturan</a>
            <a href="keluar.php" class="logout">Keluar</a>
        </nav>
    </div>
    <div class="main-wrapper">
        <header class="navbar">
            <div class="navbar-title">
                <h1>Data Presensi</h1>
                <div class="navbar-greeting">Selamat datang kembali, admin</div>
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
            <h1>Data Presensi</h1>
            <div class="filter-section">
                <form style="display: flex; gap: 18px; align-items: center;">
                    <div>
                        <label for="tanggal">Tanggal</label><br>
                        <input type="date" id="tanggal" name="tanggal" value="2023-06-15" class="form-control">
                    </div>
                    <div>
                        <label for="status">Status</label><br>
                        <select id="status" name="status" class="form-select">
                            <option value="semua">Semua</option>
                            <option value="hadir">Hadir</option>
                            <option value="izin">Izin</option>
                            <option value="sakit">Sakit</option>
                            <option value="alfa">Alfa</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary">Filter</button>
                    <button type="reset" class="btn-outline-primary">Reset</button>
                    <input type="text" placeholder="Cari..." style="margin-left:auto; padding:8px 16px; border-radius:8px; border:1px solid #ced4da;">
                </form>
            </div>
            <div class="card" style="margin-top:24px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h2 style="margin:0;">Data Presensi</h2>
                    <button class="btn-primary" onclick="window.location.href='export_presensi.php'" type="button">Export</button>
                </div>
                <table class="table" style="width:100%; margin-top:18px;">
                    <thead>
                        <tr>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>Tanggal</th>
                            <th>Jam Masuk</th>
                            <th>Jam Keluar</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data presensi akan muncul di sini setelah user melakukan clock in -->
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
