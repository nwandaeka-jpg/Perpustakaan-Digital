<?php
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
$logo = 'logo.png';
$batikBg = 'assets/batik-pattern.jpg';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Tentang Kami - Perpustakaan Digital</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root {
      --accent: #b7853b;
      --dark: #072146;
    }

    body {
      background-color: #f9fafc;
    }

    header.hero {
      background-color: var(--dark);
      color: #fff;
      position: relative;
      padding: 80px 0;
    }
    header.hero::before {
      content: "";
      position: absolute;
      inset: 0;
      background-image: url('<?=htmlspecialchars($batikBg)?>');
      background-size: cover;
      background-position: center;
      opacity: 0.15;
      z-index: 0;
    }
    header.hero .overlay {
      position: absolute;
      inset: 0;
      background: rgba(7,33,70,0.6);
      z-index: 1;
    }
    header.hero .content {
      position: relative;
      z-index: 2;
    }

    .brand-logo { height: 48px; object-fit: contain; }

    .btn-accent {
      background: var(--accent);
      color: #fff;
      border: none;
    }
    .btn-accent:hover { opacity: .9; }

    footer {
      background:#071428;
      color:#bdbdbd;
      padding: 24px 0;
      margin-top: 60px;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(90deg,#083056,#0f2b4a);">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="<?=htmlspecialchars($logo)?>" alt="logo" class="brand-logo me-2">
      <div class="d-flex flex-column" style="line-height:1;">
        <strong style="font-size:1rem;color:var(--accent)">Digital</strong>
        <small style="font-size:0.7rem;color:#cfd8dc">Perpustakaan</small>
      </div>
    </a>

    <div class="ms-auto d-flex align-items-center">
      <?php if (!$isLoggedIn): ?>
        <a href="login.php" class="btn btn-sm btn-outline-light me-2">Login</a>
        <a href="register.php" class="btn btn-sm btn-accent">Register</a>
      <?php else: ?>
        <div class="dropdown">
          <a class="btn btn-sm btn-outline-light dropdown-toggle" href="#" data-bs-toggle="dropdown">
            Halo, <?=htmlspecialchars($userName)?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="profile.php">Profil</a></li>
            <li><a class="dropdown-item" href="my_downloads.php">Unduhan Saya</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- Hero Section -->
<header class="hero">
  <div class="overlay"></div>
  <div class="container content text-center">
    <h1 class="fw-bold display-6">Tentang Perpustakaan Digital</h1>
    <p class="lead text-light mt-3">
      Platform pembelajaran dan referensi modern untuk mendukung literasi digital di era teknologi.
    </p>
  </div>
</header>

<!-- Content -->
<main class="container my-5">
  <div class="row justify-content-center">
    <div class="col-md-10">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <h4 class="fw-bold mb-3 text-accent">Visi & Misi</h4>
          <p><strong>Visi:</strong> Menjadi pusat referensi digital yang mudah diakses, berkualitas, dan mendukung pengembangan ilmu pengetahuan bagi seluruh civitas akademika.</p>
          <p><strong>Misi:</strong></p>
          <ul>
            <li>Menyediakan koleksi e-book dan jurnal digital secara terorganisir.</li>
            <li>Mempermudah pengguna dalam mengakses, membaca, dan mengunduh bahan bacaan.</li>
            <li>Mendorong budaya literasi digital di lingkungan kampus.</li>
          </ul>

          <hr class="my-4">

          <h4 class="fw-bold mb-3 text-accent">Tentang Sistem</h4>
          <p>
            Perpustakaan Digital ini dikembangkan menggunakan kombinasi <strong>PHP Native</strong> dan <strong>MySQL</strong>,
            dengan desain modern dan responsif. Sistem ini memiliki dua level pengguna:
          </p>
          <ul>
            <li><strong>Admin:</strong> Mengelola data buku digital, kategori, dan akun pengguna.</li>
            <li><strong>User:</strong> Menjelajahi, membaca, dan mengunduh koleksi e-book yang tersedia.</li>
          </ul>

          <div class="alert alert-info mt-4">
            <strong>Fun Fact:</strong> Desain halaman ini terinspirasi oleh motif <em>batik klasik</em> sebagai bentuk cinta terhadap budaya Indonesia.
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Footer -->
<footer>
  <div class="container d-flex justify-content-between align-items-center">
    <div>
      <strong>Perpustakaan Digital</strong><br>
      <small>Mendukung literasi digital kampus</small>
    </div>
    <div class="text-end small">
      <div>Contact: nugrahawandaeka@gmail.com</div>
      <div class="mt-1">© <?=date('Y')?> Perpustakaan Digital</div>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
