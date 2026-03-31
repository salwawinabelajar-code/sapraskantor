<?php
require_once '../config/database.php';
$db = new Database();

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Ambil daftar kategori dari database
$kategori_result = $db->query("SELECT nama FROM kategori ORDER BY nama");
$kategori_list = [];
if ($kategori_result) {
    while ($row = $kategori_result->fetch_assoc()) {
        $kategori_list[] = trim($row['nama']);
    }
}

// Ambil daftar kategori
$kategori_result = $db->query("SELECT nama FROM kategori ORDER BY nama");
$kategori_list = [];
if ($kategori_result) {
    while ($row = $kategori_result->fetch_assoc()) {
        $kategori_list[] = trim($row['nama']);
    }
}

// Proses pembuatan pengaduan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_pengaduan'])) {
    $tanggal_kejadian = $db->escape_string($_POST['tanggal_kejadian']);
    $judul = $db->escape_string($_POST['judul']);
    $kategori = trim($db->escape_string($_POST['kategori']));
    $prioritas = $db->escape_string($_POST['prioritas']);
    $deskripsi = $db->escape_string($_POST['deskripsi']);
    
    // Validasi kategori
    if (!in_array($kategori, $kategori_list)) {
        $error = "Kategori tidak valid!";
    } else {
        // Upload file
        $lampiran = null;
        if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] == 0) {
            $upload_dir = '../assets/uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $filename = time() . '_' . basename($_FILES['lampiran']['name']);
            $target_file = $upload_dir . $filename;
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['lampiran']['type'];
            
            if (in_array($file_type, $allowed_types) && $_FILES['lampiran']['size'] <= 2097152) {
                if (move_uploaded_file($_FILES['lampiran']['tmp_name'], $target_file)) {
                    $lampiran = $filename;
                } else {
                    $error = "Gagal mengupload gambar";
                }
            } else {
                $error = "File harus gambar (JPG/PNG/GIF) dan maksimal 2MB";
            }
        } else {
            $error = "Lampiran foto wajib diisi!";
        }
        
        if (!$error) {
            $query = "INSERT INTO pengaduan (user_id, tanggal_kejadian, judul, kategori, prioritas, lampiran, deskripsi) 
                      VALUES ('$user_id', '$tanggal_kejadian', '$judul', '$kategori', '$prioritas', '$lampiran', '$deskripsi')";
            if ($db->conn->query($query)) {
                $_SESSION['success'] = "Pengaduan berhasil dibuat!";
                // Redirect ke halaman yang sama agar tidak terjadi resubmission
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $error = "Gagal membuat pengaduan: " . $db->conn->error;
            }
        }
    }
}


// Inisialisasi filter
$filter_status = isset($_GET['status']) ? $db->escape_string($_GET['status']) : '';
$filter_kategori = isset($_GET['kategori']) ? $db->escape_string(trim($_GET['kategori'])) : '';
$filter_prioritas = isset($_GET['prioritas']) ? $db->escape_string($_GET['prioritas']) : '';
$search = isset($_GET['search']) ? $db->escape_string($_GET['search']) : '';

