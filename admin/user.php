<?php
require_once '../config/database.php';
$db = new Database();

// Cek login dan role admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$error = '';
$success = '';

// Handle hapus user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_user'])) {
    $user_id = $db->escape_string($_POST['user_id']);

    // Cegah admin menghapus akun sendiri
    if ($user_id == $_SESSION['user_id']) {
        $error = "Anda tidak dapat menghapus akun sendiri!";
    } else {
        // Mulai transaksi
        $db->conn->begin_transaction();

        try {
            // Hapus semua pengaduan milik user terlebih dahulu
            $deletePengaduan = "DELETE FROM pengaduan WHERE user_id = '$user_id'";
            $db->conn->query($deletePengaduan);

            // Hapus user
            $deleteUser = "DELETE FROM users WHERE id = '$user_id'";
            if ($db->conn->query($deleteUser)) {
                $db->conn->commit();
                $_SESSION['success'] = "User beserta seluruh pengaduannya berhasil dihapus!";
            } else {
                throw new Exception($db->conn->error);
            }
        } catch (Exception $e) {
            $db->conn->rollback();
            $error = "Gagal menghapus user: " . $e->getMessage();
        }

        redirect('user.php');
    }
}

// Get search filter
$search = isset($_GET['search']) ? $db->escape_string($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $db->escape_string($_GET['role']) : '';
$status_filter = isset($_GET['status']) ? $db->escape_string($_GET['status']) : '';

// Build query
$query = "SELECT *, 
          (SELECT COUNT(*) FROM pengaduan WHERE user_id = users.id) as total_pengaduan
          FROM users WHERE 1=1";

if ($search) {
    $query .= " AND (nama LIKE '%$search%' OR email LIKE '%$search%')";
}
if ($role_filter) {
    $query .= " AND role = '$role_filter'";
}
if ($status_filter) {
    $query .= " AND status = '$status_filter'";
}

$query .= " ORDER BY created_at DESC";
$result = $db->query($query);
$users = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User - AssetCare Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        
        .user-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        
        .user-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #666;
        }
        
        .badge-role {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge-pegawai { background: #6c757d; color: white; }  /* Warna sama seperti user */
        .badge-admin { background: var(--primary); color: white; }
        
        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-aktif { background: #198754; color: white; }
        .badge-nonaktif { background: #dc3545; color: white; }

        /* Tombol hapus */
        .btn-hapus {
            color: #dc3545;
            border-color: #dc3545;
        }
        .btn-hapus:hover {
            background: #dc3545;
            color: white;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .user-stats {
                flex-direction: column;
                gap: 10px;
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
            <h4 class="mb-0 text-primary">Manajemen User</h4>
            <div class="d-flex align-items-center gap-3">
                <a href="index.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Dashboard
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
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="user-card">
            <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filter User</h5>
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Pencarian</label>
                    <input type="text" class="form-control" name="search" placeholder="Cari nama atau email..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role">
                        <option value="">Semua Role</option>
                        <option value="pegawai" <?php echo $role_filter == 'pegawai' ? 'selected' : ''; ?>>Pegawai</option>
                        <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?php echo $status_filter == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="nonaktif" <?php echo $status_filter == 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                </div>
                
                <div class="col-12">
                    <a href="user.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-1"></i> Reset Filter
                    </a>
                    <?php if ($search || $role_filter || $status_filter): ?>
                        <span class="ms-3 text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            <?php echo count($users); ?> user ditemukan
                        </span>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Users Grid -->
        <div class="row">
            <?php if (empty($users)): ?>
                <div class="col-12">
                    <div class="user-card text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Tidak ada user ditemukan</h5>
                        <p class="mb-0">Coba gunakan filter yang berbeda</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="user-card">
                            <div class="user-avatar">
                                <?php echo generateInitials($user['nama']); ?>
                            </div>
                            
                            <div class="text-center mb-3">
                                <h5 class="mb-1"><?php echo htmlspecialchars($user['nama']); ?></h5>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-envelope me-1"></i>
                                    <?php echo $user['email']; ?>
                                </p>
                                
                                <div class="d-flex justify-content-center gap-2 mb-3">
                                    <span class="badge-role badge-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                    <span class="badge-status badge-<?php echo $user['status'] ?? 'aktif'; ?>">
                                        <?php echo ucfirst($user['status'] ?? 'aktif'); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="user-stats">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $user['total_pengaduan']; ?></div>
                                    <div class="stat-label">Pengaduan</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number">
                                        <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                                    </div>
                                    <div class="stat-label">Bergabung</div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <div class="d-grid gap-2">
                                    <a href="pengaduan.php?user_id=<?php echo $user['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-clipboard-list me-1"></i> Lihat Pengaduan
                                    </a>

                                    <!-- Tombol Hapus (kecuali admin yang sedang login) -->
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-hapus" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#hapusModal<?php echo $user['id']; ?>">
                                            <i class="fas fa-trash-alt me-1"></i> Hapus
                                        </button>

                                        <!-- Modal Konfirmasi Hapus -->
                                        <div class="modal fade" id="hapusModal<?php echo $user['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title">Konfirmasi Hapus</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Apakah Anda yakin ingin menghapus user <strong><?php echo htmlspecialchars($user['nama']); ?></strong>?</p>
                                                        <p class="text-danger mb-0">
                                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                                            Semua pengaduan milik user ini juga akan ikut terhapus!
                                                        </p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" name="hapus_user" class="btn btn-danger">
                                                                <i class="fas fa-trash-alt me-1"></i> Ya, Hapus
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Summary -->
        <div class="user-card">
            <h5 class="mb-3">Ringkasan User</h5>
            <div class="row text-center">
                <div class="col-md-3 mb-3">
                    <div class="stat-number"><?php echo count($users); ?></div>
                    <div class="stat-label">Total User</div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-number">
                        <?php
                        $admin_count = array_filter($users, function($u) {
                            return $u['role'] == 'admin';
                        });
                        echo count($admin_count);
                        ?>
                    </div>
                    <div class="stat-label">Admin</div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-number">
                        <?php
                        $active_count = array_filter($users, function($u) {
                            return ($u['status'] ?? 'aktif') == 'aktif';
                        });
                        echo count($active_count);
                        ?>
                    </div>
                    <div class="stat-label">Aktif</div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-number">
                        <?php
                        $inactive_count = array_filter($users, function($u) {
                            return ($u['status'] ?? 'aktif') == 'nonaktif';
                        });
                        echo count($inactive_count);
                        ?>
                    </div>
                    <div class="stat-label">Nonaktif</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>