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
// ------------------------------

$eq_id = $_GET['id'] ?? null;
if (!$eq_id) {
    header("Location: checklist.php");
    exit;
}

$today = date('Y-m-d');

// Fetch equipment details
$stmt = $conn->prepare("SELECT e.*, s.nama_section, l.nama_lokasi FROM equipments e JOIN sections s ON e.section_id = s.id JOIN lokasi l ON e.lokasi_id = l.id WHERE e.id = ?");
if (!$stmt) {
    die("Error preparing equipment query: " . $conn->error);
}
$stmt->bind_param("i", $eq_id);
$stmt->execute();
$equipment = $stmt->get_result()->fetch_assoc();

if (!$equipment) {
    header("Location: checklist.php");
    exit;
}

// Fetch existing inspection for today
$stmt = $conn->prepare("SELECT * FROM inspections_daily WHERE equipment_id = ? AND tanggal = ?");
$stmt->bind_param("is", $eq_id, $today);
$stmt->execute();
$inspection = $stmt->get_result()->fetch_assoc();

// Get status from GET if coming first time or from inspection
$current_status = $_GET['status'] ?? ($inspection['status'] ?? 'O');

// Handle Deletion of specific photo
if (isset($_GET['delete_photo_id'])) {
    $photo_id = (int) $_GET['delete_photo_id'];
    $stmt_del = $conn->prepare("DELETE FROM inspection_photos WHERE id = ?");
    $stmt_del->bind_param("i", $photo_id);
    $stmt_del->execute();
    header("Location: detail_temuan.php?id=$eq_id&status=$current_status");
    exit;
}

