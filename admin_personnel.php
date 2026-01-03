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

// -- HANDLE FORM SUBMISSION --
// Use session for alerts: $_SESSION['alert'] = ['type' => 'success/error', 'message' => '...'];

// 1. ADD
if (isset($_POST['add'])) {
    $nama = strtoupper($_POST['nama_personnel']); 
    $jabatan = $_POST['jabatan'];
    
    $stmt = $conn->prepare("INSERT INTO personnel (nama_personnel, jabatan) VALUES (?, ?)");
    $stmt->bind_param("ss", $nama, $jabatan);
    
    if ($stmt->execute()) {
        $_SESSION['alert'] = ['type' => 'success', 'message' => 'Personil berhasil ditambahkan!'];
    } else {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'Gagal menambahkan personil.'];
    }
    header("Location: admin_personnel.php");
    exit;
}

// 2. UPDATE
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $nama = strtoupper($_POST['nama_personnel']);
    $jabatan = $_POST['jabatan'];

    $stmt = $conn->prepare("UPDATE personnel SET nama_personnel = ?, jabatan = ? WHERE id = ?");
    $stmt->bind_param("ssi", $nama, $jabatan, $id);

    if ($stmt->execute()) {
        $_SESSION['alert'] = ['type' => 'success', 'message' => 'Data personil berhasil diperbarui!'];
    } else {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'Gagal memperbarui data personil.'];
    }
    header("Location: admin_personnel.php");
    exit;
}

// 3. DELETE
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM personnel WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['alert'] = ['type' => 'success', 'message' => 'Personil berhasil dihapus!'];
    } else {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'Gagal menghapus personil.'];
    }
    header("Location: admin_personnel.php");
    exit;
}

