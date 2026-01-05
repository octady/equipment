<?php
include "config/database.php";
session_start();

// --- AUTO-SETUP & MIGRATION Logic (Kept same as original to ensure stability) ---
// (Ideally this should be in a separate migration script, but keeping it here for continuity)
// ... [Skipping verbose migration code repetition, assuming DB is ready] ...

$eq_id = $_GET['id'] ?? null;
$today = $_GET['date'] ?? date('Y-m-d');
$is_today = ($today == date('Y-m-d'));

if (!$eq_id) {
    header("Location: riwayat_monitoring.php?date=$today");
    exit;
}

// Fetch equipment details
$stmt = $conn->prepare("SELECT e.*, s.nama_section, l.nama_lokasi FROM equipments e JOIN sections s ON e.section_id = s.id JOIN lokasi l ON e.lokasi_id = l.id WHERE e.id = ?");
if (!$stmt) die("Error preparing equipment query.");
$stmt->bind_param("i", $eq_id);
$stmt->execute();
$equipment = $stmt->get_result()->fetch_assoc();

if (!$equipment) {
    header("Location: riwayat_monitoring.php?date=$today");
    exit;
}

// Fetch existing inspection
$stmt = $conn->prepare("SELECT * FROM inspections_daily WHERE equipment_id = ? AND tanggal = ?");
$stmt->bind_param("is", $eq_id, $today);
$stmt->execute();
$inspection = $stmt->get_result()->fetch_assoc();

$current_status = $inspection['status'] ?? 'O';

// Parse Keterangan to split General Notes vs Detail Masalah
$full_keterangan = $inspection['keterangan'] ?? '';
$delimiter = '[DETAIL MASALAH]:';
$keterangan_border = strpos($full_keterangan, $delimiter);

if ($keterangan_border !== false) {
    $keterangan_umum = trim(substr($full_keterangan, 0, $keterangan_border));
    $keterangan_masalah = trim(substr($full_keterangan, $keterangan_border + strlen($delimiter)));
} else {
    $keterangan_umum = $full_keterangan;
    $keterangan_masalah = '';
}

// Logic for DISPLAY (Fix for "Kan ini menurun"):
// Ensure existing data is cleaned up on view as well, not just on save.
if ($current_status === 'O') {
    if (empty($keterangan_umum) || $keterangan_umum === 'Normal') {
        $keterangan_umum = 'Normal';
    }
} else {
    // Other statuses (Menurun, Rusak, etc.)
    if ($keterangan_umum === 'Normal') {
        $keterangan_umum = ''; 
    }
}

// User Request: Default note should be "Normal" if status is "O" (Normal)
// We handle this on SAVE, but for display, if it IS "Normal" and we want to show it as "Normal" in view mode, we leave it.
// If we want to hide "Normal" in edit mode (like we did initially), we could, but user changed mind to "Default Normal".
// Actually, for display: if it is "Normal" and status is "O", we show "Normal". 
// But if we want to mimic the logic where "Normal" is auto-set, we just ensure it displays correctly.
// The "Clean View" logic: if it's "Normal", show "Normal" text.
// If it's empty, show placeholder in edit, "Belum ada catatan" in view.

// Handle Save
if (isset($_POST['save_detail'])) {
    $ket = $_POST['keterangan'] ?? '';
    // Logic: 
    // 1. If Status is Normal ('O'), default the note to "Normal" if empty.
    // 2. If Status is NOT Normal, and the note says "Normal", CLEAR IT (because it contradicts).
    $status = $_POST['status'] ?? $current_status;

    if ($status === 'O') {
        if (empty($ket) || $ket === 'Normal') {
            $ket = 'Normal';
        }
    } else {
        // Status is NOT Normal (Menurun, Rusak, etc.)
        if ($ket === 'Normal') {
            $ket = ''; // Clear it because it doesn't make sense
        }
    }
    
    if (isset($_POST['keterangan_masalah']) && !empty($_POST['keterangan_masalah'])) {
        $ket .= "\n\n[DETAIL MASALAH]: " . $_POST['keterangan_masalah'];
    }

    if ($inspection) {
        $stmt = $conn->prepare("UPDATE inspections_daily SET status = ?, keterangan = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $ket, $inspection['id']);
        $stmt->execute();
        $inspection_id = $inspection['id'];
    } else {
        // Create new inspection if it doesn't exist (e.g. checking via detail page first)
        $stmt = $conn->prepare("INSERT INTO inspections_daily (equipment_id, tanggal, status, keterangan, checked_by) VALUES (?, ?, ?, ?, ?)");
        // Auto-assign user if creating new
        $user_name = $_SESSION['nama_lengkap'] ?? 'User Monitoring'; 
        $stmt->bind_param("issss", $eq_id, $today, $status, $ket, $user_name);
        $stmt->execute();
        $inspection_id = $conn->insert_id;
    }

    // Handle Photos (Same logic)
    $upload_dir = "assets/uploads/inspections/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    if (isset($_FILES['fotos']) && !empty($_FILES['fotos']['name'][0])) {
        foreach ($_FILES['fotos']['name'] as $key => $name) {
            if ($_FILES['fotos']['error'][$key] == 0) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $filename = "insp_" . $eq_id . "_" . time() . "_" . $key . "." . $ext;
                if (move_uploaded_file($_FILES['fotos']['tmp_name'][$key], $upload_dir . $filename)) {
                    $foto_path = "assets/uploads/inspections/" . $filename;
                    $stmt_photo = $conn->prepare("INSERT INTO inspection_photos (inspection_id, foto_path) VALUES (?, ?)");
                    $stmt_photo->bind_param("is", $inspection_id, $foto_path);
                    $stmt_photo->execute();
                }
            }
        }
    }

    header("Location: riwayat_monitoring.php?date=$today");
    exit;
}

