<?php
include "config/database.php";
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

$selected_month = $_GET['month'] ?? date('n');
$selected_year = $_GET['year'] ?? date('Y');

$filter_date = sprintf("%04d-%02d", $selected_year, $selected_month);

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);

$sections = $conn->query("SELECT * FROM sections ORDER BY urutan ASC")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Bulanan (Admin)</title>
    <script>
    if (localStorage.getItem('sidebarOpen') === 'true') {
        document.documentElement.classList.add('sidebar-open');
    }
    </script>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #087F8A;
            --primary-dark: #065C63;
            --bg-body: #f8fafc;
            --surface: #ffffff;
            --text-main: #1e293b;
            --border: #e2e8f0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            padding-bottom: 50px;
        }

        /* Navbar (Simplified for consistency) */
        .navbar {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .nav-brand { font-weight: 700; font-size: 18px; color: var(--primary); display: flex; align-items: center; gap: 10px; }
        .nav-links a { text-decoration: none; color: #64748b; font-weight: 500; margin-left: 20px; transition: 0.2s; }
        .nav-links a.active, .nav-links a:hover { color: var(--primary); }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Header & Filters */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary);
            margin: 0 0 5px 0;
        }
        .page-title p { margin: 0; color: #64748b; font-size: 14px; }

        .filter-box {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
            display: flex;
            gap: 15px;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }

        select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            color: var(--text-main);
            outline: none;
        }

        .btn-go {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-export {
            background: #10b981;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-export:hover { background: #059669; }

        .btn-daily {
            background: white;
            color: var(--primary);
            border: 1px solid var(--border);
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        /* Scrolly Table */
        .table-container {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            overflow-x: auto; /* Enable horizontal scroll */
            position: relative;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            white-space: nowrap;
        }

        th, td {
            padding: 8px 6px; /* Compact padding */
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        th {
            background: #0f172a;
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        /* Sticky Columns for identification */
        thead tr:first-child th:nth-child(1), tbody td:nth-child(1) { position: sticky; left: 0; background: inherit; z-index: 2; width: 40px; min-width: 40px; }
        thead tr:first-child th:nth-child(2), tbody td:nth-child(2) { 
            position: sticky; 
            left: 40px; 
            background: inherit; 
            z-index: 2; 
            text-align: left; 
            width: 250px; 
            min-width: 250px
            white-space: normal; 
            overflow: hidden;
            text-overflow: ellipsis;
        }
        tbody tr:nth-child(odd) td:nth-child(1), tbody tr:nth-child(odd) td:nth-child(2) { background: #fff; }
        tbody tr:nth-child(even) td:nth-child(1), tbody tr:nth-child(even) td:nth-child(2) { background: #f8fafc; }
        thead tr:first-child th:nth-child(1), thead tr:first-child th:nth-child(2) { background: #0f172a; z-index: 20; }


        .col-sticky { position: sticky; left: 0; background: white; z-index: 5; }
        tr.section-row td {
            background: #0d5d63 !important; 
            font-weight: 700;
            text-align: left;
            padding: 10px 15px;
            color: white !important;
            position: sticky;
            left: 0;
            z-index: 5;
            width: auto !important; 
            min-width: auto !important; 
        }
        
        tr.category-row td {
            position: sticky;
            left: 0;
            z-index: 6; 
            background: #0f172a !important; 
            color: white !important;
            width: auto !important;
            min-width: auto !important; 
            font-weight: 800;   
        }

        .bg-O { background: #dcfce7; color: #166534; font-weight: 700; }
        .bg-X { background: #fee2e2; color: #991b1b; font-weight: 700; }
        .bg-V { background: #fef9c3; color: #854d0e; font-weight: 700; }
        .bg-minus { background: #ffedd5; color: #9a3412; font-weight: 700; } /* Amber for Menurun */
        .bg-blank { background: #fff; }

        .perf-high { color: #166534; font-weight: 800; }
        .perf-mid { color: #854d0e; font-weight: 600; }
        .perf-low { color: #991b1b; font-weight: 700; }

        .legend-bar {
            margin-top: 20px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 13px;
            color: #64748b;
        }
        .legend-item { display: flex; align-items: center; gap: 8px; }
        .dot { width: 12px; height: 12px; border-radius: 4px; }
        @media (max-width: 992px) {
            .container {
                padding: 0 15px;
            }
            
            .header-section {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 20px auto;
                padding: 0 10px;
            }
            
            .page-title h1 {
                font-size: 20px;
            }
            
            .page-title p {
                font-size: 12px;
            }
            
            .filter-box {
                flex-wrap: wrap;
                padding: 12px 16px;
            }
            
            .filter-box select {
                flex: 1 1 auto;
                min-width: 100px;
            }
            
            .btn-export {
                padding: 8px 16px;
                font-size: 13px;
            }
            
            .btn-daily {
                padding: 8px 16px;
                font-size: 13px;
            }
            
            /* Table Container */
            .table-container {
                border-radius: 12px;
            }
            
            table {
                font-size: 11px;
            }
            
            th, td {
                padding: 6px 4px;
            }
            
            .legend-bar {
                gap: 12px;
                font-size: 11px;
            }
            
            .legend-item {
                gap: 6px;
            }
            
            .dot {
                width: 10px;
                height: 10px;
            }
        }
        
        @media (max-width: 576px) {
            body {
                padding-bottom: 30px;
            }
            
            .container {
                margin: 15px auto;
                padding: 0 8px;
            }
            
            .page-title h1 {
                font-size: 18px;
            }
            
            .header-section {
                gap: 12px;
                margin-bottom: 16px;
            }
            
            .filter-box {
                width: 100%;
                gap: 10px;
                padding: 10px 12px;
            }
            
            .filter-box label {
                display: none;
            }
            
            .filter-box select {
                padding: 6px 10px;
                font-size: 13px;
            }
            
            .btn-go {
                padding: 6px 12px;
            }
            
            .btn-export, .btn-daily {
                padding: 8px 12px;
                font-size: 12px;
                gap: 6px;
            }
            
            table {
                font-size: 10px;
            }
            
            th, td {
                padding: 4px 3px;
            }
            
            /* Sticky columns smaller on mobile */
            thead tr:first-child th:nth-child(1), tbody td:nth-child(1) {
                width: 30px;
                min-width: 30px;
            }
            
            thead tr:first-child th:nth-child(2), tbody td:nth-child(2) {
                width: 150px;
                min-width: 150px;
                left: 30px;
            }
            
            .legend-bar {
                margin-top: 15px;
                gap: 8px;
                font-size: 10px;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="container">
        
        <div class="header-section">
            <div class="page-title">
                <h1>LAPORAN BULANAN</h1>
                <p>Performance & Reliability Matrix</p>
            </div>

            <div style="display: flex; gap: 10px; align-items: center;">
                <form method="GET" class="filter-box">
                    <label style="font-size: 13px; font-weight: 600; color: #64748b;">PERIODE:</label>
                    
                    <select name="month" onchange="this.form.submit()">
                        <?php
                        $indo_months = [
                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
                            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                        ];
                        foreach ($indo_months as $m_num => $m_name) {
                            $sel = ($m_num == $selected_month) ? 'selected' : '';
                            echo "<option value='$m_num' $sel>$m_name</option>";
                        }
                        ?>
                    </select>

                    <select name="year" onchange="this.form.submit()">
                        <?php
                        $curr_year = date('Y');
                        for ($y = $curr_year; $y >= 2023; $y--) {
                            $sel = ($y == $selected_year) ? 'selected' : '';
                            echo "<option value='$y' $sel>$y</option>";
                        }
                        ?>
                    </select>

                    <!-- Removed Equipment Filter -->

                    <button type="submit" class="btn-go"><i class="fa-solid fa-filter"></i></button>
                    
                    <!-- Reset -->
                     <a href="admin_laporan_bulanan.php" title="Reset to Current Month" style="color: #94a3b8; font-size: 14px;"><i class="fa-solid fa-rotate-left"></i></a>
                </form>

                <a href="export/export_excel.php?type=monthly&month=<?= $filter_date ?>" class="btn-export">
                    <i class="fa-solid fa-file-excel"></i> Export Excel
                </a>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 40px; min-width: 40px;">No</th>
                        <th rowspan="2" style="width: 250px; min-width: 250px;">Nama Peralatan</th>
                        <th rowspan="2">Lokasi</th>
                        <th colspan="<?= $days_in_month ?>">Tanggal <?= $indo_months[$selected_month] ?> <?= $selected_year ?></th>
                        <th rowspan="2" style="width: 100px;">OPERASI TERPUTUS<br><small>(Total Jam)</small></th>
                        <th rowspan="2" style="width: 120px;">SERVICEABILITY<br>(%)<br><small>(Target 90%)</small></th>
                        <th rowspan="2" style="width: 150px;">Keterangan</th>
                    </tr>
                    <tr>
                        <?php for($d=1; $d<=$days_in_month; $d++): ?>
                            <th style="min-width: 25px;"><?= $d ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;

                    $categories = ['MECHANICAL' => [], 'ELECTRICAL' => []];
                    foreach ($sections as $sec) {
                        $cat = !empty($sec['parent_category']) ? $sec['parent_category'] : 'MECHANICAL'; 
                        $categories[$cat][] = $sec;
                    }

                    foreach ($categories as $cat_name => $cat_sections) {
                        if (empty($cat_sections)) continue;

                        $colspan = 6 + $days_in_month;
                        echo "<tr class='category-row'>";
                        echo "<td colspan='$colspan' style='text-align: left; padding: 10px; font-weight: 800; font-size: 14px; letter-spacing: 1px;'>$cat_name FACILITY</td>";
                        echo "</tr>";

                        foreach ($cat_sections as $section) {
                            $equipments = $conn->query("
                                SELECT e.*, l.nama_lokasi
                                FROM equipments e
                                JOIN lokasi l ON e.lokasi_id = l.id
                                WHERE e.section_id = {$section['id']}
                                ORDER BY e.nama_peralatan
                            ")->fetch_all(MYSQLI_ASSOC);

                            if (empty($equipments)) continue;

                            echo "<tr class='section-row'>";
                            echo "<td colspan='$colspan'>" . htmlspecialchars($section['nama_section']) . "</td>";
                            echo "</tr>";

                            $section_total_perf = 0;
                            $section_total_downtime = 0;
                            $section_eq_count = 0;

                            foreach ($equipments as $eq) {
                                echo "<tr>";
                                echo "<td>$no</td>";
                                echo "<td style='text-align:left; font-weight: 500;'>" . htmlspecialchars($eq['nama_peralatan']) . "</td>";
                                echo "<td style='font-size: 11px; color:#64748b;'>" . htmlspecialchars($eq['nama_lokasi']) . "</td>";

                                $status_counts = ['O' => 0, 'X' => 0, 'V' => 0, '-' => 0];
                                $total_downtime = 0;

                                for ($d = 1; $d <= $days_in_month; $d++) {
                                    $date_check = sprintf("%04d-%02d-%02d", $selected_year, $selected_month, $d);
                                    
                                    $check = $conn->query("SELECT status, jam_operasi FROM monitoring WHERE equipment_id = {$eq['id']} AND tanggal = '$date_check'")->fetch_assoc();
                                    
                                    $status = $check ? $check['status'] : '';
                                    $cls = 'bg-blank';
                                    if($status == 'O') $cls = 'bg-O';
                                    if($status == 'X') $cls = 'bg-X';
                                    if($status == 'V') $cls = 'bg-V';
                                    if($status == '-') $cls = 'bg-minus';
                                    
                                    if ($check) {
                                        $status_counts[$status]++;
                                        
                                        $max_hours_per_day = 24; 
                                        if (isset($eq['jam_operasi_harian']) && $eq['jam_operasi_harian'] > 0) {
                                            $max_hours_per_day = $eq['jam_operasi_harian'];
                                        }

                                        if ($status != 'O') {
                                            $op_hours = isset($check['jam_operasi']) ? $check['jam_operasi'] : 0;
                                            $loss = $max_hours_per_day - $op_hours;
                                            $total_downtime += max(0, $loss);
                                        }
                                    }

                                    echo "<td class='$cls'>$status</td>";
                                }

                                $total_potential_hours = $days_in_month * 24;
                                $perf = 100; 
                                
                                if($total_potential_hours > 0) {
                                    $perf = (($total_potential_hours - $total_downtime) / $total_potential_hours) * 100;
                                }
                                
                                $perf_cls = 'perf-high';
                                if($perf < 90) $perf_cls = 'perf-mid';
                                if($perf < 80) $perf_cls = 'perf-low';

                                $section_total_perf += $perf;
                                $section_total_downtime += $total_downtime;
                                $section_eq_count++;

                                echo "<td>" . ($total_downtime > 0 ? $total_downtime : '-') . "</td>";
                                echo "<td class='$perf_cls'>" . number_format($perf, 1) . "%</td>";
                                echo "<td>-</td>";
                                echo "</tr>";
                                $no++;
                            }

                            if ($section_eq_count > 0) {
                                $avg_perf = $section_total_perf / $section_eq_count;
                                $avg_downtime = $section_total_downtime / $section_eq_count;
                                
                                $avg_perf_cls = 'perf-high';
                                if($avg_perf < 90) $avg_perf_cls = 'perf-mid';
                                if($avg_perf < 80) $avg_perf_cls = 'perf-low';

                                echo "<tr style='font-weight: bold; background-color: #f1f5f9;'>";
                                echo "<td></td>";
                                echo "<td style='text-align: center;'>RATA-RATA</td>";
                                echo "<td></td>";
                                
                                echo str_repeat("<td></td>", $days_in_month);
                                
                                echo "<td>" . ($avg_downtime > 0 ? number_format($avg_downtime, 1) : '0') . "</td>";
                                echo "<td class='$avg_perf_cls'>" . number_format($avg_perf, 1) . "%</td>";
                                echo "<td></td>";
                                echo "</tr>";
                            }
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="legend-bar">
            <div class="legend-item"><div class="dot bg-O"></div> <span>Normal (O)</span></div>
            <div class="legend-item"><div class="dot bg-minus"></div> <span>Menurun (-)</span></div>
            <div class="legend-item"><div class="dot bg-X"></div> <span>Terputus (X)</span></div>
            <div class="legend-item"><div class="dot bg-V"></div> <span>Gangguan (V)</span></div>
            <div class="legend-item"><div class="dot bg-blank" style="border:1px solid #ccc"></div> <span>Belum Dicek (Kosong)</span></div>
        </div>

    </div>

    </main>
</body>
</html>
