<?php
// TIDAK BOLEH ADA SPASI SEBELUM TAG PHP INI
session_start();
include '../koneksi.php';

// ===== CEK LOGIN =====
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Unauthorized']); exit;
}

// ===== SIAPKAN FOLDER exports (writable) =====
$exportsDir = __DIR__ . '/exports';
if (!is_dir($exportsDir)) { mkdir($exportsDir, 0775, true); }

// ===== NAMA FILE UNIK =====
$rand = bin2hex(random_bytes(8));
$filename = 'laporan_absensi_' . date('Ymd_His') . '_' . $rand . '.csv';
$filePath = $exportsDir . '/' . $filename;

// ===== QUERY DATA =====
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
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => mysqli_error($conn)]); exit;
}

// ===== TULIS CSV KE FILE =====
$out = fopen($filePath, 'w');
if ($out === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Tidak bisa membuat file']); exit;
}
fwrite($out, "\xEF\xBB\xBF"); // BOM untuk Excel Windows
fputcsv($out, ['No','Username','Nama Lengkap','Role','Hadir','Terlambat','Izin','Sakit','Alpha','Total']);

$no = 1;
while ($r = mysqli_fetch_assoc($res)) {
    $hadir      = (int)$r['hadir'];
    $terlambat  = (int)$r['terlambat'];
    $izin       = (int)$r['izin'];
    $sakit      = (int)$r['sakit'];
    $alpha      = (int)$r['alpha'];
    $total      = $hadir + $terlambat + $izin + $sakit + $alpha;

    fputcsv($out, [
        $no++,
        $r['username'],
        $r['nama_lengkap'],
        $r['role'],
        $hadir, $terlambat, $izin, $sakit, $alpha, $total
    ]);
}
fclose($out);

// ===== BANGUN URL KE export_get.php (lebih aman & header lengkap) =====
$scheme = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
  ? $_SERVER['HTTP_X_FORWARDED_PROTO']
  : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
$host   = $_SERVER['HTTP_HOST'];
$base   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\'); // .../admin
$fileUrl = $scheme.'://'.$host.$base.'/export_get.php?f='.rawurlencode($filename);

// ===== RESPON JSON =====
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['url' => $fileUrl]);