// Handle edit pengaduan
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['edit_pengaduan'])) {
        $id = $db->escape_string($_POST['edit_id']);
        $tanggal_kejadian = $db->escape_string($_POST['edit_tanggal_kejadian']);
        $judul = $db->escape_string($_POST['edit_judul']);
        $kategori = $db->escape_string(trim($_POST['edit_kategori']));
        $prioritas = $db->escape_string($_POST['edit_prioritas']);
        $deskripsi = $db->escape_string($_POST['edit_deskripsi']);
        
        // Cek apakah pengaduan masih menunggu
        $check = $db->query("SELECT status, lampiran FROM pengaduan WHERE id='$id' AND user_id='$user_id'");
        if ($check->num_rows > 0) {
            $pengaduan = $check->fetch_assoc();
            if ($pengaduan['status'] == 'Menunggu') {
                
                // Handle file upload untuk edit (opsional)
                $lampiran = $pengaduan['lampiran'];
                if (isset($_FILES['edit_lampiran']) && $_FILES['edit_lampiran']['error'] == 0) {
                    $upload_dir = '../assets/uploads/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    if ($lampiran && file_exists($upload_dir . $lampiran)) {
                        unlink($upload_dir . $lampiran);
                    }
                    
                    $filename = time() . '_' . basename($_FILES['edit_lampiran']['name']);
                    $target_file = $upload_dir . $filename;
                    
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $file_type = $_FILES['edit_lampiran']['type'];
                    
                    if (in_array($file_type, $allowed_types) && $_FILES['edit_lampiran']['size'] <= 2097152) {
                        if (move_uploaded_file($_FILES['edit_lampiran']['tmp_name'], $target_file)) {
                            $lampiran = $filename;
                        } else {
                            $error = "Gagal mengupload gambar";
                        }
                    } else {
                        $error = "File harus gambar (JPG/PNG/GIF) dan maksimal 2MB";
                    }
                }
                
                if (!$error) {
                    $query = "UPDATE pengaduan SET 
                              tanggal_kejadian='$tanggal_kejadian',
                              judul='$judul',
                              kategori='$kategori',
                              prioritas='$prioritas',
                              lampiran='$lampiran',
                              deskripsi='$deskripsi'
                              WHERE id='$id' AND user_id='$user_id'";
                    
                    if ($db->conn->query($query)) {
                        $_SESSION['success'] = "Pengaduan berhasil diupdate!";
                        redirect('riwayat.php' . buildFilterQueryString());
                    } else {
                        $error = "Gagal mengupdate pengaduan: " . $db->conn->error;
                    }
                }
            } else {
                $error = "Pengaduan tidak bisa diedit karena status sudah " . $pengaduan['status'];
            }
        }
    }
    
    // Handle hapus pengaduan
    if (isset($_POST['delete_id'])) {
        $id = $db->escape_string($_POST['delete_id']);
        
        $check = $db->query("SELECT status, lampiran FROM pengaduan WHERE id='$id' AND user_id='$user_id'");
        if ($check->num_rows > 0) {
            $pengaduan = $check->fetch_assoc();
            if ($pengaduan['status'] == 'Menunggu') {
                if ($pengaduan['lampiran'] && file_exists('../assets/uploads/' . $pengaduan['lampiran'])) {
                    unlink('../assets/uploads/' . $pengaduan['lampiran']);
                }
                
                $query = "DELETE FROM pengaduan WHERE id='$id' AND user_id='$user_id'";
                if ($db->conn->query($query)) {
                    $_SESSION['success'] = "Pengaduan berhasil dihapus!";
                    redirect('riwayat.php' . buildFilterQueryString());
                } else {
                    $error = "Gagal menghapus pengaduan: " . $db->conn->error;
                }
            } else {
                $error = "Pengaduan tidak bisa dihapus karena status sudah " . $pengaduan['status'];
            }
        }
    }
}

// Handle hapus via GET
if (isset($_GET['delete_id'])) {
    $id = $db->escape_string($_GET['delete_id']);
    
    $check = $db->query("SELECT status, lampiran FROM pengaduan WHERE id='$id' AND user_id='$user_id'");
    if ($check->num_rows > 0) {
        $pengaduan = $check->fetch_assoc();
        if ($pengaduan['status'] == 'Menunggu') {
            if ($pengaduan['lampiran'] && file_exists('../assets/uploads/' . $pengaduan['lampiran'])) {
                unlink('../assets/uploads/' . $pengaduan['lampiran']);
            }
            
            $query = "DELETE FROM pengaduan WHERE id='$id' AND user_id='$user_id'";
            if ($db->conn->query($query)) {
                $_SESSION['success'] = "Pengaduan berhasil dihapus!";
                redirect('riwayat.php' . buildFilterQueryString());
            }
        }
    }
}

