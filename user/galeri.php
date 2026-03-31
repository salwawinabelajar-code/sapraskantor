<?php
require_once '../config/database.php';
$db = new Database();

// Cek login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Ambil daftar kategori dari database
$kategori_result = $db->query("SELECT nama FROM kategori ORDER BY nama");
$kategori_list = [];
$valid_kategori = [];
if ($kategori_result) {
    while ($row = $kategori_result->fetch_assoc()) {
        $kat_trim = trim($row['nama']);
        $kategori_list[] = $kat_trim;
        $valid_kategori[] = $kat_trim;
    }
}

// HANDLE PENGAJUAN BARU (CREATE)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_pengaduan'])) {
    $tanggal_kejadian = $db->escape_string($_POST['tanggal_kejadian']);
    $judul = $db->escape_string($_POST['judul']);
    $kategori = trim($db->escape_string($_POST['kategori']));
    $prioritas = $db->escape_string($_POST['prioritas']);
    $deskripsi = $db->escape_string($_POST['deskripsi']);

    // Validasi kategori
    if (!in_array($kategori, $valid_kategori)) {
        $error = "Kategori yang dipilih tidak valid!";
    } else {
        // Handle file upload
        $lampiran = null;

        // Validasi lampiran wajib
        if (!isset($_FILES['lampiran']) || $_FILES['lampiran']['error'] != 0) {
            $error = "Lampiran foto wajib diisi!";
        } else {
            $upload_dir = '../assets/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $filename = time() . '_' . basename($_FILES['lampiran']['name']);
            $target_file = $upload_dir . $filename;

            // Validasi file (maks 2MB, hanya gambar)
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
        }

        if (!$error) {
            $query = "INSERT INTO pengaduan (user_id, tanggal_kejadian, judul, kategori, prioritas, lampiran, deskripsi) 
                      VALUES ('$user_id', '$tanggal_kejadian', '$judul', '$kategori', '$prioritas', '$lampiran', '$deskripsi')";

            if ($db->conn->query($query)) {
                $_SESSION['success'] = "Pengaduan berhasil dibuat!";
                header('Location: galeri.php');
                exit;
            } else {
                $error = "Gagal membuat pengaduan: " . $db->conn->error;
            }
        }
    }
}

// HANDLE RATING
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rate'])) {
    $galeri_id = (int)$_POST['galeri_id'];
    $rating = (int)$_POST['rating'];
    
    if ($rating >= 1 && $rating <= 5) {
        // Cek apakah sudah pernah rating
        $check = $db->query("SELECT id FROM rating_galeri WHERE galeri_id = $galeri_id AND user_id = $user_id");
        if ($check->num_rows == 0) {
            $insert = "INSERT INTO rating_galeri (galeri_id, user_id, rating) VALUES ($galeri_id, $user_id, $rating)";
            if ($db->conn->query($insert)) {
                $_SESSION['success'] = "Rating berhasil disimpan!";
            } else {
                $_SESSION['error'] = "Gagal menyimpan rating: " . $db->conn->error;
            }
        } else {
            $_SESSION['error'] = "Anda sudah memberikan rating untuk foto ini.";
        }
    }
    header('Location: galeri.php');
    exit;
}

// HANDLE KOMENTAR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_komentar'])) {
    $galeri_id = (int)$_POST['galeri_id'];
    $komentar = trim($_POST['komentar']);
    
    if (empty($komentar)) {
        $_SESSION['error'] = "Komentar tidak boleh kosong!";
    } else {
        $komentar = $db->escape_string($komentar);
        $query = "INSERT INTO komentar_galeri (galeri_id, user_id, komentar) VALUES ($galeri_id, $user_id, '$komentar')";
        
        if ($db->conn->query($query)) {
            $_SESSION['success'] = "Komentar berhasil ditambahkan!";
        } else {
            $_SESSION['error'] = "Gagal: " . $db->conn->error;
        }
    }
    header('Location: galeri.php#galeri-' . $galeri_id);
    exit;
}

