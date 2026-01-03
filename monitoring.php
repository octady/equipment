<?php
include "config/database.php";
include "config/auth.php";

// --- AUTO-SETUP & MIGRATION ---
$conn->query("CREATE TABLE IF NOT EXISTS inspection_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT NOT NULL,
    foto_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inspection_id) REFERENCES inspections_daily(id) ON DELETE CASCADE
)");

// Migrate existing single photos to new table if they haven't been migrated
$res_mig = $conn->query("SELECT id, foto FROM inspections_daily WHERE foto IS NOT NULL AND foto != ''");
while ($row_mig = $res_mig->fetch_assoc()) {
  $check = $conn->prepare("SELECT id FROM inspection_photos WHERE inspection_id = ? AND foto_path = ?");
  $check->bind_param("is", $row_mig['id'], $row_mig['foto']);
  $check->execute();
  if ($check->get_result()->num_rows == 0) {
    $ins = $conn->prepare("INSERT INTO inspection_photos (inspection_id, foto_path) VALUES (?, ?)");
    $ins->bind_param("is", $row_mig['id'], $row_mig['foto']);
    $ins->execute();
  }
}

// Add equipment_name column if it doesn't exist
$res_col = $conn->query("SHOW COLUMNS FROM inspections_daily LIKE 'equipment_name'");
if ($res_col->num_rows == 0) {
  $conn->query("ALTER TABLE inspections_daily ADD COLUMN equipment_name VARCHAR(255) AFTER equipment_id");
}
// ------------------------------

$today = date('Y-m-d');
$success = isset($_GET['success']);

// Helper function
function getBaseEquipmentType($name)
{
  $base = preg_replace('/\d+/', '', $name);
  $base = preg_replace('/(kva|kw|hp|mva|volt|amp|liter|ton|meter|mm|cm|inch)/i', '', $base);
  $base = preg_replace('/[^\w\s]/', '', $base);
  $base = trim($base);
  return $base;
}

// Handle form submission
if (isset($_POST['save'])) {
  $upload_dir = "assets/uploads/inspections/";
  if (!is_dir($upload_dir))
    mkdir($upload_dir, 0777, true);




  // Process Section Photos
  $section_photos = [];
  if (isset($_FILES['foto_section'])) {
    foreach ($_FILES['foto_section']['name'] as $sec_id => $fname) {
      if (!empty($fname) && $_FILES['foto_section']['error'][$sec_id] == 0) {
        $ext = pathinfo($fname, PATHINFO_EXTENSION);
        $new_name = "insp_section_" . $sec_id . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['foto_section']['tmp_name'][$sec_id], $upload_dir . $new_name)) {
          $section_photos[$sec_id] = "assets/uploads/inspections/" . $new_name;
        }
      }
    }
  }

  // Pre-fetch all equipments to map ID -> Section
  $eq_map = [];
  $ids_to_check = array_keys($_POST['status'] ?? []);
  if (!empty($ids_to_check)) {
    $ids_str = implode(',', array_map('intval', $ids_to_check));
    $res_eq_map = $conn->query("SELECT id, section_id FROM equipments WHERE id IN ($ids_str)");
    while ($row = $res_eq_map->fetch_assoc()) {
      $eq_map[$row['id']] = $row['section_id'];
    }
  }

  $selected_personnel = $_POST['personnel'] ?? [];
  $other_personnel = $_POST['personnel_other'] ?? '';
  if (!empty($other_personnel))
    $selected_personnel[] = $other_personnel;
  $final_checked_by = implode(', ', $selected_personnel);

  if (isset($_POST['status']) && is_array($_POST['status'])) {
    foreach ($_POST['status'] as $eq_id => $status) {
      $ket = $_POST['keterangan'][$eq_id] ?? '';

      // Auto-fill Description if empty based on status
      if (empty($ket)) {
        switch ($status) {
          case 'O':
            $ket = 'Normal';
            break;
          case 'X':
            $ket = 'Rusak / Mati';
            break;
          case 'V':
            $ket = 'Perlu Perbaikan';
            break;
          case '-':
            $ket = 'Performa Menurun';
            break;
        }
      }

      // Fetch Equipment Name
      $eq_name = "";
      $res_name = $conn->query("SELECT nama_peralatan FROM equipments WHERE id = $eq_id");
      if ($row_name = $res_name->fetch_assoc()) {
        $eq_name = $row_name['nama_peralatan'];
      }

      $stmt_exists = $conn->prepare("SELECT id, foto, keterangan, checked_by FROM inspections_daily WHERE equipment_id = ? AND tanggal = ?");
      if (!$stmt_exists) {
        // Silent error or log to server error log if needed
      }
      $stmt_exists->bind_param("is", $eq_id, $today);
      $stmt_exists->execute();
      $exists = $stmt_exists->get_result()->fetch_assoc();

      if ($exists) {
        $inspection_id = $exists['id'];
        $final_ket = (!empty($ket)) ? $ket : $exists['keterangan'];

        // Merge Personnel
        $existing_personnel = !empty($exists['checked_by']) ? array_map('trim', explode(',', $exists['checked_by'])) : [];
        $merged_personnel = array_unique(array_merge($existing_personnel, $selected_personnel));
        $final_checked_by_update = implode(', ', $merged_personnel);

        $stmt = $conn->prepare("UPDATE inspections_daily SET status = ?, keterangan = ?, checked_by = ?, equipment_name = ? WHERE id = ?");
        if ($stmt) {
          $stmt->bind_param("ssssi", $status, $final_ket, $final_checked_by_update, $eq_name, $inspection_id);
        }
      } else {
        // New Insert
        $stmt = $conn->prepare("INSERT INTO inspections_daily (equipment_id, tanggal, status, keterangan, checked_by, equipment_name) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
          $stmt->bind_param("isssss", $eq_id, $today, $status, $ket, $final_checked_by, $eq_name);
        }
      }

      if (isset($stmt) && $stmt) {
        $stmt->execute();
      }
      if (!$exists && isset($conn->insert_id))
        $inspection_id = $conn->insert_id;

      // Handle Inline Photo for 'O' or anyone using the inline input
      if (isset($_FILES['foto']['name'][$eq_id]) && $_FILES['foto']['error'][$eq_id] == 0) {
        $ext = pathinfo($_FILES['foto']['name'][$eq_id], PATHINFO_EXTENSION);
        $filename = "insp_inline_" . $eq_id . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['foto']['tmp_name'][$eq_id], $upload_dir . $filename)) {
          $new_foto_path = "assets/uploads/inspections/" . $filename;
          $stmt_ph = $conn->prepare("INSERT INTO inspection_photos (inspection_id, foto_path) VALUES (?, ?)");
          $stmt_ph->bind_param("is", $inspection_id, $new_foto_path);
          $stmt_ph->execute();
        }
      }
      // Handle Section Photo Linkage (Only if status is Normal and no individual photo uploaded)
      elseif ($status == 'O' && isset($eq_map[$eq_id])) {
        $s_id = $eq_map[$eq_id];
        if (isset($section_photos[$s_id])) {
          $s_path = $section_photos[$s_id];
          $stmt_ph = $conn->prepare("INSERT INTO inspection_photos (inspection_id, foto_path) VALUES (?, ?)");
          $stmt_ph->bind_param("is", $inspection_id, $s_path);
          $stmt_ph->execute();
        }
      }
    }
  }
  header("Location: checklist.php?success=1");
  exit;
}

