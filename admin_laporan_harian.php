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

// 1. Determine Date & Filters
$selected_date = $_GET['date'] ?? date('Y-m-d');
$is_today = ($selected_date === date('Y-m-d'));

$filter_search = $_GET['search'] ?? '';
$filter_location = $_GET['lokasi'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_section = $_GET['section'] ?? '';
$filter_equipment = $_GET['equipment'] ?? '';


// 2. Fetch Master Data for Filters
// Locations (New)
$locations_list = [];
$res_loc = $conn->query("SELECT id, nama_lokasi FROM lokasi ORDER BY nama_lokasi");
while($r = $res_loc->fetch_assoc()) {
    $locations_list[] = $r;
}

$sections_list = [];
$res_sec = $conn->query("SELECT id, nama_section, parent_category FROM sections ORDER BY parent_category, urutan");
while($r = $res_sec->fetch_assoc()) {
    $sections_list[] = $r;
}

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
            i.created_at,
            (SELECT foto_path FROM dokumentasi_masalah WHERE inspection_id = i.id LIMIT 1) as foto_path
        FROM equipments e
        JOIN sections s ON e.section_id = s.id
        JOIN lokasi l ON e.lokasi_id = l.id
        JOIN monitoring i ON e.id = i.equipment_id AND i.tanggal = ?
        WHERE 1=1";

$types = "s";
$params = [$selected_date];

if ($filter_search) {
    $sql .= " AND e.nama_peralatan LIKE ?";
    $types .= "s";
    $params[] = "%$filter_search%";
}

if ($filter_location) { 
    $sql .= " AND l.id = ?";
    $types .= "s";
    $params[] = $filter_location;
}

$filter_status = $_GET['status'] ?? '';

// ... (other filters)

if ($filter_status) {
    $sql .= " AND i.status = ?";
    $types .= "s";
    $params[] = $filter_status;
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Monitoring (Admin)</title>
    <script>
    // Critical: Run BEFORE any rendering to prevent sidebar flicker
    if (localStorage.getItem('sidebarOpen') === 'true') {
        document.documentElement.classList.add('sidebar-open');
    }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Styles from previous reports reused for Navbar, but Page Styles updated to User version -->
    <link rel="stylesheet" href="../assets/css/style.css"> 
    
    <style>
        /* OVERRIDE & EXTEND Styles to matching User History Page */
        :root {
            --brand-primary: #087F8A;
            --brand-primary-dark: #065C63;
            --bg-body: #f4f8f8;
            --white: #ffffff;
            --text-slate: #334155;
            --border-light: #e2e8f0;
            --grad-primary: linear-gradient(135deg, #087F8A 0%, #065C63 100%);
        }

        body {
            /* Reset some admin styles to match user vibe */
            font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
            background-color: var(--bg-body);
        }

        /* Navbar tweaks for admin */
        .navbar { margin-bottom: 0; }
        
        /* New Content Styles */
        .main-content { max-width: 1400px; margin: 0 auto; padding: 20px 20px 40px; }
        
        .page-header {
            margin-bottom: 20px;
            text-align: left;
        }

        .date-display {
            background: transparent;
            color: #087F8A;
            padding: 0;
            border-radius: 0;
            display: inline-block;
            box-shadow: none;
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
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            margin-bottom: 25px;
            border: 1px solid var(--border-light);
        }

        /* Filter Grid System */
        .filter-row {
            display: grid;
            grid-template-columns: 1.2fr 1.5fr 1.2fr 1.2fr 1.2fr 1.5fr auto;
            gap: 16px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-size: 12px; font-weight: 700; color: #065C63; text-transform: uppercase; letter-spacing: 0.5px;
        }
        
        .filter-group input, .filter-group select {
            padding: 10px 12px;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            font-family: inherit;
            color: var(--text-slate);
            width: 100%;
            box-sizing: border-box;
            background-color: #f8fafc;
            font-size: 14px;
        }

        .btn-reset {
            background: transparent;
            border: 1px solid var(--border-light);
            color: #64748b;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 11px;
            transition: all 0.2s;
            text-align: center;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
            margin-left: auto; /* Float right if flex container allows */
        }
        
        .btn-reset:hover {
            background: #f1f5f9;
            color: #ef4444;
            border-color: #fda4af;
        }
        
        /* Export Buttons (Preserved from admin) */
        .export-bar {
            display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 25px; justify-content: center;
        }
        .btn-export {
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-success { background: #10b981; }
        .btn-warning { background: #f59e0b; }


        .category-section { margin-bottom: 40px; }
        
        .category-title {
            font-size: 18px;
            font-weight: 700;
            color: #065C63;
            margin-bottom: 15px;
            padding-left: 10px;
            border-left: 4px solid #087F8A;
        }

        .table-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            overflow: hidden;
            border: 1px solid var(--border-light);
        }

        /* Responsive Table Styles (Copied from User) */
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
        }
        
        td { 
            padding: 14px 16px; 
            border-bottom: 1px solid #f1f5f9; 
            font-size: 14px; 
            vertical-align: middle;
        }
        
        tr:last-child td { border-bottom: none; }
        
        /* Table Row Link */
        tr.clickable-row { cursor: pointer; transition: background 0.1s; }
        tr.clickable-row:hover { background-color: #f8fafc; }

        .badge { 
            padding: 6px 12px; 
            border-radius: 6px; 
            font-size: 13px; 
            font-weight: 700;
            display: inline-block;
            text-align: center;
            min-width: 70px;
        }
        
        .badge-success { background: #d1fae5; color: #065f46; } 
        .badge-danger { background: #fee2e2; color: #991b1b; }   
        .badge-warning { background: #ffedd5; color: #9a3412; } 
        .badge-info { background: #e0e7ff; color: #3730a3; }    
        
        .inspector-name {
            font-size: 13px;
            color: #087F8A;
            font-weight: 600;
            background: rgba(8, 127, 138, 0.08);
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        /* MOBILE RESPONSIVE STRICT (Copied from User) */
        @media (max-width: 768px) {
            body { 
                overflow-x: hidden; 
            }
            .main-content { 
                padding: 10px 0 30px 0 !important; 
                width: 100% !important;
                overflow-x: hidden !important; 
            }
            
            .filter-row { grid-template-columns: 1fr; }

            /* Table Strict Full width */
            table {
                width: 100% !important;
                table-layout: fixed !important; 
                border-collapse: collapse;
                margin: 0 !important;
                min-width: unset; /* Remove min-width override */
            }
            
            thead { display: table-header-group; }
            tbody { display: table-row-group; }
            tr { display: table-row !important; background: white; border-bottom: 1px solid #e2e8f0; }
            td, th { 
                display: table-cell !important; 
                vertical-align: middle;
                word-wrap: break-word; 
                word-break: break-all;
                white-space: normal !important; 
                hyphens: auto;
            }

            th {
                font-size: 10px;
                padding: 8px 2px;
                background: #0d5d63;
                color: white;
                text-align: center;
            }

            /* Col 1: Name (40%) */
            th:nth-child(1), td:nth-child(1) {
                width: 40% !important;
                padding: 8px 2px 8px 6px;
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
                white-space: normal;
                display: inline-block;
                line-height: 1.2;
            }

            .summary-header {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 15px;
                padding: 0 10px;
            }
        }
        
        .btn-action-edit {
            background-color: var(--brand-primary);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-action-edit:hover {
            background-color: var(--brand-primary-dark);
            transform: translateY(-1px);
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
             const searchInput = document.getElementById('searchInput');
             if(searchInput) {
                 searchInput.addEventListener('input', function() {
                     const filter = this.value.toLowerCase();
                     const rows = document.querySelectorAll('.data-row'); // Need to add class to tr
                     
                     rows.forEach(row => {
                         const eqName = row.getAttribute('data-eq-name').toLowerCase();
                         if(eqName.includes(filter)) {
                             row.style.display = '';
                         } else {
                             row.style.display = 'none';
                         }
                     });
                 });
             }
        });
    </script>
</head>
<body>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="container">
        
        <div style="display: flex; justify-content: flex-end; margin-bottom: 0px; gap: 10px;">

             <a href="admin_laporan_bulanan.php" class="btn-reset" style="background: white; padding: 8px 15px; font-size: 12px; border:1px solid #e2e8f0; color: #087F8A; font-weight:600;">
                <i class="fa-solid fa-calendar-days"></i> Lihat Laporan Bulanan
             </a>
        </div>
        <!-- Headers -->
        <div class="page-header">
            <div class="date-display">
                <h1>LAPORAN HARIAN</h1>
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

                    <!-- Text Search (Live Search) -->
                    <div class="filter-group">
                        <label><i class="fa-solid fa-search"></i> Cari</label>
                        <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($filter_search) ?>" placeholder="Nama peralatan..." autocomplete="off">
                    </div>

                    <!-- NEW: Filter Location -->
                    <div class="filter-group">
                        <label><i class="fa-solid fa-map-marker-alt"></i> Lokasi</label>
                        <select name="lokasi" onchange="this.form.submit()">
                            <option value="">Semua Lokasi</option>
                            <?php foreach($locations_list as $loc): ?>
                            <option value="<?= $loc['id'] ?>" <?= $filter_location == $loc['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc['nama_lokasi']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div class="filter-group">
                        <label><i class="fa-solid fa-filter"></i> Status</label>
                        <select name="status" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="O" <?= $filter_status == 'O' ? 'selected' : '' ?>>Normal</option>
                            <option value="-" <?= $filter_status == '-' ? 'selected' : '' ?>>Menurun</option>
                            <option value="X" <?= $filter_status == 'X' ? 'selected' : '' ?>>Terputus</option>
                            <option value="V" <?= $filter_status == 'V' ? 'selected' : '' ?>>Gangguan</option>
                        </select>
                    </div>

                    <!-- Section (Type) Filter -->
                    <div class="filter-group">
                        <label><i class="fa-solid fa-tags"></i> Jenis (Section)</label>
                        <select name="section" onchange="this.form.submit()">
                            <option value="">Semua Jenis</option>
                            <?php foreach($sections_list as $sec): ?>
                                <option value="<?= $sec['id'] ?>" <?= $filter_section == $sec['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sec['nama_section']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Equipment Name Filter + Reset -->
                    <div class="filter-group">
                        <label><i class="fa-solid fa-wrench"></i> Nama Alat</label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <select name="equipment" onchange="this.form.submit()" style="flex: 1;">
                                <option value="">Semua Alat</option>
                                <?php foreach($equipments_list as $eq): ?>
                                    <?php 
                                        if($filter_section && $eq['section_id'] != $filter_section) continue;
                                    ?>
                                    <option value="<?= $eq['id'] ?>" <?= $filter_equipment == $eq['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($eq['nama_peralatan']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <a href="admin_laporan_harian.php?date=<?= $selected_date ?>" class="btn-reset" title="Reset Filter">
                                <i class="fa-solid fa-rotate-left"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Inspector Summary (Ported from user sort of, or kept simple) -->
                <?php
                $inspectors_on_duty = [];
                foreach ($data as $cat_data) {
                    foreach ($cat_data as $item) {
                        if (!empty($item['checked_by'])) {
                            $names = explode(',', $item['checked_by']);
                            foreach($names as $nm) {
                                $nm = trim($nm);
                                if(empty($nm)) continue;
                                if(strcasecmp($nm, 'User Monitoring') === 0) continue;
                                if(strcasecmp($nm, 'Admin') === 0) continue;
                                $inspectors_on_duty[$nm] = true;
                            }
                        }
                    }
                }
                ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #e2e8f0; display: flex; align-items: center; gap: 10px; font-size: 13px;">
                    <span style="font-weight: 700; color: #64748b; text-transform: uppercase;">Petugas Inspector:</span>
                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                        <?php if(empty($inspectors_on_duty)): ?>
                            <span style="color: #94a3b8; font-style: italic;">Belum ada data inspector spesifik.</span>
                        <?php else: ?>
                            <?php foreach(array_keys($inspectors_on_duty) as $ins): ?>
                                <span class="inspector-name"><i class="fa-regular fa-user"></i> <?= htmlspecialchars($ins) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>



        <!-- Content -->
        <?php foreach(['MECHANICAL', 'ELECTRICAL'] as $cat): ?>
            <?php if(!empty($data[$cat])): ?>
            <div class="category-section">
                <div class="category-title">
                    <span><i class="fa-solid <?= $cat == 'MECHANICAL' ? 'fa-gears' : 'fa-bolt' ?>"></i> <?= $cat ?></span>
                    <span style="font-size:12px; font-weight:500; color: #64748b; margin-left:10px;">(Total: <?= count($data[$cat]) ?> Alat)</span>
                </div>
                
                <div class="table-card">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama Peralatan</th>
                                    <th>Lokasi</th>
                                    <th style="text-align: center;">Foto</th>
                                    <th style="text-align: center;">Status</th>
                                    <th style="text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($data[$cat] as $row): ?>
                                <tr class="clickable-row data-row" data-eq-name="<?= htmlspecialchars($row['nama_peralatan']) ?>" onclick="window.location.href='admin_detail_monitoring.php?id=<?= $row['equipment_id'] ?>&date=<?= $selected_date ?>'">
                                    <td>
                                        <div><?= htmlspecialchars($row['nama_peralatan']) ?></div>
                                        <div class="text-secondary" style="font-size: 12px; color: #94a3b8; font-weight: 400; margin-top:2px;">
                                            <?= htmlspecialchars($row['nama_section']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 5px;">
                                            <i class="fa-solid fa-location-dot" style="color: #cbd5e1;"></i>
                                            <?= htmlspecialchars($row['nama_lokasi']) ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if (!empty($row['foto_path']) && file_exists($row['foto_path'])): ?>
                                            <a href="<?= $row['foto_path'] ?>" target="_blank" onclick="event.stopPropagation()">
                                                <img src="<?= $row['foto_path'] ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0;">
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #cbd5e1; font-size: 18px;"><i class="fa-solid fa-image"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if($row['status'] == 'O'): ?>
                                            <span class="badge badge-success">Normal</span>
                                        <?php elseif($row['status'] == 'X'): ?>
                                            <span class="badge badge-danger">Terputus</span>
                                        <?php elseif($row['status'] == 'V'): ?>
                                            <span class="badge badge-warning">Gangguan</span>
                                        <?php elseif($row['status'] == '-'): ?>
                                            <span class="badge badge-warning">Menurun</span>
                                        <?php else: ?>
                                            <span class="badge badge-neutral">Belum</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="admin_detail_monitoring.php?id=<?= $row['equipment_id'] ?>&date=<?= $selected_date ?>" class="btn-action-edit" onclick="event.stopPropagation()">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if(empty($data['MECHANICAL']) && empty($data['ELECTRICAL'])): ?>
            <div style="text-align: center; padding: 50px; color: #94a3b8;">
                <i class="fa-solid fa-folder-open" style="font-size: 40px; margin-bottom: 10px;"></i>
                <p>Tidak ada data monitoring untuk tanggal ini.</p>
            </div>
        <?php endif; ?>
        </div>
    </main>
</body>
</html>