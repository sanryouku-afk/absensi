<?php
session_start();
include '../koneksi.php';

// Cek apakah admin sudah login
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$message = '';

/* TAMBAH USER BARU */
if (isset($_POST['tambah_user'])) {

    $username  = mysqli_real_escape_string($conn, $_POST['username']);
    $nama      = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT);  // password DIHASH
    $role      = mysqli_real_escape_string($conn, $_POST['role']);
    $status    = mysqli_real_escape_string($conn, $_POST['status']);

    $sql = "INSERT INTO users (username, nama_lengkap, email, password, role, status)
            VALUES ('$username', '$nama', '$email', '$password', '$role', '$status')";

    if (mysqli_query($conn, $sql)) {
        $message = "User berhasil ditambahkan!";
    } else {
        $message = "Gagal menambah user: " . mysqli_error($conn);
    }
}

/* EDIT DATA USER */
if (isset($_POST['edit_user'])) {

    $id     = intval($_POST['id']);
    $username  = mysqli_real_escape_string($conn, $_POST['username']);
    $nama      = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $role      = mysqli_real_escape_string($conn, $_POST['role']);
    $status    = mysqli_real_escape_string($conn, $_POST['status']);

    /* Jika password ingin diubah */
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $update_password = ", password='$password'";
    } else {
        $update_password = "";
    }

    $sql = "UPDATE users SET 
            username='$username',
            nama_lengkap='$nama',
            email='$email',
            role='$role',
            status='$status'
            $update_password
            WHERE id=$id";

    if (mysqli_query($conn, $sql)) {
        $message = "User berhasil diperbarui!";
    } else {
        $message = "Gagal memperbarui user: " . mysqli_error($conn);
    }
}

/* HAPUS USER */
if (isset($_POST['hapus_user'])) {

    $id = intval($_POST['hapus_user']);

    if (mysqli_query($conn, "DELETE FROM users WHERE id=$id")) {
        $message = "User berhasil dihapus!";
    } else {
        $message = "Gagal menghapus user: " . mysqli_error($conn);
    }
}

/*ACC USER PENDING */
if (isset($_POST['acc_user'])) {
    $id = intval($_POST['acc_user']);
    mysqli_query($conn, "UPDATE users SET status='aktif' WHERE id=$id");
    $message = "User di-ACC!";
}