// HANDLE HAPUS KOMENTAR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_komentar'])) {
    $komentar_id = (int)$_POST['komentar_id'];
    $galeri_id = (int)$_POST['galeri_id'];
    
    // Cek kepemilikan
    $check = $db->query("SELECT user_id FROM komentar_galeri WHERE id = $komentar_id");
    if ($check && $check->num_rows > 0) {
        $data = $check->fetch_assoc();
        if ($_SESSION['role'] == 'admin' || $data['user_id'] == $user_id) {
            $db->conn->query("DELETE FROM komentar_galeri WHERE id = $komentar_id");
            $_SESSION['success'] = "Komentar dihapus.";
        }
    }
    header('Location: galeri.php#galeri-' . $galeri_id);
    exit;
}

// AMBIL DATA GALERI (6 BULAN TERAKHIR)
$query = "SELECT g.*, 
          u.nama as uploader_nama,
          COALESCE(AVG(r.rating), 0) as avg_rating, 
          COUNT(DISTINCT r.id) as total_rating,
          MAX(CASE WHEN r.user_id = $user_id THEN r.rating END) as user_rating
          FROM galeri g
          LEFT JOIN users u ON g.user_id = u.id
          LEFT JOIN rating_galeri r ON g.id = r.galeri_id
          WHERE g.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
          GROUP BY g.id
          ORDER BY g.created_at DESC";
$result = $db->query($query);
$galeri_list = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// AMBIL KOMENTAR PER GALERI
$komentar_per_galeri = [];
foreach ($galeri_list as $item) {
    $gid = $item['id'];
    $q = "SELECT k.*, u.nama, u.role FROM komentar_galeri k 
          JOIN users u ON k.user_id = u.id 
          WHERE k.galeri_id = $gid 
          ORDER BY k.created_at DESC";
    $res = $db->query($q);
    $komentar_per_galeri[$gid] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

// AMBIL SESSION MESSAGES
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
    <title>Galeri - AssetCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- SweetAlert2 CSS (opsional, untuk tampilan lebih konsisten) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary: #09637E;
            --secondary: #088395;
            --accent: #7AB2B2;
            --light: #EBF4F6;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: #f0f5f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        /* Navbar */
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%) !important;
            box-shadow: 0 4px 20px rgba(9, 99, 126, 0.15);
            padding: 12px 0;
        }
        
        .navbar-custom .navbar-brand {
            color: white !important;
            font-weight: 700;
            font-size: 1.8rem;
        }
        
        .navbar-custom .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s;
            margin: 0 4px;
        }
        
        .navbar-custom .nav-link:hover,
        .navbar-custom .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white !important;
            transform: translateY(-2px);
        }
        
        .profile-avatar {
            width: 45px;
            height: 45px;
            background: white;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            border: 3px solid rgba(255,255,255,0.3);
            transition: 0.3s;
        }
        
        .profile-avatar:hover {
            transform: scale(1.1);
            border-color: white;
        }
        
        /* Footer */
        .footer {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 20px 0;
            margin-top: auto;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header Card */
        .header-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-left: 5px solid var(--primary);
        }
        
        .header-card h1 {
            color: var(--primary);
            font-weight: 700;
            font-size: 2.2rem;
        }
        
        /* Galeri Grid */
        .galeri-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .galeri-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            scroll-margin-top: 100px;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .galeri-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(9,99,126,0.15);
        }
        
        /* Foto Container */
        .foto-container {
            display: flex;
            height: 200px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .foto-container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(to bottom, transparent 50%, rgba(0,0,0,0.5) 100%);
            z-index: 1;
            pointer-events: none;
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
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            z-index: 2;
        }
        
        .foto-label.after {
            left: 70%;
        }
        
        /* Card Content */
        .card-content {
            padding: 20px;
        }
        
        .card-content h5 {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }
        
        .card-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 12px;
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .card-meta i {
            color: var(--accent);
            width: 18px;
        }
        
        /* Rating */
        .rating-container {
            background: var(--light);
            border-radius: 30px;
            padding: 8px 12px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 1rem;
            letter-spacing: 2px;
        }
        
        .rating-count {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        /* Form Rating */
        .rating-form {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 15px;
        }
        
        .btn-rate {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 600;
            width: 100%;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .btn-rate:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(9,99,126,0.3);
        }
        
        /* Form Komentar */
        .komentar-form {
            margin-bottom: 15px;
        }
        
        .komentar-form textarea {
            border-radius: 12px;
            border: 1px solid #dee2e6;
            resize: none;
            font-size: 0.9rem;
        }
        
        .btn-kirim {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 30px;
            padding: 5px 18px;
            transition: all 0.3s;
        }
        
        .btn-kirim:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }
        
        /* Tombol Lihat Komentar */
        .btn-lihat-komentar {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
            border-radius: 30px;
            padding: 5px 15px;
            font-size: 0.85rem;
            transition: all 0.3s;
            width: 100%;
        }
        
        .btn-lihat-komentar:hover {
            background: var(--primary);
            color: white;
        }
        

