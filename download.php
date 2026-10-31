<?php
session_start();
require_once 'koneksi.php'; // pastikan $conn adalah mysqli

// helper escape singkat
function e($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// cek param id
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo "Permintaan tidak valid.";
    exit;
}

// ambil data buku
$stmt = $conn->prepare("SELECT id, title, file_path, access_level FROM books WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo "DB prepare error: " . e($conn->error);
    exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$book = $res->fetch_assoc();
$stmt->close();

if (!$book) {
    http_response_code(404);
    echo "Buku tidak ditemukan.";
    exit;
}

// akses kontrol
$access = $book['access_level'] ?? 'public';
$isLoggedIn = isset($_SESSION['user_id']);
if ($access === 'registered' && !$isLoggedIn) {
    // redirect ke login atau tampil pesan
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// pastikan ada file_path
$file_path_db = $book['file_path'] ?? '';
if (!$file_path_db) {
    http_response_code(404);
    echo "File tidak tersedia untuk buku ini.";
    exit;
}

// bangun path fisik aman
$projectRoot = realpath(__DIR__);
$requested = realpath($projectRoot . '/' . ltrim($file_path_db, '/\\'));

// pastikan file ada dan berada di dalam project (hindari path traversal)
if (!$requested || strpos($requested, $projectRoot) !== 0 || !is_file($requested)) {
    http_response_code(404);
    echo "File tidak ditemukan (path invalid).";
    exit;
}

// catat unduhan ke tabel downloads
$user_id = $isLoggedIn ? (int) $_SESSION['user_id'] : null;
$ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

$ins = $conn->prepare("INSERT INTO downloads (book_id, user_id, ip_address, created_at) VALUES (?, ?, ?, NOW())");
if ($ins) {
    // jika user_id null, bind_param tidak menerima null untuk i -> gunakan NULL int 0 dan later set to NULL in SQL?
    // solusi: gunakan 'i' untuk book_id, 'i' untuk user_id dan 's' for ip. If user_id null, bind 0 and let DB set to NULL via INSERT ... VALUES (?, NULL, ?, NOW())
    // namun easier: prepare two variants
    if ($user_id) {
        $ins->bind_param("iis", $id, $user_id, $ip_address);
    } else {
        // gunakan query dengan literal NULL untuk user_id
        $ins->close();
        $ins = $conn->prepare("INSERT INTO downloads (book_id, user_id, ip_address, created_at) VALUES (?, NULL, ?, NOW())");
        if ($ins) $ins->bind_param("is", $id, $ip_address);
    }

    if (!$ins->execute()) {
        // logging error (tapi jangan hentikan pengiriman file)
        error_log("Gagal mencatat unduhan: " . $ins->error);
    }
    $ins->close();
} else {
    error_log("Gagal prepare pencatatan unduhan: " . $conn->error);
}

// Kirim file (stream)
$filename = basename($requested);
$filesize = filesize($requested);
$mime = mime_content_type($requested) ?: 'application/octet-stream';

// Headers untuk download
// Untuk PDF biasanya disarankan inline agar bisa dibuka di browser, tapi pengguna biasanya minta download.
// Kita set Content-Disposition: attachment; agar selalu download.
// Jika ingin buka di browser, ganti attachment => inline
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . $filesize);
header('Cache-Control: public, must-revalidate, max-age=0');
header('Pragma: public');
header('Expires: 0');

// flush output buffers
@ob_clean();
@flush();

// baca file dengan stream (lebih aman untuk file besar)
$fp = fopen($requested, 'rb');
if ($fp === false) {
    http_response_code(500);
    echo "Gagal membuka file.";
    exit;
}

while (!feof($fp)) {
    // baca chunk (8MB)
    echo fread($fp, 8 * 1024 * 1024);
    @flush();
}
fclose($fp);
exit;
