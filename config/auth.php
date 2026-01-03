<?php
// Authentication Guard
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Get user info from session
$nama_lengkap = $_SESSION['nama_lengkap'] ?? 'Teknisi';
$username = $_SESSION['username'] ?? '';
$user_role = $_SESSION['role'] ?? 'user';
?>