// Export to Excel
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $pengaduan_data = getFilteredPengaduan($db, $user_id, $filter_status, $filter_kategori, $filter_prioritas, $search);
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="riwayat_pengaduan_' . date('Y-m-d_H-i-s') . '.xls"');
    
    echo '<html><head><meta charset="UTF-8"><style>table{border-collapse:collapse;width:100%;}th{background-color:#09637E;color:white;font-weight:bold;padding:8px;border:1px solid #ddd;}td{padding:8px;border:1px solid #ddd;}</style></head><body>';
    echo '<h2>Riwayat Pengaduan</h2>';
    echo '<p>Tanggal Export: ' . date('d F Y H:i:s') . '</p>';
    echo '<p>User: ' . $_SESSION['nama'] . '</p>';
    echo '<table border="1">';
    echo '<tr><th>No</th><th>Tanggal Kejadian</th><th>Judul</th><th>Kategori</th><th>Prioritas</th><th>Status</th><th>Deskripsi</th></tr>';
    
    $no = 1;
    foreach ($pengaduan_data as $item) {
        $kategori_tampil = trim($item['kategori'] ?? '');
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . date('d/m/Y', strtotime($item['tanggal_kejadian'])) . '</td>';
        echo '<td>' . htmlspecialchars($item['judul']) . '</td>';
        echo '<td>' . ($kategori_tampil !== '' ? htmlspecialchars($kategori_tampil) : '-') . '</td>';
        echo '<td>' . $item['prioritas'] . '</td>';
        echo '<td>' . $item['status'] . '</td>';
        echo '<td>' . htmlspecialchars($item['deskripsi']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table></body></html>';
    exit();
}

// Fungsi untuk query string filter
function buildFilterQueryString() {
    $params = [];
    if (!empty($_GET['status'])) $params[] = 'status=' . urlencode($_GET['status']);
    if (!empty($_GET['kategori'])) $params[] = 'kategori=' . urlencode($_GET['kategori']);
    if (!empty($_GET['prioritas'])) $params[] = 'prioritas=' . urlencode($_GET['prioritas']);
    if (!empty($_GET['search'])) $params[] = 'search=' . urlencode($_GET['search']);
    return $params ? '?' . implode('&', $params) : '';
}

// Fungsi untuk mendapatkan data dengan filter
function getFilteredPengaduan($db, $user_id, $status, $kategori, $prioritas, $search) {
    $query = "SELECT * FROM pengaduan WHERE user_id = '$user_id'";
    
    if ($status) $query .= " AND status = '$status'";
    if ($kategori) {
        $kategori_clean = trim($kategori);
        $query .= " AND LOWER(TRIM(kategori)) = LOWER('$kategori_clean')";
    }
    if ($prioritas) $query .= " AND prioritas = '$prioritas'";
    if ($search) {
        $query .= " AND (judul LIKE '%$search%' OR deskripsi LIKE '%$search%')";
    }
    
    $query .= " ORDER BY created_at DESC";
    $result = $db->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get data pengaduan
$pengaduan_data = getFilteredPengaduan($db, $user_id, $filter_status, $filter_kategori, $filter_prioritas, $search);
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pengaduan - AssetCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #09637E;
            --secondary: #088395;
            --accent: #7AB2B2;
            --light: #EBF4F6;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%) !important;
            padding: 15px 0;
        }
        
        .navbar-custom .navbar-brand {
            color: white !important;
            font-weight: 700;
            font-size: 1.8rem;
        }
        
        .navbar-custom .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            padding: 8px 15px !important;
            border-radius: 8px;
            margin: 0 2px;
        }
        
        .navbar-custom .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white !important;
        }
        
        .profile-avatar {
            width: 40px;
            height: 40px;
            background: white;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .page-title {
            color: var(--primary);
            font-weight: 700;
            margin: 30px 0 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--accent);
        }
        
        .btn-primary-custom {
            background: var(--primary);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .btn-primary-custom:hover {
            background: var(--secondary);
            color: white;
        }
        
        .btn-export {
            background: #28a745;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .table-container {
            display: block;
        }
        
        .card-container {
            display: none;
        }
        
        .pengaduan-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary);
        }
        
        .pengaduan-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .card-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }
        
        .card-actions .btn {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        /* Tabel Responsif */
        .table-responsive-custom {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            background: white;
        }
        
        .table-responsive-custom table {
            min-width: 800px;
            margin-bottom: 0;
            width: 100%;
        }
        
        .table-responsive-custom th {
            background: var(--light);
            border-bottom: 2px solid var(--accent);
            font-weight: 700;
            color: var(--primary);
            padding: 16px 12px;
            white-space: nowrap;
        }
        
        .table-responsive-custom td {
            padding: 16px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #eee;
        }
        
        /* Kolom khusus untuk responsif */
        .table-responsive-custom th:nth-child(1), /* # */
        .table-responsive-custom td:nth-child(1) {
            width: 50px;
            min-width: 50px;
            text-align: center;
        }
        
        .table-responsive-custom th:nth-child(2), /* Tanggal */
        .table-responsive-custom td:nth-child(2) {
            width: 120px;
            min-width: 120px;
        }
        
        .table-responsive-custom th:nth-child(3), /* Judul */
        .table-responsive-custom td:nth-child(3) {
            width: 300px;
            min-width: 300px;
        }
        
        .table-responsive-custom th:nth-child(4), /* Kategori */
        .table-responsive-custom td:nth-child(4) {
            width: 150px;
            min-width: 150px;
        }
        
        .table-responsive-custom th:nth-child(5), /* Prioritas */
        .table-responsive-custom td:nth-child(5) {
            width: 120px;
            min-width: 120px;
        }
        
        .table-responsive-custom th:nth-child(6), /* Status */
        .table-responsive-custom td:nth-child(6) {
            width: 140px;
            min-width: 140px;
        }
        
        .table-responsive-custom th:nth-child(7), /* Aksi */
        .table-responsive-custom td:nth-child(7) {
            width: 200px;
            min-width: 200px;
            text-align: center;
        }
        
        /* Judul dan Deskripsi lebih kompak */
        .judul-deskripsi {
            max-width: 300px;
        }
        
        .judul-deskripsi h6 {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 4px;
            line-height: 1.3;
            color: #333;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .judul-deskripsi small {
            font-size: 0.85rem;
            color: #666;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Status badge */
        .status-badge {
            padding: 6px 15px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-block;
            min-width: 100px;
            text-align: center;
        }
        
        .priority-badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }
        
        .bg-menunggu { background-color: #ffc107; color: #212529; }
        .bg-diproses { background-color: #0dcaf0; color: white; }
        .bg-selesai { background-color: #198754; color: white; }
        .bg-ditolak { background-color: #dc3545; color: white; }
        .bg-rendah { background-color: #198754; color: white; }
        .bg-sedang { background-color: #ffc107; color: #212529; }
        .bg-tinggi { background-color: #dc3545; color: white; }
        
        .action-buttons .btn {
            padding: 6px 12px;
            border-radius: 8px;
            margin: 2px;
            transition: all 0.3s;
            font-size: 0.85rem;
        }
        
        .action-buttons .btn:hover {
            transform: scale(1.1);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 0.25rem rgba(122, 178, 178, 0.25);
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        /* Scrollbar untuk tabel */
        .table-responsive-custom::-webkit-scrollbar {
            height: 8px;
        }
        
        .table-responsive-custom::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .table-responsive-custom::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 10px;
        }
        
        .table-responsive-custom::-webkit-scrollbar-thumb:hover {
            background: var(--secondary);
        }
        
        /* File Upload Preview */
        .file-preview {
            max-width: 150px;
            max-height: 150px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #eee;
        }
        
        /* Badge catatan admin */
        .badge-catatan {
            background: #17a2b8;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: 5px;
            cursor: help;
        }
        
        /* Responsif */
        @media (max-width: 768px) {
            .table-container {
                display: none;
            }
            
            .card-container {
                display: block;
            }
            
            .navbar-custom .nav-link {
                margin: 5px 0;
                text-align: center;
            }
            
            .page-title {
                font-size: 1.5rem;
                text-align: center;
            }
            
            .filter-card {
                padding: 15px;
            }
            
            .judul-deskripsi h6 {
                font-size: 0.9rem;
            }
            
            .judul-deskripsi small {
                font-size: 0.8rem;
            }
        }
        
        @media (min-width: 769px) {
            .table-responsive-custom {
                border-radius: 10px;
                overflow: hidden;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Konten utama harus dibungkus container -->
    <div class="container mt-4">


    <!-- Modal Buat Pengaduan -->
<div class="modal fade" id="buatPengaduanModal" tabindex="-1" aria-labelledby="buatPengaduanModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg, #09637E, #088395); color: white;">
        <h5 class="modal-title" id="buatPengaduanModalLabel">
          <i class="fas fa-plus-circle me-2"></i>Buat Pengaduan Baru
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="row g-3">
            <!-- Tanggal Kejadian -->
            <div class="col-md-6">
              <label for="tanggal_kejadian" class="form-label fw-bold">Tanggal Kejadian <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                <input type="date" class="form-control" id="tanggal_kejadian" name="tanggal_kejadian" required max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
              </div>
            </div>
            <!-- Kategori -->
            <div class="col-md-6">
              <label for="kategori" class="form-label fw-bold">Kategori <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-tags"></i></span>
                <select class="form-select" id="kategori" name="kategori" required>
                  <option value="">Pilih Kategori</option>
                  <?php foreach ($kategori_list as $kat): ?>
                    <option value="<?php echo htmlspecialchars($kat); ?>"><?php echo htmlspecialchars($kat); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <!-- Judul -->
            <div class="col-12">
              <label for="judul" class="form-label fw-bold">Judul Pengaduan <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-heading"></i></span>
                <input type="text" class="form-control" id="judul" name="judul" placeholder="Contoh: Komputer tidak bisa menyala" required>
              </div>
            </div>
            <!-- Prioritas -->
            <div class="col-md-6">
              <label class="form-label fw-bold">Prioritas <span class="text-danger">*</span></label>
              <div class="btn-group w-100" role="group" aria-label="Prioritas">
                <input type="radio" class="btn-check" name="prioritas" id="prioritas_rendah" value="Rendah" autocomplete="off" checked>
                <label class="btn btn-outline-success" for="prioritas_rendah"><i class="fas fa-arrow-down me-1"></i>Rendah</label>

                <input type="radio" class="btn-check" name="prioritas" id="prioritas_sedang" value="Sedang" autocomplete="off">
                <label class="btn btn-outline-warning" for="prioritas_sedang"><i class="fas fa-minus me-1"></i>Sedang</label>

                <input type="radio" class="btn-check" name="prioritas" id="prioritas_tinggi" value="Tinggi" autocomplete="off">
                <label class="btn btn-outline-danger" for="prioritas_tinggi"><i class="fas fa-arrow-up me-1"></i>Tinggi</label>
              </div>
            </div>
            <!-- Lampiran Foto -->
            <div class="col-md-6">
              <label for="lampiran" class="form-label fw-bold">Lampiran Foto <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="file" class="form-control" id="lampiran" name="lampiran" accept="image/*" required>
                <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('lampiran').click();"><i class="fas fa-upload"></i></button>
              </div>
              <small class="text-muted">Wajib diisi. Maksimal 2MB (JPG, PNG, GIF)</small>
            </div>
            <!-- Deskripsi -->
            <div class="col-12">
              <label for="deskripsi" class="form-label fw-bold">Deskripsi Kerusakan <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                <textarea class="form-control" id="deskripsi" name="deskripsi" rows="5" placeholder="Jelaskan kerusakan secara detail..." required></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Batal</button>
          <button type="submit" name="create_pengaduan" class="btn btn-primary-custom"><i class="fas fa-save me-1"></i>Simpan Pengaduan</button>
        </div>
      </form>
    </div>
  </div>
</div>
        <!-- Notifikasi -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mt-4">
            <h1 class="page-title">Riwayat Pengaduan</h1>
            <div class="d-flex gap-2">
                <a href="riwayat.php?export=excel<?php echo buildFilterQueryString(); ?>" class="btn btn-export">
                    <i class="fas fa-file-excel me-2"></i>Excel
                </a>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-card">
            <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filter Pengaduan</h5>
            <form method="GET" action="">
                <div class="row g-2">
                    <div class="col-md-3 mb-2">
                        <select class="form-select form-select-sm" name="status">
                            <option value="">Semua Status</option>
                            <option value="Menunggu" <?php echo $filter_status == 'Menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                            <option value="Diproses" <?php echo $filter_status == 'Diproses' ? 'selected' : ''; ?>>Diproses</option>
                            <option value="Selesai" <?php echo $filter_status == 'Selesai' ? 'selected' : ''; ?>>Selesai</option>
                            <option value="Ditolak" <?php echo $filter_status == 'Ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-2">
                        <select class="form-select form-select-sm" name="kategori">
                            <option value="">Semua Kategori</option>
                            <?php 
                            $filter_kategori_trim = trim($filter_kategori);
                            foreach ($kategori_list as $kat): 
                                $kat_trim = trim($kat);
                            ?>
                                <option value="<?php echo htmlspecialchars($kat_trim); ?>" 
                                    <?php echo ($filter_kategori_trim == $kat_trim) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kat_trim); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-2">
                        <select class="form-select form-select-sm" name="prioritas">
                            <option value="">Semua Prioritas</option>
                            <option value="Rendah" <?php echo $filter_prioritas == 'Rendah' ? 'selected' : ''; ?>>Rendah</option>
                            <option value="Sedang" <?php echo $filter_prioritas == 'Sedang' ? 'selected' : ''; ?>>Sedang</option>
                            <option value="Tinggi" <?php echo $filter_prioritas == 'Tinggi' ? 'selected' : ''; ?>>Tinggi</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" name="search" placeholder="Cari..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary-custom btn-sm">
                                <i class="fas fa-filter me-1"></i>Filter
                            </button>
                            <a href="riwayat.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-redo me-1"></i>Reset
                            </a>
                            <?php if ($filter_status || $filter_kategori || $filter_prioritas || $search): ?>
                                <span class="ms-auto text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <?php echo count($pengaduan_data); ?> data ditemukan
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Desktop Table View -->
        <div class="table-container">
            <div class="table-responsive-custom">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center">#</th>
                            <th>Tanggal</th>
                            <th>Judul</th>
                            <th>Kategori</th>
                            <th>Prioritas</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pengaduan_data)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h5 class="text-muted">Tidak ada pengaduan</h5>
                                        <p>
                                            <?php if ($filter_status || $filter_kategori || $filter_prioritas || $search): ?>
                                                Tidak ada data dengan filter yang dipilih
                                            <?php else: ?>
                                                Belum ada riwayat pengaduan
                                            <?php endif; ?>
                                        </p>
                                        <a href="index.php" class="btn btn-primary-custom mt-2">
                                            <i class="fas fa-plus me-2"></i>Buat Pengaduan
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pengaduan_data as $index => $p): 
                                $kategori_tampil = trim($p['kategori'] ?? '');
                                $rowClass = '';
                                if ($p['status'] == 'Menunggu') $rowClass = 'table-warning';
                                elseif ($p['status'] == 'Diproses') $rowClass = 'table-info';
                                elseif ($p['status'] == 'Selesai') $rowClass = 'table-success';
                                elseif ($p['status'] == 'Ditolak') $rowClass = 'table-danger';
                            ?>
                                <tr class="<?php echo $rowClass; ?>">
                                    <td class="text-center fw-bold"><?php echo $index + 1; ?></td>
                                    <td class="fw-semibold"><?php echo date('d/m/Y', strtotime($p['tanggal_kejadian'])); ?></td>
                                    <td>
                                        <div class="judul-deskripsi">
                                            <h6 title="<?php echo htmlspecialchars($p['judul']); ?>"><?php echo htmlspecialchars($p['judul']); ?></h6>
                                            <small title="<?php echo htmlspecialchars($p['deskripsi']); ?>"><?php echo htmlspecialchars(substr($p['deskripsi'], 0, 80)); ?>...</small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($kategori_tampil !== ''): ?>
                                            <span class="badge bg-light text-dark border">
                                                <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($kategori_tampil); ?>
                                            </span>
                                        <?php else: ?>
                                            <em class="text-muted">-</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="priority-badge bg-<?php echo strtolower($p['prioritas']); ?>">
                                            <i class="fas fa-exclamation-circle me-1"></i><?php echo $p['prioritas']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge bg-<?php echo strtolower($p['status']); ?>">
                                            <i class="fas fa-circle me-1" style="font-size: 0.7rem;"></i><?php echo $p['status']; ?>
                                        </span>
                                        <?php if (!empty($p['catatan_admin'])): ?>
                                            <span class="badge-catatan" title="Ada catatan dari admin">
                                                <i class="fas fa-comment"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center action-buttons">
                                        <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailModal<?php echo $p['id']; ?>" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($p['status'] == 'Menunggu'): ?>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $p['id']; ?>" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $p['id']; ?>)" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-warning" disabled title="Tidak dapat diedit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" disabled title="Tidak dapat dihapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile Card View -->
        <div class="card-container">
            <?php if (empty($pengaduan_data)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h5 class="text-muted">Tidak ada pengaduan</h5>
                    <p>
                        <?php if ($filter_status || $filter_kategori || $filter_prioritas || $search): ?>
                            Tidak ada data dengan filter yang dipilih
                        <?php else: ?>
                            Belum ada riwayat pengaduan
                        <?php endif; ?>
                    </p>
                    <a href="index.php" class="btn btn-primary-custom mt-2">
                        <i class="fas fa-plus me-2"></i>Buat Pengaduan
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($pengaduan_data as $index => $p): 
                    $kategori_tampil = trim($p['kategori'] ?? '');
                ?>
                    <div class="pengaduan-card">
                        <div class="pengaduan-card-header">
                            <div>
                                <strong>#<?php echo $index + 1; ?></strong>
                                <span class="ms-2"><?php echo date('d/m/Y', strtotime($p['tanggal_kejadian'])); ?></span>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <?php if (!empty($p['catatan_admin'])): ?>
                                    <span class="badge-catatan" title="Ada catatan dari admin">
                                        <i class="fas fa-comment"></i>
                                    </span>
                                <?php endif; ?>
                                <span class="status-badge bg-<?php echo strtolower($p['status']); ?>">
                                    <?php echo $p['status']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <h6 class="mb-2"><?php echo htmlspecialchars($p['judul']); ?></h6>
                        <p class="text-muted small mb-3"><?php echo substr($p['deskripsi'], 0, 80); ?>...</p>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-light text-dark border me-2">
                                    <?php if ($kategori_tampil !== ''): ?>
                                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($kategori_tampil); ?>
                                    <?php else: ?>
                                        <em class="text-muted">-</em>
                                    <?php endif; ?>
                                </span>
                                <span class="priority-badge bg-<?php echo strtolower($p['prioritas']); ?>">
                                    <i class="fas fa-exclamation-circle me-1"></i><?php echo $p['prioritas']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="card-actions">
                            <button class="btn btn-sm btn-info flex-fill" data-bs-toggle="modal" data-bs-target="#detailModal<?php echo $p['id']; ?>">
                                <i class="fas fa-eye me-1"></i>Detail
                            </button>
                            <?php if ($p['status'] == 'Menunggu'): ?>
                                <button class="btn btn-sm btn-warning flex-fill" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $p['id']; ?>">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </button>
                                <button class="btn btn-sm btn-danger flex-fill" onclick="confirmDelete(<?php echo $p['id']; ?>)">
                                    <i class="fas fa-trash me-1"></i>Hapus
                                </button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-warning flex-fill" disabled>
                                    <i class="fas fa-edit me-1"></i>Edit
                                </button>
                                <button class="btn btn-sm btn-danger flex-fill" disabled>
                                    <i class="fas fa-trash me-1"></i>Hapus
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detail Modals -->
    <?php foreach ($pengaduan_data as $p): 
        $kategori_tampil = trim($p['kategori'] ?? '');
    ?>
        <div class="modal fade" id="detailModal<?php echo $p['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header" style="background: var(--primary); color: white;">
                        <h5 class="modal-title">Detail Pengaduan</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <h6 class="text-primary mb-2">Informasi Umum</h6>
                                <table class="table table-sm table-borderless">
                                    <tr><td width="40%"><strong>Judul</strong></td><td>: <?php echo htmlspecialchars($p['judul']); ?></td></tr>
                                    <tr><td><strong>Tanggal Kejadian</strong></td><td>: <?php echo date('d F Y', strtotime($p['tanggal_kejadian'])); ?></td></tr>
                                    <tr><td><strong>Kategori</strong></td><td>: 
                                        <?php if ($kategori_tampil !== ''): ?>
                                            <?php echo htmlspecialchars($kategori_tampil); ?>
                                        <?php else: ?>
                                            <em class="text-muted">-</em>
                                        <?php endif; ?>
                                    </td></tr>
                                    <tr><td><strong>Prioritas</strong></td><td>: 
                                        <span class="badge bg-<?php echo strtolower($p['prioritas']); ?>">
                                            <?php echo $p['prioritas']; ?>
                                        </span>
                                    </td></tr>
                                    <tr><td><strong>Status</strong></td><td>: 
                                        <span class="badge bg-<?php echo strtolower($p['status']); ?>">
                                            <?php echo $p['status']; ?>
                                        </span>
                                    </td></tr>
                                    <tr><td><strong>Dibuat</strong></td><td>: <?php echo date('d/m/Y H:i', strtotime($p['created_at'])); ?></td></tr>
                                </table>
                            </div>
                            
                            <?php if ($p['lampiran']): ?>
                            <div class="col-12 mb-3">
                                <h6 class="text-primary mb-2">Lampiran Foto</h6>
                                <div class="text-center">
                                    <img src="../assets/uploads/<?php echo $p['lampiran']; ?>" 
                                         alt="Lampiran" 
                                         class="img-fluid rounded shadow-sm file-preview"
                                         style="max-height: 300px;">
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-12 mb-3">
                                <h6 class="text-primary mb-2">Deskripsi Kerusakan</h6>
                                <div class="border rounded p-3 bg-light">
                                    <?php echo nl2br(htmlspecialchars($p['deskripsi'])); ?>
                                </div>
                            </div>
                            
                            <?php if ($p['catatan_admin']): ?>
                            <div class="col-12">
                                <h6 class="text-primary mb-2">Catatan Admin</h6>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <?php echo nl2br(htmlspecialchars($p['catatan_admin'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Edit (hanya untuk status Menunggu) -->
        <?php if ($p['status'] == 'Menunggu'): ?>
        <div class="modal fade" id="editModal<?php echo $p['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit me-2"></i>Edit Pengaduan
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="edit_id" value="<?php echo $p['id']; ?>">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Tanggal Kejadian</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                        <input type="date" class="form-control" name="edit_tanggal_kejadian" 
                                               value="<?php echo $p['tanggal_kejadian']; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Kategori</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-tags"></i></span>
                                        <select class="form-select" name="edit_kategori" required>
                                            <?php 
                                            $kategori_saat_ini = trim($p['kategori'] ?? '');
                                            foreach ($kategori_list as $kat): 
                                                $kat_trim = trim($kat);
                                            ?>
                                                <option value="<?php echo htmlspecialchars($kat_trim); ?>" 
                                                    <?php echo ($kategori_saat_ini == $kat_trim) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($kat_trim); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label fw-bold">Judul Pengaduan</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-heading"></i></span>
                                        <input type="text" class="form-control" name="edit_judul" 
                                               value="<?php echo htmlspecialchars($p['judul']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Prioritas</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-exclamation-triangle"></i></span>
                                        <select class="form-select" name="edit_prioritas" required>
                                            <option value="Rendah" <?php echo $p['prioritas'] == 'Rendah' ? 'selected' : ''; ?>>Rendah</option>
                                            <option value="Sedang" <?php echo $p['prioritas'] == 'Sedang' ? 'selected' : ''; ?>>Sedang</option>
                                            <option value="Tinggi" <?php echo $p['prioritas'] == 'Tinggi' ? 'selected' : ''; ?>>Tinggi</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Status</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                                        <input type="text" class="form-control" value="<?php echo $p['status']; ?>" disabled>
                                    </div>
                                    <small class="text-muted">Status hanya bisa diubah oleh admin</small>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label fw-bold">Lampiran Foto Saat Ini</label>
                                    <?php if ($p['lampiran']): ?>
                                        <div class="mb-3">
                                            <img src="../assets/uploads/<?php echo $p['lampiran']; ?>" 
                                                 alt="Lampiran Saat Ini" 
                                                 class="file-preview">
                                            <p class="text-muted small mt-1">Foto akan diganti jika memilih file baru</p>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">Tidak ada lampiran</p>
                                    <?php endif; ?>
                                    
                                    <label class="form-label fw-bold">Ganti Foto (Opsional)</label>
                                    <div class="input-group">
                                        <input type="file" class="form-control" name="edit_lampiran" accept="image/*" id="editLampiran<?php echo $p['id']; ?>" onchange="previewImage(this, 'previewEdit<?php echo $p['id']; ?>')">
                                        <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('editLampiran<?php echo $p['id']; ?>').click()">
                                            <i class="fas fa-upload"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Maksimal 2MB (JPG, PNG, GIF)</small>
                                    <div id="previewEdit<?php echo $p['id']; ?>" class="mt-2 text-center"></div>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label fw-bold">Deskripsi Kerusakan</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                                        <textarea class="form-control" name="edit_deskripsi" rows="5" required><?php echo htmlspecialchars($p['deskripsi']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Batal
                            </button>
                            <button type="submit" name="edit_pengaduan" class="btn btn-primary-custom">
                                <i class="fas fa-save me-1"></i>Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(id) {
            if (confirm('Apakah Anda yakin ingin menghapus pengaduan ini?')) {
                window.location.href = 'riwayat.php?delete_id=' + id + '<?php echo buildFilterQueryString(); ?>';
            }
        }
        
        function previewImage(input, previewId) {
            var preview = document.getElementById(previewId);
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                var file = input.files[0];
                
                if (file.size > 2097152) {
                    alert('Ukuran file maksimal 2MB');
                    input.value = '';
                    return;
                }
                
                var allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Hanya file JPG, PNG, dan GIF yang diizinkan');
                    input.value = '';
                    return;
                }
                
                var reader = new FileReader();
                reader.onload = function(e) {
                    var img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'file-preview';
                    img.style.maxHeight = '150px';
                    preview.appendChild(img);
                }
                reader.readAsDataURL(file);
            }
        }
    </script>
        </div> <!-- Penutup container utama -->

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="..."></script>
</body>
</html>
</body>
</html>