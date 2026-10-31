<?php
$conn = mysqli_connect("localhost", "root", "", "perpustakaan_digital");

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>
