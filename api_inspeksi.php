<?php
session_start();
include "config/database.php";

header('Content-Type: application/json');

// Check login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0 && $action !== 'get_all') {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

switch ($action) {
    case 'delete':
        // Get photo path first to delete file
        $stmt = $conn->prepare("SELECT foto FROM kegiatan_inspeksi WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        // Delete record
        $stmt = $conn->prepare("DELETE FROM kegiatan_inspeksi WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // Delete photo file if exists
            if ($row && !empty($row['foto']) && file_exists($row['foto'])) {
                unlink($row['foto']);
            }
            echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus data: ' . $stmt->error]);
        }
        $stmt->close();
        break;
        
    case 'get':
        $stmt = $conn->prepare("SELECT * FROM kegiatan_inspeksi WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
        }
        break;
        
    case 'update':
        $kegiatan = trim($_POST['kegiatan'] ?? '');
        $lokasi = trim($_POST['lokasi'] ?? '');
        $tanggal = $_POST['tanggal'] ?? '';
        $hasil = trim($_POST['hasil'] ?? '');
        $catatan = trim($_POST['catatan'] ?? '');
        
        if (empty($kegiatan)) {
            echo json_encode(['success' => false, 'message' => 'Nama kegiatan tidak boleh kosong']);
            exit;
        }
        
        // Handle photo upload if provided
        $photo_sql = "";
        $photo_path = null;
        
        if (isset($_FILES['foto']) && !empty($_FILES['foto']['name'])) {
            $target_dir = "assets/uploads/laporan/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['foto']['name']);
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                $photo_path = $target_file;
                
                // Delete old photo
                $stmt = $conn->prepare("SELECT foto FROM kegiatan_inspeksi WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $old = $result->fetch_assoc();
                $stmt->close();
                
                if ($old && !empty($old['foto']) && file_exists($old['foto'])) {
                    unlink($old['foto']);
                }
            }
        }
        
        if ($photo_path) {
            $stmt = $conn->prepare("UPDATE kegiatan_inspeksi SET kegiatan=?, lokasi=?, tanggal=?, hasil=?, catatan=?, foto=? WHERE id=?");
            $stmt->bind_param("ssssssi", $kegiatan, $lokasi, $tanggal, $hasil, $catatan, $photo_path, $id);
        } else {
            $stmt = $conn->prepare("UPDATE kegiatan_inspeksi SET kegiatan=?, lokasi=?, tanggal=?, hasil=?, catatan=? WHERE id=?");
            $stmt->bind_param("sssssi", $kegiatan, $lokasi, $tanggal, $hasil, $catatan, $id);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Data berhasil diupdate']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengupdate data: ' . $stmt->error]);
        }
        $stmt->close();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
