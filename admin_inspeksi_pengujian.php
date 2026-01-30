<?php
include "config/database.php";
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

// Handle Delete
if (isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    
    // Get file path first to delete image
    $stmt = $conn->prepare("SELECT foto FROM inspeksi WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['foto']) && file_exists($row['foto'])) {
            unlink($row['foto']);
        }
    }
    $stmt->close();
    
    // Delete record
    $stmt = $conn->prepare("DELETE FROM inspeksi WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    header("Location: admin_inspeksi_pengujian.php?deleted=1");
    exit;
}

// AJAX Handler
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    // Prevent Caching
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Clear output buffer
    if (ob_get_length()) ob_clean();
    
    $filter_month = $_GET['month'] ?? '';
    $filter_year = $_GET['year'] ?? '';
    $search = $_GET['search'] ?? '';

    $where = ["1=1"];
    $params = [];
    $types = "";

    if ($filter_month && $filter_year) {
        $where[] = "MONTH(tanggal) = ? AND YEAR(tanggal) = ?";
        $params[] = intval($filter_month);
        $params[] = intval($filter_year);
        $types .= "ii";
    } elseif ($filter_month) {
        $where[] = "MONTH(tanggal) = ?";
        $params[] = intval($filter_month);
        $types .= "i";
    } elseif ($filter_year) {
        $where[] = "YEAR(tanggal) = ?";
        $params[] = intval($filter_year);
        $types .= "i";
    }

    if ($search) {
        $where[] = "(kegiatan LIKE ? OR lokasi LIKE ? OR hasil LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "sss";
    }

    $query = "SELECT * FROM inspeksi WHERE " . implode(' AND ', $where) . " ORDER BY tanggal DESC, created_at DESC";
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $records = $result->fetch_all(MYSQLI_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($records);
    exit;
}

// Initial Data Load (Get years only)
$years_result = $conn->query("SELECT DISTINCT YEAR(tanggal) as year FROM inspeksi ORDER BY year DESC");
$available_years = [];
while ($row = $years_result->fetch_assoc()) {
    $available_years[] = $row['year'];
}
if (empty($available_years)) {
    $available_years[] = date('Y');
}