/*TOLAK USER PENDING (HAPUS)*/
if (isset($_POST['tolak_user'])) {
    $id = intval($_POST['tolak_user']);
    mysqli_query($conn, "DELETE FROM users WHERE id=$id");
    $message = "User pending ditolak!";
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= htmlspecialchars($tema) ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Kelola User</title>
    <link rel="stylesheet" href="../admin_css/kelola_user.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
</head>
<body>
    <!-- NAVBAR -->
    <div class="navbar">
        <div class="navbar-left">
            <button class="hamburger" id="hamburger" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
            <div class="navbar-title"><h1>Kelola User</h1></div>
        </div>
        <div class="navbar-profile">
            <img src="../assets/admin.jpg" alt="admin" class="profile-img">
            <div class="profile-info">
                <span class="profile-name"><?= htmlspecialchars($namaLogin) ?></span>
                <span class="profile-role"><?= htmlspecialchars(ucfirst($role)) ?></span>
            </div>
        </div>
    </div>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header"><span class="sidebar-title">Absensi System</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
            <a href="kelola_user.php" class="active"><span class="icon">👥</span> Kelola User</a>
            <a href="absensi.php"><span class="icon">📅</span> Absensi Hari Ini</a>
            <a href="laporan.php"><span class="icon">📊</span> Laporan</a>
            <a href="pengaturan.php"><span class="icon">⚙️</span> Pengaturan</a>
        </nav>
        <a href="keluar.php" class="sidebar-logout"><span class="icon">🔴</span> Logout</a>
    </aside>

    <div class="main-wrapper">
        <main class="main-content">
            <?php if ($message) { echo $message; } ?>

            <div class="card kelola-user-card">
                <div class="card-header">
                    <span class="card-title">Kelola User</span>
                    <button class="btn-primary" onclick="document.getElementById('modalUser').style.display='block'">+ Tambah User</button>
                </div>

                <!-- Search -->
                <input type="text" class="search-user" placeholder="Cari user..." id="searchUser" />

                <!-- Table -->
                <div class="table-responsive">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th class="sortable">Nama</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $sql = "SELECT * FROM users ORDER BY id ASC";
                        $result = mysqli_query($conn, $sql);
                        $no = 1;
                        if ($result) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                echo '<tr>';
                                echo '<td>' . $no . '</td>';
                                echo '<td>' . htmlspecialchars($row['nama_lengkap']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                                echo '<td><span class="badge role-user">' . htmlspecialchars(ucfirst($row['role'])) . '</span></td>';
                                echo '<td><span class="badge status-aktif">' . htmlspecialchars(ucfirst($row['status'])) . '</span></td>';
                                echo '<td style="text-align:center;">';
                                if (strtolower($row['status']) === 'pending') {
                                    echo '<form method="post" style="display:inline;">';
                                    echo '<button class="btn-edit" type="submit" name="acc_user" value="' . $row['id'] . '" onclick="return confirm(\'ACC user ini?\')">ACC</button> ';
                                    echo '</form>';
                                    echo '<form method="post" style="display:inline;">';
                                    echo '<button class="btn-hapus" type="submit" name="tolak_user" value="' . $row['id'] . '" onclick="return confirm(\'Tolak dan hapus user ini?\')">Tolak</button>';
                                    echo '</form>';
                                } else {
                                    echo '<button type="button" class="btn-edit" onclick="editUser(' . htmlspecialchars(json_encode($row)) . ')">Edit</button> ';
                                    echo '<form method="post" action="" style="display:inline;">';
                                    echo '<button class="btn-hapus" type="submit" name="hapus_user" value="' . $row['id'] . '" onclick="return confirm(\'Yakin hapus user ini?\')">Hapus</button>';
                                    echo '</form>';
                                }
                                echo '</td>';
                                echo '</tr>';
                                $no++;
                            }
                        }
                        ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pager -->
                <div id="pagerMount"></div>
            </div>

            <!-- Modal Tambah User -->
            <div id="modalUser" class="modal-absen" style="display:none;">
                <div class="modal-content">
                    <span class="close" onclick="document.getElementById('modalUser').style.display='none'">&times;</span>
                    <h2>Tambah User Baru</h2>
                    <form method="post" action="">
                        <label>Username</label>
                        <input type="text" name="username" required>
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" required>
                        <label>Email</label>
                        <input type="email" name="email" required>
                        <label>Password</label>
                        <div style="position:relative;">
                            <input type="password" name="password" id="add_password" required>
                            <span style="position:absolute;right:10px;top:12px;cursor:pointer;" onclick="toggleAddPassword()">👁️</span>
                        </div>
                        <label>Role</label>
                        <select name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                        <label>Status</label>
                        <select name="status" required>
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                        <button type="submit" name="tambah_user" class="btn-primary" style="margin-top:12px;">Simpan</button>
                    </form>
                </div>
            </div>

            <!-- Modal Edit User -->
            <div id="modalEditUser" class="modal-absen" style="display:none;">
                <div class="modal-content">
                    <span class="close" onclick="document.getElementById('modalEditUser').style.display='none'">&times;</span>
                    <h2>Edit User</h2>
                    <form method="post" action="">
                        <input type="hidden" name="id" id="edit_id">
                        <label>Username</label>
                        <input type="text" name="username" id="edit_username" required>
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" id="edit_nama_lengkap" required>
                        <label>Email</label>
                        <input type="email" name="email" id="edit_email" required>
                        <label>Password (kosongkan jika tidak diubah)</label>
                        <div style="position:relative;">
                            <input type="password" name="password" id="edit_password">
                            <span style="position:absolute;right:10px;top:12px;cursor:pointer;" onclick="toggleEditPassword()">👁️</span>
                        </div>
                        <label>Role</label>
                        <select name="role" id="edit_role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                        <label>Status</label>
                        <select name="status" id="edit_status" required>
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                        <button type="submit" name="edit_user" class="btn-primary" style="margin-top:12px;">Update</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
    // Toggle sidebar
    document.addEventListener('DOMContentLoaded', function() {
      const hamburger = document.getElementById('hamburger');
      const sidebar   = document.getElementById('sidebar');
      if (hamburger) {
        hamburger.addEventListener('click', function(e) {
          e.stopPropagation();
          sidebar.classList.toggle('active');
        });
        document.addEventListener('click', function(event) {
          const inSidebar = sidebar.contains(event.target);
          const inBurger  = hamburger.contains(event.target);
          if (!inSidebar && !inBurger && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
          }
        });
      }
      window.onclick = function(event) {
        const modalUser = document.getElementById('modalUser');
        const modalEdit = document.getElementById('modalEditUser');
        if (event.target === modalUser) modalUser.style.display = "none";
        if (event.target === modalEdit) modalEdit.style.display = "none";
      };
      window.editUser = function(row) {
        const data = typeof row === 'string' ? JSON.parse(row) : row;
        document.getElementById('edit_id').value           = data.id;
        document.getElementById('edit_username').value     = data.username;
        document.getElementById('edit_nama_lengkap').value = data.nama_lengkap;
        document.getElementById('edit_email').value        = data.email;
        document.getElementById('edit_role').value         = data.role;
        document.getElementById('edit_status').value       = data.status;
        document.getElementById('edit_password').value     = '';
        document.getElementById('modalEditUser').style.display = 'block';
      };
      window.toggleAddPassword = function() {
        const f = document.getElementById('add_password');
        if (f) f.type = (f.type === 'password') ? 'text' : 'password';
      };
      window.toggleEditPassword = function() {
        const f = document.getElementById('edit_password');
        if (f) f.type = (f.type === 'password') ? 'text' : 'password';
      };
    });

    // Pager + sort
    (function(){
      const PER_PAGE     = 6;
      const VIRTUAL_MAX  = 111;
      const VIRTUAL_PAGES= Math.max(1, Math.ceil(VIRTUAL_MAX / PER_PAGE));

      const table        = document.querySelector('.user-table');
      if(!table) return;
      const tbody        = table.querySelector('tbody');
      const thNama       = table.querySelector('thead th:nth-child(2)');
      const searchInput  = document.getElementById('searchUser');
      const tableWrapper = document.querySelector('.table-responsive');
      const pagerMount   = document.getElementById('pagerMount');

      const ALL_ROWS = Array.from(tbody.querySelectorAll('tr'));
      thNama?.classList.add('sortable');

      let page = 1;

      function cell(tr, i){
        const td = tr.children[i];
        return td ? td.textContent.trim().toLowerCase() : '';
      }
      function getSortDir(){
        if (thNama?.classList.contains('desc')) return 'desc';
        return thNama?.classList.contains('asc') ? 'asc' : 'asc';
      }
      function getFiltered(){
        const q = (searchInput?.value || '').trim().toLowerCase();
        let rows = ALL_ROWS.slice();
        if (q) {
          rows = rows.filter(tr => {
            const nama = cell(tr,1), email = cell(tr,2), role = cell(tr,3);
            return nama.includes(q) || email.includes(q) || role.includes(q);
          });
        }
        const dir = getSortDir();
        rows.sort((a,b)=>{
          const A = cell(a,1), B = cell(b,1);
          if (A<B) return (dir==='asc')? -1: 1;
          if (A>B) return (dir==='asc')?  1:-1;
          return 0;
        });
        return rows;
      }
      function renderTable(){
        const rows = getFiltered();
        tbody.innerHTML = '';
        const start = (page-1)*PER_PAGE, end = start + PER_PAGE;
        const pageRows = rows.slice(start, end);
        if (pageRows.length > 0) {
          pageRows.forEach((tr, idx) => {
            const numCell = tr.querySelector('td:first-child');
            if (numCell) numCell.textContent = (start + idx + 1);
            tbody.appendChild(tr);
          });
        } else {
          const colCount = table.querySelectorAll('thead th').length || 6;
          const trEmpty = document.createElement('tr');
          const tdEmpty = document.createElement('td');
          tdEmpty.colSpan = colCount;
          tdEmpty.className = 'empty-state';
          tdEmpty.textContent = 'Belum ada data';
          trEmpty.appendChild(tdEmpty);
          tbody.appendChild(trEmpty);
        }
      }
      function buildPager(){
        pagerMount.innerHTML = '';
        const bar   = document.createElement('div'); bar.className   = 'pager-bar';
        const label = document.createElement('div'); label.className = 'pager-label';
        label.textContent = `Page ${page} of ${VIRTUAL_PAGES}`;

        const pages = document.createElement('div'); pages.className = 'pager-pages';
        [1,2,3].forEach(n=>{
          if(n>VIRTUAL_PAGES) return;
          const b = document.createElement('button');
          b.className = 'pager-btn' + (n===page? ' active':'');
          b.textContent = n;
          b.addEventListener('click', ()=>{ page = n; renderAll(); });
          pages.appendChild(b);
        });
        const next = document.createElement('button');
        next.className = 'pager-btn icon';
        next.innerHTML = '&rsaquo;';
        next.disabled = (page >= VIRTUAL_PAGES);
        next.addEventListener('click', ()=>{ if(page<VIRTUAL_PAGES){ page++; renderAll(); } });
        pages.appendChild(next);

        bar.appendChild(label);
        bar.appendChild(pages);
        pagerMount.appendChild(bar);
      }
      function renderAll(){
        if (page < 1) page = 1;
        if (page > VIRTUAL_PAGES) page = VIRTUAL_PAGES;
        renderTable();
        buildPager();
      }

      searchInput?.addEventListener('input', ()=>{ page = 1; renderAll(); });
      thNama?.addEventListener('click', ()=>{
        thNama.classList.toggle('asc');
        thNama.classList.toggle('desc');
        page = 1; renderAll();
      });

      let sx=0, sy=0;
      tableWrapper?.addEventListener('touchstart', e=>{
        sx=e.changedTouches[0].clientX; sy=e.changedTouches[0].clientY;
      }, {passive:true});
      tableWrapper?.addEventListener('touchend', e=>{
        const dx=e.changedTouches[0].clientX-sx;
        const dy=e.changedTouches[0].clientY-sy;
        if (Math.abs(dx)>50 && Math.abs(dy)<30){
          if (dx<0 && page<VIRTUAL_PAGES){ page++; renderAll(); }
          if (dx>0 && page>1){ page--; renderAll(); }
        }
      }, {passive:true});

      renderAll();
    })();
    </script>
</body>
</html>