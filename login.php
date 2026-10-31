<?php
session_start();
require 'koneksi.php'; // pastikan $conn (mysqli) terdefinisi

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = "Email dan password wajib diisi.";
    } else {
        // Ambil user berdasarkan email
        $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
        if (!$stmt) {
            $error = "Terjadi kesalahan (DB).";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($user) {
                $stored = $user['password'];

                // 1) Cek hash modern
                if (password_verify($password, $stored)) {
                    $login_ok = true;
                } 
                // 2) Fallback: cek MD5 legacy (panjang 32, pola hex)
                elseif (strlen($stored) === 32 && md5($password) === $stored) {
                    $login_ok = true;
                    // upgrade ke password_hash() — lakukan update ke DB
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $up = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                    if ($up) {
                        $up->bind_param("si", $newHash, $user['id']);
                        $up->execute();
                        $up->close();
                    }
                } else {
                    $login_ok = false;
                }

                if (!empty($login_ok)) {
                    // sukses login: set session dan arahkan menurut role
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];

                    if ($user['role'] === 'admin') {
                        header("Location: admin_dashboard.php");
                        exit;
                    } else {
                        header("Location: index.php");
                        exit;
                    }
                } else {
                    $error = "Email atau password salah!";
                }
            } else {
                $error = "Email atau password salah!";
            }
        }
    }
}

$batikBg = 'assets/batik-pattern.jpg';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login - Perpustakaan Digital</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --accent: #b7853b;
      --dark: #072146;
    }
    body {
      background-color: #f7f7f7;
      background-image: url('<?= htmlspecialchars($batikBg) ?>');
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      color: #333;
    }
    .login-card {
      max-width: 400px;
      margin: 80px auto;
      background: rgba(255,255,255,0.92);
      border-radius: 16px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.2);
      padding: 2rem;
    }
    .brand { text-align: center; margin-bottom: 1.5rem; }
    .brand img { height: 60px; margin-bottom: 0.5rem; }
    .btn-accent { background: var(--accent); color: #fff; border: none; }
    .btn-accent:hover { background: #a3762f; }
    a { color: var(--accent); text-decoration: none; }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>

<div class="login-card">
  <div class="brand">
    <img src="logo.png" alt="Logo">
    <h5 class="fw-bold" style="color: var(--dark)">Perpustakaan Digital</h5>
    <small class="text-muted">Selamat datang kembali</small>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" required placeholder="Masukkan email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required placeholder="Masukkan password">
    </div>

    <button type="submit" class="btn btn-accent w-100 py-2">Masuk</button>

    <div class="text-center mt-3 small">
      Belum punya akun? <a href="register.php">Daftar sekarang</a>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
