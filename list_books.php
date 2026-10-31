<?php
session_start();
require_once 'koneksi.php'; // pastikan $conn tersedia (mysqli)

if (!isset($conn) || !$conn) {
    die("Koneksi database belum tersedia.");
}

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;

$logo = 'logo.png'; // pastikan file ini ada di root project

$sql = "SELECT b.id, b.title, b.author, b.cover_path, b.file_path, b.access_level, b.created_at,
               b.publication_year, b.isbn, c.name AS category_name
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.id
        ORDER BY b.title ASC";
$result = $conn->query($sql);
if ($result === false) {
    die("Query gagal: " . $conn->error);
}

function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Koleksi Buku | Perpustakaan Digital</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root{
      --blue-dark: #083056;
      --blue-dark-2: #0f2b4a;
      --accent-gold: #b7853b;
      --text-dark: #222;
    }

    html,body { height:100%; margin:0; font-family: "Poppins", sans-serif; }

    body {
      display:flex;
      flex-direction:column;
      min-height:100vh;
      background: #f7f6f4;
      color: var(--text-dark);
    }

    /* NAVBAR - biru seperti permintaan */
    .navbar {
      background: linear-gradient(90deg, var(--blue-dark), var(--blue-dark-2));
      padding: .45rem 1rem; /* lebih ramping */
      box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    }
    .navbar .brand-wrap { display:flex; align-items:center; gap:.5rem; }
    .brand-logo { height:34px; width:auto; object-fit:contain; display:block; }
    .navbar .navbar-brand { color:#fff !important; font-weight:700; margin:0; }
    .nav-right .btn { margin-left:.45rem; }

    /* HERO title banner (semi-transparent supaya terbaca) */
    .hero-bg {
      background-image: url('https://images.unsplash.com/photo-1524995997946-a1c2e315a42f?auto=format&fit=crop&w=1920&q=80');
      background-size: cover;
      background-position: center;
      padding: 40px 0 32px;
      position: relative;
    }
    .hero-overlay {
      position:absolute; inset:0; background: rgba(8,48,86,0.28); pointer-events:none;
    }
    .hero-title {
      position:relative;
      z-index:2;
      text-align:center;
      color: #fff;
      padding: 14px 18px;
      display:inline-block;
      background: rgba(4,22,38,0.42);
      border-radius: 8px;
      font-size:1.45rem;
      font-weight:700;
      box-shadow: 0 6px 20px rgba(0,0,0,0.18);
      margin-top:8px;
    }

    main { flex:1; padding: 28px 16px 36px; }

    .container-collection { max-width:1240px; margin: 0 auto; transform: translateY(-24px); } /* naik sedikit supaya menyatu dengan hero */

    .book-card {
      background: #fff;
      border-radius:12px;
      box-shadow: 0 6px 18px rgba(0,0,0,0.08);
      overflow:hidden;
      transition: transform .18s ease, box-shadow .18s ease;
      height:100%;
      display:flex;
      flex-direction:column;
    }
    .book-card:hover { transform: translateY(-6px); box-shadow: 0 12px 32px rgba(0,0,0,0.14); }

    .book-cover {
      width:100%;
      height:240px;
      object-fit:cover;
      background:#f3f3f3;
      display:block;
    }

    .book-info { padding:14px; flex:1; display:flex; flex-direction:column; }
    .book-info h5 { font-size:1.02rem; margin:0 0 6px 0; color:var(--text-dark); font-weight:700; }
    .book-info .meta { color:#6c757d; font-size:.88rem; margin-bottom:8px; }
    .book-info .small-meta { color:#6c757d; font-size:.83rem; margin-bottom:10px; }

    .lock-badge { background:#ffdca6; color: #4b2f05; padding:4px 8px; border-radius:6px; font-size:.82rem; display:inline-block; }

    .book-actions { margin-top:auto; display:flex; gap:8px; align-items:center; }
    .btn-accent { background: var(--accent-gold); color:#fff; border:none; }
    .btn-accent:hover { background:#a6742f; }

    /* Footer biru */
    footer {
      background: linear-gradient(90deg, var(--blue-dark), var(--blue-dark-2));
      color:#fff;
      text-align:center;
      padding:12px 0;
      margin-top:auto;
      box-shadow: 0 -2px 6px rgba(0,0,0,0.06);
    }

    /* Responsive tweaks */
    @media (max-width:576px){
      .hero-title { font-size:1.05rem; padding:10px 12px; }
      .book-cover { height:200px; }
    }
  </style>
</head>
<body>

  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
      <div class="brand-wrap">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
          <img src="<?= e($logo) ?>" alt="Logo" class="brand-logo">
          <span style="color:#fff; font-weight:700; margin-left:6px;">Digital Library</span>
        </a>
      </div>

      <div class="ms-auto nav-right">
        <?php if (!$isLoggedIn): ?>
          <a href="login.php" class="btn btn-sm btn-light rounded-pill">Login</a>
          <a href="register.php" class="btn btn-sm btn-outline-light rounded-pill">Register</a>
        <?php else: ?>
          <a href="profile.php" class="btn btn-sm btn-outline-light rounded-pill">Profil</a>
          <a href="logout.php" class="btn btn-sm btn-light rounded-pill">Logout</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <!-- HERO: background image dan judul -->
  <header class="hero-bg" aria-hidden="true">
    <div class="hero-overlay"></div>
    <div class="container text-center" style="position:relative; z-index:2;">
      <div class="hero-title">Koleksi Buku Digital</div>
    </div>
  </header>

  <main>
    <div class="container-collection container">
      <?php if ($result->num_rows === 0): ?>
        <div class="alert alert-info text-center">Belum ada buku di koleksi.</div>
      <?php endif; ?>

      <div class="row g-4">
        <?php while ($b = $result->fetch_assoc()):
          $cover = !empty($b['cover_path']) && file_exists(__DIR__ . '/' . $b['cover_path'])
                   ? $b['cover_path']
                   : 'https://via.placeholder.com/400x560?text=No+Cover';
          $isRestricted = ($b['access_level'] ?? 'public') === 'registered';
        ?>
        <div class="col-6 col-md-4 col-lg-3 d-flex">
          <div class="book-card w-100">
            <img src="<?= e($cover) ?>" alt="Cover <?= e($b['title']) ?>" class="book-cover">
            <div class="book-info">
              <h5><?= e($b['title']) ?></h5>
              <div class="meta"><?= e($b['author'] ?: '-') ?> · <?= e($b['category_name'] ?: '-') ?></div>
              <div class="small-meta">Tahun: <?= e($b['publication_year'] ?: '-') ?> · ISBN: <?= e($b['isbn'] ?: '-') ?></div>

              <div class="d-flex justify-content-between align-items-center book-actions">
                <div>
                  <?php if ($isRestricted && !$isLoggedIn): ?>
                    <span class="lock-badge">🔒 Hanya untuk pengguna terdaftar</span>
                  <?php else: ?>
                    <a href="book_detail.php?id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                    <?php if (!empty($b['file_path'])): ?>
                      <a href="download.php?id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-accent">Unduh</a>
                    <?php else: ?>
                      <button class="btn btn-sm btn-outline-secondary" disabled>Tidak ada file</button>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
                <div class="text-muted" style="font-size:.82rem"><?= date('d M Y', strtotime($b['created_at'])) ?></div>
              </div>
            </div>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
    </div>
  </main>

  <footer>
    © <?= date('Y') ?> Perpustakaan Digital Batik. Semua Hak Dilindungi.
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
