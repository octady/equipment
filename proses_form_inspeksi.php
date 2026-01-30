<?php
session_start();
include "config/database.php";

// Check login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

if (!isset($_POST['save_kegiatan'])) {
    header("Location: form_inspeksi.php");
    exit;
}

$items = $_POST['items'] ?? [];
$user_id = $_SESSION['user_id'] ?? null;
$success_count = 0;
$error_msg = '';

// Handle Data & Uploads
foreach ($items as $index => $item) {
    // Skip empty rows (if Nama Kegiatan is empty)
    if (empty(trim($item['kegiatan']))) {
        continue;
    }

    $kegiatan = trim($item['kegiatan']);
    $lokasi = trim($item['lokasi']);
    $tanggal = $item['tanggal'];
    $hasil = trim($item['hasil']);
    $catatan = trim($item['catatan']);

    // Photo Handling
    $photo_path = '';

    if (isset($_FILES['items']['name'][$index]['foto']) && !empty($_FILES['items']['name'][$index]['foto'])) {
        $target_dir = "assets/uploads/laporan/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_name = time() . '_' . $index . '_' . basename($_FILES['items']['name'][$index]['foto']);
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES['items']['tmp_name'][$index]['foto'], $target_file)) {
            $photo_path = $target_file;
        }
    }

    // Insert to database
    $stmt = $conn->prepare("INSERT INTO inspeksi (kegiatan, lokasi, tanggal, hasil, catatan, foto, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssi", $kegiatan, $lokasi, $tanggal, $hasil, $catatan, $photo_path, $user_id);
    
    if ($stmt->execute()) {
        $success_count++;
    } else {
        $error_msg = $stmt->error;
    }
    $stmt->close();
}

if ($success_count > 0) {
    header("Location: form_inspeksi.php?success=1&count=" . $success_count);
} else {
    $msg = $error_msg ?: 'Tidak ada data yang disimpan. Pastikan minimal mengisi nama kegiatan.';
    header("Location: form_inspeksi.php?error=1&msg=" . urlencode($msg));
}
exit;