// Fetch All Personnel
$personnel = $conn->query("SELECT * FROM personnel ORDER BY nama_personnel ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Personil - Equipment Monitoring</title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #087F8A;
            --primary-dark: #065C63;
            --secondary: #0ea5e9;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg-body: #f8fafc;
            --text-main: #1e293b;
            --border: #e2e8f0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            padding: 0;
        }

        .admin-main {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Card Styles */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            border: 1px solid var(--border);
            margin-bottom: 25px;
            overflow: hidden;
        }

        .card-header {
            background: white;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            color: var(--primary-dark);
            font-size: 16px;
        }

        .card-body {
            padding: 24px;
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group { margin-bottom: 15px; }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-family: inherit;
            color: var(--text-main);
            background: #f8fafc;
            transition: 0.2s;
            box-sizing: border-box; 
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(8, 127, 138, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            font-family: inherit;
            font-size: 14px;
        }

        .btn-primary { background: linear-gradient(135deg, #087F8A, #065C63); color: white; box-shadow: 0 2px 10px rgba(8, 127, 138, 0.2); }
        .btn-primary:hover { box-shadow: 0 4px 15px rgba(8, 127, 138, 0.3); transform: translateY(-1px); }

        .btn-cancel { background: white; border: 1px solid var(--border); color: #64748b; }
        .btn-cancel:hover { background: #f1f5f9; color: var(--danger); border-color: #fda4af; }

        /* Table Styles */
        .table-responsive { overflow-x: auto; border-radius: 12px; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        
        thead th {
            background: var(--primary-dark); 
            color: white;
            padding: 16px;
            text-align: left;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            vertical-align: middle;
            color: #334155;
        }

        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background-color: #f8fafc; }

        /* Badges & Actions */
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            background: #e0f2fe;
            color: #0369a1;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: 0.2s;
            font-size: 14px;
            text-decoration: none;
        }

        .btn-edit { background: #e0f2fe; color: #0284c7; }
        .btn-edit:hover { background: #0284c7; color: white; }

        .btn-delete { background: #fee2e2; color: #ef4444; }
        .btn-delete:hover { background: #ef4444; color: white; }

        /* Sweet Alert Small Override */
        .swal-title-sm { font-size: 18px !important; }
        .swal-text-sm { font-size: 13px !important; }
        .swal2-popup.swal-popup-sm { font-size: 0.9rem !important; border-radius: 12px; }
        .swal2-icon { transform: scale(0.8); margin: 1em auto 0 !important; }
        .swal2-actions { margin: 1em auto 0 !important; }

        /* Modal Styles */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.5); 
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto; 
            padding: 0;
            border: 1px solid var(--border);
            width: 90%;
            max-width: 500px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {transform: translateY(-50px); opacity: 0;}
            to {transform: translateY(0); opacity: 1;}
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title { font-weight: 700; font-size: 18px; color: var(--primary-dark); }
        .close-modal { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; transition: 0.2s;}
        .close-modal:hover { color: var(--danger); }
        
        .modal-body { padding: 24px; }


        /* Responsive */
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .admin-main { padding: 15px; }
        }
    </style>
</head>
<body>

    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fa-solid fa-users-gear"></i> Kelola Personil</h1>
            </div>
        </div>

        <!-- Add Personnel Form (Visible) -->
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-user-plus"></i> Tambah Personil Baru
            </div>
            <div class="card-body">
                <form method="POST" id="addPersonnelForm" onsubmit="confirmSave(event)">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nama Lengkap</label>
                            <input type="text" name="nama_personnel" class="form-control" placeholder="Contoh: BUDI SANTOSO" required>
                            <small style="display:block; margin-top:5px; color:#94a3b8; font-size:12px;">*Otomatis kapital saat disimpan</small>
                        </div>
                        <div class="form-group">
                            <label>Jabatan</label>
                            <select name="jabatan" class="form-control" required>
                                <option value="">Pilih Jabatan</option>
                                <option value="Airport Equipment Supervisor">Airport Equipment Supervisor</option>
                                <option value="Airport Equipment Engineer">Airport Equipment Engineer</option>
                                <option value="Technician">Technician</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top: 25px; text-align: right;">
                        <button type="submit" name="add" class="btn btn-primary">
                            <i class="fa-solid fa-plus"></i> Tambah Personil
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Personnel List -->
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-list-ul"></i> Daftar Personil
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 60px; text-align: center;">No</th>
                                <th>Nama Personil</th>
                                <th>Jabatan</th>
                                <th style="width: 120px; text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($personnel) > 0): ?>
                                <?php foreach($personnel as $idx => $p): ?>
                                <tr>
                                    <td style="text-align: center; color: #64748b; font-weight: 600;"><?= $idx+1 ?></td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--primary-dark);"><?= htmlspecialchars($p['nama_personnel']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge"><?= htmlspecialchars($p['jabatan']) ?></span>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <button 
                                                onclick="openEditModal(this)"
                                                data-id="<?= $p['id'] ?>"
                                                data-nama="<?= htmlspecialchars($p['nama_personnel']) ?>"
                                                data-jabatan="<?= htmlspecialchars($p['jabatan']) ?>"
                                                class="action-btn btn-edit" 
                                                title="Edit">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <button onclick="confirmDelete(<?= $p['id'] ?>)" class="action-btn btn-delete" title="Hapus">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 40px; color: #94a3b8;">
                                        <i class="fa-solid fa-users-slash" style="font-size: 32px; margin-bottom: 10px;"></i>
                                        <p>Belum ada data personil.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- EDIT MODAL -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Edit Data Personil</div>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="editPersonnelForm" onsubmit="confirmSave(event)">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_personnel" id="edit_nama" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Jabatan</label>
                        <select name="jabatan" id="edit_jabatan" class="form-control" required>
                            <option value="">Pilih Jabatan</option>
                            <option value="Airport Equipment Supervisor">Airport Equipment Supervisor</option>
                            <option value="Airport Equipment Engineer">Airport Equipment Engineer</option>
                            <option value="Technician">Technician</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div style="margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="btn btn-cancel" onclick="closeModal()">Batal</button>
                        <button type="submit" name="update" class="btn btn-primary">
                            <i class="fa-solid fa-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Use Session Alert
        <?php if(isset($_SESSION['alert'])): ?>
            Swal.fire({
                icon: '<?= $_SESSION['alert']['type'] ?>',
                title: '<?= $_SESSION['alert']['type'] == 'success' ? 'Berhasil' : 'Gagal' ?>',
                text: '<?= $_SESSION['alert']['message'] ?>',
                timer: 2000,
                width: '320px',
                padding: '1em',
                showConfirmButton: false,
                backdrop: `rgba(0,0,123,0.1)`,
                customClass: { title: 'swal-title-sm', popup: 'swal-popup-sm' }
            });
            <?php unset($_SESSION['alert']); ?>
        <?php endif; ?>

        // Modal Logic
        function openEditModal(btn) {
            const id = btn.getAttribute('data-id');
            const nama = btn.getAttribute('data-nama');
            const jabatan = btn.getAttribute('data-jabatan');

            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_jabatan').value = jabatan;

            document.getElementById('editModal').style.display = "block";
        }

        function closeModal() {
            document.getElementById('editModal').style.display = "none";
        }

        // Close modal if clicked outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('editModal')) {
                closeModal();
            }
        }

        // Confirm Delete
        function confirmDelete(id) {
            Swal.fire({
                title: 'Hapus Data?',
                text: "Data akan dihapus permanen!",
                icon: 'warning',
                width: '320px',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                customClass: { title: 'swal-title-sm', popup: 'swal-popup-sm', htmlContainer: 'swal-text-sm' }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'admin_personnel.php?delete=' + id;
                }
            })
        }

        // Confirm Save for Forms
        function confirmSave(e) {
            e.preventDefault();
            const form = e.target;
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

             Swal.fire({
                title: 'Simpan Data?',
                text: "Pastikan data sudah benar.",
                icon: 'question',
                width: '320px',
                showCancelButton: true,
                confirmButtonColor: '#087F8A',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Simpan',
                cancelButtonText: 'Batal',
                customClass: { title: 'swal-title-sm', popup: 'swal-popup-sm', htmlContainer: 'swal-text-sm' }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Need to manually add the submit button's name value because programmatic submit doesn't include it
                    // The easiest way is to append a hidden input with the name of the submit button that would have been clicked
                    // Add Form: has name="add", Edit Form has name="update"
                    
                    const actionName = form.id === 'addPersonnelForm' ? 'add' : 'update';
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = actionName;
                    hiddenInput.value = '1';
                    form.appendChild(hiddenInput);
                    
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>
