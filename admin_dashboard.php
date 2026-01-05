<?php
// Admin Dashboard - Equipment Monitoring System
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// If not admin, redirect to user dashboard
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Get user info from session
$nama_lengkap = $_SESSION['nama_lengkap'] ?? 'Administrator';

// Database connection
require_once 'config/database.php';

$today = date('Y-m-d');

// Get total equipment count
$total_equipment = $conn->query("SELECT COUNT(*) as total FROM equipments")->fetch_assoc()['total'];

// Get total personnel count
$total_personnel = $conn->query("SELECT COUNT(*) as total FROM personnel")->fetch_assoc()['total'];

// Get total locations count
$total_lokasi = $conn->query("SELECT COUNT(*) as total FROM lokasi")->fetch_assoc()['total'];

// Get today's inspections
$total_checked = $conn->query("SELECT COUNT(DISTINCT equipment_id) as total FROM inspections_daily WHERE tanggal = '$today'")->fetch_assoc()['total'];

// Get status breakdown for today
$status_normal = $conn->query("SELECT COUNT(*) as total FROM inspections_daily WHERE tanggal = '$today' AND status = 'O'")->fetch_assoc()['total'];
$status_menurun = $conn->query("SELECT COUNT(*) as total FROM inspections_daily WHERE tanggal = '$today' AND status = '-'")->fetch_assoc()['total'];
$status_rusak = $conn->query("SELECT COUNT(*) as total FROM inspections_daily WHERE tanggal = '$today' AND status = 'X'")->fetch_assoc()['total'];
$status_perbaikan = $conn->query("SELECT COUNT(*) as total FROM inspections_daily WHERE tanggal = '$today' AND status = 'V'")->fetch_assoc()['total'];

// Total issues (need attention = -, X, V)
$perlu_perhatian = $status_menurun;
$bermasalah = $status_rusak + $status_perbaikan;

// Get problematic equipment from today's inspections
$problem_query = "
    SELECT i.*, e.nama_peralatan, l.nama_lokasi, s.nama_section
    FROM inspections_daily i
    JOIN equipments e ON i.equipment_id = e.id
    JOIN lokasi l ON e.lokasi_id = l.id
    JOIN sections s ON e.section_id = s.id
    WHERE i.tanggal = '$today' AND i.status IN ('X', 'V', '-')
    ORDER BY FIELD(i.status, 'X', 'V', '-')
    LIMIT 10
";
$problem_result = $conn->query($problem_query);
$problem_count = $problem_result->num_rows;
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Equipment Monitoring System</title>
<script>
// Critical: Run BEFORE any rendering to prevent sidebar flicker
if (localStorage.getItem('sidebarOpen') === 'true') {
    document.documentElement.classList.add('sidebar-open');
}
</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --primary: #087F8A;
    --primary-dark: #065C63;
    --primary-light: #087F8A;
    --primary-lighter: #f0f9fa;
    --success: #166534;
    --success-light: #f0fdf4;
    --warning: #d97706;
    --warning-light: #fffbeb;
    --danger: #dc2626;
    --danger-light: #fef2f2;
    --info: #065C63;
    --info-light: #f0f9fa;
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-300: #d1d5db;
    --gray-400: #9ca3af;
    --gray-500: #6b7280;
    --gray-600: #4b5563;
    --gray-700: #374151;
    --gray-800: #1f2937;
    --gray-900: #111827;
    --white: #ffffff;
    --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.04);
    --shadow-md: 0 2px 4px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 4px 8px rgba(0, 0, 0, 0.08);
    --shadow-xl: 0 8px 16px rgba(0, 0, 0, 0.08);
    --border-radius: 6px;
}

body {
    font-family: 'Plus Jakarta Sans', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: var(--gray-100);
    min-height: 100vh;
    color: var(--gray-800);
}

/* MAIN CONTENT */
.admin-main {
    padding: 30px;
    min-height: 100vh;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
}

/* PAGE HEADER */
.page-header {
    margin-bottom: 28px;
    padding: 20px 24px;
    background: var(--white);
    border-radius: 8px;
    border-left: 4px solid var(--primary-dark);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.page-header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}

