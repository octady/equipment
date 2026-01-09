<?php
session_start();
include "config/database.php";

// Check login and admin role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] != 'admin') {
    header("Location: form_laporan.php");
    exit;
}

// Handle Delete
if (isset($_POST['delete_report'])) {
    $delete_id = intval($_POST['delete_report']);
    $stmt = $conn->prepare("DELETE FROM laporan_pengukuran WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    header("Location: admin_laporan_pengukuran.php?deleted=1");
    exit;
}

// Handle Save
if (isset($_POST['save_report'])) {
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $dibuat_oleh = $_POST['dibuat_oleh'] ?? '';
    $jabatan = $_POST['jabatan'] ?? '';
    $tahanan_isolasi = $_POST['tahanan_isolasi_json'] ?? '[]';
    $simulasi_genset = $_POST['simulasi_genset_json'] ?? '[]';
    $simulasi_ups = $_POST['simulasi_ups_json'] ?? '[]';
    $user_id = $_SESSION['user_id'] ?? null;
    
    // Check if editing existing report
    $edit_id = $_POST['edit_id'] ?? null;
    
    if ($edit_id) {
        $stmt = $conn->prepare("UPDATE laporan_pengukuran SET tanggal = ?, dibuat_oleh = ?, jabatan = ?, tahanan_isolasi_data = ?, simulasi_genset_data = ?, simulasi_ups_data = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $tanggal, $dibuat_oleh, $jabatan, $tahanan_isolasi, $simulasi_genset, $simulasi_ups, $edit_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO laporan_pengukuran (tanggal, dibuat_oleh, jabatan, tahanan_isolasi_data, simulasi_genset_data, simulasi_ups_data, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssi", $tanggal, $dibuat_oleh, $jabatan, $tahanan_isolasi, $simulasi_genset, $simulasi_ups, $user_id);
    }
    
    if ($stmt->execute()) {
        $saved_id = $edit_id ? $edit_id : $conn->insert_id;
        header("Location: admin_form_laporan.php?saved=1&id=" . $saved_id);
        exit;
    }
}

// Check for existing saved report to view/edit
$saved_report = null;
$view_mode = false;
$edit_mode = false;

if (isset($_GET['id'])) {
    $report_id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM laporan_pengukuran WHERE id = ?");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $saved_report = $result->fetch_assoc();
    
    if ($saved_report) {
        $view_mode = !isset($_GET['edit']);
        $edit_mode = isset($_GET['edit']);
    }
}

// Fetch Personnel list
$personnel_list = [];
$res_p = $conn->query("SELECT * FROM personnel ORDER BY nama_personnel");
if ($res_p) {
    while ($row = $res_p->fetch_assoc())
        $personnel_list[] = $row;
}

// Fetch all saved reports for this user
$my_reports = [];
$user_id = $_SESSION['user_id'] ?? null;
if ($user_id) {
    $res_reports = $conn->query("SELECT id, tanggal, dibuat_oleh, created_at FROM laporan_pengukuran WHERE created_by = $user_id ORDER BY tanggal DESC LIMIT 10");
    if ($res_reports) {
        while ($row = $res_reports->fetch_assoc())
            $my_reports[] = $row;
    }
}

$today = date('Y-m-d');
$today_display = date('d/m/Y');

$indo_months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
?>

<!DOCTYPE html>
<html lang="id">
<script>
if (localStorage.getItem('sidebarOpen') === 'true') {
    document.documentElement.classList.add('sidebar-open');
}
</script>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Laporan - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/monitoring.css">
    <!-- SheetJS Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        :root {
            --brand-teal: #087F8A;
            --brand-teal-dark: #065C63;
            --brand-emerald: #10b981;
        }

        /* Override global padding for admin view */
        body {
            padding-top: 0 !important;
        }

        /* Admin layout adjustments */
        .admin-main .view-mode-banner {
            margin-bottom: 20px;
        }
        
        .admin-main .form-container {
            padding: 24px;
            background: #fff;
            border-radius: 16px;
        }
        
        .admin-main .form-header {
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .admin-main .form-header h1 {
            font-size: 1.4rem;
        }
        


        .form-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        .form-header {
            background: linear-gradient(135deg, #087F8A 0%, #065C63 100%);
            color: white;
            padding: 32px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(8, 127, 138, 0.25);
        }

        .form-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 8px 0;
        }

        .form-header p {
            opacity: 0.9;
            margin: 0;
            font-size: 0.9rem;
        }

        .header-meta {
            display: flex;
            gap: 24px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.15);
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 0.9rem;
        }

        .meta-item label {
            font-weight: 500;
            opacity: 0.9;
        }

        .meta-item input,
        .meta-item select {
            background: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 600;
            color: #1e293b;
        }

        .meta-item select {
            cursor: pointer;
            min-width: 200px;
        }

        .section-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            margin-bottom: 24px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .section-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .section-header:hover {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        }

        .section-header i.chevron {
            color: var(--brand-teal);
            transition: transform 0.3s;
        }

        .section-header.collapsed i.chevron {
            transform: rotate(-90deg);
        }

        .section-header h2 {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            flex: 1;
        }

        .section-header .section-icon {
            width: 36px;
            height: 36px;
            background: var(--brand-teal);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .section-body {
            padding: 24px;
            overflow-x: auto;
        }

        .section-body.collapsed {
            display: none;
        }

        .kriteria-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .kriteria-box h4 {
            color: #15803d;
            font-size: 0.85rem;
            font-weight: 700;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .kriteria-list {
            font-size: 0.85rem;
            color: #166534;
        }

        .kriteria-list li {
            margin-bottom: 4px;
        }

        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
            font-size: 0.85rem;
        }

        .data-table th {
            background: var(--brand-teal);
            color: white;
            padding: 14px 12px;
            text-align: center;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border: 1px solid #065C63;
        }

        .data-table th.sub-header {
            background: #0d9488;
            font-size: 0.75rem;
        }

        .data-table td {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .data-table tr:nth-child(even) {
            background: #f8fafc;
        }

        .data-table tr:hover {
            background: #f0f9fa;
        }

        .data-table .circuit-row,
        .data-table .category-row {
            background: #f1f5f9 !important;
            font-weight: 600;
        }

        .data-table .circuit-row td,
        .data-table .category-row td {
            color: #334155;
        }

        .data-table .sub-item td:first-child {
            padding-left: 30px;
        }

        .table-input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.85rem;
            transition: all 0.2s;
            background: white;
        }

        .table-input:focus {
            outline: none;
            border-color: var(--brand-teal);
            box-shadow: 0 0 0 3px rgba(8, 127, 138, 0.1);
        }

        .table-input.number {
            text-align: center;
            width: 80px;
        }

        .table-input.readonly {
            background: #f8fafc;
            border: none;
            color: #64748b;
        }

        /* Radio buttons for Normal/Tidak Normal */
        .status-radio-group {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .status-radio {
            display: none;
        }

        .status-label {
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.2s;
            border: 2px solid #e2e8f0;
            background: white;
        }

        .status-radio:checked + .status-label.normal {
            background: #dcfce7;
            border-color: #22c55e;
            color: #15803d;
        }

        .status-radio:checked + .status-label.tidak-normal {
            background: #fee2e2;
            border-color: #ef4444;
            color: #dc2626;
        }

        .status-label:hover {
            background: #f1f5f9;
        }

        /* Action buttons */
        .btn-add-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: #f0f9fa;
            color: var(--brand-teal);
            border: 2px dashed #087F8A;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            font-size: 0.85rem;
            margin-top: 16px;
        }

        .btn-add-row:hover {
            background: var(--brand-teal);
            color: white;
            border-style: solid;
        }

        .btn-delete {
            background: none;
            border: none;
            color: #cbd5e1;
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .btn-delete:hover {
            background: #fee2e2;
            color: #ef4444;
        }

        /* Download button */
        /* Button bar - simple inline buttons without card */
        .button-bar {
            text-align: center;
            padding: 20px 0;
            margin-top: 10px;
        }

        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: linear-gradient(135deg, #087F8A 0%, #065C63 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            box-shadow: 0 2px 8px rgba(8, 127, 138, 0.25);
        }

        .btn-download:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(8, 127, 138, 0.35);
        }

        .btn-download i {
            font-size: 0.9rem;
        }

        /* Action button variants */
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            text-decoration: none;
            margin-right: 10px;
        }

        .btn-action:hover {
            transform: translateY(-1px);
        }

        .btn-action.btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }

        .btn-action.btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }

        .btn-action.btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }

        .btn-action.btn-secondary {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(100, 116, 139, 0.3);
        }

        /* Loading overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .loading-box {
            background: white;
            padding: 40px;
            border-radius: 16px;
            text-align: center;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e2e8f0;
            border-top-color: var(--brand-teal);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* View Mode (Read-only) Styles */
        .view-mode-banner {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .view-mode-banner i {
            font-size: 1.5rem;
            margin-right: 12px;
        }

        .view-mode-banner .banner-text {
            flex: 1;
        }

        .view-mode-banner .banner-text h3 {
            margin: 0 0 4px 0;
            font-size: 1rem;
        }

        .view-mode-banner .banner-text p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.85rem;
        }

        body.view-mode .table-input,
        body.view-mode .meta-item input,
        body.view-mode .meta-item select {
            background: #f1f5f9 !important;
            pointer-events: none;
            border: 1px solid #e2e8f0 !important;
            color: #475569;
        }

        body.view-mode .status-label {
            pointer-events: none;
        }

        body.view-mode .btn-add-row,
        body.view-mode .btn-delete-row,
        body.view-mode .btn-delete-circuit {
            display: none !important;
        }


        /* Responsive */
        @media (max-width: 768px) {
            .form-container {
                padding: 16px;
            }
            
            .header-meta {
                flex-direction: column;
                gap: 12px;
            }
            
            .meta-item {
                width: 100%;
            }
            
            .meta-item select {
                flex: 1;
            }
        }
