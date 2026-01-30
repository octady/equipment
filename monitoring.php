<?php
include "config/database.php";
include "config/auth.php";

// --- AUTO-SETUP & MIGRATION ---
$conn->query("CREATE TABLE IF NOT EXISTS dokumentasi_masalah (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT NOT NULL,
    foto_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inspection_id) REFERENCES monitoring(id) ON DELETE CASCADE
)");

// Migrate existing single photos to new table if they haven't been migrated
$res_mig = $conn->query("SELECT id, foto FROM monitoring WHERE foto IS NOT NULL AND foto != ''");
while ($row_mig = $res_mig->fetch_assoc()) {
  $check = $conn->prepare("SELECT id FROM dokumentasi_masalah WHERE inspection_id = ? AND foto_path = ?");
  $check->bind_param("is", $row_mig['id'], $row_mig['foto']);
  $check->execute();
  if ($check->get_result()->num_rows == 0) {
    $ins = $conn->prepare("INSERT INTO dokumentasi_masalah (inspection_id, foto_path) VALUES (?, ?)");
    $ins->bind_param("is", $row_mig['id'], $row_mig['foto']);
    $ins->execute();
  }
}
// ------------------------------

$today = date('Y-m-d');
$check_submitted = $conn->query("SELECT id FROM monitoring WHERE tanggal = '$today' LIMIT 1");
$is_submitted = ($check_submitted && $check_submitted->num_rows > 0);
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
  // DEBUG: Log submission attempt
  file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - Saving Checklist. Count: " . count($_POST['status'] ?? []) . "\n", FILE_APPEND);

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
  // Merge IDs from status and findings
  $ids_to_check = array_keys($_POST['status'] ?? []);
  if (isset($_POST['findings'])) {
    $ids_to_check = array_merge($ids_to_check, array_keys($_POST['findings']));
  }
  $ids_to_check = array_unique($ids_to_check);

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

  // Process Findings (Deferred Details)
  $processed_ids = [];
  if (isset($_POST['findings']) && is_array($_POST['findings'])) {
    foreach ($_POST['findings'] as $eq_id => $data) {
      $status = $data['status'];
      $ket = $data['keterangan'] ?? '';
      $processed_ids[] = $eq_id;

      // DB Logic (Insert/Update)
      $stmt_exists = $conn->prepare("SELECT id, foto, keterangan FROM monitoring WHERE equipment_id = ? AND tanggal = ?");
      $stmt_exists->bind_param("is", $eq_id, $today);
      $stmt_exists->execute();
      $exists = $stmt_exists->get_result()->fetch_assoc();

      if ($exists) {
        $inspection_id = $exists['id'];
        $stmt = $conn->prepare("UPDATE monitoring SET status = ?, keterangan = ?, checked_by = ? WHERE id = ?");
        $stmt->bind_param("sssi", $status, $ket, $final_checked_by, $inspection_id);
      } else {
        $stmt = $conn->prepare("INSERT INTO monitoring (equipment_id, tanggal, status, keterangan, checked_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $eq_id, $today, $status, $ket, $final_checked_by);
      }
      $stmt->execute();
      if (!$exists)
        $inspection_id = $conn->insert_id;

      // Save Deferred Photos
      if (isset($data['photos']) && is_array($data['photos'])) {
        foreach ($data['photos'] as $path) {
          // Path is already relative (assets/uploads/...)
          $stmt_ph = $conn->prepare("INSERT INTO dokumentasi_masalah (inspection_id, foto_path) VALUES (?, ?)");
          $stmt_ph->bind_param("is", $inspection_id, $path);
          $stmt_ph->execute();
        }
      }
    }
  }

  // Process Standard Status (excluding already processed)
  if (isset($_POST['status']) && is_array($_POST['status'])) {
    foreach ($_POST['status'] as $eq_id => $status) {
      if (in_array($eq_id, $processed_ids))
        continue;

      $ket = $_POST['keterangan'][$eq_id] ?? '';

      $stmt_exists = $conn->prepare("SELECT id, foto, keterangan FROM monitoring WHERE equipment_id = ? AND tanggal = ?");
      $stmt_exists->bind_param("is", $eq_id, $today);
      $stmt_exists->execute();
      $exists = $stmt_exists->get_result()->fetch_assoc();

      if ($exists) {
        $inspection_id = $exists['id'];
        $final_ket = (!empty($ket)) ? $ket : $exists['keterangan'];
        $stmt = $conn->prepare("UPDATE monitoring SET status = ?, keterangan = ?, checked_by = ? WHERE id = ?");
        $stmt->bind_param("sssi", $status, $final_ket, $final_checked_by, $inspection_id);
      } else {
        $stmt = $conn->prepare("INSERT INTO monitoring (equipment_id, tanggal, status, keterangan, checked_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $eq_id, $today, $status, $ket, $final_checked_by);
      }
      if (!$stmt->execute()) {
        file_put_contents('debug_log.txt', "SQL Error (Eq $eq_id): " . $stmt->error . "\n", FILE_APPEND);
      }
      if (!$exists)
        $inspection_id = $conn->insert_id;

      // Handle Inline Photo for 'O' or anyone using the inline input
      if (isset($_FILES['foto']['name'][$eq_id]) && $_FILES['foto']['error'][$eq_id] == 0) {
        $ext = pathinfo($_FILES['foto']['name'][$eq_id], PATHINFO_EXTENSION);
        $filename = "insp_inline_" . $eq_id . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['foto']['tmp_name'][$eq_id], $upload_dir . $filename)) {
          $new_foto_path = "assets/uploads/inspections/" . $filename;
          $stmt_ph = $conn->prepare("INSERT INTO dokumentasi_masalah (inspection_id, foto_path) VALUES (?, ?)");
          $stmt_ph->bind_param("is", $inspection_id, $new_foto_path);
          $stmt_ph->execute();
        }
      }
      // Handle Section Photo Linkage (Only if status is Normal and no individual photo uploaded)
      elseif ($status == 'O' && isset($eq_map[$eq_id])) {
        $s_id = $eq_map[$eq_id];
        if (isset($section_photos[$s_id])) {
          $s_path = $section_photos[$s_id];
          $stmt_ph = $conn->prepare("INSERT INTO dokumentasi_masalah (inspection_id, foto_path) VALUES (?, ?)");
          $stmt_ph->bind_param("is", $inspection_id, $s_path);
          $stmt_ph->execute();
        }
      }
    }
  }

  header("Location: monitoring.php?success=1");
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
/* Search filter removed for client-side search */

