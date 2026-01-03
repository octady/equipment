<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

include "config/database.php";

// 1. Determine Date & Filters
$selected_date = $_GET['date'] ?? date('Y-m-d');
$is_today = ($selected_date === date('Y-m-d'));

$filter_search = $_GET['search'] ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_section = $_GET['section'] ?? '';
$filter_equipment = $_GET['equipment'] ?? '';
$filter_inspector = $_GET['inspector'] ?? ''; 

// 2. Fetch Master Data for Filters
// Sections
$sections_list = [];
$res_sec = $conn->query("SELECT id, nama_section, parent_category FROM sections ORDER BY parent_category, urutan");
while($r = $res_sec->fetch_assoc()) {
    $sections_list[] = $r;
}

// Equipments (Only name and ID for dropdown)
$equipments_list = [];
$res_eq_master = $conn->query("SELECT id, nama_peralatan, section_id FROM equipments ORDER BY nama_peralatan");
while($r = $res_eq_master->fetch_assoc()) {
    $equipments_list[] = $r;
}


// 3. Build Query
$sql = "SELECT 
            e.id as equipment_id,
            e.nama_peralatan,
            s.parent_category,
            s.nama_section,
            l.nama_lokasi,
            i.status,
            i.keterangan,
            i.checked_by,
            i.created_at
        FROM equipments e
        JOIN sections s ON e.section_id = s.id
        JOIN lokasi l ON e.lokasi_id = l.id
        JOIN inspections_daily i ON e.id = i.equipment_id AND i.tanggal = ?
        WHERE 1=1";

$types = "s";
$params = [$selected_date];

if ($filter_search) {
    $sql .= " AND e.nama_peralatan LIKE ?";
    $types .= "s";
    $params[] = "%$filter_search%";
}

if ($filter_category) {
    $sql .= " AND s.parent_category = ?";
    $types .= "s";
    $params[] = $filter_category;
}

if ($filter_section) {
    $sql .= " AND s.id = ?";
    $types .= "i";
    $params[] = $filter_section;
}

if ($filter_equipment) {
    $sql .= " AND e.id = ?";
    $types .= "i";
    $params[] = $filter_equipment;
}

$sql .= " ORDER BY s.parent_category, s.urutan, e.nama_peralatan";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [
    'MECHANICAL' => [],
    'ELECTRICAL' => []
];

// 4. Group Data
while ($row = $result->fetch_assoc()) {
    $cat = strtoupper($row['parent_category']);
    if (isset($data[$cat])) {
        $data[$cat][] = $row;
    }
}

