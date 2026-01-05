<?php
include "config/database.php";
include "config/auth.php";

header('Content-Type: application/json');

$upload_dir = "assets/uploads/inspections/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$uploaded_files = [];
$errors = [];

if (isset($_FILES['fotos'])) {
    foreach ($_FILES['fotos']['name'] as $key => $name) {
        if ($_FILES['fotos']['error'][$key] == 0) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            // Use timestamp + random for unique filename
            $filename = "insp_temp_" . time() . "_" . uniqid() . "." . $ext;
            $target_path = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['fotos']['tmp_name'][$key], $target_path)) {
                // Return path relative to user folder (since scripts run from user/)
                // Actually, logic expects "assets/..." so we return that.
                $uploaded_files[] = "assets/uploads/inspections/" . $filename;
            } else {
                $errors[] = "Failed to move $name";
            }
        } else {
            $errors[] = "Error uploading $name";
        }
    }
}

echo json_encode([
    'success' => count($errors) === 0,
    'files' => $uploaded_files,
    'errors' => $errors
]);
