<?php
/**
 * Download Laporan Pengukuran from Database
 * Loads saved report data and generates Excel file
 */

session_start();
require 'vendor/autoload.php';
include "config/database.php";

use PhpOffice\PhpSpreadsheet\IOFactory;

// Check login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$report_id = $_GET['id'] ?? null;

if (!$report_id) {
    header("Location: admin_laporan_pengukuran.php");
    exit;
}

// Fetch report from database
$stmt = $conn->prepare("SELECT * FROM laporan_pengukuran WHERE id = ?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();

if (!$report) {
    header("Location: admin_laporan_pengukuran.php");
    exit;
}

$tanggal = $report['tanggal'];
$dibuatOleh = $report['dibuat_oleh'];
$jabatan = $report['jabatan'];
$tahananIsolasiData = json_decode($report['tahanan_isolasi_data'], true) ?? [];
$simulasiGensetData = json_decode($report['simulasi_genset_data'], true) ?? [];
$simulasiUPSData = json_decode($report['simulasi_ups_data'], true) ?? [];

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
    $sheet1->setCellValue('G7', $formattedDate);
    
    $circuitStartRow = 19;
    $rowsPerCircuit = 3;
    $templateCircuitCount = 6;
    $actualCircuitCount = count($tahananIsolasiData);
    
    // Insert rows for EXTRA circuits (7+) if needed
    if ($actualCircuitCount > $templateCircuitCount) {
        $extraCircuits = $actualCircuitCount - $templateCircuitCount;
        $rowsToInsert = $extraCircuits * $rowsPerCircuit;
        
        $insertPosition = $circuitStartRow + ($templateCircuitCount * $rowsPerCircuit);
        $sheet1->insertNewRowBefore($insertPosition, $rowsToInsert);
        
        $sourceCircuitStart = $circuitStartRow + (($templateCircuitCount - 1) * $rowsPerCircuit);
        
        for ($i = 0; $i < $rowsToInsert; $i++) {
            $rowType = $i % $rowsPerCircuit;
            $sourceRow = $sourceCircuitStart + $rowType;
            $targetRow = $insertPosition + $i;
            
            $height = $sheet1->getRowDimension($sourceRow)->getRowHeight();
            if ($height > 0) {
                $sheet1->getRowDimension($targetRow)->setRowHeight($height);
            }
            
            foreach (range('A', 'O') as $col) {
                $sheet1->duplicateStyle($sheet1->getStyle($col . $sourceRow), $col . $targetRow);
            }
        }
    }
    
    // Fill circuit data
    $currentRow = $circuitStartRow;
    $circuitNumber = 0;
    
    foreach ($tahananIsolasiData as $circuit) {
        $circuitNumber++;
        $isNewCircuit = ($circuitNumber > $templateCircuitCount);
        
        if ($isNewCircuit) {
            $sheet1->setCellValue('A' . $currentRow, '');
            $sheet1->setCellValue('B' . $currentRow, $circuitNumber);
            $sheet1->setCellValue('C' . $currentRow, $circuit['name'] ?? 'New Circuit ' . $circuitNumber);
        }
        $currentRow++;
        
        if (isset($circuit['items']) && is_array($circuit['items'])) {
            foreach ($circuit['items'] as $item) {
                if ($isNewCircuit) {
                    $sheet1->setCellValue('A' . $currentRow, '');
                    $sheet1->setCellValue('B' . $currentRow, '');
                    $sheet1->setCellValue('C' . $currentRow, $item['itemName'] ?? '');
                    $sheet1->setCellValue('K' . $currentRow, $item['satuan'] ?? '');
                }
                
                if (!empty($item['hasil'])) {
                    $sheet1->setCellValue('L' . $currentRow, $item['hasil']);
                }
                
                if (($item['status'] ?? '') === 'NORMAL') {
                    $sheet1->setCellValue('M' . $currentRow, 'NORMAL');
                    $sheet1->setCellValue('N' . $currentRow, '');
                } elseif (($item['status'] ?? '') === 'TIDAK NORMAL') {
                    $sheet1->setCellValue('M' . $currentRow, '');
                    $sheet1->setCellValue('N' . $currentRow, 'TIDAK NORMAL');
                }
                
                if (!empty($item['keterangan'])) {
                    $sheet1->setCellValue('O' . $currentRow, $item['keterangan']);
                }
                
                $currentRow++;
            }
        }
        
        $itemCount = isset($circuit['items']) ? count($circuit['items']) : 0;
        if ($itemCount < 2) {
            $currentRow += (2 - $itemCount);
        }
    }
    
    // Signature
    $signatureOffset = max(0, ($actualCircuitCount - $templateCircuitCount) * $rowsPerCircuit);
    $sheet1->setCellValue('M' . (55 + $signatureOffset), strtoupper($jabatan));
    $sheet1->setCellValue('M' . (59 + $signatureOffset), $dibuatOleh);
    
    // ===== Sheet 2: FORM SIMULASI GENSET =====
    if ($spreadsheet->getSheetCount() >= 2) {
        $sheet2 = $spreadsheet->getSheet(1);
        $sheet2->setCellValue('G7', $formattedDate);
        
        $gensetRow = 15;
        foreach ($simulasiGensetData as $item) {
            if (!empty($item['hasil'])) {
                $sheet2->setCellValue('L' . $gensetRow, $item['hasil']);
            }
            if (($item['status'] ?? '') === 'NORMAL') {
                $sheet2->setCellValue('M' . $gensetRow, 'NORMAL');
            } elseif (($item['status'] ?? '') === 'TIDAK NORMAL') {
                $sheet2->setCellValue('N' . $gensetRow, 'TIDAK NORMAL');
            }
            if (!empty($item['keterangan'])) {
                $sheet2->setCellValue('O' . $gensetRow, $item['keterangan']);
            }
            $gensetRow++;
        }
        
        $sheet2->setCellValue('N57', strtoupper($jabatan));
        $sheet2->setCellValue('N61', $dibuatOleh);
    }
    
    // ===== Sheet 3: FORM SIMULASI UPS =====
    if ($spreadsheet->getSheetCount() >= 3) {
        $sheet3 = $spreadsheet->getSheet(2);
        $sheet3->setCellValue('G7', $formattedDate);
        
        $upsDataRows = [16, 17, 18, 21, 22, 23, 24, 25, 26, 29, 30, 31, 32, 35, 36];
        
        foreach ($simulasiUPSData as $idx => $item) {
            if (!isset($upsDataRows[$idx])) break;
            $excelRow = $upsDataRows[$idx];
            
            $sheet3->setCellValue('M' . $excelRow, '');
            
            if (!empty($item['hasil'])) {
                $sheet3->setCellValue('L' . $excelRow, $item['hasil']);
            }
            if (($item['status'] ?? '') === 'NORMAL') {
                $sheet3->setCellValue('M' . $excelRow, 'NORMAL');
            } elseif (($item['status'] ?? '') === 'TIDAK NORMAL') {
                $sheet3->setCellValue('N' . $excelRow, 'TIDAK NORMAL');
            }
            if (!empty($item['keterangan'])) {
                $sheet3->setCellValue('O' . $excelRow, $item['keterangan']);
            }
        }
        
        $sheet3->setCellValue('N46', strtoupper($jabatan));
        $sheet3->setCellValue('N50', $dibuatOleh);
    }
    
    // Save and output
    $tempFile = tempnam(sys_get_temp_dir(), 'laporan_') . '.xlsx';
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($tempFile);
    
    $filename = 'Laporan_Pengukuran_' . $tanggal . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tempFile));
    header('Cache-Control: max-age=0');
    
    readfile($tempFile);
    unlink($tempFile);
    
} catch (Exception $e) {
    header("Location: admin_laporan_pengukuran.php?error=" . urlencode($e->getMessage()));
}