</style>
</head>

<body class="<?= $view_mode ? 'view-mode' : '' ?>">
    <?php include 'includes/admin_sidebar.php'; ?>
    <main class="admin-main">

    <div class="admin-content" style="padding: 0 24px 24px 24px; max-width: 1400px; margin: 0 auto;">
        <?php if ($view_mode && $saved_report): ?>
        
        <div style="padding: 20px 0 10px 0;">
            <a href="admin_laporan_pengukuran.php" style="display: inline-flex; align-items: center; gap: 8px; color: #64748b; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: color 0.2s;">
                <i class="fas fa-arrow-left"></i> Kembali ke Laporan Pengukuran
            </a>
        </div>

        <!-- View Mode Banner -->
        <div class="view-mode-banner" style="margin-bottom: 16px; margin-top: 10px;">
            <i class="fas fa-check-circle"></i>
            <div class="banner-text">
                <h3>Laporan Tersimpan</h3>
                <p>Tanggal: <?= date('d', strtotime($saved_report['tanggal'])) ?> <?= $indo_months[intval(date('m', strtotime($saved_report['tanggal'])))] ?> <?= date('Y', strtotime($saved_report['tanggal'])) ?> | Oleh: <?= htmlspecialchars($saved_report['dibuat_oleh']) ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="form-container" style="padding: 0;">
            <!-- Header -->
            <div class="form-header">
                <?php if ($view_mode && $saved_report): ?>
                <h1><i class="fas fa-file-alt" style="margin-right: 10px;"></i>Detail Laporan Pengukuran</h1>
                <p>Menampilkan data laporan pengukuran yang tersimpan</p>
                <?php else: ?>
                <h1><i class="fas fa-file-excel" style="margin-right: 10px;"></i>Form Laporan Pengukuran</h1>
                <p>Isi data pengukuran untuk generate laporan Excel dengan 3 sheet</p>
                <?php endif; ?>
                
                <div class="header-meta">
                    <div class="meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <label>Tanggal:</label>
                        <input type="date" id="tanggalLaporan" value="<?= $today ?>">
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-user"></i>
                        <label>Dibuat Oleh:</label>
                        <select id="dibuatOleh">
                            <option value="">-- Pilih Personnel --</option>
                            <?php foreach ($personnel_list as $p): ?>
                                <option value="<?= htmlspecialchars($p['nama_personnel']) ?>" data-jabatan="<?= htmlspecialchars($p['jabatan']) ?>">
                                    <?= htmlspecialchars($p['nama_personnel']) ?> - <?= htmlspecialchars($p['jabatan']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 1: Form Tahanan Isolasi -->
            <div class="section-card" id="sectionTahananIsolasi">
                <div class="section-header" onclick="toggleSection('tahananIsolasi')">
                    <i class="fas fa-chevron-down chevron" id="chevronTahananIsolasi"></i>
                    <div class="section-icon"><i class="fas fa-bolt"></i></div>
                    <h2>FORM TAHANAN ISOLASI</h2>
                </div>
                <div class="section-body" id="bodyTahananIsolasi">
                    <div class="kriteria-box">
                        <h4><i class="fas fa-info-circle"></i> Kriteria</h4>
                        <ul class="kriteria-list">
                            <li>Panjang kabel &lt; 3.000 meter → <strong>Megaohm</strong></li>
                            <li>Panjang kabel 3.000 - 6.000 meter → <strong>Megaohm</strong></li>
                            <li>Panjang kabel &gt;6.000 meter → <strong>Megaohm</strong></li>
                        </ul>
                    </div>

                    <table class="data-table" id="tableTahananIsolasi">
                        <thead>
                            <tr>
                                <th rowspan="2" style="width: 50px;">NO</th>
                                <th rowspan="2" style="width: 30%;">ITEM</th>
                                <th rowspan="2" style="width: 100px;">SATUAN</th>
                                <th colspan="3">PENGUKURAN</th>
                                <th rowspan="2" style="width: 20%;">KETERANGAN</th>
                                <th rowspan="2" style="width: 40px;"></th>
                            </tr>
                            <tr>
                                <th class="sub-header" style="width: 100px;">HASIL</th>
                                <th class="sub-header" style="width: 80px;">NORMAL</th>
                                <th class="sub-header" style="width: 100px;">TIDAK NORMAL</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyTahananIsolasi">
                            <!-- Will be populated by JavaScript -->
                        </tbody>
                    </table>

                    <button type="button" class="btn-add-row" onclick="addCircuit('TahananIsolasi')">
                        <i class="fas fa-plus-circle"></i> Tambah Circuit Baru
                    </button>
                </div>
            </div>

            <!-- Section 2: Form Simulasi Genset -->
            <div class="section-card" id="sectionSimulasiGenset">
                <div class="section-header" onclick="toggleSection('simulasiGenset')">
                    <i class="fas fa-chevron-down chevron" id="chevronSimulasiGenset"></i>
                    <div class="section-icon"><i class="fas fa-car-battery"></i></div>
                    <h2>FORM SIMULASI GENSET</h2>
                </div>
                <div class="section-body" id="bodySimulasiGenset">
                    <table class="data-table" id="tableSimulasiGenset">
                        <thead>
                            <tr>
                                <th rowspan="2" style="width: 50px;">NO</th>
                                <th rowspan="2" style="width: 30%;">ITEM</th>
                                <th rowspan="2" style="width: 100px;">SATUAN</th>
                                <th colspan="3">PENGUKURAN</th>
                                <th rowspan="2" style="width: 20%;">KETERANGAN</th>
                                <th rowspan="2" style="width: 40px;"></th>
                            </tr>
                            <tr>
                                <th class="sub-header" style="width: 100px;">HASIL</th>
                                <th class="sub-header" style="width: 80px;">NORMAL</th>
                                <th class="sub-header" style="width: 100px;">TIDAK NORMAL</th>
                            </tr>
                        </thead>
                        <tbody id="tbodySimulasiGenset">
                            <!-- Will be populated by JavaScript -->
                        </tbody>
                    </table>

                    <button type="button" class="btn-add-row" onclick="addItemGenset()">
                        <i class="fas fa-plus-circle"></i> Tambah Item Baru
                    </button>
                </div>
            </div>

            <!-- Section 3: Form Simulasi UPS -->
            <div class="section-card" id="sectionSimulasiUPS">
                <div class="section-header" onclick="toggleSection('simulasiUPS')">
                    <i class="fas fa-chevron-down chevron" id="chevronSimulasiUPS"></i>
                    <div class="section-icon"><i class="fas fa-battery-full"></i></div>
                    <h2>FORM SIMULASI UPS</h2>
                </div>
                <div class="section-body" id="bodySimulasiUPS">
                    <table class="data-table" id="tableSimulasiUPS">
                        <thead>
                            <tr>
                                <th rowspan="2" style="width: 50px;">NO</th>
                                <th rowspan="2" style="width: 30%;">ITEM</th>
                                <th rowspan="2" style="width: 100px;">SATUAN</th>
                                <th colspan="3">PENGUKURAN</th>
                                <th rowspan="2" style="width: 20%;">KETERANGAN</th>
                                <th rowspan="2" style="width: 40px;"></th>
                            </tr>
                            <tr>
                                <th class="sub-header" style="width: 100px;">HASIL</th>
                                <th class="sub-header" style="width: 80px;">NORMAL</th>
                                <th class="sub-header" style="width: 100px;">TIDAK NORMAL</th>
                            </tr>
                        </thead>
                        <tbody id="tbodySimulasiUPS">
                            <!-- Will be populated by JavaScript -->
                        </tbody>
                    </table>

                    <button type="button" class="btn-add-row" onclick="addItemUPS()">
                        <i class="fas fa-plus-circle"></i> Tambah Item Baru
                    </button>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="button-bar">
                <?php if ($view_mode && $saved_report): ?>
                    <!-- VIEW MODE: Show Edit, Delete, Download buttons -->
                    <a href="form_laporan.php?id=<?= $saved_report['id'] ?>&edit=1" class="btn-action btn-warning">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    
                    <button type="button" class="btn-action btn-primary" onclick="downloadExcel()">
                        <i class="fas fa-file-download"></i> Download Excel
                    </button>
                    
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus laporan ini?');">
                        <input type="hidden" name="delete_report" value="<?= $saved_report['id'] ?>">
                        <button type="submit" class="btn-action btn-danger" style="margin-right: 0;">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    </form>
                <?php elseif ($edit_mode && $saved_report): ?>
                    <!-- EDIT MODE: Show Save Changes AND Download Excel buttons -->
                    <form method="POST" id="saveForm" style="display: inline;">
                        <input type="hidden" name="save_report" value="1">
                        <input type="hidden" name="tanggal" id="saveTanggal">
                        <input type="hidden" name="dibuat_oleh" id="saveDibuatOleh">
                        <input type="hidden" name="jabatan" id="saveJabatan">
                        <input type="hidden" name="tahanan_isolasi_json" id="saveTahananIsolasi">
                        <input type="hidden" name="simulasi_genset_json" id="saveSimulasiGenset">
                        <input type="hidden" name="simulasi_ups_json" id="saveSimulasiUps">
                        <input type="hidden" name="edit_id" value="<?= $saved_report['id'] ?>">
                        
                        <button type="button" class="btn-action btn-primary" onclick="saveReport()">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                    </form>
                    
                    <button type="button" class="btn-action btn-primary" onclick="downloadExcel()" style="background: linear-gradient(135deg, #087F8A 0%, #065C63 100%);">
                        <i class="fas fa-file-download"></i> Download Excel
                    </button>
                    
                    <a href="form_laporan.php?id=<?= $saved_report['id'] ?>" class="btn-action btn-secondary" style="margin-right: 0;">
                        <i class="fas fa-times"></i> Batal
                    </a>
                <?php else: ?>
                    <!-- NEW FORM MODE: Show only Save button (Download available after save) -->
                    <form method="POST" id="saveForm" style="display: inline;">
                        <input type="hidden" name="save_report" value="1">
                        <input type="hidden" name="tanggal" id="saveTanggal">
                        <input type="hidden" name="dibuat_oleh" id="saveDibuatOleh">
                        <input type="hidden" name="jabatan" id="saveJabatan">
                        <input type="hidden" name="tahanan_isolasi_json" id="saveTahananIsolasi">
                        <input type="hidden" name="simulasi_genset_json" id="saveSimulasiGenset">
                        <input type="hidden" name="simulasi_ups_json" id="saveSimulasiUps">
                        
                        <button type="button" class="btn-action btn-primary" onclick="saveReport()">
                            <i class="fas fa-save"></i> Simpan Laporan
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            

        </div>
    </div>
    <?php if ($_SESSION['role'] == 'admin'): ?>
    </main>
    <?php endif; ?>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-box">
            <div class="loading-spinner"></div>
            <p style="color: #1e293b; font-weight: 600;">Memproses Excel...</p>
        </div>
    </div>

    <script>
        // ========================================
        // DATA STRUCTURES (Exact from Template)
        // ========================================
        
        // Form Tahanan Isolasi - Circuits
        const defaultTahananIsolasi = [
            { 
                circuit: "Runway Light circuit 1", 
                items: [
                    { name: "Panjang kabel", satuan: "meter" },
                    { name: "Tahanan isolasi", satuan: "Megaohm" }
                ]
            },
            { 
                circuit: "Runway Light circuit 2", 
                items: [
                    { name: "Panjang kabel", satuan: "meter" },
                    { name: "Tahanan isolasi", satuan: "Megaohm" }
                ]
            },
            { 
                circuit: "Precision Approach Path Indicator circuit 1", 
                items: [
                    { name: "Panjang kabel", satuan: "meter" },
                    { name: "Tahanan isolasi", satuan: "Megaohm" }
                ]
            },
            { 
                circuit: "Precision Approach Path Indicator circuit 2", 
                items: [
                    { name: "Panjang kabel", satuan: "meter" },
                    { name: "Tahanan isolasi", satuan: "Megaohm" }
                ]
            },
            { 
                circuit: "Approach Light R/W …. circuit 1", 
                items: [
                    { name: "Panjang kabel", satuan: "meter" },
                    { name: "Tahanan isolasi", satuan: "Megaohm" }
                ]
            },
            { 
                circuit: "Approach Light R/W …. circuit 2", 
                items: [
                    { name: "Panjang kabel", satuan: "meter" },
                    { name: "Tahanan isolasi", satuan: "Megaohm" }
                ]
            }
        ];

        // Form Simulasi Genset - Exact structure from template
        const defaultSimulasiGenset = [
            { type: "category", name: "PARAMETER CHANGE OVER" },
            { type: "category", name: "CHANGE OVER PLN KE GENSET" },
            { type: "item", name: "ATS OPERATION", satuan: "auto/man" },
            { type: "item", name: "WAKTU TRANSFER PLN KE GENSET", satuan: "sec" },
            { type: "category", name: "PARAMETER PANEL SINKRON" },
            { type: "sub-item", name: "TEGANGAN", satuan: "V" },
            { type: "sub-item", name: "ARUS", satuan: "A" },
            { type: "sub-item", name: "FREKUENSI", satuan: "Hz" },
            { type: "sub-item", name: "POWER", satuan: "kW" },
            { type: "sub-item", name: "POWER FACTOR", satuan: "lag/lead" },
            { type: "category", name: "CHANGE OVER GENSET KE PLN" },
            { type: "item", name: "ATS OPERATION", satuan: "auto/man" },
            { type: "item", name: "WAKTU TRANSFER GENSET KE PLN", satuan: "sec" },
            { type: "category", name: "PARAMETER PANEL PLN" },
            { type: "sub-item", name: "TEGANGAN", satuan: "V" },
            { type: "sub-item", name: "ARUS", satuan: "A" },
            { type: "sub-item", name: "FREKUENSI", satuan: "Hz" },
            { type: "sub-item", name: "POWER", satuan: "kW" },
            { type: "sub-item", name: "POWER FACTOR", satuan: "lag/lead" },
            { type: "category", name: "PARAMETER GENSET" },
            { type: "item", name: "JML GENSET YANG BEROPERASI", satuan: "unit" },
            { type: "category", name: "PARAMETER PEMBEBANAN" },
            { type: "sub-item", name: "GENSET 500 KVA", satuan: "kW" },
            { type: "sub-item", name: "GENSET 1000 KVA", satuan: "kW" },
            { type: "sub-item", name: "DST…", satuan: "W" },
            { type: "category", name: "PERALATAN PENDUKUNG" },
            { type: "item", name: "PANEL KONTROL GENSET", satuan: "AVAILABLE" },
            { type: "item", name: "DAILY TANK", satuan: "Lt" },
            { type: "item", name: "MONTHLY TANK", satuan: "Lt" },
            { type: "item", name: "BATTERY CHARGER", satuan: "AVAILABLE" }
        ];

        // Form Simulasi UPS - Exact structure from template
        const defaultSimulasiUPS = [
            { type: "category", name: "PARAMETER INCOMING" },
            { type: "item", name: "TEGANGAN", satuan: "Volt" },
            { type: "item", name: "FREKUENSI", satuan: "Hz" },
            { type: "item", name: "POWER FACTOR", satuan: "lag/lead" },
            { type: "category", name: "PARAMETER OUTGOING" },
            { type: "item", name: "TEGANGAN", satuan: "Volt" },
            { type: "item", name: "ARUS", satuan: "Ampere" },
            { type: "item", name: "FREKUENSI", satuan: "Hz" },
            { type: "item", name: "POWER", satuan: "kW" },
            { type: "item", name: "POWER FACTOR", satuan: "lag/lead" },
            { type: "item", name: "KEMAMPUAN BACK UP BEBAN", satuan: "menit" },
            { type: "category", name: "PARAMETER BATTERY" },
            { type: "item", name: "TEGANGAN INPUT BATTERY", satuan: "Volt" },
            { type: "item", name: "KAPASITAS PER BATTERY", satuan: "Ampere Hour" },
            { type: "item", name: "TEGANGAN OUTPUT BATTERY", satuan: "Volt" },
            { type: "item", name: "BATTERY TEMPERATURE", satuan: "⁰C" },
            { type: "category", name: "RUANGAN" },
            { type: "item", name: "KEBERSIHAN RUANGAN", satuan: "CLEAR" },
            { type: "item", name: "SUHU RUANGAN", satuan: "⁰C" }
        ];

        let circuitCounter = 0;

        // ========================================
        // INITIALIZATION
        // ========================================
        
        document.addEventListener('DOMContentLoaded', function() {
            initializeTahananIsolasi();
            initializeSimulasiGenset();
            initializeSimulasiUPS();
        });

        function initializeTahananIsolasi() {
            const tbody = document.getElementById('tbodyTahananIsolasi');
            tbody.innerHTML = '';
            
            defaultTahananIsolasi.forEach((circuit, index) => {
                addCircuitToTable('TahananIsolasi', circuit.circuit, circuit.items, index + 1);
            });
            
            circuitCounter = defaultTahananIsolasi.length;
        }

        function initializeSimulasiGenset() {
            const tbody = document.getElementById('tbodySimulasiGenset');
            tbody.innerHTML = '';
            
            let itemNo = 0;
            defaultSimulasiGenset.forEach((item, index) => {
                if (item.type === 'category') {
                    addCategoryRow('SimulasiGenset', item.name);
                } else if (item.type === 'sub-item') {
                    addSubItemRow('SimulasiGenset', item.name, item.satuan);
                } else {
                    itemNo++;
                    addGensetItemRow('SimulasiGenset', item.name, item.satuan, itemNo);
                }
            });
        }

        function initializeSimulasiUPS() {
            const tbody = document.getElementById('tbodySimulasiUPS');
            tbody.innerHTML = '';
            
            let itemNo = 0;
            defaultSimulasiUPS.forEach((item, index) => {
                if (item.type === 'category') {
                    addCategoryRow('SimulasiUPS', item.name);
                } else {
                    itemNo++;
                    addUPSItemRow('SimulasiUPS', item.name, item.satuan, itemNo);
                }
            });
        }

        // ========================================
        // ADD ROWS - TAHANAN ISOLASI
        // ========================================

        function addCircuitToTable(section, circuitName, items, number) {
            const tbody = document.getElementById('tbody' + section);
            const circuitId = `circuit_${section}_${Date.now()}_${Math.random().toString(36).substr(2, 5)}`;
            
            // Circuit header row
            const circuitRow = document.createElement('tr');
            circuitRow.className = 'circuit-row';
            circuitRow.id = circuitId;
            circuitRow.innerHTML = `
                <td style="text-align: center; font-weight: 700;">${number}</td>
                <td colspan="6">
                    <input type="text" class="table-input circuit-name" value="${circuitName}" 
                           style="font-weight: 600; border: none; background: transparent; width: 100%;">
                </td>
                <td style="text-align: center;">
                    <button type="button" class="btn-delete" onclick="deleteCircuit('${circuitId}')" title="Hapus Circuit">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(circuitRow);

            // Item rows under this circuit - keterangan with rowspan (merged per circuit like Excel)
            const itemCount = items.length;
            items.forEach((item, idx) => {
                const itemRow = document.createElement('tr');
                itemRow.className = 'item-row sub-item';
                itemRow.dataset.circuitId = circuitId;
                const itemId = `item_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
                itemRow.id = itemId;
                
                // Keterangan only on first item row with rowspan to span all items in circuit
                const keteranganTd = idx === 0 
                    ? `<td rowspan="${itemCount}" style="vertical-align: middle;">
                        <input type="text" class="table-input keterangan" placeholder="Keterangan..." style="min-height: 80px; height: auto;">
                       </td>`
                    : ''; // Skip td for other items (rowspan covers them)
                
                itemRow.innerHTML = `
                    <td></td>
                    <td>
                        <input type="text" class="table-input item-name" value="${item.name}" style="border: none; background: transparent;">
                    </td>
                    <td style="text-align: center;">
                        <input type="text" class="table-input satuan" value="${item.satuan}" style="text-align: center; border: none; background: transparent; width: 80px;">
                    </td>
                    <td style="text-align: center;">
                        <input type="text" class="table-input number hasil" placeholder="-">
                    </td>
                    <td style="text-align: center;">
                        <div class="status-radio-group">
                            <input type="radio" name="status_${itemId}" value="NORMAL" class="status-radio" id="normal_${itemId}">
                            <label for="normal_${itemId}" class="status-label normal">✓</label>
                        </div>
                    </td>
                    <td style="text-align: center;">
                        <div class="status-radio-group">
                            <input type="radio" name="status_${itemId}" value="TIDAK NORMAL" class="status-radio" id="tidaknormal_${itemId}">
                            <label for="tidaknormal_${itemId}" class="status-label tidak-normal">✗</label>
                        </div>
                    </td>
                    ${keteranganTd}
                    <td></td>
                `;
                tbody.appendChild(itemRow);
            });
        }

        function addCircuit(section) {
            circuitCounter++;
            const newCircuit = {
                circuit: `Circuit Baru ${circuitCounter}`,
                items: [
                    { name: "Panjang kabel", satuan: "meter" },
                    { name: "Tahanan isolasi", satuan: "Megaohm" }
                ]
            };
            addCircuitToTable(section, newCircuit.circuit, newCircuit.items, circuitCounter);
        }

        // ========================================
        // ADD ROWS - GENSET & UPS
        // ========================================

        function addCategoryRow(section, categoryName) {
            const tbody = document.getElementById('tbody' + section);
            const catId = `cat_${Date.now()}_${Math.random().toString(36).substr(2, 5)}`;
            
            // Check if this is PARAMETER PEMBEBANAN category - add button to insert new rows
            const isParameterPembebanan = categoryName.includes('PARAMETER PEMBEBANAN');
            const addButton = isParameterPembebanan 
                ? `<button type="button" class="btn-add-inline" onclick="addPembebananRow('${section}', '${catId}')" title="Tambah Row" style="margin-left: 10px; background: #10b981; color: white; border: none; border-radius: 4px; padding: 2px 8px; font-size: 12px; cursor: pointer;">
                    <i class="fas fa-plus"></i> Tambah
                   </button>`
                : '';
            
            const row = document.createElement('tr');
            row.className = 'category-row';
            row.id = catId;
            row.innerHTML = `
                <td></td>
                <td colspan="6" style="font-weight: 700; color: #1e293b;">
                    ${categoryName} ${addButton}
                </td>
                <td></td>
            `;
            tbody.appendChild(row);
        }

        // Function to add new row under PARAMETER PEMBEBANAN
        function addPembebananRow(section, categoryId) {
            const tbody = document.getElementById('tbody' + section);
            const categoryRow = document.getElementById(categoryId);
            
            if (!categoryRow) return;
            
            // Find the next category row (PERALATAN PENDUKUNG) to insert before it
            let insertBefore = null;
            let foundCategory = false;
            const rows = tbody.querySelectorAll('tr');
            
            for (let row of rows) {
                if (row.id === categoryId) {
                    foundCategory = true;
                    continue;
                }
                if (foundCategory && row.classList.contains('category-row')) {
                    insertBefore = row;
                    break;
                }
            }
            
            // Create new sub-item row
            const itemId = `item_pembebanan_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
            const newRow = document.createElement('tr');
            newRow.className = 'item-row sub-item';
            newRow.id = itemId;
            newRow.innerHTML = `
                <td></td>
                <td style="padding-left: 30px;">
                    <span style="color: #64748b;">-</span>&nbsp;&nbsp;
                    <input type="text" class="table-input item-name" value="GENSET BARU" style="width: calc(100% - 30px); border: none; background: transparent;">
                </td>
                <td style="text-align: center;">
                    <input type="text" class="table-input satuan" value="kW" style="text-align: center; border: none; background: transparent; width: 80px;">
                </td>
                <td style="text-align: center;">
                    <input type="text" class="table-input number hasil" placeholder="-">
                </td>
                <td style="text-align: center;">
                    <div class="status-radio-group">
                        <input type="radio" name="status_${itemId}" value="NORMAL" class="status-radio" id="normal_${itemId}">
                        <label for="normal_${itemId}" class="status-label normal">✓</label>
                    </div>
                </td>
                <td style="text-align: center;">
                    <div class="status-radio-group">
                        <input type="radio" name="status_${itemId}" value="TIDAK NORMAL" class="status-radio" id="tidaknormal_${itemId}">
                        <label for="tidaknormal_${itemId}" class="status-label tidak-normal">✗</label>
                    </div>
                </td>
                <td>
                    <input type="text" class="table-input keterangan" placeholder="Keterangan...">
                </td>
                <td style="text-align: center;">
                    <button type="button" class="btn-delete" onclick="deleteItem('${itemId}')" title="Hapus">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            
            if (insertBefore) {
                tbody.insertBefore(newRow, insertBefore);
            } else {
                tbody.appendChild(newRow);
            }
        }

        function addSubItemRow(section, itemName, satuan) {
            const tbody = document.getElementById('tbody' + section);
            const itemId = `item_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
            
            const row = document.createElement('tr');
            row.className = 'item-row sub-item';
            row.id = itemId;
            row.innerHTML = `
                <td></td>
                <td style="padding-left: 30px;">
                    <span style="color: #64748b;">-</span>&nbsp;&nbsp;
                    <input type="text" class="table-input item-name" value="${itemName}" style="width: calc(100% - 30px); border: none; background: transparent;">
                </td>
                <td style="text-align: center;">
                    <input type="text" class="table-input satuan" value="${satuan}" style="text-align: center; border: none; background: transparent; width: 80px;">
                </td>
                <td style="text-align: center;">
                    <input type="text" class="table-input number hasil" placeholder="-">
                </td>
                <td style="text-align: center;">
                    <div class="status-radio-group">
                        <input type="radio" name="status_${itemId}" value="NORMAL" class="status-radio" id="normal_${itemId}">
                        <label for="normal_${itemId}" class="status-label normal">✓</label>
                    </div>
                </td>
                <td style="text-align: center;">
                    <div class="status-radio-group">
                        <input type="radio" name="status_${itemId}" value="TIDAK NORMAL" class="status-radio" id="tidaknormal_${itemId}">
                        <label for="tidaknormal_${itemId}" class="status-label tidak-normal">✗</label>
                    </div>
                </td>
                <td>
                    <input type="text" class="table-input keterangan" placeholder="Keterangan...">
                </td>
                <td style="text-align: center;">
                    <button type="button" class="btn-delete" onclick="deleteItem('${itemId}')" title="Hapus">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        }

        function addGensetItemRow(section, itemName, satuan, number) {
            const tbody = document.getElementById('tbody' + section);
            const itemId = `item_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
            
            const row = document.createElement('tr');
            row.className = 'item-row';
            row.id = itemId;
            row.innerHTML = `
                <td style="text-align: center;">${number || ''}</td>
                <td>
                    <input type="text" class="table-input item-name" value="${itemName}" style="border: none; background: transparent;">
                </td>
                <td style="text-align: center;">
                    <input type="text" class="table-input satuan" value="${satuan}" style="text-align: center; border: none; background: transparent; width: 80px;">
                </td>
                <td style="text-align: center;">
                    <input type="text" class="table-input number hasil" placeholder="-">
                </td>
                <td style="text-align: center;">
                    <div class="status-radio-group">
                        <input type="radio" name="status_${itemId}" value="NORMAL" class="status-radio" id="normal_${itemId}">
                        <label for="normal_${itemId}" class="status-label normal">✓</label>
                    </div>
                </td>
                <td style="text-align: center;">
                    <div class="status-radio-group">
                        <input type="radio" name="status_${itemId}" value="TIDAK NORMAL" class="status-radio" id="tidaknormal_${itemId}">
                        <label for="tidaknormal_${itemId}" class="status-label tidak-normal">✗</label>
                    </div>
                </td>
                <td>
                    <input type="text" class="table-input keterangan" placeholder="Keterangan...">
                </td>
                <td style="text-align: center;">
                    <button type="button" class="btn-delete" onclick="deleteItem('${itemId}')" title="Hapus">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        }

        function addUPSItemRow(section, itemName, satuan, number) {
            addGensetItemRow(section, itemName, satuan, number);
        }

        function addItemGenset() {
            addGensetItemRow('SimulasiGenset', 'Item Baru', 'Unit', '');
        }

        function addItemUPS() {
            addGensetItemRow('SimulasiUPS', 'Item Baru', 'Unit', '');
        }

        // ========================================
        // DELETE FUNCTIONS
        // ========================================

        function deleteCircuit(circuitId) {
            if (!confirm('Hapus circuit ini beserta semua itemnya?')) return;
            
            const circuitRow = document.getElementById(circuitId);
            if (circuitRow) circuitRow.remove();
            
            document.querySelectorAll(`tr[data-circuit-id="${circuitId}"]`).forEach(row => row.remove());
            renumberCircuits();
        }

        function deleteItem(itemId) {
            if (!confirm('Hapus item ini?')) return;
            
            const itemRow = document.getElementById(itemId);
            if (itemRow) itemRow.remove();
        }

        function renumberCircuits() {
            const tbody = document.getElementById('tbodyTahananIsolasi');
            let num = 0;
            tbody.querySelectorAll('tr.circuit-row').forEach(row => {
                num++;
                row.querySelector('td').textContent = num;
            });
            circuitCounter = num;
        }

        // ========================================
        // SECTION TOGGLE
        // ========================================

        function toggleSection(section) {
            const sectionMap = {
                'tahananIsolasi': 'TahananIsolasi',
                'simulasiGenset': 'SimulasiGenset',
                'simulasiUPS': 'SimulasiUPS'
            };
            const key = sectionMap[section];
            const body = document.getElementById('body' + key);
            const chevron = document.getElementById('chevron' + key);
            const header = chevron.closest('.section-header');
            
            if (body.classList.contains('collapsed')) {
                body.classList.remove('collapsed');
                header.classList.remove('collapsed');
            } else {
                body.classList.add('collapsed');
                header.classList.add('collapsed');
            }
        }

        // ========================================
        // EXCEL EXPORT (Using PhpSpreadsheet via PHP backend)
        // ========================================

        async function downloadExcel() {
            const tanggal = document.getElementById('tanggalLaporan').value;
            const selectEl = document.getElementById('dibuatOleh');
            const dibuatOleh = selectEl.value;
            const selectedOption = selectEl.options[selectEl.selectedIndex];
            const jabatan = selectedOption ? selectedOption.getAttribute('data-jabatan') || '' : '';
            
            if (!dibuatOleh) {
                alert('Silakan pilih personnel terlebih dahulu!');
                return;
            }

            document.getElementById('loadingOverlay').style.display = 'flex';

            try {
                // Collect all form data
                const tahananIsolasiData = collectTahananIsolasiData();
                const simulasiGensetData = collectSimulasiGensetData();
                const simulasiUPSData = collectSimulasiUPSData();

                const payload = {
                    tanggal: tanggal,
                    dibuatOleh: dibuatOleh,
                    jabatan: jabatan,
                    tahananIsolasi: tahananIsolasiData,
                    simulasiGenset: simulasiGensetData,
                    simulasiUPS: simulasiUPSData
                };

                // Send to PHP backend
                const response = await fetch('export_laporan.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.error || 'Export failed');
                }

                // Download the file
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Laporan_Pengukuran_${tanggal}.xlsx`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();

                alert('File berhasil didownload!');

            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan: ' + error.message);
            } finally {
                document.getElementById('loadingOverlay').style.display = 'none';
            }
        }

        // Collect data from Tahanan Isolasi form - includes circuits with NO, item names, satuan
        function collectTahananIsolasiData() {
            const data = [];
            const tbody = document.getElementById('tbodyTahananIsolasi');
            const allRows = tbody.querySelectorAll('tr');
            
            let currentCircuit = null;
            
            allRows.forEach(row => {
                if (row.classList.contains('circuit-row')) {
                    // Circuit header row
                    const no = row.querySelector('td')?.textContent?.trim() || '';
                    const circuitName = row.querySelector('.circuit-name')?.value || '';
                    currentCircuit = {
                        type: 'circuit',
                        no: no,
                        name: circuitName,
                        items: []
                    };
                    data.push(currentCircuit);
                } else if (row.classList.contains('item-row')) {
                    // Item row under circuit
                    const itemName = row.querySelector('.item-name')?.value || '';
                    const satuan = row.querySelector('.satuan-text')?.textContent?.trim() || 
                                   row.querySelector('.satuan')?.value || '';
                    const hasil = row.querySelector('.hasil')?.value || '';
                    const checkedRadio = row.querySelector('.status-radio:checked');
                    const status = checkedRadio ? checkedRadio.value : '';
                    const keterangan = row.querySelector('.keterangan')?.value || '';
                    
                    const item = { itemName, satuan, hasil, status, keterangan };
                    
                    if (currentCircuit) {
                        currentCircuit.items.push(item);
                    }
                }
            });
            
            return data;
        }

        // Collect data from Simulasi Genset form
        function collectSimulasiGensetData() {
            const data = [];
            const tbody = document.getElementById('tbodySimulasiGenset');
            const itemRows = tbody.querySelectorAll('tr.item-row');
            
            itemRows.forEach(row => {
                // Check if this is an extra row
                const isExtra = row.id && row.id.includes('item_pembebanan');
                
                const itemName = row.querySelector('.item-name')?.value || '';
                const satuan = row.querySelector('.satuan')?.value || '';
                const hasil = row.querySelector('.hasil')?.value || '';
                const checkedRadio = row.querySelector('.status-radio:checked');
                const status = checkedRadio ? checkedRadio.value : '';
                const keterangan = row.querySelector('.keterangan')?.value || '';
                
                data.push({ 
                    itemName, 
                    satuan, 
                    hasil, 
                    status, 
                    keterangan,
                    isExtra: isExtra  // Add isExtra flag for Excel export
                });
            });
            
            return data;
        }

        // Collect data from Simulasi UPS form
        function collectSimulasiUPSData() {
            const data = [];
            const tbody = document.getElementById('tbodySimulasiUPS');
            const itemRows = tbody.querySelectorAll('tr.item-row');
            
            itemRows.forEach(row => {
                const itemName = row.querySelector('.item-name')?.value || '';
                const satuan = row.querySelector('.satuan')?.value || '';
                const hasil = row.querySelector('.hasil')?.value || '';
                const checkedRadio = row.querySelector('.status-radio:checked');
                const status = checkedRadio ? checkedRadio.value : '';
                const keterangan = row.querySelector('.keterangan')?.value || '';
                
                data.push({ itemName, satuan, hasil, status, keterangan });
            });
            
            return data;
        }

        // Helper: Fill date cell in a sheet (Row 7, around column F)
        function fillDate(sheet, formattedDate) {
            // Try common positions for date - adjust based on your template
            // Template shows "TANGGAL : 21 November 2025" - date value around F7 or G7
            sheet['F7'] = { t: 's', v: formattedDate };
        }

        // Helper: Fill "Dibuat" personnel name in signature section
        function fillDibuat(sheet, dibuatOleh, signatureRow) {
            // Personnel name goes in the "Dibuat" section - around column N, row ~50
            // The exact row may vary per sheet
            const cell = XLSX.utils.encode_cell({r: signatureRow - 1, c: 13}); // Column N = index 13
            sheet[cell] = { t: 's', v: dibuatOleh };
        }

        function fillSheet1_TahananIsolasi(workbook, formattedDate, dibuatOleh) {
            const sheetName = workbook.SheetNames[0];
            const sheet = workbook.Sheets[sheetName];
            
            // Fill date and personnel name
            fillDate(sheet, formattedDate);
            fillDibuat(sheet, dibuatOleh, 50); // Adjust row number based on template
            
            // Get only item rows (not circuit headers), these are the rows with input fields
            const tbody = document.getElementById('tbodyTahananIsolasi');
            const itemRows = tbody.querySelectorAll('tr.item-row');
            
            // Starting Excel row for data items (based on template)
            // Row 20 = first item (Panjang kabel under circuit 1)
            // Row 21 = second item (Tahanan isolasi under circuit 1)
            // Row 23 = first item (Panjang kabel under circuit 2) - skip circuit header row
            // etc.
            
            // Based on template structure:
            // Row 19: Circuit 1 header
            // Row 20: Panjang kabel (first data row)
            // Row 21: Tahanan isolasi
            // Row 22: Circuit 2 header
            // Row 23: Panjang kabel
            // Row 24: Tahanan isolasi
            // ... pattern: header, item, item, header, item, item...
            
            let circuitIndex = 0;
            let itemInCircuit = 0;
            let excelRow = 20; // First data row in Excel
            
            itemRows.forEach((row, index) => {
                const hasil = row.querySelector('.hasil')?.value || '';
                const checkedRadio = row.querySelector('.status-radio:checked');
                const status = checkedRadio ? checkedRadio.value : '';
                const keterangan = row.querySelector('.keterangan')?.value || '';
                
                // Only write if there's a value
                if (hasil) {
                    // Column L (index 11) = HASIL
                    sheet[XLSX.utils.encode_cell({r: excelRow - 1, c: 11})] = { t: 's', v: hasil };
                }
                if (status === 'NORMAL') {
                    // Column M (index 12) = NORMAL
                    sheet[XLSX.utils.encode_cell({r: excelRow - 1, c: 12})] = { t: 's', v: 'NORMAL' };
                } else if (status === 'TIDAK NORMAL') {
                    // Column N (index 13) = TIDAK NORMAL  
                    sheet[XLSX.utils.encode_cell({r: excelRow - 1, c: 13})] = { t: 's', v: 'TIDAK NORMAL' };
                }
                if (keterangan) {
                    // Column O (index 14) = KETERANGAN
                    sheet[XLSX.utils.encode_cell({r: excelRow - 1, c: 14})] = { t: 's', v: keterangan };
                }
                
                itemInCircuit++;
                excelRow++;
                
                // After 2 items (Panjang kabel & Tahanan isolasi), skip the circuit header row
                if (itemInCircuit >= 2) {
                    itemInCircuit = 0;
                    circuitIndex++;
                    excelRow++; // Skip circuit header row
                }
            });
        }

        function fillSheet2_SimulasiGenset(workbook, formattedDate, dibuatOleh) {
            if (workbook.SheetNames.length < 2) return;
            
            const sheetName = workbook.SheetNames[1];
            const sheet = workbook.Sheets[sheetName];
            
            // Fill date and personnel name
            fillDate(sheet, formattedDate);
            fillDibuat(sheet, dibuatOleh, 50); // Adjust row number based on template
            
            const tbody = document.getElementById('tbodySimulasiGenset');
            const itemRows = tbody.querySelectorAll('tr.item-row');
            
            // Get the existing rows in template and match them
            // For now, we'll use a simple sequential approach
            // Adjust starting row based on your template
            let excelRow = 15; // Adjust this based on template
            
            itemRows.forEach(row => {
                const hasil = row.querySelector('.hasil')?.value || '';
                const checkedRadio = row.querySelector('.status-radio:checked');
                const status = checkedRadio ? checkedRadio.value : '';
                const keterangan = row.querySelector('.keterangan')?.value || '';
                
                if (hasil) {
                    sheet[XLSX.utils.encode_cell({r: excelRow - 1, c: 11})] = { t: 's', v: hasil };
                }
                if (status === 'NORMAL') {
                    sheet[XLSX.utils.encode_cell({r: excelRow - 1, c: 12})] = { t: 's', v: 'NORMAL' };
                } else if (status === 'TIDAK NORMAL') {
                    sheet[XLSX.utils.encode_cell({r: excelRow - 1, c: 13})] = { t: 's', v: 'TIDAK NORMAL' };
                }
                if (keterangan) {
                    sheet[XLSX.utils.encode_cell({r: excelRow - 1, c: 14})] = { t: 's', v: keterangan };
                }
                excelRow++;
            });
        }

        function fillSheet3_SimulasiUPS(workbook, formattedDate, dibuatOleh) {
            if (workbook.SheetNames.length < 3) return;
            
            const sheetName = workbook.SheetNames[2];
            const sheet = workbook.Sheets[sheetName];
            
            // Fill date and personnel name
            fillDate(sheet, formattedDate);
            fillDibuat(sheet, dibuatOleh, 50); // Based on screenshot, row 50 has name
            
            const tbody = document.getElementById('tbodySimulasiUPS');
            const itemRows = tbody.querySelectorAll('tr.item-row');
            
            let excelRow = 15; // Adjust based on template
            
            itemRows.forEach(row => {
                const hasil = row.querySelector('.hasil')?.value || '';
                const checkedRadio = row.querySelector('.status-radio:checked');
                const status = checkedRadio ? checkedRadio.value : '';
                const keterangan = row.querySelector('.keterangan')?.value || '';
                
                if (hasil) {
                    sheet[XLSX.utils.encode_cell({r: excelRow - 1, c: 11})] = { t: 's', v: hasil };
                }
                if (status === 'NORMAL') {
                    sheet[XLSX.utils.encode_cell({r: excelRow - 1, c: 12})] = { t: 's', v: 'NORMAL' };
                } else if (status === 'TIDAK NORMAL') {
                    sheet[XLSX.utils.encode_cell({r: excelRow - 1, c: 13})] = { t: 's', v: 'TIDAK NORMAL' };
                }
                if (keterangan) {
                    sheet[XLSX.utils.encode_cell({r: excelRow - 1, c: 14})] = { t: 's', v: keterangan };
                }
                excelRow++;
            });
        }

        // ========================================
        // SAVE REPORT FUNCTION
        // ========================================
        function saveReport() {
            // Validate
            const tanggal = document.getElementById('tanggalLaporan').value;
            const dibuatOleh = document.getElementById('dibuatOleh').value;
            const jabatan = <?= json_encode($personnel_list) ?>.find(p => p.nama_personnel === dibuatOleh)?.jabatan || '';
            
            if (!tanggal) {
                alert('Mohon isi tanggal terlebih dahulu!');
                return;
            }
            if (!dibuatOleh) {
                alert('Mohon pilih/isi nama personel terlebih dahulu!');
                return;
            }
            
            // Collect Tahanan Isolasi data
            const tahananIsolasiData = [];
            const tbody = document.getElementById('tbodyTahananIsolasi');
            const circuitRows = tbody.querySelectorAll('.circuit-row');
            
            circuitRows.forEach(circuitRow => {
                const circuitId = circuitRow.id;
                const circuitName = circuitRow.querySelector('.circuit-name')?.value || '';
                const items = [];
                
                // Get all item-rows that belong to this circuit
                const itemRows = tbody.querySelectorAll(`tr.item-row[data-circuit-id="${circuitId}"]`);
                itemRows.forEach(row => {
                    // Get keterangan - it may be in first row with rowspan
                    let keterangan = row.querySelector('.keterangan')?.value || '';
                    // If not found, check first row of this circuit group
                    if (!keterangan) {
                        const firstItemRow = tbody.querySelector(`tr.item-row[data-circuit-id="${circuitId}"]`);
                        if (firstItemRow) {
                            keterangan = firstItemRow.querySelector('.keterangan')?.value || '';
                        }
                    }
                    
                    items.push({
                        itemName: row.querySelector('.item-name')?.value || '',
                        satuan: row.querySelector('.satuan')?.value || '',
                        hasil: row.querySelector('.hasil')?.value || '',
                        status: row.querySelector('.status-radio:checked')?.value || '',
                        keterangan: keterangan
                    });
                });
                tahananIsolasiData.push({ name: circuitName, items: items });
            });
            
            // Collect Simulasi Genset data - save itemName/satuan for ALL rows
            const simulasiGensetData = [];
            const gensetRows = document.querySelectorAll('#tbodySimulasiGenset .item-row');
            gensetRows.forEach((row, idx) => {
                // Check if this is an extra row (added dynamically, has id starting with item_pembebanan)
                const isExtra = row.id && row.id.includes('item_pembebanan');
                
                simulasiGensetData.push({
                    hasil: row.querySelector('.hasil')?.value || '',
                    status: row.querySelector('.status-radio:checked')?.value || '',
                    keterangan: row.querySelector('.keterangan')?.value || '',
                    // Save itemName and satuan for ALL rows (so edits are preserved)
                    isExtra: isExtra,
                    itemName: row.querySelector('.item-name')?.value || '',
                    satuan: row.querySelector('.satuan')?.value || ''
                });
            });
            
            // Collect Simulasi UPS data - save itemName/satuan for ALL rows
            const simulasiUPSData = [];
            const upsRows = document.querySelectorAll('#tbodySimulasiUPS .item-row');
            upsRows.forEach(row => {
                simulasiUPSData.push({
                    itemName: row.querySelector('.item-name')?.value || '',
                    satuan: row.querySelector('.satuan')?.value || '',
                    hasil: row.querySelector('.hasil')?.value || '',
                    status: row.querySelector('.status-radio:checked')?.value || '',
                    keterangan: row.querySelector('.keterangan')?.value || ''
                });
            });
            
            // Set form values
            document.getElementById('saveTanggal').value = tanggal;
            document.getElementById('saveDibuatOleh').value = dibuatOleh;
            document.getElementById('saveJabatan').value = jabatan;
            document.getElementById('saveTahananIsolasi').value = JSON.stringify(tahananIsolasiData);
            document.getElementById('saveSimulasiGenset').value = JSON.stringify(simulasiGensetData);
            document.getElementById('saveSimulasiUps').value = JSON.stringify(simulasiUPSData);
            
            // Submit form
            document.getElementById('saveForm').submit();
        }

        // ========================================
        // LOAD SAVED DATA (if exists)
        // ========================================
        <?php if ($saved_report): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Set date and personnel
            document.getElementById('tanggalLaporan').value = '<?= $saved_report['tanggal'] ?>';
            document.getElementById('dibuatOleh').value = '<?= htmlspecialchars($saved_report['dibuat_oleh']) ?>';
            
            // Load Tahanan Isolasi data - REPLACE default data with saved data
            const savedTahananIsolasi = <?= $saved_report['tahanan_isolasi_data'] ?: '[]' ?>;
            if (savedTahananIsolasi.length > 0) {
                // Clear existing table and re-render with saved data
                const tbody = document.getElementById('tbodyTahananIsolasi');
                tbody.innerHTML = '';
                
                savedTahananIsolasi.forEach((circuit, index) => {
                    const circuitId = `circuit_TahananIsolasi_saved_${index}`;
                    
                    // Circuit header row
                    const circuitRow = document.createElement('tr');
                    circuitRow.className = 'circuit-row';
                    circuitRow.id = circuitId;
                    circuitRow.innerHTML = `
                        <td style="text-align: center; font-weight: 700;">${index + 1}</td>
                        <td colspan="6">
                            <input type="text" class="table-input circuit-name" value="${circuit.name || ''}" 
                                   style="font-weight: 600; border: none; background: transparent; width: 100%;">
                        </td>
                        <td style="text-align: center;">
                            <button type="button" class="btn-delete" onclick="deleteCircuit('${circuitId}')" title="Hapus Circuit">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(circuitRow);
                    
                    // Item rows
                    const items = circuit.items || [];
                    const itemCount = items.length;
                    items.forEach((item, idx) => {
                        const itemRow = document.createElement('tr');
                        itemRow.className = 'item-row sub-item';
                        itemRow.dataset.circuitId = circuitId;
                        const itemId = `item_saved_${index}_${idx}`;
                        itemRow.id = itemId;
                        
                        // Keterangan only on first item row
                        const keteranganTd = idx === 0 
                            ? `<td rowspan="${itemCount}" style="vertical-align: middle;">
                                <input type="text" class="table-input keterangan" value="${item.keterangan || ''}" placeholder="Keterangan..." style="min-height: 80px; height: auto;">
                               </td>`
                            : '';
                        
                        // Determine which radio is checked
                        const normalChecked = item.status === 'NORMAL' ? 'checked' : '';
                        const tidakNormalChecked = item.status === 'TIDAK NORMAL' ? 'checked' : '';
                        
                        itemRow.innerHTML = `
                            <td></td>
                            <td>
                                <input type="text" class="table-input item-name" value="${item.itemName || item.name || ''}" style="border: none; background: transparent;">
                            </td>
                            <td style="text-align: center;">
                                <input type="text" class="table-input satuan" value="${item.satuan || ''}" style="text-align: center; border: none; background: transparent; width: 80px;">
                            </td>
                            <td style="text-align: center;">
                                <input type="text" class="table-input number hasil" value="${item.hasil || ''}" placeholder="-">
                            </td>
                            <td style="text-align: center;">
                                <div class="status-radio-group">
                                    <input type="radio" name="status_${itemId}" value="NORMAL" class="status-radio" id="normal_${itemId}" ${normalChecked}>
                                    <label for="normal_${itemId}" class="status-label normal">✓</label>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <div class="status-radio-group">
                                    <input type="radio" name="status_${itemId}" value="TIDAK NORMAL" class="status-radio" id="tidaknormal_${itemId}" ${tidakNormalChecked}>
                                    <label for="tidaknormal_${itemId}" class="status-label tidak-normal">✗</label>
                                </div>
                            </td>
                            ${keteranganTd}
                            <td></td>
                        `;
                        tbody.appendChild(itemRow);
                    });
                });
                
                circuitCounter = savedTahananIsolasi.length;
            }
            
            // Load Simulasi Genset data - handle extra rows added to PEMBEBANAN
            const savedSimulasiGenset = <?= $saved_report['simulasi_genset_data'] ?: '[]' ?>;
            if (savedSimulasiGenset.length > 0) {
                setTimeout(() => {
                    const tbody = document.getElementById('tbodySimulasiGenset');
                    const gensetRows = tbody.querySelectorAll('.item-row');
                    let templateRowIdx = 0;
                    
                    savedSimulasiGenset.forEach((item, idx) => {
                        if (item.isExtra) {
                            // This is an EXTRA row - find PERALATAN PENDUKUNG and insert before it
                            const allRows = tbody.querySelectorAll('tr');
                            let insertBefore = null;
                            for (let row of allRows) {
                                if (row.classList.contains('category-row') && row.textContent.includes('PERALATAN PENDUKUNG')) {
                                    insertBefore = row;
                                    break;
                                }
                            }
                            
                            // Create new row with same format as DST...
                            const itemId = `item_pembebanan_${Date.now()}_${idx}`;
                            const newRow = document.createElement('tr');
                            newRow.className = 'item-row sub-item';
                            newRow.id = itemId;
                            
                            const normalChecked = item.status === 'NORMAL' ? 'checked' : '';
                            const tidakNormalChecked = item.status === 'TIDAK NORMAL' ? 'checked' : '';
                            
                            newRow.innerHTML = `
                                <td></td>
                                <td style="padding-left: 30px;">
                                    <span style="color: #64748b;">-</span>&nbsp;&nbsp;
                                    <input type="text" class="table-input item-name" value="${item.itemName || ''}" style="width: calc(100% - 30px); border: none; background: transparent;">
                                </td>
                                <td style="text-align: center;">
                                    <input type="text" class="table-input satuan" value="${item.satuan || 'kW'}" style="text-align: center; border: none; background: transparent; width: 80px;">
                                </td>
                                <td style="text-align: center;">
                                    <input type="text" class="table-input number hasil" value="${item.hasil || ''}" placeholder="-">
                                </td>
                                <td style="text-align: center;">
                                    <div class="status-radio-group">
                                        <input type="radio" name="status_${itemId}" value="NORMAL" class="status-radio" id="normal_${itemId}" ${normalChecked}>
                                        <label for="normal_${itemId}" class="status-label normal">✓</label>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <div class="status-radio-group">
                                        <input type="radio" name="status_${itemId}" value="TIDAK NORMAL" class="status-radio" id="tidaknormal_${itemId}" ${tidakNormalChecked}>
                                        <label for="tidaknormal_${itemId}" class="status-label tidak-normal">✗</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" class="table-input keterangan" value="${item.keterangan || ''}" placeholder="Keterangan...">
                                </td>
                                <td style="text-align: center;">
                                    <button type="button" class="btn-delete" onclick="deleteItem('${itemId}')" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            `;
                            
                            if (insertBefore) {
                                tbody.insertBefore(newRow, insertBefore);
                            } else {
                                tbody.appendChild(newRow);
                            }
                        } else {
                            // Normal template row - fill values including edited itemName/satuan
                            if (gensetRows[templateRowIdx]) {
                                const row = gensetRows[templateRowIdx];
                                // Restore edited itemName and satuan
                                if (item.itemName) {
                                    const itemNameInput = row.querySelector('.item-name');
                                    if (itemNameInput) itemNameInput.value = item.itemName;
                                }
                                if (item.satuan) {
                                    const satuanInput = row.querySelector('.satuan');
                                    if (satuanInput) satuanInput.value = item.satuan;
                                }
                                // Restore measurement data
                                if (item.hasil) row.querySelector('.hasil').value = item.hasil;
                                if (item.status) {
                                    const radio = row.querySelector(`.status-radio[value="${item.status}"]`);
                                    if (radio) radio.checked = true;
                                }
                                if (item.keterangan) row.querySelector('.keterangan').value = item.keterangan;
                            }
                            templateRowIdx++;
                        }
                    });
                }, 100);
            }
            
            // Load Simulasi UPS data - restore edited itemName/satuan
            const savedSimulasiUPS = <?= $saved_report['simulasi_ups_data'] ?: '[]' ?>;
            if (savedSimulasiUPS.length > 0) {
                setTimeout(() => {
                    const upsRows = document.querySelectorAll('#tbodySimulasiUPS .item-row');
                    savedSimulasiUPS.forEach((item, idx) => {
                        if (upsRows[idx]) {
                            const row = upsRows[idx];
                            // Restore edited itemName and satuan
                            if (item.itemName) {
                                const itemNameInput = row.querySelector('.item-name');
                                if (itemNameInput) itemNameInput.value = item.itemName;
                            }
                            if (item.satuan) {
                                const satuanInput = row.querySelector('.satuan');
                                if (satuanInput) satuanInput.value = item.satuan;
                            }
                            // Restore measurement data
                            if (item.hasil) row.querySelector('.hasil').value = item.hasil;
                            if (item.status) {
                                const radio = row.querySelector(`.status-radio[value="${item.status}"]`);
                                if (radio) radio.checked = true;
                            }
                            if (item.keterangan) row.querySelector('.keterangan').value = item.keterangan;
                        }
                    });
                }, 100);
            }
        });
        <?php endif; ?>

        <?php if (isset($_GET['saved'])): ?>
        // Show success message
        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.style.display = 'flex';
            overlay.innerHTML = `
                <div class="loading-box" style="text-align: center;">
                    <div style="width: 60px; height: 60px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                        <i class="fas fa-check" style="color: white; font-size: 28px;"></i>
                    </div>
                    <p style="color: #1e293b; font-weight: 600; font-size: 1.1rem; margin: 0 0 8px 0;">Laporan Berhasil Disimpan!</p>
                    <p style="color: #64748b; font-size: 0.9rem; margin: 0;">Data akan ditampilkan di halaman admin.</p>
                </div>
            `;
            document.body.appendChild(overlay);
            
            setTimeout(() => {
                overlay.style.opacity = '0';
                overlay.style.transition = '0.3s';
                setTimeout(() => overlay.remove(), 300);
            }, 2000);
        });
        <?php endif; ?>
    </script>
</body>

</html>
