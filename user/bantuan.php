<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
$db = new Database();

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['nama'];
$user_role = $_SESSION['role'];


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




// Ambil data kontak dari tabel settings
$kontak_default = [
    'telepon' => '(021) 1234-5678',
    'email' => 'helpdesk@assetcare.com',
    'alamat' => 'Gedung Utama Lt. 3, Ruang IT Support',
    'jam_kerja' => 'Senin-Jumat, 08:00-17:00 WIB',
    'petugas1' => 'Budi Santoso (Ext. 123)',
    'petugas2' => 'Siti Rahayu (Ext. 124)'
];

$kontak = [];
$result = $db->query("SELECT setting_key, setting_value FROM settings");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $kontak[$row['setting_key']] = $row['setting_value'];
    }
} else {
    // Jika belum ada, gunakan default
    $kontak = $kontak_default;
}

// Ekstrak variabel
$telepon = $kontak['telepon'] ?? $kontak_default['telepon'];
$email = $kontak['email'] ?? $kontak_default['email'];
$alamat = $kontak['alamat'] ?? $kontak_default['alamat'];
$jam_kerja = $kontak['jam_kerja'] ?? $kontak_default['jam_kerja'];
$petugas1 = $kontak['petugas1'] ?? $kontak_default['petugas1'];
$petugas2 = $kontak['petugas2'] ?? $kontak_default['petugas2'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bantuan - AssetCare</title>
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
            line-height: 1.5;
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            box-shadow: 0 4px 20px rgba(9, 99, 126, 0.15);
            padding: 12px 0;
        }
        
        .navbar-custom .navbar-brand {
            color: white;
            font-weight: 700;
            font-size: 1.6rem;
        }
        
        .navbar-custom .nav-link {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.3s;
            margin: 0 3px;
            font-size: 0.95rem;
        }
        
        .navbar-custom .nav-link:hover,
        .navbar-custom .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }
        
        .profile-avatar {
            width: 42px;
            height: 42px;
            background: white;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .hero-section {
            background: linear-gradient(rgba(9, 99, 126, 0.85), rgba(8, 131, 149, 0.85)),
                        url('https://images.unsplash.com/photo-1486312338219-ce68d2c6f44d?ixlib=rb-1.2.1&auto=format&fit=crop&w=1600&q=80');
            background-size: cover;
            background-position: center;
            border-radius: 16px;
            padding: 50px 30px;
            margin: 15px 0 30px;
            color: white;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .hero-section h1 {
            font-weight: 700;
            margin-bottom: 12px;
            font-size: 2rem;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);
        }
        
        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            height: 100%;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .card-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 18px 20px;
            border-bottom: none;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            color: white;
            padding: 10px 22px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(9, 99, 126, 0.25);
            color: white;
        }
        
        .accordion-button:not(.collapsed) {
            background-color: var(--light);
            color: var(--primary);
            font-weight: 600;
        }
        
        .accordion-button:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 0.2rem rgba(122, 178, 178, 0.2);
        }
        
        .contact-card {
            border-left: 4px solid var(--primary);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            background: white;
            transition: all 0.3s;
        }
        
        .contact-card:hover {
            background-color: var(--light);
            transform: translateX(5px);
        }
        
        .step-card {
            text-align: center;
            padding: 25px 15px;
            border-radius: 12px;
            background: white;
            height: 100%;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #eee;
            transition: all 0.3s;
        }
        
        .step-card:hover {
            border-color: var(--accent);
            transform: translateY(-3px);
        }
        
        .step-number {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            color: white;
            font-weight: bold;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }
        
        .faq-item {
            margin-bottom: 8px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #eee;
        }
        
        .contact-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .contact-info h6 {
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--primary);
        }
        
        .contact-info p {
            margin-bottom: 4px;
            font-size: 0.95rem;
        }
        
        .contact-info small {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 35px 20px;
                margin: 10px 0 25px;
                border-radius: 12px;
            }
            
            .hero-section h1 {
                font-size: 1.6rem;
            }
            
            .hero-section .lead {
                font-size: 1rem;
            }
            
            .navbar-custom .nav-link {
                margin: 4px 0;
                padding: 10px 15px;
                text-align: left;
            }
            
            .card-header-custom {
                padding: 15px 18px;
            }
            
            .step-card {
                padding: 20px 12px;
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 576px) {
            .navbar-custom .navbar-brand {
                font-size: 1.4rem;
            }
            
            .hero-section {
                padding: 30px 15px;
            }
            
            .hero-section h1 {
                font-size: 1.4rem;
            }
            
            .btn-primary-custom {
                padding: 8px 18px;
                font-size: 0.9rem;
            }
        }
        
        .back-to-home {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            box-shadow: 0 4px 12px rgba(9, 99, 126, 0.3);
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .back-to-home:hover {
            transform: scale(1.1);
            color: white;
        }
        
        .section-title {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light);
        }
        
        .compact-list {
            line-height: 1.4;
            margin-bottom: 8px;
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
        
        <!-- Hero Section -->
        <div class="hero-section animate__animated animate__fadeIn">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-6">Pusat Bantuan AssetCare</h1>
                    <p class="lead mb-3">Temukan panduan penggunaan sistem, jawaban untuk pertanyaan umum, dan informasi kontak untuk bantuan lebih lanjut.</p>
                    <a href="index.php" class="btn btn-primary-custom">
                        <i class="fas fa-arrow-left me-2"></i>Kembali ke Dashboard
                    </a>
                </div>
                <div class="col-lg-4 text-center d-none d-lg-block">
                    <i class="fas fa-question-circle fa-8x opacity-25"></i>
                </div>
            </div>
        </div>

        <!-- Panduan Cepat -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card card-custom animate__animated animate__fadeInUp">
                    <div class="card-header card-header-custom">
                        <h3 class="card-title mb-0">
                            <i class="fas fa-compass me-2"></i>Panduan Cepat Menggunakan AssetCare
                        </h3>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <div class="col-md-6 col-lg-3">
                                <div class="step-card">
                                    <div class="step-number">1</div>
                                    <h5 class="fs-6 fw-bold">Buat Pengaduan</h5>
                                    <p class="text-muted small">Klik "Buat Pengaduan" dan isi formulir dengan detail kerusakan atau masalah.</p>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="step-card">
                                    <div class="step-number">2</div>
                                    <h5 class="fs-6 fw-bold">Lampirkan Bukti</h5>
                                    <p class="text-muted small">Unggah foto pendukung (maks 2MB) untuk membantu tim memahami masalah.</p>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="step-card">
                                    <div class="step-number">3</div>
                                    <h5 class="fs-6 fw-bold">Pantau Status</h5>
                                    <p class="text-muted small">Lacak perkembangan pengaduan melalui dashboard atau halaman riwayat.</p>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="step-card">
                                    <div class="step-number">4</div>
                                    <h5 class="fs-6 fw-bold">Konfirmasi Selesai</h5>
                                    <p class="text-muted small">Setelah status "Selesai", evaluasi hasil perbaikan yang telah dilakukan.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kontak Bantuan -->
        <div class="row mb-4">
            <div class="col-lg-4 mb-4 mb-lg-0">
                <div class="card card-custom animate__animated animate__fadeInUp">
                    <div class="card-header card-header-custom">
                        <h3 class="card-title mb-0">
                            <i class="fas fa-headset me-2"></i>Kontak Bantuan
                        </h3>
                    </div>
                    <div class="card-body p-3">
                        <div class="contact-card">
                            <div class="d-flex align-items-start">
                                <div class="contact-icon">
                                    <i class="fas fa-phone-alt"></i>
                                </div>
                                <div class="contact-info">
                                    <h6>Telepon</h6>
                                    <p class="mb-1"><?php echo htmlspecialchars($telepon); ?></p>
                                    <small><?php echo htmlspecialchars($jam_kerja); ?></small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="contact-card">
                            <div class="d-flex align-items-start">
                                <div class="contact-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="contact-info">
                                    <h6>Email</h6>
                                    <p class="mb-1"><?php echo htmlspecialchars($email); ?></p>
                                    <small>Respon dalam 1-2 jam kerja</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="contact-card">
                            <div class="d-flex align-items-start">
                                <div class="contact-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="contact-info">
                                    <h6>Lokasi Kantor</h6>
                                    <p class="mb-1"><?php echo htmlspecialchars($alamat); ?></p>
                                    <small>Ruang IT Support (08:00-16:00)</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="contact-card">
                            <div class="d-flex align-items-start">
                                <div class="contact-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="contact-info">
                                    <h6>Petugas Teknis</h6>
                                    <p class="mb-1"><?php echo htmlspecialchars($petugas1); ?></p>
                                    <p class="mb-1"><?php echo htmlspecialchars($petugas2); ?></p>
                                    <small>Konsultasi teknis langsung</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3 p-3">
                            <h6 class="mb-2"><i class="fas fa-lightbulb me-2"></i>Tips Cepat</h6>
                            <ul class="mb-0 ps-3 small compact-list">
                                <li>Sertakan nomor pengaduan saat menghubungi</li>
                                <li>Foto kerusakan dari berbagai sudut</li>
                                <li>Periksa FAQ sebelum menghubungi kami</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- FAQ -->
            <div class="col-lg-8">
                <div class="card card-custom animate__animated animate__fadeInUp">
                    <div class="card-header card-header-custom">
                        <h3 class="card-title mb-0">
                            <i class="fas fa-question-circle me-2"></i>Pertanyaan Umum (FAQ)
                        </h3>
                    </div>
                    <div class="card-body p-3">
                        <div class="accordion" id="faqAccordion">
                            <!-- FAQ 1 -->
                            <div class="accordion-item faq-item">
                                <h2 class="accordion-header" id="faqHeading1">
                                    <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse1">
                                        <i class="fas fa-file-alt me-2"></i> Cara membuat pengaduan baru?
                                    </button>
                                </h2>
                                <div id="faqCollapse1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        <ol class="mb-0 compact-list">
                                            <li>Klik tombol <strong>"Buat Pengaduan"</strong></li>
                                            <li>Isi semua field yang diperlukan</li>
                                            <li>Upload foto pendukung (maks 2MB)</li>
                                            <li>Klik <strong>"Simpan Pengaduan"</strong></li>
                                            <li>Status: <span class="badge bg-warning">Menunggu</span></li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- FAQ 2 -->
                            <div class="accordion-item faq-item">
                                <h2 class="accordion-header" id="faqHeading2">
                                    <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse2">
                                        <i class="fas fa-edit me-2"></i> Bisa edit/hapus pengaduan?
                                    </button>
                                </h2>
                                <div id="faqCollapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        <p>Ya, hanya jika status <strong>"Menunggu"</strong>. Setelah diproses admin, tidak bisa diubah.</p>
                                        <p class="mb-0 compact-list">Edit: <span class="badge bg-warning"><i class="fas fa-edit"></i></span> | Hapus: <span class="badge bg-danger"><i class="fas fa-trash"></i></span></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- FAQ 3 -->
                            <div class="accordion-item faq-item">
                                <h2 class="accordion-header" id="faqHeading3">
                                    <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse3">
                                        <i class="fas fa-history me-2"></i> Arti status pengaduan?
                                    </button>
                                </h2>
                                <div id="faqCollapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        <ul class="mb-0 compact-list">
                                            <li><span class="badge bg-warning">Menunggu</span>: Menunggu tinjauan admin</li>
                                            <li><span class="badge bg-info">Diproses</span>: Ditangani tim teknis</li>
                                            <li><span class="badge bg-success">Selesai</span>: Selesai ditangani</li>
                                            <li><span class="badge bg-danger">Ditolak</span>: Ditolak dengan alasan</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- FAQ 4 -->
                            <div class="accordion-item faq-item">
                                <h2 class="accordion-header" id="faqHeading4">
                                    <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse4">
                                        <i class="fas fa-clock me-2"></i> Lama penanganan pengaduan?
                                    </button>
                                </h2>
                                <div id="faqCollapse4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        <p class="mb-2">Bergantung pada:</p>
                                        <ul class="mb-0 compact-list">
                                            <li><strong>Prioritas</strong>: Tinggi (1-2 hari), Sedang (3-5), Rendah (5-7)</li>
                                            <li><strong>Kompleksitas</strong>: Masalah sederhana lebih cepat</li>
                                            <li><strong>Waktu kerja</strong>: Senin-Jumat, 08:00-17:00</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- FAQ 5 -->
                            <div class="accordion-item faq-item">
                                <h2 class="accordion-header" id="faqHeading5">
                                    <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse5">
                                        <i class="fas fa-images me-2"></i> Syarat file lampiran?
                                    </button>
                                </h2>
                                <div id="faqCollapse5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        <ul class="mb-0 compact-list">
                                            <li><strong>Format</strong>: JPG, PNG, atau GIF</li>
                                            <li><strong>Ukuran</strong>: Maksimal 2MB</li>
                                            <li><strong>Jumlah</strong>: 1 file per pengaduan</li>
                                            <li><strong>Tips</strong>: Foto dengan pencahayaan baik</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- FAQ 6 -->
                            <div class="accordion-item faq-item">
                                <h2 class="accordion-header" id="faqHeading6">
                                    <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse6">
                                        <i class="fas fa-ban me-2"></i> Alasan pengaduan ditolak?
                                    </button>
                                </h2>
                                <div id="faqCollapse6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        <ul class="mb-0 compact-list">
                                            <li>Informasi tidak lengkap</li>
                                            <li>Masalah di luar ruang lingkup</li>
                                            <li>Kerusakan karena kelalaian</li>
                                            <li>Barang bukan milik kantor</li>
                                            <li>Pengaduan duplikat</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tombol Kembali ke Home -->
    <a href="index.php" class="back-to-home animate__animated animate__fadeInUp" title="Kembali ke Dashboard">
        <i class="fas fa-home"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animasi saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            // Animasi untuk accordion FAQ
            const faqButtons = document.querySelectorAll('.accordion-button');
            faqButtons.forEach(button => {
                button.addEventListener('click', function() {
                    this.classList.add('animate__animated', 'animate__pulse');
                    setTimeout(() => {
                        this.classList.remove('animate__animated', 'animate__pulse');
                    }, 300);
                });
            });
            
            // Tampilkan tanggal dan waktu saat ini di konsol untuk debugging
            console.log('Halaman bantuan diakses pada: ' + new Date().toLocaleString());
        });
        
        // Responsif untuk accordion di mobile
        if (window.innerWidth < 768) {
            document.querySelectorAll('.accordion-button').forEach(button => {
                button.style.fontSize = '0.9rem';
                button.style.padding = '12px 15px';
            });
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