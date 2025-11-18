<?php
// ⚠️ Pastikan TIDAK ADA karakter/whitespace sebelum `<?php` di baris paling pertama.
session_start();
include '../koneksi.php';

// ===== CEK LOGIN =====
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    http_response_code(401);
    // Balas page biasa agar JS mendeteksi dan mengarahkan
    echo "<!doctype html><meta charset='utf-8'><title>Unauthorized</title><p>Silakan login kembali.</p>";
    exit;
}

// ===== MATIKAN SEMUA OUTPUT BUFFERING & KOMpresi =====
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) { ob_end_clean(); }
}
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
ini_set('zlib.output_compression', '0');

// ===== NAMA FILE =====
$filename = 'laporan_absensi_'.date('Ymd_His').'.csv';

// ===== HEADER WAJIB =====
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
// Untuk nginx/proxy agar tidak dibuffer terlalu lama
header('X-Accel-Buffering: no');

// ===== BUKA STREAM =====
$out = fopen('php://output', 'w');
if ($out === false) { exit; }

// ===== BOM UTF-8 (Excel Windows friendly) =====
fwrite($out, "\xEF\xBB\xBF");

// ===== HEADER KOLOM =====
fputcsv($out, ['No','Username','Nama Lengkap','Role','Hadir','Terlambat','Izin','Sakit','Alpha','Total']);

// ===== QUERY =====
$sql = "
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
$res = mysqli_query($conn, $sql);
if (!$res) {
    // Tulis error agar kelihatan
    fputcsv($out, ['ERROR', mysqli_error($conn)]);
    fclose($out);
    exit;
}

// ===== STREAM DATA =====
$no = 1;
while ($r = mysqli_fetch_assoc($res)) {
    $hadir = (int)$r['hadir'];
    $terlambat = (int)$r['terlambat'];
    $izin = (int)$r['izin'];
    $sakit = (int)$r['sakit'];
    $alpha = (int)$r['alpha'];
    $total = $hadir + $terlambat + $izin + $sakit + $alpha;

    fputcsv($out, [
        $no++,
        $r['username'],
        $r['nama_lengkap'],
        $r['role'],
        $hadir, $terlambat, $izin, $sakit, $alpha, $total
    ]);
    // Flush periodik untuk streaming panjang
    if (function_exists('flush')) { flush(); }
}

// ===== TUTUP =====
fclose($out);
exit;
