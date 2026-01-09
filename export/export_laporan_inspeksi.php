<?php
session_start();
include "../config/database.php";

// Check login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Fetch data from database
$stmt = $conn->prepare("SELECT * FROM kegiatan_inspeksi WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal ASC, id ASC");
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$result = $stmt->get_result();
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$stmt->close();

if (count($data) == 0) {
    echo "<script>alert('Tidak ada data untuk periode yang dipilih.'); window.history.back();</script>";
    exit;
}

// Generate Excel (HTML Format)
$filename = "Laporan_Kegiatan_" . $date_from . "_to_" . $date_to . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid black;
            padding: 8px;
            vertical-align: middle;
        }
        th {
            background-color: #087F8A;
            color: white;
            text-align: center;
            font-weight: bold;
        }
        .header-title {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
        }
        .info-table td {
            border: none;
            padding: 4px;
        }
    </style>
</head>
<body>

    <h3 class="header-title" style="text-align:center;">LAPORAN KEGIATAN INSPEKSI & PENGUJIAN</h3>

    <table class="info-table" style="margin-bottom: 20px; border: none;">
        <tr>
            <td style="width: 150px; border:none;">Kantor Cabang</td>
            <td style="width: 10px; border:none;">:</td>
            <td style="border:none;">Bandara Radin Inten II</td>
        </tr>
        <tr>
            <td style="border:none;">Unit</td>
            <td style="border:none;">:</td>
            <td style="border:none;">Airport Equipment</td>
        </tr>
        <tr>
            <td style="border:none;">Periode</td>
            <td style="border:none;">:</td>
            <td style="border:none;">
                <?= date('d M Y', strtotime($date_from)) ?> s/d <?= date('d M Y', strtotime($date_to)) ?>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 50px;">NO</th>
                <th style="width: 250px;">NAMA KEGIATAN</th>
                <th style="width: 150px;">LOKASI</th>
                <th style="width: 120px;">TANGGAL</th>
                <th style="width: 250px;">HASIL INSPEKSI / PENGUJIAN</th>
                <th style="width: 200px;">CATATAN</th>
                <th style="width: 150px;">DOKUMENTASI</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $i => $row): ?>
                <tr style="height: 100px;">
                    <td style="text-align: center;">
                        <?= $i + 1 ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($row['kegiatan']) ?>
                    </td>
                    <td style="text-align: center;">
                        <?= htmlspecialchars($row['lokasi']) ?>
                    </td>
                    <td style="text-align: center;">
                        <?= date('d-M-y', strtotime($row['tanggal'])) ?>
                    </td>
                    <td>
                        <?= nl2br(htmlspecialchars($row['hasil'])) ?>
                    </td>
                    <td>
                        <?= nl2br(htmlspecialchars($row['catatan'])) ?>
                    </td>
                    <td style="text-align: center;">
                        <?php if (!empty($row['foto'])):
                            $clean_foto_path = str_replace('\\', '/', $row['foto']);
                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                            $host = $_SERVER['HTTP_HOST'];
                            $base_path = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
                            $base_path = rtrim($base_path, '/');
                            $full_url = $protocol . $host . $base_path . '/' . $clean_foto_path;
                            echo '<img src="' . $full_url . '" width="120" height="90" style="object-fit:cover;">';
                        endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</body>
</html>