$indo_months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspeksi dan Pengujian - Admin</title>
    <!-- Cache busting meta -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    
    <script>
    if (localStorage.getItem('sidebarOpen') === 'true') {
        document.documentElement.classList.add('sidebar-open');
    }
    </script>
    <!-- Load Plus Jakarta Sans (Standard Font) -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    
    <style>
        /* Force Plus Jakarta Sans globally */
        :root, body, h1, h2, h3, h4, p, span, td, th, div, input, select, button {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        body { background: #f8fafc; margin: 0; padding: 0; }
        
        /* Layout Fixes - CLOSED STATE (Default) */
        .main-content {
            /* Top: 30px, Right: 20px, Bottom: 20px */
            /* LEFT: 80px (To avoid overlap with hamburger button) */
            padding: 30px 20px 20px 80px !important; 
            box-sizing: border-box !important;
            width: 100% !important;
            margin-left: 0 !important;
        }

        /* Sidebar adjustment - OPEN STATE */
        html.sidebar-open body .main-content {
            /* No extra margin needed - body already has padding-left from sidebar */
            margin-left: 0 !important;
            
            /* Full width minus sidebar */
            width: 100% !important;
            
            /* Standard padding */
            padding: 30px 20px 20px 20px !important;
        }
        
        /* Utilities */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .header-title h1 { font-size: 24px; font-weight: 700; color: #1e293b; margin: 0 0 4px 0; }
        .header-title p { color: #64748b; margin: 0; font-size: 14px; }
        
        .filter-section { 
            background: white; 
            padding: 16px; 
            border-radius: 12px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); 
            display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; 
            border: 1px solid #e2e8f0;
        }
        .filter-input { 
            padding: 10px 14px; 
            border: 1px solid #e2e8f0; 
            border-radius: 8px; 
            background: #fff;
            color: #1e293b;
            font-size: 14px;
        }
        .filter-input:focus { outline: 2px solid #087F8A; border-color: transparent; }
        .search-box { flex: 1; min-width: 200px; }
        
        .table-container { 
            background: white; 
            border-radius: 12px; 
            overflow: hidden; 
            border: 1px solid #e2e8f0; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        table { width: 100%; border-collapse: collapse; }
        th { 
            background: #f8fafc; 
            padding: 14px 16px; 
            text-align: left; 
            font-size: 12px; 
            font-weight: 600; 
            color: #64748b; 
            border-bottom: 1px solid #e2e8f0; 
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        td { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #334155; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: #f8fafc; }
        
        .btn-download { 
            background: #10b981; 
            color: white; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 8px; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            font-weight: 600; 
            font-size: 14px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-download:hover { background: #059669; }
        
        .btn-delete { 
            background: #ef4444; 
            color: white; 
            border: none; 
            padding: 8px; 
            border-radius: 6px; 
            cursor: pointer; 
            width: 32px; 
            height: 32px; 
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .btn-delete:hover { background: #dc2626; }
        
        .photo-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; cursor: pointer; border: 1px solid #e2e8f0; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center; }
        .modal-content { max-width: 90%; max-height: 90%; border-radius: 8px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body>
    <?php include "includes/admin_sidebar.php"; ?>

    <!-- INLINE STYLES TO FORCE LAYOUT -->
    <main class="main-content">
        <div class="page-header">
            <div class="header-title">
                <h1>Inspeksi dan Pengujian</h1>
                <p>Data laporan hasil inspeksi lapangan</p>
            </div>
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <input type="date" id="downloadDateFrom" class="filter-input" value="<?= date('Y-m-01') ?>" style="padding: 8px 12px;">
                <span style="color: #64748b;">s/d</span>
                <input type="date" id="downloadDateTo" class="filter-input" value="<?= date('Y-m-d') ?>" style="padding: 8px 12px;">
                <button onclick="downloadExcel()" class="btn-download">
                    <i class="fas fa-file-excel"></i> Download Excel
                </button>
            </div>
        </div>

        <?php if (isset($_GET['deleted'])): ?>
        <div style="background: #dcfce7; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
            <i class="fas fa-check-circle"></i> Data berhasil dihapus.
        </div>
        <?php endif; ?>

        <div class="filter-section">
            <select id="filterMonth" class="filter-input" onchange="loadData()">
                <option value="">Semua Bulan</option>
                <?php foreach ($indo_months as $num => $name): ?>
                    <option value="<?= $num ?>"><?= $name ?></option>
                <?php endforeach; ?>
            </select>
            
            <select id="filterYear" class="filter-input" onchange="loadData()">
                <option value="">Semua Tahun</option>
                <?php foreach ($available_years as $y): ?>
                    <option value="<?= $y ?>"><?= $y ?></option>
                <?php endforeach; ?>
            </select>
            
            <input type="text" id="searchInput" class="filter-input search-box" placeholder="Cari Kegiatan / Lokasi..." oninput="loadData()">
        </div>

        <div class="table-container">
            <table id="dataTable">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th width="120">Tanggal</th>
                        <th>Kegiatan</th>
                        <th>Lokasi</th>
                        <th>Hasil</th>
                        <th>Catatan</th>
                        <th>Foto</th>
                        <th width="60" style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="8" style="text-align: center; padding: 20px;">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
    </main>

    <div id="imageModal" class="modal" onclick="this.style.display='none'">
        <img class="modal-content" id="modalImage">
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', loadData);

        function loadData() {
            const month = encodeURIComponent(document.getElementById('filterMonth').value);
            const year = encodeURIComponent(document.getElementById('filterYear').value);
            const search = encodeURIComponent(document.getElementById('searchInput').value);

            const tbody = document.getElementById('tableBody');
            
            fetch(`admin_inspeksi_pengujian.php?ajax=1&month=${month}&year=${year}&search=${search}&t=${new Date().getTime()}`)
                .then(response => {
                    if (!response.ok) throw new Error('Net Error');
                    return response.json();
                })
                .then(data => {
                    tbody.innerHTML = '';
                    if (!data || data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 40px; color: #64748b;">Tidak ada data ditemukan</td></tr>';
                        return;
                    }
                    data.forEach((row, index) => {
                        const date = new Date(row.tanggal);
                        const formattedDate = date.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
                        const photoHtml = row.foto ? `<img src="${row.foto}" class="photo-thumb" onclick="showImage('${row.foto}')">` : '-';
                        
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${index + 1}</td>
                            <td>${formattedDate}</td>
                            <td>${escapeHtml(row.kegiatan)}</td>
                            <td>${escapeHtml(row.lokasi)}</td>
                            <td>${escapeHtml(row.hasil)}</td>
                            <td>${escapeHtml(row.catatan)}</td>
                            <td>${photoHtml}</td>
                            <td style="text-align: center;">
                                <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?');" style="display:inline;">
                                    <input type="hidden" name="delete_id" value="${row.id}">
                                    <button class="btn-delete" title="Hapus"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                })
                .catch(err => {
                    console.error(err);
                    tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: red;">Gagal memuat data. Silakan refresh.</td></tr>`;
                });
        }

        function escapeHtml(text) {
            return text ? text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;") : '-';
        }
        function showImage(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').style.display = 'flex';
        }
        function downloadExcel() {
            const dateFrom = document.getElementById('downloadDateFrom').value;
            const dateTo = document.getElementById('downloadDateTo').value;
            if (!dateFrom || !dateTo) {
                alert('Silahkan pilih tanggal dari dan sampai');
                return;
            }
            window.location.href = 'export/export_laporan_inspeksi.php?date_from=' + dateFrom + '&date_to=' + dateTo;
        }
    </script>
</body>
</html>
