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
            padding: 0; /* Remove padding to let table flush with edges or cleaner look */
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
            min-width: 900px; /* Ensure it doesn't squish too much */
        }

        .activity-table th {
            text-align: left;
            padding: 16px;
            font-size: 0.85rem;
            font-weight: 700;
            background: #087F8A; /* Teal background */
            color: #ffffff; /* White text for contrast */
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
            margin: 0 32px 32px 32px; /* Margin from edges */
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
            flex-direction: column; /* Allow stacked icon/text */
            gap: 4px;
            color: #64748b;
            font-size: 0.8rem;
            transition: all 0.2s;
            width: 60px; /* Fixed size */ 
            height: 60px;
            overflow: hidden; /* contain image */
        }
        
        .mini-upload:hover {
            border-color: #087F8A;
            color: #087F8A;
            background: #f0f9fa;
        }
    </style>
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="p-container">
        <div class="p-card" style="max-width: 1200px; margin: 0 auto; overflow: hidden;">
            <div class="p-card-header">
                
                <div style="display:flex; justify-content:space-between; align-items:center; width: 100%;">
                    <div style="display:flex; flex-direction:column;">
                        <span class="p-section-title">Input Kegiatan Inspeksi</span>
                        <span class="p-section-subtitle">Silahkan isi tabel di bawah untuk membuat laporan</span>
                    </div>
                    
                    <!-- Global Date -->
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
                            <!-- Rows will be added here by JS -->
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn-add-row" onclick="addRow()">
                    <i class="fas fa-plus-circle"></i> Tambah Baris Baru
                </button>

                <div style="text-align: right; padding: 24px 32px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                    <button type="submit" class="p-btn-submit" name="save_kegiatan" style="padding: 12px 36px; box-shadow: 0 4px 12px rgba(8, 127, 138, 0.2);">
                        <i class="fas fa-save" style="margin-right: 8px;"></i> Simpan Laporan
                    </button>
                </div>

            </form>
        </div>
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
                
                <td>
                    <input type="text" name="items[${rowCount}][kegiatan]" class="table-input" placeholder="Nama Kegiatan...">
                </td>
                
                <td>
                    <input type="text" name="items[${rowCount}][lokasi]" list="lokasiList" class="table-input" placeholder="Lokasi...">
                </td>
                
                <td>
                    <input type="date" name="items[${rowCount}][tanggal]" class="table-input" value="${defaultDate}">
                </td>
                
                <td>
                    <textarea name="items[${rowCount}][hasil]" class="table-input" rows="1" placeholder="Hasil..." style="resize: none; height: 42px; overflow:hidden;" oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"></textarea>
                </td>
                
                <td>
                    <textarea name="items[${rowCount}][catatan]" class="table-input" rows="1" placeholder="Catatan..." style="resize: none; height: 42px; overflow:hidden;" oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"></textarea>
                </td>
                
                <td>
                    <div class="mini-upload" onclick="this.nextElementSibling.click()" title="Upload Foto">
                        <i class="fas fa-camera"></i>
                        <span>Img</span>
                    </div>
                    <input type="file" name="items[${rowCount}][foto]" accept="image/*" style="display:none" onchange="previewMini(this)">
                    <div class="mini-preview"></div>
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
            // Target the .mini-upload div which is the trigger
            const triggerDiv = input.previousElementSibling; 
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Replace content of trigger div with the image
                    triggerDiv.innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover; border-radius:6px;">`;
                    triggerDiv.style.border = 'none'; // Remove dashed border
                    triggerDiv.style.padding = '0';   // Remove padding so image fits full box
                }
                reader.readAsDataURL(file);
            }
        }

        // Initialize with 3 rows for convenience
        window.onload = function() {
            addRow();
            addRow();
            addRow();
        };

        // Update default date listener
        document.getElementById('tanggalGlobal').addEventListener('change', function(e) {
            const inputs = document.querySelectorAll('input[type="date"][name^="items"]');
            inputs.forEach(input => {
                input.value = e.target.value;
            });
        });
    </script>
</body>

</html>