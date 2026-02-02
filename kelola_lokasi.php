<?php
include "config/database.php";
include "config/auth.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nama = trim($_POST['nama_lokasi'] ?? '');

        if ($nama === '') {
            echo json_encode(['success' => false, 'message' => 'Nama lokasi wajib diisi']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO lokasi (nama_lokasi) VALUES (?)");
        $stmt->bind_param("s", $nama);
        echo json_encode(
            $stmt->execute()
            ? ['success' => true, 'message' => 'Lokasi berhasil ditambahkan']
            : ['success' => false, 'message' => 'Gagal menambah lokasi: ' . $conn->error]
        );
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $nama = trim($_POST['nama_lokasi'] ?? '');

        if ($id <= 0 || $nama === '') {
            echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE lokasi SET nama_lokasi=? WHERE id=?");
        $stmt->bind_param("si", $nama, $id);
        echo json_encode(
            $stmt->execute()
            ? ['success' => true, 'message' => 'Lokasi diperbarui']
            : ['success' => false, 'message' => 'Gagal update: ' . $conn->error]
        );
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $check = $conn->prepare("SELECT COUNT(*) cnt FROM equipments WHERE lokasi_id=?");
        $check->bind_param("i", $id);
        $check->execute();
        if ($check->get_result()->fetch_assoc()['cnt'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Lokasi masih memiliki peralatan']);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM lokasi WHERE id=?");
        $stmt->bind_param("i", $id);
        echo json_encode(
            $stmt->execute()
            ? ['success' => true, 'message' => 'Lokasi dihapus']
            : ['success' => false, 'message' => 'Gagal hapus']
        );
        exit;
    }
}

$q = "SELECT l.id, l.nama_lokasi, COUNT(e.id) equipment_count
      FROM lokasi l
      LEFT JOIN equipments e ON l.id=e.lokasi_id
      GROUP BY l.id ORDER BY l.nama_lokasi";
$res = $conn->query($q);
if (!$res) {
    die("Error Database (Query Utama): " . $conn->error);
}
$data = [];
while ($r = $res->fetch_assoc())
    $data[] = $r;

$total_lokasi = count($data);
$total_equipment = array_sum(array_column($data, 'equipment_count'));
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Lokasi</title>
    <script>
    if (localStorage.getItem('sidebarOpen') === 'true') {
        document.documentElement.classList.add('sidebar-open');
    }
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8fafc;
            margin: 0;
        }

        .container {
            max-width: 1100px;
            margin: auto
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px
        }

        button {
            cursor: pointer;
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            font-weight: 600
        }

        .btn-add {
            background: #087F8A;
            color: #fff
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden
        }

        th,
        td {
            padding: 14px 16px;
            text-align: left
        }

        thead {
            background: #e6f4f5
        }

        tr:not(:last-child) {
            border-bottom: 1px solid #e5e7eb
        }

        .badge {
            background: #e6f4f5;
            color: #087F8A;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            align-items: center;
            justify-content: center
        }

        .modal.active {
            display: flex
        }

        .modal-box {
            background: #fff;
            padding: 24px;
            border-radius: 16px;
            width: 100%;
            max-width: 400px
        }

        input {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1
        }

        .actions button {
            margin-right: 6px
        }

        .success,
        .error {
            display: none;
            margin-bottom: 12px;
            padding: 12px;
            border-radius: 10px
        }

        .success {
            background: #10b981;
            color: #fff
        }

        .error {
            background: #ef4444;
            color: #fff
        }

        .admin-main {
            padding: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid #e2e8f0;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-info h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
        }

        .stat-info p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        .bg-blue {
            background: #eff6ff;
            color: #3b82f6;
        }

        .bg-purple {
            background: #f3e8ff;
            color: #a855f7;
        }
        
        /* Action Buttons Professional Style */
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
        
        /* Responsive */
        @media (max-width: 992px) {
            .admin-main {
                padding: 25px 15px;
            }
        }
        
        @media (max-width: 768px) {
            .admin-main {
                padding: 70px 15px 15px 15px;
            }
            
            .container {
                max-width: 100%;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .header h2 {
                font-size: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            
            .stat-card {
                padding: 16px;
            }
            
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 20px;
            }
            
            .stat-info h3 {
                font-size: 20px;
            }
            
            .stat-info p {
                font-size: 12px;
            }
            
            .search-box {
                flex-direction: column;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .search-box button {
                width: 100%;
            }
            
            /* Table Responsive */
            table {
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            th, td {
                padding: 12px 10px;
            }
            
            .modal-box {
                max-width: 90%;
                margin: 20px;
            }
        }
        
        @media (max-width: 576px) {
            .admin-main {
                padding: 65px 10px 10px 10px;
            }
            
            .header h2 {
                font-size: 18px;
            }
            
            .btn-add {
                padding: 8px 12px;
                font-size: 13px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .stat-card {
                padding: 14px;
            }
            
            .stat-icon {
                width: 36px;
                height: 36px;
                font-size: 18px;
            }
            
            .stat-info h3 {
                font-size: 18px;
            }
            
            /* Table minimum width */
            table {
                min-width: 400px;
            }
            
            th, td {
                padding: 10px 8px;
                font-size: 13px;
            }
            
            .badge {
                font-size: 11px;
                padding: 3px 8px;
            }
            
            .action-btn {
                width: 32px;
                height: 32px;
            }
            
            .modal-box {
                max-width: 95%;
                padding: 16px;
                margin: 10px;
            }
            
            .modal-box h3 {
                font-size: 16px;
            }
            
            .modal-box input {
                padding: 10px;
                font-size: 14px;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="container">

            <div class="success" id="success"></div>
            <div class="error" id="error"></div>

            <div class="header" style="margin-bottom: 20px;">
                <div>
                    <h2>Kelola Lokasi</h2>
                </div>
                <button class="btn-add" onclick="openModal()">
                    <i class="fas fa-plus" style="margin-right: 8px;"></i>Tambah Lokasi
                </button>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon bg-blue">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $total_lokasi ?></h3>
                        <p>Total Lokasi</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-purple">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $total_equipment ?></h3>
                        <p>Total Peralatan</p>
                    </div>
                </div>
            </div>

            <div class="search-box" style="display: flex; gap: 10px; margin-bottom: 20px;">
                <input type="text" id="keyword" placeholder="Cari nama lokasi..."
                    style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1;" onkeyup="search()">
                <button onclick="search()"
                    style="padding: 12px 20px; background: #0e7490; color: white; border: none; border-radius: 8px; cursor: pointer; transition: 0.2s;">
                    <i class="fas fa-search"></i>
                </button>
            </div>

            <div class="table-wrapper" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Lokasi</th>
                        <th>Peralatan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <?php foreach ($data as $i => $l): ?>
                        <tr data-id="<?= $l['id'] ?>" data-name="<?= htmlspecialchars($l['nama_lokasi']) ?>">
                            <td>
                                <?= $i + 1 ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($l['nama_lokasi']) ?>
                            </td>
                            <td><span class="badge">
                                    <?= $l['equipment_count'] ?>
                                </span></td>
                            <td class="actions">
                                <button onclick="edit(<?= $l['id'] ?>)" class="action-btn btn-edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="hapus(<?= $l['id'] ?>)" class="action-btn btn-delete" title="Hapus">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- MODAL -->
        <div class="modal" id="modal">
            <div class="modal-box">
                <h3 id="modalTitle">Tambah Lokasi</h3>
                <input type="text" id="nama" placeholder="Nama Lokasi"
                    style="margin-bottom:20px; width:100%; padding:12px; border-radius:8px; border:1px solid #cbd5e1">

                <div style="display:flex; justify-content: flex-end; gap:8px;">
                    <button class="btn-add" onclick="simpan()">Simpan</button>
                    <button onclick="closeModal()" style="background:#e2e8f0; color:#475569">Batal</button>
                </div>
            </div>
        </div>

        <script>
            let editId = null;

            function openModal() {
                editId = null;
                document.getElementById('nama').value = '';
                document.getElementById('modalTitle').innerText = 'Tambah Lokasi';
                document.getElementById('modal').classList.add('active');
            }
            function closeModal() { document.getElementById('modal').classList.remove('active'); }

            function edit(id) {
                const r = document.querySelector(`tr[data-id="${id}"]`);
                editId = id;
                document.getElementById('nama').value = r.dataset.name;
                document.getElementById('modalTitle').innerText = 'Edit Lokasi';
                document.getElementById('modal').classList.add('active');
            }

            function simpan() {
                const namaInput = document.getElementById('nama');
                const namaVal = namaInput.value.trim();

                if (!namaVal) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Peringatan',
                        text: 'Nama lokasi tidak boleh kosong'
                    });
                    return;
                }

                const fd = new FormData();
                fd.append('ajax', '1');
                fd.append('nama_lokasi', namaVal);

                if (editId) {
                    fd.append('action', 'edit');
                    fd.append('id', editId);
                } else {
                    fd.append('action', 'add');
                }

                console.log('Sending data...', Object.fromEntries(fd));

                fetch('', {
                    method: 'POST',
                    body: fd
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error('Server response:', text);
                                throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                            }
                        });
                    })
                    .then(d => {
                        if (d.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil',
                                text: d.message,
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: d.message
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error Sistem',
                            text: error.message
                        });
                    });
            }

            function hapus(id) {
                Swal.fire({
                    title: 'Hapus lokasi?',
                    text: "Data yang dihapus tidak dapat dikembalikan!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Ya, hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const fd = new FormData();
                        fd.append('ajax', '1');
                        fd.append('action', 'delete');
                        fd.append('id', id);
                        fetch('', { method: 'POST', body: fd })
                            .then(r => r.json()).then(d => {
                                if (d.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Terhapus!',
                                        text: d.message,
                                        showConfirmButton: false,
                                        timer: 1500
                                    }).then(() => location.reload());
                                } else {
                                    Swal.fire('Gagal', d.message, 'error');
                                }
                            });
                    }
                })
            }

            function search() {
                const q = document.getElementById('keyword').value.toLowerCase();
                const rows = document.querySelectorAll('#tbody tr');

                rows.forEach(r => {
                    const name = r.dataset.name.toLowerCase();
                    if (name.includes(q)) {
                        r.style.display = '';
                    } else {
                        r.style.display = 'none';
                    }
                });
            }
        </script>
    </main>
</body>

</html>