<?php
/**
 * Excel Export untuk Equipment Monitoring
 * Format sesuai template: LAPORAN UNJUK HASIL / PERFORMANCE (BULANAN/HARIAN)
 */

// Start output buffering immediately
ob_start();

include "../config/database.php";

// Determine export type
$type = isset($_GET['type']) ? $_GET['type'] : 'daily';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Clean buffer
ob_clean();

// Set headers
header("Content-Type: application/vnd.ms-excel");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: 0");

if ($type == 'daily') {
  $filename = "Laporan_Harian_" . date('Y-m-d', strtotime($date)) . ".xls";
  $title = "LAPORAN UNJUK HASIL / PERFORMANCE (HARIAN)";
  $subtitle = "Tanggal: " . date('d F Y', strtotime($date));
} else {
  $filename = "Laporan_Unjuk_Performance_" . date('Y-m', strtotime($month)) . ".xls";
  $title = "LAPORAN UNJUK HASIL / PERFORMANCE (BULANAN)";
  $subtitle = "Bulan: " . date('F Y', strtotime($month . '-01'));
}

header("Content-Disposition: attachment; filename=\"$filename\"");

// Output BOM (Byte Order Mark) to ensure Excel recognizes UTF-8
echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    table {
      border-collapse: collapse;
      width: 100%;
    }

    th,
    td {
      border: 1px solid #000;
      padding: 5px;
      font-size: 11px;
      text-align: center;
      vertical-align: middle;
    }

    .header-title {
      font-size: 16px;
      font-weight: bold;
      text-align: center;
      background-color: #CCCCCC;
      padding: 10px;
    }

    .section-header {
      background-color: #D9D9D9;
      font-weight: bold;
      text-align: left;
      padding: 8px;
    }

    .normal-bg {
      background-color: #00FF00;
    }

    /* Hijau - O */
    .menurun-bg {
      background-color: #FFFF00;
    }

    /* Kuning - X */
    .tergesa-bg {
      background-color: #FF0000;
      color: white;
    }

    /* Merah - V */
    .gangguan-bg {
      background-color: #0000FF;
      color: white;
    }

    /* Biru - - */
    .rata-rata {
      background-color: #E7E6E6;
      font-weight: bold;
    }

    .text-left {
      text-align: left;
    }
  </style>
</head>

