<?php
// pengaturan.php - dengan dark/light mode dan profil admin
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();

include '../koneksi.php';
if (!isset($conn) || !$conn) {
    die("Koneksi database tidak tersedia. Periksa file koneksi.php");
}

// Cek login
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header("Location: ../index.php");
    exit;
}

$username_login = $_SESSION['username'];
$nama_login = $_SESSION['nama_lengkap'] ?? 'Admin';

// Ambil data user yang login
$stmt_user = mysqli_prepare($conn, "SELECT email, nama_lengkap FROM users WHERE username=? LIMIT 1");
mysqli_stmt_bind_param($stmt_user, "s", $username_login);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user_data = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);

$user_email = $user_data['email'] ?? '';
$user_nama = $user_data['nama_lengkap'] ?? $nama_login;

/* Timezone maps */
$TZ_SHORT_TO_IANA = [
    'WIB'    => 'Asia/Jakarta',
    'WITA'   => 'Asia/Makassar',
    'WIT'    => 'Asia/Jayapura',
    'UTC'    => 'UTC',
    'SERVER' => date_default_timezone_get()
];

function iana_from_short($short) {
    global $TZ_SHORT_TO_IANA;
    return $TZ_SHORT_TO_IANA[$short] ?? $TZ_SHORT_TO_IANA['WIB'];
}

function short_from_iana($iana) {
    global $TZ_SHORT_TO_IANA;
    $iana = (string)$iana;
    foreach ($TZ_SHORT_TO_IANA as $short => $mapIana) {
        if ($iana === $mapIana || $iana === $short) return $short;
    }
    if (stripos($iana, 'jakarta') !== false) return 'WIB';
    if (stripos($iana, 'makassar') !== false) return 'WITA';
    if (stripos($iana, 'jayapura') !== false) return 'WIT';
    return 'SERVER';
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    } else {
        $_SESSION['csrf_token'] = bin2hex(uniqid('', true));
    }
}
$csrf_token = $_SESSION['csrf_token'];

// ambil row pengaturan
$sql = "SELECT * FROM pengaturan LIMIT 1";
$res = mysqli_query($conn, $sql);
if ($res === false) {
    error_log("Query error (SELECT pengaturan): " . mysqli_error($conn));
    die("Terjadi kesalahan pada database. Cek log server.");
}
$data = mysqli_fetch_assoc($res);

// backward compatibility
if (!isset($data['nama_kantor']) || $data['nama_kantor'] === '') {
    if (isset($data['nama_sekolah']) && $data['nama_sekolah'] !== '') {
        $data['nama_kantor'] = $data['nama_sekolah'];
    } else {
        $data['nama_kantor'] = '';
    }
}

$current_tz_short = isset($data['timezone']) ? short_from_iana($data['timezone']) : 'SERVER';

// prepare time defaults
$jm_h = 7; $jm_m = 0; $jp_h = 16; $jp_m = 0;
if (!empty($data['jam_masuk'])) {
    $parts = explode(':', $data['jam_masuk']);
    if (isset($parts[0])) $jm_h = (int)$parts[0];
    if (isset($parts[1])) $jm_m = (int)$parts[1];
}
if (!empty($data['jam_pulang'])) {
    $parts = explode(':', $data['jam_pulang']);
    if (isset($parts[0])) $jp_h = (int)$parts[0];
    if (isset($parts[1])) $jp_m = (int)$parts[1];
}

