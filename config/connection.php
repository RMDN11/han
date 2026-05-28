<?php
// Konfigurasi database
$host = 'localhost';
$username = 'wegqxcgv_tbeliau';
$password = 'g#liuO07up_WTht6';
$database = 'wegqxcgv_tbeliau';

// Membuat koneksi
$conn = new mysqli($host, $username, $password, $database);

// Mengatur karakter set koneksi
$conn->set_charset("utf8mb4");

// Memeriksa koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Set karakter encoding
$conn->set_charset("utf8mb4");
?>