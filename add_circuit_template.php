<?php
/**
 * Script untuk menambah circuit baru ke template Excel
 * Mempertahankan 100% formatting asli (logo, warna, merge, border, dll)
 * 
 * Cara pakai:
 * 1. Akses via browser: add_circuit_template.php?count=2 (untuk tambah 2 circuit)
 * 2. File template baru akan di-download
 */

session_start();
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Check login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die('Unauthorized. Please login first.');
}

// Get number of circuits to add
$circuitsToAdd = intval($_GET['count'] ?? 1);
if ($circuitsToAdd < 1 || $circuitsToAdd > 20) {
    die('Invalid count. Must be between 1 and 20.');
}

try {
    $templatePath = 'assets/dokumen/template_laporan.xlsx';
    if (!file_exists($templatePath)) {
        throw new Exception('Template file not found');
    }
    
    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getSheet(0); // Sheet 1: Form Tahanan Isolasi
    
    // Template structure:
    // Row 19: Circuit 1 header
    // Row 20: Panjang kabel
    // Row 21: Tahanan isolasi
    // ... (6 circuits total, ending at row 36)
    
    $circuitStartRow = 19;
    $rowsPerCircuit = 3; // 1 header + 2 items
    $existingCircuits = 6;
    
    // Position to insert new rows (after last circuit, before signature)
    $insertPosition = $circuitStartRow + ($existingCircuits * $rowsPerCircuit); // Row 37
    $rowsToInsert = $circuitsToAdd * $rowsPerCircuit;
    
    // Insert new rows
    $sheet->insertNewRowBefore($insertPosition, $rowsToInsert);
    
    // Get the last circuit row range to copy from (circuit 6: rows 34-36)
    $sourceCircuitStart = $circuitStartRow + (($existingCircuits - 1) * $rowsPerCircuit); // Row 34
    
    // Copy formatting for each new circuit
    for ($circuit = 0; $circuit < $circuitsToAdd; $circuit++) {
        for ($rowOffset = 0; $rowOffset < $rowsPerCircuit; $rowOffset++) {
            $sourceRow = $sourceCircuitStart + $rowOffset;
            $targetRow = $insertPosition + ($circuit * $rowsPerCircuit) + $rowOffset;
            
            // Copy row height
            $height = $sheet->getRowDimension($sourceRow)->getRowHeight();
            if ($height > 0) {
                $sheet->getRowDimension($targetRow)->setRowHeight($height);
            }
            
            // Copy each cell's style and value structure
            foreach (range('A', 'O') as $col) {
                $sourceCell = $col . $sourceRow;
                $targetCell = $col . $targetRow;
                
                // Copy style (includes font, fill, border, alignment, etc.)
                $sheet->duplicateStyle($sheet->getStyle($sourceCell), $targetCell);
                
                // Copy value if it's a formula or static text (but adjust for new circuit)
                $cellValue = $sheet->getCell($sourceCell)->getValue();
                
                // For header row (first row of circuit), update the circuit number
                if ($rowOffset == 0) {
                    $newCircuitNumber = $existingCircuits + $circuit + 1;
                    
                    if ($col == 'A') {
                        // Clear column A
                        $sheet->setCellValue($targetCell, '');
                    } elseif ($col == 'B') {
                        // Number in column B
                        $sheet->setCellValue($targetCell, $newCircuitNumber);
                    } elseif ($col == 'C') {
                        // Circuit name in column C
                        $sheet->setCellValue($targetCell, 'New Circuit ' . $newCircuitNumber);
                    }
                } elseif ($rowOffset > 0) {
                    // Item rows - copy values from source
                    if ($col == 'A') {
                        $sheet->setCellValue($targetCell, '');
                    } elseif ($col == 'B' || $col == 'C' || $col == 'K') {
                        $sheet->setCellValue($targetCell, $cellValue);
                    }
                }
            }
        }
    }
    
    // Now we need to copy merged cells from source circuit to new circuits
    $mergedCells = $sheet->getMergeCells();
    
    foreach ($mergedCells as $mergeRange) {
        // Parse the merge range
        preg_match('/([A-Z]+)(\d+):([A-Z]+)(\d+)/', $mergeRange, $matches);
        if (count($matches) === 5) {
            $startCol = $matches[1];
            $startRowNum = (int)$matches[2];
            $endCol = $matches[3];
            $endRowNum = (int)$matches[4];
            
            // Check if this merge is within our source circuit rows
            $sourceRowMin = $sourceCircuitStart;
            $sourceRowMax = $sourceCircuitStart + $rowsPerCircuit - 1;
            
            if ($startRowNum >= $sourceRowMin && $endRowNum <= $sourceRowMax) {
                // This merge is within our source circuit - replicate for new circuits
                $relativeStartRow = $startRowNum - $sourceCircuitStart;
                $relativeEndRow = $endRowNum - $sourceCircuitStart;
                
                for ($circuit = 0; $circuit < $circuitsToAdd; $circuit++) {
                    $newStartRow = $insertPosition + ($circuit * $rowsPerCircuit) + $relativeStartRow;
                    $newEndRow = $insertPosition + ($circuit * $rowsPerCircuit) + $relativeEndRow;
                    
                    $newMergeRange = $startCol . $newStartRow . ':' . $endCol . $newEndRow;
                    
                    try {
                        $sheet->mergeCells($newMergeRange);
                    } catch (Exception $e) {
                        // Merge might already exist or overlap, ignore
                    }
                }
            }
        }
    }
    
    // Save to temp file and download
    $tempFile = tempnam(sys_get_temp_dir(), 'template_') . '.xlsx';
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($tempFile);
    
    $filename = 'template_laporan_with_' . ($existingCircuits + $circuitsToAdd) . '_circuits.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tempFile));
    header('Cache-Control: max-age=0');
    
    readfile($tempFile);
    unlink($tempFile);
    
    echo "\n\n<!-- Template generated with " . ($existingCircuits + $circuitsToAdd) . " circuits -->";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
