<?php
session_start();
require_once 'koneksi.php';

function e($v){ return htmlspecialchars($v, ENT_QUOTES); }

// pastikan login & role admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$current_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT name, role FROM users WHERE id = ?");
$stmt->bind_param("i", $current_id);
$stmt->execute();
$res = $stmt->get_result();
$current = $res->fetch_assoc();
$stmt->close();

if (!$current || $current['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$logo = 'logo.png';
$errors = [];
$success = '';

// HANDLE ADD/EDIT user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Edit/save user
    if (isset($_POST['save_user'])) {
        $uid = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = in_array($_POST['role'] ?? 'user', ['admin','user']) ? $_POST['role'] : 'user';

        if ($name === '' || $email === '') $errors[] = "Nama & email wajib diisi.";

        // cek email unik (kecuali untuk user yg sedang diedit)
        if (empty($errors)) {
            if ($uid) {
                $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $chk->bind_param("si", $email, $uid);
            } else {
                $chk = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $chk->bind_param("s", $email);
            }
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) $errors[] = "Email sudah terdaftar pada akun lain.";
            $chk->close();
        }

        if (empty($errors)) {
            if ($uid) {
                // prevent demoting last admin: count admins
                if ($role !== 'admin') {
                    $countAdmins = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch_assoc()['c'] ?? 0;
                    // jika user yang di-edit adalah admin dan hanya ada 1 admin, jangan izinkan demote
                    $isEditingAdmin = $conn->query("SELECT role FROM users WHERE id = $uid")->fetch_assoc()['role'] ?? '';
                    if ($isEditingAdmin === 'admin' && $countAdmins <= 1) {
                        $errors[] = "Tidak bisa mengubah role — harus ada minimal 1 admin.";
                    }
                }
            }
        }

        if (empty($errors)) {
            if ($uid) {
                $upd = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, updated_at = NOW() WHERE id = ?");
                $upd->bind_param("sssi", $name, $email, $role, $uid);
                if ($upd->execute()) $success = "Data pengguna diperbarui.";
                else $errors[] = "Gagal memperbarui: " . $upd->error;
                $upd->close();
            } else {
                // create new user (admin convenience) with random password and notify admin
                $random = bin2hex(random_bytes(4));
                $hash = password_hash($random, PASSWORD_DEFAULT);
                $ins = $conn->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
                $ins->bind_param("ssss", $name, $email, $hash, $role);
                if ($ins->execute()) {
                    $success = "Pengguna baru ditambahkan. Password sementara: " . $random;
                } else {
                    $errors[] = "Gagal menambahkan pengguna: " . $ins->error;
                }
                $ins->close();
            }
        }
    }

    // Reset password
    if (isset($_POST['reset_pass']) && isset($_POST['id'])) {
        $uid = (int) $_POST['id'];
        $newpw = trim($_POST['new_password'] ?? '');
        $generate = isset($_POST['generate']) ? true : false;

        if ($generate || $newpw === '') {
            // generate random password
            $plain = substr(bin2hex(random_bytes(4)), 0, 8);
        } else {
            $plain = $newpw;
        }

        if (!empty($plain)) {
            $hash = password_hash($plain, PASSWORD_DEFAULT);
            $up = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $up->bind_param("si", $hash, $uid);
            if ($up->execute()) {
                $success = "Password berhasil di-reset. Password baru: " . $plain;
            } else {
                $errors[] = "Gagal reset password: " . $up->error;
            }
            $up->close();
        } else {
            $errors[] = "Password baru kosong.";
        }
    }

    // Delete user
    if (isset($_POST['delete_user']) && isset($_POST['id'])) {
        $uid = (int) $_POST['id'];

        // cannot delete self
        if ($uid === $current_id) {
            $errors[] = "Anda tidak dapat menghapus akun sendiri.";
        } else {
            // prevent deleting last admin
            $roleCheckStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
            $roleCheckStmt->bind_param("i", $uid);
            $roleCheckStmt->execute();
            $roleRes = $roleCheckStmt->get_result()->fetch_assoc();
            $roleCheckStmt->close();

            if ($roleRes && $roleRes['role'] === 'admin') {
                $countAdmins = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch_assoc()['c'] ?? 0;
                if ($countAdmins <= 1) {
                    $errors[] = "Tidak bisa menghapus pengguna. Harus ada minimal 1 admin.";
                }
            }

            if (empty($errors)) {
                // delete user
                $del = $conn->prepare("DELETE FROM users WHERE id = ?");
                $del->bind_param("i", $uid);
                if ($del->execute()) $success = "Pengguna berhasil dihapus.";
                else $errors[] = "Gagal menghapus pengguna: " . $del->error;
                $del->close();
            }
        }
    }
}

