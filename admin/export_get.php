<?php
// TIDAK BOLEH ADA SPASI SEBELUM TAG PHP INI
session_start();

// ===== CEK LOGIN =====
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    http_response_code(401);
    echo "Unauthorized"; exit;
}

// ===== VALIDASI NAMA FILE =====
$fname = basename($_GET['f'] ?? '');
$path  = __DIR__ . '/exports/' . $fname;

if (!$fname || !is_file($path)) {
    http_response_code(404); echo "File tidak ditemukan"; exit;
}

// ===== HEADER LENGKAP =====
$size = filesize($path);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Content-Length: '.$size);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Accel-Buffering: no');

// ===== KIRIM FILE =====
readfile($path);
exit;