<body>

  <?php
  if ($type == 'monthly') {
    // ============= LAPORAN BULANAN (REVISED) =============
    $year = date('Y', strtotime($month . '-01'));
    $month_num = date('m', strtotime($month . '-01'));
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month_num, $year);
    $month_name = date('F Y', strtotime($month . '-01'));
    $last_date_of_month = date('t F Y', strtotime($month . '-01')); // e.g. 30 November 2025

    // Metadata Header
    echo "<table>";
    echo "<tr><td colspan='5' class='text-left' style='border:none;'>Kantor Cabang : Bandara Radin Inten II</td></tr>";
    echo "<tr><td colspan='5' class='text-left' style='border:none;'>Unit : Airport Equipment</td></tr>";
    echo "<tr><td colspan='5' class='text-left' style='border:none;'>Bulan / Tahun : $month_name</td></tr>";
    echo "</table>";

    echo "<h2 style='text-align: center;'>$title</h2>";
    echo "<br>";

    // Get all sections ordered by Category then Order
    // Note: Column is 'parent_category' not 'category'. ASC order puts Electrical before Mechanical.
    $sections = $conn->query("SELECT * FROM sections ORDER BY parent_category ASC, urutan ASC")->fetch_all(MYSQLI_ASSOC);

    echo "<table>";

    // Header Row 1
    echo "<tr>";
    echo "<th rowspan='2'>NO</th>";
    echo "<th rowspan='2' style='width: 250px;'>NAMA PERALATAN</th>";
    echo "<th rowspan='2' style='width: 150px;'>LOKASI</th>"; // Restored Location
    
    echo "<th colspan='$days_in_month'>TANGGAL</th>";
    
    echo "<th rowspan='2' style='width: 100px;'>OPERASI<br>TERPUTUS</th>";
    echo "<th rowspan='2' style='width: 120px;'>SERVICEABILITY (%)<br>(Target 90%)</th>";
    echo "<th rowspan='2'>KETERANGAN</th>";
    
    // Extra Calculation Columns from Image
    echo "<th rowspan='2'>JAM<br>OPERASIONAL</th>";
    echo "<th rowspan='2'>JUMLAH<br>UNIT</th>";
    echo "<th rowspan='2'>HARI DLM<br>BULAN</th>";
    echo "<th rowspan='2'>JUMLAH JAM<br>OPERASIONAL/Bulan</th>";
    
    echo "</tr>";

    // Header Row 2 - Days
    echo "<tr>";
    for ($d = 1; $d <= $days_in_month; $d++) {
      echo "<th>$d</th>";
    }
    echo "</tr>";

    $no = 1;
    $current_cat = '';

    foreach ($sections as $section) {
        $cat = !empty($section['parent_category']) ? $section['parent_category'] : 'MECHANICAL';
        $section_id = $section['id'];
        
        // Category Header (Mechanical / Electrical)
        if ($cat != $current_cat) {
            $current_cat = $cat;
            $cat_print = ($cat == 'MECHANICAL') ? 'MECHANICAL FACILITY' : 'ELECTRICAL FACILITY';
            $colspan_total = 3 + $days_in_month + 7; // No+Name+Loc + Days + Ops+Serv+Ket + 4Extra
            echo "<tr>";
            echo "<td colspan='$colspan_total' style='font-weight: bold; background-color: #CCCCCC; text-align: left;'>$cat_print</td>";
            echo "</tr>";
        }
        
        // Get equipments
        $equipments = $conn->query("
            SELECT e.*, l.nama_lokasi 
            FROM equipments e
            JOIN lokasi l ON e.lokasi_id = l.id
            WHERE e.section_id = $section_id 
            ORDER BY e.nama_peralatan
        ")->fetch_all(MYSQLI_ASSOC);
        
        if (empty($equipments)) continue;

        // Section Header Row
        $colspan_total = 3 + $days_in_month + 7; // Updated Colspan
        echo "<tr>";
        echo "<td colspan='$colspan_total' class='section-header' style='background-color: #0d5d63; color: white;'>".htmlspecialchars($section['nama_section'])."</td>";
        echo "</tr>";

        $section_total_downtime = 0;
        $section_total_perf = 0;
        $section_total_jam_ops_bulanan = 0;
        $section_eq_count = 0;

        foreach ($equipments as $eq) {
             // Calculate Constants
             $jam_ops_per_hari = isset($eq['jam_operasi_harian']) && $eq['jam_operasi_harian'] > 0 ? $eq['jam_operasi_harian'] : 24;
             $jml_unit = 1; // Default 1 unit per row
             $total_jam_sebulan = $days_in_month * $jam_ops_per_hari * $jml_unit;

             echo "<tr>";
             echo "<td>$no</td>";
             echo "<td class='text-left'>" . htmlspecialchars($eq['nama_peralatan']) . "</td>";
             echo "<td class='text-left'>" . htmlspecialchars($eq['nama_lokasi']) . "</td>"; // Output Location

             $status_counts = ['O' => 0, 'X' => 0, 'V' => 0, '-' => 0];
             $total_downtime = 0;

             for ($d = 1; $d <= $days_in_month; $d++) {
                $check_date = sprintf("%04d-%02d-%02d", $year, $month_num, $d);
                $check = $conn->query("SELECT status, jam_operasi FROM inspections_daily WHERE equipment_id = {$eq['id']} AND tanggal = '$check_date'")->fetch_assoc();
                
                $status = $check ? $check['status'] : ''; // Empty string if no data
                 
                // Styling
                $bg_class = '';
                if($status == 'O') $bg_class = 'normal-bg';
                if($status == 'X') $bg_class = 'menurun-bg';
                if($status == 'V') $bg_class = 'tergesa-bg';
                if($status == '-') $bg_class = 'gangguan-bg'; // Blue


                // Web View Mapping:
                // O = Normal (Green)
                // - = Menurun (Amber/Yellow)
                // X = Rusak (Red)
                // V = Standby? (Blue?) -> Wait, previous web code:
                // bg-minus (Amber) for '-'.
                
                // Update CSS classes for Excel:
                // Let's rely on the text content primarily.
                
                $cell_content = $status;
                if ($status == '-') {
                    $bg_class = 'menurun-bg'; // Yellow
                } elseif ($status == 'X') {
                    $bg_class = 'tergesa-bg'; // Red (Rusak)
                } elseif ($status == 'V') {
                    $bg_class = 'gangguan-bg'; // Blue
                } elseif ($status == 'O') {
                    $bg_class = 'normal-bg';
                }

                if ($check) {
                    $status_counts[$status]++;
                    
                    // Downtime Calc
                    if ($status != 'O') {
                         $op_hours = isset($check['jam_operasi']) ? $check['jam_operasi'] : 0;
                         $loss = $jam_ops_per_hari - $op_hours;
                         $total_downtime += max(0, $loss);
                    }
                }
                
                echo "<td class='$bg_class'>$cell_content</td>";
             }

             // Serviceability Calc
             $perf = 100;
             if ($total_jam_sebulan > 0) {
                 $perf = (($total_jam_sebulan - $total_downtime) / $total_jam_sebulan) * 100;
             }
             
             // Accumulate
             $section_total_downtime += $total_downtime;
             $section_total_perf += $perf;
             $section_total_jam_ops_bulanan += $total_jam_sebulan;
             $section_eq_count++;

             echo "<td>" . ($total_downtime > 0 ? $total_downtime : '0') . "</td>";
             echo "<td>" . number_format($perf, 0) . "</td>"; // Image shows integer 100
             echo "<td></td>"; // Keterangan

             // Extra Columns
             echo "<td>$jam_ops_per_hari</td>";
             echo "<td>$jml_unit</td>";
             echo "<td>$days_in_month</td>";
             echo "<td>$total_jam_sebulan</td>";

             echo "</tr>";
             $no++;
        }

        // RATA-RATA ROW
        if ($section_eq_count > 0) {
            $avg_downtime = $section_total_downtime / $section_eq_count;
            $avg_perf = $section_total_perf / $section_eq_count;
            $avg_total_jam = $section_total_jam_ops_bulanan / $section_eq_count;

            echo "<tr class='rata-rata'>";
            echo "<td></td>";
            echo "<td class='text-left'>RATA-RATA</td>";
            echo "<td></td>"; // Spacer for Location
            echo "<td colspan='$days_in_month'></td>";
            echo "<td>" . number_format($avg_downtime, 0) . "</td>";
            echo "<td>" . number_format($avg_perf, 0) . "</td>";
            echo "<td colspan='4'></td>";
            echo "<td>" . number_format($avg_total_jam, 0) . "</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    
    // FOOTER (Legend & Signature)
    echo "<br><br>";
    echo "<table>";
    echo "<tr>";
    
    // Left Column: Legend
    echo "<td colspan='6' valign='top' style='border:none;'>";
        echo "<br><br><br>"; // Pushing down as requested
        echo "<table style='width:100%; border:none;'>";
        echo "<tr><td style='border:none;'>Performance :</td><td colspan='2' style='border:none;'></td></tr>";
        echo "<tr><td colspan='3' style='border:none; border-bottom:1px solid black; text-align:center;'>Jumlah Jam Operasi - Jumlah jam Terputus</td><td rowspan='2' style='border:none; vertical-align:middle;'> X 100 %</td></tr>";
        echo "<tr><td colspan='3' style='border:none; text-align:center;'>Jumlah Jam Operasi</td></tr>";
        
        echo "</table>";
    echo "</td>";

    // Spacer
    echo "<td colspan='" . ($days_in_month - 6) . "' style='border:none;'></td>";

    // Right Column: Signature
    echo "<td colspan='6' valign='top' style='border:none; text-align:center;'>";
        echo "<p>LAMPUNG SELATAN, $last_date_of_month</p>";
        echo "<p>PT. ANGKASA PURA INDONESIA</p>";
        echo "<p>BANDARA RADIN INTEN II LAMPUNG</p>";
        echo "<p>PGS. AIRPORT EQUIPMENT & TECHNOLOGY DEPARTEMENT HEAD</p>";
        echo "<br><br><br><br>";
        echo "<p>(................................................)</p>";
    echo "</td>";
    
    echo "</tr>";
    echo "</table>";

  } else {
    // ============= LAPORAN HARIAN (Existing) =============
    // ... (Keep existing daily report logic)
    $sections = $conn->query("SELECT * FROM sections ORDER BY urutan ASC")->fetch_all(MYSQLI_ASSOC);
    // ...


    echo "<h2 style='text-align: center;'>$title</h2>";
    echo "<h4 style='text-align: center;'>$subtitle</h4>";
    echo "<br>";

    echo "<table>";

    // Header
    echo "<tr>";
    echo "<th>No</th>";
    echo "<th>NAMA PERALATAN</th>";
    echo "<th>FASILITAS/BANGUNAN</th>";
    echo "<th>LOKASI</th>";
    echo "<th>STATUS</th>";
    echo "<th>JAM OPERASI</th>";
    echo "<th>STANDARD OPERASI (%)</th>";
    echo "<th>KETERANGAN</th>";
    echo "<th>DIPERIKSA OLEH</th>";
    echo "</tr>";

    $no = 1;
    $section_averages = [];

    foreach ($sections as $section) {
      // Section Header
      echo "<tr>";
      echo "<td colspan='9' class='section-header'>" . htmlspecialchars($section['nama_section']) . "</td>";
      echo "</tr>";

      // Get equipment with inspection data
      $equipments = $conn->query("
            SELECT 
                e.*, 
                l.nama_lokasi, 
                f.nama_fasilitas,
                i.status,
                i.jam_operasi,
                i.keterangan,
                i.checked_by
            FROM equipments e
            JOIN lokasi l ON e.lokasi_id = l.id
            JOIN fasilitas f ON l.fasilitas_id = f.id
            LEFT JOIN inspections_daily i ON e.id = i.equipment_id AND i.tanggal = '$date'
            WHERE e.section_id = {$section['id']}
            ORDER BY e.nama_peralatan
        ")->fetch_all(MYSQLI_ASSOC);

      $section_performance_sum = 0;
      $section_equipment_count = 0;

      foreach ($equipments as $eq) {
        $status = $eq['status'] ?? '-';
        $jam_operasi = $eq['jam_operasi'] ?? 0;

        // Performance calculation
        $performance = ($jam_operasi / $eq['jam_operasi_harian']) * 100;
        if ($status == 'O')
          $performance = 100;

        $section_performance_sum += $performance;
        $section_equipment_count++;

        // Color based on status
        $bg_class = '';
        if ($status == 'O')
          $bg_class = 'normal-bg';
        elseif ($status == 'X')
          $bg_class = 'menurun-bg';
        elseif ($status == 'V')
          $bg_class = 'tergesa-bg';
        elseif ($status == '-')
          $bg_class = 'gangguan-bg';

        echo "<tr>";
        echo "<td>$no</td>";
        echo "<td class='text-left'>" . htmlspecialchars($eq['nama_peralatan']) . "</td>";
        echo "<td>" . htmlspecialchars($eq['nama_fasilitas']) . "</td>";
        echo "<td>" . htmlspecialchars($eq['nama_lokasi']) . "</td>";
        echo "<td class='$bg_class'><strong>$status</strong></td>";
        echo "<td>$jam_operasi jam</td>";
        echo "<td>" . number_format($performance, 1) . "%</td>";
        echo "<td class='text-left'>" . htmlspecialchars($eq['keterangan'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($eq['checked_by'] ?? '-') . "</td>";
        echo "</tr>";

        $no++;
      }

      // RATA-RATA per section
      if ($section_equipment_count > 0) {
        $section_avg = $section_performance_sum / $section_equipment_count;
        $section_averages[] = $section_avg;

        echo "<tr class='rata-rata'>";
        echo "<td colspan='6' class='text-left'>RATA-RATA</td>";
        echo "<td>" . number_format($section_avg, 1) . "%</td>";
        echo "<td colspan='2'>-</td>";
        echo "</tr>";
      }
    }

    // RATA-RATA TOTAL
    if (count($section_averages) > 0) {
      $total_avg = array_sum($section_averages) / count($section_averages);

      echo "<tr class='rata-rata'>";
      echo "<td colspan='6' class='text-left'><strong>RATA-RATA TOTAL</strong></td>";
      echo "<td><strong>" . number_format($total_avg, 1) . "%</strong></td>";
      echo "<td colspan='2'>-</td>";
      echo "</tr>";
    }

    echo "</table>";
  }
  ?>

  <br><br>

  <!-- Legend -->
  <table style="width: 50%;">
    <tr>
      <td colspan="2" style="background-color: #CCCCCC; font-weight: bold;">KETERANGAN</td>
    </tr>
    <tr>
      <td style="width: 30px; background-color: #00FF00;">O</td>
      <td class="text-left">Operasi Normal</td>
    </tr>
    <tr>
      <td style="background-color: #FFFF00;">X</td>
      <td class="text-left">Operasi Menurun</td>
    </tr>
    <tr>
      <td style="background-color: #FF0000; color: white;">V</td>
      <td class="text-left">Operasi Tergesa</td>
    </tr>
    <tr>
      <td style="background-color: #0000FF; color: white;">-</td>
      <td class="text-left">Gangguan pada peralatan</td>
    </tr>
  </table>

  <br><br>

  <!-- Footer -->
  <div style="text-align: center; font-size: 10px;">
    <p><strong>LAPORAN MONITORING EQUIPMENT</strong></p>
    <p>PT. ANGKASA PURA PRIMA INDONESIA</p>
    <p>BANDARA BANDAR RADEN INTEN II LAMPUNG</p>
    <p>PS2. AIRPORT EQUIPMENT & TECHNOLOGY</p>
    <br>
    <p>Di-generate pada: <?= date('d F Y H:i:s') ?></p>
  </div>

</body>

</html>

<?php
$content = ob_get_clean();
echo mb_convert_encoding($content, 'UTF-16LE', 'UTF-8');
?>