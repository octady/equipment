<?php
session_start();
include "config/database.php";

// Check login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Fetch Lokasi mostly for suggestions
$lokasi_list = [];
$res_l = $conn->query("SELECT nama_lokasi FROM lokasi ORDER BY nama_lokasi");
if ($res_l) {
    while ($row = $res_l->fetch_assoc())
        $lokasi_list[] = $row['nama_lokasi'];
}

// Fetch existing data for display (all data)
$existing_data = $conn->query("SELECT * FROM inspeksi ORDER BY tanggal DESC, id DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Input Laporan Kegiatan - Equipment Monitoring</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/monitoring.css">
    <style>
        .p-card-body {
            padding: 0;
        }
        
        .p-card-header {
            padding: 32px 32px 24px 32px;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-table-container {
            overflow-x: auto;
            padding: 0 32px 32px 32px;
        }

        .activity-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        .activity-table th {
            text-align: left;
            padding: 16px;
            font-size: 0.85rem;
            font-weight: 700;
            background: #087F8A;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #065C63;
            white-space: nowrap;
        }
        
        .activity-table th:first-child {
            border-top-left-radius: 12px;
        }
        
        .activity-table th:last-child {
            border-top-right-radius: 12px;
        }

        .activity-table td {
            padding: 16px;
            vertical-align: top;
            border-bottom: 1px solid #f1f5f9;
        }

        .activity-table tr:last-child td {
            border-bottom: none;
        }

        .table-input {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-family: inherit;
            font-size: 0.9rem;
            transition: all 0.2s;
            background: #fff;
        }
        
        .table-input:focus {
            outline: none;
            border-color: #087F8A;
            box-shadow: 0 0 0 3px rgba(8, 127, 138, 0.1);
        }
        
        .table-input::placeholder {
            color: #ccc;
        }

        .btn-add-row {
            margin: 0 32px 32px 32px;
            width: calc(100% - 64px);
            background: #f8fafc;
            color: #087F8A;
            border: 2px dashed #cbd5e1;
            padding: 14px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-add-row:hover {
            background: #f0f9fa;
            border-color: #087F8A;
        }

        .btn-remove-row {
            color: #cbd5e1;
            background: none;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn-remove-row:hover {
            background: #fee2e2;
            color: #ef4444;
        }

        /* Photo Upload Mini */
        .mini-upload {
            border: 1px dashed #cbd5e1;
            padding: 8px 12px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 4px;
            color: #64748b;
            font-size: 0.8rem;
            transition: all 0.2s;
            width: 60px;
            height: 60px;
            overflow: hidden;
        }
        
        .mini-upload:hover {
            border-color: #087F8A;
            color: #087F8A;
            background: #f0f9fa;
        }

        /* Excel Button Style */
        .btn-excel {
            background: #10b981;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .btn-excel:hover {
            background: #059669;
        }

        /* Data List Styles */
        .data-list-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-list-table th {
            background: #f8fafc;
            padding: 12px 16px;
            text-align: left;
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e2e8f0;
        }

        .data-list-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
            color: #334155;
        }

        .data-list-table tr:hover {
            background: #f8fafc;
        }

        .btn-action {
            padding: 6px 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }

        .btn-edit {
            background: #e0f2fe;
            color: #0369a1;
        }
        .btn-edit:hover {
            background: #bae6fd;
        }

        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
        }
        .btn-delete:hover {
            background: #fecaca;
        }

        .foto-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 12px;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.show {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 24px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h3 {
            margin: 0;
            color: #087F8A;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #94a3b8;
        }
        .modal-close:hover {
            color: #ef4444;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #475569;
            font-size: 0.9rem;
        }
        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }
        .btn-cancel {
            background: #f1f5f9;
            color: #475569;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-save {
            background: #087F8A;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-save:hover {
            background: #065C63;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 0;
            margin-bottom: 0;
        }
        .tab-btn {
            padding: 14px 24px;
            border: none;
            background: #f1f5f9;
            cursor: pointer;
            font-weight: 600;
            color: #64748b;
            border-radius: 12px 12px 0 0;
            transition: all 0.2s;
        }
        .tab-btn.active {
            background: white;
            color: #087F8A;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="p-container">
        <?php if (isset($_GET['success'])): ?>
        <div class="alert-success" style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; max-width: 1200px; margin-left: auto; margin-right: auto;">
            <i class="fas fa-check-circle" style="font-size: 18px;"></i>
            <span><strong>Berhasil!</strong> <?= intval($_GET['count'] ?? 0) ?> data kegiatan telah disimpan ke database.</span>
        </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
        <div class="alert-error" style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; max-width: 1200px; margin-left: auto; margin-right: auto;">
            <i class="fas fa-exclamation-circle" style="font-size: 18px;"></i>
            <span><strong>Error!</strong> <?= htmlspecialchars($_GET['msg'] ?? 'Terjadi kesalahan saat menyimpan data.') ?></span>
        </div>
        <?php endif; ?>

        <!-- Download Excel Section -->
        <div class="p-card" style="max-width: 1200px; margin: 0 auto 20px auto; overflow: hidden; padding: 20px 32px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div style="display: flex; flex-direction: column;">
                    <span style="font-weight: 700; font-size: 1rem; color: #087F8A;"><i class="fas fa-file-excel" style="margin-right: 8px;"></i>Download Laporan Inspeksi</span>
                    <span style="font-size: 0.8rem; color: #64748b;">Pilih rentang tanggal untuk mengunduh data dalam format Excel</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <span style="font-size: 0.8rem; color: #475569; font-weight: 600;">Dari:</span>
                        <input type="date" id="downloadDateFrom" class="table-input" value="<?= date('Y-m-01') ?>" style="padding: 8px 12px;">
                    </div>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <span style="font-size: 0.8rem; color: #475569; font-weight: 600;">Sampai:</span>
                        <input type="date" id="downloadDateTo" class="table-input" value="<?= date('Y-m-d') ?>" style="padding: 8px 12px;">
                    </div>
                    <button type="button" onclick="downloadExcel()" class="btn-excel">
                        <i class="fas fa-download"></i> Download Excel
                    </button>
                </div>
            </div>
        </div>

        <div class="p-card" style="max-width: 1200px; margin: 0 auto; overflow: hidden;">
            <!-- Tabs -->
            <div style="padding: 24px 32px 0 32px; background: #f8fafc;">
                <div class="tabs">
                    <button class="tab-btn active" onclick="switchTab('input')"><i class="fas fa-plus-circle" style="margin-right: 6px;"></i>Laporan Baru</button>
                    <button class="tab-btn" onclick="switchTab('list')"><i class="fas fa-list" style="margin-right: 6px;"></i>Data Tersimpan (<?= count($existing_data) ?>)</button>
                </div>
            </div>

            <!-- Tab: Input Baru -->
            <div id="tab-input" class="tab-content active">
                <div class="p-card-header" style="border-top: none;">
                    <div style="display:flex; justify-content:space-between; align-items:center; width: 100%;">
                        <div style="display:flex; flex-direction:column;">
                            <span class="p-section-title">Input Kegiatan Inspeksi</span>
                            <span class="p-section-subtitle">Silahkan isi tabel di bawah untuk membuat laporan</span>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 10px; background: #f1f5f9; padding: 6px 12px; border-radius: 8px; margin-left: auto;">
                            <span style="font-size: 0.85rem; font-weight: 600; color: #475569;">Tanggal Default:</span>
                            <input type="date" name="tanggal_global" id="tanggalGlobal" class="table-input" value="<?= date('Y-m-d') ?>" style="width: auto; padding: 6px 10px; border:none; background: transparent; font-size: 0.9rem; font-weight: 600; color: #087F8A;">
                        </div>
                    </div>
                </div>
                
                <form action="proses_form_inspeksi.php" method="POST" enctype="multipart/form-data" id="laporanForm">
                    <div class="data-table-container" style="margin-top: 32px;">
                        <table class="activity-table" id="activityTable">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th style="width: 20%;">Nama Kegiatan</th>
                                    <th style="width: 20%;">Lokasi</th>
                                    <th style="width: 15%;">Tanggal</th>
                                    <th style="width: 20%;">Hasil Inspeksi</th>
                                    <th style="width: 15%;">Catatan</th>
                                    <th style="width: 10%;">Dokumentasi</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="activityBody">
                            </tbody>
                        </table>
                    </div>

                    <button type="button" class="btn-add-row" onclick="addRow()">
                        <i class="fas fa-plus-circle"></i> Tambah Baris Baru
                    </button>

                    <div style="text-align: right; padding: 24px 32px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                        <button type="submit" class="p-btn-submit" name="save_kegiatan" style="padding: 12px 36px; box-shadow: 0 4px 12px rgba(8, 127, 138, 0.2);">
                            <i class="fas fa-save" style="margin-right: 8px;"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tab: Data Tersimpan -->
            <div id="tab-list" class="tab-content">
                <div class="p-card-header" style="border-top: none;">
                    <div style="display:flex; flex-direction:column;">
                        <span class="p-section-title">Data Kegiatan Tersimpan</span>
                        <span class="p-section-subtitle">Daftar kegiatan inspeksi yang sudah diinput</span>
                    </div>
                </div>

                <div class="data-table-container" style="margin-top: 20px;">
                    <?php if (count($existing_data) > 0): ?>
                    <table class="data-list-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>Kegiatan</th>
                                <th>Lokasi</th>
                                <th style="width: 100px;">Tanggal</th>
                                <th>Hasil</th>
                                <th>Catatan</th>
                                <th style="width: 70px;">Foto</th>
                                <th style="width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($existing_data as $i => $row): ?>
                            <tr id="data-row-<?= $row['id'] ?>">
                                <td style="text-align: center; color: #94a3b8;"><?= $i + 1 ?></td>
                                <td><strong><?= htmlspecialchars($row['kegiatan']) ?></strong></td>
                                <td><?= htmlspecialchars($row['lokasi']) ?></td>
                                <td style="text-align: center;"><?= date('d M Y', strtotime($row['tanggal'])) ?></td>
                                <td><?= nl2br(htmlspecialchars($row['hasil'])) ?></td>
                                <td style="color: #64748b; font-size: 0.85rem;"><?= nl2br(htmlspecialchars($row['catatan'])) ?></td>
                                <td style="text-align: center;">
                                    <?php if (!empty($row['foto']) && file_exists($row['foto'])): ?>
                                        <img src="<?= htmlspecialchars($row['foto']) ?>" class="foto-thumb" onclick="showImage('<?= htmlspecialchars($row['foto']) ?>')">
                                    <?php else: ?>
                                        <span style="color: #cbd5e1;"><i class="fas fa-image"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <button type="button" class="btn-action btn-edit" onclick="editData(<?= $row['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn-action btn-delete" onclick="deleteData(<?= $row['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Belum ada data untuk periode ini</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit" style="margin-right: 8px;"></i>Edit Kegiatan</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="editForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label>Nama Kegiatan *</label>
                    <input type="text" name="kegiatan" id="edit_kegiatan" class="table-input" required>
                </div>
                <div class="form-group">
                    <label>Lokasi</label>
                    <input type="text" name="lokasi" id="edit_lokasi" class="table-input" list="lokasiList">
                </div>
                <div class="form-group">
                    <label>Tanggal *</label>
                    <input type="date" name="tanggal" id="edit_tanggal" class="table-input" required>
                </div>
                <div class="form-group">
                    <label>Hasil Inspeksi</label>
                    <textarea name="hasil" id="edit_hasil" class="table-input" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Catatan</label>
                    <textarea name="catatan" id="edit_catatan" class="table-input" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>Foto Baru (opsional)</label>
                    <input type="file" name="foto" class="table-input" accept="image/*">
                    <div id="edit_foto_preview" style="margin-top: 8px;"></div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn-save"><i class="fas fa-save" style="margin-right: 6px;"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div class="modal-overlay" id="imageModal" onclick="this.classList.remove('show')">
        <img id="imagePreview" src="" style="max-width: 90%; max-height: 90%; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
    </div>

    <!-- Datalist for Location Suggestions -->
    <datalist id="lokasiList">
        <?php foreach ($lokasi_list as $loc): ?>
            <option value="<?= htmlspecialchars($loc) ?>">
        <?php endforeach; ?>
    </datalist>

    <script>
        let rowCount = 0;

        function addRow() {
            rowCount++;
            const tbody = document.getElementById('activityBody');
            const defaultDate = document.getElementById('tanggalGlobal').value;
            
            const tr = document.createElement('tr');
            tr.id = `row-${rowCount}`;
            
            tr.innerHTML = `
                <td style="text-align: center; color: #94a3b8; font-weight: 600; padding-top: 22px;">${rowCount}</td>
                <td><input type="text" name="items[${rowCount}][kegiatan]" class="table-input" placeholder="Nama Kegiatan..."></td>
                <td><input type="text" name="items[${rowCount}][lokasi]" list="lokasiList" class="table-input" placeholder="Lokasi..."></td>
                <td><input type="date" name="items[${rowCount}][tanggal]" class="table-input" value="${defaultDate}"></td>
                <td><textarea name="items[${rowCount}][hasil]" class="table-input" rows="1" placeholder="Hasil..." style="resize: none; height: 42px; overflow:hidden;" oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"></textarea></td>
                <td><textarea name="items[${rowCount}][catatan]" class="table-input" rows="1" placeholder="Catatan..." style="resize: none; height: 42px; overflow:hidden;" oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"></textarea></td>
                <td>
                    <div class="mini-upload" onclick="this.nextElementSibling.click()" title="Upload Foto">
                        <i class="fas fa-camera"></i>
                        <span>Img</span>
                    </div>
                    <input type="file" name="items[${rowCount}][foto]" accept="image/*" style="display:none" onchange="previewMini(this)">
                </td>
                <td style="vertical-align: middle;">
                    <button type="button" class="btn-remove-row" onclick="removeRow(${rowCount})" title="Hapus Baris">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(tr);
        }

        function removeRow(id) {
            const row = document.getElementById(`row-${id}`);
            if (row) row.remove();
        }

        function previewMini(input) {
            const triggerDiv = input.previousElementSibling; 
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    triggerDiv.innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover; border-radius:6px;">`;
                    triggerDiv.style.border = 'none';
                    triggerDiv.style.padding = '0';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Initialize with 3 rows
        window.onload = function() {
            addRow(); addRow(); addRow();
        };

        // Update default date listener
        document.getElementById('tanggalGlobal').addEventListener('change', function(e) {
            document.querySelectorAll('input[type="date"][name^="items"]').forEach(input => {
                input.value = e.target.value;
            });
        });

        // Tab switching
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            document.querySelector(`[onclick="switchTab('${tab}')"]`).classList.add('active');
            document.getElementById(`tab-${tab}`).classList.add('active');
        }

        // Download Excel
        function downloadExcel() {
            const dateFrom = document.getElementById('downloadDateFrom').value;
            const dateTo = document.getElementById('downloadDateTo').value;
            if (!dateFrom || !dateTo) {
                alert('Silahkan pilih tanggal dari dan sampai');
                return;
            }
            window.location.href = 'export/export_laporan_inspeksi.php?date_from=' + dateFrom + '&date_to=' + dateTo;
        }

        // Edit data
        function editData(id) {
            fetch('api_inspeksi.php?action=get&id=' + id)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_id').value = data.data.id;
                        document.getElementById('edit_kegiatan').value = data.data.kegiatan;
                        document.getElementById('edit_lokasi').value = data.data.lokasi || '';
                        document.getElementById('edit_tanggal').value = data.data.tanggal;
                        document.getElementById('edit_hasil').value = data.data.hasil || '';
                        document.getElementById('edit_catatan').value = data.data.catatan || '';
                        
                        const preview = document.getElementById('edit_foto_preview');
                        if (data.data.foto) {
                            preview.innerHTML = `<img src="${data.data.foto}" style="width:80px; height:60px; object-fit:cover; border-radius:6px;">`;
                        } else {
                            preview.innerHTML = '';
                        }
                        
                        document.getElementById('editModal').classList.add('show');
                    } else {
                        alert(data.message);
                    }
                });
        }

        // Close modal
        function closeModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        // Submit edit form
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('api_inspeksi.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    closeModal();
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        });

        // Delete data
        function deleteData(id) {
            if (!confirm('Yakin ingin menghapus data ini?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            
            fetch('api_inspeksi.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('data-row-' + id).remove();
                } else {
                    alert(data.message);
                }
            });
        }

        // Show image
        function showImage(src) {
            document.getElementById('imagePreview').src = src;
            document.getElementById('imageModal').classList.add('show');
        }

        // Auto-hide alerts
        setTimeout(function() {
            document.querySelectorAll('.alert-success, .alert-error').forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() { alert.style.display = 'none'; }, 500);
            });
        }, 5000);
    </script>
</body>
</html>