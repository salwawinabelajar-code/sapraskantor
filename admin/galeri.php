<?php
require_once '../config/database.php';
$db = new Database();

// Cek login admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$error = '';
$success = '';

// FUNGSI UPLOAD FOTO
function uploadFoto($file, $prefix) {
    if ($file['error'] != 0) {
        return ['error' => 'File ' . $prefix . ' gagal diupload'];
    }
    
    $target_dir = "../assets/uploads/galeri/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array($extension, $allowed)) {
        return ['error' => 'Format file harus JPG/PNG/GIF'];
    }
    
    if ($file['size'] > 5242880) {
        return ['error' => 'File maksimal 5MB'];
    }
    
    $filename = time() . '_' . $prefix . '_' . uniqid() . '.' . $extension;
    $target_file = $target_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return ['success' => $filename];
    } else {
        return ['error' => 'Gagal upload file'];
    }
}

// TAMBAH DATA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
    $judul = $db->escape_string($_POST['judul']);
    $deskripsi = $db->escape_string($_POST['deskripsi']);
    
    if (empty($judul)) {
        $_SESSION['error'] = "Judul tidak boleh kosong!";
        header('Location: galeri.php');
        exit;
    }
    
    // Upload foto before
    $uploadBefore = uploadFoto($_FILES['foto_before'], 'before');
    if (isset($uploadBefore['error'])) {
        $_SESSION['error'] = $uploadBefore['error'];
        header('Location: galeri.php');
        exit;
    }
    
    // Upload foto after
    $uploadAfter = uploadFoto($_FILES['foto_after'], 'after');
    if (isset($uploadAfter['error'])) {
        $_SESSION['error'] = $uploadAfter['error'];
        header('Location: galeri.php');
        exit;
    }
    
    $foto_before = $uploadBefore['success'];
    $foto_after = $uploadAfter['success'];
    
    $query = "INSERT INTO galeri (user_id, judul, foto_before, foto_after, deskripsi) 
              VALUES ({$_SESSION['user_id']}, '$judul', '$foto_before', '$foto_after', '$deskripsi')";
    
    if ($db->conn->query($query)) {
        $_SESSION['success'] = "Foto berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal: " . $db->conn->error;
    }
    header('Location: galeri.php');
    exit;
}

// EDIT DATA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
    $id = (int)$_POST['id'];
    $judul = $db->escape_string($_POST['judul']);
    $deskripsi = $db->escape_string($_POST['deskripsi']);
    $foto_before_lama = $_POST['foto_before_lama'];
    $foto_after_lama = $_POST['foto_after_lama'];
    $foto_before_baru = $foto_before_lama;
    $foto_after_baru = $foto_after_lama;
    
    // Upload foto before baru jika ada
    if (isset($_FILES['foto_before']) && $_FILES['foto_before']['error'] == 0) {
        $uploadBefore = uploadFoto($_FILES['foto_before'], 'before');
        if (isset($uploadBefore['error'])) {
            $_SESSION['error'] = $uploadBefore['error'];
            header('Location: galeri.php');
            exit;
        }
        $foto_before_baru = $uploadBefore['success'];
        // Hapus foto lama
        if (file_exists("../assets/uploads/galeri/$foto_before_lama")) {
            unlink("../assets/uploads/galeri/$foto_before_lama");
        }
    }
    
    // Upload foto after baru jika ada
    if (isset($_FILES['foto_after']) && $_FILES['foto_after']['error'] == 0) {
        $uploadAfter = uploadFoto($_FILES['foto_after'], 'after');
        if (isset($uploadAfter['error'])) {
            $_SESSION['error'] = $uploadAfter['error'];
            header('Location: galeri.php');
            exit;
        }
        $foto_after_baru = $uploadAfter['success'];
        // Hapus foto lama
        if (file_exists("../assets/uploads/galeri/$foto_after_lama")) {
            unlink("../assets/uploads/galeri/$foto_after_lama");
        }
    }
    
    $query = "UPDATE galeri SET 
              judul = '$judul',
              foto_before = '$foto_before_baru',
              foto_after = '$foto_after_baru',
              deskripsi = '$deskripsi'
              WHERE id = $id";
    
    if ($db->conn->query($query)) {
        $_SESSION['success'] = "Foto berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal: " . $db->conn->error;
    }
    header('Location: galeri.php');
    exit;
}

