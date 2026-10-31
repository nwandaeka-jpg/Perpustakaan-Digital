<?php
session_start();
require_once 'koneksi.php'; // pastikan $conn (mysqli) sudah tersedia

// cek login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// ambil data user saat ini (cek role)
$stmt = $conn->prepare("SELECT name, role FROM users WHERE id = ?");
if (!$stmt) {
    die("Query gagal: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$current = $res->fetch_assoc();
$stmt->close();

if (!$current || $current['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$logo = 'logo.png';

/* Statistik */
$total_users = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'user'")->fetch_assoc()['total'] ?? 0;
$total_admins = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin'")->fetch_assoc()['total'] ?? 0;
$total_books = $conn->query("SELECT COUNT(*) AS total FROM books")->fetch_assoc()['total'] ?? 0;
$total_categories = $conn->query("SELECT COUNT(*) AS total FROM categories")->fetch_assoc()['total'] ?? 0;
$total_downloads = $conn->query("SELECT COUNT(*) AS total FROM downloads")->fetch_assoc()['total'] ?? 0;

/* Recent lists (limit 6) */
// Recent users with role = user
$recent_users_stmt = $conn->prepare("SELECT id, name, email, created_at FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT 6");
$recent_users_stmt->execute();
$recent_users = $recent_users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_users_stmt->close();

// Recent books
$recent_books_stmt = $conn->prepare("SELECT b.id, b.title, b.author, c.name AS category, b.created_at 
    FROM books b LEFT JOIN categories c ON b.category_id = c.id
    ORDER BY b.created_at DESC LIMIT 6");
$recent_books_stmt->execute();
$recent_books = $recent_books_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_books_stmt->close();

// Recent downloads
$recent_dl_stmt = $conn->prepare("SELECT d.id, b.title AS book_title, u.name AS user_name, d.created_at
    FROM downloads d
    LEFT JOIN books b ON d.book_id = b.id
    LEFT JOIN users u ON d.user_id = u.id
    ORDER BY d.created_at DESC LIMIT 6");
$recent_dl_stmt->execute();
$recent_downloads = $recent_dl_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_dl_stmt->close();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard Admin - Perpustakaan Digital</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{
      --accent: #b7853b;
      --dark: #072146;
      --card-bg: #ffffff;
    }
    body { background:#f5f7fb; min-height:100vh; display:flex; flex-direction:column; }
    .navbar { background: linear-gradient(90deg,#083056,#0f2b4a); }
    .brand-logo { height:40px; object-fit:contain; }
    main { flex:1; padding:30px 15px; }
    .stat-card { border-radius:12px; box-shadow:0 6px 18px rgba(11,22,40,0.06); border: 1px solid rgba(11,22,40,0.04); }
    .stat-icon { font-size:28px; opacity:0.95; }
    .btn-accent { background:var(--accent); color:#fff; border:none; }
    .btn-accent:hover { background:#d19c3c; }
    .table-responsive { max-height:360px; overflow:auto; }
    .muted-small { font-size:0.85rem; color:#6c757d; }
    footer { background:#071428; color:#bdbdbd; text-align:center; padding:12px 0; margin-top:auto; }
    .card-header-custom { background: linear-gradient(90deg,#0b2b4a,#08283f); color:#fff; border-bottom: none; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="admin_dashboard.php">
      <img src="<?= htmlspecialchars($logo) ?>" alt="logo" class="brand-logo me-2">
      <div class="d-flex flex-column" style="line-height:1;">
        <strong style="font-size:1rem;color:var(--accent)">Admin</strong>
        <small style="font-size:0.7rem;color:#cfd8dc">Perpustakaan</small>
      </div>
    </a>

    <div class="ms-auto d-flex align-items-center gap-2">
      <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
    </div>
  </div>
</nav>

<main class="container">
  <div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0">Dashboard Admin</h3>
        <div class="muted-small">Halo, <strong><?= htmlspecialchars($current['name']) ?></strong> — kelola perpustakaan digital di sini.</div>
      </div>
    </div>
  </div>

  <!-- Statistik -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
      <div class="p-3 stat-card">
        <div class="d-flex align-items-center">
          <div class="me-3 stat-icon">👥</div>
          <div>
            <div class="text-muted small">Pengguna (User)</div>
            <h4 class="mb-0"><?= (int)$total_users ?></h4>
            <div class="muted-small">Aktif user terdaftar</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-md-3">
      <div class="p-3 stat-card">
        <div class="d-flex align-items-center">
          <div class="me-3 stat-icon">📚</div>
          <div>
            <div class="text-muted small">Total Buku</div>
            <h4 class="mb-0"><?= (int)$total_books ?></h4>
            <div class="muted-small"><?= (int)$total_categories ?> kategori</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-md-3">
      <div class="p-3 stat-card">
        <div class="d-flex align-items-center">
          <div class="me-3 stat-icon">🗂️</div>
          <div>
            <div class="text-muted small">Kategori</div>
            <h4 class="mb-0"><?= (int)$total_categories ?></h4>
            <div class="muted-small">Organisasi koleksi</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-md-3">
      <div class="p-3 stat-card">
        <div class="d-flex align-items-center">
          <div class="me-3 stat-icon">⬇️</div>
          <div>
            <div class="text-muted small">Unduhan</div>
            <h4 class="mb-0"><?= (int)$total_downloads ?></h4>
            <div class="muted-small">Total aksi unduh</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Dua kolom: left recent users/books, right recent downloads -->
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
          <div><strong>Pengguna Terbaru (role = user)</strong></div>
          <small class="text-white">list terbaru</small>
        </div>
        <div class="card-body">
          <?php if (count($recent_users) > 0): ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th style="width:60px">#</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Tanggal Daftar</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $n=1; foreach ($recent_users as $u): ?>
                    <tr>
                      <td><?= $n++ ?></td>
                      <td><?= htmlspecialchars($u['name']) ?></td>
                      <td><?= htmlspecialchars($u['email']) ?></td>
                      <td class="muted-small"><?= date('d M Y H:i', strtotime($u['created_at'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="alert alert-secondary mb-0">Belum ada pengguna (role=user).</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
          <div><strong>Buku Terbaru</strong></div>
          <small class="text-white">tambah/kelola di Manage Buku</small>
        </div>
        <div class="card-body">
          <?php if (count($recent_books) > 0): ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th style="width:60px">#</th>
                    <th>Judul</th>
                    <th>Penulis</th>
                    <th>Kategori</th>
                    <th>Ditambahkan</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $m=1; foreach ($recent_books as $b): ?>
                    <tr>
                      <td><?= $m++ ?></td>
                      <td><?= htmlspecialchars($b['title']) ?></td>
                      <td class="muted-small"><?= htmlspecialchars($b['author'] ?? '-') ?></td>
                      <td class="muted-small"><?= htmlspecialchars($b['category'] ?? '-') ?></td>
                      <td class="muted-small"><?= date('d M Y', strtotime($b['created_at'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="alert alert-secondary mb-0">Belum ada buku.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
          <div><strong>Unduhan Terbaru</strong></div>
          <small class="text-white">riwayat unduh</small>
        </div>
        <div class="card-body">
          <?php if (count($recent_downloads) > 0): ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th style="width:40px">#</th>
                    <th>Buku</th>
                    <th>User</th>
                    <th>Waktu</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $k=1; foreach ($recent_downloads as $dl): ?>
                    <tr>
                      <td><?= $k++ ?></td>
                      <td><?= htmlspecialchars($dl['book_title'] ?? '-') ?></td>
                      <td class="muted-small"><?= htmlspecialchars($dl['user_name'] ?? 'Guest') ?></td>
                      <td class="muted-small"><?= date('d M Y H:i', strtotime($dl['created_at'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="alert alert-secondary mb-0">Belum ada data unduhan.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-body text-center">
          <h6 class="mb-1">Quick Actions</h6>
          <div class="d-grid gap-2">
            <a href="manage_books.php" class="btn btn-accent">Tambahkan Buku</a>
            <a href="manage_categories.php" class="btn btn-outline-secondary">Kelola Kategori</a>
            <a href="manage_users.php" class="btn btn-outline-dark">Kelola Pengguna</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<footer>
  © <?= date('Y') ?> Perpustakaan Digital — Admin Panel
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
