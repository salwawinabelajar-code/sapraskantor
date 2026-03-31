<?php
require_once '../config/database.php';
$db = new Database();

// Cek login dan role admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// Get filter values
$periode = isset($_GET['periode']) ? $_GET['periode'] : 'custom';
$start_date = isset($_GET['start_date']) ? $db->escape_string($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? $db->escape_string($_GET['end_date']) : '';
$status_filter = isset($_GET['status']) ? $db->escape_string($_GET['status']) : '';
$kategori_filter = isset($_GET['kategori']) ? $db->escape_string($_GET['kategori']) : '';
$search = isset($_GET['search']) ? $db->escape_string($_GET['search']) : '';

// Jika periode 'all', kosongkan tanggal agar tidak dipakai di query
if ($periode == 'all') {
    $start_date = '';
    $end_date = '';
}

// Set default tanggal jika periode bukan 'all' dan tanggal belum diisi (misal pertama kali buka halaman)
if ($periode != 'all' && empty($start_date) && empty($end_date)) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d');
}

// Build query
$query = "SELECT p.*, u.nama as nama_user 
          FROM pengaduan p 
          LEFT JOIN users u ON p.user_id = u.id 
          WHERE 1=1";

if ($periode != 'all' && !empty($start_date) && !empty($end_date)) {
    $query .= " AND DATE(p.created_at) BETWEEN '$start_date' AND '$end_date'";
}
if ($status_filter) {
    $query .= " AND p.status = '$status_filter'";
}
if ($kategori_filter) {
    $query .= " AND p.kategori = '$kategori_filter'";
}
if ($search) {
    $query .= " AND (p.judul LIKE '%$search%' OR p.deskripsi LIKE '%$search%' OR u.nama LIKE '%$search%')";
}

$query .= " ORDER BY p.created_at DESC";
$result = $db->query($query);
$riwayat = $result->fetch_all(MYSQLI_ASSOC);

// ========== AMBIL KATEGORI DARI DATABASE ==========
$kategori_result = $db->query("SELECT nama FROM kategori ORDER BY nama");
$kategori_list = [];
if ($kategori_result && $kategori_result->num_rows > 0) {
    while ($row = $kategori_result->fetch_assoc()) {
        $kategori_list[] = $row['nama'];
    }
}
// ========== END AMBIL KATEGORI ==========

