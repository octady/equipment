<?php
/**
 * Export Laporan Pengukuran - PhpSpreadsheet
 * CRITICAL FIX: 
 * - Circuits 1-6: ONLY fill L, M, N, O (don't touch template structure)
 * - Circuits 7+: Full data with proper column placement
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
    $sheet1->setCellValue('G7', $formattedDate);
    
    $circuitStartRow = 19;
    $rowsPerCircuit = 3; // 1 header + 2 items
    $templateCircuitCount = 6;
    $actualCircuitCount = count($tahananIsolasiData);
    
    // Insert rows for EXTRA circuits (7+) if needed
    if ($actualCircuitCount > $templateCircuitCount) {
        $extraCircuits = $actualCircuitCount - $templateCircuitCount;
        $rowsToInsert = $extraCircuits * $rowsPerCircuit;
        
        $insertPosition = $circuitStartRow + ($templateCircuitCount * $rowsPerCircuit);
        $sheet1->insertNewRowBefore($insertPosition, $rowsToInsert);
        
        // Copy styles from last circuit
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
        
        // Copy merged cells for new circuits
        $mergedCells = $sheet1->getMergeCells();
        foreach ($mergedCells as $mergeRange) {
            preg_match('/([A-Z]+)(\d+):([A-Z]+)(\d+)/', $mergeRange, $matches);
            if (count($matches) === 5) {
                $startCol = $matches[1];
                $startRowNum = (int)$matches[2];
                $endCol = $matches[3];
                $endRowNum = (int)$matches[4];
                
                $sourceRowMin = $sourceCircuitStart;
                $sourceRowMax = $sourceCircuitStart + $rowsPerCircuit - 1;
                
                if ($startRowNum >= $sourceRowMin && $endRowNum <= $sourceRowMax) {
                    $relativeStartRow = $startRowNum - $sourceCircuitStart;
                    $relativeEndRow = $endRowNum - $sourceCircuitStart;
                    
                    for ($c = 0; $c < $extraCircuits; $c++) {
                        $newStartRow = $insertPosition + ($c * $rowsPerCircuit) + $relativeStartRow;
                        $newEndRow = $insertPosition + ($c * $rowsPerCircuit) + $relativeEndRow;
                        
                        try {
                            $sheet1->mergeCells($startCol . $newStartRow . ':' . $endCol . $newEndRow);
                        } catch (Exception $e) {}
                    }
                }
            }
        }
    }
    
    // Fill circuit data
    $currentRow = $circuitStartRow;
    $circuitNumber = 0;
    
    foreach ($tahananIsolasiData as $circuit) {
        $circuitNumber++;
        $isNewCircuit = ($circuitNumber > $templateCircuitCount);
        
        // === Circuit Header Row ===
        if ($isNewCircuit) {
            // New circuits: write full data
            $sheet1->setCellValue('A' . $currentRow, '');
            $sheet1->setCellValue('B' . $currentRow, $circuitNumber);
            $sheet1->setCellValue('C' . $currentRow, $circuit['name'] ?? 'New Circuit ' . $circuitNumber);
        } else {
            // Template circuits (1-6): write circuit name if edited
            if (!empty($circuit['name'])) {
                $sheet1->setCellValue('C' . $currentRow, $circuit['name']);
            }
        }
        $currentRow++;
        
        // === Item Rows ===
        if (isset($circuit['items']) && is_array($circuit['items'])) {
            foreach ($circuit['items'] as $item) {
                // Write item name and satuan for ALL circuits (both template and new)
                // This allows users to edit item names and have them appear in Excel
                if (!empty($item['itemName'])) {
                    $sheet1->setCellValue('C' . $currentRow, $item['itemName']);
                }
                if (!empty($item['satuan'])) {
                    $sheet1->setCellValue('K' . $currentRow, $item['satuan']);
                }
                
                // For new circuits only: clear columns A and B
                if ($isNewCircuit) {
                    $sheet1->setCellValue('A' . $currentRow, '');
                    $sheet1->setCellValue('B' . $currentRow, '');
                }
                
                // ALL circuits: fill measurement data (L, M, N, O)
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
        
        // Ensure proper row advancement for circuits with < 2 items
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
        
        // Count extra rows that need to be inserted
        $extraRowCount = 0;
        foreach ($simulasiGensetData as $item) {
            if (isset($item['isExtra']) && $item['isExtra']) {
                $extraRowCount++;
            }
        }
        
        // Insert extra rows after DST... (row 41 in template) if needed
        if ($extraRowCount > 0) {
            $insertAfterRow = 41; // After DST... row (last PEMBEBANAN item)
            $sheet2->insertNewRowBefore($insertAfterRow + 1, $extraRowCount);
            
            // Set formatting for new rows - no bold
            for ($i = 0; $i < $extraRowCount; $i++) {
                $targetRow = $insertAfterRow + 1 + $i;
                $sheet2->getStyle('C' . $targetRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                $sheet2->getStyle('K' . $targetRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet2->getStyle('C' . $targetRow)->getFont()->setBold(false);
                $sheet2->getStyle('K' . $targetRow)->getFont()->setBold(false);
            }
        }
        
        // Row mapping based on ACTUAL template structure (from template_laporan.xlsx Sheet 2)
        // Form item index => Excel row number
        $gensetDataRows = [
            17,              // 0: ATS OPERATION (CHANGE OVER PLN KE GENSET)
            18,              // 1: WAKTU TRANSFER PLN KE GENSET
            20, 21, 22, 23, 24,  // 2-6: PARAMETER PANEL SINKRON (TEGANGAN, ARUS, FREKUENSI, POWER, POWER FACTOR)
            27,              // 7: ATS OPERATION (CHANGE OVER GENSET KE PLN)
            28,              // 8: WAKTU TRANSFER GENSET KE PLN
            30, 31, 32, 33, 34,  // 9-13: PARAMETER PANEL PLN (TEGANGAN, ARUS, FREKUENSI, POWER, POWER FACTOR)
            37,              // 14: JML GENSET YANG BEROPERASI
            39, 40, 41,      // 15-17: PARAMETER PEMBEBANAN (GENSET 500 KVA, 1000 KVA, DST...)
            46, 47, 48, 49   // 18-21: PERALATAN PENDUKUNG (PANEL KONTROL, DAILY TANK, MONTHLY TANK, BATTERY CHARGER)
        ];
        $extraRowIdx = 0;
        
        foreach ($simulasiGensetData as $idx => $item) {
            if (isset($item['isExtra']) && $item['isExtra']) {
                // Extra rows go after DST... (row 41), so 42, 43, etc.
                $excelRow = 42 + $extraRowIdx;
                $extraRowIdx++;
                
                // Write dash to column C, item name to column D (same format as template)
                $sheet2->setCellValue('C' . $excelRow, '-');
                if (!empty($item['itemName'])) {
                    $sheet2->setCellValue('D' . $excelRow, $item['itemName']);
                }
                if (!empty($item['satuan'])) {
                    $sheet2->setCellValue('K' . $excelRow, $item['satuan']);
                }
            } else {
                // Normal template row
                if (!isset($gensetDataRows[$idx])) continue;
                $excelRow = $gensetDataRows[$idx];
                
                // Rows with dash prefix (sub-items) use column D for the name
                // Rows 20-24 (PANEL SINKRON), 30-34 (PANEL PLN), 39-41 (PEMBEBANAN)
                $dashRows = [20, 21, 22, 23, 24, 30, 31, 32, 33, 34, 39, 40, 41];
                
                // Write edited item name to correct column
                if (!empty($item['itemName'])) {
                    if (in_array($excelRow, $dashRows)) {
                        // Sub-item with dash - write to column D
                        $sheet2->setCellValue('D' . $excelRow, $item['itemName']);
                    } else {
                        // Main item without dash - write to column C
                        $sheet2->setCellValue('C' . $excelRow, $item['itemName']);
                    }
                }
            }
            
            // Write measurement data for all rows
            if (!empty($item['hasil'])) {
                $sheet2->setCellValue('L' . $excelRow, $item['hasil']);
            }
            if (($item['status'] ?? '') === 'NORMAL') {
                $sheet2->setCellValue('M' . $excelRow, 'NORMAL');
            } elseif (($item['status'] ?? '') === 'TIDAK NORMAL') {
                $sheet2->setCellValue('N' . $excelRow, 'TIDAK NORMAL');
            }
            if (!empty($item['keterangan'])) {
                $sheet2->setCellValue('O' . $excelRow, $item['keterangan']);
            }
        }
        
        // Update signature positions if there were extra rows
        $signatureOffset = $extraRowCount;
        $sheet2->setCellValue('N' . (57 + $signatureOffset), strtoupper($jabatan));
        $sheet2->setCellValue('N' . (61 + $signatureOffset), $dibuatOleh);
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
            
            // Write item name if edited
            if (!empty($item['itemName'])) {
                $sheet3->setCellValue('C' . $excelRow, $item['itemName']);
            }
            // Write satuan if provided
            if (!empty($item['satuan'])) {
                $sheet3->setCellValue('K' . $excelRow, $item['satuan']);
            }
            // Write measurement data
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
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
