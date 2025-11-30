<?php
require __DIR__ . '/../vendor/autoload.php';
include '../koneksi.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Query data presensi
$query = "SELECT nik, nama, tanggal, jam_masuk, jam_keluar, status FROM presensi ORDER BY tanggal DESC";
$result = mysqli_query($conn, $query);
if (!$result) {
    die('Query error: ' . mysqli_error($conn));
}
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header
$sheet->setCellValue('A1', 'NIK');
$sheet->setCellValue('B1', 'Nama');
$sheet->setCellValue('C1', 'Tanggal');
$sheet->setCellValue('D1', 'Jam Masuk');
$sheet->setCellValue('E1', 'Jam Keluar');
$sheet->setCellValue('F1', 'Status');

$row = 2;
while ($data = mysqli_fetch_assoc($result)) {
    $sheet->setCellValue('A' . $row, $data['nik']);
    $sheet->setCellValue('B' . $row, $data['nama']);
    $sheet->setCellValue('C' . $row, $data['tanggal']);
    $sheet->setCellValue('D' . $row, $data['jam_masuk']);
    $sheet->setCellValue('E' . $row, $data['jam_keluar']);
    $sheet->setCellValue('F' . $row, $data['status']);
    $row++;
}
foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
$writer = new Xlsx($spreadsheet);
$filename = "Data_Presensi_" . date('Y-m-d') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$writer->save("php://output");
exit;
?>