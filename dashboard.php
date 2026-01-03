<?php
// Dashboard - Equipment Monitoring System
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// If admin, redirect to admin dashboard
if ($_SESSION['role'] === 'admin') {
    header("Location: admin_dashboard.php");
    exit;
}

// Get user info from session
$nama_lengkap = $_SESSION['nama_lengkap'] ?? 'User';

// Database connection
require_once 'config/database.php';

$today = date('Y-m-d');

// Get total equipment count
$total_equipment = $conn->query("SELECT COUNT(*) as total FROM equipments")->fetch_assoc()['total'];

// Get today's inspections
$total_checked = $conn->query("SELECT COUNT(DISTINCT equipment_id) as total FROM inspections_daily WHERE tanggal = '$today'")->fetch_assoc()['total'];

// Calculate pending (not yet checked today)
$pending = $total_equipment - $total_checked;

// Get status breakdown for today
$status_normal = $conn->query("SELECT COUNT(*) as total FROM inspections_daily WHERE tanggal = '$today' AND status = 'O'")->fetch_assoc()['total'];
$status_menurun = $conn->query("SELECT COUNT(*) as total FROM inspections_daily WHERE tanggal = '$today' AND status = '-'")->fetch_assoc()['total'];
$status_rusak = $conn->query("SELECT COUNT(*) as total FROM inspections_daily WHERE tanggal = '$today' AND status = 'X'")->fetch_assoc()['total'];
$status_perbaikan = $conn->query("SELECT COUNT(*) as total FROM inspections_daily WHERE tanggal = '$today' AND status = 'V'")->fetch_assoc()['total'];

// Total issues (X + V + -)
$total_issues = $status_rusak + $status_perbaikan + $status_menurun;

// Calculate percentage for normal
$normal_percentage = $total_checked > 0 ? round(($status_normal / $total_checked) * 100) : 0;

// Get equipment by category
$electrical_count = $conn->query("SELECT COUNT(*) as total FROM equipments e JOIN sections s ON e.section_id = s.id WHERE s.parent_category = 'ELECTRICAL'")->fetch_assoc()['total'];
$mechanical_count = $conn->query("SELECT COUNT(*) as total FROM equipments e JOIN sections s ON e.section_id = s.id WHERE s.parent_category = 'MECHANICAL'")->fetch_assoc()['total'];
$username = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Equipment Monitoring System</title>
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
    font-family: 'Inter', 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    background: var(--gray-100);
    min-height: 100vh;
    color: var(--gray-800);
}

/* MAIN CONTENT */
.main-content {
    padding-top: 120px;
    padding-bottom: 40px;
    min-height: 100vh;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 24px;
}

/* PAGE HEADER */
.page-header {
    margin-bottom: 32px;
}

.page-header-content {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}

.greeting {
    animation: fadeInUp 0.5s ease-out;
}

.greeting-text {
    font-size: 14px;
    color: var(--gray-500);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.greeting-text i {
    color: var(--warning);
}

.page-title {
    font-size: 28px;
    font-weight: 800;
    color: var(--gray-900);
    margin-bottom: 6px;
    letter-spacing: -0.5px;
}

.page-subtitle {
    font-size: 14px;
    color: var(--gray-600);
    font-weight: 500;
}

.header-actions {
    display: flex;
    gap: 12px;
    animation: fadeInUp 0.5s ease-out 0.1s both;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    font-size: 14px;
    font-weight: 500;
    font-family: inherit;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    border: none;
}

.btn-primary {
    background: var(--primary-dark);
    color: var(--white);
    box-shadow: none;
}

.btn-primary:hover {
    background: var(--primary);
    box-shadow: var(--shadow-sm);
}

.btn-outline {
    background: var(--white);
    color: var(--gray-700);
    border: 1px solid var(--gray-300);
}

.btn-outline:hover {
    border-color: var(--primary-dark);
    color: var(--primary-dark);
    background: var(--gray-50);
}

/* STATS CARDS */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 32px;
}

