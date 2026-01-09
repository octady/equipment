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
    $stmt = $conn->prepare("DELETE FROM laporan_pengukuran WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    header("Location: admin_laporan_pengukuran.php?deleted=1");
    exit;
}

// Filters
$filter_month = $_GET['month'] ?? '';
$filter_year = $_GET['year'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where = ["1=1"];
$params = [];
$types = "";

if ($filter_month && $filter_year) {
    $where[] = "MONTH(tanggal) = ? AND YEAR(tanggal) = ?";
    $params[] = intval($filter_month);
    $params[] = intval($filter_year);
    $types .= "ii";
} elseif ($filter_year) {
    $where[] = "YEAR(tanggal) = ?";
    $params[] = intval($filter_year);
    $types .= "i";
}

if ($search) {
    $where[] = "(dibuat_oleh LIKE ? OR jabatan LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

$query = "SELECT * FROM laporan_pengukuran WHERE " . implode(' AND ', $where) . " ORDER BY tanggal DESC, created_at DESC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$reports = $result->fetch_all(MYSQLI_ASSOC);

// Get years for filter
$years_result = $conn->query("SELECT DISTINCT YEAR(tanggal) as year FROM laporan_pengukuran ORDER BY year DESC");
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
    <title>Laporan Pengukuran - Admin</title>
    <script>
    if (localStorage.getItem('sidebarOpen') === 'true') {
        document.documentElement.classList.add('sidebar-open');
    }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #087F8A;
            --primary-dark: #065C63;
            --bg-body: #f8fafc;
            --surface: #ffffff;
            --text-main: #1e293b;
            --border: #e2e8f0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            padding-bottom: 50px;
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary);
            margin: 0 0 5px 0;
        }
        .page-title p { margin: 0; color: #64748b; font-size: 14px; }

        .filter-box {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
            display: flex;
            gap: 15px;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            flex-wrap: wrap;
        }

        select, input[type="text"] {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            color: var(--text-main);
            outline: none;
            font-size: 14px;
        }

        .btn-go {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-go:hover { background: var(--primary-dark); }

        .table-container {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th, td {
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        th {
            background: #0f172a;
            color: white;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #dcfce7; color: #166534; }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .btn-download {
            background: #10b981;
            color: white;
        }
        .btn-download:hover { background: #059669; }
        
        .btn-view {
            background: var(--primary);
            color: white;
        }
        .btn-view:hover { background: var(--primary-dark); }
        
        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
        }
        .btn-delete:hover { background: #fecaca; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .alert-success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>

    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="container">
        
        <?php if (isset($_GET['deleted'])): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i>
            Laporan berhasil dihapus!
        </div>
        <?php endif; ?>

        <div class="header-section">
            <div class="page-title">
                <h1>LAPORAN PENGUKURAN</h1>
                <p>Data Laporan Form Tahanan Isolasi, Simulasi Genset & UPS</p>
            </div>

            <form method="GET" class="filter-box">
                <input type="text" name="search" placeholder="Cari nama..." value="<?= htmlspecialchars($search) ?>">
                
                <select name="month">
                    <option value="">Semua Bulan</option>
                    <?php foreach ($indo_months as $num => $name): ?>
                        <option value="<?= $num ?>" <?= $filter_month == $num ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="year">
                    <?php foreach ($available_years as $y): ?>
                        <option value="<?= $y ?>" <?= $filter_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn-go"><i class="fa-solid fa-filter"></i></button>
                <a href="admin_laporan_pengukuran.php" title="Reset" style="color: #94a3b8; font-size: 14px;"><i class="fa-solid fa-rotate-left"></i></a>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Tanggal</th>
                        <th>Dibuat Oleh</th>
                        <th>Jabatan</th>
                        <th>Waktu Submit</th>
                        <th style="width: 280px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="fas fa-file-alt"></i>
                                <p>Belum ada laporan pengukuran yang tersimpan.</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php $no = 1; foreach ($reports as $report): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <strong><?= date('d', strtotime($report['tanggal'])) ?></strong>
                            <?= $indo_months[intval(date('m', strtotime($report['tanggal'])))] ?>
                            <?= date('Y', strtotime($report['tanggal'])) ?>
                        </td>
                        <td><?= htmlspecialchars($report['dibuat_oleh']) ?></td>
                        <td><?= htmlspecialchars($report['jabatan']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($report['created_at'])) ?></td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <a href="admin_form_laporan.php?id=<?= $report['id'] ?>" class="btn-action btn-view">
                                    <i class="fas fa-eye"></i> Lihat
                                </a>
                                <button type="button" class="btn-action btn-download" onclick="downloadExcel(<?= $report['id'] ?>)">
                                    <i class="fas fa-download"></i> Excel
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus laporan ini?');">
                                    <input type="hidden" name="delete_id" value="<?= $report['id'] ?>">
                                    <button type="submit" class="btn-action btn-delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
    </main>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 12px; text-align: center;">
            <div style="width: 40px; height: 40px; border: 4px solid #e2e8f0; border-top-color: #087F8A; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px;"></div>
            <p style="color: #1e293b; font-weight: 600;">Memproses Excel...</p>
        </div>
    </div>
    <style>@keyframes spin { to { transform: rotate(360deg); } }</style>

    <script>
    // Store all reports data for Excel download
    const reportsData = <?= json_encode(array_map(function($r) {
        return [
            'id' => $r['id'],
            'tanggal' => $r['tanggal'],
            'dibuat_oleh' => $r['dibuat_oleh'],
            'jabatan' => $r['jabatan'],
            'tahanan_isolasi_data' => json_decode($r['tahanan_isolasi_data'] ?: '[]', true),
            'simulasi_genset_data' => json_decode($r['simulasi_genset_data'] ?: '[]', true),
            'simulasi_ups_data' => json_decode($r['simulasi_ups_data'] ?: '[]', true)
        ];
    }, $reports)) ?>;

    async function downloadExcel(reportId) {
        const report = reportsData.find(r => r.id == reportId);
        if (!report) {
            alert('Laporan tidak ditemukan!');
            return;
        }

        document.getElementById('loadingOverlay').style.display = 'flex';

        try {
            const payload = {
                tanggal: report.tanggal,
                dibuatOleh: report.dibuat_oleh,
                jabatan: report.jabatan,
                tahananIsolasi: report.tahanan_isolasi_data,
                simulasiGenset: report.simulasi_genset_data,
                simulasiUPS: report.simulasi_ups_data
            };

            const response = await fetch('export_laporan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Export failed');
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `Laporan_Pengukuran_${report.tanggal}.xlsx`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            a.remove();

        } catch (error) {
            console.error('Error:', error);
            alert('Terjadi kesalahan: ' + error.message);
        } finally {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
    }
    </script>
</body>
</html>