$query = "SELECT e.*, s.nama_section, s.parent_category, l.nama_lokasi, 
                 i.status as rs, i.keterangan as rk, 
                 (SELECT COUNT(*) FROM dokumentasi_masalah WHERE inspection_id = i.id) as photo_count
          FROM equipments e 
          JOIN sections s ON e.section_id = s.id 
          JOIN lokasi l ON e.lokasi_id = l.id 
          LEFT JOIN monitoring i ON e.id = i.equipment_id AND i.tanggal = '$today'
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
  <style>
    body.locked {
      background-color: #f1f5f9;
    }
    body.locked .p-card, body.locked .p-header, body.locked .p-legend {
      opacity: 0.8;
      pointer-events: none;
      filter: grayscale(0.5);
    }
    body.locked .locked-message {
      display: block !important;
    }
    .locked-message {
        display: none;
        background: #fff;
        border: 1px solid #cbd5e1;
        border-left: 4px solid #087F8A;
        padding: 20px;
        margin-bottom: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        text-align: center;
    }
    .locked-message h3 {
        margin: 0 0 8px;
        color: #1e293b;
        font-size: 18px;
    }
    .locked-message p {
        margin: 0 0 16px;
        color: #64748b;
        font-size: 14px;
    }
    .locked-btn {
        display: inline-block;
        background: #087F8A;
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.2s;
    }
    .locked-btn:hover {
        background: #065C63;
        transform: translateY(-1px);
    }
  </style>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/monitoring.css">
</head>

