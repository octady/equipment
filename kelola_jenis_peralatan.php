<?php
include "config/database.php";
include "config/auth.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'add_equipment') {
        $nama = trim($_POST['nama_peralatan'] ?? '');
        $lokasi_id = intval($_POST['lokasi_id'] ?? 0);
        $section_id = intval($_POST['section_id'] ?? 0);

        if ($nama === '' || $lokasi_id <= 0 || $section_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO equipments (nama_peralatan, lokasi_id, section_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sii", $nama, $lokasi_id, $section_id);

        echo json_encode(
            $stmt->execute()
            ? ['success' => true, 'message' => 'Equipment berhasil ditambahkan']
            : ['success' => false, 'message' => 'Gagal menambah equipment']
        );
        exit;
    }

    if ($action === 'edit_equipment') {
        $id = intval($_POST['id'] ?? 0);
        $nama = trim($_POST['nama_peralatan'] ?? '');
        $lokasi_id = intval($_POST['lokasi_id'] ?? 0);

        if ($id <= 0 || $nama === '' || $lokasi_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE equipments SET nama_peralatan=?, lokasi_id=? WHERE id=?");
        $stmt->bind_param("sii", $nama, $lokasi_id, $id);

        echo json_encode(
            $stmt->execute()
            ? ['success' => true, 'message' => 'Equipment diperbarui']
            : ['success' => false, 'message' => 'Gagal update equipment']
        );
        exit;
    }

    if ($action === 'delete_equipment') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM equipments WHERE id=?");
        $stmt->bind_param("i", $id);

        echo json_encode(
            $stmt->execute()
            ? ['success' => true, 'message' => 'Equipment dihapus']
            : ['success' => false, 'message' => 'Gagal hapus equipment']
        );
        exit;
    }

    if ($action === 'add') {
        $nama = trim($_POST['nama_jenis'] ?? '');

        if ($nama === '') {
            echo json_encode(['success' => false, 'message' => 'Nama jenis peralatan wajib diisi']);
            exit;
        }

        // Insert new section. Assuming other fields are nullable.
        // We set 'created_at' if exists, or just minimal insert.
        $stmt = $conn->prepare("INSERT INTO sections (nama_section) VALUES (?)");
        $stmt->bind_param("s", $nama);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Jenis peralatan berhasil ditambahkan']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menambah jenis peralatan: ' . $conn->error]);
        }
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $nama = trim($_POST['nama_jenis'] ?? '');

        if ($id <= 0 || $nama === '') {
            echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE sections SET nama_section=? WHERE id=?");
        $stmt->bind_param("si", $nama, $id);

        echo json_encode(
            $stmt->execute()
            ? ['success' => true, 'message' => 'Nama jenis peralatan diperbarui']
            : ['success' => false, 'message' => 'Gagal update']
        );
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        // DELETE CASCADE: Hapus equipment dulu
        $del_eq = $conn->prepare("DELETE FROM equipments WHERE section_id=?");
        $del_eq->bind_param("i", $id);
        $del_eq->execute();

        // Baru hapus section
        $stmt = $conn->prepare("DELETE FROM sections WHERE id=?");
        $stmt->bind_param("i", $id);

        echo json_encode(
            $stmt->execute()
            ? ['success' => true, 'message' => 'Jenis peralatan & equipment di dalamnya terhapus']
            : ['success' => false, 'message' => 'Gagal hapus']
        );
        exit;
    }
}

// 1. Get All Sections
$data = [];
$res_s = $conn->query("SELECT * FROM sections ORDER BY nama_section");
while ($s = $res_s->fetch_assoc()) {
    $s['equipments'] = [];
    $data[$s['id']] = $s;
}

