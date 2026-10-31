<?php
// manage_books.php (perbaikan lengkap dengan handling upload yang lebih aman)
session_start();
require_once 'koneksi.php'; // pastikan $conn (mysqli) tersedia

// debug sementara (hapus/comment jika tidak perlu)
// ini membantu ketika masih debug; jangan aktifkan di production
// ini_set('display_errors', 1); error_reporting(E_ALL);

// --- pastikan admin ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = (int) $_SESSION['user_id'];

// ambil role user saat ini
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
if (!$stmt) die("DB error: " . $conn->error);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$current = $res->fetch_assoc();
$stmt->close();

if (!$current || $current['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// --- directories untuk upload ---
$cover_dir = __DIR__ . '/uploads/covers/';
$file_dir  = __DIR__ . '/uploads/files/';

// try create dirs and check writable
if (!is_dir($cover_dir) && !mkdir($cover_dir, 0755, true)) {
    die("Tidak bisa membuat folder cover: $cover_dir");
}
if (!is_dir($file_dir) && !mkdir($file_dir, 0755, true)) {
    die("Tidak bisa membuat folder file: $file_dir");
}
if (!is_writable($cover_dir) || !is_writable($file_dir)) {
    die("Folder upload tidak bisa ditulisi. Cek permission: $cover_dir , $file_dir");
}

// helper
function e($v) { return htmlspecialchars($v, ENT_QUOTES); }

// helper random safe
function safe_random_hex($len = 12) {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($len));
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($len));
    }
    // fallback insecure - should not happen in modern PHP
    return bin2hex(substr(str_shuffle(str_repeat('0123456789abcdef', 10)), 0, $len*2));
}

// helper untuk bind dinamis
function mysqli_stmt_bind_params_dynamic(mysqli_stmt $stmt, string $types, array $params) {
    if ($types === '') return true;
    $refs = [];
    $refs[] = & $types;
    foreach ($params as $i => $v) {
        $refs[] = & $params[$i];
    }
    return call_user_func_array([$stmt, 'bind_param'], $refs);
}

$errors = [];
$success = '';

// mapping upload errors
function upload_error_message($code) {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE: return "File melebihi upload_max_filesize di server.";
        case UPLOAD_ERR_FORM_SIZE: return "File melebihi MAX_FILE_SIZE yang diset di form.";
        case UPLOAD_ERR_PARTIAL: return "File hanya ter-upload sebagian.";
        case UPLOAD_ERR_NO_FILE: return "Tidak ada file yang diupload.";
        case UPLOAD_ERR_NO_TMP_DIR: return "Folder sementara (tmp) tidak tersedia di server.";
        case UPLOAD_ERR_CANT_WRITE: return "Gagal menulis file ke disk.";
        case UPLOAD_ERR_EXTENSION: return "Upload dihentikan oleh ekstensi PHP.";
        default: return "Kesalahan upload file (kode: $code).";
    }
}

// --- handle delete ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // ambil info file untuk dihapus
    $q = $conn->prepare("SELECT cover_path, file_path FROM books WHERE id = ?");
    if (!$q) die("DB error: " . $conn->error);
    $q->bind_param("i", $id);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    $q->close();

    $del = $conn->prepare("DELETE FROM books WHERE id = ?");
    if (!$del) die("DB error: " . $conn->error);
    $del->bind_param("i", $id);
    if ($del->execute()) {
        // hapus file fisik jika ada
        if (!empty($r['cover_path']) && file_exists(__DIR__ . '/' . $r['cover_path'])) @unlink(__DIR__ . '/' . $r['cover_path']);
        if (!empty($r['file_path']) && file_exists(__DIR__ . '/' . $r['file_path'])) @unlink(__DIR__ . '/' . $r['file_path']);
        $success = "Buku berhasil dihapus.";
    } else {
        $errors[] = "Gagal menghapus buku: " . $del->error;
    }
    $del->close();
}

