<?php
require_once '../config/database.php';
$db = new Database();

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get statistics (sama seperti sebelumnya)
$stats = [];
$result = $db->query("SELECT COUNT(*) as total,
    SUM(status = 'Menunggu') as menunggu,
    SUM(status = 'Diproses') as diproses,
    SUM(status = 'Selesai') as selesai,
    SUM(status = 'Ditolak') as ditolak
    FROM pengaduan");
$stats = $result->fetch_assoc();

// Get recent pengaduan
$result = $db->query("SELECT p.*, u.nama as nama_user FROM pengaduan p 
                      LEFT JOIN users u ON p.user_id = u.id 
                      WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                      ORDER BY p.created_at DESC LIMIT 10");
$recent_pengaduan = $result->fetch_all(MYSQLI_ASSOC);

// Get top categories
$result = $db->query("SELECT kategori, COUNT(*) as jumlah FROM pengaduan 
                      GROUP BY kategori ORDER BY jumlah DESC LIMIT 5");
$top_categories = $result->fetch_all(MYSQLI_ASSOC);

// Get monthly data for chart
$monthly_data = [];
$result = $db->query("SELECT DATE_FORMAT(created_at, '%b %Y') as bulan,
                      COUNT(*) as total,
                      SUM(status = 'Selesai') as selesai
                      FROM pengaduan 
                      WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                      GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                      ORDER BY created_at");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $monthly_data[] = $row;
    }
} else {
    for ($i = 5; $i >= 0; $i--) {
        $month = date('M Y', strtotime("-$i months"));
        $monthly_data[] = ['bulan' => $month, 'total' => 0, 'selesai' => 0];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - AssetCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .main-content {
            min-height: 100vh;
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
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 15px;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 10px;
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .chart-wrapper {
            height: 250px;
            position: relative;
        }
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        .table th {
            background: var(--light);
            color: var(--primary);
            font-weight: 600;
            border: none;
            padding: 15px;
        }
        .table td {
            padding: 12px 15px;
            vertical-align: middle;
            border-color: #f1f1f1;
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
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .quick-action-btn {
            background: white;
            border: 2px solid var(--light);
            border-radius: 10px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--primary);
            text-decoration: none;
            transition: all 0.3s;
            flex: 1;
            min-width: 200px;
        }
        .quick-action-btn:hover {
            border-color: var(--accent);
            background: var(--light);
            transform: translateY(-3px);
        }
        .quick-action-btn i {
            font-size: 1.5rem;
        }
        @media (max-width: 768px) {
            .quick-action-btn {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main content -->
    <div class="main-content">
        <!-- Navbar atas -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <a class="navbar-brand" href="#">Dashboard Admin</a>
                <div class="d-flex align-items-center ms-auto">
                    <span class="text-muted me-3 d-none d-md-block">
                        <i class="fas fa-calendar me-1"></i><?php echo date('d F Y'); ?>
                    </span>
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                            <div class="profile-avatar me-2">
                                <?php echo generateInitials($_SESSION['nama']); ?>
                            </div>
                            <div class="d-none d-md-block">
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
        </nav>

        <div class="container-fluid px-4">
            <!-- Quick Actions -->
            <div class="quick-actions mb-4">
                <a href="pengaduan.php?status=Menunggu" class="quick-action-btn">
                    <i class="fas fa-clock text-warning"></i>
                    <div>
                        <strong>Pengaduan Menunggu</strong>
                        <small class="d-block">Tinjau <?php echo $stats['menunggu']; ?> pengaduan</small>
                    </div>
                </a>
                <a href="pengaduan.php?status=Diproses" class="quick-action-btn">
                    <i class="fas fa-cogs text-info"></i>
                    <div>
                        <strong>Sedang Diproses</strong>
                        <small class="d-block">Pantau <?php echo $stats['diproses']; ?> pengaduan</small>
                    </div>
                </a>
                <a href="user.php" class="quick-action-btn">
                    <i class="fas fa-user-plus text-success"></i>
                    <div>
                        <strong>Manajemen User</strong>
                        <small class="d-block">Kelola akun pengguna</small>
                    </div>
                </a>
            </div>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(9, 99, 126, 0.1); color: var(--primary);">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-number text-primary"><?php echo $stats['total'] ?? 0; ?></div>
                        <div class="text-muted">Total Pengaduan</div>
                        <small class="d-block mt-2">
                            <i class="fas fa-chart-line me-1"></i>
                            <?php echo $stats['total'] > 0 ? round(($stats['selesai'] / $stats['total']) * 100) : 0; ?>% terselesaikan
                        </small>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number text-warning"><?php echo $stats['menunggu'] ?? 0; ?></div>
                        <div class="text-muted">Menunggu</div>
                        <small class="d-block mt-2">
                            <i class="fas fa-exclamation-circle me-1"></i>Perlu segera ditinjau
                        </small>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(13, 202, 240, 0.1); color: #0dcaf0;">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <div class="stat-number text-info"><?php echo $stats['diproses'] ?? 0; ?></div>
                        <div class="text-muted">Diproses</div>
                        <small class="d-block mt-2">
                            <i class="fas fa-spinner me-1"></i>Sedang dalam penanganan
                        </small>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(25, 135, 84, 0.1); color: #198754;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-number text-success"><?php echo $stats['selesai'] ?? 0; ?></div>
                        <div class="text-muted">Selesai</div>
                        <small class="d-block mt-2">
                            <i class="fas fa-calendar-check me-1"></i>Telah diselesaikan
                        </small>
                    </div>
                </div>
            </div>

            <!-- Charts and Top Categories -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="chart-container">
                        <h5 class="mb-3">Statistik Pengaduan (6 Bulan Terakhir)</h5>
                        <div class="chart-wrapper">
                            <canvas id="pengaduanChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="chart-container">
                        <h5 class="mb-3">Kategori Teratas</h5>
                        <div class="mt-4">
                            <?php if (empty($top_categories)): ?>
                                <p class="text-muted">Belum ada data kategori</p>
                            <?php else: ?>
                                <?php foreach ($top_categories as $cat): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span><?php echo htmlspecialchars($cat['kategori']); ?></span>
                                            <span><?php echo $cat['jumlah']; ?> laporan</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <?php $percentage = $stats['total'] > 0 ? ($cat['jumlah'] / $stats['total'] * 100) : 0; ?>
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%; background: var(--accent);"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Pengaduan -->
            <div class="table-container">
                <div class="p-4">
                    <h5 class="mb-3">Pengaduan Terbaru (7 Hari Terakhir)</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Pelapor</th>
                                    <th>Tanggal</th>
                                    <th>Judul</th>
                                    <th>Kategori</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_pengaduan)): ?>
                                    <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada pengaduan dalam 7 hari terakhir</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_pengaduan as $index => $p): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="profile-avatar me-2" style="width:35px;height:35px;">
                                                        <?php echo generateInitials($p['nama_user']); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($p['nama_user']); ?></strong><br>
                                                        <small class="text-muted">ID: <?php echo $p['user_id']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($p['tanggal_kejadian'])); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($p['judul']); ?></strong><br>
                                                <small class="text-muted"><?php echo substr($p['deskripsi'], 0, 50); ?>...</small>
                                            </td>
                                            <td><span class="badge bg-light text-dark"><?php echo $p['kategori']; ?></span></td>
                                            <td><span class="badge-status badge-<?php echo strtolower($p['status']); ?>"><?php echo $p['status']; ?></span></td>
                                            <td><a href="detail_pengaduan.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> Detail</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart initialization (sama seperti sebelumnya)
        const ctx = document.getElementById('pengaduanChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_data); ?>;
        const labels = monthlyData.map(item => item.bulan);
        const totalData = monthlyData.map(item => item.total || 0);
        const selesaiData = monthlyData.map(item => item.selesai || 0);
        if (labels.length > 0) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Total Pengaduan', data: totalData, borderColor: '#09637E', backgroundColor: 'rgba(9,99,126,0.1)', tension: 0.3, fill: true },
                        { label: 'Pengaduan Selesai', data: selesaiData, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,0.1)', tension: 0.3, fill: true }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: { y: { beginAtZero: true, stepSize: 1, precision: 0 } }
                }
            });
        }
    </script>
</body>
</html>