<body class="<?= $is_submitted ? 'locked' : '' ?>">
  <?php include 'includes/navbar.php'; ?>

  <div class="p-container">
    
    <!-- LOCKED MESSAGE -->
    <div class="locked-message">
        <h3><i class="fa-solid fa-lock"></i> Laporan Hari Ini Telah Disubmit</h3>
        <p>Anda sudah melakukan submit data monitoring untuk hari ini. Jika ingin mengubah data, silakan lakukan di halaman Riwayat Monitoring.</p>
        <a href="riwayat_monitoring.php" class="locked-btn"><i class="fa-solid fa-clock-rotate-left"></i> Ke Riwayat Monitoring</a>
    </div>
    <!-- Filter Bar -->
    <div class="p-card" style="padding: 16px 24px; display: flex; gap: 16px; align-items: center;">
      <form method="GET" style="display: flex; gap: 12px; flex: 1;" id="filterForm">
        <div style="position: relative; flex: 1;">
          <input type="text" id="searchInput" name="search" placeholder="Cari"
            value="<?= htmlspecialchars($search ?? '') ?>"
            style="width: 100%; padding: 8px 36px 8px 16px; border-radius: 12px; border: 1.5px solid #e2e8f0; font-size: 0.85rem; transition: border-color 0.2s;"
            onkeyup="filterChecklist()" onkeydown="if(event.key === 'Enter') event.preventDefault();">
          <button type="button" onclick="filterChecklist()"
            style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 4px; cursor: pointer; color: #94a3b8; display: flex; align-items: center; justify-content: center; transition: color 0.2s;">
            <i class="fa-solid fa-magnifying-glass"></i>
          </button>
        </div>
        <select id="checklistStatusFilter" onchange="filterByChecklistStatus(this.value)"
          style="padding: 8px 16px; border-radius: 12px; border: 1.5px solid #e2e8f0; font-size: 0.85rem; cursor: pointer; background: white;">
          <option value="">Semua</option>
          <option value="checked">Sudah Dichecklist</option>
          <option value="unchecked">Belum Dichecklist</option>
        </select>
        <select name="section" onchange="this.form.submit()"
          style="padding: 8px 16px; border-radius: 12px; border: 1.5px solid #e2e8f0; font-size: 0.85rem; cursor: pointer; background: white;">
          <option value="">Semua Jenis Peralatan</option>
          <?php foreach ($section_list as $s) { ?>
            <?php if (!$filter_category || $s['parent_category'] == $filter_category) { ?>
              <option value="<?= $s['id'] ?>" <?= $filter_section == $s['id'] ? 'selected' : '' ?>>
                <?= ucwords(strtolower($s['nama_section'])) ?>
              </option>
            <?php } ?>
          <?php } ?>
        </select>
        <select name="lokasi" onchange="this.form.submit()"
          style="padding: 8px 16px; border-radius: 12px; border: 1.5px solid #e2e8f0; font-size: 0.85rem; cursor: pointer; background: white;">
          <option value="">Semua Lokasi</option>
          <?php foreach ($lokasi_list as $l) { ?>
            <option value="<?= $l['id'] ?>" <?= $filter_lokasi == $l['id'] ? 'selected' : '' ?>>
              <?= ucwords(strtolower($l['nama_lokasi'])) ?>
            </option>
          <?php } ?>
        </select>
        <a href="monitoring.php"
          style="color: #087F8A; text-decoration: none; font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 4px; transition: color 0.3s; white-space: nowrap;"
          onmouseover="this.style.color='#065C63';" onmouseout="this.style.color='#087F8A';">
          ↻ Reset
        </a>
      </form>
    </div>

    <!-- Mini Legend -->
    <div class="p-legend">
      <div class="p-legend-item"><span class="p-legend-dot" style="background: #10b981"></span> <b>O</b>
        &nbsp;(Normal)</div>
      <div class="p-legend-item"><span class="p-legend-dot" style="background: #f59e0b"></span> <b>-</b>
        &nbsp;(Menurun)</div>
      <div class="p-legend-item"><span class="p-legend-dot" style="background: #ef4444"></span> <b>X</b>
        &nbsp;(Terputus)</div>
      <div class="p-legend-item"><span class="p-legend-dot" style="background: #8b5cf6"></span> <b>V</b>
        &nbsp;(Gangguan)</div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="checklistForm">
      <?php
      foreach ($grouped_equipments as $section_name => $equipment_types) {
        ?>
        <div class="p-card">
          <div class="p-card-header" onclick="toggleSection(this);"
            style="cursor: pointer; position: relative; z-index: 1;">
            <div style="display:flex; align-items: center; gap: 10px;">
              <i class="fa-solid fa-chevron-right p-chevron"
                style="color: var(--brand-teal); transition: transform 0.3s;"></i>
              <div style="display:flex; flex-direction:column;">
                <span class="p-section-title"><?= htmlspecialchars($section_name) ?></span>
                <span class="p-section-subtitle">JENIS PERALATAN</span>
              </div>
            </div>
            <div style="display:flex; gap:12px; align-items:center;" onclick="event.stopPropagation()">
              <?php
              // Get first item from first equipment type to get section_id
              $first_group = reset($equipment_types);
              $first_item = reset($first_group);
              ?>
              <label class="p-photo-group-btn">
                <input type="file" name="foto_section[<?= $first_item['section_id'] ?>]" accept="image/*"
                  style="display:none" onchange="previewGroupPhoto(this)">
                <div class="p-photo-placeholder">
                  <img src="../assets/img/upload_icon.png"
                    style="width: 28px; height: 28px; object-fit: contain; filter: grayscale(100%) opacity(0.6);">
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
          <div class="p-card-body" style="display: none;">
            <?php
            foreach ($equipment_types as $base_type => $items) {
              // Check if all are normal
              $all_normal = true;
              foreach ($items as $item) {
                if (($item['rs'] ?? 'O') != 'O') {
                  $all_normal = false;
                  break;
                }
              }
              ?>
              <div class="p-category-group">

                <?php foreach ($items as $eq) { ?>
                  <div class="p-row <?= ($eq['rs'] ?? 'O') == 'O' ? 'p-row-normal' : 'p-row-findings' ?>">
                    <div class="p-eq-info">
                      <div class="p-eq-name"><?= htmlspecialchars($eq['nama_peralatan']) ?></div>
                      <div class="p-eq-loc"><?= htmlspecialchars($eq['nama_lokasi']) ?></div>
                    </div>
                    <div class="p-pills">
                      <?php foreach (['O' => 'o-pill', '-' => 'g-pill', 'X' => 'x-pill', 'V' => 'v-pill'] as $v => $cls) { ?>
                        <label class="p-pill">
                          <input type="radio" name="status[<?= $eq['id'] ?>]" value="<?= $v ?>"
                            class="status-radio-<?= $v == '-' ? 'minus' : $v ?> status-radio-<?= $v ?>" <?= ($eq['rs'] ?? '') == $v ? 'checked' : '' ?> onchange="updateRowState(this, <?= $eq['id'] ?>)">
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
                <?php } ?>
              </div>
            <?php } ?>
          </div>
        </div>
      <?php } ?>
      <?php if (empty($equipments)) { ?>
        <div
          style="text-align: center; padding: 60px; background: white; border-radius: 12px; color: #94a3b8; border: 1px dashed #e2e8f0;">
          Peralatan tidak ditemukan.</div>
      <?php } ?>
      <!-- Personnel Selection Card -->
      <div class="p-card" style="margin-bottom: 24px;">
        <div class="p-card-header" style="cursor: default;">
          <div style="display:flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-users" style="color: var(--brand-teal); font-size: 1rem;"></i>
            <div style="display:flex; flex-direction:column;">
              <span class="p-section-title">Personel Bertugas</span>
              <span class="p-section-subtitle">Pilih yang melakukan monitoring</span>
            </div>
          </div>
        </div>
        <div class="p-card-body" style="display: block; padding: 24px;">
          <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px;" id="personnelList">
            <?php foreach ($personnel_list as $p) { ?>
              <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 12px 16px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; transition: all 0.2s;">
                <input type="checkbox" name="personnel[]" value="<?= htmlspecialchars($p['nama_personnel']) ?>"
                  style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--brand-teal); border-radius: 4px;">
                <div style="display: flex; flex-direction: column;">
                  <strong style="font-family: 'Plus Jakarta Sans'; font-size: 0.9rem; font-weight: 600; color: #334155;"><?= htmlspecialchars($p['nama_personnel']) ?></strong>
                  <span style="font-family: 'Plus Jakarta Sans'; font-size: 0.7rem; color: #94a3b8; font-weight: 500;"><?= ucwords(strtolower($p['jabatan'])) ?></span>
                </div>
              </label>
            <?php } ?>
          </div>
          
          <!-- Add Personnel Row -->
          <div style="margin-top: 20px; display: flex; gap: 10px; align-items: center; padding-top: 16px; border-top: 1px dashed #e2e8f0;">
            <div style="flex: 1; max-width: 240px;">
              <input type="text" id="newPersonnelName" placeholder="Nama..."
                style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.85rem; font-family: 'Plus Jakarta Sans'; outline: none; background: white;"
                onkeypress="if(event.key === 'Enter') { event.preventDefault(); document.getElementById('newPersonnelRole').focus(); }">
            </div>
            <div style="flex: 1; max-width: 180px;">
              <input type="text" id="newPersonnelRole" placeholder="Sebagai..."
                style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.85rem; font-family: 'Plus Jakarta Sans'; outline: none; background: white;"
                onkeypress="if(event.key === 'Enter') { event.preventDefault(); addPersonnel(); }">
            </div>
            <button type="button" onclick="addPersonnel()"
              style="background: var(--brand-teal); border: none; color: white; width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;"
              onmouseover="this.style.background='#065C63';"
              onmouseout="this.style.background='var(--brand-teal)';">
              <i class="fa-solid fa-plus" style="font-size: 0.9rem;"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Submit Button Section -->
      <?php if(!$is_submitted): ?>
      <div style="display: flex; justify-content: flex-end; align-items: center; gap: 16px; margin-bottom: 40px;">
        <div id="personnelError"
          style="display: none; color: #ef4444; font-size: 0.85rem; font-weight: 600; animation: shake 0.4s cubic-bezier(.36,.07,.19,.97) both;">
          <i class="fa-solid fa-circle-exclamation" style="margin-right: 6px;"></i> Harap pilih minimal satu personel bertugas!
        </div>
        <button type="button" onclick="showConfirmModal()" class="p-btn-submit" style="padding: 14px 32px; font-size: 0.9rem;">
          <i class="fa-solid fa-floppy-disk" style="margin-right: 8px;"></i>SIMPAN DATA
        </button>
      </div>
      <?php endif; ?>
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
      // I will target the specific area.
    }
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
        <button type="button" onclick="document.getElementById('successOverlay').remove()" class="p-btn-submit"
          style="width:100%">TUTUP</button>
      </div>
    </div>
    <script>
      setTimeout(() => { 
        const el = document.getElementById('successOverlay'); 
        if (el) { 
          el.style.opacity = '0'; 
          el.style.transition = '0.5s'; 
          setTimeout(() => {
            el.remove();
            window.location.href = 'riwayat_monitoring.php'; // Redirect after saved
          }, 500); 
        } 
      }, 2000); // 2 seconds delay
    </script>
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
      // Get the preview img (direct child, not inside placeholder)
      const img = btn.querySelector(':scope > img');
      const placeholder = btn.querySelector('.p-photo-placeholder');
      const removeBtn = btn.querySelector('.p-photo-remove');

      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
          img.src = e.target.result;
          img.style.display = 'block';
          img.style.width = '100%';
          img.style.height = '100%';
          img.style.objectFit = 'cover';
          img.style.borderRadius = '8px';
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
      // Get the preview img (direct child, not inside placeholder)
      const img = parent.querySelector(':scope > img');
      const placeholder = parent.querySelector('.p-photo-placeholder');

      input.value = '';
      img.src = '';
      img.style.display = 'none';
      placeholder.style.display = 'flex';
      btn.style.display = 'none';
      parent.style.borderStyle = 'dashed';
      parent.style.borderColor = '#e2e8f0';
    }

    function selectAllNormal(container = document) {
      container.querySelectorAll('.status-radio-O').forEach(r => {
        if (!r.checked) {
          r.checked = true;
          updateRowState(r, r.name.match(/\[(\d+)\]/)[1]);
          
          // Trigger change event manually to trigger auto-save
          const event = new Event('change', { bubbles: true });
          r.dispatchEvent(event);
        }
      });
    }

    function selectGroupNormal(checkbox) {
      const card = checkbox.closest('.p-card');
      if (checkbox.checked) {
        // Select all items to Normal
        selectAllNormal(card);
      } else {
        // Uncheck all - clear all radio selections in this section
        card.querySelectorAll('input[type="radio"]:checked').forEach(r => {
          r.checked = false;
        });
        // Also remove any row styling
        card.querySelectorAll('.p-row').forEach(row => {
          row.classList.remove('p-row-normal', 'p-row-findings');
          // Remove detail buttons if any
          const detailCol = row.querySelector('.p-detail-col');
          if (detailCol) detailCol.remove();
        });
      }
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

    function toggleSection(header) {
      var body = header.nextElementSibling;
      var chevron = header.querySelector('.p-chevron');

      // Get computed style to check actual display
      var computedDisplay = window.getComputedStyle(body).display;

      if (computedDisplay === 'none') {
        body.setAttribute('style', 'display: block !important;');
        if (chevron) chevron.style.transform = 'rotate(90deg)';
      } else {
        body.setAttribute('style', 'display: none !important;');
        if (chevron) chevron.style.transform = 'rotate(0deg)';
      }
    }

    function filterChecklist() {
      const input = document.getElementById('searchInput');
      const filter = input.value.toLowerCase();
      const cards = document.querySelectorAll('.p-card');

      cards.forEach(card => {
        // Skip filter card (the first one with filterForm)
        if (card.querySelector('#filterForm')) return;

        const sectionTitle = card.querySelector('.p-section-title')?.innerText.toLowerCase() || '';
        const rows = card.querySelectorAll('.p-row');
        let hasVisibleRow = false;

        // Check each row in this section
        rows.forEach(row => {
          const eqName = row.querySelector('.p-eq-name')?.innerText.toLowerCase() || '';
          const eqLoc = row.querySelector('.p-eq-loc')?.innerText.toLowerCase() || '';

          const isMatch = eqName.includes(filter) || eqLoc.includes(filter);

          if (isMatch) {
            row.style.display = ''; // Reset to default (grid)
            hasVisibleRow = true;
          } else {
            // Only hide if section title doesn't match either
            // If section title matches, we might want to show all children? 
            // Usually search filters children strictly. Let's stick to strict row filtering.
            // UNLESS the search matches the Section Title, in which case maybe we show all?
            // Let's implement: Matches Row OR (Matches Section AND filter is not empty) -> Show

            if (sectionTitle.includes(filter) && filter.length > 0) {
              row.style.display = '';
              hasVisibleRow = true;
            } else {
              row.style.display = 'none';
            }
          }
        });

        // Toggle Section Visibility
        if (hasVisibleRow) {
          card.style.display = '';

          // Auto-expand if searching
          if (filter.length > 0) {
            const body = card.querySelector('.p-card-body');
            const chevron = card.querySelector('.p-chevron');
            if (body) body.setAttribute('style', 'display: block !important;');
            if (chevron) chevron.style.transform = 'rotate(90deg)';
          }
        } else {
          card.style.display = 'none';
        }
      });

      // Handle empty results message logic if needed, or simply let it be empty
    }

    // ============================================
    // FILTER BY CHECKLIST STATUS
    // ============================================
    function filterByChecklistStatus(status) {
      const today = new Date().toISOString().split('T')[0];
      
      // Get checked items from localStorage
      const localChecked = new Set();
      
      // Read from checklist_state_v1
      try {
        const checklistState = localStorage.getItem('checklist_state_v1');
        if (checklistState) {
          const data = JSON.parse(checklistState);
          if (data.date === today) {
            Object.keys(data).forEach(key => {
              const match = key.match(/^status\[(\d+)\]$/);
              if (match) {
                localChecked.add(match[1]);
              }
            });
          }
        }
      } catch (e) {
        console.error('Error reading checklist state:', e);
      }
      
      // Read from draft_detail_*
      try {
        Object.keys(localStorage).forEach(key => {
          if (key.startsWith('draft_detail_')) {
            const data = JSON.parse(localStorage.getItem(key));
            if (data.date === today && data.eq_id) {
              localChecked.add(String(data.eq_id));
            }
          }
        });
      } catch (e) {
        console.error('Error reading draft details:', e);
      }
      
      // Filter equipment rows
      const allRows = document.querySelectorAll('.p-row');
      let visibleCount = 0;
      
      allRows.forEach(row => {
        // Get equipment ID from radio button name
        const radioInput = row.querySelector('input[type="radio"][name^="status"]');
        if (!radioInput) return;
        
        const match = radioInput.name.match(/status\[(\d+)\]/);
        if (!match) return;
        
        const equipId = match[1];
        const isChecked = localChecked.has(equipId) || row.querySelector('input[type="radio"]:checked');
        
        // Apply filter
        if (!status || status === '') {
          // Show all
          row.style.display = '';
          visibleCount++;
        } else if (status === 'checked') {
          // Show only checked
          row.style.display = isChecked ? '' : 'none';
          if (isChecked) visibleCount++;
        } else if (status === 'unchecked') {
          // Show only unchecked
          row.style.display = isChecked ? 'none' : '';
          if (!isChecked) visibleCount++;
        }
      });
      
      // Show/hide ONLY equipment sections (cards that have .p-row elements)
      // Don't hide header/filter sections
      document.querySelectorAll('.p-card').forEach(card => {
        const rows = card.querySelectorAll('.p-row');
        // Only process cards that actually contain equipment rows
        if (rows.length > 0) {
          const hasVisibleRow = Array.from(rows).some(row => row.style.display !== 'none');
          card.style.display = hasVisibleRow ? '' : 'none';
        }
        // Cards without .p-row (like headers) are left unchanged
      });
      
      console.log(`Filter: ${status || 'all'}, Visible: ${visibleCount}`);
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
    // ============================================
    // PERSISTENCE & DEFERRED SUBMISSION LOGIC
    // ============================================

    const LS_CHECKLIST_KEY = 'checklist_state_v1';

    // Save Scroll State
    function saveScrollState() {
      localStorage.setItem('checklistScrollPos', window.scrollY);
    }

    window.addEventListener('beforeunload', () => {
      saveScrollState();
    });

    // Save state when clicking Detail buttons
    document.addEventListener('click', (e) => {
      if (e.target.closest('.p-btn-detail')) {
        saveScrollState();
      }
    });

    // 1. Restore State & Details on Load
    document.addEventListener('DOMContentLoaded', () => {
      // Defer restoration to end of event queue to prevent override by other scripts
      setTimeout(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const referrer = document.referrer;

        // Check if coming from detail page
        const fromDetailPage = referrer.includes('detail_temuan.php');

        // Clear storage if success (submitted)
        if (urlParams.has('success')) {
          localStorage.removeItem(LS_CHECKLIST_KEY);
          localStorage.removeItem('checklistScrollPos');
          Object.keys(localStorage).forEach(key => {
            if (key.startsWith('draft_detail_')) localStorage.removeItem(key);
          });
          return;
        }

        // REMOVED: Old logic that cleared localStorage when navigating from other pages
        // This was preventing auto-save from working properly

        // DAILY RESET CHECK FOR ALL AUTO-SAVE DATA
        const today = new Date().toISOString().split('T')[0];
        
        // Check main checklist state
        const savedRaw = localStorage.getItem(LS_CHECKLIST_KEY);
        if (savedRaw) {
          try {
            const savedData = JSON.parse(savedRaw);
            // If date is missing (old data) or different from today -> RESET
            if (!savedData.date || savedData.date !== today) {
              console.log('New day detected! Clearing main checklist storage');
              localStorage.removeItem(LS_CHECKLIST_KEY);
              localStorage.removeItem('checklistScrollPos');
            }
          } catch (e) { console.error('Error checking main checklist date', e); }
        }
        
        // Check all draft_detail keys for daily reset
        Object.keys(localStorage).forEach(key => {
          if (key.startsWith('draft_detail_')) {
            try {
              const draftData = JSON.parse(localStorage.getItem(key));
              // If date is missing or different from today -> CLEAR
              if (!draftData.date || draftData.date !== today) {
                console.log(`Clearing old draft detail: ${key}`);
                localStorage.removeItem(key);
              }
            } catch (e) {
              // Invalid data, clear it
              localStorage.removeItem(key);
            }
          }
        });
        
        // If we cleared main state, return early to skip restoration
        if (!localStorage.getItem(LS_CHECKLIST_KEY)) {
          // Still restore drafts if they exist for today
        }

        // Restore Scroll only
        const lastScroll = localStorage.getItem('checklistScrollPos');
        if (lastScroll) {
          setTimeout(() => {
            window.scrollTo({
              top: parseInt(lastScroll),
              behavior: 'instant'
            });
          }, 200);
        }

        // Restore Checkbox/Radio State
        const savedState = JSON.parse(localStorage.getItem(LS_CHECKLIST_KEY) || '{}');
        let restoredCount = 0;
        
        Object.keys(savedState).forEach(name => {
          const val = savedState[name];
          // Handle standard inputs
          const inputs = document.getElementsByName(name);
          if (inputs.length) {
            if (inputs[0].type === 'radio') {
              inputs.forEach(r => {
                if (r.value === val) {
                  r.checked = true;
                  const eqId = name.match(/\[(\d+)\]/);
                  if (eqId && eqId[1]) updateRowState(r, eqId[1]);
                  restoredCount++;
                }
              });
            } else if (inputs[0].type === 'checkbox' && name !== 'personnel[]') {
              if (val === true || val === 'true') inputs[0].checked = true;
            }
          }
        });
        
        // Specific restore for personnel[] array
        if (savedState.personnel_selection && Array.isArray(savedState.personnel_selection)) {
           const checkboxes = document.getElementsByName('personnel[]');
           checkboxes.forEach(cb => {
              if (savedState.personnel_selection.includes(cb.value)) {
                 cb.checked = true;
              }
           });
        }

        // Restore Draft Details
        const form = document.getElementById('checklistForm');
        Object.keys(localStorage).forEach(key => {
          if (key.startsWith('draft_detail_')) {
            try {
              const data = JSON.parse(localStorage.getItem(key));
              if (data && data.eq_id) {
                applyDraftDetail(data, form);
              }
            } catch (e) { console.error('Error parsing draft', e); }
          }
        });
        
        // Show restore notification if data was restored
        if (restoredCount > 0 || savedState.personnel_selection) {
          setTimeout(() => {
            const statusCount = restoredCount > 0 ? ` (${restoredCount} item)` : '';
            showAutoSaveToast(`Data sebelumnya dikembalikan${statusCount}`, true);
          }, 500);
        }
      }, 0); // End setTimeout - run after other scripts
    });

    // Auto-Save Toast Notification
    function showAutoSaveToast(message, isRestore = false) {
      // Remove existing toast if any
      const existingToast = document.getElementById('autoSaveToast');
      if (existingToast) existingToast.remove();

      const toast = document.createElement('div');
      toast.id = 'autoSaveToast';
      toast.style.cssText = `
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: ${isRestore ? 'linear-gradient(135deg, #0d9488 0%, #115e59 100%)' : 'linear-gradient(135deg, #10b981 0%, #059669 100%)'};
        color: white;
        padding: 14px 20px 14px 16px;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        gap: 10px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 14px;
        font-weight: 600;
        z-index: 10000;
        animation: slideInRight 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        cursor: pointer;
      `;
      
      toast.innerHTML = `
        <i class="fa-solid ${isRestore ? 'fa-rotate-left' : 'fa-cloud-arrow-up'}" style="font-size: 18px;"></i>
        <span>${message}</span>
      `;
      
      document.body.appendChild(toast);
      
      // Auto-remove after 3 seconds
      setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease-out forwards';
        setTimeout(() => toast.remove(), 300);
      }, 3000);
      
      // Remove on click
      toast.addEventListener('click', () => {
        toast.style.animation = 'slideOutRight 0.3s ease-out forwards';
        setTimeout(() => toast.remove(), 300);
      });
    }

    // Save State on Change with Toast
    document.getElementById('checklistForm').addEventListener('change', (e) => {
      if (e.target.name && (e.target.type === 'radio' || e.target.type === 'checkbox')) {
        const savedState = JSON.parse(localStorage.getItem(LS_CHECKLIST_KEY) || '{}');
        
        if (e.target.type === 'radio') {
          if (e.target.checked) savedState[e.target.name] = e.target.value;
        } else if (e.target.type === 'checkbox') {
          if (e.target.name === 'personnel[]') {
             // Save all checked personnel as array
             const checked = Array.from(document.querySelectorAll('input[name="personnel[]"]:checked')).map(cb => cb.value);
             savedState.personnel_selection = checked;
          } else {
             // Normal single checkbox
             savedState[e.target.name] = e.target.checked;
          }
        }
        // Save current date for daily reset check
        savedState.date = new Date().toISOString().split('T')[0];
        localStorage.setItem(LS_CHECKLIST_KEY, JSON.stringify(savedState));
        
        // Show auto-save toast
        showAutoSaveToast('Data tersimpan otomatis');
      }
    });

    function applyDraftDetail(data, form) {
      // 1. Update Radio Status if different
      const radioName = `status[${data.eq_id}]`;
      const radios = document.getElementsByName(radioName);
      radios.forEach(r => {
        if (r.value === data.status) {
          r.checked = true;
          updateRowState(r, data.eq_id);
        }
      });

      // 2. Update UI (Button Filled)
      const row = document.querySelector(`.status-radio-${data.status}[name="status[${data.eq_id}]"]`).closest('.p-row');
      if (row) {
        const detailBtn = row.querySelector('.p-btn-detail');
        if (detailBtn) {
          detailBtn.classList.add('filled');
          detailBtn.innerText = 'DETAIL TERISI';
        }
      }

      // 3. Inject Hidden Inputs for Final Submission
      // Remove existing injections if any
      const existingContainer = document.getElementById(`draft_inputs_${data.eq_id}`);
      if (existingContainer) existingContainer.remove();

      const container = document.createElement('div');
      container.id = `draft_inputs_${data.eq_id}`;

      // Status & Keterangan (Override default inputs)
      container.innerHTML += `<input type="hidden" name="findings[${data.eq_id}][status]" value="${data.status}">`;
      container.innerHTML += `<input type="hidden" name="findings[${data.eq_id}][keterangan]" value="${data.keterangan.replace(/"/g, '&quot;')}">`;

      // Photos
      if (data.new_photos && data.new_photos.length > 0) {
        data.new_photos.forEach(path => {
          container.innerHTML += `<input type="hidden" name="findings[${data.eq_id}][photos][]" value="${path}">`;
        });
      }

      form.appendChild(container);
    }
  </script>
</body>

</html>