.page-info {
    padding-left: 0;
}

.page-info h1 {
    font-size: 22px;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 4px;
}

.page-info p {
    font-size: 13px;
    color: var(--gray-500);
}

.header-date {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: var(--gray-50);
    border-radius: 6px;
    border: 1px solid var(--gray-200);
}

.header-date i {
    color: var(--primary-dark);
    font-size: 14px;
}

.header-date span {
    font-size: 13px;
    font-weight: 500;
    color: var(--gray-700);
}

/* STATS CARDS - Soft Light Design */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 28px;
}

.stat-card {
    background: #f8f9fa;
    border-radius: 16px;
    padding: 24px;
    position: relative;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
}

.stat-card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}

.stat-card.primary {
    background: #f5f7f7;
    border-color: #e8eaeb;
}

.stat-card.danger {
    background: #faf6f6;
    border-color: #f0e8e8;
}

.stat-card.success {
    background: #f6faf7;
    border-color: #e8f0ea;
}

.stat-card.warning {
    background: #faf9f6;
    border-color: #f0ede8;
}

.stat-card .stat-body {
    flex: 1;
}

.stat-card .stat-header {
    display: none;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.stat-card.primary .stat-icon { 
    background: rgba(15, 76, 92, 0.1); 
    color: #0F4C5C; 
}
.stat-card.success .stat-icon { 
    background: rgba(34, 84, 61, 0.1); 
    color: #22543d; 
}
.stat-card.warning .stat-icon { 
    background: rgba(146, 115, 54, 0.1); 
    color: #927336; 
}
.stat-card.danger .stat-icon { 
    background: rgba(155, 64, 64, 0.1); 
    color: #9b4040; 
}

.stat-value {
    font-size: 36px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 6px;
    line-height: 1;
    letter-spacing: -0.5px;
}

.stat-label {
    font-size: 13px;
    font-weight: 500;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

/* CONTENT GRID */
.content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

.content-full {
    grid-column: span 2;
}

/* DASHBOARD TWO-COLUMN GRID */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

.card {
    background: var(--white);
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    background: linear-gradient(180deg, var(--gray-50) 0%, var(--white) 100%);
    border-bottom: 1px solid var(--gray-200);
}

.card-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--gray-800);
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-title i {
    color: var(--primary-dark);
    font-size: 16px;
}

.card-action {
    font-size: 13px;
    color: var(--primary-dark);
    text-decoration: none;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 4px;
}

.card-action:hover {
    color: var(--primary);
}

.card-body {
    padding: 20px;
    background: var(--white);
}

/* PROBLEM EQUIPMENT - Clean Table Style */
.problem-list {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.problem-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    background: var(--white);
    border-bottom: 1px solid var(--gray-200);
    border-left: 3px solid var(--danger);
}

.problem-item:last-child {
    border-bottom: none;
}

.problem-item.warning {
    border-left-color: var(--warning);
}

.problem-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: linear-gradient(135deg, rgba(220, 38, 38, 0.04) 0%, rgba(220, 38, 38, 0.02) 100%);
    border-radius: 8px;
    border-left: 4px solid var(--danger);
    transition: all 0.2s ease;
}

.problem-item:hover {
    background: linear-gradient(135deg, rgba(220, 38, 38, 0.08) 0%, rgba(220, 38, 38, 0.04) 100%);
    box-shadow: 0 2px 8px rgba(220, 38, 38, 0.12);
}

.problem-item.warning {
    background: linear-gradient(135deg, rgba(217, 119, 6, 0.04) 0%, rgba(217, 119, 6, 0.02) 100%);
    border-left-color: var(--warning);
}

.problem-item.warning:hover {
    background: linear-gradient(135deg, rgba(217, 119, 6, 0.08) 0%, rgba(217, 119, 6, 0.04) 100%);
    box-shadow: 0 2px 8px rgba(217, 119, 6, 0.12);
}

