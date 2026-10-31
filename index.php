<?php
session_start();
require_once 'koneksi.php'; // pastikan koneksi mysqli tersedia sebagai $conn

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
$logo = 'logo.png';
$batikBg = 'assets/batik-pattern.jpg';
$bookshelf = 'bg.jpg';

// Ambil 4 rekomendasi terbaru dari DB
$rekom = [];
if (isset($conn) && $conn) {
    $sql = "SELECT b.id, b.title, b.author, b.cover_path, b.file_path, b.access_level,
                   b.publication_year, b.isbn, b.description, c.name AS category_name, b.created_at
            FROM books b
            LEFT JOIN categories c ON b.category_id = c.id
            ORDER BY b.created_at DESC
            LIMIT 4";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $rekom[] = $row;
        }
    }
}

// helper
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Perpustakaan Digital</title>

  <!-- Bootstrap 5 CSS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root{
      --accent: #b7853b; /* emas / batik feel */
      --dark: #072146;
      --nav-height: 64px; /* sesuaikan tinggi navbar */
    }

    html,body { height:100%; margin:0; }

    /* body padding supaya konten tidak tertutup navbar fixed */
    body {
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      padding-top: var(--nav-height);
      background: #f6f5f3;
    }

    /* Batik textured hero */
    .hero {
      background-color: var(--dark);
      color: #fff;
      position: relative;
      overflow: hidden;
      padding: 3.2rem 0; /* agak lebih pendek bila perlu */
      z-index: 1; /* pastikan navbar di atas hero */
    }
    .hero::before{
      content: "";
      position: absolute;
      inset: 0;
      background-image: url('<?=htmlspecialchars($batikBg)?>');
      background-size: cover;
      background-position: center;
      opacity: 0.15; /* subtle texture */
      filter: grayscale(20%);
      z-index: 0;
    }
    .hero .overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, rgba(7, 33, 70, 0.75), rgba(7,33,70,0.5));
      z-index: 1;
    }
    .hero .content {
      position: relative;
      z-index: 2;
    }

    /* bookshelf image on right */
    .hero .bookshelf {
      max-width: 420px;
      box-shadow: 0 10px 30px rgba(0,0,0,.4);
      border-radius: 8px;
      transform: translateY(-10px);
    }

    .brand-logo {
      height: 48px;
      object-fit: contain;
    }

    .btn-accent {
      background: var(--accent);
      color: #fff;
      border: none;
    }
    .btn-accent:hover {
      background-color: #e0a43d;
      color: #fff;
    }

    /* simple card hover */
    .book-card {
      transition: all .25s ease;
      position: relative;
      z-index: 2; /* pastikan kartu tampil di atas hero overlay */
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 8px 20px rgba(16,24,40,0.06);
    }
    .book-card:hover { transform: translateY(-6px); box-shadow: 0 12px 28px rgba(0,0,0,.12); }

    /* footer */
    footer { background:#071428; color:#bdbdbd; padding: 24px 0; margin-top:40px; }

    /* navbar fixed */
    .navbar.fixed-top {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: var(--nav-height);
      z-index: 1030;
    }

    /* responsive tweaks */
    @media (max-width: 768px) {
      .hero { padding: 2rem 0; }
      .hero .bookshelf { max-width: 220px; }
    }
  </style>
</head>
<body>

<!-- Top bar (fixed) -->
<nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: linear-gradient(90deg,#083056,#0f2b4a);">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <img src="<?=htmlspecialchars($logo)?>" alt="logo" class="brand-logo me-2">
      <div class="d-flex flex-column" style="line-height:1;">
        <strong style="font-size:1rem;color:var(--accent)">Digital</strong>
        <small style="font-size:0.7rem;color:#cfd8dc">Perpustakaan</small>
      </div>
    </a>

    <div class="ms-auto d-flex align-items-center">
      <!-- Search -->
      <form class="d-none d-md-flex me-3" action="list_books.php" method="get">
        <div class="input-group">
          <input name="q" type="search" class="form-control form-control-sm" placeholder="Cari judul, penulis, kategori..." aria-label="Search">
          <button class="btn btn-sm btn-outline-light" type="submit">Cari</button>
        </div>
      </form>

      <?php if (!$isLoggedIn): ?>
        <a href="login.php" class="btn btn-sm btn-outline-light me-2">Login</a>
        <a href="register.php" class="btn btn-sm btn-accent">Register</a>
      <?php else: ?>
        <div class="dropdown">
          <a class="btn btn-sm btn-outline-light dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false">
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

<!-- HERO -->
<header class="hero py-5">
  <div class="overlay"></div>
  <div class="container content">
    <div class="row align-items-center">
      <div class="col-md-7">
        <h1 class="display-5 fw-bold">Perpustakaan Digital</h1>
        <p class="lead" style="color: #ffffff;">Akses koleksi digital: e-book, jurnal, dan referensi akademik kapan saja. Ringan, aman, dan cepat untuk mahasiswa & dosen.</p>

        <div class="mt-4 d-flex gap-2">
          <a href="list_books.php" class="btn btn-accent btn-lg">Jelajahi Koleksi</a>
          <a href="about.php" class="btn btn-outline-light btn-lg">Tentang</a>
        </div>

        <div class="mt-4 small" style="color: #fff;">
          <span class="me-3"><strong>✓</strong> PDF viewer & download</span>
          <span class="me-3"><strong>✓</strong> Kontrol akses: publik / terdaftar</span>
          <span><strong>✓</strong> Admin uploader</span>
        </div>
      </div>

      <div class="col-md-5 text-center d-none d-md-block">
        <img src="<?=htmlspecialchars($bookshelf)?>" alt="rak buku" class="bookshelf img-fluid shadow-sm">
      </div>
    </div>
  </div>
</header>

<!-- CONTENT: rekomendasi diambil dari DB -->
<main class="container my-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Rekomendasi</h4>
    <a href="list_books.php" class="small">Lihat semua &raquo;</a>
  </div>

  <div class="row g-3">
    <?php if (empty($rekom)): ?>
      <div class="col-12">
        <div class="alert alert-info">Belum ada rekomendasi saat ini.</div>
      </div>
    <?php else: foreach ($rekom as $idx => $b): 
        // fallback cover
        $cover = (!empty($b['cover_path']) && file_exists(__DIR__ . '/' . $b['cover_path'])) ? $b['cover_path'] : 'https://via.placeholder.com/300x420?text=No+Cover';
        $isRestricted = ($b['access_level'] ?? 'public') === 'registered';
    ?>
      <div class="col-6 col-md-3">
        <div class="card book-card h-100">
          <img src="<?= e($cover) ?>" class="card-img-top" alt="cover">
          <div class="card-body d-flex flex-column">
            <h6 class="card-title"><?= e($b['title']) ?></h6>
            <p class="card-text text-muted small mb-2">Penulis: <?= e($b['author'] ?: '-') ?></p>
            <p class="card-text text-truncate mb-2" style="flex:1"><?= e($b['description'] ? strip_tags($b['description']) : '') ?></p>
            <p class="small text-muted mb-2">Tahun: <?= e($b['publication_year'] ?: '-') ?> · ISBN: <?= e($b['isbn'] ?: '-') ?></p>

            <?php if ($isRestricted && !$isLoggedIn): ?>
              <div class="alert alert-warning text-center mt-2 p-2 small" style="font-size: 0.8rem;">
                <span class="me-2">🔒 Hanya untuk pengguna terdaftar</span>
                <div class="mt-2"><a href="login.php" class="text-decoration-none text-warning">Login untuk melihat detail & mengunduh</a></div>
              </div>
            <?php else: ?>
              <div class="d-flex gap-2 mt-2">
                <a href="book_detail.php?id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                <?php if (!empty($b['file_path'])): ?>
                  <a href="download.php?id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-accent">Unduh</a>
                <?php else: ?>
                  <button class="btn btn-sm btn-outline-secondary" disabled>Tidak ada file</button>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</main>

<!-- Footer -->
<footer>
  <div class="container d-flex justify-content-between align-items-center">
    <div>
      <strong>Perpustakaan Digital</strong><br>
    </div>
    <div class="text-end small">
      <div>Contact: nugrahawandaeka@gmail.com</div>
      <div class="mt-1">© <?=date('Y')?></div>
    </div>
  </div>
</footer>

<!-- Bootstrap JS (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