// 2. Get All Equipments
$res_e = $conn->query("
    SELECT e.id, e.nama_peralatan, e.section_id, e.lokasi_id, l.nama_lokasi 
    FROM equipments e
    LEFT JOIN lokasi l ON e.lokasi_id = l.id
    ORDER BY e.nama_peralatan
");
while ($e = $res_e->fetch_assoc()) {
    if (isset($data[$e['section_id']])) {
        $data[$e['section_id']]['equipments'][] = $e;
    }
}

// 3. Get All Locations for Dropdown
$locations = [];
$res_l = $conn->query("SELECT id, nama_lokasi FROM lokasi ORDER BY nama_lokasi");
while ($l = $res_l->fetch_assoc()) {
    $locations[] = $l;
}

// Re-index
$data = array_values($data);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Jenis Peralatan</title>
    <script>
    // Critical: Run BEFORE any rendering to prevent sidebar flicker
    if (localStorage.getItem('sidebarOpen') === 'true') {
        document.documentElement.classList.add('sidebar-open');
    }
    </script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8fafc;
            margin: 0
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px
        }

        button {
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            font-weight: 600;
            cursor: pointer
        }

        .btn-add {
            background: #087F8A;
            color: white
        }

        table {
            width: 100%;
            background: white;
            border-radius: 12px;
            border-collapse: collapse
        }

        th,
        td {
            padding: 14px
        }

        thead {
            background: #e6f4f5
        }

        .badge {
            background: #e6f4f5;
            color: #087F8A;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            align-items: center;
            justify-content: center
        }

        .modal.active {
            display: flex
        }

        .modal-box {
            background: white;
            padding: 24px;
            border-radius: 16px;
            width: 100%;
            max-width: 400px
        }

        input {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1
        }

        .section-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .section-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: all 0.2s;
            position: relative;
        }

        .section-card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }

        .section-header {
            display: flex;
            align-items: center;
            padding: 20px 24px;
            cursor: pointer;
            position: relative;
            background: white;
        }

        .section-border {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            background: #0e7490;
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
        }

        .section-icon {
            color: #0e7490;
            margin-right: 20px;
            font-size: 14px;
            transition: transform 0.3s;
            width: 20px;
            text-align: center;
        }

        .section-card.expanded .section-icon {
            transform: rotate(90deg);
        }

        .section-info {
            flex: 1;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #0e7490;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .section-subtitle {
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .section-meta {
            margin-left: 20px;
        }

        .count-badge {
            background: #e6f4f5;
            color: #0e7490;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .section-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease-out;
            background: #ffffff;
            border-top: 1px solid #f1f5f9;
        }

        .section-card.expanded .section-body {
            max-height: 3000px;
            /* Fix cut-off issue */
        }

        .body-content {
            padding: 24px;
            display: flex;
            flex-direction: column;
            /* Stack vertically */
            gap: 24px;
        }

        .equipment-viewer {
            flex: 1;
            width: 100%;
            /* Ensure full width */
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #cbd5e1;
            /* Separator below buttons */
            justify-content: flex-end;
            /* Keep right aligned */
            width: 100%;
        }

        .btn-edit,
        .btn-delete {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-edit {
            background: #fff;
            border: 1px solid #0284c7;
            color: #0284c7;
        }

        .btn-edit:hover {
            background: #f0f9ff;
        }

        .btn-delete {
            background: #fff;
            border: 1px solid #ef4444;
            color: #ef4444;
        }

        .btn-delete:hover {
            background: #fef2f2;
        }

        .equipment-list {
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        .equipment-item {
            padding: 16px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .equipment-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .eq-name {
            font-size: 15px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 2px;
            letter-spacing: -0.01em;
            /* Make it look tighter/modern */
        }

        .eq-loc {
            font-size: 13px;
            color: #94a3b8;
            font-weight: 500;
            text-transform: uppercase;
        }

        .btn-add-eq {
            background: #e6f4f5;
            color: #087F8A;
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
        }

        .btn-add-eq:hover {
            background: #087F8A;
            color: white;
            transform: scale(1.1);
        }

        .equipment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .eq-info {
            flex: 1;
        }

        .eq-actions {
            display: flex;
            gap: 8px;
            opacity: 0.5;
            transition: opacity 0.2s;
        }

        .equipment-item:hover .eq-actions {
            opacity: 1;
        }

        .btn-icon-edit,
        .btn-icon-delete {
            background: none;
            border: none;
            padding: 6px;
            font-size: 14px;
            cursor: pointer;
            color: #94a3b8;
        }

        .btn-icon-edit:hover {
            color: #0284c7;
        }

        .btn-icon-delete:hover {
            color: #ef4444;
        }

        .empty-state {
            color: #94a3b8;
            font-size: 14px;
            font-style: italic;
            padding: 10px 0;
        }

        /* CUSTOM SWEETALERT STYLE */
        div:where(.swal2-container) div:where(.swal2-popup) {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            border-radius: 16px;
            padding: 24px;
        }

        div:where(.swal2-container) h2:where(.swal2-title) {
            font-size: 18px !important;
            color: #1e293b !important;
            font-weight: 700 !important;
        }

        div:where(.swal2-container) button:where(.swal2-styled) {
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 13px !important;
            padding: 10px 20px !important;
            box-shadow: none !important;
        }

        div:where(.swal2-container) button:where(.swal2-styled).swal2-confirm {
            background-color: #ef4444 !important;
        }

        div:where(.swal2-container) button:where(.swal2-styled).swal2-cancel {
            background-color: #f1f5f9 !important;
            color: #64748b !important;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .container {
                padding: 20px 15px;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px 10px;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .header h2 {
                font-size: 20px;
            }
            
            .search-box {
                flex-direction: column !important;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .search-box button {
                width: 100%;
            }
            
            .section-header {
                padding: 16px 18px;
            }
            
            .section-title {
                font-size: 14px;
            }
            
            .body-content {
                padding: 16px;
            }
            
            .action-buttons {
                flex-wrap: wrap;
                justify-content: flex-start;
                gap: 8px;
            }
            
            .btn-edit, .btn-delete {
                padding: 8px 12px;
                font-size: 12px;
            }
            
            .equipment-item {
                padding: 12px 0;
            }
            
            .eq-name {
                font-size: 14px;
            }
            
            .eq-loc {
                font-size: 12px;
            }
            
            .modal-box {
                max-width: 90%;
                margin: 20px;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding: 12px 8px;
            }
            
            .header h2 {
                font-size: 18px;
            }
            
            .btn-add {
                width: 100%;
                padding: 10px 16px;
                font-size: 14px;
            }
            
            .section-header {
                padding: 14px 16px;
            }
            
            .section-icon {
                margin-right: 12px;
                font-size: 12px;
            }
            
            .section-title {
                font-size: 13px;
            }
            
            .count-badge {
                font-size: 11px;
                padding: 4px 10px;
            }
            
            .btn-add-eq {
                width: 28px;
                height: 28px;
            }
            
            .section-meta {
                margin-left: 12px;
                gap: 8px !important;
            }
            
            .body-content {
                padding: 14px;
                gap: 16px;
            }
            
            .action-buttons {
                padding-bottom: 14px;
            }
            
            .btn-edit, .btn-delete {
                flex: 1 1 100%;
                justify-content: center;
            }
            
            .equipment-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .eq-actions {
                opacity: 1;
            }
            
            .modal-box {
                max-width: 95%;
                padding: 16px;
                margin: 10px;
            }
            
            .modal-box h3 {
                font-size: 16px;
            }
            
            input {
                padding: 10px;
                font-size: 14px;
            }
            
            select {
                padding: 10px !important;
                font-size: 14px !important;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="container">
        <div class="header">
            <h2>Daftar Peralatan</h2>
            <button class="btn-add" onclick="openModal()">
                <i class="fas fa-plus"></i> Tambah Jenis Peralatan
            </button>
        </div>

        <!-- SEARCH BAR -->
        <div class="search-box" style="display: flex; gap: 10px; margin-bottom: 20px;">
            <input type="text" id="keyword" placeholder="Cari jenis peralatan atau nama equipment..."
                style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1;" onkeyup="search()">
            <button onclick="search()"
                style="padding: 12px 20px; background: #0e7490; color: white; border: none; border-radius: 8px; cursor: pointer; transition: 0.2s;">
                <i class="fas fa-search"></i>
            </button>
        </div>

        <div class="section-list">
            <?php foreach ($data as $i => $d): ?>
                <div class="section-card" id="card-<?= $d['id'] ?>">
                    <!-- Header / Clickable Area -->
                    <div class="section-header" onclick="toggleSection(<?= $d['id'] ?>)">
                        <div class="section-border"></div>
                        <div class="section-icon">
                            <i class="fas fa-chevron-right" id="icon-<?= $d['id'] ?>"></i>
                        </div>
                        <div class="section-info">
                            <div class="section-title"><?= htmlspecialchars($d['nama_section'] ?? '') ?></div>
                        </div>
                        <div class="section-meta" style="display:flex; align-items:center; gap:12px">
                            <span class="count-badge"><?= count($d['equipments']) ?> Unit</span>
                            <!-- Add Equipment Button (Prevents propagation) -->
                            <button onclick="event.stopPropagation(); openEqModal('add', null, <?= $d['id'] ?>)"
                                class="btn-add-eq" title="Tambah Equipment di Section ini">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Body / Expanded Area -->
                    <div class="section-body" id="body-<?= $d['id'] ?>">
                        <div class="body-content">
                            <!-- Section Actions (Moved to Top) -->
                            <div class="action-buttons">
                                <button class="btn-edit" onclick="edit(<?= $d['id'] ?>)">
                                    <i class="fas fa-pencil-alt"></i> Edit Nama Jenis Peralatan
                                </button>
                                <button class="btn-delete" onclick="hapus(<?= $d['id'] ?>)">
                                    <i class="fas fa-trash"></i> Hapus Jenis Peralatan
                                </button>
                            </div>

                            <!-- Equipment List -->
                            <div class="equipment-viewer">
                                <?php if (!empty($d['equipments'])): ?>
                                    <div class="equipment-list">
                                        <?php foreach ($d['equipments'] as $eq): ?>
                                            <div class="equipment-item">
                                                <div class="eq-info">
                                                    <div class="eq-name"><?= htmlspecialchars($eq['nama_peralatan'] ?? '') ?></div>
                                                    <div class="eq-loc">
                                                        <?= htmlspecialchars($eq['nama_lokasi'] ?? '') ?>
                                                    </div>
                                                </div>
                                                <div class="eq-actions">
                                                    <button
                                                        onclick="openEqModal('edit', {id:<?= $eq['id'] ?>, nama:'<?= addslashes($eq['nama_peralatan']) ?>', lokasi_id:<?= $eq['lokasi_id'] ?? 0 ?>}, <?= $d['id'] ?>)"
                                                        class="btn-icon-edit" title="Edit Equipment">
                                                        <i class="fas fa-pencil-alt"></i>
                                                    </button>
                                                    <button onclick="hapusEq(<?= $eq['id'] ?>, <?= $d['id'] ?>)"
                                                        class="btn-icon-delete" title="Hapus Equipment">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">Belum ada equipment di jenis peralatan ini.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
            <?php if (empty($data)): ?>
                <p style="text-align:center; color:#94a3b8; padding:30px">Belum ada data jenis peralatan.</p>
            <?php endif; ?>
        </div>
    </main>

    <!-- MODAL SECTION -->
    <div class="modal" id="modal">
        <div class="modal-box">
            <h3 id="modalTitle">Tambah Jenis Peralatan</h3>
            <input id="nama" placeholder="Nama Jenis Peralatan" style="margin-bottom:16px">
            <div style="display:flex;justify-content:flex-end;gap:8px">
                <button class="btn-add" onclick="simpan()">Simpan</button>
                <button onclick="closeModal()">Batal</button>
            </div>
        </div>
    </div>

    <!-- MODAL EQUIPMENT -->
    <div class="modal" id="modalEq">
        <div class="modal-box">
            <h3 id="modalEqTitle">Tambah Equipment</h3>
            <input type="hidden" id="eqId">
            <input type="hidden" id="eqSectionId">

            <div style="margin-bottom:12px">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px">Nama Equipment</label>
                <input id="eqNama" placeholder="Contoh: Genset 500 KVA">
            </div>

            <div style="margin-bottom:16px">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px">Lokasi</label>
                <select id="eqLokasi" style="width:100%; padding:12px; border-radius:8px; border:1px solid #cbd5e1">
                    <option value="">-- Pilih Lokasi --</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['nama_lokasi'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:8px">
                <button class="btn-add" onclick="simpanEq()">Simpan</button>
                <button onclick="closeModalEq()">Batal</button>
            </div>
        </div>
    </div>

    <script>
        // Prevent browser from auto-restoring scroll, we handle it manually
        if (history.scrollRestoration) {
            history.scrollRestoration = 'manual';
        }

        const modal = document.getElementById('modal');
        const modalTitle = document.getElementById('modalTitle');
        const nama = document.getElementById('nama');
        let editId = null;

        function toggleSection(id) {
            const card = document.getElementById('card-' + id);
            card.classList.toggle('expanded');
        }

        function openModal() {
            editId = null;
            nama.value = '';
            modalTitle.innerText = 'Tambah Jenis Peralatan';
            modal.classList.add('active');
        }
        function closeModal() { modal.classList.remove('active'); }

        function edit(id) {
            const card = document.getElementById('card-' + id);
            const name = card.querySelector('.section-title').innerText.trim();
            editId = id;
            nama.value = name;
            modalTitle.innerText = 'Edit Nama Jenis Peralatan';
            modal.classList.add('active');
        }

        function saveState() {
            localStorage.setItem('scrollPos', window.scrollY);
            const kw = document.getElementById('keyword').value;
            if (kw) localStorage.setItem('searchKeyword', kw);
        }

        function simpan() {
            if (!nama.value.trim()) {
                Swal.fire('Peringatan', 'Nama tidak boleh kosong', 'warning');
                return;
            }
            const fd = new FormData();
            fd.append('ajax', 1);
            fd.append('nama_jenis', nama.value.trim());
            if (editId) {
                fd.append('action', 'edit');
                fd.append('id', editId);
            } else fd.append('action', 'add');

            fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        localStorage.setItem('scrollPos', window.scrollY);
                        if (editId) {
                            localStorage.setItem('openSectionId', editId);
                        }
                        Swal.fire('Berhasil', d.message, 'success').then(() => location.reload());
                    } else Swal.fire('Gagal', d.message, 'error');
                });
        }

        function hapus(id) {
            Swal.fire({
                title: 'Hapus jenis peralatan?',
                html: `
                    <div style="text-align: left; color: #475569; font-size: 14px; line-height: 1.6;">
                        <p style="margin-bottom: 16px;">
                            Anda akan menghapus Jenis Peralatan ini. 
                            <br>
                            <span style="color:#ef4444; font-weight:600;">PERHATIAN:</span> Semua equipment di dalamnya juga akan 
                            <span style="font-weight: 700; color: #ef4444;">terhapus permanen</span>.
                        </p>
                        
                        <div style="background: #fff1f2; border: 1px solid #fda4af; border-radius: 8px; padding: 12px; display: flex; gap: 10px; align-items: flex-start;">
                            <input type="checkbox" id="agree_del" style="margin-top: 4px; accent-color: #e11d48; width: 16px; height: 16px; cursor: pointer;">
                            <label for="agree_del" style="font-size: 13px; color: #9f1239; font-weight: 600; cursor: pointer; line-height: 1.4;">
                                Saya mengerti dan ingin menghapus data ini beserta isinya secara permanen.
                            </label>
                        </div>
                    </div>
                `,
                icon: 'warning',
                iconColor: '#ef4444',
                showCancelButton: true,
                confirmButtonText: 'Hapus Permanen',
                cancelButtonText: 'Batal',
                reverseButtons: true, // Common pattern: Cancel left, Confirm right
                focusConfirm: false,
                preConfirm: () => {
                    const cb = document.getElementById('agree_del');
                    if (!cb || !cb.checked) {
                        Swal.showValidationMessage('Mohon centang persetujuan di atas');
                        return false;
                    }
                    return true;
                }
            }).then(r => {
                if (r.isConfirmed) {
                    const fd = new FormData();
                    fd.append('ajax', 1);
                    fd.append('action', 'delete');
                    fd.append('id', id);
                    fetch('', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(d => {
                            if (d.success) {
                                localStorage.setItem('scrollPos', window.scrollY);
                                Swal.fire('Terhapus', d.message, 'success').then(() => location.reload());
                            } else Swal.fire('Gagal', d.message, 'error');
                        });
                }
            });
        }

        // EQUIPMENT LOGIC ------------------------------------------------
        // ... (simpanEq/hapusEq already updated, assuming valid) ...
        // We only overwrite the END of the file where DOMContentLoaded is.

        // Auto-open last section and restore scroll
        document.addEventListener('DOMContentLoaded', () => {
            const lastId = localStorage.getItem('openSectionId');
            const lastScroll = localStorage.getItem('scrollPos');

            if (lastId) {
                const card = document.getElementById('card-' + lastId);
                if (card) {
                    // Disable transition temporarily to force immediate height
                    const body = card.querySelector('.section-body');
                    if (body) body.style.transition = 'none';

                    card.classList.add('expanded');

                    // Restore transition shortly after
                    setTimeout(() => {
                        if (body) body.style.transition = '';
                    }, 100);
                }
                localStorage.removeItem('openSectionId');
            }

            if (lastScroll) {
                // Ensure layout is ready
                setTimeout(() => {
                    window.scrollTo(0, parseInt(lastScroll));
                    localStorage.removeItem('scrollPos');
                }, 10);
            }
        });

        // SEARCH LOGIC
        function search() {
            const keyword = document.getElementById('keyword').value.toLowerCase();
            const cards = document.querySelectorAll('.section-card');

            cards.forEach(card => {
                const title = card.querySelector('.section-title').innerText.toLowerCase();
                const sectionMatch = title.includes(keyword);

                const eqItems = card.querySelectorAll('.equipment-item');
                let hasEqMatch = false;

                eqItems.forEach(item => {
                    const text = item.innerText.toLowerCase();
                    const match = text.includes(keyword);
                    if (match) hasEqMatch = true;

                    // If section matches, show all (optional, but let's filter strict first)
                    // Actually if section matches, we usually want to see the section structure.
                    // But if searching "Genset A", we want to hide "Genset B".
                    // Let's rely on strict item filtering unless keyword is in section title

                    if (sectionMatch || match) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });

                if (sectionMatch || hasEqMatch) {
                    card.style.display = 'block';
                    // If found, maybe expand? 
                    // if (keyword.length > 2) card.classList.add('expanded'); 
                } else {
                    card.style.display = 'none';
                }
            });
        }

        const modalEq = document.getElementById('modalEq');
        const eqId = document.getElementById('eqId');
        const eqSectionId = document.getElementById('eqSectionId');
        const eqNama = document.getElementById('eqNama');
        const eqLokasi = document.getElementById('eqLokasi');
        const modalEqTitle = document.getElementById('modalEqTitle');

        function openEqModal(mode, data = null, sectionId = null) {
            if (mode === 'add') {
                eqId.value = '';
                eqSectionId.value = sectionId;
                eqNama.value = '';
                eqLokasi.value = '';
                modalEqTitle.innerText = 'Tambah Equipment';
            } else {
                eqId.value = data.id;
                eqSectionId.value = sectionId; // Store section ID for reopen
                eqNama.value = data.nama;
                eqLokasi.value = data.lokasi_id;
                modalEqTitle.innerText = 'Edit Equipment';
            }
            modalEq.classList.add('active');
        }

        function closeModalEq() {
            modalEq.classList.remove('active');
        }

        function simpanEq() {
            if (!eqNama.value.trim() || !eqLokasi.value) {
                Swal.fire('Peringatan', 'Nama Equipment dan Lokasi wajib diisi', 'warning');
                return;
            }

            const fd = new FormData();
            fd.append('ajax', 1);
            fd.append('nama_peralatan', eqNama.value.trim());
            fd.append('lokasi_id', eqLokasi.value);

            if (eqId.value) {
                fd.append('action', 'edit_equipment');
                fd.append('id', eqId.value);
            } else {
                fd.append('action', 'add_equipment');
                fd.append('section_id', eqSectionId.value);
            }

            fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        saveState();
                        // Persist state
                        if (eqSectionId.value) localStorage.setItem('openSectionId', eqSectionId.value);
                        localStorage.setItem('scrollPos', window.scrollY);

                        Swal.fire('Berhasil', d.message, 'success').then(() => location.reload());
                    } else Swal.fire('Gagal', d.message, 'error');
                });
        }

        function hapusEq(id, sectionId) {
            Swal.fire({
                title: 'Hapus equipment?',
                text: 'Data akan dihapus permanen',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33'
            }).then(r => {
                if (r.isConfirmed) {
                    const fd = new FormData();
                    fd.append('ajax', 1);
                    fd.append('action', 'delete_equipment');
                    fd.append('id', id);
                    fetch('', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(d => {
                            if (d.success) {
                                saveState();
                                // Persist state
                                if (sectionId) localStorage.setItem('openSectionId', sectionId);
                                localStorage.setItem('scrollPos', window.scrollY);

                                Swal.fire('Terhapus', d.message, 'success').then(() => location.reload());
                            } else Swal.fire('Gagal', d.message, 'error');
                        });
                }
            });
        }

        // Auto-open last section and restore scroll
        document.addEventListener('DOMContentLoaded', () => {
            const lastId = localStorage.getItem('openSectionId');
            const lastScroll = localStorage.getItem('scrollPos');
            const lastKw = localStorage.getItem('searchKeyword');

            // 1. Restore Search
            if (lastKw) {
                document.getElementById('keyword').value = lastKw;
                search(); // Trigger filter immediate
                localStorage.removeItem('searchKeyword');
            }

            // 2. Restore Accordion
            if (lastId) {
                const card = document.getElementById('card-' + lastId);
                if (card) {
                    const body = card.querySelector('.section-body');
                    if (body) body.style.transition = 'none'; // Disable transition temporarily
                    card.classList.add('expanded');

                    // Force reflow
                    void card.offsetWidth;

                    // Re-enable transition shortly after
                    setTimeout(() => { if (body) body.style.transition = ''; }, 50);
                }
                localStorage.removeItem('openSectionId');
            }

            // 3. Restore Scroll
            if (lastScroll) {
                setTimeout(() => {
                    window.scrollTo(0, parseInt(lastScroll));
                    localStorage.removeItem('scrollPos');
                }, 0);
            }
        });
    </script>
</body>

</html>