.problem-icon {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.problem-item .problem-icon {
    background: rgba(220, 38, 38, 0.12);
    color: var(--danger);
}

.problem-item.warning .problem-icon {
    background: rgba(217, 119, 6, 0.12);
    color: var(--warning);
}

.problem-content {
    flex: 1;
}

.problem-content h4 {
    font-size: 14px;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 2px;
}

.problem-content p {
    font-size: 13px;
    color: var(--gray-500);
}

.problem-status {
    padding: 6px 14px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.problem-status.danger {
    background: var(--danger);
    color: var(--white);
}

.problem-status.warning {
    background: var(--warning);
    color: var(--white);
}

.problem-content {
    flex: 1;
}

.problem-content h4 {
    font-size: 14px;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 4px;
}

.problem-content p {
    font-size: 13px;
    color: var(--gray-500);
}

.problem-status {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.problem-status.danger {
    background: var(--danger-light);
    color: var(--danger);
}

.problem-status.warning {
    background: var(--warning-light);
    color: var(--warning);
}

/* CHECKLIST SUMMARY - Status Bar Style */
.summary-grid {
    display: flex;
    gap: 0;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--gray-200);
}

.summary-card {
    flex: 1;
    padding: 24px 20px;
    background: var(--white);
    text-align: center;
    border-right: 1px solid var(--gray-200);
    position: relative;
}

.summary-card:last-child {
    border-right: none;
}

.summary-card::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.summary-card.success::after { background: var(--success); }
.summary-card.warning::after { background: var(--warning); }
.summary-card.danger::after { background: var(--danger); }

.summary-value {
    font-size: 42px;
    font-weight: 700;
    margin-bottom: 8px;
    line-height: 1;
}

.summary-card.success .summary-value { color: var(--success); }
.summary-card.warning .summary-value { color: var(--warning); }
.summary-card.danger .summary-value { color: var(--danger); }

.summary-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* QUICK LINKS GRID (2x2) */
.quick-links-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.quick-link-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: var(--gray-50);
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s ease;
    border: 1px solid var(--gray-200);
}

.quick-link-item:hover {
    background: var(--white);
    border-color: var(--primary-dark);
    box-shadow: 0 2px 8px rgba(6, 92, 99, 0.12);
}

.quick-link-item .quick-link-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    font-size: 14px;
}

.quick-link-item span {
    font-size: 13px;
    font-weight: 500;
    color: var(--gray-700);
}

/* BADGE COUNT */
.badge-count {
    font-size: 12px;
    font-weight: 600;
    padding: 4px 10px;
    background: rgba(220, 38, 38, 0.1);
    color: var(--danger);
    border-radius: 12px;
}

/* PROBLEM TABLE */
.problem-table {
    width: 100%;
    border-collapse: collapse;
}

.problem-table thead {
    background: var(--gray-50);
}

.problem-table th {
    padding: 12px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--gray-200);
}

.problem-table td {
    padding: 14px 16px;
    font-size: 14px;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.problem-table tbody tr:hover {
    background: var(--gray-50);
}

.problem-table tr.status-danger {
    border-left: 3px solid var(--danger);
}

.problem-table tr.status-warning {
    border-left: 3px solid var(--warning);
}

.problem-table .problem-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    background: rgba(220, 38, 38, 0.1);
    color: var(--danger);
}

.problem-table .problem-icon.warning {
    background: rgba(217, 119, 6, 0.1);
    color: var(--warning);
}

.problem-status {
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.problem-status.danger {
    background: var(--danger);
    color: var(--white);
}

.problem-status.warning {
    background: var(--warning);
    color: var(--white);
}

.summary-value {
    font-size: 48px;
    font-weight: 800;
    margin-bottom: 8px;
    letter-spacing: -1px;
    line-height: 1;
}

.summary-card.success .summary-value { color: var(--success); }
.summary-card.warning .summary-value { color: var(--warning); }
.summary-card.danger .summary-value { color: var(--danger); }

.summary-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.summary-value {
    font-size: 36px;
    font-weight: 800;
    margin-bottom: 6px;
}

.summary-card.success .summary-value { color: var(--success); }
.summary-card.warning .summary-value { color: var(--warning); }
.summary-card.danger .summary-value { color: var(--danger); }

.summary-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--gray-600);
}