// HAPUS DATA GALERI
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus'])) {
    $id = (int)$_POST['id'];
    
    // Ambil nama file untuk dihapus
    $result = $db->query("SELECT foto_before, foto_after FROM galeri WHERE id = $id");
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        if (file_exists("../assets/uploads/galeri/" . $data['foto_before'])) {
            unlink("../assets/uploads/galeri/" . $data['foto_before']);
        }
        if (file_exists("../assets/uploads/galeri/" . $data['foto_after'])) {
            unlink("../assets/uploads/galeri/" . $data['foto_after']);
        }
    }
    
    // Hapus juga rating dan komentar terkait
    $db->conn->query("DELETE FROM rating_galeri WHERE galeri_id = $id");
    $db->conn->query("DELETE FROM komentar_galeri WHERE galeri_id = $id");
    $db->conn->query("DELETE FROM galeri WHERE id = $id");
    
    $_SESSION['success'] = "Foto berhasil dihapus!";
    header('Location: galeri.php');
    exit;
}

// HAPUS KOMENTAR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_komentar'])) {
    $komentar_id = (int)$_POST['komentar_id'];
    
    if ($db->conn->query("DELETE FROM komentar_galeri WHERE id = $komentar_id")) {
        $_SESSION['success'] = "Komentar berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus komentar: " . $db->conn->error;
    }
    header('Location: galeri.php');
    exit;
}

// AMBIL DATA GALERI
$query = "SELECT g.*, u.nama as uploader_nama,
          COUNT(DISTINCT r.id) as total_rating,
          COUNT(DISTINCT k.id) as total_komentar,
          COALESCE(AVG(r.rating), 0) as avg_rating
          FROM galeri g
          LEFT JOIN users u ON g.user_id = u.id
          LEFT JOIN rating_galeri r ON g.id = r.galeri_id
          LEFT JOIN komentar_galeri k ON g.id = k.galeri_id
          GROUP BY g.id
          ORDER BY g.created_at DESC";
