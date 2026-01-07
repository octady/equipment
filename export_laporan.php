<?php
/**
 * Export Laporan Pengukuran - PhpSpreadsheet
 * Reads template_laporan.xlsx and fills user input values while preserving all formatting
 */

session_start();
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Check login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No data received']);
    exit;
}

$tanggal = $data['tanggal'] ?? date('Y-m-d');
$dibuatOleh = $data['dibuatOleh'] ?? '';
$jabatan = $data['jabatan'] ?? '';
$tahananIsolasiData = $data['tahananIsolasi'] ?? [];
$simulasiGensetData = $data['simulasiGenset'] ?? [];
$simulasiUPSData = $data['simulasiUPS'] ?? [];

// Format date
$dateObj = new DateTime($tanggal);
$formattedDate = $dateObj->format('d F Y');

// Month names in Indonesian
$months = [
    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
    'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
    'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
    'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
];
foreach ($months as $en => $id) {
    $formattedDate = str_replace($en, $id, $formattedDate);
}

try {
    // Load template
    $templatePath = 'assets/dokumen/template_laporan.xlsx';
    if (!file_exists($templatePath)) {
        throw new Exception('Template file not found');
    }
    
    $spreadsheet = IOFactory::load($templatePath);
    
    // ===== Sheet 1: FORM TAHANAN ISOLASI =====
    $sheet1 = $spreadsheet->getSheet(0);
    
    // Fill date (cell G7 - replaces old date value)
    $sheet1->setCellValue('G7', $formattedDate);
    
    // Fill data - ONLY write HASIL, NORMAL/TIDAK NORMAL, KETERANGAN
    // Template already has NO, ITEM, SATUAN - don't overwrite them!
    // Row 20 = first item (Panjang kabel), Row 21 = Tahanan isolasi
    // Row 23 = next item, Row 24 = next item (skip circuit headers)
    
    // Flatten the circuit data to get just items
    $allItems = [];
    foreach ($tahananIsolasiData as $circuit) {
        if (isset($circuit['items'])) {
            foreach ($circuit['items'] as $item) {
                $allItems[] = $item;
            }
        }
    }
    
    $excelRow = 20; // First data row
    $itemInCircuit = 0;
    
    foreach ($allItems as $item) {
        // Only write HASIL, status, KETERANGAN - DO NOT write item name or satuan!
        if (!empty($item['hasil'])) {
            $sheet1->setCellValue('L' . $excelRow, $item['hasil']);
        }
        if ($item['status'] === 'NORMAL') {
            $sheet1->setCellValue('M' . $excelRow, 'NORMAL');
        } elseif ($item['status'] === 'TIDAK NORMAL') {
            $sheet1->setCellValue('N' . $excelRow, 'TIDAK NORMAL');
        }
        if (!empty($item['keterangan'])) {
            $sheet1->setCellValue('O' . $excelRow, $item['keterangan']);
        }
        
        $itemInCircuit++;
        $excelRow++;
        
        // After 2 items, skip circuit header row
        if ($itemInCircuit >= 2) {
            $itemInCircuit = 0;
            $excelRow++; // Skip circuit header
        }
    }
    
    // Fill "Dibuat" - Replace YOGA PRANATA and AIRPORT EQUIPMENT SUPERVISOR
    // Cell reference shows M59 for YOGA PRANATA (confirmed from user screenshot)
    $sheet1->setCellValue('M55', strtoupper($jabatan));
    $sheet1->setCellValue('M59', $dibuatOleh);
    
    // ===== Sheet 2: FORM SIMULASI GENSET =====
    if ($spreadsheet->getSheetCount() >= 2) {
        $sheet2 = $spreadsheet->getSheet(1);
        $sheet2->setCellValue('G7', $formattedDate);
        
        $excelRow = 15; // Adjust starting row
        foreach ($simulasiGensetData as $item) {
            if (!empty($item['hasil'])) {
                $sheet2->setCellValue('L' . $excelRow, $item['hasil']);
            }
            if ($item['status'] === 'NORMAL') {
                $sheet2->setCellValue('M' . $excelRow, 'NORMAL');
            } elseif ($item['status'] === 'TIDAK NORMAL') {
                $sheet2->setCellValue('N' . $excelRow, 'TIDAK NORMAL');
            }
            if (!empty($item['keterangan'])) {
                $sheet2->setCellValue('O' . $excelRow, $item['keterangan']);
            }
            $excelRow++;
        }
        
        // Fill "Dibuat" for sheet 2 - Replace YOGA PRANATA (N61) and AIRPORT EQUIPMENT SUPERVISOR (N57)
        $sheet2->setCellValue('N57', strtoupper($jabatan));
        $sheet2->setCellValue('N61', $dibuatOleh);
    }
    
    // ===== Sheet 3: FORM SIMULASI UPS - SUDAH BENAR, JANGAN DIUBAH =====
    if ($spreadsheet->getSheetCount() >= 3) {
        $sheet3 = $spreadsheet->getSheet(2);
        $sheet3->setCellValue('G7', $formattedDate);
        
        // Define exact row numbers for data items (skipping category rows)
        // Based on template: PARAMETER INCOMING(16-18), OUTGOING(21-26), BATTERY(29-32), RUANGAN(35-36)
        $upsDataRows = [16, 17, 18, 21, 22, 23, 24, 25, 26, 29, 30, 31, 32, 35, 36];
        
        foreach ($simulasiUPSData as $idx => $item) {
            if (!isset($upsDataRows[$idx])) break; // safety check
            $excelRow = $upsDataRows[$idx];
            
            // Clear existing NORMAL value from template first (so empty if user didn't select)
            $sheet3->setCellValue('M' . $excelRow, '');
            
            if (!empty($item['hasil'])) {
                $sheet3->setCellValue('L' . $excelRow, $item['hasil']);
            }
            if ($item['status'] === 'NORMAL') {
                $sheet3->setCellValue('M' . $excelRow, 'NORMAL');
            } elseif ($item['status'] === 'TIDAK NORMAL') {
                $sheet3->setCellValue('N' . $excelRow, 'TIDAK NORMAL');
            }
            if (!empty($item['keterangan'])) {
                $sheet3->setCellValue('O' . $excelRow, $item['keterangan']);
            }
        }
        
        // Fill "Dibuat" for sheet 3 - SUDAH BENAR: N46 = jabatan, N50 = nama
        $sheet3->setCellValue('N46', strtoupper($jabatan));
        $sheet3->setCellValue('N50', $dibuatOleh);
    }
    
    // Save to temp file
    $tempFile = tempnam(sys_get_temp_dir(), 'laporan_') . '.xlsx';
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($tempFile);
    
    // Output file for download
    $filename = 'Laporan_Pengukuran_' . $tanggal . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tempFile));
    header('Cache-Control: max-age=0');
    
    readfile($tempFile);
    unlink($tempFile); // Clean up temp file
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
