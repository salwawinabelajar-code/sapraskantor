<?php
require_once '../config/database.php';
$db = new Database();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $db->escape_string($_POST['email']);
    $password = $_POST['password'];
    
    $errors = [];
    
    if (empty($email) || empty($password)) {
        $errors[] = "Email dan password harus diisi";
    }
    
    if (empty($errors)) {
        $query = "SELECT * FROM users WHERE email='$email'";
        $result = $db->conn->query($query);
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                if ($user['role'] == 'admin') {
                    redirect('../admin/index.php');
                } else {
                    redirect('../user/index.php');
                }
            } else {
                $errors[] = "Password salah";
            }
        } else {
            $errors[] = "Email tidak ditemukan";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login | AssetCare</title>
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
            max-width: 420px;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(9, 99, 126, 0.15);
        }

        .logo {
            text-align: center;
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #666;
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

        .register-link {
            text-align: center;
            margin-top: 20px;
        }

        .register-link a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="logo">AssetCare</div>
    <div class="subtitle">
        Sistem Sarana Prasarana Kantor<br>
        Silakan login untuk melanjutkan
    </div>

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
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-4">
            <label class="form-label">Password</label>
            <div class="input-group password-group">
                <input type="password" id="password" name="password" class="form-control" required>
                <span class="input-group-text" onclick="togglePassword('password', this)">
                    <i class="fa-regular fa-eye"></i>
                </span>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            Login
        </button>
    </form>

    <div class="register-link">
        Belum punya akun? <a href="register.php">Daftar di sini</a>
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
