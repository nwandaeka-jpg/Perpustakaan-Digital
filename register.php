<?php
require_once 'koneksi.php';
session_start();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    if ($password !== $confirm) {
        $message = "❌ Password dan konfirmasi tidak sama.";
    } else {
        // Cek apakah email sudah terdaftar
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $message = "⚠️ Email sudah terdaftar. Silakan login.";
        } else {
            // Hash password dan simpan user baru
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
            $stmt->bind_param("sss", $name, $email, $hashed);

            if ($stmt->execute()) {
                $message = "✅ Registrasi berhasil! Silakan login.";
                header("Refresh:2; url=login.php"); // Redirect setelah 2 detik
            } else {
                $message = "❌ Terjadi kesalahan saat menyimpan data.";
            }
        }
    }
}
?>

<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Register - Perpustakaan Digital</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: url('assets/batik-pattern.jpg') center/cover fixed;
      backdrop-filter: blur(3px);
      min-height: 100vh;
    }
    .register-card {
      max-width: 440px;
      margin: 80px auto;
      background: rgba(255, 255, 255, 0.95);
      border-radius: 12px;
      padding: 35px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    .btn-accent {
      background-color: #b7853b;
      color: #fff;
      border: none;
      transition: all .3s ease;
    }
    .btn-accent:hover {
      background-color: #dba74c;
      transform: scale(1.03);
      box-shadow: 0 0 15px rgba(219,167,76,0.4);
    }
  </style>
</head>
<body>

<div class="register-card">
  <h4 class="text-center mb-3">Daftar Akun Baru</h4>

  <?php if ($message): ?>
    <div class="alert alert-info py-2 text-center"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <div class="mb-3">
      <label class="form-label fw-semibold">Nama Lengkap</label>
      <input type="text" name="name" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold">Email</label>
      <input type="email" name="email" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold">Password</label>
      <input type="password" name="password" class="form-control" required minlength="5">
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold">Konfirmasi Password</label>
      <input type="password" name="confirm" class="form-control" required minlength="5">
    </div>

    <button type="submit" class="btn btn-accent w-100 py-2">Daftar</button>
  </form>

  <p class="text-center mt-3 small">
    Sudah punya akun?
    <a href="login.php" class="text-decoration-none text-primary">Login di sini</a>
  </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