// --- handle add / edit POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_book'])) {
    $mode = $_POST['mode'] ?? 'add'; // add atau edit
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $description = trim($_POST['description'] ?? '');
    $publication_year = isset($_POST['publication_year']) && $_POST['publication_year'] !== '' ? (int)$_POST['publication_year'] : null;
    $isbn = trim($_POST['isbn'] ?? '');
    $access_level = (isset($_POST['access_level']) && $_POST['access_level'] === 'registered') ? 'registered' : 'public';

    if ($title === '') $errors[] = "Judul wajib diisi.";

    // handle uploads
    $cover_path_db = null;
    $file_path_db = null;

    // COVER (opsional)
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
        $cov = $_FILES['cover'];
        if ($cov['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Cover: " . upload_error_message($cov['error']);
        } else {
            $ext = strtolower(pathinfo($cov['name'], PATHINFO_EXTENSION));
            $allowed_cover = ['jpg','jpeg','png','webp'];
            if (!in_array($ext, $allowed_cover)) $errors[] = "Format cover tidak valid (jpg/jpeg/png/webp).";
            if ($cov['size'] > 2 * 1024 * 1024) $errors[] = "Ukuran cover maksimal 2MB.";
            if (empty($errors)) {
                if (!is_uploaded_file($cov['tmp_name'])) {
                    $errors[] = "Cover: file tidak berasal dari upload.";
                } else {
                    $fn = 'cover_' . time() . '_' . safe_random_hex(6) . '.' . $ext;
                    $dest = $cover_dir . $fn;
                    if (move_uploaded_file($cov['tmp_name'], $dest)) {
                        $cover_path_db = 'uploads/covers/' . $fn;
                    } else {
                        $errors[] = "Gagal mengunggah cover (move_uploaded_file gagal).";
                    }
                }
            }
        }
    }

    // FILE (PDF)
    if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['file'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File: " . upload_error_message($f['error']);
        } else {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $allowed_file = ['pdf'];
            if (!in_array($ext, $allowed_file)) $errors[] = "Format file harus PDF.";
            if ($f['size'] > 150 * 1024 * 1024) $errors[] = "Ukuran file maksimal 150MB.";
            if (empty($errors)) {
                if (!is_uploaded_file($f['tmp_name'])) {
                    $errors[] = "File: file tidak berasal dari upload.";
                } else {
                    // buat nama file aman
                    $fn = 'book_' . time() . '_' . safe_random_hex(6) . '.' . $ext;
                    $dest = $file_dir . $fn;
                    if (move_uploaded_file($f['tmp_name'], $dest)) {
                        $file_path_db = 'uploads/files/' . $fn;
                    } else {
                        $errors[] = "Gagal mengunggah file buku (move_uploaded_file gagal).";
                    }
                }
            }
        }
    }

    // Jika mode add -> file wajib
    if ($mode === 'add' && !$file_path_db) {
        $errors[] = "File PDF wajib diunggah untuk buku baru.";
    }

    // jika tidak ada error lanjut DB
    if (empty($errors)) {
        if ($mode === 'add') {
            // columns sesuai schema yang kamu punya (tanpa file_mime/file_size)
            $cols = [
                'title','author','category_id','description','publication_year','isbn',
                'cover_path','file_path','access_level','uploaded_by'
            ];

            $placeholders = [];
            $types = '';
            $params = [];

            // title, author
            $placeholders[] = '?'; $types .= 's'; $params[] = $title;
            $placeholders[] = '?'; $types .= 's'; $params[] = $author;

            // category_id (nullable)
            if (is_null($category_id)) {
                $placeholders[] = 'NULL';
            } else {
                $placeholders[] = '?'; $types .= 'i'; $params[] = $category_id;
            }

            // description
            $placeholders[] = '?'; $types .= 's'; $params[] = $description;

            // publication_year
            if (is_null($publication_year)) {
                $placeholders[] = 'NULL';
            } else {
                $placeholders[] = '?'; $types .= 'i'; $params[] = $publication_year;
            }

            // isbn
            $placeholders[] = '?'; $types .= 's'; $params[] = $isbn;

            // cover_path (nullable)
            $placeholders[] = '?'; $types .= 's'; $params[] = $cover_path_db;

            // file_path
            $placeholders[] = '?'; $types .= 's'; $params[] = $file_path_db;

            // access_level
            $placeholders[] = '?'; $types .= 's'; $params[] = $access_level;

            // uploaded_by
            $placeholders[] = '?'; $types .= 'i'; $params[] = $user_id;

            $sql = "INSERT INTO books (" . implode(',', $cols) . ", created_at) VALUES (" . implode(',', $placeholders) . ", NOW())";
            $stmtIns = $conn->prepare($sql);
            if (!$stmtIns) {
                $errors[] = "DB prepare error: " . $conn->error;
            } else {
                if ($types !== '') {
                    if (!mysqli_stmt_bind_params_dynamic($stmtIns, $types, $params)) {
                        $errors[] = "Bind params gagal.";
                    }
                }
                if (empty($errors) && $stmtIns->execute()) {
                    $success = "Buku berhasil ditambahkan.";
                } else {
                    $errors[] = "Gagal menyimpan buku: " . $stmtIns->error;
                    // bersihkan file yang sudah ter-upload bila DB error
                    if ($file_path_db && file_exists(__DIR__ . '/' . $file_path_db)) @unlink(__DIR__ . '/' . $file_path_db);
                    if ($cover_path_db && file_exists(__DIR__ . '/' . $cover_path_db)) @unlink(__DIR__ . '/' . $cover_path_db);
                }
                $stmtIns->close();
            }
        } elseif ($mode === 'edit' && !empty($_POST['id'])) {
            $id = (int) $_POST['id'];

            // ambil current paths
            $qq = $conn->prepare("SELECT cover_path, file_path FROM books WHERE id = ?");
            if (!$qq) die("DB error: " . $conn->error);
            $qq->bind_param("i", $id);
            $qq->execute();
            $old = $qq->get_result()->fetch_assoc();
            $qq->close();

            // jika upload baru hapus yang lama, kalau gak ada tetap pakai yang lama
            if (empty($cover_path_db)) {
                $cover_path_db = $old['cover_path'];
            } else {
                if (!empty($old['cover_path']) && file_exists(__DIR__ . '/' . $old['cover_path'])) @unlink(__DIR__ . '/' . $old['cover_path']);
            }

            if (empty($file_path_db)) {
                $file_path_db = $old['file_path'];
            } else {
                if (!empty($old['file_path']) && file_exists(__DIR__ . '/' . $old['file_path'])) @unlink(__DIR__ . '/' . $old['file_path']);
            }

            // Build update SQL with handling nullable ints
            $setParts = [];
            $types = '';
            $params = [];

            $setParts[] = "title = ?"; $types .= 's'; $params[] = $title;
            $setParts[] = "author = ?"; $types .= 's'; $params[] = $author;

            if (is_null($category_id)) {
                $setParts[] = "category_id = NULL";
            } else {
                $setParts[] = "category_id = ?"; $types .= 'i'; $params[] = $category_id;
            }

            $setParts[] = "description = ?"; $types .= 's'; $params[] = $description;

            if (is_null($publication_year)) {
                $setParts[] = "publication_year = NULL";
            } else {
                $setParts[] = "publication_year = ?"; $types .= 'i'; $params[] = $publication_year;
            }

            $setParts[] = "isbn = ?"; $types .= 's'; $params[] = $isbn;
            $setParts[] = "cover_path = ?"; $types .= 's'; $params[] = $cover_path_db;
            $setParts[] = "file_path = ?"; $types .= 's'; $params[] = $file_path_db;
            $setParts[] = "access_level = ?"; $types .= 's'; $params[] = $access_level;
            $setParts[] = "uploaded_by = ?"; $types .= 'i'; $params[] = $user_id;
            $setParts[] = "updated_at = NOW()";

            $sql = "UPDATE books SET " . implode(', ', $setParts) . " WHERE id = ?";
            $types .= 'i'; $params[] = $id;

            $upd = $conn->prepare($sql);
            if (!$upd) {
                $errors[] = "DB prepare error: " . $conn->error;
            } else {
                if (!mysqli_stmt_bind_params_dynamic($upd, $types, $params)) {
                    $errors[] = "Bind params gagal.";
                }
                if (empty($errors) && $upd->execute()) {
                    $success = "Buku berhasil diperbarui.";
                } else {
                    $errors[] = "Gagal memperbarui buku: " . $upd->error;
                }
                $upd->close();
            }
        }
    }
}