.stat-card {
    background: var(--white);
    border-radius: 6px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid var(--gray-200);
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.stat-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

/* Status-tinted backgrounds */
.stat-card.primary { background: linear-gradient(135deg, rgba(6, 92, 99, 0.06) 0%, rgba(8, 127, 138, 0.03) 100%); }
.stat-card.success { background: linear-gradient(135deg, rgba(22, 101, 52, 0.06) 0%, rgba(22, 101, 52, 0.03) 100%); }
.stat-card.warning { background: linear-gradient(135deg, rgba(217, 119, 6, 0.06) 0%, rgba(217, 119, 6, 0.03) 100%); }
.stat-card.danger { background: linear-gradient(135deg, rgba(220, 38, 38, 0.06) 0%, rgba(220, 38, 38, 0.03) 100%); }

.stat-card.primary::before { background: var(--primary-dark); }
.stat-card.success::before { background: var(--success); }
.stat-card.warning::before { background: var(--warning); }
.stat-card.danger::before { background: var(--danger); }

.stat-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 16px;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.stat-card.primary .stat-icon { background: rgba(6, 92, 99, 0.12); color: var(--primary-dark); }
.stat-card.success .stat-icon { background: rgba(22, 101, 52, 0.12); color: var(--success); }
.stat-card.warning .stat-icon { background: rgba(217, 119, 6, 0.12); color: var(--warning); }
.stat-card.danger .stat-icon { background: rgba(220, 38, 38, 0.12); color: var(--danger); }

.stat-trend {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 500;
    padding: 3px 8px;
    border-radius: 4px;
    background: var(--gray-100);
    color: var(--gray-600);
}

.stat-trend.up { color: var(--success); }
.stat-trend.down { color: var(--danger); }

.stat-value {
    font-size: 36px;
    font-weight: 800;
    color: var(--gray-900);
    margin-bottom: 4px;
    line-height: 1;
    letter-spacing: -0.5px;
}

.stat-label {
    font-size: 13px;
    font-weight: 500;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* CONTENT GRID */
.content-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
}

/* HERO SECTION */
.hero-section {
    grid-column: span 3;
    border-radius: 8px;
    padding: 40px;
    color: var(--white);
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-md);
    background: url('assets/img/hero.jpg') center center / cover no-repeat;
    min-height: 180px;
}

/* Dark overlay for readability */
.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(17, 24, 39, 0.75);
    z-index: 1;
}

/* Remove animated shimmer - keep only static overlay */
.hero-section::after {
    display: none;
}

/* Dark gradient overlay for readability */
.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(
        135deg,
        rgba(15, 23, 42, 0.88) 0%,
        rgba(15, 23, 42, 0.7) 40%,
        rgba(14, 116, 144, 0.5) 75%,
        rgba(14, 165, 164, 0.3) 100%
    );
    z-index: 1;
}



.hero-content {
    position: relative;
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
}

.hero-text h2 {
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 10px;
    letter-spacing: -0.3px;
    color: var(--white);
}

.hero-text p {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    max-width: 500px;
    line-height: 1.6;
}

.hero-icon {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    flex-shrink: 0;
}

.hero-icon:hover {
    background: rgba(255, 255, 255, 0.15);
}

/* PROGRESS RING */
.progress-ring-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    padding: 16px 0;
}

.progress-ring {
    position: relative;
    width: 140px;
    height: 140px;
}

.progress-ring svg {
    transform: rotate(-90deg);
}

.progress-ring circle {
    fill: none;
    stroke-linecap: round;
}

.progress-ring .bg {
    stroke: var(--gray-200);
}

.progress-ring .progress-normal {
    stroke: var(--success);
    stroke-dasharray: 377;
    stroke-dashoffset: 377;
    transition: stroke-dashoffset 1s ease-out;
}

.progress-ring .progress-warning {
    stroke: var(--warning);
    stroke-dasharray: 377;
    stroke-dashoffset: 377;
    transition: stroke-dashoffset 1s ease-out;
}

.progress-ring .progress-danger {
    stroke: var(--danger);
    stroke-dasharray: 377;
    stroke-dashoffset: 377;
    transition: stroke-dashoffset 1s ease-out;
}

.progress-ring-center {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.progress-ring-value {
    font-size: 28px;
    font-weight: 800;
    color: var(--gray-800);
    line-height: 1;
}

.progress-ring-label {
    font-size: 11px;
    color: var(--gray-500);
    margin-top: 4px;
}

.progress-legend {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 16px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--gray-600);
}

.legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.legend-dot.normal { background: var(--success); }
.legend-dot.warning { background: var(--warning); }
.legend-dot.danger { background: var(--danger); }
.legend-dot.offline { background: var(--gray-400); }

/* CATEGORY CARDS */
.category-cards {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.category-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px;
    background: linear-gradient(135deg, rgba(6, 92, 99, 0.04) 0%, rgba(8, 127, 138, 0.02) 100%);
    border-radius: 8px;
    transition: all 0.2s ease;
    border: 1px solid var(--gray-200);
}