/* TECHNICIAN ACTIVITY */
.technician-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.technician-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    background: var(--gray-50);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.technician-item:hover {
    background: var(--primary-lighter);
}

.technician-avatar {
    width: 38px;
    height: 38px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 600;
    color: var(--white);
    flex-shrink: 0;
    background: var(--primary-dark);
}

.technician-avatar.blue { background: var(--primary-dark); }
.technician-avatar.green { background: var(--success); }
.technician-avatar.orange { background: var(--warning); }
.technician-avatar.purple { background: var(--gray-600); }

.technician-info {
    flex: 1;
}

.technician-info h4 {
    font-size: 14px;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 2px;
}

.technician-info p {
    font-size: 12px;
    color: var(--gray-500);
}

.technician-stats {
    display: flex;
    align-items: center;
    gap: 12px;
}

.technician-stat {
    text-align: center;
    padding: 6px 12px;
    background: var(--white);
    border-radius: 8px;
}

.technician-stat-value {
    font-size: 16px;
    font-weight: 700;
    color: var(--gray-800);
}

.technician-stat-label {
    font-size: 10px;
    color: var(--gray-500);
    text-transform: uppercase;
}

/* QUICK LINKS */
.quick-links {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

.quick-link {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 24px 16px;
    background: var(--white);
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s ease;
    border: 1px solid var(--gray-200);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.quick-link:hover {
    border-color: var(--primary-dark);
    box-shadow: 0 4px 12px rgba(6, 92, 99, 0.15);
    transform: translateY(-2px);
}

.quick-link-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: 20px;
}

.quick-link span {
    font-size: 13px;
    font-weight: 600;
    color: var(--gray-700);
    text-align: center;
}

/* ANIMATIONS */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.stat-card:nth-child(1) { animation: fadeInUp 0.5s ease-out 0.1s both; }
.stat-card:nth-child(2) { animation: fadeInUp 0.5s ease-out 0.15s both; }
.stat-card:nth-child(3) { animation: fadeInUp 0.5s ease-out 0.2s both; }
.stat-card:nth-child(4) { animation: fadeInUp 0.5s ease-out 0.25s both; }

.card { animation: fadeInUp 0.5s ease-out 0.3s both; }

/* RESPONSIVE */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .quick-links {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .content-full {
        grid-column: span 1;
    }
    
    .summary-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .admin-main {
        padding: 20px;
    }
    
    .page-header-content {
        flex-direction: column;
        align-items: stretch;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .quick-links {
        grid-template-columns: 1fr;
    }
    
    .summary-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<!-- ADMIN SIDEBAR -->
<?php include 'includes/admin_sidebar.php'; ?>

<!-- MAIN CONTENT -->
<main class="admin-main">
    <div class="container">
        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="page-header-content">
                <div class="page-info">
                    <h1>Dashboard Admin</h1>
                    <p>Selamat datang kembali! Kelola sistem monitoring peralatan bandara.</p>
                </div>
                <div class="header-date">
                    <i class="fas fa-calendar-alt"></i>
                    <span id="currentDate">Rabu, 1 Januari 2026</span>
                </div>
            </div>
        </div>

        <!-- STATS CARDS - Modern Professional -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-body">
                    <div class="stat-value"><?= $total_equipment ?></div>
                    <div class="stat-label">Total Peralatan</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-tools"></i>
                </div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-body">
                    <div class="stat-value"><?= $bermasalah ?></div>
                    <div class="stat-label">Peralatan Bermasalah</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-body">
                    <div class="stat-value"><?= $total_personnel ?></div>
                    <div class="stat-label">Total Personil</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-body">
                    <div class="stat-value"><?= $total_lokasi ?></div>
                    <div class="stat-label">Lokasi Terdaftar</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
            </div>
        </div>

        <!-- TWO COLUMN LAYOUT: Checklist + Quick Links -->
        <div class="dashboard-grid">
            <!-- LEFT: Checklist Summary -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-clipboard-check"></i>
                        Rangkuman Checklist Hari Ini
                    </h3>
                    <a href="admin_laporan_harian.php" class="card-action">
                        Lihat Detail <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <div class="card-body">
                    <div class="summary-grid">
                        <div class="summary-card success">
                            <div class="summary-value"><?= $status_normal ?></div>
                            <div class="summary-label">Peralatan Normal</div>
                        </div>
                        <div class="summary-card warning">
                            <div class="summary-value"><?= $perlu_perhatian ?></div>
                            <div class="summary-label">Perlu Perhatian</div>
                        </div>
                        <div class="summary-card danger">
                            <div class="summary-value"><?= $bermasalah ?></div>
                            <div class="summary-label">Bermasalah</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Quick Links -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-bolt"></i>
                        Akses Cepat
                    </h3>
                </div>
                <div class="card-body">
                    <div class="quick-links-grid">
                        <a href="kelola_lokasi.php" class="quick-link-item">
                            <div class="quick-link-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <span>Kelola Lokasi</span>
                        </a>
                        <a href="kelola_jenis_peralatan.php" class="quick-link-item">
                            <div class="quick-link-icon">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <span>Kelola Peralatan</span>
                        </a>
                        <a href="admin_personnel.php" class="quick-link-item">
                            <div class="quick-link-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <span>Tambah Personil</span>
                        </a>
                        <?php $current_month = date('Y-m'); ?>
                        <a href="export/export_excel.php?type=monthly&month=<?= $current_month ?>" class="quick-link-item">
                            <div class="quick-link-icon">
                                <i class="fas fa-download"></i>
                            </div>
                            <span>Download Laporan</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- FULL WIDTH: Problem Equipment -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-exclamation-circle"></i>
                    Peralatan Bermasalah
                </h3>
                <span class="badge-count"><?= $problem_count ?> Item</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="problem-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;"></th>
                            <th>Nama Peralatan</th>
                            <th>Keterangan</th>
                            <th style="width: 100px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($problem_count > 0): ?>
                            <?php while ($problem = $problem_result->fetch_assoc()): ?>
                                <?php 
                                    $status_class = ($problem['status'] == 'X' || $problem['status'] == 'V') ? 'danger' : 'warning';
                                    $status_text = match($problem['status']) {
                                        'X' => 'Terputus',
                                        'V' => 'Gangguan',
                                        '-' => 'Menurun',
                                        default => 'Unknown'
                                    };
                                    $icon_class = match(true) {
                                        str_contains(strtolower($problem['nama_section']), 'genset') => 'fa-car-battery',
                                        str_contains(strtolower($problem['nama_section']), 'hvac') => 'fa-fan',
                                        str_contains(strtolower($problem['nama_section']), 'listrik') => 'fa-bolt',
                                        str_contains(strtolower($problem['nama_section']), 'ups') => 'fa-battery-half',
                                        str_contains(strtolower($problem['nama_section']), 'lighting') => 'fa-lightbulb',
                                        str_contains(strtolower($problem['nama_section']), 'pms') || str_contains(strtolower($problem['nama_section']), 'escalator') => 'fa-arrows-alt-v',
                                        str_contains(strtolower($problem['nama_section']), 'bhs') => 'fa-suitcase-rolling',
                                        default => 'fa-tools'
                                    };
                                ?>
                                <tr class="status-<?= $status_class ?>">
                                    <td><div class="problem-icon <?= $status_class == 'warning' ? 'warning' : '' ?>"><i class="fas <?= $icon_class ?>"></i></div></td>
                                    <td><strong><?= htmlspecialchars($problem['nama_peralatan']) ?> - <?= htmlspecialchars($problem['nama_lokasi']) ?></strong></td>
                                    <td><?= htmlspecialchars($problem['keterangan'] ?? $status_text) ?></td>
                                    <td><span class="problem-status <?= $status_class ?>"><?= $status_text ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 40px; color: var(--gray-500);">
                                    <i class="fas fa-check-circle" style="color: var(--success); font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                    Tidak ada peralatan bermasalah hari ini
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
// Set current date
document.addEventListener('DOMContentLoaded', function() {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const today = new Date();
    document.getElementById('currentDate').textContent = today.toLocaleDateString('id-ID', options);
});
</script>

</body>
</html>