// Filters & Masters
$filter_category = $_GET['category'] ?? '';
$filter_section = $_GET['section'] ?? '';
$filter_lokasi = $_GET['lokasi'] ?? '';
$search = $_GET['search'] ?? '';

$categories = [];
$res_c = $conn->query("SELECT DISTINCT parent_category FROM sections ORDER BY parent_category");
if ($res_c) {
  while ($row = $res_c->fetch_assoc())
    $categories[] = $row;
}

$section_list = [];
$res_s = $conn->query("SELECT id, nama_section, parent_category FROM sections ORDER BY urutan");
if ($res_s) {
  while ($row = $res_s->fetch_assoc())
    $section_list[] = $row;
}

$personnel_list = [];
$res_p = $conn->query("SELECT * FROM personnel ORDER BY nama_personnel");
if ($res_p) {
  while ($row = $res_p->fetch_assoc())
    $personnel_list[] = $row;
}

$lokasi_list = [];
$res_l = $conn->query("SELECT * FROM lokasi ORDER BY nama_lokasi");
if ($res_l) {
  while ($row = $res_l->fetch_assoc())
    $lokasi_list[] = $row;
}

// Load Equipments
$where = ["1=1"];
$params = [];
$types = "";
if ($filter_category) {
  $where[] = "s.parent_category = ?";
  $params[] = $filter_category;
  $types .= "s";
}
if ($filter_section) {
  // Basic verification: ensure section belongs to category if category is set
  $isValidSection = true;
  if ($filter_category) {
    $found = false;
    foreach ($section_list as $sl) {
      if ($sl['id'] == $filter_section && $sl['parent_category'] == $filter_category) {
        $found = true;
        break;
      }
    }
    if (!$found)
      $isValidSection = false;
  }

  if ($isValidSection) {
    $where[] = "s.id = ?";
    $params[] = (int) $filter_section;
    $types .= "i";
  }
}
if ($filter_lokasi) {
  $where[] = "e.lokasi_id = ?";
  $params[] = (int) $filter_lokasi;
  $types .= "i";
}
if ($search) {
  $search_lower = strtolower($search);
  $where[] = "(LOWER(e.nama_peralatan) LIKE ? OR LOWER(l.nama_lokasi) LIKE ? OR LOWER(s.nama_section) LIKE ?)";
  $wildcard_search = "%$search_lower%";
  $params[] = $wildcard_search;
  $params[] = $wildcard_search;
  $params[] = $wildcard_search;
  $types .= "sss";
}

$query = "SELECT e.*, s.nama_section, s.parent_category, l.nama_lokasi, 
                 i.status as rs, i.keterangan as rk, 
                 (SELECT COUNT(*) FROM inspection_photos WHERE inspection_id = i.id) as photo_count
          FROM equipments e 
          JOIN sections s ON e.section_id = s.id 
          JOIN lokasi l ON e.lokasi_id = l.id 
          LEFT JOIN inspections_daily i ON e.id = i.equipment_id AND i.tanggal = '$today'
          WHERE " . implode(' AND ', $where) . " ORDER BY s.urutan, e.nama_peralatan";
$stmt_eq = $conn->prepare($query);
if (!$stmt_eq) {
  die("Error preparing query: " . $conn->error);
}
if (!empty($params)) {
  $stmt_eq->bind_param($types, ...$params);
}
$stmt_eq->execute();
$res_eq = $stmt_eq->get_result();
$equipments = [];
if ($res_eq) {
  while ($row = $res_eq->fetch_assoc())
    $equipments[] = $row;
}