// Ambil daftar users (limit 200, bisa ditambah pagination nanti)
$users = [];
$q = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 200");
if ($q) {
    while ($r = $q->fetch_assoc()) $users[] = $r;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Manage Users - Admin</title>
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
    td, th{ vertical-align: middle; }
    .small-muted{ font-size:0.85rem; color:#6c757d; }
  </style>
</head>
<body>

<nav class="navbar navbar-dark">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="admin_dashboard.php">
      <img src="<?= e($logo) ?>" style="height:36px" alt="logo" class="me-2">
      <div class="d-flex flex-column" style="line-height:1;">
        <strong style="font-size:1rem;color:var(--accent)">Admin</strong>
        <small style="font-size:0.7rem;color:#cfd8dc">Kelola Pengguna</small>
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
    <h4 class="mb-0">Kelola Pengguna</h4>
    <button class="btn btn-accent" data-bs-toggle="collapse" data-bs-target="#formCollapse">Tambah Pengguna</button>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
  <?php if ($errors): foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; endif; ?>

  <div class="collapse mb-4" id="formCollapse">
    <div class="card card-body">
      <?php
        // edit mode jika ada param edit
        $edit_user = null;
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
            $eid = (int) $_GET['id'];
            $q = $conn->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
            $q->bind_param("i", $eid);
            $q->execute();
            $edit_user = $q->get_result()->fetch_assoc();
            $q->close();
            echo "<script>setTimeout(()=>{document.querySelector('[data-bs-target=\"#formCollapse\"]').click();},120);</script>";
        }
      ?>

      <form method="post" class="row g-3">
        <input type="hidden" name="mode" value="<?= $edit_user ? 'edit' : 'add' ?>">
        <?php if ($edit_user): ?><input type="hidden" name="id" value="<?= (int)$edit_user['id'] ?>"><?php endif; ?>

        <div class="col-md-6">
          <label class="form-label">Nama</label>
          <input type="text" name="name" class="form-control" required value="<?= e($edit_user['name'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required value="<?= e($edit_user['email'] ?? '') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Role</label>
          <select name="role" class="form-select">
            <option value="user" <?= (isset($edit_user['role']) && $edit_user['role']=='user') ? 'selected' : '' ?>>User</option>
            <option value="admin" <?= (isset($edit_user['role']) && $edit_user['role']=='admin') ? 'selected' : '' ?>>Admin</option>
          </select>
        </div>

        <div class="col-md-8 d-flex align-items-end gap-2">
          <button type="submit" name="save_user" class="btn btn-accent"><?= $edit_user ? 'Simpan Perubahan' : 'Tambah Pengguna' ?></button>
          <?php if ($edit_user): ?><a href="manage_users.php" class="btn btn-outline-secondary">Batal</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- daftar pengguna -->
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:60px">#</th>
              <th>Nama</th>
              <th>Email</th>
              <th>Role</th>
              <th>Terdaftar</th>
              <th style="width:220px">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($users) > 0): $i=1; foreach ($users as $u): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= e($u['name']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e($u['role']) ?></td>
                <td class="small-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td>
                  <a href="manage_users.php?action=edit&id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>

                  <!-- Reset password modal trigger -->
                  <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetModal<?= (int)$u['id'] ?>">Reset Password</button>

                  <!-- Delete form (POST) -->
                  <form method="post" style="display:inline-block" onsubmit="return confirm('Hapus pengguna ini?');">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button type="submit" name="delete_user" class="btn btn-sm btn-outline-danger" <?= ((int)$u['id'] === $current_id) ? 'disabled title=\"Tidak bisa menghapus akun sendiri\"' : '' ?>>Hapus</button>
                  </form>

                  <!-- Reset Modal -->
                  <div class="modal fade" id="resetModal<?= (int)$u['id'] ?>" tabindex="-1" aria-labelledby="resetLabel<?= (int)$u['id'] ?>" aria-hidden="true">
                    <div class="modal-dialog">
                      <div class="modal-content">
                        <form method="post" class="mb-0">
                          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                          <div class="modal-header">
                            <h5 class="modal-title" id="resetLabel<?= (int)$u['id'] ?>">Reset Password — <?= e($u['name']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body">
                            <div class="mb-2 small-muted">Kosongkan kolom untuk generate password acak 8 karakter.</div>
                            <div class="mb-3">
                              <label class="form-label">Password Baru (opsional)</label>
                              <input type="text" name="new_password" class="form-control" placeholder="Isi password baru atau biarkan kosong">
                            </div>
                            <div class="form-check mb-2">
                              <input class="form-check-input" type="checkbox" name="generate" id="gen<?= (int)$u['id'] ?>">
                              <label class="form-check-label" for="gen<?= (int)$u['id'] ?>">Generate password acak</label>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" name="reset_pass" class="btn btn-warning">Reset Password</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="6" class="text-center text-muted">Belum ada pengguna.</td></tr>
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