$result = $db->query($query);
$galeri = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// AMBIL KOMENTAR PER GALERI
$komentar_per_galeri = [];
foreach ($galeri as $g) {
    $gid = $g['id'];
    $q = "SELECT k.*, u.nama, u.email, u.role 
          FROM komentar_galeri k 
          JOIN users u ON k.user_id = u.id 
          WHERE k.galeri_id = $gid 
          ORDER BY k.created_at DESC";
    $res = $db->query($q);
    $komentar_per_galeri[$gid] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

// AMBIL RATING PER GALERI (untuk admin melihat daftar user yang memberi rating)
$rating_per_galeri = [];
foreach ($galeri as $g) {
    $gid = $g['id'];
    $q = "SELECT r.*, u.nama, u.email 
          FROM rating_galeri r 
          JOIN users u ON r.user_id = u.id 
          WHERE r.galeri_id = $gid 
          ORDER BY r.created_at DESC";
    $res = $db->query($q);
    $rating_per_galeri[$gid] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

// AMBIL SESSION
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Galeri - AssetCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
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
        
        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            color: white;
            position: fixed;
            width: 250px;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
        }
        .sidebar .nav-link i {
            width: 25px;
        }
        
        .profile-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .navbar-top {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 20px;
            margin: -20px -20px 20px;
            border-radius: 0 0 15px 15px;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(9,99,126,0.3);
            color: white;
        }
        
        /* Galeri Grid */
        .galeri-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }
        
        .galeri-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .galeri-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(9,99,126,0.15);
        }
        
        .foto-container {
            display: flex;
            height: 200px;
            position: relative;
            overflow: hidden;
        }
        
        .foto-container img {
            width: 50%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .foto-container:hover img {
            transform: scale(1.1);
        }
        
        .foto-label {
            position: absolute;
            bottom: 10px;
            left: 20%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            z-index: 2;
        }
        
        .foto-label.after {
            left: 70%;
        }
        
        .card-content {
            padding: 20px;
        }
        
        .card-header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .card-title {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.1rem;
            margin: 0;
        }
        
        .card-meta {
            display: flex;
            gap: 15px;
            color: #6c757d;
            font-size: 0.85rem;
            margin-bottom: 15px;
        }
        
        .card-meta i {
            color: var(--accent);
            width: 18px;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 0.9rem;
        }
        
        .stats-badge {
            background: var(--light);
            padding: 8px 12px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-around;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #666;
        }
        
        /* Tombol Aksi */
        .btn-aksi {
            font-size: 0.85rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        /* Modal Komentar & Rating */
        .modal-komentar .modal-header,
        .modal-rating .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        .modal-komentar .modal-header .btn-close,
        .modal-rating .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        .komentar-item, .rating-item {
            border-bottom: 1px solid #eee;
            padding: 12px 0;
        }
        .komentar-item:last-child, .rating-item:last-child {
            border-bottom: none;
        }
        .komentar-header, .rating-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.85rem;
        }
        .komentar-user, .rating-user {
            font-weight: 600;
            color: var(--primary);
        }
        .badge-admin {
            background: var(--primary);
            color: white;
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
        }
        .komentar-date, .rating-date {
            color: #999;
            font-size: 0.75rem;
        }
        .komentar-text, .rating-value {
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        .rating-value i {
            color: #ffc107;
            letter-spacing: 2px;
        }
        .btn-hapus {
            color: #dc3545;
            font-size: 0.75rem;
            text-decoration: none;
            background: none;
            border: none;
        }
        
        /* Modal lainnya */
        .modal-content {
            border-radius: 20px;
            overflow: hidden;
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        .modal-img-preview {
            display: flex;
            gap: 15px;
        }
        .modal-img-preview img {
            width: 50%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 10px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        /* Responsive */
        @media (max-width: 991.98px) {
            .sidebar {
                width: 70px;
            }
            .sidebar .nav-link span {
                display: none;
            }
            .main-content {
                margin-left: 70px;
            }
        }
        @media (max-width: 768px) {
            .galeri-grid {
                grid-template-columns: 1fr;
            }
            .modal-img-preview {
                flex-direction: column;
            }
            .modal-img-preview img {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- Top Navbar -->
        <div class="navbar-top d-flex justify-content-between align-items-center">
            <h4 class="mb-0 text-primary"><i class="fas fa-images me-2"></i>Manajemen Galeri</h4>
            <div class="d-flex align-items-center gap-3">
                <a href="../user/galeri.php" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fas fa-eye me-1"></i> Lihat User View</a>
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                        <div class="profile-avatar me-2"><?php echo isset($_SESSION['nama']) ? substr($_SESSION['nama'], 0, 1) : 'A'; ?></div>
                        <div class="d-none d-md-block">
                            <span class="fw-bold"><?php echo $_SESSION['nama'] ?? 'Admin'; ?></span><br>
                            <small class="text-muted">Admin</small>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profil.php"><i class="fas fa-user me-2"></i>Profil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Notifikasi -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeInDown">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeInDown">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Header Card -->
        <div class="card p-4 mb-4" style="background: white; border-radius: 15px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Galeri Sarana Prasarana</h5>
                    <p class="text-muted mb-0">Kelola foto-foto sebelum dan sesudah perbaikan</p>
                </div>
                <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#tambahModal">
                    <i class="fas fa-plus me-2"></i>Tambah Foto Baru
                </button>
            </div>
        </div>

        <!-- Grid Galeri -->
        <?php if (empty($galeri)): ?>
            <div class="empty-state animate__animated animate__fadeIn">
                <i class="fas fa-camera"></i>
                <h5 class="text-muted">Belum ada foto</h5>
                <p class="mb-3">Klik tombol "Tambah Foto Baru" untuk mulai mengunggah</p>
                <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#tambahModal">Tambah Foto Pertama</button>
            </div>
        <?php else: ?>
            <div class="galeri-grid">
                <?php foreach ($galeri as $g): ?>
                    <div class="galeri-card animate__animated animate__fadeInUp">
                        <div class="foto-container">
                            <img src="../assets/uploads/galeri/<?php echo $g['foto_before']; ?>" onerror="this.src='../assets/images/no-image.jpg'">
                            <img src="../assets/uploads/galeri/<?php echo $g['foto_after']; ?>" onerror="this.src='../assets/images/no-image.jpg'">
                            <span class="foto-label">Sebelum</span>
                            <span class="foto-label after">Sesudah</span>
                        </div>
                        <div class="card-content">
                            <div class="card-header-info">
                                <h6 class="card-title" title="<?php echo htmlspecialchars($g['judul']); ?>"><?php echo htmlspecialchars(substr($g['judul'],0,40)) . (strlen($g['judul'])>40?'...':''); ?></h6>
                                <span class="rating-stars">
                                    <?php $avg = round($g['avg_rating']??0); for($i=1;$i<=5;$i++): echo $i<=$avg?'★':'☆'; endfor; ?>
                                </span>
                            </div>
                            <div class="card-meta">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($g['uploader_nama']??'Admin'); ?></span>
                                <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($g['created_at'])); ?></span>
                            </div>
                            <div class="stats-badge">
                                <div class="stat-item"><div class="stat-value"><?php echo $g['total_rating']; ?></div><div class="stat-label">Rating</div></div>
                                <div class="stat-item"><div class="stat-value"><?php echo $g['total_komentar']; ?></div><div class="stat-label">Komentar</div></div>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-primary btn-sm flex-fill btn-aksi" data-bs-toggle="modal" data-bs-target="#detailModal<?php echo $g['id']; ?>"><i class="fas fa-eye"></i> Detail</button>
                                <button class="btn btn-outline-warning btn-sm flex-fill btn-aksi" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $g['id']; ?>"><i class="fas fa-edit"></i> Edit</button>
                                <button class="btn btn-outline-danger btn-sm flex-fill btn-aksi" data-bs-toggle="modal" data-bs-target="#hapusModal<?php echo $g['id']; ?>"><i class="fas fa-trash"></i> Hapus</button>
                            </div>
                            
                            <!-- Tombol Lihat Rating (baru) -->
                            <button class="btn btn-outline-info btn-sm w-100 mt-2 btn-aksi" data-bs-toggle="modal" data-bs-target="#ratingModal<?php echo $g['id']; ?>">
                                <i class="fas fa-star me-1"></i> Lihat Rating (<?php echo $g['total_rating']; ?>)
                            </button>
                            
                            <!-- Tombol Lihat Komentar -->
                            <button class="btn btn-outline-secondary btn-sm w-100 mt-2 btn-aksi" data-bs-toggle="modal" data-bs-target="#komentarModal<?php echo $g['id']; ?>">
                                <i class="fas fa-comments me-1"></i> Lihat Komentar (<?php echo $g['total_komentar']; ?>)
                            </button>
                        </div>
                    </div>

                    <!-- MODAL DETAIL -->
                    <div class="modal fade" id="detailModal<?php echo $g['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Detail Foto</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="modal-img-preview">
                                        <img src="../assets/uploads/galeri/<?php echo $g['foto_before']; ?>">
                                        <img src="../assets/uploads/galeri/<?php echo $g['foto_after']; ?>">
                                    </div>
                                    <h6 class="fw-bold mt-3"><?php echo htmlspecialchars($g['judul']); ?></h6>
                                    <p class="small">Uploader: <?php echo htmlspecialchars($g['uploader_nama']??'Admin'); ?> | <?php echo date('d F Y H:i', strtotime($g['created_at'])); ?></p>
                                    <?php if (!empty($g['deskripsi'])): ?>
                                        <div class="p-3 bg-light rounded"><?php echo nl2br(htmlspecialchars($g['deskripsi'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL EDIT -->
                    <div class="modal fade" id="editModal<?php echo $g['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Foto</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                                    <input type="hidden" name="foto_before_lama" value="<?php echo $g['foto_before']; ?>">
                                    <input type="hidden" name="foto_after_lama" value="<?php echo $g['foto_after']; ?>">
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Judul</label>
                                            <input type="text" class="form-control" name="judul" value="<?php echo htmlspecialchars($g['judul']); ?>" required>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Foto Before (kosongkan jika tidak diganti)</label>
                                                <img src="../assets/uploads/galeri/<?php echo $g['foto_before']; ?>" class="d-block mb-2" style="max-width:100%; max-height:100px; border-radius:8px;">
                                                <input type="file" class="form-control" name="foto_before" accept="image/*">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Foto After (kosongkan jika tidak diganti)</label>
                                                <img src="../assets/uploads/galeri/<?php echo $g['foto_after']; ?>" class="d-block mb-2" style="max-width:100%; max-height:100px; border-radius:8px;">
                                                <input type="file" class="form-control" name="foto_after" accept="image/*">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Deskripsi</label>
                                            <textarea class="form-control" name="deskripsi" rows="3"><?php echo htmlspecialchars($g['deskripsi']); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" name="edit" class="btn btn-primary">Simpan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL HAPUS -->
                    <div class="modal fade" id="hapusModal<?php echo $g['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                                    <div class="modal-body">
                                        <p>Hapus foto <strong><?php echo htmlspecialchars($g['judul']); ?></strong>?</p>
                                        <p class="text-danger small">Semua rating dan komentar terkait ikut terhapus.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" name="hapus" class="btn btn-danger">Ya, Hapus</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL RATING (baru) -->
                    <div class="modal fade modal-rating" id="ratingModal<?php echo $g['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="fas fa-star me-2"></i>Rating dari Pengguna</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                                    <?php if (empty($rating_per_galeri[$g['id']])): ?>
                                        <p class="text-muted text-center">Belum ada rating.</p>
                                    <?php else: ?>
                                        <?php foreach ($rating_per_galeri[$g['id']] as $r): ?>
                                            <div class="rating-item">
                                                <div class="rating-header">
                                                    <span class="rating-user">
                                                        <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($r['nama']); ?>
                                                        <span class="badge-admin"><?php echo $r['email']; ?></span>
                                                    </span>
                                                    <span class="rating-date"><?php echo date('d M H:i', strtotime($r['created_at'])); ?></span>
                                                </div>
                                                <div class="rating-value">
                                                    <?php 
                                                    $stars = $r['rating'];
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        echo $i <= $stars ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                                    }
                                                    echo ' (' . $stars . '⭐)';
                                                    ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL KOMENTAR -->
                    <div class="modal fade modal-komentar" id="komentarModal<?php echo $g['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="fas fa-comments me-2"></i>Komentar</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                                    <?php if (empty($komentar_per_galeri[$g['id']])): ?>
                                        <p class="text-muted text-center">Belum ada komentar.</p>
                                    <?php else: ?>
                                        <?php foreach ($komentar_per_galeri[$g['id']] as $k): ?>
                                            <div class="komentar-item">
                                                <div class="komentar-header">
                                                    <span class="komentar-user">
                                                        <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($k['nama']); ?>
                                                        <?php if ($k['role'] == 'admin'): ?><span class="badge-admin">Admin</span><?php endif; ?>
                                                    </span>
                                                    <span class="komentar-date"><?php echo date('d M H:i', strtotime($k['created_at'])); ?></span>
                                                </div>
                                                <div class="komentar-text"><?php echo nl2br(htmlspecialchars($k['komentar'])); ?></div>
                                                <div class="text-end">
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Hapus komentar ini?')">
                                                        <input type="hidden" name="komentar_id" value="<?php echo $k['id']; ?>">
                                                        <button type="submit" name="hapus_komentar" class="btn-hapus"><i class="fas fa-trash-alt me-1"></i>Hapus</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- MODAL TAMBAH -->
    <div class="modal fade" id="tambahModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Foto Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Judul <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="judul" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Foto Sebelum <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" name="foto_before" accept="image/*" required>
                                <small class="text-muted">Max 5MB (JPG,PNG,GIF)</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Foto Sesudah <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" name="foto_after" accept="image/*" required>
                                <small class="text-muted">Max 5MB (JPG,PNG,GIF)</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah" class="btn btn-primary-custom">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(el => {
                el.style.transition = 'opacity 0.5s';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>