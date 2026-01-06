<?php
session_start();
include "config/database.php";

if (!isset($_POST['save_kegiatan'])) {
    header("Location: form_laporan.php");
    exit;
}

$items = $_POST['items'] ?? [];
$data_for_excel = [];

// Handle Data & Uploads
foreach ($items as $index => $item) {
    // Skip empty rows (if Nama Kegiatan is empty)
    if (empty($item['kegiatan'])) {
        continue;
    }

    $kegiatan = htmlspecialchars($item['kegiatan']);
    $lokasi = htmlspecialchars($item['lokasi']);
    $tanggal = htmlspecialchars($item['tanggal']);
    $hasil = htmlspecialchars($item['hasil']);
    $catatan = htmlspecialchars($item['catatan']);

    // Photo Handling
    $photo_path = ''; // Default empty

    // Check if file uploaded for this index
    // Note: $_FILES['items']['name'][$index]['foto'] structure depends on how PHP parses recursive file arrays
    // Actually simplicity: we used name="items[index][foto]" (singular) in the updated form
    // PHP arranges files as $_FILES['items']['name'][$rowCount]['foto'] 

    if (isset($_FILES['items']['name'][$index]['foto']) && !empty($_FILES['items']['name'][$index]['foto'])) {
        $target_dir = "assets/uploads/laporan/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_name = time() . '_' . basename($_FILES['items']['name'][$index]['foto']);
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES['items']['tmp_name'][$index]['foto'], $target_file)) {
            // Store absolute path or relative path accessible by web
            // For Excel export to show image, we often need absolute path or base64
            // Let's store relative path in DB logic (if we were DB-ing), but for runtime here we keep track
            $photo_path = $target_file;
        }
    }

    $data_for_excel[] = [
        'kegiatan' => $kegiatan,
        'lokasi' => $lokasi,
        'tanggal' => $tanggal,
        'hasil' => $hasil,
        'catatan' => $catatan,
        'foto' => $photo_path
    ];
}

// Generate Excel (HTML Format)
$filename = "Laporan_Kegiatan_" . date('Y-m-d_H-i') . ".xls";

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

        th,
        td {
            border: 1px solid black;
            padding: 8px;
            vertical-align: middle;
        }

        th {
            background-color: #f2f2f2;
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
            <td style="border:none;">Bulan / Tahun</td>
            <td style="border:none;">:</td>
            <td style="border:none;">
                <?= date('F / Y') ?>
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
            <?php foreach ($data_for_excel as $i => $row): ?>
                <tr style="height: 100px;">
                    <td style="text-align: center;">
                        <?= $i + 1 ?>
                    </td>
                    <td>
                        <?= $row['kegiatan'] ?>
                    </td>
                    <td style="text-align: center;">
                        <?= $row['lokasi'] ?>
                    </td>
                    <td style="text-align: center;">
                        <?= date('d-M-y', strtotime($row['tanggal'])) ?>
                    </td>
                    <td>
                        <?= nl2br($row['hasil']) ?>
                    </td>
                    <td>
                        <?= nl2br($row['catatan']) ?>
                    </td>
                    <td style="text-align: center;">
                        <?php if (!empty($row['foto'])):
                            // Fix Windows path separators
                            $clean_foto_path = str_replace('\\', '/', $row['foto']);

                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                            $host = $_SERVER['HTTP_HOST'];

                            // dirname might return backslashes on Windows, so we normalize it
                            $base_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

                            // Ensure no double slashes
                            $base_path = rtrim($base_path, '/');

                            $full_url = $protocol . $host . $base_path . '/' . $clean_foto_path;

                            echo '<img src="' . $full_url . '" width="120" height="90" style="object-fit:cover;">';
                            ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</body>

</html>