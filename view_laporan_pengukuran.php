<?php
include "config/database.php";
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$report_id = $_GET['id'] ?? null;

if (!$report_id) {
    header("Location: " . ($_SESSION['role'] == 'admin' ? 'admin_laporan_pengukuran.php' : 'form_laporan.php'));
    exit;
}

// Fetch report with personnel data
$stmt = $conn->prepare("SELECT lp.*, p.nama_personnel, p.jabatan FROM laporan_pengukuran lp LEFT JOIN personnel p ON lp.personel_id = p.id WHERE lp.id = ?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();

if (!$report) {
    header("Location: " . ($_SESSION['role'] == 'admin' ? 'admin_laporan_pengukuran.php' : 'form_laporan.php'));
    exit;
}

$tahananIsolasiData = json_decode($report['tahanan_isolasi_data'], true) ?? [];
$simulasiGensetData = json_decode($report['simulasi_genset_data'], true) ?? [];
$simulasiUPSData = json_decode($report['simulasi_ups_data'], true) ?? [];

$indo_months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$tanggal_formatted = date('d', strtotime($report['tanggal'])) . ' ' . 
                     $indo_months[intval(date('m', strtotime($report['tanggal'])))] . ' ' .
                     date('Y', strtotime($report['tanggal']));

$backUrl = $_SESSION['role'] == 'admin' ? 'admin_laporan_pengukuran.php' : 'form_laporan.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Laporan Pengukuran</title>
    <?php if ($_SESSION['role'] == 'admin'): ?>
    <script>
    if (localStorage.getItem('sidebarOpen') === 'true') {
        document.documentElement.classList.add('sidebar-open');
    }
    </script>
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/monitoring.css">
    <style>
        :root {
            --brand-teal: #087F8A;
            --brand-teal-dark: #065C63;
        }

        .view-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }

        .view-header {
            background: linear-gradient(135deg, #087F8A 0%, #065C63 100%);
            color: white;
            padding: 32px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(8, 127, 138, 0.25);
        }

        .view-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 8px 0;
        }

        .header-meta {
            display: flex;
            gap: 24px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.15);
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 0.9rem;
        }

        .section-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            margin-bottom: 24px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .section-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-header h2 {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            flex: 1;
        }

        .section-icon {
            width: 36px;
            height: 36px;
            background: var(--brand-teal);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .section-body {
            padding: 24px;
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
            font-size: 0.85rem;
        }

        .data-table th {
            background: var(--brand-teal);
            color: white;
            padding: 12px;
            text-align: center;
            font-weight: 600;
            font-size: 0.8rem;
            border: 1px solid #065C63;
        }

        .data-table td {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .data-table tr:nth-child(even) {
            background: #f8fafc;
        }

        .circuit-row {
            background: #f1f5f9 !important;
            font-weight: 600;
        }

        .status-normal {
            background: #dcfce7;
            color: #166534;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
        }

        .status-abnormal {
            background: #fee2e2;
            color: #dc2626;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--brand-teal);
            color: white;
        }
        .btn-primary:hover { background: var(--brand-teal-dark); }

        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover { background: #059669; }

        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .btn-secondary:hover { background: #e2e8f0; }
    </style>
</head>
<body>
    <?php if ($_SESSION['role'] == 'admin'): ?>
        <?php include 'includes/admin_sidebar.php'; ?>
        <main class="admin-main">
    <?php else: ?>
        <?php include 'includes/navbar.php'; ?>
        <div class="p-container">
    <?php endif; ?>

    <div class="view-container">
        <!-- Header -->
        <div class="view-header">
            <a href="<?= $backUrl ?>" style="color: white; text-decoration: none; font-size: 14px; opacity: 0.8;">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <h1 style="margin-top: 16px;"><i class="fas fa-file-alt" style="margin-right: 10px;"></i>Detail Laporan Pengukuran</h1>
            
            <div class="header-meta">
                <div class="meta-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span><strong>Tanggal:</strong> <?= $tanggal_formatted ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-user"></i>
                    <span><strong>Dibuat Oleh:</strong> <?= htmlspecialchars($report['nama_personnel'] ?? '-') ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-briefcase"></i>
                    <span><strong>Jabatan:</strong> <?= htmlspecialchars($report['jabatan'] ?? '-') ?></span>
                </div>
            </div>
        </div>

        <!-- Section 1: Tahanan Isolasi -->
        <?php if (!empty($tahananIsolasiData)): ?>
        <div class="section-card">
            <div class="section-header">
                <div class="section-icon"><i class="fas fa-bolt"></i></div>
                <h2>FORM TAHANAN ISOLASI</h2>
            </div>
            <div class="section-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">NO</th>
                            <th>CIRCUIT / ITEM</th>
                            <th style="width: 100px;">SATUAN</th>
                            <th style="width: 100px;">HASIL</th>
                            <th style="width: 120px;">STATUS</th>
                            <th>KETERANGAN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tahananIsolasiData as $idx => $circuit): ?>
                        <tr class="circuit-row">
                            <td style="text-align: center;"><?= $idx + 1 ?></td>
                            <td colspan="5"><?= htmlspecialchars($circuit['name'] ?? 'Circuit ' . ($idx + 1)) ?></td>
                        </tr>
                        <?php if (!empty($circuit['items'])): ?>
                            <?php foreach ($circuit['items'] as $item): ?>
                            <tr>
                                <td></td>
                                <td style="padding-left: 30px;"><?= htmlspecialchars($item['itemName'] ?? '') ?></td>
                                <td style="text-align: center;"><?= htmlspecialchars($item['satuan'] ?? '') ?></td>
                                <td style="text-align: center;"><?= htmlspecialchars($item['hasil'] ?? '-') ?></td>
                                <td style="text-align: center;">
                                    <?php if (($item['status'] ?? '') === 'NORMAL'): ?>
                                        <span class="status-normal">NORMAL</span>
                                    <?php elseif (($item['status'] ?? '') === 'TIDAK NORMAL'): ?>
                                        <span class="status-abnormal">TIDAK NORMAL</span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($item['keterangan'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Section 2: Simulasi Genset -->
        <?php if (!empty($simulasiGensetData)): ?>
        <div class="section-card">
            <div class="section-header">
                <div class="section-icon"><i class="fas fa-car-battery"></i></div>
                <h2>FORM SIMULASI GENSET</h2>
            </div>
            <div class="section-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">NO</th>
                            <th style="width: 100px;">HASIL</th>
                            <th style="width: 120px;">STATUS</th>
                            <th>KETERANGAN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($simulasiGensetData as $idx => $item): ?>
                        <tr>
                            <td style="text-align: center;"><?= $idx + 1 ?></td>
                            <td style="text-align: center;"><?= htmlspecialchars($item['hasil'] ?? '-') ?></td>
                            <td style="text-align: center;">
                                <?php if (($item['status'] ?? '') === 'NORMAL'): ?>
                                    <span class="status-normal">NORMAL</span>
                                <?php elseif (($item['status'] ?? '') === 'TIDAK NORMAL'): ?>
                                    <span class="status-abnormal">TIDAK NORMAL</span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item['keterangan'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Section 3: Simulasi UPS -->
        <?php if (!empty($simulasiUPSData)): ?>
        <div class="section-card">
            <div class="section-header">
                <div class="section-icon"><i class="fas fa-battery-full"></i></div>
                <h2>FORM SIMULASI UPS</h2>
            </div>
            <div class="section-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">NO</th>
                            <th style="width: 100px;">HASIL</th>
                            <th style="width: 120px;">STATUS</th>
                            <th>KETERANGAN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($simulasiUPSData as $idx => $item): ?>
                        <tr>
                            <td style="text-align: center;"><?= $idx + 1 ?></td>
                            <td style="text-align: center;"><?= htmlspecialchars($item['hasil'] ?? '-') ?></td>
                            <td style="text-align: center;">
                                <?php if (($item['status'] ?? '') === 'NORMAL'): ?>
                                    <span class="status-normal">NORMAL</span>
                                <?php elseif (($item['status'] ?? '') === 'TIDAK NORMAL'): ?>
                                    <span class="status-abnormal">TIDAK NORMAL</span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item['keterangan'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="<?= $backUrl ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <a href="form_laporan.php?id=<?= $report['id'] ?>" class="btn btn-success">
                <i class="fas fa-download"></i> Download Excel
            </a>
        </div>
    </div>

    <?php if ($_SESSION['role'] == 'admin'): ?>
        </main>
    <?php else: ?>
        </div>
    <?php endif; ?>
</body>
</html>