$grouped_equipments = [];
foreach ($equipments as $eq) {
  $section = $eq['nama_section'];
  $base_type = getBaseEquipmentType($eq['nama_peralatan']);

  if (!isset($grouped_equipments[$section])) {
    $grouped_equipments[$section] = [];
  }
  if (!isset($grouped_equipments[$section][$base_type])) {
    $grouped_equipments[$section][$base_type] = [];
  }
  $grouped_equipments[$section][$base_type][] = $eq;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Premium Checklist - Aviation</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Custom Styles Included Below -->
  <style>
    /* Camera Icon Style */
    .fa-camera,
    .fa-camera-retro,
    .fa-image {
      color: #94a3b8;
      font-size: 1.5rem;
    }

    :root {
      --brand-primary: #087F8A;
      --brand-primary-dark: #065C63;
      --brand-teal: #087F8A;
      --brand-teal-dark: #065C63;
      --brand-secondary: #f59e0b;
      --brand-success: #087F8A;
      --brand-danger: #f43f5e;
      --brand-info: #6366f1;
      --bg-vibrant: #f8fafc;
      --grad-primary: linear-gradient(135deg, #087F8A 0%, #065C63 100%);
      --grad-success: linear-gradient(135deg, #087F8A 0%, #065C63 100%);
      --grad-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      --grad-danger: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%);
      --grad-info: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
      --grad-card-soft: linear-gradient(135deg, #ffffff 0%, #f4f9fa 100%);
      --shadow-premium: 0 10px 40px rgba(8, 127, 138, 0.05);
      --font-heading: 'Plus Jakarta Sans', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      --font-body: 'Plus Jakarta Sans', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
      font-family: var(--font-body);
      background: var(--bg-vibrant);
      background-attachment: fixed;
      color: #1e293b;
      margin: 0;
      padding-top: 100px;
      padding-bottom: 60px;
    }

    .p-container {
      max-width: 1200px;
      margin: 32px auto;
      padding: 0 24px;
    }

    /* Filter Bar Styles */
    .p-filter-bar {
      background: white;
      border-radius: 16px;
      padding: 20px 24px;
      margin-bottom: 16px;
      box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
      border: 1px solid #f1f5f9;
    }

    .p-filter-form {
      display: flex;
      gap: 12px;
      align-items: center;
      flex-wrap: wrap;
    }

    .p-search-box {
      position: relative;
      flex: 1;
      min-width: 180px;
    }

    .p-search-box input {
      width: 100%;
      padding: 10px 40px 10px 16px;
      border-radius: 10px;
      border: 1.5px solid #e2e8f0;
      font-size: 0.85rem;
      font-family: var(--font-body);
      transition: border-color 0.2s, box-shadow 0.2s;
      background: white;
    }

    .p-search-box input:focus {
      outline: none;
      border-color: var(--brand-teal);
      box-shadow: 0 0 0 3px rgba(8, 127, 138, 0.1);
    }

    .p-search-box button {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      padding: 4px;
      cursor: pointer;
      color: #94a3b8;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: color 0.2s;
    }

    .p-search-box button:hover {
      color: var(--brand-teal);
    }

    .p-filter-select {
      padding: 10px 32px 10px 14px;
      border-radius: 10px;
      border: 1.5px solid #e2e8f0;
      font-size: 0.85rem;
      font-family: var(--font-body);
      cursor: pointer;
      background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 9L1 4h10z'/%3E%3C/svg%3E") no-repeat right 12px center;
      appearance: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .p-filter-select:focus {
      outline: none;
      border-color: var(--brand-teal);
      box-shadow: 0 0 0 3px rgba(8, 127, 138, 0.1);
    }

    .p-select-kategori {
      min-width: 110px;
    }

    .p-select-jenis {
      flex: 1;
      min-width: 180px;
    }

    .p-select-lokasi {
      min-width: 150px;
    }

    .p-reset-btn {
      color: var(--brand-teal);
      text-decoration: none;
      font-size: 0.85rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 10px 16px;
      border-radius: 10px;
      transition: all 0.2s;
      white-space: nowrap;
    }

    .p-reset-btn:hover {
      background: rgba(8, 127, 138, 0.08);
      color: var(--brand-teal-dark);
    }

    /* Legend Styles */
    .p-legend {
      display: flex;
      gap: 24px;
      padding: 12px 24px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }

    .p-legend-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.85rem;
      color: #475569;
    }

    .p-legend-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      flex-shrink: 0;
    }

    @media (max-width: 768px) {
      .p-filter-form {
        flex-direction: column;
        align-items: stretch;
      }
      
      .p-search-box,
      .p-filter-select {
        width: 100%;
      }
    }

    .p-card {
      background: var(--grad-card-soft);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.5);
      overflow: hidden;
      margin-bottom: 20px;
    }

    .p-card-header {
      padding: 14px 20px;
      background: linear-gradient(to right, rgba(255, 255, 255, 0.8), rgba(255, 255, 255, 0.4));
      border-bottom: 1px solid rgba(8, 127, 138, 0.1);
      font-family: var(--font-heading);
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: relative;
      cursor: pointer;
    }

    .p-card-header::before {
      content: "";
      position: absolute;
      left: 0;
      top: 0;
      bottom: 0;
      width: 3px;
      background: var(--grad-primary);
      border-top-left-radius: 12px;
    }

    .p-section-title {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--brand-primary-dark);
      letter-spacing: -0.01em;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .p-section-title i {
      color: var(--brand-teal);
      font-size: 0.85rem;
      transition: transform 0.3s ease;
    }

    .p-section-subtitle {
      font-size: 0.65rem;
      color: #64748b;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-top: 2px;
    }

    .p-row {
      display: grid;
      grid-template-columns: minmax(0, 1.5fr) 200px 220px;
      gap: 20px;
      padding: 12px 16px;
      border-bottom: 1px solid rgba(248, 250, 252, 0.5);
      align-items: center;
      transition: background 0.2s ease;
    }

    .p-row:last-child {
      border-bottom: none;
    }

    .p-row:hover {
      background: rgba(255, 255, 255, 0.4);
    }

    .p-photo-col {
      display: none;
    }

    .p-row.p-row-normal .p-photo-col {
      display: block;
    }

    .p-row.p-row-findings .p-detail-col {
      display: block;
    }

    .p-row.p-row-normal {
      background: var(--grad-card-soft);
    }

    .p-detail-col {
      display: none;
    }

    .p-photo-btn {
      width: 100%;
      height: 60px;
      border: 1.5px dashed #e2e8f0;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      position: relative;
      background: white;
      transition: all 0.2s;
    }

    .p-photo-btn:hover {
      border-color: var(--brand-teal);
      background: #f0fbfc;
    }

    .p-photo-btn img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 10px;
    }

    @media (max-width: 992px) {
      .p-row {
        grid-template-columns: 1fr;
        gap: 20px;
        padding: 24px;
      }

      .p-photo-btn {
        height: 120px;
      }

      .p-pills {
        justify-content: center;
      }
    }

    .p-eq-name {
      font-family: var(--font-heading);
      font-weight: 600;
      font-size: 0.9rem;
      color: #1e293b;
      line-height: 1.3;
      letter-spacing: -0.01em;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      flex-shrink: 1;
      min-width: 0;
    }

    .p-eq-loc {
      font-size: 0.8rem;
      color: #64748b;
      font-weight: 500;
      text-transform: none;
      letter-spacing: 0.01em;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      flex-shrink: 0;
    }

    /* Equipment Info - Horizontal Layout */
    .p-eq-info {
      display: flex;
      align-items: center;
      gap: 12px;
      min-width: 0;
      flex: 1;
    }

    .p-eq-divider {
      color: #cbd5e1;
      font-weight: 300;
      font-size: 0.9rem;
    }

    .p-pills {
      display: flex;
      gap: 6px;
      flex-wrap: nowrap;
      min-width: 154px;
    }

    .p-pill {
      flex-shrink: 0;
      cursor: pointer;
      position: relative;
    }

    .p-pill input {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
    }

    .p-pill span {
      display: flex;
      width: 32px;
      height: 28px;
      align-items: center;
      justify-content: center;
      border-radius: 7px;
      font-size: 0.85rem;
      font-weight: 700;
      background: #f1f5f9;
      color: #94a3b8;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .p-pill input:checked+.o-pill {
      background: var(--grad-success);
      color: white;
    }

    .p-pill input:checked+.g-pill {
      background: var(--grad-warning);
      color: white;
    }

    .p-pill input:checked+.x-pill {
      background: var(--grad-danger);
      color: white;
    }

    .p-pill input:checked+.v-pill {
      background: var(--grad-info);
      color: white;
    }


    .p-btn-detail {
      display: none;
      background: white;
      color: #64748b;
      border: 1.5px solid #e2e8f0;
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 0.7rem;
      font-weight: 600;
      cursor: pointer;
      align-items: center;
      gap: 6px;
      transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      text-decoration: none;
      justify-content: center;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    .p-btn-detail:hover {
      background: #f8fafc;
      border-color: #cbd5e1;
      transform: translateY(-2px);
      color: var(--brand-teal);
    }

    .p-btn-detail.active {
      display: flex;
    }

    .p-btn-detail.filled {
      background: rgba(16, 185, 129, 0.1);
      border-color: rgba(16, 185, 129, 0.3);
      color: var(--brand-emerald);
    }

    .p-btn-detail.filled:hover {
      background: var(--brand-emerald);
      color: white;
      border-color: var(--brand-emerald);
    }

    .p-photo-btn {
      width: 200px;
      height: 150px;
      border: 2px dashed #e2e8f0;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      position: relative;
      background: #fcfdfe;
      transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
      overflow: visible;
    }

    .p-photo-btn:hover {
      border-color: var(--brand-teal);
      background: #f0fbfc;
      transform: translateY(-4px);
      box-shadow: 0 10px 20px rgba(8, 127, 138, 0.08);
    }

    .p-photo-btn img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 8px;
    }

    .p-photo-remove {
      position: absolute;
      top: -10px;
      right: -10px;
      width: 22px;
      height: 22px;
      background: var(--brand-rose);
      color: white;
      border-radius: 50%;
      display: none;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      font-weight: 800;
      box-shadow: 0 2px 10px rgba(244, 63, 94, 0.4);
      z-index: 20;
      cursor: pointer;
      transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      border: 2px solid white;
    }

    @keyframes shake {

      10%,
      90% {
        transform: translate3d(-1px, 0, 0);
      }

      20%,
      80% {
        transform: translate3d(2px, 0, 0);
      }

      30%,
      50%,
      70% {
        transform: translate3d(-4px, 0, 0);
      }

      40%,
      60% {
        transform: translate3d(4px, 0, 0);
      }
    }

    .p-photo-remove:hover {
      transform: scale(1.15) rotate(90deg);
      background: var(--brand-rose-dark);
    }

    .p-photo-req {
      position: absolute;
      top: -1px;
      right: -1px;
      width: 9px;
      height: 9px;
      background: var(--brand-rose);
      border-radius: 50%;
      border: 2px solid white;
      display: none;
      /* animation: pulse 2s infinite; */
    }

    /* @keyframes pulse {
      0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(244, 63, 94, 0.7);
      }

      70% {
        transform: scale(1);
        box-shadow: 0 0 0 6px rgba(244, 63, 94, 0);
      }

      100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(244, 63, 94, 0);
      }
    } */

    .p-footer {
      background: var(--grad-card-soft);
      backdrop-filter: blur(12px);
      padding: 32px;
      border-radius: 20px;
      border: 1px solid rgba(255, 255, 255, 0.5);
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: var(--shadow-premium);
      margin-top: 40px;
      margin-bottom: 80px;
    }

    .p-btn-submit {
      background: var(--grad-primary);
      color: white;
      border: none;
      padding: 10px 28px;
      border-radius: 8px;
      font-weight: 700;
      font-family: var(--font-heading);
      font-size: 0.85rem;
      cursor: pointer;
      /* transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); */
      box-shadow: 0 2px 6px rgba(8, 127, 138, 0.15);
    }

    /* .p-btn-submit:hover {
      transform: translateY(-4px) scale(1.02);
      box-shadow: 0 12px 30px rgba(8, 127, 138, 0.4);
    } */

    .p-btn-submit:active {
      transform: translateY(0) scale(0.96);
    }

    .p-filter-btn {
      background: var(--grad-success);
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 700;
      cursor: pointer;
      transition: 0.3s;
      box-shadow: var(--shadow-success);
    }

    .p-filter-btn:hover {
      transform: translateY(-1px);
      filter: brightness(1.1);
    }

    .p-mini-select {
      background: rgba(16, 185, 129, 0.08);
      border: 1px solid rgba(16, 185, 129, 0.2);
      color: var(--brand-emerald);
      padding: 6px 14px;
      border-radius: 8px;
      font-size: 0.75rem;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      /* transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); */
      text-transform: uppercase;
      letter-spacing: 0.025em;
    }

    .p-mini-select:hover {
      background: var(--brand-emerald);
      color: white;
      border-color: var(--brand-emerald);
      /* transform: translateY(-1px); */
      /* box-shadow: var(--shadow-success); */
    }

    .p-mini-select:active {
      transform: translateY(0) scale(0.96);
    }

    /* SELECT ALL Checkbox */
    .p-select-all-checkbox {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      user-select: none;
    }

    .p-select-all-checkbox input[type="checkbox"] {
      display: none;
    }

    .checkbox-custom {
      width: 16px;
      height: 16px;
      border: 2px solid rgba(16, 185, 129, 0.3);
      border-radius: 4px;
      background: white;
      position: relative;
      transition: all 0.2s ease;
    }

    .p-select-all-checkbox:hover .checkbox-custom {
      border-color: var(--brand-emerald);
      background: rgba(16, 185, 129, 0.05);
    }

    .p-select-all-checkbox input[type="checkbox"]:checked+.checkbox-custom {
      background: var(--brand-emerald);
      border-color: var(--brand-emerald);
    }

    .p-select-all-checkbox input[type="checkbox"]:checked+.checkbox-custom::after {
      content: "✓";
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      color: white;
      font-weight: bold;
      font-size: 11px;
    }

      .checkbox-label {
      font-family: 'Plus Jakarta Sans', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      font-size: 0.8rem;
      font-weight: 500;
      color: #64748b;
      letter-spacing: 0.01em;
    }

    /* Responsive Overrides */
    @media (max-width: 1100px) {
      .p-row {
        grid-template-columns: minmax(0, 1fr) 200px 180px;
        gap: 16px;
      }
    }

    @media (max-width: 900px) {
      .p-row {
        grid-template-columns: 1fr 1fr;
        gap: 20px;
      }

      .p-eq-info {
        grid-column: span 2;
      }

      .p-btn-detail {
        grid-column: span 2;
        width: 100%;
      }
    }

    @media (max-width: 600px) {
      .p-container {
        padding: 0 16px;
        margin-top: 20px;
      }

      .p-row {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
        padding: 24px 20px;
      }

      .p-eq-info,
      .p-pills,
      .p-photo-btn,
      .p-remark-input {
        width: 100%;
      }

      .p-photo-btn {
        max-width: 100%;
        height: 180px;
      }

      .p-footer {
        flex-direction: column;
        gap: 24px;
        text-align: center;
      }

      .p-footer select,
      .p-btn-submit {
        width: 100%;
      }

      .p-card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
      }

      .p-mini-select {
        width: 100%;
        justify-content: center;
      }
    }

    .p-legend {
      display: inline-flex;
      flex-wrap: wrap;
      gap: 24px;
      margin-top: 16px;
      margin-bottom: 32px;
      padding: 10px 24px;
      background: rgba(255, 255, 255, 0.8);
      backdrop-filter: blur(8px);
      border-radius: 100px;
      box-shadow: 0 4px 20px rgba(8, 127, 138, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.6);
      align-items: center;
    }

    .p-legend-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.8rem;
      font-weight: 700;
      color: #475569;
      font-family: var(--font-body);
      letter-spacing: 0.02em;
    }

    .p-legend-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.8);
    }

    /* Success Overlay Premium */
    .p-success-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.6);
      backdrop-filter: blur(8px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 2000;
      /* animation: fadeIn 0.4s ease; */
    }

    .p-success-card {
      background: white;
      padding: 40px;
      border-radius: 24px;
      text-align: center;
      max-width: 400px;
      width: 90%;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      /* animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1); */
    }

    /* @keyframes fadeIn {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    @keyframes slideUp {
      from {
        transform: translateY(20px);
        opacity: 0;
      }

      to {
        transform: translateY(0);
        opacity: 1;
      }
    } */

    .p-success-icon {
      width: 80px;
      height: 80px;
      background: var(--grad-success);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 40px;
      margin: 0 auto 24px;
      box-shadow: var(--shadow-success);
    }

    /* Accordion Styles for Equipment Types */
    .p-accordion-item {
      margin-bottom: 12px;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(8, 127, 138, 0.08);
      background: white;
      border: 1px solid rgba(8, 127, 138, 0.1);
    }

    .p-accordion-header {
      padding: 14px 20px;
      background: linear-gradient(to right, rgba(8, 127, 138, 0.04), rgba(255, 255, 255, 0.9));
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 12px;
      transition: all 0.3s ease;
      border-bottom: 1px solid transparent;
      user-select: none;
    }

    .p-accordion-header:hover {
      background: linear-gradient(to right, rgba(8, 127, 138, 0.08), rgba(255, 255, 255, 0.95));
    }

    .p-accordion-header.active {
      background: linear-gradient(to right, rgba(8, 127, 138, 0.06), rgba(255, 255, 255, 0.9));
      border-bottom-color: rgba(8, 127, 138, 0.15);
    }

    .p-accordion-chevron {
      width: 24px;
      height: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(8, 127, 138, 0.1);
      border-radius: 50%;
      transition: transform 0.3s ease;
      flex-shrink: 0;
    }

    .p-accordion-chevron i {
      font-size: 12px;
      color: var(--brand-teal-dark);
      font-weight: 900;
    }

    .p-accordion-header.active .p-accordion-chevron {
      transform: rotate(90deg);
      background: var(--brand-teal);
    }

    .p-accordion-header.active .p-accordion-chevron i {
      color: white;
    }

    .p-accordion-title-text {
      font-family: var(--font-heading);
      font-weight: 700;
      color: var(--brand-teal-dark);
      font-size: 0.9rem;
      letter-spacing: -0.01em;
      flex: 1;
    }

    .p-accordion-count {
      background: rgba(8, 127, 138, 0.1);
      color: var(--brand-teal);
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.05em;
    }

    .p-accordion-controls {
      display: flex;
      gap: 10px;
      align-items: center;
      margin-left: auto;
    }

    .p-photo-group-btn {
      width: 42px;
      height: 42px;
      border-radius: 8px;
      background: white;
      border: 1px dashed #cbd5e1;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s;
      position: relative;
      flex-shrink: 0;
    }

    .p-photo-group-btn:hover {
      border-color: var(--brand-teal);
      transform: scale(1.08);
      background: #f0f9ff;
    }

    .p-photo-group-btn .p-photo-placeholder i {
      font-size: 18px;
      color: #94a3b8;
    }

    .p-photo-group-btn img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 6px;
    }

    .p-accordion-content {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.4s ease;
    }

    .p-accordion-content.active {
      max-height: 5000px;
    }

    .p-accordion-body {
      padding: 0;
    }
  </style>
