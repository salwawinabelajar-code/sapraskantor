<?php
require_once '../config/database.php';
$db = new Database();

// Cek login dan role admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle update status pengaduan
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_status'])) {
        $pengaduan_id = $db->escape_string($_POST['pengaduan_id']);
        $new_status = $db->escape_string($_POST['new_status']);
        $catatan_admin = $db->escape_string($_POST['catatan_admin'] ?? '');
        
        $query = "UPDATE pengaduan SET status = '$new_status', catatan_admin = '$catatan_admin' WHERE id = '$pengaduan_id'";
        
        if ($db->conn->query($query)) {
            $_SESSION['success'] = "Status pengaduan berhasil diupdate!";
            redirect('pengaduan.php');
        } else {
            $error = "Gagal mengupdate status: " . $db->conn->error;
        }
    }
}

// Get filter values
$filter_status = isset($_GET['status']) ? $db->escape_string($_GET['status']) : '';
$filter_kategori = isset($_GET['kategori']) ? $db->escape_string($_GET['kategori']) : '';
$filter_prioritas = isset($_GET['prioritas']) ? $db->escape_string($_GET['prioritas']) : '';
$search = isset($_GET['search']) ? $db->escape_string($_GET['search']) : '';

// ========== TAMBAHAN: Filter berdasarkan user_id ==========
$filter_user_id = isset($_GET['user_id']) ? $db->escape_string($_GET['user_id']) : '';
$user_info = null;
if ($filter_user_id) {
    $user_res = $db->query("SELECT nama FROM users WHERE id = '$filter_user_id'");
    if ($user_res->num_rows > 0) {
        $user_info = $user_res->fetch_assoc();
    } else {
        // Jika user_id tidak valid, redirect ke halaman utama pengaduan dengan pesan error
        $_SESSION['error'] = "User tidak ditemukan!";
        redirect('pengaduan.php');
    }
}
// ========== End TAMBAHAN ==========

// ========== AMBIL KATEGORI DARI DATABASE ==========
$kategori_result = $db->query("SELECT nama FROM kategori ORDER BY nama");
$kategori_list = [];
if ($kategori_result && $kategori_result->num_rows > 0) {
    while ($row = $kategori_result->fetch_assoc()) {
        $kategori_list[] = $row['nama'];
    }
}
// ========== END AMBIL KATEGORI ==========

// Build query with filters
$query = "SELECT p.*, u.nama as nama_user, u.email as email_user 
          FROM pengaduan p 
          LEFT JOIN users u ON p.user_id = u.id 
          WHERE 1=1";

// ========== TAMBAHAN: Filter user_id ==========
if ($filter_user_id) {
    $query .= " AND p.user_id = '$filter_user_id'";
}
// ========== End TAMBAHAN ==========

if ($filter_status) {
    $query .= " AND p.status = '$filter_status'";
}
if ($filter_kategori) {
    $query .= " AND p.kategori = '$filter_kategori'";
}
if ($filter_prioritas) {
    $query .= " AND p.prioritas = '$filter_prioritas'";
}
if ($search) {
    $query .= " AND (p.judul LIKE '%$search%' OR p.deskripsi LIKE '%$search%' OR u.nama LIKE '%$search%')";
}

$query .= " ORDER BY p.created_at DESC";
$result = $db->query($query);
$pengaduan_data = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengaduan - AssetCare Admin</title>
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
        .sidebar .nav-link {
    color: rgba(255, 255, 255, 0.8);
    padding: 12px 20px;
    margin: 5px 0;
    border-radius: 8px;
    transition: all 0.3s;
    font-weight: 500;
}

.sidebar .nav-link:hover,
.sidebar .nav-link.active {
    background: rgba(255, 255, 255, 0.15);
    color: white;
}