/* Tombol gradien khas AssetCare */
.btn-primary-custom {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    border: none;
    color: white;
    padding: 12px 25px;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s;
}
.btn-primary-custom:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(9, 99, 126, 0.3);
    color: white;
}



        /* Modal Komentar */
        .modal-komentar .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
        }
        .modal-komentar .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        .modal-komentar .modal-body {
            padding: 20px;
        }
        .komentar-item {
            border-bottom: 1px solid #eee;
            padding: 12px 0;
        }
        .komentar-item:last-child {
            border-bottom: none;
        }
        .komentar-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.85rem;
        }
        .komentar-nama {
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
        .komentar-tanggal {
            color: #999;
            font-size: 0.75rem;
        }
        .komentar-text {
            font-size: 0.9rem;
            margin-bottom: 5px;
            line-height: 1.4;
        }
        .btn-hapus {
            color: #dc3545;
            font-size: 0.75rem;
            text-decoration: none;
            background: none;
            border: none;
            padding: 0;
        }
        .btn-hapus:hover {
            text-decoration: underline;
        }
        
        /* Alert */
        .alert {
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 25px;
            border: none;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 20px;
            grid-column: 1/-1;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 15px;
        }
        
        /* Modal Detail Foto */
        .modal-detail .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        .modal-detail .modal-img-container {
            display: flex;
            gap: 15px;
        }
        .modal-detail .modal-img {
            flex: 1;
            text-align: center;
        }
        .modal-detail .modal-img img {
            width: 100%;
            max-height: 250px;
            object-fit: cover;
            border-radius: 10px;
        }
        
        /* Preview gambar pada form buat pengaduan */
        .file-preview {
            max-width: 150px;
            max-height: 150px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #eee;
            margin-top: 10px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .galeri-grid {
                grid-template-columns: 1fr;
            }
            .modal-detail .modal-img-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="container">
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
            <div class="header-card animate__animated animate__fadeIn">
                <div class="d-flex align-items-center">
                    <div class="me-4">
                        <i class="fas fa-images fa-3x" style="color: var(--primary); opacity: 0.5;"></i>
                    </div>
                    <div>
                        <h1 class="mb-2">Galeri Sarana Prasarana</h1>
                        <p class="text-muted mb-0">Jelajahi foto-foto sarana dan prasarana 6 bulan terakhir.</p>
                    </div>
                </div>
            </div>

            <!-- Grid Galeri -->
            <?php if (empty($galeri_list)): ?>
                <div class="empty-state animate__animated animate__fadeIn">
                    <i class="fas fa-camera"></i>
                    <h4>Belum Ada Foto</h4>
                    <p class="text-muted mb-3">Foto akan muncul setelah admin mengunggahnya.</p>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                        <a href="../admin/galeri.php" class="btn btn-primary">Unggah Foto</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="galeri-grid">
                    <?php foreach ($galeri_list as $item): 
                        $total_komentar = count($komentar_per_galeri[$item['id']] ?? []);
                    ?>
                        <div class="galeri-card animate__animated animate__fadeInUp" id="galeri-<?php echo $item['id']; ?>">
                            <!-- Foto Container (klik untuk detail) -->
                            <div class="foto-container" data-bs-toggle="modal" data-bs-target="#modalDetail<?php echo $item['id']; ?>">
                                <img src="../assets/uploads/galeri/<?php echo $item['foto_before']; ?>" onerror="this.src='../assets/images/no-image.jpg'">
                                <img src="../assets/uploads/galeri/<?php echo $item['foto_after']; ?>" onerror="this.src='../assets/images/no-image.jpg'">
                                <span class="foto-label">Sebelum</span>
                                <span class="foto-label after">Sesudah</span>
                            </div>
                            
                            <div class="card-content">
                                <h5 title="<?php echo htmlspecialchars($item['judul']); ?>"><?php echo htmlspecialchars($item['judul']); ?></h5>
                                <div class="card-meta">
                                    <span><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($item['uploader_nama'] ?? 'Admin'); ?></span>
                                    <span><i class="fas fa-calendar me-1"></i><?php echo date('d M Y', strtotime($item['created_at'])); ?></span>
                                </div>
                                
                                <!-- Rating -->
                                <div class="rating-container">
                                    <div class="rating-stars">
                                        <?php 
                                        $avg = round($item['avg_rating'] * 2) / 2;
                                        for ($i = 1; $i <= 5; $i++):
                                            if ($i <= floor($avg)) echo '<i class="fas fa-star"></i>';
                                            elseif ($i - 0.5 <= $avg) echo '<i class="fas fa-star-half-alt"></i>';
                                            else echo '<i class="far fa-star"></i>';
                                        endfor;
                                        ?>
                                    </div>
                                    <span class="rating-count"><?php echo $item['total_rating']; ?> ulasan</span>
                                </div>
                                
                                <!-- Form Rating -->
                                <?php if (!$item['user_rating']): ?>
                                    <div class="rating-form">
                                        <form method="POST">
                                            <input type="hidden" name="galeri_id" value="<?php echo $item['id']; ?>">
                                            <select name="rating" class="form-select form-select-sm mb-2" required>
                                                <option value="">Beri rating</option>
                                                <option value="5">5 ⭐</option>
                                                <option value="4">4 ⭐</option>
                                                <option value="3">3 ⭐</option>
                                                <option value="2">2 ⭐</option>
                                                <option value="1">1 ⭐</option>
                                            </select>
                                            <button type="submit" name="rate" class="btn-rate btn-sm">Beri Rating</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-success py-1 px-2 mb-2 small">Rating Anda: <?php echo $item['user_rating']; ?> ⭐</div>
                                <?php endif; ?>

                                <!-- Form Komentar -->
                                <div class="komentar-form">
                                    <form method="POST">
                                        <input type="hidden" name="galeri_id" value="<?php echo $item['id']; ?>">
                                        <div class="input-group">
                                            <textarea name="komentar" class="form-control form-control-sm" rows="1" placeholder="Tulis komentar..." required></textarea>
                                            <button class="btn btn-kirim btn-sm" type="submit" name="submit_komentar"><i class="fas fa-paper-plane"></i></button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Tombol Lihat Semua Komentar -->
                                <button type="button" class="btn-lihat-komentar" data-bs-toggle="modal" data-bs-target="#modalKomentar<?php echo $item['id']; ?>">
                                    <i class="fas fa-comments me-1"></i> Lihat Komentar (<?php echo $total_komentar; ?>)
                                </button>
                            </div>
                        </div>

                        <!-- Modal Detail Foto -->
                        <div class="modal fade modal-detail" id="modalDetail<?php echo $item['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><?php echo htmlspecialchars($item['judul']); ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="modal-img-container">
                                            <div class="modal-img">
                                                <img src="../assets/uploads/galeri/<?php echo $item['foto_before']; ?>" onerror="this.src='../assets/images/no-image.jpg'">
                                                <p class="mt-2">Sebelum</p>
                                            </div>
                                            <div class="modal-img">
                                                <img src="../assets/uploads/galeri/<?php echo $item['foto_after']; ?>" onerror="this.src='../assets/images/no-image.jpg'">
                                                <p class="mt-2">Sesudah</p>
                                            </div>
                                        </div>
                                        <?php if (!empty($item['deskripsi'])): ?>
                                            <div class="mt-3 p-3 bg-light rounded">
                                                <h6 class="fw-bold">Deskripsi</h6>
                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($item['deskripsi'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        <div class="mt-3 text-muted small">
                                            <i class="fas fa-user me-1"></i> Diunggah oleh: <?php echo htmlspecialchars($item['uploader_nama'] ?? 'Admin'); ?>
                                            <span class="mx-2">|</span>
                                            <i class="fas fa-calendar me-1"></i> <?php echo date('d F Y H:i', strtotime($item['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Semua Komentar -->
                        <div class="modal fade modal-komentar" id="modalKomentar<?php echo $item['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="fas fa-comments me-2"></i>Komentar</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                                        <?php if (empty($komentar_per_galeri[$item['id']])): ?>
                                            <p class="text-muted text-center">Belum ada komentar.</p>
                                        <?php else: ?>
                                            <?php foreach ($komentar_per_galeri[$item['id']] as $k): ?>
                                                <div class="komentar-item">
                                                    <div class="komentar-header">
                                                        <span class="komentar-nama">
                                                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($k['nama']); ?>
                                                            <?php if ($k['role'] == 'admin'): ?>
                                                                <span class="badge-admin">Admin</span>
                                                            <?php endif; ?>
                                                        </span>
                                                        <span class="komentar-tanggal"><?php echo date('d M H:i', strtotime($k['created_at'])); ?></span>
                                                    </div>
                                                    <div class="komentar-text"><?php echo nl2br(htmlspecialchars($k['komentar'])); ?></div>
                                                    <?php if ($_SESSION['role'] == 'admin' || $k['user_id'] == $user_id): ?>
                                                        <div class="text-end">
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="komentar_id" value="<?php echo $k['id']; ?>">
                                                                <input type="hidden" name="galeri_id" value="<?php echo $item['id']; ?>">
                                                                <button type="submit" name="hapus_komentar" class="btn-hapus" onclick="return confirm('Hapus komentar ini?')"><i class="fas fa-trash-alt me-1"></i>Hapus</button>
                                                            </form>
                                                        </div>
                                                    <?php endif; ?>
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
    </div>

    <!-- Modal Buat Pengaduan (diperbaiki agar sama dengan halaman bantuan) -->
    <div class="modal fade" id="buatPengaduanModal" tabindex="-1" aria-labelledby="buatPengaduanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #09637E, #088395); color: white;">
                    <h5 class="modal-title" id="buatPengaduanModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Buat Pengaduan Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data" id="formBuatPengaduan">
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
                                    <input type="file" class="form-control" id="lampiran" name="lampiran" accept="image/*" required onchange="previewImage(this, 'previewBuat')">
                                    <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('lampiran').click();"><i class="fas fa-upload"></i></button>
                                </div>
                                <small class="text-muted">Wajib diisi. Maksimal 2MB (JPG, PNG, GIF)</small>
                                <div id="previewBuat" class="mt-2 text-center"></div>
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

    <?php include 'footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Auto close alert
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(el => {
                el.style.transition = 'opacity 0.5s';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            });
        }, 5000);

        // Set tanggal hari ini sebagai default
        document.querySelector('#tanggalKejadian').value = '<?php echo date("Y-m-d"); ?>';

        // Preview gambar sebelum upload
        function previewImage(input, previewId) {
            var preview = document.getElementById(previewId);
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                var file = input.files[0];
                
                // Validasi ukuran file (maks 2MB)
                if (file.size > 2097152) {
                    Swal.fire({
                        title: 'File terlalu besar!',
                        text: 'Ukuran file maksimal 2MB',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                    input.value = '';
                    return;
                }
                
                // Validasi tipe file
                var allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    Swal.fire({
                        title: 'Format file tidak didukung!',
                        text: 'Hanya file JPG, PNG, dan GIF yang diizinkan',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
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

        // Validasi form sebelum submit
        document.getElementById('formBuatPengaduan')?.addEventListener('submit', function(e) {
            var judul = document.getElementById('judul').value;
            var kategori = document.getElementById('kategori').value;
            var deskripsi = document.getElementById('deskripsi').value;
            var lampiran = document.getElementById('lampiran').value;
            
            if (!judul || !kategori || !deskripsi || !lampiran) {
                e.preventDefault();
                Swal.fire({
                    title: 'Form tidak lengkap!',
                    text: 'Harap isi semua field yang wajib diisi termasuk lampiran foto',
                    icon: 'warning',
                    confirmButtonColor: '#3085d6'
                });
            }
        });

        // Animasi modal
        document.addEventListener('DOMContentLoaded', function() {
            var modals = document.querySelectorAll('.modal');
            modals.forEach(function(modal) {
                modal.addEventListener('show.bs.modal', function() {
                    this.classList.add('animate__animated', 'animate__fadeIn');
                });
            });
        });
    </script>

</body>
</html>