// Export to Excel
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="riwayat_pengaduan_' . date('Y-m-d_H-i-s') . '.xls"');
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th { background-color: #09637E; color: white; font-weight: bold; padding: 8px; border: 1px solid #ddd; }';
    echo 'td { padding: 8px; border: 1px solid #ddd; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h2>Riwayat Pengaduan AssetCare</h2>';
    echo '<p>Tanggal Export: ' . date('d F Y H:i:s') . '</p>';
    
    if ($periode == 'all') {
        echo '<p>Periode: Semua Data</p>';
    } else {
        echo '<p>Periode: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)) . '</p>';
    }
    echo '<p>Filter Status: ' . ($status_filter ?: 'Semua') . '</p>';
    echo '<p>Filter Kategori: ' . ($kategori_filter ?: 'Semua') . '</p>';
    
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>No</th>';
    echo '<th>Tanggal</th>';
    echo '<th>Pelapor</th>';
    echo '<th>Judul</th>';
    echo '<th>Kategori</th>';
    echo '<th>Prioritas</th>';
    echo '<th>Status</th>';
    echo '<th>Deskripsi</th>';
    echo '</tr>';
    
    $no = 1;
    foreach ($riwayat as $item) {
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . date('d/m/Y', strtotime($item['tanggal_kejadian'])) . '</td>';
        echo '<td>' . htmlspecialchars($item['nama_user']) . '</td>';
        echo '<td>' . htmlspecialchars($item['judul']) . '</td>';
        echo '<td>' . $item['kategori'] . '</td>';
        echo '<td>' . $item['prioritas'] . '</td>';
        echo '<td>' . $item['status'] . '</td>';
        echo '<td>' . htmlspecialchars($item['deskripsi']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pengaduan - AssetCare Admin</title>
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
        
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
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
        
        .summary-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .summary-number {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .export-btn {
            background: #28a745;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .export-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .main-content {
                margin-left: 70px;
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
            <h4 class="mb-0 text-primary">Riwayat Pengaduan</h4>
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

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="summary-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="summary-number"><?php echo count($riwayat); ?></div>
                            <div>Total Pengaduan</div>
                        </div>
                        <i class="fas fa-clipboard-list fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="summary-card" style="background: linear-gradient(135deg, #ffc107 0%, #ffdb6d 100%); color: #212529;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="summary-number">
                                <?php
                                $menunggu = array_filter($riwayat, function($r) {
                                    return $r['status'] == 'Menunggu';
                                });
                                echo count($menunggu);
                                ?>
                            </div>
                            <div>Menunggu</div>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="summary-card" style="background: linear-gradient(135deg, #0dcaf0 0%, #6edff6 100%); color: white;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="summary-number">
                                <?php
                                $diproses = array_filter($riwayat, function($r) {
                                    return $r['status'] == 'Diproses';
                                });
                                echo count($diproses);
                                ?>
                            </div>
                            <div>Diproses</div>
                        </div>
                        <i class="fas fa-cogs fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="summary-card" style="background: linear-gradient(135deg, #198754 0%, #4caf50 100%); color: white;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="summary-number">
                                <?php
                                $selesai = array_filter($riwayat, function($r) {
                                    return $r['status'] == 'Selesai';
                                });
                                echo count($selesai);
                                ?>
                            </div>
                            <div>Selesai</div>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-card">
            <h5 class="mb-4"><i class="fas fa-filter me-2"></i>Filter Riwayat</h5>
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" class="form-control" name="start_date" id="start_date" 
                           value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control" name="end_date" id="end_date" 
                           value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Periode</label>
                    <select class="form-select" name="periode" id="periodeSelect">
                        <option value="custom" <?php echo $periode == 'custom' ? 'selected' : ''; ?>>Kustom</option>
                        <option value="today" <?php echo $periode == 'today' ? 'selected' : ''; ?>>Hari Ini</option>
                        <option value="week" <?php echo $periode == 'week' ? 'selected' : ''; ?>>Minggu Ini</option>
                        <option value="month" <?php echo $periode == 'month' ? 'selected' : ''; ?>>Bulan Ini</option>
                        <option value="year" <?php echo $periode == 'year' ? 'selected' : ''; ?>>Tahun Ini</option>
                        <option value="all" <?php echo $periode == 'all' ? 'selected' : ''; ?>>Semua Data</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">Semua Status</option>
                        <option value="Menunggu" <?php echo $status_filter == 'Menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                        <option value="Diproses" <?php echo $status_filter == 'Diproses' ? 'selected' : ''; ?>>Diproses</option>
                        <option value="Selesai" <?php echo $status_filter == 'Selesai' ? 'selected' : ''; ?>>Selesai</option>
                        <option value="Ditolak" <?php echo $status_filter == 'Ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Kategori</label>
                    <select class="form-select" name="kategori">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($kategori_list as $kategori): ?>
                            <option value="<?php echo htmlspecialchars($kategori); ?>" <?php echo $kategori_filter == $kategori ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kategori); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Pencarian</label>
                    <input type="text" class="form-control" name="search" 
                           placeholder="Cari judul, deskripsi, atau nama pelapor..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-search me-1"></i> Terapkan Filter
                    </button>
                    <a href="riwayat.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Export Options -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-0">Data Riwayat Pengaduan</h5>
                <small class="text-muted">
                    <?php
                    if ($periode == 'all') {
                        echo 'Periode: Semua Data';
                    } else {
                        echo 'Periode: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date));
                    }
                    ?>
                    <?php if ($status_filter): ?> | Status: <?php echo $status_filter; ?><?php endif; ?>
                    <?php if ($kategori_filter): ?> | Kategori: <?php echo $kategori_filter; ?><?php endif; ?>
                </small>
            </div>
            
            <div class="d-flex gap-2">
                <a href="riwayat.php?export=excel&<?php echo http_build_query($_GET); ?>" 
                   class="btn export-btn">
                    <i class="fas fa-file-excel me-2"></i> Export Excel
                </a>
            </div>
        </div>

        <!-- Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tanggal</th>
                            <th>Pelapor</th>
                            <th>Judul</th>
                            <th>Kategori</th>
                            <th>Prioritas</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($riwayat)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <h5>Tidak ada data riwayat</h5>
                                    <p class="mb-0">Coba gunakan filter yang berbeda</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($riwayat as $index => $r): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo $index + 1; ?></td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($r['tanggal_kejadian'])); ?><br>
                                        <small class="text-muted"><?php echo date('H:i', strtotime($r['created_at'])); ?></small>
                                    </td>
                                    <td><?php echo $r['nama_user']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($r['judul']); ?></strong><br>
                                        <small class="text-muted"><?php echo substr($r['deskripsi'], 0, 50); ?>...</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php echo $r['kategori']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $priority_color = '';
                                        switch($r['prioritas']) {
                                            case 'Tinggi': $priority_color = 'danger'; break;
                                            case 'Sedang': $priority_color = 'warning'; break;
                                            case 'Rendah': $priority_color = 'success'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $priority_color; ?>">
                                            <?php echo $r['prioritas']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-status badge-<?php echo strtolower($r['status']); ?>">
                                            <?php echo $r['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="detail_pengaduan.php?id=<?php echo $r['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
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
        
        <!-- Pagination (optional) -->
        <?php if (count($riwayat) > 0): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1">Menampilkan <?php echo count($riwayat); ?> data</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi untuk mengatur tanggal berdasarkan periode yang dipilih
        function setDatesFromPeriode(periode) {
            const startInput = document.getElementById('start_date');
            const endInput = document.getElementById('end_date');
            const today = new Date();
            let startDate, endDate;

            function formatDate(date) {
                const y = date.getFullYear();
                const m = String(date.getMonth() + 1).padStart(2, '0');
                const d = String(date.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}`;
            }

            // Hilangkan atribut disabled (input selalu aktif)
            startInput.disabled = false;
            endInput.disabled = false;

            switch (periode) {
                case 'today':
                    startDate = endDate = today;
                    break;
                case 'week': {
                    // Minggu ini: Senin s/d hari ini
                    const day = today.getDay(); // 0 = Minggu, 1 = Senin, ..., 6 = Sabtu
                    const diffToMonday = (day === 0 ? 6 : day - 1); // mundur ke Senin
                    const monday = new Date(today);
                    monday.setDate(today.getDate() - diffToMonday);
                    startDate = monday;
                    endDate = today;
                    break;
                }
                case 'month':
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    endDate = today;
                    break;
                case 'year':
                    startDate = new Date(today.getFullYear(), 0, 1);
                    endDate = today;
                    break;
                case 'all':
                    // Kosongkan input tanggal
                    startInput.value = '';
                    endInput.value = '';
                    return; // langsung keluar, tidak set nilai
                default: // custom
                    return; // biarkan nilai apa adanya
            }

            startInput.value = formatDate(startDate);
            endInput.value = formatDate(endDate);
        }

        // Event listener untuk dropdown periode
        document.getElementById('periodeSelect').addEventListener('change', function(e) {
            setDatesFromPeriode(this.value);
        });

        // Jika user mengubah tanggal manual, set periode ke 'custom'
        document.getElementById('start_date').addEventListener('change', function() {
            document.getElementById('periodeSelect').value = 'custom';
        });
        document.getElementById('end_date').addEventListener('change', function() {
            document.getElementById('periodeSelect').value = 'custom';
        });

        // Saat halaman dimuat, pastikan tanggal sesuai dengan periode yang dipilih
        document.addEventListener('DOMContentLoaded', function() {
            const periodeSelect = document.getElementById('periodeSelect');
            setDatesFromPeriode(periodeSelect.value);
        });
    </script>
</body>
</html>