.sidebar .nav-link i {
    width: 25px;
    font-size: 1.1rem;
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
        
        .sidebar {
            background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            color: white;
            position: fixed;
            width: 250px;
            z-index: 1000;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }
        
        .sidebar .nav-link i {
            width: 25px;
            font-size: 1.1rem;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .navbar-top {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 15px 20px;
            margin: -20px -20px 20px;
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
            font-size: 1rem;
        }
        
        .card-custom {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            border: none;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }
        
        .table th {
            background: var(--light);
            color: var(--primary);
            font-weight: 600;
            border: none;
            padding: 15px;
        }
        
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge-menunggu { background: #ffc107; color: #212529; }
        .badge-diproses { background: #0dcaf0; color: white; }
        .badge-selesai { background: #198754; color: white; }
        .badge-ditolak { background: #dc3545; color: white; }
        
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            .sidebar .nav-link span {
                display: none;
            }
            .main-content {
                margin-left: 70px;
            }
            .table-responsive {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="navbar-top d-flex justify-content-between align-items-center">
            <h4 class="mb-0 text-primary">Manajemen Pengaduan</h4>
            <div class="d-flex align-items-center gap-3">
                <a href="index.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" 
                       data-bs-toggle="dropdown">
                        <div class="profile-avatar me-2">
                            <?php echo generateInitials($_SESSION['nama']); ?>
                        </div>
                        <div>
                            <span class="fw-bold"><?php echo $_SESSION['nama']; ?></span><br>
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
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeInDown" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeInDown" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ========== TAMBAHAN: Info User ========== -->
        <?php if ($user_info): ?>
            <div class="alert alert-info d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
                <div>
                    <i class="fas fa-user me-2"></i>
                    Menampilkan pengaduan dari <strong><?php echo htmlspecialchars($user_info['nama']); ?></strong>
                </div>
                <a href="pengaduan.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-times me-1"></i> Tampilkan Semua Pengaduan
                </a>
            </div>
        <?php endif; ?>
        <!-- ========== End TAMBAHAN ========== -->

        <!-- Filter Section -->
        <div class="filter-card">
            <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filter Pengaduan</h5>
            <form method="GET" class="row g-3">
                <!-- ========== TAMBAHAN: Hidden input untuk user_id ========== -->
                <?php if ($filter_user_id): ?>
                    <input type="hidden" name="user_id" value="<?php echo $filter_user_id; ?>">
                <?php endif; ?>
                <!-- ========== End TAMBAHAN ========== -->
                
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">Semua Status</option>
                        <option value="Menunggu" <?php echo $filter_status == 'Menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                        <option value="Diproses" <?php echo $filter_status == 'Diproses' ? 'selected' : ''; ?>>Diproses</option>
                        <option value="Selesai" <?php echo $filter_status == 'Selesai' ? 'selected' : ''; ?>>Selesai</option>
                        <option value="Ditolak" <?php echo $filter_status == 'Ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Kategori</label>
                    <select class="form-select" name="kategori">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($kategori_list as $kategori): ?>
                            <option value="<?php echo htmlspecialchars($kategori); ?>" <?php echo $filter_kategori == $kategori ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kategori); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Prioritas</label>
                    <select class="form-select" name="prioritas">
                        <option value="">Semua Prioritas</option>
                        <option value="Rendah" <?php echo $filter_prioritas == 'Rendah' ? 'selected' : ''; ?>>Rendah</option>
                        <option value="Sedang" <?php echo $filter_prioritas == 'Sedang' ? 'selected' : ''; ?>>Sedang</option>
                        <option value="Tinggi" <?php echo $filter_prioritas == 'Tinggi' ? 'selected' : ''; ?>>Tinggi</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Pencarian</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Cari..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <div class="col-12">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i> Terapkan Filter
                        </button>
                        <!-- ========== TAMBAHAN: Link reset dengan tetap mempertahankan user_id jika ada ========== -->
                        <a href="pengaduan.php<?php echo $filter_user_id ? '?user_id='.$filter_user_id : ''; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-redo me-1"></i> Reset
                        </a>
                        <!-- ========== End TAMBAHAN ========== -->
                        <?php if ($filter_status || $filter_kategori || $filter_prioritas || $search || $filter_user_id): ?>
                            <span class="ms-auto text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <?php echo count($pengaduan_data); ?> data ditemukan
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Pengaduan Table -->
        <div class="table-container">
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">Daftar Pengaduan</h5>
                    <div class="d-flex gap-2">
                        <span class="badge bg-light text-dark">
                            <i class="fas fa-list me-1"></i>
                            Total: <?php echo count($pengaduan_data); ?>
                        </span>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Pelapor</th>
                                <th>Tanggal</th>
                                <th>Judul</th>
                                <th>Kategori</th>
                                <th>Prioritas</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pengaduan_data)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <h5>Tidak ada data pengaduan</h5>
                                        <p class="mb-0">Tidak ada pengaduan dengan filter yang dipilih</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pengaduan_data as $index => $p): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo $index + 1; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="profile-avatar me-2" style="width:35px; height:35px; font-size:0.9rem;">
                                                    <?php echo generateInitials($p['nama_user']); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo $p['nama_user']; ?></strong><br>
                                                    <small class="text-muted"><?php echo $p['email_user']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($p['tanggal_kejadian'])); ?><br>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($p['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($p['judul']); ?></strong><br>
                                            <small class="text-muted"><?php echo substr($p['deskripsi'], 0, 50); ?>...</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo $p['kategori']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $priority_color = '';
                                            switch($p['prioritas']) {
                                                case 'Tinggi': $priority_color = 'danger'; break;
                                                case 'Sedang': $priority_color = 'warning'; break;
                                                case 'Rendah': $priority_color = 'success'; break;
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $priority_color; ?>">
                                                <?php echo $p['prioritas']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-status badge-<?php echo strtolower($p['status']); ?>">
                                                <?php echo $p['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="detail_pengaduan.php?id=<?php echo $p['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Detail">
                                                <i class="fas fa-eye"></i> Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh untuk pengaduan menunggu (tetap dipertahankan)
        function checkPendingReports() {
            const pendingBadge = document.getElementById('pendingCount');
            if (pendingBadge && <?php echo $filter_status == 'Menunggu' || $filter_status == '' ? 'true' : 'false'; ?>) {
                fetch('api/get_pending_count.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.pending > 0) {
                            pendingBadge.textContent = data.pending;
                            pendingBadge.classList.add('animate__animated', 'animate__pulse');
                            setTimeout(() => {
                                pendingBadge.classList.remove('animate__animated', 'animate__pulse');
                            }, 1000);
                        }
                    });
            }
        }
        setInterval(checkPendingReports, 30000);
    </script>
</body>
</html>