// Handle Save
if (isset($_POST['save_detail'])) {
    $ket = $_POST['keterangan'] ?? '';
    $status = $_POST['status'] ?? $current_status;

    // 1. Save or Update basic inspection
    if ($inspection) {
        $stmt = $conn->prepare("UPDATE inspections_daily SET status = ?, keterangan = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $ket, $inspection['id']);
        $stmt->execute();
        $inspection_id = $inspection['id'];
    } else {
        $stmt = $conn->prepare("INSERT INTO inspections_daily (equipment_id, tanggal, status, keterangan) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $eq_id, $today, $status, $ket);
        $stmt->execute();
        $inspection_id = $conn->insert_id;
    }

    // 2. Handle Multiple Photo Uploads
    $upload_dir = "assets/uploads/inspections/";
    if (!is_dir($upload_dir))
        mkdir($upload_dir, 0777, true);

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

    header("Location: checklist.php");
    exit;
}

// Fetch photos for this exhibition
$photos = [];
if ($inspection) {
    $res_photos = $conn->query("SELECT * FROM inspection_photos WHERE inspection_id = " . $inspection['id'] . " ORDER BY id DESC");
    if ($res_photos) {
        while ($p = $res_photos->fetch_assoc())
            $photos[] = $p;
    }
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumentasi - <?= htmlspecialchars($equipment['nama_peralatan']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand-primary: #087F8A;
            --brand-teal: #087F8A;
            --brand-teal-dark: #065C63;
            --brand-emerald: #10b981;
            --brand-amber: #f59e0b;
            --brand-rose: #f43f5e;
            --font-heading: 'Plus Jakarta Sans', sans-serif;
            --font-body: 'Plus Jakarta Sans', sans-serif;
            --bg-page: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-font-smoothing: antialiased;
        }

        body {
            font-family: var(--font-body);
            background: var(--bg-page);
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 100px 20px 40px;
        }

        /* Navbar Styles (Preserved but simplified) */
        .top-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 70px;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            padding: 0 24px;
        }

        .logo-injourney {
            height: 36px;
            width: auto;
        }

        .logo-bandara {
            height: 40px;
            width: auto;
        }

        .navbar-center {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .nav-menu {
            display: flex;
            gap: 8px;
            list-style: none;
        }

        .nav-item>a {
            text-decoration: none;
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 6px;
            transition: color 0.2s;
        }

        .nav-item>a:hover,
        .nav-item.active>a {
            color: var(--brand-primary);
        }

        .logout-btn {
            background: var(--brand-primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
        }

        /* Main Card - Landscape & Compact */
        .p-detail-card {
            width: 100%;
            max-width: 900px;
            /* Wider for landscape */
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-top: 10px;
        }

        .p-header {
            padding: 24px 32px;
            background: white;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .p-header-content {
            flex: 1;
        }

        .p-header h1 {
            font-family: var(--font-heading);
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
            margin-bottom: 4px;
        }

        .p-header p {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
        }

        /* 2-Column Grid Body */
        .p-body {
            padding: 32px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
        }

        .p-left-col {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .p-right-col {
            display: flex;
            flex-direction: column;
            gap: 24px;
            height: 100%;
        }

        .p-form-group {
            margin-bottom: 0;
        }

        .p-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--brand-primary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
        }

        /* Status Pills */
        .p-status-row {
            display: flex;
            gap: 12px;
            margin-bottom: 0;
            flex-wrap: wrap;
        }

        .p-status-radio {
            display: none;
        }

        .p-status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--border-color);
            background: #fff;
            color: #64748b;
            transition: all 0.2s;
            flex: 1;
            text-align: center;
        }

        .p-status-radio:checked+.p-status-pill {
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .p-status-radio[value="O"]:checked+.p-status-pill {
            background: var(--brand-emerald);
        }

        .p-status-radio[value="X"]:checked+.p-status-pill {
            background: var(--brand-rose);
        }

        .p-status-radio[value="-"]:checked+.p-status-pill {
            background: var(--brand-amber);
        }

        .p-status-radio[value="V"]:checked+.p-status-pill {
            background: var(--brand-primary);
        }

        /* Static Status Pill Colors (Soft/Pastel) */
        .status-O {
            background: #d1fae5;
            color: #065f46;
            border-color: transparent;
        }

        /* Soft Emerald */
        .status-X {
            background: #ffe4e6;
            color: #9f1239;
            border-color: transparent;
        }

        /* Soft Rose */
        .status-- {
            background: #fef3c7;
            color: #92400e;
            border-color: transparent;
        }

        /* Soft Amber */
        .status-V {
            background: #ccfbf1;
            color: #115e59;
            border-color: transparent;
        }

        /* Soft Teal */

        /* Textarea - Full Height in Column */
        .p-textarea {
            width: 100%;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 16px;
            font-family: inherit;
            font-size: 0.9rem;
            line-height: 1.5;
            height: 100%;
            min-height: 200px;
            resize: none;
            background: #fcfcfc;
            color: #334155;
            transition: border-color 0.2s;
        }

        .p-textarea:focus {
            outline: none;
            border-color: var(--brand-primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(8, 127, 138, 0.1);
        }

        /* Gallery & Upload */
        .p-gallery-unified {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .p-gallery-item {
            position: relative;
            background: #f1f5f9;
            border-radius: 12px;
            overflow: hidden;
            aspect-ratio: 4/3;
        }

        .p-gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .p-photo-remove {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            text-decoration: none;
            transition: background 0.2s;
        }

        .p-photo-remove:hover {
            background: var(--brand-rose);
        }

        .p-upload-card {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            background: #f8fafc;
            color: #64748b;
            gap: 8px;
            aspect-ratio: 4/3;
            width: 100%;
        }

        .p-upload-card:hover {
            border-color: var(--brand-primary);
            background: #f0f9fa;
            color: var(--brand-primary);
        }

        .p-upload-card i {
            font-size: 1.5rem;
        }

        .p-upload-card span {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* Actions */
        .p-actions {
            display: flex;
            gap: 12px;
            margin-top: auto;
            /* Push to bottom of right col */
        }

        .p-btn {
            flex: 1;
            padding: 14px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .p-btn-secondary {
            background: white;
            border: 1px solid var(--border-color);
            color: #64748b;
        }

        .p-btn-secondary:hover {
            background: #f1f5f9;
            color: #334155;
        }

        .p-btn-primary {
            background: var(--brand-primary);
            color: white;
        }

        .p-btn-primary:hover {
            background: var(--brand-teal-dark);
            transform: translateY(-1px);
        }

        @media (max-width: 800px) {
            .p-body {
                grid-template-columns: 1fr;
                gap: 32px;
            }

            .p-detail-card {
                margin-top: 0;
                border-radius: 0;
                border: none;
            }

            body {
                padding-top: 80px;
                padding-bottom: 0;
                background: white;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="p-detail-card">
        <div class="p-header">
            <p><?= htmlspecialchars($equipment['nama_section']) ?> — <?= htmlspecialchars($equipment['nama_lokasi']) ?>
            </p>
            <h1><?= htmlspecialchars($equipment['nama_peralatan']) ?></h1>
        </div>

        <form method="POST" enctype="multipart/form-data" class="p-body">
            <input type="hidden" name="status" value="<?= htmlspecialchars($current_status) ?>">

            <!-- Left Column: Status & Photos -->
            <div class="p-left-col">
                <div class="p-form-group">
                    <label class="p-label">Status Terpilih</label>
                    <div class="p-status-pill status-<?= $current_status ?>">
                        <?php
                        $status_map = ['O' => 'Normal', '-' => 'Menurun', 'X' => 'Terputus', 'V' => 'Gangguan'];
                        echo $status_map[$current_status] ?? $current_status;
                        ?>
                    </div>
                </div>

                <div class="p-form-group">
                    <label class="p-label">Dokumentasi Foto</label>
                    <div class="p-gallery-unified" id="photo-grid">
                        <?php foreach ($photos as $ph): ?>
                            <div class="p-gallery-item">
                                <img src="<?= $ph['foto_path'] ?>">
                                <a href="?id=<?= $eq_id ?>&status=<?= $current_status ?>&delete_photo_id=<?= $ph['id'] ?>"
                                    class="p-photo-remove" title="Hapus Foto"
                                    onclick="return confirm('Hapus foto ini?')">✕</a>
                            </div>
                        <?php endforeach; ?>

                        <div id="new-previews-container" style="display: contents;"></div>

                        <label class="p-upload-card" id="upload-card">
                            <input type="file" id="photo-input" multiple accept="image/*" onchange="addPhotos(this)"
                                style="display:none">
                            <i class="fa-solid fa-camera"></i>
                            <b>Tambah</b>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Right Column: Notes & Actions -->
            <div class="p-right-col">
                <div class="p-form-group" style="flex: 1; display: flex; flex-direction: column;">
                    <label class="p-label">Detail Catatan / Temuan</label>
                    <textarea name="keterangan" class="p-textarea" placeholder="Tambahkan catatan detail..."
                        style="flex: 1;"><?= htmlspecialchars($inspection['keterangan'] ?? '') ?></textarea>
                </div>

                <div class="p-actions">
                    <a href="checklist.php" class="p-btn p-btn-secondary">KEMBALI</a>
                    <button type="submit" name="save_detail" class="p-btn p-btn-primary"
                        onclick="return prepareFormData(event)">SIMPAN</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        let accumulatedFiles = [];

        function addPhotos(input) {
            if (input.files && input.files.length > 0) {
                // Add new files to the accumulated collection
                Array.from(input.files).forEach(file => {
                    accumulatedFiles.push(file);
                    displayPreview(file, accumulatedFiles.length - 1);
                });

                // Clear the input so the same file can be selected again if needed
                input.value = '';
            }
        }

        function displayPreview(file, index) {
            const previewContainer = document.getElementById('new-previews-container');
            const reader = new FileReader();

            reader.onload = function (e) {
                const div = document.createElement('div');
                div.className = 'p-gallery-item';
                div.setAttribute('data-file-index', index);
                div.innerHTML = `
                    <img src="${e.target.result}">
                    <div class="preview-badge">Baru</div>
                    <div class="p-photo-remove" onclick="removeNewPhoto(${index})" title="Hapus Foto" style="cursor: pointer;">✕</div>
                `;
                previewContainer.appendChild(div);
            }

            reader.readAsDataURL(file);
        }

        function removeNewPhoto(index) {
            // Remove from array
            accumulatedFiles.splice(index, 1);

            // Re-render all previews with updated indices
            const previewContainer = document.getElementById('new-previews-container');
            previewContainer.innerHTML = '';

            accumulatedFiles.forEach((file, idx) => {
                displayPreview(file, idx);
            });
        }

        function prepareFormData(event) {
            if (accumulatedFiles.length === 0) {
                // No new photos, allow normal form submission
                return true;
            }

            event.preventDefault();

            const form = event.target.closest('form');
            const formData = new FormData(form);

            // Remove the original file input data if any
            formData.delete('fotos[]');

            // Add accumulated files
            accumulatedFiles.forEach((file, index) => {
                formData.append('fotos[]', file);
            });

            // Submit via fetch
            fetch(form.action || window.location.href, {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (response.ok) {
                        window.location.href = 'checklist.php';
                    } else {
                        alert('Terjadi kesalahan saat menyimpan. Silakan coba lagi.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat menyimpan. Silakan coba lagi.');
                });

            return false;
        }

        // ========================================
        // NAVBAR MOBILE MENU FUNCTIONS
        // ========================================
        function toggleMobileMenu() {
            const navbar = document.getElementById('navbarMenu');
            const icon = document.getElementById('hamburgerIcon');
            navbar.classList.toggle('active');
            if (navbar.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        }

        document.addEventListener('click', function (event) {
            const navbar = document.getElementById('navbarMenu');
            const hamburger = document.getElementById('hamburgerBtn');
            if (navbar && !navbar.contains(event.target) && !hamburger.contains(event.target)) {
                navbar.classList.remove('active');
                const icon = document.getElementById('hamburgerIcon');
                if (icon) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) {
                const navbar = document.getElementById('navbarMenu');
                const icon = document.getElementById('hamburgerIcon');
                if (navbar) navbar.classList.remove('active');
                if (icon) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        });
    </script>
</body>

</html>