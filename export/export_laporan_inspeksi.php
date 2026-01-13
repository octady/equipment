<?php
session_start();
include "../config/database.php";
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Check login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Fetch data from database
$stmt = $conn->prepare("SELECT * FROM kegiatan_inspeksi WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal ASC, id ASC");
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$result = $stmt->get_result();
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$stmt->close();

if (count($data) == 0) {
    echo "<script>alert('Tidak ada data untuk periode yang dipilih.'); window.history.back();</script>";
    exit;
}

// Create new Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan Inspeksi');

// Format period date
$fromDate = date('d M Y', strtotime($date_from));
$toDate = date('d M Y', strtotime($date_to));

// === HEADER SECTION ===
// Row 1: Title (merged A1:G1)
$sheet->mergeCells('A1:G1');
$sheet->setCellValue('A1', 'LAPORAN KEGIATAN INSPEKSI & PENGUJIAN');
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

// Row 2: Empty (spacing after title)

// Row 3: Kantor Cabang
$sheet->setCellValue('A3', 'Kantor Cabang');
$sheet->setCellValue('B3', ':');
$sheet->setCellValue('C3', 'Bandara Radin Inten II');

// Row 4: Unit
$sheet->setCellValue('A4', 'Unit');
$sheet->setCellValue('B4', ':');
$sheet->setCellValue('C4', 'Airport Equipment');

// Row 5: Periode
$sheet->setCellValue('A5', 'Periode');
$sheet->setCellValue('B5', ':');
$sheet->setCellValue('C5', $fromDate . ' s/d ' . $toDate);

// Row 6: Empty (spacing before table)

// === TABLE HEADER (Row 7) ===
$headerRow = 7;
$headers = ['NO', 'NAMA KEGIATAN', 'LOKASI', 'TANGGAL', 'HASIL INSPEKSI / PENGUJIAN', 'CATATAN', 'DOKUMENTASI'];
$columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

foreach ($headers as $idx => $header) {
    $sheet->setCellValue($columns[$idx] . $headerRow, $header);
}

// Header style (teal background, white text, bold)
$sheet->getStyle('A7:G7')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '087F8A']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
]);
$sheet->getRowDimension($headerRow)->setRowHeight(25);

// === DATA ROWS (Start from Row 8) ===
$startRow = 8;
$currentRow = $startRow;

foreach ($data as $i => $row) {
    $no = $i + 1;
    
    // Set row height to accommodate image (60 pixels)
    $sheet->getRowDimension($currentRow)->setRowHeight(50);
    
    // Fill data
    $sheet->setCellValue('A' . $currentRow, $no);
    $sheet->setCellValue('B' . $currentRow, $row['kegiatan'] ?? '');
    $sheet->setCellValue('C' . $currentRow, $row['lokasi'] ?? '');
    $sheet->setCellValue('D' . $currentRow, date('d-M-y', strtotime($row['tanggal'])));
    $sheet->setCellValue('E' . $currentRow, $row['hasil'] ?? '');
    $sheet->setCellValue('F' . $currentRow, $row['catatan'] ?? '');
    
    // Add image if exists
    if (!empty($row['foto'])) {
        $imagePath = __DIR__ . '/../' . $row['foto'];
        if (file_exists($imagePath)) {
            $drawing = new Drawing();
            $drawing->setName('Foto ' . $no);
            $drawing->setDescription('Dokumentasi');
            $drawing->setPath($imagePath);
            $drawing->setCoordinates('G' . $currentRow);
            $drawing->setHeight(45); // Height in pixels - fits in 50px row
            $drawing->setOffsetX(5);
            $drawing->setOffsetY(3);
            $drawing->setWorksheet($sheet);
        }
    }
    
    // Apply borders
    $sheet->getStyle('A' . $currentRow . ':G' . $currentRow)->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
    ]);
    
    // Center align specific columns
    $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('G' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Wrap text
    $sheet->getStyle('B' . $currentRow)->getAlignment()->setWrapText(true);
    $sheet->getStyle('E' . $currentRow)->getAlignment()->setWrapText(true);
    $sheet->getStyle('F' . $currentRow)->getAlignment()->setWrapText(true);
    
    $currentRow++;
}

// === COLUMN WIDTHS ===
$sheet->getColumnDimension('A')->setWidth(15);   // NO + Kantor Cabang label
$sheet->getColumnDimension('B')->setWidth(22);   // NAMA KEGIATAN
$sheet->getColumnDimension('C')->setWidth(20);   // LOKASI
$sheet->getColumnDimension('D')->setWidth(12);   // TANGGAL
$sheet->getColumnDimension('E')->setWidth(30);   // HASIL
$sheet->getColumnDimension('F')->setWidth(18);   // CATATAN
$sheet->getColumnDimension('G')->setWidth(18);   // DOKUMENTASI (wider for text)

// Generate filename
$filename = "Laporan_Kegiatan_" . $date_from . "_to_" . $date_to . ".xlsx";

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

// Save to output
$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
exit;
