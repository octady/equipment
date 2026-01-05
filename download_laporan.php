<?php
// Script khusus untuk mendownload file laporan
// Ini menghindari masalah "File Not Found" karena salah path URL

$filepath = 'assets/dokumen/template_laporan.xlsx';

if (file_exists($filepath)) {
    // Reset buffer
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Header setup
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));

    // Kirim file
    readfile($filepath);
    exit;
} else {
    // Error handling jika file fisik benar-benar tidak ada
    echo "Maaf, file laporan belum tersedia di server.";
    echo "<br>path: " . realpath($filepath);
}
?>