// Active Page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Monitoring - InJourney Airports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* ========================================
           GLOBAL STYLES (Kept Same)
           ======================================== */
        /* ========================================
           GLOBAL STYLES (Matching checklist.php)
           ======================================== */
        :root {
            --brand-primary: #087F8A;
            --brand-primary-dark: #065C63;
            --brand-secondary: #f59e0b;
            --brand-success: #087F8A; /* Teal as success per checklist.php */
            --brand-danger: #f43f5e;
            --brand-warning: #f59e0b;
            --brand-info: #6366f1;
            
            --bg-vibrant: #f8fafc;
            
            --grad-primary: linear-gradient(135deg, #087F8A 0%, #065C63 100%);
            --grad-success: linear-gradient(135deg, #087F8A 0%, #065C63 100%);
            --grad-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --grad-danger: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%);
            --grad-info: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            
            --primary: #087F8A; /* Keep for other usages */
            --dark-teal: #065C63;
            --bg-body: #f4f8f8;
            --white: #ffffff;
            --text-slate: #334155;
            --border-light: #e2e8f0;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-body);
            margin: 0;
            padding-top: 110px;
            color: var(--text-slate);
            overflow-x: hidden;
        }

        /* ========================================
           NAVBAR STYLES
           ======================================== */


        /* ========================================
           PAGE CONTENT
           ======================================== */
        .main-content { max-width: 1400px; margin: 0 auto; padding: 0 20px 40px; }
        
        .page-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .date-display {
            background: var(--grad-primary);
            color: white;
            padding: 15px 30px;
            border-radius: 12px;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(8, 127, 138, 0.2);
        }

        .date-display h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        
        .date-display p {
            margin: 5px 0 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .control-panel {
            background: var(--white);
            padding: 20px 24px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            margin-bottom: 25px;
            border: 1px solid var(--border-light);
        }

        /* Filter Grid System - 5 Columns + Reset */
        .filter-row {
            display: grid;
            grid-template-columns: 1.2fr 1.5fr 1.2fr 1.2fr 1.5fr auto;
            gap: 16px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-size: 11px; 
            font-weight: 700; 
            color: var(--brand-primary); 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-group label i {
            font-size: 12px;
        }
        
        .filter-group input, .filter-group select {
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            color: var(--text-slate);
            width: 100%;
            height: 42px;
            box-sizing: border-box;
            background-color: #f8fafc;
            font-size: 13px;
            transition: all 0.2s;
        }

        .filter-group input:focus, .filter-group select:focus {
            outline: none;
            border-color: var(--brand-primary);
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(8, 127, 138, 0.1);
        }

        .btn-reset {
            background: #fff;
            border: 1px solid #e2e8f0;
            color: var(--text-slate);
            width: 42px;
            height: 42px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn-reset:hover {
            background: #f1f5f9;
            color: var(--brand-danger);
            border-color: #cbd5e1;
        }

        /* ========================================
           SECTIONS & TABLES
           ======================================== */
        .category-section {
            margin-bottom: 40px;
        }
        
        .category-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark-teal);
            margin-bottom: 15px;
            padding-left: 10px;
            border-left: 4px solid var(--primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            overflow: hidden;
            border: 1px solid var(--border-light);
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th { 
            background: #0d5d63; 
            color: #ffffff; 
            text-align: left; 
            padding: 16px; 
            font-size: 13px; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            border-bottom: none;
        }
        
        td { 
            padding: 14px 16px; 
            border-bottom: 1px solid #f1f5f9; 
            font-size: 14px; 
            vertical-align: middle;
        }
        
        tr:last-child td { border-bottom: none; }
        
        /* Specific Column Styles */
        td.cell-notes {
            white-space: normal;
            min-width: 250px;
            color: #64748b;
            font-style: italic;
        }

        /* Badge Styles matching user request (Flat, No Icons) */
        .badge { 
            padding: 6px 12px; 
            border-radius: 6px; 
            font-size: 13px; 
            font-weight: 700;
            display: inline-block;
            text-align: center;
            min-width: 70px;
            box-shadow: none;
        }
        
        .badge i { display: none; }
        
        .badge-success { background: #d1fae5; color: #065f46; } /* Normal */
        .badge-danger { background: #fee2e2; color: #991b1b; }   /* Rusak */
        .badge-warning { background: #ffedd5; color: #9a3412; } /* Menurun */
        .badge-info { background: #e0e7ff; color: #3730a3; }    /* Standby */
        
        .badge-neutral { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

        .btn-edit {
            background: white;
            border: 1px solid var(--border-light);
            color: var(--primary);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }

        .btn-edit:hover {
            border-color: var(--primary);
            background: #f0fbfc;
        }
        
        .inspector-name {
            font-size: 13px;
            color: var(--brand-primary);
            font-weight: 600;
            background: rgba(8, 127, 138, 0.08);
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        /* ========================================
           RESPONSIVE
           ======================================== */
        .hamburger-menu { display: none; background: none; border: none; font-size: 24px; color: var(--primary); cursor: pointer; }

        @media (max-width: 768px) {
            body { 
                padding-top: 85px; 
                overflow-x: hidden; /* GLOBAL LOCK: Prevent horizontal scroll */
            }
            .header-content { padding: 10px 15px; }
            .logo-injourney { height: 35px; }
            .logo-bandara { height: 40px; }
            
            .hamburger-menu { display: block; }
            .navbar-center {
                display: none; position: absolute; top: 100%; left: 0; width: 100%;
                background: #fff; flex-direction: column; padding: 15px 0;
                box-shadow: 0 10px 15px rgba(0,0,0,0.05);
            }
            .navbar-center.active { display: flex; }
            .nav-menu { flex-direction: column; width: 100%; }
            .nav-item { width: 100%; }
            .nav-item > a { padding: 15px 25px; border-bottom: 1px solid #f8f9fa; }
            .logout-btn { margin: 15px 25px; }

            /* REMOVE ALL PADDING FROM CONTAINER ON MOBILE to ensure full width */
            .main-content { 
                padding: 10px 0 30px 0 !important; 
                width: 100% !important;
                overflow-x: hidden !important; 
            }
            .control-panel, .page-header {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .filter-row { grid-template-columns: 1fr; }

            /* Responsive Table -> STRICT Full Scaled View */
            table {
                width: 100% !important;
                table-layout: fixed !important; 
                border-collapse: collapse;
                margin: 0 !important; /* No margin */
            }
            
            thead { display: table-header-group; }
            tbody { display: table-row-group; }
            tr { display: table-row !important; background: white; border-bottom: 1px solid #e2e8f0; }
            td, th { 
                display: table-cell !important; 
                vertical-align: middle;
                word-wrap: break-word; 
                word-break: break-all; /* Aggressive break */
                white-space: normal !important; 
                hyphens: auto;
            }
            
            thead tr { 
                position: relative;
                top: auto; 
                left: auto;
            }

            /* Header Styling for Mobile */
            th {
                font-size: 10px;
                padding: 8px 2px;
                background: #0d5d63;
                color: white;
                text-align: center;
            }

            /* Column Sizing - Adjusted for best fit */
            /* Col 1: Name (40%) */
            th:nth-child(1), td:nth-child(1) {
                width: 40% !important;
                padding: 8px 2px 8px 6px; /* Small left padding */
                text-align: left;
            }

            td:nth-child(1) {
                font-size: 10.5px;
                line-height: 1.25;
                font-weight: 600;
            }
            td:nth-child(1) .text-secondary { 
                font-size: 9px; 
                display: block; 
                margin-top: 2px;
            }

            /* Col 2: Location (30%) */
            th:nth-child(2), td:nth-child(2) {
                width: 30% !important;
                text-align: left;
                padding: 8px 2px;
            }
            td:nth-child(2) {
                font-size: 10px;
                color: #64748b;
                line-height: 1.2;
            }
            tr td { vertical-align: top !important; } 

            /* Col 3: Status (30%) */
            th:nth-child(3), td:nth-child(3) {
                width: 30% !important;
                text-align: center;
                padding: 8px 2px 8px 0;
            }
            
            td:nth-child(3) .badge {
                width: auto;
                max-width: 95%; 
                font-size: 9px;
                padding: 4px 2px;
                white-space: normal; /* Allow wrapping inside badge */
                display: inline-block;
                line-height: 1.2;
            }

            .summary-header {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 15px;
                padding: 0 10px; /* Add padding back to header text */
            }
            .summary-header > div {
                width: 100%;
            }
            .summary-time-box {
                text-align: left !important;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>


<main class="main-content">
    
    <!-- Date Header -->
    <div class="page-header">
        <div class="date-display">
            <h1>RIWAYAT MONITORING</h1>
            <p><i class="fa-solid fa-clipboard-list"></i> Laporan Harian & Status Pengecekkan</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="control-panel">
        <form method="GET" action="" id="filterForm">
            <div class="filter-row">
                <!-- Date Filter -->
                <div class="filter-group">
                    <label><i class="fa-regular fa-calendar"></i> Tanggal</label>
                    <input type="date" name="date" value="<?= $selected_date ?>" onchange="this.form.submit()" max="<?= date('Y-m-d') ?>">
                </div>

                <!-- Text Search -->
                <div class="filter-group">
                    <label><i class="fa-solid fa-search"></i> Cari</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($filter_search) ?>" placeholder="Nama peralatan..." onchange="this.form.submit()">
                </div>

                <!-- Category Filter -->
                <div class="filter-group">
                    <label><i class="fa-solid fa-layer-group"></i> Kategori</label>
                    <select name="category" onchange="this.form.submit()">
                        <option value="">Semua Kategori</option>
                        <option value="MECHANICAL" <?= $filter_category == 'MECHANICAL' ? 'selected' : '' ?>>Mechanical</option>
                        <option value="ELECTRICAL" <?= $filter_category == 'ELECTRICAL' ? 'selected' : '' ?>>Electrical</option>
                    </select>
                </div>

                <!-- Section (Type) Filter -->
                <div class="filter-group">
                    <label><i class="fa-solid fa-tags"></i> Jenis (Section)</label>
                    <select name="section" onchange="this.form.submit()">
                        <option value="">Semua Jenis</option>
                        <?php foreach($sections_list as $sec): ?>
                            <?php if(!$filter_category || $sec['parent_category'] == $filter_category): ?>
                            <option value="<?= $sec['id'] ?>" <?= $filter_section == $sec['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sec['nama_section']) ?>
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Equipment Name Filter -->
                <div class="filter-group">
                    <label><i class="fa-solid fa-wrench"></i> Nama Alat</label>
                    <select name="equipment" onchange="this.form.submit()">
                        <option value="">Semua Alat</option>
                        <?php foreach($equipments_list as $eq): ?>
                            <?php 
                                // Simple logic to filter equipment by section if selected
                                if($filter_section && $eq['section_id'] != $filter_section) continue;
                            ?>
                            <option value="<?= $eq['id'] ?>" <?= $filter_equipment == $eq['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($eq['nama_peralatan']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                     <a href="riwayat_monitoring.php?date=<?= $selected_date ?>" class="btn-reset" title="Reset Filter">
                        <i class="fa-solid fa-rotate-left"></i>
                     </a>
                </div>
            </div>
            
            <?php
            // Calculate unique inspectors (filtering out 'User Monitoring')
            // And calculate Latest Check Time
            $inspectors_on_duty = [];
            $latest_time_ts = 0;
            
            foreach ($data as $cat_data) {
                foreach ($cat_data as $item) {
                    // Inspectors
                    if (!empty($item['checked_by'])) {
                        $names = explode(',', $item['checked_by']);
                        foreach($names as $nm) {
                            $nm = trim($nm);
                            if(empty($nm)) continue;
                            if(strcasecmp($nm, 'User Monitoring') === 0) continue;
                            $inspectors_on_duty[$nm] = true;
                        }
                    }
                    // Time
                    if (!empty($item['created_at'])) {
                        $ts = strtotime($item['created_at']);
                        if ($ts > $latest_time_ts) {
                            $latest_time_ts = $ts;
                        }
                    }
                }
            }
            $inspectors_str = implode(', ', array_keys($inspectors_on_duty));
            
            // Format Latest Time
            $display_time = ($latest_time_ts > 0) ? date('H:i:s', $latest_time_ts) : '-';
            
            // Date formatting
            $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            
            $ts = strtotime($selected_date);
            $dayName = $days[date('w', $ts)];
            $dayDate = date('d', $ts);
            $monthName = $months[date('n', $ts)];
            $year = date('Y', $ts);
            
            $formatted_date = "$dayName, $dayDate $monthName $year";
            ?>

            <!-- NEW HEADER SECTION inside Filter Card -->
            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px dashed #e2e8f0;">
                <div class="summary-header" style="display: flex; justify-content: space-between; align-items: flex-end;">
                    <div>
                        <div style="font-size: 24px; font-weight: 700; color: #0d5d63; margin-bottom: 5px;">
                            <?= $formatted_date ?>
                        </div>
                        <?php if (!empty($inspectors_str)): ?>
                        <div style="display: flex; align-items: flex-start; gap: 12px; margin-top: 8px; flex-wrap: wrap;">
                            <span style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; padding-top: 3px; min-width: 70px;">
                                <i class="fa-solid fa-user-check"></i> Petugas:
                            </span> 
                            <span style="font-size: 14px; color: #334155; font-weight: 600; line-height: 1.4;">
                                <?= htmlspecialchars($inspectors_str) ?>
                            </span>
                        </div>
                        <?php else: ?>
                        <div style="font-size: 13px; color: #94a3b8; font-style: italic;">
                            Belum ada petugas tercatat.
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="summary-time-box" style="text-align: right;">
                        <div style="font-family: 'Courier New', monospace; font-size: 20px; font-weight: 700; color: #0f172a; background: #f1f5f9; padding: 4px 12px; border-radius: 6px; border: 1px solid #cbd5e1; display: inline-block;">
                            <?= $display_time ?>
                        </div>

                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- MECHANICAL Table -->
    <?php if (!$filter_category || $filter_category == 'MECHANICAL'): ?>
    <div class="category-section">
        <div class="category-title">
            <span><i class="fa-solid fa-gears"></i> MECHANICAL</span>
            <span style="font-size: 14px; color: #64748b; font-weight:normal;"><?= count($data['MECHANICAL']) ?> Peralatan</span>
        </div>
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 25%;">Nama Peralatan</th>
                            <th style="width: 15%;">Lokasi</th>
                            <th style="width: 15%;">Status</th>



                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($data['MECHANICAL']) > 0): ?>
                            <?php foreach ($data['MECHANICAL'] as $item): ?>
                            <tr onclick="window.location.href='detail_monitoring_user.php?id=<?= $item['equipment_id'] ?>&date=<?= $selected_date ?>'" style="cursor: pointer; transition: background-color 0.2s;">
                                <td>
                                    <div style="font-weight: 600; color: #334155;"><?= htmlspecialchars($item['nama_peralatan']) ?></div>
                                    <div style="font-size: 12px; color: #94a3b8; margin-top: 2px;"><?= htmlspecialchars($item['nama_section']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($item['nama_lokasi']) ?></td>
                                <td>
                                    <?php if ($item['status'] == 'O'): ?>
                                        <span class="badge badge-success"><i class="fa-solid fa-check-circle"></i> Normal</span>
                                    <?php elseif ($item['status'] == 'X'): ?>
                                        <span class="badge badge-danger"><i class="fa-solid fa-circle-exclamation"></i> Rusak</span>
                                    <?php elseif ($item['status'] == 'V'): ?>
                                        <span class="badge badge-info"><i class="fa-solid fa-triangle-exclamation"></i> Standby</span>
                                    <?php elseif ($item['status'] == '-'): ?>
                                        <span class="badge badge-warning"><i class="fa-solid fa-arrow-trend-down"></i> Menurun</span>
                                    <?php else: ?>
                                        <span class="badge badge-neutral"><i class="fa-regular fa-circle"></i> Belum Dicek</span>
                                    <?php endif; ?>
                                </td>



                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; padding: 20px;">Tidak ada data mechanical yang ditemukan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ELECTRICAL Table -->
    <?php if (!$filter_category || $filter_category == 'ELECTRICAL'): ?>
    <div class="category-section">
        <div class="category-title">
            <span><i class="fa-solid fa-bolt"></i> ELECTRICAL</span>
            <span style="font-size: 14px; color: #64748b; font-weight:normal;"><?= count($data['ELECTRICAL']) ?> Peralatan</span>
        </div>
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 25%;">Nama Peralatan</th>
                            <th style="width: 15%;">Lokasi</th>
                            <th style="width: 15%;">Status</th>



                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($data['ELECTRICAL']) > 0): ?>
                            <?php foreach ($data['ELECTRICAL'] as $item): ?>
                            <tr onclick="window.location.href='detail_monitoring_user.php?id=<?= $item['equipment_id'] ?>&date=<?= $selected_date ?>'" style="cursor: pointer; transition: background-color 0.2s;">
                                <td>
                                    <div style="font-weight: 600; color: #334155;"><?= htmlspecialchars($item['nama_peralatan']) ?></div>
                                    <div style="font-size: 12px; color: #94a3b8; margin-top: 2px;"><?= htmlspecialchars($item['nama_section']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($item['nama_lokasi']) ?></td>
                                <td>
                                    <?php if ($item['status'] == 'O'): ?>
                                        <span class="badge badge-success"><i class="fa-solid fa-check-circle"></i> Normal</span>
                                    <?php elseif ($item['status'] == 'X'): ?>
                                        <span class="badge badge-danger"><i class="fa-solid fa-circle-exclamation"></i> Rusak</span>
                                    <?php elseif ($item['status'] == 'V'): ?>
                                        <span class="badge badge-warning"><i class="fa-solid fa-triangle-exclamation"></i> Standby</span>
                                    <?php else: ?>
                                        <span class="badge badge-neutral"><i class="fa-regular fa-circle"></i> Belum Dicek</span>
                                    <?php endif; ?>
                                </td>



                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; padding: 20px;">Tidak ada data electrical yang ditemukan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</main>




</body>
</html>