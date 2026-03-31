<?php
require_once '../config/database.php';
$db = new Database();

// Cek login dan role admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

if (!isset($_GET['id'])) {
    redirect('pengaduan.php');
}

$pengaduan_id = $db->escape_string($_GET['id']);
$error = '';
$success = '';

// Get pengaduan details
$query = "SELECT p.*, u.nama as nama_user, u.email as email_user 
          FROM pengaduan p 
          LEFT JOIN users u ON p.user_id = u.id 
          WHERE p.id = '$pengaduan_id'";
$result = $db->query($query);

if ($result->num_rows == 0) {
    $_SESSION['error'] = "Pengaduan tidak ditemukan!";
    redirect('pengaduan.php');
}

$pengaduan = $result->fetch_assoc();

// Handle update status
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_status'])) {
        $new_status = $db->escape_string($_POST['new_status']);
        $catatan_admin = $db->escape_string($_POST['catatan_admin'] ?? '');
        
        $query = "UPDATE pengaduan SET status = '$new_status', catatan_admin = '$catatan_admin' WHERE id = '$pengaduan_id'";
        
        if ($db->conn->query($query)) {
            $_SESSION['success'] = "Status pengaduan berhasil diupdate!";
            redirect('detail_pengaduan.php?id=' . $pengaduan_id);
        } else {
            $error = "Gagal mengupdate status: " . $db->conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pengaduan - AssetCare Admin</title>
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
        
        .detail-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }
        
        .info-label {
            color: #666;
            font-weight: 600;
            min-width: 150px;
        }
        
        .info-value {
            color: #333;
            font-weight: 500;
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
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--light);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -20px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary);
        }
        
        .image-preview {
            max-width: 300px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .detail-card {
                padding: 20px;
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
            <div class="d-flex align-items-center gap-3">
                <a href="pengaduan.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
                <h4 class="mb-0 text-primary">Detail Pengaduan</h4>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted">ID: #<?php echo str_pad($pengaduan['id'], 6, '0', STR_PAD_LEFT); ?></span>
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

        <div class="row">
            <!-- Left Column: Informasi Pengaduan -->
            <div class="col-lg-8 mb-4">
                <div class="detail-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">Informasi Pengaduan</h5>
                        <span class="badge-status badge-<?php echo strtolower($pengaduan['status']); ?>">
                            <?php echo $pengaduan['status']; ?>
                        </span>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <div class="info-label">Judul Pengaduan</div>
                            <div class="info-value h5"><?php echo htmlspecialchars($pengaduan['judul']); ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="info-label">Tanggal Kejadian</div>
                            <div class="info-value">
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo date('d F Y', strtotime($pengaduan['tanggal_kejadian'])); ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="info-label">Kategori</div>
                            <div class="info-value">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-tag me-1"></i>
                                    <?php echo $pengaduan['kategori']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="info-label">Prioritas</div>
                            <div class="info-value">
                                <?php
                                $priority_color = '';
                                switch($pengaduan['prioritas']) {
                                    case 'Tinggi': $priority_color = 'danger'; break;
                                    case 'Sedang': $priority_color = 'warning'; break;
                                    case 'Rendah': $priority_color = 'success'; break;
                                }
                                ?>
                                <span class="badge bg-<?php echo $priority_color; ?>">
                                    <i class="fas fa-exclamation-circle me-1"></i>
                                    <?php echo $pengaduan['prioritas']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="info-label">Dibuat Pada</div>
                            <div class="info-value">
                                <i class="fas fa-clock me-1"></i>
                                <?php echo date('d/m/Y H:i', strtotime($pengaduan['created_at'])); ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="info-label">Terakhir Diupdate</div>
                            <div class="info-value">
                                <i class="fas fa-sync-alt me-1"></i>
                                <?php echo date('d/m/Y H:i', strtotime($pengaduan['updated_at'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="info-label mb-2">Deskripsi Kerusakan</div>
                        <div class="border rounded p-3 bg-light">
                            <?php echo nl2br(htmlspecialchars($pengaduan['deskripsi'])); ?>
                        </div>
                    </div>
                    
                    <?php if ($pengaduan['lampiran']): ?>
                        <div class="mb-4">
                            <div class="info-label mb-2">Lampiran Foto</div>
                            <div>
                                <img src="../assets/uploads/<?php echo $pengaduan['lampiran']; ?>" 
                                     alt="Lampiran Pengaduan" 
                                     class="image-preview img-fluid"
                                     data-bs-toggle="modal" data-bs-target="#imageModal">
                                <small class="d-block text-muted mt-1">Klik gambar untuk memperbesar</small>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Catatan Admin -->
                    <?php if ($pengaduan['catatan_admin']): ?>
                        <div class="mb-4">
                            <div class="info-label mb-2">Catatan Admin</div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <?php echo nl2br(htmlspecialchars($pengaduan['catatan_admin'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Column: Informasi Pelapor dan Aksi -->
            <div class="col-lg-4">
                <!-- Informasi Pelapor -->
                <div class="detail-card mb-4">
                    <h5 class="mb-4">Informasi Pelapor</h5>
                    
                    <div class="d-flex align-items-center mb-3">
                        <div class="profile-avatar me-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                            <?php echo generateInitials($pengaduan['nama_user']); ?>
                        </div>
                        <div>
                            <div class="info-value h6 mb-1"><?php echo $pengaduan['nama_user']; ?></div>
                            <div class="text-muted">
                                <i class="fas fa-envelope me-1"></i>
                                <?php echo $pengaduan['email_user']; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="info-label">User ID</div>
                            <div class="info-value">#<?php echo str_pad($pengaduan['user_id'], 6, '0', STR_PAD_LEFT); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">Total Laporan</div>
                            <div class="info-value">
                                <?php
                                $total_query = $db->query("SELECT COUNT(*) as total FROM pengaduan WHERE user_id = '{$pengaduan['user_id']}'");
                                $total = $total_query->fetch_assoc()['total'];
                                echo $total;
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <a href="profil_user.php?id=<?php echo $pengaduan['user_id']; ?>" class="btn btn-outline-primary w-100">
    <i class="fas fa-user me-1"></i> Lihat Profil User
</a>
                    </div>
                </div>
                
                <!-- Aksi Admin -->
                <div class="detail-card">
                    <h5 class="mb-4">Aksi Admin</h5>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Ubah Status</label>
                            <select class="form-select" name="new_status" required>
                                <?php if ($pengaduan['status'] == 'Menunggu'): ?>
                                    <option value="Diproses">Diproses</option>
                                    <option value="Ditolak">Ditolak</option>
                                <?php elseif ($pengaduan['status'] == 'Diproses'): ?>
                                    <option value="Selesai">Selesai</option>
                                <?php else: ?>
                                    <option value="<?php echo $pengaduan['status']; ?>" selected><?php echo $pengaduan['status']; ?></option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tambahkan Catatan</label>
                            <textarea class="form-control" name="catatan_admin" rows="4" 
                                      placeholder="Tambahkan catatan untuk pelapor..."><?php echo $pengaduan['catatan_admin']; ?></textarea>
                            <small class="text-muted">Catatan ini akan ditampilkan ke pelapor</small>
                        </div>
                        
                        <button type="submit" name="update_status" class="btn btn-primary w-100">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="info-label">Dibuat</div>
                            <div class="info-value small">
                                <?php echo date('d/m/Y H:i', strtotime($pengaduan['created_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="timeline-item">
                            <div class="info-label">Status Saat Ini</div>
                            <div class="info-value">
                                <span class="badge-status badge-<?php echo strtolower($pengaduan['status']); ?>">
                                    <?php echo $pengaduan['status']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($pengaduan['updated_at'] != $pengaduan['created_at']): ?>
                            <div class="timeline-item">
                                <div class="info-label">Terakhir Diupdate</div>
                                <div class="info-value small">
                                    <?php echo date('d/m/Y H:i', strtotime($pengaduan['updated_at'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Lampiran Foto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="../assets/uploads/<?php echo $pengaduan['lampiran']; ?>" 
                         alt="Lampiran Pengaduan" 
                         class="img-fluid rounded">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus catatan textarea jika status Ditolak
        document.querySelector('select[name="new_status"]').addEventListener('change', function() {
            if (this.value === 'Ditolak') {
                document.querySelector('textarea[name="catatan_admin"]').focus();
                document.querySelector('textarea[name="catatan_admin"]').placeholder = "Harap berikan alasan penolakan...";
            }
        });
    </script>
</body>
</html>