// Get current theme
$current_theme = $data['tema'] ?? 'light';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        die("Permintaan tidak valid (CSRF).");
    }

    // Handle update profil admin (email & password)
    if (isset($_POST['update_profil'])) {
        $new_email = trim($_POST['admin_email'] ?? '');
        $new_password = trim($_POST['admin_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if ($new_email === '') {
            $_SESSION['flash_error'] = "Email tidak boleh kosong.";
            header("Location: pengaturan.php");
            exit;
        }

        // Validasi email
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = "Format email tidak valid.";
            header("Location: pengaturan.php");
            exit;
        }

        // Update email
        $stmt = mysqli_prepare($conn, "UPDATE users SET email=? WHERE username=?");
        mysqli_stmt_bind_param($stmt, "ss", $new_email, $username_login);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Update password jika diisi
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                $_SESSION['flash_error'] = "Password dan konfirmasi password tidak sama.";
                header("Location: pengaturan.php");
                exit;
            }

            if (strlen($new_password) < 6) {
                $_SESSION['flash_error'] = "Password minimal 6 karakter.";
                header("Location: pengaturan.php");
                exit;
            }

            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET password=? WHERE username=?");
            mysqli_stmt_bind_param($stmt, "ss", $password_hash, $username_login);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $_SESSION['flash_success'] = "Profil berhasil diperbarui!";
        header("Location: pengaturan.php");
        exit;
    }

    // Handle update pengaturan kantor
    $nama_kantor = trim($_POST['nama_kantor'] ?? '');
    $alamat      = trim($_POST['alamat'] ?? '');
    $email_admin = trim($_POST['email_admin'] ?? '');
    $tema        = trim($_POST['tema'] ?? 'light');
    $tz_short    = $_POST['timezone_sel'] ?? 'WIB';
    $timezone_store = iana_from_short($tz_short);

    $jm_h_post = isset($_POST['jam_masuk_hour']) ? (int)$_POST['jam_masuk_hour'] : 0;
    $jm_m_post = isset($_POST['jam_masuk_minute']) ? (int)$_POST['jam_masuk_minute'] : 0;
    $jam_masuk = sprintf('%02d:%02d:00', $jm_h_post, $jm_m_post);

    $jp_h_post = isset($_POST['jam_pulang_hour']) ? (int)$_POST['jam_pulang_hour'] : 0;
    $jp_m_post = isset($_POST['jam_pulang_minute']) ? (int)$_POST['jam_pulang_minute'] : 0;
    $jam_pulang = sprintf('%02d:%02d:00', $jp_h_post, $jp_m_post);

    if ($nama_kantor === '') {
        $_SESSION['flash_error'] = "Nama kantor wajib diisi.";
        header("Location: pengaturan.php");
        exit;
    }

    if ($data) {
        $id = (int)$data['id'];
        $stmt = mysqli_prepare($conn, "UPDATE pengaturan SET nama_kantor=?, alamat=?, jam_masuk=?, jam_pulang=?, timezone=?, email_admin=?, tema=? WHERE id=?");
        if (!$stmt) {
            error_log("Prepare update failed: " . mysqli_error($conn));
            $_SESSION['flash_error'] = "Terjadi kesalahan saat menyiapkan query.";
            header("Location: pengaturan.php");
            exit;
        }
        mysqli_stmt_bind_param($stmt, "sssssssi", $nama_kantor, $alamat, $jam_masuk, $jam_pulang, $timezone_store, $email_admin, $tema, $id);
        $ok = mysqli_stmt_execute($stmt);
        if (!$ok) {
            error_log("Execute update failed: " . mysqli_error($conn));
            $_SESSION['flash_error'] = "Gagal menyimpan pengaturan.";
            mysqli_stmt_close($stmt);
            header("Location: pengaturan.php");
            exit;
        }
        mysqli_stmt_close($stmt);
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO pengaturan (nama_kantor, alamat, jam_masuk, jam_pulang, timezone, email_admin, tema) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            error_log("Prepare insert failed: " . mysqli_error($conn));
            $_SESSION['flash_error'] = "Terjadi kesalahan saat menyiapkan query.";
            header("Location: pengaturan.php");
            exit;
        }
        mysqli_stmt_bind_param($stmt, "sssssss", $nama_kantor, $alamat, $jam_masuk, $jam_pulang, $timezone_store, $email_admin, $tema);
        $ok = mysqli_stmt_execute($stmt);
        if (!$ok) {
            error_log("Execute insert failed: " . mysqli_error($conn));
            $_SESSION['flash_error'] = "Gagal menyimpan pengaturan.";
            mysqli_stmt_close($stmt);
            header("Location: pengaturan.php");
            exit;
        }
        mysqli_stmt_close($stmt);
    }

    header("Location: pengaturan.php?status=sukses");
    exit;
}