.category-card:hover {
    background: linear-gradient(135deg, rgba(6, 92, 99, 0.08) 0%, rgba(8, 127, 138, 0.04) 100%);
    border-color: var(--primary-dark);
    box-shadow: 0 2px 8px rgba(6, 92, 99, 0.12);
}

.category-icon {
    width: 52px;
    height: 52px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.category-icon.electrical {
    background: rgba(217, 119, 6, 0.12);
    color: var(--warning);
}

.category-icon.mechanical {
    background: rgba(6, 92, 99, 0.12);
    color: var(--primary-dark);
}

.category-content h4 {
    font-size: 15px;
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 4px;
}

.category-content p {
    font-size: 13px;
    color: var(--gray-500);
}

.category-count {
    margin-left: auto;
    font-size: 28px;
    font-weight: 800;
    color: var(--primary-dark);
    letter-spacing: -0.5px;
}

.category-content h4 {
    font-size: 15px;
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 4px;
}

.category-content p {
    font-size: 13px;
    color: var(--gray-500);
}

.category-count {
    margin-left: auto;
    font-size: 24px;
    font-weight: 800;
    color: var(--primary);
}

.card {
    background: var(--white);
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    background: linear-gradient(135deg, rgba(6, 92, 99, 0.04) 0%, rgba(8, 127, 138, 0.02) 100%);
    border-bottom: 1px solid var(--gray-200);
}

.card-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--primary-dark);
    display: flex;
    align-items: center;
    gap: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card-title i {
    color: var(--primary-dark);
    font-size: 16px;
}

.card-action {
    font-size: 12px;
    color: var(--primary-dark);
    text-decoration: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: color 0.2s;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.card-action:hover {
    color: var(--primary);
}

.card-body {
    padding: 20px;
    background: var(--white);
}

/* RECENT ACTIVITY */
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px;
    background: var(--gray-50);
    border-radius: 8px;
    transition: all 0.2s ease;
    border: 1px solid var(--gray-200);
}

.activity-item:hover {
    background: var(--white);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.activity-icon {
    width: 42px;
    height: 42px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.activity-icon.check { background: rgba(22, 101, 52, 0.12); color: var(--success); }
.activity-icon.warning { background: rgba(217, 119, 6, 0.12); color: var(--warning); }
.activity-icon.error { background: rgba(220, 38, 38, 0.12); color: var(--danger); }
.activity-icon.info { background: rgba(6, 92, 99, 0.12); color: var(--primary-dark); }

.activity-content {
    flex: 1;
    min-width: 0;
}

.activity-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 4px;
}

.activity-desc {
    font-size: 13px;
    color: var(--gray-500);
    margin-bottom: 6px;
}

.activity-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 12px;
    color: var(--gray-400);
}

.activity-meta i {
    margin-right: 4px;
}

/* QUICK ACTIONS */
.quick-actions {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    background: linear-gradient(135deg, rgba(6, 92, 99, 0.04) 0%, rgba(8, 127, 138, 0.02) 100%);
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
}

.quick-action-btn:hover {
    background: linear-gradient(135deg, rgba(6, 92, 99, 0.08) 0%, rgba(8, 127, 138, 0.04) 100%);
    border-color: var(--primary-dark);
    box-shadow: 0 2px 8px rgba(6, 92, 99, 0.12);
}

.quick-action-icon {
    width: 44px;
    height: 44px;
    background: var(--primary-dark);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: 18px;
    flex-shrink: 0;
}

.quick-action-content h4 {
    font-size: 14px;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 2px;
}

.quick-action-content p {
    font-size: 12px;
    color: var(--gray-500);
}

/* EQUIPMENT STATUS */
.status-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.status-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    background: var(--gray-50);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.status-item:hover {
    background: var(--gray-100);
}

.status-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

.status-dot.normal { background: var(--success); }
.status-dot.warning { background: var(--warning); }
.status-dot.error { background: var(--danger); }

.status-name {
    font-size: 14px;
    font-weight: 500;
    color: var(--gray-700);
}

.status-count {
    font-size: 13px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    background: var(--gray-200);
    color: var(--gray-600);
}

/* TODAY'S SCHEDULE */
.schedule-card {
    margin-top: 24px;
}

.schedule-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 0;
    border-bottom: 1px solid var(--gray-100);
}

