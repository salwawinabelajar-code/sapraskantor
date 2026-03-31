<?php
require_once '../config/database.php';
$db = new Database();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $db->escape_string($_POST['nama']);
    $email = $db->escape_string($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    if (empty($nama)) $errors[] = "Nama harus diisi";
    if (empty($email)) $errors[] = "Email harus diisi";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Format email tidak valid";
    if (empty($password)) $errors[] = "Password harus diisi";
    if ($password != $confirm_password) $errors[] = "Password tidak cocok";
    
    // Cek email sudah terdaftar
    $check = $db->conn->query("SELECT id FROM users WHERE email='$email'");
    if ($check->num_rows > 0) $errors[] = "Email sudah terdaftar";
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO users (nama, email, password) VALUES ('$nama', '$email', '$hashed_password')";
        
        if ($db->conn->query($query)) {
            $_SESSION['success'] = "Registrasi berhasil! Silakan login.";
            redirect('../auth/login.php');
        } else {
            $errors[] = "Registrasi gagal: " . $db->conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Register | AssetCare</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        :root {
            --primary: #09637E;
            --secondary: #088395;
            --accent: #7AB2B2;
            --light: #EBF4F6;
        }

        body {
            background: linear-gradient(135deg, var(--light), #ffffff);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
        }

        .auth-card {
            background: #fff;
            width: 100%;
            max-width: 450px;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(9, 99, 126, 0.15);
        }

        .logo {
            text-align: center;
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 30px;
        }

        .form-label {
            font-weight: 500;
        }

        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 .2rem rgba(122, 178, 178, 0.3);
        }

        .password-group .form-control {
            border-right: none;
        }

        .password-group .input-group-text {
            background: #fff;
            border-left: none;
            cursor: pointer;
            color: var(--secondary);
            padding: 0 15px;
        }

        .password-group .input-group-text:hover {
            color: var(--primary);
        }

        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 12px;
            font-weight: 600;
            border-radius: 10px;
        }

        .btn-primary:hover {
            background: var(--secondary);
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
        }

        .login-link a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="logo">AssetCare</div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="nama" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Password</label>
            <div class="input-group password-group">
                <input type="password" id="password" name="password" class="form-control" required>
                <span class="input-group-text" onclick="togglePassword('password', this)">
                    <i class="fa-regular fa-eye"></i>
                </span>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Konfirmasi Password</label>
            <div class="input-group password-group">
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                <span class="input-group-text" onclick="togglePassword('confirm_password', this)">
                    <i class="fa-regular fa-eye"></i>
                </span>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            Register sebagai Pegawai
        </button>
    </form>

    <div class="login-link">
        Sudah punya akun? <a href="login.php">Login di sini</a>
    </div>
</div>

<script>
function togglePassword(id, el) {
    const input = document.getElementById(id);
    const icon = el.querySelector('i');

    if (input.type === "password") {
        input.type = "text";
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = "password";
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