$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
$flash_success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= htmlspecialchars($current_theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pengaturan Kantor</title>
<link rel="stylesheet" href="../admin_css/pengaturan.css">
</head>
<body>
<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header"><span class="sidebar-title">Absensi System</span></div>
  <nav class="sidebar-menu">
    <a href="dashboard.php"><span>🏠</span> Dashboard</a>
    <a href="kelola_user.php"><span>👥</span> Kelola User</a>
    <a href="absensi.php"><span>📅</span> Absensi Hari Ini</a>
    <a href="laporan.php"><span>📊</span> Laporan</a>
    <a href="pengaturan.php" class="active"><span>⚙️</span> Pengaturan</a>
  </nav>
  <a href="keluar.php" class="sidebar-logout"><span>🔴</span> Logout</a>
</aside>

<div class="main-wrapper">
  <!-- NAVBAR -->
  <div class="navbar">
    <div class="navbar-left">
      <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
      <div class="navbar-title"><h1>Pengaturan Kantor <span class="tz-badge"><?= htmlspecialchars($current_tz_short) ?></span></h1></div>
    </div>
    <div class="navbar-profile">
      <img src="../assets/admin.png" class="profile-img" alt="Admin">
      <div class="profile-info"><span class="profile-name"><?= htmlspecialchars($user_nama) ?></span><span class="profile-role">Administrator</span></div>
    </div>
  </div>

  <div class="main-content">
    <?php if (isset($_GET['status']) && $_GET['status'] == 'sukses'): ?>
      <div class="notification notification-success"><span class="icon">✓</span> Pengaturan berhasil disimpan!</div>
    <?php endif; ?>

    <?php if ($flash_success): ?>
      <div class="notification notification-success"><span class="icon">✓</span> <?= htmlspecialchars($flash_success) ?></div>
    <?php endif; ?>

    <?php if ($flash_error): ?>
      <div class="notification notification-error"><span class="icon">✕</span> <?= htmlspecialchars($flash_error) ?></div>
    <?php endif; ?>

    <!-- Card Profil Admin -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">👤 Profil Admin</h2></div>
      <div class="card-body">
        <form method="post" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
          <input type="hidden" name="update_profil" value="1">

          <div class="form-group">
            <label>Username</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($username_login) ?>" disabled>
            <small style="color: var(--text-secondary); font-size: 0.85rem;">Username tidak dapat diubah</small>
          </div>

          <div class="form-group">
            <label>Nama Lengkap</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($user_nama) ?>" disabled>
            <small style="color: var(--text-secondary); font-size: 0.85rem;">Nama dapat diubah di menu Kelola User</small>
          </div>

          <div class="form-group">
            <label>Email Admin</label>
            <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($user_email) ?>" required>
          </div>

          <div class="form-group">
            <label>Password Baru (Kosongkan jika tidak ingin mengubah)</label>
            <div style="position: relative;">
              <input type="password" name="admin_password" id="admin_password" class="form-control" placeholder="Minimal 6 karakter">
              <button type="button" onclick="togglePassword('admin_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 1.2rem; color: var(--text-secondary);">👁️</button>
            </div>
          </div>

          <div class="form-group">
            <label>Konfirmasi Password Baru</label>
            <div style="position: relative;">
              <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Ulangi password baru">
              <button type="button" onclick="togglePassword('confirm_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 1.2rem; color: var(--text-secondary);">👁️</button>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Perbarui Profil</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Card Pengaturan Kantor -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">🏢 Pengaturan Kantor</h2></div>
      <div class="card-body">
        <form method="post" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

          <div class="form-group">
            <label>Nama Kantor</label>
            <input type="text" name="nama_kantor" class="form-control" value="<?= htmlspecialchars($data['nama_kantor'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label>Alamat</label>
            <textarea name="alamat" class="form-control" required><?= htmlspecialchars($data['alamat'] ?? '') ?></textarea>
          </div>

          <!-- Jam Masuk -->
          <div class="form-group">
            <label>Jam Masuk (24-hour) — <small><?= htmlspecialchars($current_tz_short) ?></small></label>
            <div class="time-selects">
              <select name="jam_masuk_hour" class="form-control">
                <?php for ($h=0;$h<24;$h++): $hh = sprintf('%02d',$h); ?>
                  <option value="<?= $hh ?>" <?= $h === $jm_h ? 'selected' : '' ?>><?= $hh ?></option>
                <?php endfor; ?>
              </select>
              :
              <select name="jam_masuk_minute" class="form-control">
                <?php for ($m=0;$m<60;$m++): $mm = sprintf('%02d',$m); ?>
                  <option value="<?= $mm ?>" <?= $m === $jm_m ? 'selected' : '' ?>><?= $mm ?></option>
                <?php endfor; ?>
              </select>
            </div>
          </div>

          <!-- Jam Pulang -->
          <div class="form-group">
            <label>Jam Pulang (24-hour) — <small><?= htmlspecialchars($current_tz_short) ?></small></label>
            <div class="time-selects">
              <select name="jam_pulang_hour" class="form-control">
                <?php for ($h=0;$h<24;$h++): $hh = sprintf('%02d',$h); ?>
                  <option value="<?= $hh ?>" <?= $h === $jp_h ? 'selected' : '' ?>><?= $hh ?></option>
                <?php endfor; ?>
              </select>
              :
              <select name="jam_pulang_minute" class="form-control">
                <?php for ($m=0;$m<60;$m++): $mm = sprintf('%02d',$m); ?>
                  <option value="<?= $mm ?>" <?= $m === $jp_m ? 'selected' : '' ?>><?= $mm ?></option>
                <?php endfor; ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>Zona Waktu</label>
            <select name="timezone_sel" class="form-control">
              <option value="SERVER" <?= $current_tz_short === 'SERVER' ? 'selected' : '' ?>>Server default (<?= htmlspecialchars(date_default_timezone_get()) ?>)</option>
              <option value="WIB" <?= $current_tz_short === 'WIB' ? 'selected' : '' ?>>WIB — Asia/Jakarta (GMT+7)</option>
              <option value="WITA" <?= $current_tz_short === 'WITA' ? 'selected' : '' ?>>WITA — Asia/Makassar (GMT+8)</option>
              <option value="WIT" <?= $current_tz_short === 'WIT' ? 'selected' : '' ?>>WIT — Asia/Jayapura (GMT+9)</option>
              <option value="UTC" <?= $current_tz_short === 'UTC' ? 'selected' : '' ?>>UTC</option>
            </select>
          </div>

          <div class="form-group">
            <label>Email Admin (untuk notifikasi sistem)</label>
            <input type="email" name="email_admin" class="form-control" value="<?= htmlspecialchars($data['email_admin'] ?? '') ?>">
            <small style="color: var(--text-secondary); font-size: 0.85rem;">Email untuk notifikasi sistem, berbeda dengan email login</small>
          </div>

          <div class="form-group">
            <label>Tema Aplikasi</label>
            <div class="theme-toggle">
              <div class="theme-option">
                <input type="radio" name="tema" id="theme-light" value="light" <?= $current_theme === 'light' ? 'checked' : '' ?>>
                <label for="theme-light"><span class="theme-icon">☀️</span> Light Mode</label>
              </div>
              <div class="theme-option">
                <input type="radio" name="tema" id="theme-dark" value="dark" <?= $current_theme === 'dark' ? 'checked' : '' ?>>
                <label for="theme-dark"><span class="theme-icon">🌙</span> Dark Mode</label>
              </div>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan</button>
            <button type="reset" class="btn btn-secondary">🔄 Reset</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  // Toggle password visibility
  function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    if (field) {
      field.type = field.type === 'password' ? 'text' : 'password';
    }
  }

  // Toggle sidebar
  const hamburger = document.getElementById("hamburger");
  const sidebar = document.getElementById("sidebar");
  if (hamburger && sidebar) {
    hamburger.addEventListener("click", () => { 
      sidebar.classList.toggle("active"); 
      hamburger.classList.toggle("active"); 
    });
    document.addEventListener("click", function(event) {
      if (window.innerWidth <= 768 && !sidebar.contains(event.target) && !hamburger.contains(event.target) && sidebar.classList.contains("active")) {
        sidebar.classList.remove("active"); 
        hamburger.classList.remove("active");
      }
    });
    window.addEventListener("resize", function() { 
      if (window.innerWidth > 768) { 
        sidebar.classList.remove("active"); 
        hamburger.classList.remove("active"); 
      } 
    });
  }

  // Auto-hide notifications
  setTimeout(() => { 
    document.querySelectorAll('.notification').forEach(n => { 
      n.style.opacity='0'; 
      setTimeout(()=>{ if(n.parentNode) n.remove(); },500); 
    }); 
  }, 5000);

  // Live theme preview
  const themeRadios = document.querySelectorAll('input[name="tema"]');
  themeRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      document.documentElement.setAttribute('data-theme', this.value);
    });
  });
</script>
</body>
</html>