.schedule-item:last-child {
    border-bottom: none;
}

.schedule-time {
    font-size: 13px;
    font-weight: 600;
    color: var(--primary);
    background: var(--primary-lighter);
    padding: 8px 12px;
    border-radius: 8px;
    min-width: 70px;
    text-align: center;
}

.schedule-content h4 {
    font-size: 14px;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 4px;
}

.schedule-content p {
    font-size: 13px;
    color: var(--gray-500);
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
    
    .content-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .hero-section {
        grid-column: span 2;
    }
}

@media (max-width: 992px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .hero-section {
        grid-column: span 1;
    }
    
    .hero-content {
        flex-direction: column;
        text-align: center;
    }
    
    .hero-icon {
        order: -1;
    }
}

@media (max-width: 768px) {
    .main-content {
        padding-top: 90px;
    }
    
    .container {
        padding: 0 16px;
    }
    
    .page-header-content {
        flex-direction: column;
        align-items: stretch;
    }
    
    .page-title {
        font-size: 24px;
    }
    
    .header-actions {
        flex-direction: column;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .stat-value {
        font-size: 28px;
    }
    
    .hero-section {
        padding: 30px 24px;
    }
    
    .hero-text h2 {
        font-size: 22px;
    }
    
    .hero-text p {
        font-size: 14px;
    }
    
    .hero-icon {
        width: 70px;
        height: 70px;
        font-size: 28px;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding-top: 85px;
    }
    
    .page-title {
        font-size: 22px;
    }
    
    .card-header {
        padding: 16px 18px;
    }
    
    .card-body {
        padding: 18px;
    }
    
    .category-card {
        padding: 16px;
    }
    
    .category-icon {
        width: 46px;
        height: 46px;
        font-size: 18px;
    }
    
    .category-count {
        font-size: 20px;
    }
}
</style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>



<!-- MAIN CONTENT -->
<main class="main-content">
    <div class="container">
        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="page-header-content">
                <div class="greeting">
                    <p class="greeting-text">
                        <i class="fas fa-sun"></i> <span id="greetingText">Selamat Pagi,</span> <strong><?= htmlspecialchars($nama_lengkap) ?></strong>
                    </p>
                    <h1 class="page-title">Dashboard Monitoring</h1>
                    <p class="page-subtitle">Pantau dan kelola pengecekan peralatan bandara hari ini</p>
                </div>
                <div class="header-actions">
                    <a href="monitoring.php" class="btn btn-primary">
                        <i class="fas fa-clipboard-check"></i>
                        Mulai Monitoring
                    </a>
                    <button class="btn btn-outline" id="refreshBtn">
                        <i class="fas fa-sync-alt"></i>
                        Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- STATS CARDS -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="stat-trend up">
                        <i class="fas fa-arrow-up"></i>
                        12%
                    </div>
                </div>
                <div class="stat-value"><?= $total_equipment ?></div>
                <div class="stat-label">Total Peralatan</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-value"><?= $pending ?></div>
                <div class="stat-label">Menunggu Pengecekan</div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-trend down">
                        <i class="fas fa-arrow-down"></i>
                        8%
                    </div>
                </div>
                <div class="stat-value"><?= $total_issues ?></div>
                <div class="stat-label">Peralatan Bermasalah</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-value"><?= $total_checked ?></div>
                <div class="stat-label">Pengecekan Selesai</div>
            </div>
        </div>

        <!-- CONTENT GRID -->
        <div class="content-grid">
            <!-- HERO SECTION -->
            <div class="hero-section">
                <div class="hero-content">
                    <div class="hero-text">
                        <h2>Sistem Monitoring Peralatan Bandara</h2>
                        <p>Pantau kondisi seluruh peralatan bandara secara real-time. Pastikan semua peralatan dalam kondisi optimal untuk mendukung operasional bandara yang aman dan efisien.</p>
                    </div>
                    <div class="hero-icon">
                        <i class="fas fa-plane-departure"></i>
                    </div>
                </div>
            </div>

            <!-- QUICK ACTIONS -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-bolt"></i>
                        Aksi Cepat
                    </h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="monitoring.php" class="quick-action-btn">
                            <div class="quick-action-icon">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div class="quick-action-content">
                                <h4>Mulai Monitoring</h4>
                                <p>Pengecekan peralatan baru</p>
                            </div>
                        </a>
                        <a href="history.php" class="quick-action-btn">
                            <div class="quick-action-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="quick-action-content">
                                <h4>Lihat Riwayat</h4>
                                <p>Cek hasil monitoring sebelumnya</p>
                            </div>
                        </a>
                        <a href="#" class="quick-action-btn">
                            <div class="quick-action-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="quick-action-content">
                                <h4>Laporan</h4>
                                <p>Export laporan monitoring</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- EQUIPMENT STATUS WITH VISUAL CHART -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-pie"></i>
                        Status Peralatan
                    </h3>
                </div>
                <div class="card-body">
                    <div class="progress-ring-container">
                        <div class="progress-ring">
                            <?php 
                            // Calculate stroke-dashoffset based on percentage
                            // Circle circumference = 2 * PI * r = 2 * 3.14159 * 60 = 377
                            $circumference = 377;
                            $offset = $circumference - ($circumference * $normal_percentage / 100);
                            
                            // Determine color based on percentage
                            if ($normal_percentage >= 80) {
                                $ring_color = 'var(--success)'; // Green
                            } elseif ($normal_percentage >= 50) {
                                $ring_color = 'var(--warning)'; // Yellow/Orange
                            } else {
                                $ring_color = 'var(--danger)'; // Red
                            }
                            ?>
                            <svg width="140" height="140">
                                <circle class="bg" cx="70" cy="70" r="60" stroke-width="12"/>
                                <circle cx="70" cy="70" r="60" stroke-width="12" 
                                    fill="none" 
                                    stroke="<?= $ring_color ?>" 
                                    stroke-linecap="round"
                                    stroke-dasharray="<?= $circumference ?>"
                                    stroke-dashoffset="<?= $offset ?>"
                                    style="transform: rotate(-90deg); transform-origin: center; transition: stroke-dashoffset 1s ease-out;"/>
                            </svg>
                            <div class="progress-ring-center">
                                <div class="progress-ring-value"><?= $normal_percentage ?>%</div>
                                <div class="progress-ring-label">Normal</div>
                            </div>
                        </div>
                        <div class="progress-legend">
                            <div class="legend-item">
                                <div class="legend-dot normal"></div>
                                <span>Normal (<?= $status_normal ?>)</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-dot warning"></div>
                                <span>Menurun (<?= $status_menurun ?>)</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-dot danger"></div>
                                <span>Gangguan (<?= $status_perbaikan ?>)</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-dot offline"></div>
                                <span>Terputus (<?= $status_rusak ?>)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EQUIPMENT CATEGORIES -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-layer-group"></i>
                        Kategori Peralatan
                    </h3>
                </div>
                <div class="card-body">
                    <div class="category-cards">
                        <div class="category-card">
                            <div class="category-icon electrical">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div class="category-content">
                                <h4>Elektrikal</h4>
                                <p>Panel, Genset, UPS, Trafo</p>
                            </div>
                            <div class="category-count"><?= $electrical_count ?></div>
                        </div>
                        <div class="category-card">
                            <div class="category-icon mechanical">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <div class="category-content">
                                <h4>Mekanikal</h4>
                                <p>Conveyor, Escalator, Lift, AHU</p>
                            </div>
                            <div class="category-count"><?= $mechanical_count ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Dynamic Greeting
function updateGreeting() {
    const hour = new Date().getHours();
    const greetingEl = document.getElementById('greetingText');
    const iconEl = document.querySelector('.greeting-text i');
    
    if (hour >= 5 && hour < 12) {
        greetingEl.textContent = 'Selamat Pagi,';
        iconEl.className = 'fas fa-sun';
        iconEl.style.color = '#f59e0b';
    } else if (hour >= 12 && hour < 15) {
        greetingEl.textContent = 'Selamat Siang,';
        iconEl.className = 'fas fa-sun';
        iconEl.style.color = '#f59e0b';
    } else if (hour >= 15 && hour < 18) {
        greetingEl.textContent = 'Selamat Sore,';
        iconEl.className = 'fas fa-cloud-sun';
        iconEl.style.color = '#f97316';
    } else {
        greetingEl.textContent = 'Selamat Malam,';
        iconEl.className = 'fas fa-moon';
        iconEl.style.color = '#6366f1';
    }
}

updateGreeting();

// Refresh Button
document.getElementById('refreshBtn').addEventListener('click', function() {
    const icon = this.querySelector('i');
    icon.classList.add('fa-spin');
    
    setTimeout(() => {
        icon.classList.remove('fa-spin');
        location.reload();
    }, 1000);
});
</script>

</body>
</html>
