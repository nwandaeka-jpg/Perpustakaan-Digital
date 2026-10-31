<?php
session_start();
require_once 'koneksi.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$logo = 'logo.png';

// Ambil data riwayat unduhan
$query = "
    SELECT 
        d.id, 
        b.title AS judul, 
        b.author AS penulis,
        b.file_path, 
        d.created_at 
    FROM downloads d
    JOIN books b ON d.book_id = b.id
    WHERE d.user_id = ?
    ORDER BY d.created_at DESC
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Query gagal disiapkan: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$downloads = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Riwayat Unduhan Saya | Perpustakaan Digital</title>
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
      padding: 40px 15px;
    }

    .navbar {
      background: linear-gradient(90deg, #083056, #0f2b4a);
    }

    .brand-logo {
      height: 44px;
      object-fit: contain;
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

    .card {
      border-radius: 12px;
      box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    }

    footer {
      background: #071428;
      color: #bdbdbd;
      text-align: center;
      padding: 15px 0;
      margin-top: auto;
    }

    table th {
      background: var(--dark);
      color: #fff;
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

    <div class="ms-auto">
      <a href="profile.php" class="btn btn-sm btn-outline-light me-2">Profil</a>
      <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
    </div>
  </div>
</nav>

<!-- Konten utama -->
<main class="container">
  <div class="card">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0">📥 Riwayat Unduhan Saya</h5>
      <a href="list_books.php" class="btn btn-sm btn-accent">Kembali ke Koleksi</a>
    </div>
    <div class="card-body">
      <?php if (count($downloads) > 0): ?>
      <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
          <thead>
            <tr>
              <th style="width:60px;">No</th>
              <th>Judul Buku</th>
              <th>Penulis</th>
              <th>Tanggal Unduh</th>
              <th style="width:130px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php $no = 1; foreach ($downloads as $dl): ?>
              <tr>
                <td><?= $no++ ?></td>
                <td><?= htmlspecialchars($dl['judul']) ?></td>
                <td><?= htmlspecialchars($dl['penulis'] ?? '-') ?></td>
                <td><?= date('d F Y, H:i', strtotime($dl['created_at'])) ?></td>
                <td>
                  <a href="<?= htmlspecialchars($dl['file_path']) ?>" class="btn btn-sm btn-accent" download>⬇ Unduh Lagi</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <div class="alert alert-warning mb-0">Belum ada riwayat unduhan ditemukan.</div>
      <?php endif; ?>
    </div>
  </div>
</main>

<!-- Footer -->
<footer>
  © <?= date('Y') ?> Perpustakaan Digital | Semua Hak Dilindungi
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