</head>

<body>
  <?php include 'includes/navbar.php'; ?>

  <div class="p-container">
    <!-- Filter Bar -->
    <div class="p-filter-bar">
      <form method="GET" class="p-filter-form" id="filterForm">
        <div class="p-search-box">
          <input type="text" name="search" placeholder="Cari peralatan..." value="<?= htmlspecialchars($search) ?>">
          <button type="submit">
            <i class="fa-solid fa-magnifying-glass"></i>
          </button>
        </div>
        <select name="category" class="p-filter-select p-select-kategori" onchange="this.form.section.value=''; this.form.submit()">
          <option value="">Kategori</option>
          <?php foreach ($categories as $c) { ?>
            <option value="<?= $c['parent_category'] ?>" <?= $filter_category == $c['parent_category'] ? 'selected' : '' ?>>
              <?= ucwords(strtolower($c['parent_category'])) ?>
            </option>
          <?php } ?>
        </select>
        <select name="section" class="p-filter-select p-select-jenis" onchange="this.form.submit()">
          <option value="">Semua Jenis Peralatan</option>
          <?php foreach ($section_list as $s) { ?>
            <?php if (!$filter_category || $s['parent_category'] == $filter_category) { ?>
              <option value="<?= $s['id'] ?>" <?= $filter_section == $s['id'] ? 'selected' : '' ?>>
                <?= ucwords(strtolower($s['nama_section'])) ?>
              </option>
            <?php } ?>
          <?php } ?>
        </select>
        <select name="lokasi" class="p-filter-select p-select-lokasi" onchange="this.form.submit()">
          <option value="">Semua Lokasi</option>
          <?php foreach ($lokasi_list as $l) { ?>
            <option value="<?= $l['id'] ?>" <?= $filter_lokasi == $l['id'] ? 'selected' : '' ?>>
              <?= ucwords(strtolower($l['nama_lokasi'])) ?>
            </option>
          <?php } ?>
        </select>
        <a href="monitoring.php" class="p-reset-btn">
          <i class="fa-solid fa-rotate-right"></i> Reset
        </a>
      </form>
    </div>

    <!-- Mini Legend -->
    <div class="p-legend">
      <div class="p-legend-item"><span class="p-legend-dot" style="background: var(--brand-emerald)"></span> <b>O</b>&nbsp;(Normal)</div>
      <div class="p-legend-item"><span class="p-legend-dot" style="background: var(--brand-amber)"></span> <b>-</b>&nbsp;(Menurun)</div>
      <div class="p-legend-item"><span class="p-legend-dot" style="background: var(--brand-rose)"></span> <b>X</b>&nbsp;(Terputus)</div>
      <div class="p-legend-item"><span class="p-legend-dot" style="background: var(--brand-indigo)"></span> <b>V</b>&nbsp;(Gangguan)</div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="checklistForm">
      <?php
      foreach ($grouped_equipments as $section_name => $equipment_types) {
        ?>
        <div class="p-accordion-item">
          <div class="p-accordion-header" onclick="toggleAccordion(this)">
            <div class="p-accordion-chevron">
              <i class="fas fa-chevron-right"></i>
            </div>
            <span class="p-accordion-title-text"><?= htmlspecialchars($section_name) ?></span>
            <span class="p-accordion-count"><?php 
              // Count total equipment in this section
              $total_count = 0;
              foreach ($equipment_types as $items) {
                $total_count += count($items);
              }
              echo $total_count;
            ?> Item</span>
            <div class="p-accordion-controls" onclick="event.stopPropagation()">
              <?php
              // Get first item from first equipment type to get section_id
              $first_group = reset($equipment_types);
              $first_item = reset($first_group);
              ?>
              <label class="p-photo-group-btn" title="Upload Foto untuk <?= htmlspecialchars($section_name) ?>">
                <input type="file" name="foto_section[<?= $first_item['section_id'] ?>]" accept="image/*"
                  style="display:none" onchange="previewGroupPhoto(this)">
                <div class="p-photo-placeholder">
                  <i class="fa-regular fa-images"></i>
                </div>
                <img src="" style="display:none">
                <div class="p-photo-remove" onclick="removeGroupPhoto(this, event)">✕</div>
              </label>
              <label class="p-select-all-checkbox">
                <input type="checkbox" onchange="selectGroupNormal(this)">
                <span class="checkbox-custom"></span>
                <span class="checkbox-label">Pilih Semua</span>
              </label>
            </div>
          </div>
          <div class="p-accordion-content">
            <div class="p-accordion-body">
              <?php
              // Flatten all equipment from all base_types in this section
              foreach ($equipment_types as $base_type => $items) {
                foreach ($items as $eq) {
              ?>
                <div class="p-row <?= ($eq['rs'] ?? 'O') == 'O' ? 'p-row-normal' : 'p-row-findings' ?>">
                  <div class="p-eq-info">
                    <span class="p-eq-name"><?= htmlspecialchars($eq['nama_peralatan']) ?></span>
                    <span class="p-eq-divider">|</span>
                    <span class="p-eq-loc"><?= htmlspecialchars($eq['nama_lokasi']) ?></span>
                  </div>
                  <div class="p-pills">
                    <?php foreach (['O' => 'o-pill', '-' => 'g-pill', 'X' => 'x-pill', 'V' => 'v-pill'] as $v => $cls) { ?>
                      <label class="p-pill">
                        <input type="radio" name="status[<?= $eq['id'] ?>]" value="<?= $v ?>"
                          class="status-radio-<?= $v == '-' ? 'minus' : $v ?> status-radio-<?= $v ?>" <?= ($eq['rs'] ?? 'O') == $v ? 'checked' : '' ?> onchange="updateRowState(this, <?= $eq['id'] ?>)">
                        <span class="<?= $cls ?>"><?= $v ?></span>
                      </label>
                    <?php } ?>
                  </div>

                  <?php
                  $current_status = $eq['rs'] ?? 'O';
                  if ($current_status != 'O') {
                    ?>
                    <div class="p-detail-col" style="display:block">
                      <a href="detail_temuan.php?id=<?= $eq['id'] ?>&status=<?= $current_status ?>"
                        class="p-btn-detail active <?= ($eq['photo_count'] > 0 || !empty($eq['rk'])) ? 'filled' : '' ?>">
                        <?php
                        if ($eq['photo_count'] > 0 || !empty($eq['rk'])) {
                          echo "DETAIL LENGKAP";
                        } else {
                          echo "DETAIL TEMUAN";
                        }
                        ?>
                      </a>
                    </div>
                  <?php } ?>
                </div>
              <?php 
                }
              } 
              ?>
            </div>
          </div>
        </div>
      <?php } ?>
      <?php if (empty($equipments)) { ?>
        <div
          style="text-align: center; padding: 60px; background: white; border-radius: 12px; color: #94a3b8; border: 1px dashed #e2e8f0;">
          Peralatan tidak ditemukan.</div>
      <?php } ?>

      <div style="display: flex; gap: 24px; align-items: flex-end;">
        <div class="p-card" style="flex: 1; margin-bottom: 0;">
          <div class="p-card-body" style="padding: 24px 0;">
            <div style="margin-bottom: 24px; padding-left: 20px;">
              <label
                style="font-family: var(--font-heading); font-size: 0.85rem; font-weight: 600; color: #64748b; letter-spacing: 0.02em; display: flex; align-items: center; gap: 8px;">
                Peralatan Dimonitor Oleh (Pilih Personel Bertugas):
              </label>
            </div>
            <div style="display: flex; flex-direction: column; gap: 12px; padding-left: 20px;" id="personnelList">
              <?php foreach ($personnel_list as $p) { ?>
                <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 4px 0;">
                  <input type="checkbox" name="personnel[]" value="<?= htmlspecialchars($p['nama_personnel']) ?>"
                    style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--brand-teal); border-radius: 4px; border: 2px solid #cbd5e1;">
                  <div style="display: flex; flex-direction: column;">
                    <strong
                      style="font-family: 'Plus Jakarta Sans'; font-size: 0.9rem; font-weight: 600; color: #334155;"><?= htmlspecialchars($p['nama_personnel']) ?></strong>
                    <span
                      style="font-family: 'Plus Jakarta Sans'; font-size: 0.7rem; color: #94a3b8; font-weight: 500; letter-spacing: 0.02em;"><?= ucwords(strtolower($p['jabatan'])) ?></span>
                  </div>
                </label>
              <?php } ?>
            </div>
            <div
              style="margin-top: 24px; display: flex; gap: 8px; align-items: center; padding-left: 20px; padding-right: 20px;">
              <div style="position: relative; flex: 2; max-width: 250px;">
                <input type="text" id="newPersonnelName" placeholder="Nama..."
                  style="width: 100%; padding: 10px 16px; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 0.85rem; font-family: 'Plus Jakarta Sans'; outline: none; transition: all 0.2s;"
                  onkeypress="if(event.key === 'Enter') { event.preventDefault(); document.getElementById('newPersonnelRole').focus(); }">
              </div>
              <div style="position: relative; flex: 1.5; max-width: 200px;">
                <input type="text" id="newPersonnelRole" placeholder="Sebagai..."
                  style="width: 100%; padding: 10px 16px; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 0.85rem; font-family: 'Plus Jakarta Sans'; outline: none; transition: all 0.2s;"
                  onkeypress="if(event.key === 'Enter') { event.preventDefault(); addPersonnel(); }">
              </div>
              <button type="button" onclick="addPersonnel()"
                style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;"
                onmouseover="this.style.background='#e2e8f0'; this.style.borderColor='#94a3b8'"
                onmouseout="this.style.background='#f1f5f9'; this.style.borderColor='#cbd5e1'">
                <i class="fa-solid fa-plus" style="font-size: 0.9rem;"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="p-footer"
          style="margin-top: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 12px; padding-bottom: 24px;">
          <button type="button" onclick="showConfirmModal()" class="p-btn-submit">SIMPAN DATA</button>
          <div id="personnelError"
            style="display: none; color: #ef4444; font-size: 0.85rem; font-weight: 600; margin-top: 4px; animation: shake 0.4s cubic-bezier(.36,.07,.19,.97) both;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 6px;"></i> Harap pilih minimal satu personel
            bertugas!
          </div>
        </div>
      </div>
  </div>
  </form>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
  <script>
    // Existing updateRowState, previewGroupPhoto, removeGroupPhoto functions...

    // ... (Keep existing functions)

    function updateRowState(radio, id) {
      // ... existing code ...
      <?= file_get_contents('c:\laragon\www\monitoring-equipment\user\checklist.php_snippet_updateRowState') ?? '' ?>
    // I should not replace the whole script tag if I can avoid it or I need to be careful.
    // The user prompt is just to update addPersonnel.
    // I will target the specific area.   }
  </script>

  </div>
  </form>
  </div>

  <?php if ($success) { ?>
    <div class="p-success-overlay" id="successOverlay" onclick="this.remove()">
      <div class="p-success-card" onclick="event.stopPropagation()">
        <div class="p-success-icon">✓</div>
        <h2 style="font-family: var(--font-heading); margin: 0 0 12px; font-size: 1.5rem; color: #1e293b;">Berhasil
          Disimpan!</h2>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 32px; line-height: 1.6;">Laporan pemeriksaan harian
          Anda telah aman tersimpan dalam sistem kami.</p>
        <div style="display: flex; gap: 12px;">
          <a href="rekap.php" class="p-btn-submit"
            style="flex: 1; text-align: center; text-decoration: none; background: white; color: var(--brand-primary); border: 1px solid var(--border-color);">LIHAT
            RIWAYAT</a>
          <button type="button" onclick="document.getElementById('successOverlay').remove()" class="p-btn-submit"
            style="flex: 1;">TUTUP</button>
        </div>
      </div>
    </div>
    <script>setTimeout(() => { const el = document.getElementById('successOverlay'); if (el) { el.style.opacity = '0'; el.style.transition = '0.5s'; setTimeout(() => el.remove(), 500); } }, 4000);</script>
  <?php } ?>

  <!-- Confirmation Modal -->
  <div class="p-modal-overlay" id="confirmModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); animation: fadeIn 0.3s ease;">
    <div class="p-modal-card"
      style="background: white; padding: 32px; border-radius: 24px; width: 90%; max-width: 400px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: zoomIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);">
      <div
        style="width: 64px; height: 64px; background: #ecfdf5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: #10b981; font-size: 32px;">
        <i class="fa-solid fa-floppy-disk"></i>
      </div>
      <h3 style="font-family: var(--font-heading); font-size: 1.25rem; color: #1e293b; margin: 0 0 12px;">Simpan
        Laporan?</h3>
      <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 24px; line-height: 1.5;">Pastikan semua data peralatan
        dan foto bukti sudah sesuai. Laporan tidak dapat diubah setelah disimpan.</p>

      <div
        style="margin-bottom: 24px; text-align: left; background: #f8fafc; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0;">
        <label style="display: flex; gap: 12px; align-items: flex-start; cursor: pointer;">
          <input type="checkbox" id="confirmCheckbox" onchange="toggleSubmitButton()"
            style="width: 18px; height: 18px; margin-top: 2px; accent-color: var(--brand-teal); cursor: pointer;">
          <span style="font-size: 0.85rem; color: #475569; line-height: 1.5; font-weight: 500;">Saya menyatakan bahwa
            data pemeriksaan ini sudah benar dan sesuai dengan kondisi lapangan.</span>
        </label>
      </div>

      <div style="display: flex; gap: 12px;">
        <button type="button" onclick="closeConfirmModal()"
          style="flex: 1; padding: 12px; border-radius: 12px; border: 1px solid #cbd5e1; background: white; color: #64748b; font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: 'Plus Jakarta Sans';">
          Kembali
        </button>
        <button type="button" id="submitBtnModal" onclick="submitChecklist()" disabled
          style="flex: 1; padding: 12px; border-radius: 12px; border: none; background: linear-gradient(135deg, #0d9488 0%, #115e59 100%); color: white; font-weight: 600; cursor: not-allowed; opacity: 0.5; transition: all 0.2s; font-family: 'Plus Jakarta Sans'; box-shadow: none;">
          Simpan
        </button>
      </div>
    </div>
  </div>

  <script>
      // Toggle Accordion
      function toggleAccordion(header) {
        const item = header.closest('.p-accordion-item');
        const content = item.querySelector('.p-accordion-content');
        const isActive = header.classList.contains('active');

        // Toggle current accordion
        header.classList.toggle('active');
        content.classList.toggle('active');
      }

      // Accordions start collapsed (no auto-expand)

      function updateRowState(radio, id) {
        const row = radio.closest('.p-row');
        let detailCol = row.querySelector('.p-detail-col');

        if (radio.value === 'O') {
          // Status NORMAL: Remove detail button if exists
          row.classList.remove('p-row-findings');
          row.classList.add('p-row-normal');

          if (detailCol) {
            detailCol.remove();
          }
        } else {
          // Status ABNORMAL: Show detail button
          row.classList.remove('p-row-normal');
          row.classList.add('p-row-findings');

          // Add detail button if not exists
          if (!detailCol) {
            const pillsDiv = row.querySelector('.p-pills');
            const detailHtml = `
            <div class="p-detail-col" style="display:block">
              <a href="detail_temuan.php?id=${id}&status=${radio.value}" class="p-btn-detail active">
                DETAIL TEMUAN
              </a>
            </div>
          `;
            pillsDiv.insertAdjacentHTML('afterend', detailHtml);
          } else {
            // Update existing button with new status
            const link = detailCol.querySelector('a');
            link.href = `detail_temuan.php?id=${id}&status=${radio.value}`;
          }

          // UNCHECK Select All checkbox in this section
          const card = row.closest('.p-card');
          const selectAllCheckbox = card.querySelector('.p-select-all-checkbox input[type="checkbox"]');
          if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
          }
        }

        // Check if all items in section are Normal, then re-check SELECT ALL
        if (radio.value === 'O') {
          const card = row.closest('.p-card');
          const allRadios = card.querySelectorAll('input[type="radio"]:checked');
          const allNormal = Array.from(allRadios).every(r => r.value === 'O');
          const selectAllCheckbox = card.querySelector('.p-select-all-checkbox input[type="checkbox"]');
          if (selectAllCheckbox && allNormal) {
            selectAllCheckbox.checked = true;
          }
        }
      }

      function previewPhoto(input) {
        const btn = input.closest('.p-photo-btn');
        const img = btn.querySelector('img');
        const placeholder = btn.querySelector('.p-photo-placeholder');
        const removeBtn = btn.querySelector('.p-photo-remove');

        if (input.files && input.files[0]) {
          const reader = new FileReader();
          reader.onload = function (e) {
            img.src = e.target.result;
            img.style.display = 'block';
            placeholder.style.display = 'none';
            removeBtn.style.display = 'flex';
            btn.style.borderStyle = 'solid';
            btn.style.borderColor = 'var(--brand-emerald)';
          }
          reader.readAsDataURL(input.files[0]);
        }
      }

      function removePhoto(btn, e) {
        e.preventDefault();
        e.stopPropagation();
        const parent = btn.closest('.p-photo-btn');
        const input = parent.querySelector('input');
        const img = parent.querySelector('img');
        const placeholder = parent.querySelector('.p-photo-placeholder');

        input.value = '';
        img.src = '';
        img.style.display = 'none';
        placeholder.style.display = 'block';
        btn.style.display = 'none';
        parent.style.borderStyle = 'dashed';
        parent.style.borderColor = '#e2e8f0';
      }

      function previewGroupPhoto(input) {
        const btn = input.closest('.p-photo-group-btn');
        const img = btn.querySelector('img');
        const placeholder = btn.querySelector('.p-photo-placeholder');
        const removeBtn = btn.querySelector('.p-photo-remove');

        if (input.files && input.files[0]) {
          const reader = new FileReader();
          reader.onload = function (e) {
            img.src = e.target.result;
            img.style.display = 'block';
            placeholder.style.display = 'none';
            removeBtn.style.display = 'flex';
            btn.style.borderStyle = 'solid';
            btn.style.borderColor = 'var(--brand-emerald)';
          }
          reader.readAsDataURL(input.files[0]);
        }
      }

      function removeGroupPhoto(btn, e) {
        e.preventDefault();
        e.stopPropagation();
        const parent = btn.closest('.p-photo-group-btn');
        const input = parent.querySelector('input');
        const img = parent.querySelector('img');
        const placeholder = parent.querySelector('.p-photo-placeholder');

        input.value = '';
        img.src = '';
        img.style.display = 'none';
        placeholder.style.display = 'block';
        btn.style.display = 'none';
        parent.style.borderStyle = 'dashed';
        parent.style.borderColor = '#e2e8f0';
      }

      function selectAllNormal(container = document) {
        container.querySelectorAll('.status-radio-O').forEach(r => {
          if (!r.checked) {
            r.checked = true;
            updateRowState(r, r.name.match(/\[(\d+)\]/)[1]);
          }
        });
      }

      function selectGroupNormal(checkbox) {
        const card = checkbox.closest('.p-card');
        if (checkbox.checked) {
          // Select all items to Normal
          selectAllNormal(card);
        }
        // If unchecked, do nothing (user can manually change individual items)
      }

      function addPersonnel() {
        const nameInput = document.getElementById('newPersonnelName');
        const roleInput = document.getElementById('newPersonnelRole');
        const name = nameInput.value.trim();
        const role = roleInput.value.trim();
        const container = document.getElementById('personnelList');

        if (name) {
          const fullValue = role ? `${name} (${role})` : name;
          const displayRole = role ? role : 'External / Other';

          const label = document.createElement('label');
          label.style.display = 'flex';
          label.style.alignItems = 'center';
          label.style.gap = '12px';
          label.style.cursor = 'pointer';
          label.style.padding = '6px 0';
          label.style.animation = 'fadeIn 0.3s ease';

          label.innerHTML = `
            <input type="checkbox" name="personnel[]" value="${fullValue}" checked
              style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--brand-teal); border-radius: 4px; border: 2px solid #cbd5e1;">
            <div style="display: flex; flex-direction: column;">
              <strong style="font-family: 'Plus Jakarta Sans'; font-size: 0.9rem; font-weight: 600; color: #334155;">${name}</strong>
              <span style="font-family: 'Plus Jakarta Sans'; font-size: 0.7rem; color: #94a3b8; font-weight: 500; letter-spacing: 0.02em;">${displayRole}</span>
            </div>
        `;

          container.appendChild(label);
          nameInput.value = '';
          roleInput.value = '';
          nameInput.focus();
        }
      }

      function showConfirmModal() {
        const personnelCheckboxes = document.querySelectorAll('input[name="personnel[]"]:checked');
        const errorMsg = document.getElementById('personnelError');

        if (personnelCheckboxes.length === 0) {
          // Show inline error
          if (errorMsg) {
            errorMsg.style.display = 'block';
            errorMsg.style.animation = 'shake 0.4s cubic-bezier(.36,.07,.19,.97) both';
            setTimeout(() => { errorMsg.style.animation = ''; }, 400);
          }
          return;
        }

        // Hide error if valid
        if (errorMsg) errorMsg.style.display = 'none';

        document.getElementById('confirmModal').style.display = 'flex';
      }

      function closeConfirmModal() {
        document.getElementById('confirmModal').style.display = 'none';
      }

      function submitChecklist() {
        const form = document.getElementById('checklistForm');
        const submitBtn = document.createElement('input');
        submitBtn.type = 'hidden';
        submitBtn.name = 'save';
        submitBtn.value = '1';
        form.appendChild(submitBtn);
        form.submit();
      }

      function toggleSubmitButton() {
        const checkbox = document.getElementById('confirmCheckbox');
        const btn = document.getElementById('submitBtnModal');

        if (checkbox.checked) {
          btn.disabled = false;
          btn.style.cursor = 'pointer';
          btn.style.opacity = '1';
          btn.style.boxShadow = '0 4px 12px rgba(13, 148, 136, 0.3)';
        } else {
          btn.disabled = true;
          btn.style.cursor = 'not-allowed';
          btn.style.opacity = '0.5';
          btn.style.boxShadow = 'none';
        }
      }
  </script>
</body>

</html>