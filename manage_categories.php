<?php
session_start();
require_once 'koneksi.php';

// --- cek login & admin ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = (int) $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$current = $res->fetch_assoc();
$stmt->close();

if (!$current || $current['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

function e($v){ return htmlspecialchars($v, ENT_QUOTES); }

$errors = [];
$success = '';

// --- HANDLE ADD / EDIT (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_cat'])) {
    $mode = $_POST['mode'] ?? 'add';
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $errors[] = "Nama kategori wajib diisi.";
    } else {
        if ($mode === 'add') {
            // cek unik
            $check = $conn->prepare("SELECT id FROM categories WHERE name = ?");
            $check->bind_param("s", $name);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $errors[] = "Kategori dengan nama tersebut sudah ada.";
            }
            $check->close();

            if (empty($errors)) {
                $ins = $conn->prepare("INSERT INTO categories (name, created_at) VALUES (?, NOW())");
                $ins->bind_param("s", $name);
                if ($ins->execute()) $success = "Kategori berhasil ditambahkan.";
                else $errors[] = "Gagal menambahkan kategori: " . $ins->error;
                $ins->close();
            }
        } else if ($mode === 'edit' && !empty($_POST['id'])) {
            $id = (int) $_POST['id'];
            // cek unique kecuali id sendiri
            $check = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
            $check->bind_param("si", $name, $id);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $errors[] = "Kategori dengan nama tersebut sudah ada.";
            }
            $check->close();

            if (empty($errors)) {
                $upd = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
                $upd->bind_param("si", $name, $id);
                if ($upd->execute()) $success = "Kategori berhasil diperbarui.";
                else $errors[] = "Gagal memperbarui kategori: " . $upd->error;
                $upd->close();
            }
        }
    }
}

// --- HANDLE DELETE (GET action=delete&id=) ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];

    // safe check: pastikan tidak ada buku yg memakai kategori (opsional)
    $chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM books WHERE category_id = ?");
    $chk->bind_param("i", $id);
    $chk->execute();
    $res = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($res['cnt'] > 0) {
        $errors[] = "Kategori tidak bisa dihapus: masih ada buku yang memakai kategori ini.";
    } else {
        $del = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $del->bind_param("i", $id);
        if ($del->execute()) $success = "Kategori berhasil dihapus.";
        else $errors[] = "Gagal menghapus kategori: " . $del->error;
        $del->close();
    }
}

// ambil list kategori
$cats = [];
$q = $conn->query("SELECT id, name, created_at FROM categories ORDER BY name ASC");
if ($q) {
    while ($r = $q->fetch_assoc()) $cats[] = $r;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Manage Categories - Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{ --accent:#b7853b; --dark:#072146; }
    body{ background:#f8f9fa; min-height:100vh; display:flex; flex-direction:column; }
    .navbar{ background: linear-gradient(90deg,#083056,#0f2b4a); }
    main{ flex:1; padding:24px; }
    .btn-accent{ background:var(--accent); color:#fff; border:none; }
     .btn-accent:hover {
  background: #e9a231ff;
  color: #fff;
}
    .card{ border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.04); }
    footer{ background:#071428; color:#bdbdbd; text-align:center; padding:12px 0; margin-top:auto; }
    .small-muted{ font-size:0.85rem; color:#6c757d; }
  </style>
</head>
<body>

<nav class="navbar navbar-dark">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="admin_dashboard.php">
      <img src="<?= e('logo.png') ?>" style="height:36px" alt="logo" class="me-2">
      <div class="d-flex flex-column" style="line-height:1;">
        <strong style="font-size:1rem;color:var(--accent)">Admin</strong>
        <small style="font-size:0.7rem;color:#cfd8dc">Kelola Kategori</small>
      </div>
    </a>
    <div class="ms-auto d-flex gap-2">
      <a href="admin_dashboard.php" class="btn btn-sm btn-outline-light">Dashboard</a>
      <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
    </div>
  </div>
</nav>

<main class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Kategori</h4>
    <button class="btn btn-accent" type="button" data-bs-toggle="collapse" data-bs-target="#catForm">Tambah Kategori</button>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
  <?php if ($errors): foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; endif; ?>

  <div class="collapse mb-4" id="catForm">
    <div class="card card-body">
      <?php
        $edit = null;
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
            $eid = (int) $_GET['id'];
            $st = $conn->prepare("SELECT id, name FROM categories WHERE id = ?");
            $st->bind_param("i", $eid);
            $st->execute();
            $edit = $st->get_result()->fetch_assoc();
            $st->close();
            echo "<script>setTimeout(()=>{document.querySelector('[data-bs-target=\"#catForm\"]').click();},120);</script>";
        }
      ?>

      <form method="post" class="row g-3">
        <input type="hidden" name="mode" value="<?= $edit ? 'edit' : 'add' ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

        <div class="col-md-8">
          <label class="form-label">Nama Kategori</label>
          <input type="text" name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end gap-2">
          <button type="submit" name="save_cat" class="btn btn-accent"><?= $edit ? 'Simpan Perubahan' : 'Tambah' ?></button>
          <?php if ($edit): ?><a href="manage_categories.php" class="btn btn-outline-secondary">Batal</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:60px">#</th>
              <th>Nama Kategori</th>
              <th>Ditambahkan</th>
              <th style="width:150px">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($cats) > 0): $i=1; foreach ($cats as $c): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= e($c['name']) ?></td>
                <td class="small-muted"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                <td>
                  <a href="manage_categories.php?action=edit&id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                  <a href="manage_categories.php?action=delete&id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus kategori ini?')">Hapus</a>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="4" class="text-center text-muted">Belum ada kategori.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<footer>© <?= date('Y') ?> Perpustakaan Digital</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
