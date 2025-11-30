<?php
require __DIR__ . '/../vendor/autoload.php';
include '../koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

session_start();
if (!isset($_SESSION['username'])) {
    die("Anda harus login untuk mengakses halaman ini");
}

$username = $_SESSION['username'];

// Ambil data absensi dari database
$query = "SELECT tanggal, clock_in, clock_out, status, keterangan 
          FROM absensi 
          WHERE username = '$username' 
          ORDER BY tanggal DESC";
$result = mysqli_query($conn, $query);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Judul di atas
$sheet->setCellValue('A1', 'Riwayat Absensi - ' . $username);
$sheet->mergeCells('A1:E1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

// Header tabel
$sheet->setCellValue('A2', 'Tanggal');
$sheet->setCellValue('B2', 'Clock In');
$sheet->setCellValue('C2', 'Clock Out');
$sheet->setCellValue('D2', 'Status');
$sheet->setCellValue('E2', 'Keterangan');
$sheet->getStyle('A2:E2')->getFont()->setBold(true);
$sheet->getStyle('A2:E2')->getAlignment()->setHorizontal('center');

// Isi data
$row = 3;
while ($data = mysqli_fetch_assoc($result)) {
    $sheet->setCellValue('A' . $row, $data['tanggal']);
    $sheet->setCellValue('B' . $row, $data['clock_in']);
    $sheet->setCellValue('C' . $row, $data['clock_out']);
    $sheet->setCellValue('D' . $row, $data['status']);
    $sheet->setCellValue('E' . $row, $data['keterangan']);
    $row++;
}

// Auto width
foreach (range('A', 'E') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Border tabel
$styleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['argb' => '000000'],
        ],
    ],
];
$sheet->getStyle('A2:E' . ($row - 1))->applyFromArray($styleArray);

// Export ke Excel
$writer = new Xlsx($spreadsheet);
$filename = "Absensi_" . $username . "_" . date('Y-m-d') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$writer->save("php://output");
exit;
?>
