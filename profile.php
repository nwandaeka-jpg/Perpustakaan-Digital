<?php
session_start();
require_once 'koneksi.php'; // koneksi mysqli = $conn

// pastikan login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$logo = 'logo.png';

// ambil data user
$stmt = $conn->prepare("SELECT name, email, created_at FROM users WHERE id = ?");
if (!$stmt) {
    die("Query gagal disiapkan: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();

if (function_exists('mysqli_stmt_get_result')) {
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
} else {
    $stmt->bind_result($name, $email, $created_at);
    if ($stmt->fetch()) {
        $user = ['name' => $name, 'email' => $email, 'created_at' => $created_at];
    } else {
        $user = null;
    }
}
$stmt->close();

if (!$user) {
    die("Data pengguna tidak ditemukan.");
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Profil - Perpustakaan Digital</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --accent: #b7853b;
      --dark: #072146;
    }

    html, body {
      height: 100%;
      margin: 0;
      display: flex;
      flex-direction: column;
      background: #f8f9fa;
    }

    main {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 40px 15px;
    }

    .profile-card {
      width: 100%;
      max-width: 540px;
      border-radius: 12px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, .08);
      overflow: hidden;
      background: #fff;
    }

    .profile-header {
      background: var(--dark);
      color: #fff;
      padding: 20px;
      text-align: center;
    }

    .profile-body {
      padding: 24px;
    }

    .btn-accent {
      background: var(--accent);
      color: #fff;
      border: none;
    }
    .btn-accent:hover {
      background: #e0a43d;
      color: #fff;
    }

    .brand-logo {
      height: 40px;
      object-fit: contain;
    }

    .navbar {
      background: linear-gradient(90deg, #083056, #0f2b4a);
    }

    footer {
      background: #071428;
      color: #bdbdbd;
      text-align: center;
      padding: 15px 0;
      margin-top: auto;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="<?= htmlspecialchars($logo) ?>" alt="logo" class="brand-logo me-2">
      <div class="d-flex flex-column" style="line-height:1;">
        <strong style="font-size:1rem;color:var(--accent)">Digital</strong>
        <small style="font-size:0.7rem;color:#cfd8dc">Perpustakaan</small>
      </div>
    </a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <a href="my_downloads.php" class="btn btn-sm btn-outline-light">Unduhan Saya</a>
      <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
    </div>
  </div>
</nav>

<!-- Main Content -->
<main>
  <div class="card profile-card">
    <div class="profile-header">
      <h4 class="mb-0">Profil Pengguna</h4>
    </div>
    <div class="profile-body">
      <div class="mb-3">
        <label class="form-label fw-semibold">Nama Lengkap</label>
        <div><?= htmlspecialchars($user['name']) ?></div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Email</label>
        <div><?= htmlspecialchars($user['email']) ?></div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Tanggal Bergabung</label>
        <div><?= date('d F Y', strtotime($user['created_at'])) ?></div>
      </div>

      <div class="text-center mt-4">
        <a href="index.php" class="btn btn-accent">Kembali ke Beranda</a>
      </div>
    </div>
  </div>
</main>

<!-- Footer -->
<footer>
  © <?= date('Y') ?> Perpustakaan Digital
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