// ambil daftar kategori untuk select
$cats = [];
$cr = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($cr) while ($row = $cr->fetch_assoc()) $cats[] = $row;

// ambil daftar buku (simple list) - untuk admin
$books = [];
$br = $conn->query("SELECT b.*, c.name AS category_name, u.name AS uploader FROM books b LEFT JOIN categories c ON b.category_id = c.id LEFT JOIN users u ON b.uploaded_by = u.id ORDER BY b.created_at DESC");
if ($br) {
    while ($r = $br->fetch_assoc()) $books[] = $r;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Manage Books - Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root { --accent:#b7853b; --dark:#072146; }
    body { background:#f8f9fa; min-height:100vh; display:flex; flex-direction:column; }
    .navbar { background: linear-gradient(90deg,#083056,#0f2b4a); }
    .brand-logo { height:40px; object-fit:contain; }
    main { flex:1; padding:24px; }
    .btn-accent { background:var(--accent); color:#fff; border:none; }
    .btn-accent:hover { background: #e9a231ff; color:#fff; }
    .card { border-radius:10px; box-shadow: 0 6px 20px rgba(0,0,0,0.04); }
    footer { background:#071428; color:#bdbdbd; text-align:center; padding:12px 0; margin-top:auto; }
    td, th { vertical-align: middle; }
    .small-muted { font-size:0.85rem; color:#6c757d; }
  </style>
</head>
<body>
<nav class="navbar navbar-dark">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="admin_dashboard.php">
      <img src="<?= e('logo.png') ?>" class="brand-logo me-2" alt="logo">
      <div class="d-flex flex-column" style="line-height:1;">
        <strong style="font-size:1rem;color:var(--accent)">Admin</strong>
        <small style="font-size:0.7rem;color:#cfd8dc">Kelola Buku</small>
      </div>
    </a>
    <div class="ms-auto d-flex gap-2">
      <a href="admin_dashboard.php" class="btn btn-sm btn-outline-light">Dashboard</a>
      <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
    </div>
  </div>
</nav>

<main class="container">
  <div class="mb-3 d-flex justify-content-between align-items-center">
    <h4 class="mb-0">Kelola Buku</h4>
    <button class="btn btn-accent" data-bs-toggle="collapse" data-bs-target="#formCollapse">Tambah / Edit Buku</button>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
  <?php endif; ?>
  <?php if ($errors): foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
  <?php endforeach; endif; ?>

  <div class="collapse mb-4" id="formCollapse">
    <div class="card card-body">
      <?php
      // Jika edit diminta lewat GET
      $edit_book = null;
      if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
          $eid = (int)$_GET['id'];
          $q = $conn->prepare("SELECT * FROM books WHERE id = ?");
          if ($q) {
              $q->bind_param("i", $eid);
              $q->execute();
              $edit_book = $q->get_result()->fetch_assoc();
              $q->close();
              echo "<script>setTimeout(()=>{document.querySelector('[data-bs-target=\"#formCollapse\"]').click();},100);</script>";
          }
      }
      ?>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="mode" value="<?= $edit_book ? 'edit' : 'add' ?>">
        <?php if ($edit_book): ?><input type="hidden" name="id" value="<?= (int)$edit_book['id'] ?>"><?php endif; ?>

        <div class="mb-3">
          <label class="form-label">Judul</label>
          <input type="text" name="title" class="form-control" required value="<?= e($edit_book['title'] ?? '') ?>">
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Penulis</label>
            <input type="text" name="author" class="form-control" value="<?= e($edit_book['author'] ?? '') ?>">
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Tahun</label>
            <input type="number" min="1900" max="<?= date('Y') ?>" name="publication_year" class="form-control" value="<?= e($edit_book['publication_year'] ?? '') ?>">
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">ISBN</label>
            <input type="text" name="isbn" class="form-control" value="<?= e($edit_book['isbn'] ?? '') ?>">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Kategori</label>
          <select name="category_id" class="form-select">
            <option value="">-- Pilih kategori (opsional) --</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (isset($edit_book['category_id']) && $edit_book['category_id']==$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="small-muted">Jika tidak ada kategori yang cocok, tambahkan kategori baru di Manage Categories.</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Ringkasan / Deskripsi</label>
          <textarea name="description" rows="4" class="form-control"><?= e($edit_book['description'] ?? '') ?></textarea>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Cover (jpg/png/webp) - max 2MB</label>
            <input type="file" name="cover" class="form-control" accept="image/*">
            <?php if (!empty($edit_book['cover_path'])): ?>
              <div class="small-muted mt-1">Cover saat ini: <a href="<?= e($edit_book['cover_path']) ?>" target="_blank">Lihat</a></div>
            <?php endif; ?>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">File PDF (opsional saat edit) - max 150MB</label>
            <input type="file" name="file" class="form-control" accept="application/pdf">
            <?php if (!empty($edit_book['file_path'])): ?>
              <div class="small-muted mt-1">File saat ini: <a href="<?= e($edit_book['file_path']) ?>" target="_blank">Unduh</a></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Akses</label>
          <select name="access_level" class="form-select">
            <option value="public" <?= (isset($edit_book['access_level']) && $edit_book['access_level']==='public') ? 'selected' : '' ?>>Publik</option>
            <option value="registered" <?= (isset($edit_book['access_level']) && $edit_book['access_level']==='registered') ? 'selected' : '' ?>>Terdaftar (hanya user login)</option>
          </select>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" name="save_book" class="btn btn-accent"><?= $edit_book ? 'Simpan Perubahan' : 'Tambah Buku' ?></button>
          <?php if ($edit_book): ?><a href="manage_books.php" class="btn btn-outline-secondary">Batal</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- daftar buku -->
  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover">
          <thead class="table-light">
            <tr>
              <th style="width:60px">#</th>
              <th>Judul</th>
              <th>Penulis</th>
              <th>Kategori</th>
              <th>Akses</th>
              <th>Uploader</th>
              <th>Ditambahkan</th>
              <th style="width:140px">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php $i=1; foreach ($books as $b): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= e($b['title']) ?></td>
                <td class="small-muted"><?= e($b['author'] ?: '-') ?></td>
                <td class="small-muted"><?= e($b['category_name'] ?: '-') ?></td>
                <td><?= e($b['access_level']) ?></td>
                <td class="small-muted"><?= e($b['uploader'] ?: '-') ?></td>
                <td class="small-muted"><?= date('d M Y', strtotime($b['created_at'])) ?></td>
                <td>
                  <a href="manage_books.php?action=edit&id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                  <a href="manage_books.php?action=delete&id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus buku ini?')">Hapus</a>
                </td>
              </tr>
            <?php endforeach; if (empty($books)): ?>
              <tr><td colspan="8" class="text-center text-muted">Belum ada data buku.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</main>

<footer>
  © <?= date('Y') ?> Perpustakaan Digital
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
