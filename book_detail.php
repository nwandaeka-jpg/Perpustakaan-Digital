<?php
session_start();
require_once 'koneksi.php'; // pastikan $conn mysqli tersedia

// helper
function e($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? (int)$_SESSION['user_id'] : null;
$userRole = $_SESSION['user_role'] ?? null;

// ambil id buku dari query
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: list_books.php');
    exit;
}

// prepare dan ambil data buku (dengan kategori & uploader)
$sql = "SELECT b.*, c.name AS category_name, u.name AS uploader_name
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.id
        LEFT JOIN users u ON b.uploaded_by = u.id
        WHERE b.id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("DB prepare error: " . $conn->error);
}
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$book = $res->fetch_assoc();
$stmt->close();

if (!$book) {
    // buku tidak ditemukan
    http_response_code(404);
    echo "<h2>Buku tidak ditemukan</h2><p>Kembali ke <a href='list_books.php'>daftar buku</a>.</p>";
    exit;
}

// akses kontrol
$access_level = $book['access_level'] ?? 'public';
$requiresLogin = ($access_level === 'registered');
$canView = !$requiresLogin || $isLoggedIn;

// cek file cover & file pdf ada atau tidak
$coverPath = !empty($book['cover_path']) && file_exists(__DIR__ . '/' . $book['cover_path']) ? $book['cover_path'] : null;
$filePath  = !empty($book['file_path'])  && file_exists(__DIR__ . '/' . $book['file_path'])   ? $book['file_path']  : null;

$logo = 'logo.png';
$batikBg = 'assets/batik-pattern.jpg';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($book['title']) ?> — Perpustakaan Digital</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{ --accent:#b7853b; --dark:#072146; --nav-height:64px; }
    html,body{height:100%; margin:0;}
    body { padding-top: var(--nav-height); background:#f6f5f3; font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; }
    .brand-logo{ height:44px; object-fit:contain; }
   .hero-mini {
  background: linear-gradient(90deg, #072949ff, #05233fff); /* warna biru elegan seperti index.php */
  color: #fff;
  padding: 28px 0;
  margin-bottom: 18px;
}

    .card-book { border-radius:10px; box-shadow:0 8px 20px rgba(0,0,0,0.06); }
    .btn-accent { background:var(--accent); color:#fff; border:none; }
    .btn-accent:hover { background:#e0a43d; color:#fff; }
    footer { background:#071428; color:#bdbdbd; padding:18px 0; margin-top:30px; }
    .meta { color:#6c757d; font-size:0.9rem; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: linear-gradient(90deg,#083056,#0f2b4a); height:var(--nav-height);">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="<?= e($logo) ?>" class="brand-logo me-2" alt="logo">
      <div class="d-flex flex-column" style="line-height:1;">
        <strong style="font-size:0.95rem;color:var(--accent)">Digital</strong>
        <small style="font-size:0.65rem;color:#cfd8dc">Perpustakaan</small>
      </div>
    </a>

    <div class="ms-auto">
      <?php if (!$isLoggedIn): ?>
        <a href="login.php" class="btn btn-sm btn-outline-light me-2">Login</a>
        <a href="register.php" class="btn btn-sm btn-accent">Register</a>
      <?php else: ?>
        <a href="profile.php" class="btn btn-sm btn-outline-light me-2">Profil</a>
        <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<header class="hero-mini">
  <div class="container">
    <h2 class="mb-0"><?= e($book['title']) ?></h2>
    <div class="meta"><?= e($book['author'] ?: '-') ?> · <?= e($book['category_name'] ?: '-') ?></div>
  </div>
</header>

<main class="container mb-5">
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card card-book">
        <?php if ($coverPath): ?>
          <img src="<?= e($coverPath) ?>" class="img-fluid" alt="Cover <?= e($book['title']) ?>">
        <?php else: ?>
          <img src="https://via.placeholder.com/420x600?text=No+Cover" class="img-fluid" alt="No cover">
        <?php endif; ?>

        <div class="card-body">
          <h5 class="card-title"><?= e($book['title']) ?></h5>
          <p class="meta mb-2"><?= e($book['author'] ?: '-') ?> · <?= e($book['publication_year'] ?: '-') ?></p>
          <p class="small text-muted mb-2">ISBN: <?= e($book['isbn'] ?: '-') ?></p>

          <?php if ($requiresLogin && !$isLoggedIn): ?>
            <div class="alert alert-warning small">🔒 Buku ini hanya dapat diakses oleh pengguna terdaftar. <a href="login.php">Login</a> untuk mengunduh.</div>
          <?php else: ?>
            <?php if ($filePath): ?>
              <a href="download.php?id=<?= (int)$book['id'] ?>" class="btn btn-accent w-100 mb-2">Unduh PDF</a>
            <?php else: ?>
              <button class="btn btn-outline-secondary w-100 mb-2" disabled>Tidak ada file untuk diunduh</button>
            <?php endif; ?>
          <?php endif; ?>

          <a href="list_books.php" class="btn btn-outline-dark w-100">Kembali ke Koleksi</a>

          <?php if ($userRole === 'admin'): ?>
            <div class="mt-3 d-flex gap-2">
              <a href="manage_books.php?action=edit&id=<?= (int)$book['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
              <a href="manage_books.php?action=delete&id=<?= (int)$book['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus buku ini?')">Hapus</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card card-book p-3">
        <div class="card-body">
          <h5>Deskripsi</h5>
          <p><?= nl2br(e($book['description'] ?: '-')) ?></p>

          <hr>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-2"><strong>Kategori</strong><br><?= e($book['category_name'] ?: '-') ?></div>
              <div class="mb-2"><strong>Pengunggah</strong><br><?= e($book['uploader_name'] ?: '-') ?></div>
            </div>
            <div class="col-md-6">
              <div class="mb-2"><strong>Tahun</strong><br><?= e($book['publication_year'] ?: '-') ?></div>
              <div class="mb-2"><strong>Diupload</strong><br><?= date('d M Y', strtotime($book['created_at'])) ?></div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</main>

<footer>
  <div class="container d-flex justify-content-between align-items-center">
    <div><strong>Perpustakaan Digital</strong></div>
    <div class="text-end small">Contact: nugrahawandaeka@gmail.com<br>© <?= date('Y') ?></div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