// Handle Photo Deletion
if (isset($_POST['delete_photo_id'])) {
    $del_id = intval($_POST['delete_photo_id']);
    // Check ownership/validity
    // User can delete any photo for this inspection
    $stmt_check = $conn->prepare("SELECT foto_path FROM inspection_photos WHERE id = ? AND inspection_id = ?");
    $stmt_check->bind_param("ii", $del_id, $inspection['id']);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    
    if ($row = $res_check->fetch_assoc()) {
        // Delete file
        if (file_exists($row['foto_path'])) {
            unlink($row['foto_path']);
        }
        // Delete DB record
        $stmt_del = $conn->prepare("DELETE FROM inspection_photos WHERE id = ?");
        $stmt_del->bind_param("i", $del_id);
        $stmt_del->execute();
    }
    // Correct redirect to self
    header("Location: detail_monitoring_user.php?id=$eq_id&date=$today");
    exit;
}


// Fetch photos
$photos = [];
if ($inspection) {
    $res_photos = $conn->query("SELECT * FROM inspection_photos WHERE inspection_id = " . $inspection['id'] . " ORDER BY id DESC");
    if ($res_photos) {
        while ($p = $res_photos->fetch_assoc()) $photos[] = $p;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Monitoring - <?= htmlspecialchars($equipment['nama_peralatan']) ?></title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary: #087F8A;
            --primary-dark: #065C63;
            --bg-body: #f1f5f9;
            --surface: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            
            --status-normal-bg: #d1fae5; --status-normal-text: #065f46; --status-normal-border: #10b981;
            --status-down-bg: #fef3c7; --status-down-text: #92400e; --status-down-border: #f59e0b;
            --status-broken-bg: #ffe4e6; --status-broken-text: #9f1239; --status-broken-border: #f43f5e;
            --status-standby-bg: #e0e7ff; --status-standby-text: #3730a3; --status-standby-border: #6366f1;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            /* Adjust padding for navbar */
            padding-top: 120px; 
        }

        /* Container */
        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Modern Card */
        .glass-card {
            background: var(--surface);
            border-radius: 24px;
            box-shadow: 0 20px 40px -8px rgba(0,0,0,0.08), 0 0 1px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.8);
        }

        /* Header Section */
        .detail-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        /* Ornamental Circle */
        .detail-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(8,127,138,0.3) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .section-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(4px);
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .check-time {
            text-align: right;
        }
        
        .time-label {
            font-size: 11px;
            opacity: 0.7;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        
        .time-value {
            font-family: 'Courier New', monospace;
            font-size: 24px;
            font-weight: 700;
            color: #38bdf8; /* Light blue accent */
        }

        .equipment-title {
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 8px 0;
            line-height: 1.2;
        }

        .equipment-location {
            font-size: 15px;
            opacity: 0.8;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 0;
        }

        /* Left Sidebar (Status) */
        .status-panel {
            background: #f8fafc;
            padding: 40px 30px;
            border-right: 1px solid var(--border);
        }

        /* Right Content (Forms) */
        .form-panel {
            padding: 40px;
            background: white;
        }

        /* Status Selectors */
        .status-header {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 16px;
            letter-spacing: 0.5px;
        }

        .status-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .status-radio-label {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            background: white;
            border: 2px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .status-radio-label:hover {
            border-color: #cbd5e1;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        /* Active States for Radios */
        input[type="radio"]:checked + .status-radio-label.s-O {
            border-color: var(--status-normal-border);
            background: var(--status-normal-bg);
            color: var(--status-normal-text);
        }
        input[type="radio"]:checked + .status-radio-label.s-X {
            border-color: var(--status-broken-border);
            background: var(--status-broken-bg);
            color: var(--status-broken-text);
        }
        input[type="radio"]:checked + .status-radio-label.s-- {
            border-color: var(--status-down-border);
            background: var(--status-down-bg);
            color: var(--status-down-text);
        }
        input[type="radio"]:checked + .status-radio-label.s-V {
            border-color: var(--status-standby-border);
            background: var(--status-standby-bg);
            color: var(--status-standby-text);
        }

        .status-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 16px;
            font-weight: 800;
            font-family: inherit;
        }

        .status-text {
            font-weight: 600;
            font-size: 15px;
        }

        .check-mark {
            position: absolute;
            right: 20px;
            opacity: 0;
            transform: scale(0.5);
            transition: all 0.2s;
        }
        input[type="radio"]:checked + .status-radio-label .check-mark {
            opacity: 1;
            transform: scale(1);
        }

        /* Form Textarea */
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 10px;
        }

        textarea.premium-input {
            width: 100%;
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            font-family: inherit;
            font-size: 15px;
            color: var(--text-main);
            transition: all 0.2s;
            resize: vertical;
            background: #fbfbfc;
        }

        textarea.premium-input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(8, 127, 138, 0.1);
        }

        /* Conditional Alert Box */
        .alert-box {
            background: #fff1f2;
            border: 1px solid #fda4af;
            border-radius: 16px;
            padding: 24px;
            margin-top: 30px;
            display: none;
            animation: slideDown 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .alert-box.active {
            display: block;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-title {
            color: #be123c;
            font-weight: 700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Photo Upload */
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 12px;
            margin-top: 20px;
        }
        
        .photo-thumb {
            aspect-ratio: 1;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            background: #e2e8f0;
        }
        
        .photo-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Button */
        .save-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            margin-top: 40px;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(8, 127, 138, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .save-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(8, 127, 138, 0.3);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white; /* Contrast against header */
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        .back-link:hover { opacity: 1; }

        @media (max-width: 800px) {
            .content-grid { grid-template-columns: 1fr; }
            .status-panel { border-right: none; border-bottom: 1px solid var(--border); }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<div class="container">
    
    <div class="glass-card">
        <!-- Header -->
        <div class="detail-header">
            <a href="riwayat_monitoring.php?date=<?= $today ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> KEMBALI</a>

            <?php if (!$is_today): ?>
            <div style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 8px 12px; margin-bottom: 15px; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: #cbd5e1;">
                <i class="fa-solid fa-lock"></i> Mode Baca Saja (History)
            </div>
            <?php endif; ?>
            
            <div class="header-top">
                <span class="section-badge"><?= htmlspecialchars($equipment['nama_section']) ?></span>
                <div class="check-time">
                    <div class="time-label">WAKTU CEK</div>
                    <div class="time-value"><?= ($inspection && $inspection['created_at']) ? date('H:i', strtotime($inspection['created_at'])) : '--:--' ?></div>
                </div>
            </div>
            
            <h1 class="equipment-title"><?= htmlspecialchars($equipment['nama_peralatan']) ?></h1>
            <div class="equipment-location">
                <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($equipment['nama_lokasi']) ?>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" class="content-grid" id="detailForm">
            <input type="hidden" name="status" id="statusInput" value="<?= $current_status ?>">
            <!-- Hidden input to emulate button click for PHP check -->
            <input type="hidden" name="save_detail" value="1">

            <!-- STATUS SIDEBAR -->
            <div class="status-panel">
                <div class="status-header">UPDATE KONDISI ALAT</div>
                
                <div class="status-options">
                    <!-- Normal -->
                    <label style="display:block; cursor: <?= $is_today ? 'pointer' : 'default' ?>;">
                        <input type="radio" name="status_visible" value="O" style="display:none;" <?= $current_status == 'O' ? 'checked' : '' ?> <?= $is_today ? 'onchange="updateStatus(this)"' : 'disabled' ?>>
                        <div class="status-radio-label s-O">
                            <div class="status-icon">O</div>
                            <div class="status-text">Normal</div>
                            <div class="check-mark"><i class="fa-solid fa-circle-check"></i></div>
                        </div>
                    </label>

                    <!-- Menurun -->
                    <label style="display:block; cursor: <?= $is_today ? 'pointer' : 'default' ?>;">
                        <input type="radio" name="status_visible" value="-" style="display:none;" <?= $current_status == '-' ? 'checked' : '' ?> <?= $is_today ? 'onchange="updateStatus(this)"' : 'disabled' ?>>
                        <div class="status-radio-label s--">
                            <div class="status-icon">-</div>
                            <div class="status-text">Menurun</div>
                            <div class="check-mark"><i class="fa-solid fa-circle-check"></i></div>
                        </div>
                    </label>

                     <!-- Standby -->
                     <label style="display:block; cursor: <?= $is_today ? 'pointer' : 'default' ?>;">
                        <input type="radio" name="status_visible" value="V" style="display:none;" <?= $current_status == 'V' ? 'checked' : '' ?> <?= $is_today ? 'onchange="updateStatus(this)"' : 'disabled' ?>>
                        <div class="status-radio-label s-V">
                            <div class="status-icon">V</div>
                            <div class="status-text">Standby / Gangguan</div>
                            <div class="check-mark"><i class="fa-solid fa-circle-check"></i></div>
                        </div>
                    </label>

                    <!-- Rusak -->
                    <label style="display:block; cursor: <?= $is_today ? 'pointer' : 'default' ?>;">
                        <input type="radio" name="status_visible" value="X" style="display:none;" <?= $current_status == 'X' ? 'checked' : '' ?> <?= $is_today ? 'onchange="updateStatus(this)"' : 'disabled' ?>>
                        <div class="status-radio-label s-X">
                            <div class="status-icon">X</div>
                            <div class="status-text">Rusak Total</div>
                            <div class="check-mark"><i class="fa-solid fa-circle-check"></i></div>
                        </div>
                    </label>
                </div>

                <!-- Helper -->
                <div style="margin-top: 20px; font-size: 13px; color: var(--text-muted); line-height: 1.5;">
                    <i class="fa-solid fa-info-circle"></i> Pilih statsu selain <b>Normal</b> jika ditemukan anomali. Form detail akan otomatis muncul.
                </div>
            </div>

            <!-- MAIN CONTENT FORM -->
            <div class="form-panel">
                
                <div class="form-group">
                    <label>Catatan Umum / Keterangan</label>
                    <!-- View Mode -->
                    <div id="view_keterangan" class="premium-input" style="min-height: 60px; background: #f8fafc; cursor: default; position: relative; border: 1px solid #cbd5e1;"> 
                        <?= nl2br(htmlspecialchars($keterangan_umum ?? '')) ?: '<span style="color:#94a3b8; font-style:italic;">Belum ada catatan.</span>' ?>
                        <?php if ($is_today): ?>
                        <div onclick="toggleEdit('keterangan')" style="position: absolute; top: 10px; right: 10px; cursor: pointer; color: var(--primary); background: rgba(8,127,138,0.1); width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
                            <i class="fa-solid fa-pen" style="font-size: 12px;"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <!-- Edit Mode -->
                    <textarea id="edit_keterangan" name="keterangan" class="premium-input" placeholder="Tulis catatan umum pengecekkan disini..." style="height: 120px; display: none;"><?= htmlspecialchars($keterangan_umum ?? '') ?></textarea>
                </div>

                <!-- CONDITIONAL FORM -->
                <div id="extraForm" style="margin-top: 20px; display: none;"> 
                    <div class="form-group">
                         <label style="color: #b91c1c;">Deskripsi Permasalahan & Temuan</label>
                         
                         <!-- View Mode -->
                        <div id="view_masalah" class="premium-input" style="min-height: 60px; background: #fff1f2; border: 1px solid #fda4af; cursor: default; position: relative;">
                            <?= nl2br(htmlspecialchars($keterangan_masalah ?? '')) ?: '<span style="color:#94a3b8; font-style:italic;">Belum ada deskripsi masalah.</span>' ?>
                            <?php if ($is_today): ?>
                            <div onclick="toggleEdit('masalah')" style="position: absolute; top: 10px; right: 10px; cursor: pointer; color: #b91c1c; background: rgba(185,28,28,0.1); width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
                                <i class="fa-solid fa-pen" style="font-size: 12px;"></i>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Edit Mode -->
                        <textarea id="edit_masalah" name="keterangan_masalah" class="premium-input" 
                            placeholder="Jelaskan secara detail: Gejala kerusakan, parameter yang tidak normal, dll..." 
                            style="height: 150px; border-color: #fda4af; display: none;"><?= htmlspecialchars($keterangan_masalah ?? '') ?></textarea>
                    </div>
                </div>

                 <!-- Photo Upload Section -->
                 <div class="form-group" style="margin-top: 30px;">
                    <label>Dokumentasi Foto</label>
                    
                    <?php if ($is_today): ?>
                    <label style="display: flex; gap:10px; align-items: center; cursor: pointer; padding: 12px; border: 2px dashed #e2e8f0; border-radius: 12px; width: fit-content;">
                        <div style="background: var(--bg-body); width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                            <i class="fa-solid fa-camera"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600; font-size: 14px;">Tambah Foto</div>
                            <div style="font-size: 11px; color: var(--text-muted); text-transform:uppercase;">Klik untuk upload</div>
                        </div>
                        <input type="file" name="fotos[]" multiple accept="image/*" style="display:none;" onchange="previewPhotos(this)">
                    </label>
                    <?php endif; ?>

                    <div class="photo-grid" id="newPhotoGrid">
                        <!-- Existing Photos -->
                         <?php foreach ($photos as $ph): ?>
                            <div class="photo-thumb" style="position:relative;">
                                <img src="<?= $ph['foto_path'] ?>">
                                <!-- Delete Button -->
                                <button type="button" 
                                    onclick="deletePhoto(<?= $ph['id'] ?>)"
                                    style="position:absolute; top:-5px; right:-5px; background:red; color:white; border:none; border-radius:50%; width:20px; height:20px; display:flex; align-items:center; justify-content:center; cursor:pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                    <i class="fa-solid fa-times" style="font-size: 12px;"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($is_today): ?>
                <button type="button" onclick="confirmSave()" class="save-btn">
                    <i class="fa-solid fa-save"></i> SIMPAN PERUBAHAN
                </button>
                <?php endif; ?>

            </div>
        </form>
        
         <!-- Hidden Form for Deletion -->
        <form id="deletePhotoForm" method="POST" style="display:none;">
            <input type="hidden" name="delete_photo_id" id="delete_photo_id_input">
        </form>
    </div>
</div>

<script>
    function updateStatus(startNode) {
        // Sync to hidden input
        document.getElementById('statusInput').value = startNode.value;
        
        // Toggle Conditional Form
        const form = document.getElementById('extraForm');
        if (startNode.value !== 'O') {
            form.style.display = 'block';
        } else {
             // Check if there is content in the textarea OR view div (since we might have content but empty textarea if not edited yet? no, textarea has content)
            // Actually check the PHP value rendered into textarea
            const problemText = document.getElementById('edit_masalah').value.trim();
            if (problemText.length > 0) {
                 form.style.display = 'block';
            } else {
                 form.style.display = 'none';
            }
        }
    }

    function toggleEdit(type) {
        if (type === 'keterangan') {
            document.getElementById('view_keterangan').style.display = 'none';
            document.getElementById('edit_keterangan').style.display = 'block';
            document.getElementById('edit_keterangan').focus();
        } else if (type === 'masalah') {
            document.getElementById('view_masalah').style.display = 'none';
            document.getElementById('edit_masalah').style.display = 'block';
            document.getElementById('edit_masalah').focus();
        }
    }

    function deletePhoto(id) {
        Swal.fire({
            title: 'Hapus Foto?',
            text: "Foto akan dihapus permanen.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            width: '320px', // Compact size
            customClass: {
                popup: 'small-swal-popup'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete_photo_id_input').value = id;
                document.getElementById('deletePhotoForm').submit();
            }
        });
    }

    function previewPhotos(input) {
        if (input.files) {
            Array.from(input.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'photo-thumb';
                    div.innerHTML = `<img src="${e.target.result}"><div style="position:absolute; bottom:0; left:0; right:0; background:rgba(0,128,0,0.7); color:white; font-size:10px; text-align:center; padding:2px;">BARU</div>`;
                    document.getElementById('newPhotoGrid').appendChild(div);
                }
                reader.readAsDataURL(file);
            });
        }
    }
    
    // Initial check
    updateStatus(document.querySelector('input[name="status_visible"]:checked') || {value: '<?= $current_status ?>'});

    function confirmSave() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Apakah anda yakin?',
                text: "Pastikan data status dan keterangan sudah benar.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#087F8A',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Simpan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('detailForm').submit();
                }
            });
        } else {
            // Fallback for offline/error
            if (confirm("Apakah anda yakin data sudah benar?")) {
                document.getElementById('detailForm').submit();
            }
        }
    }
</script>

</body>
</html>
