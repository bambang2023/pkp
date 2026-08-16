<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>Metode tidak diizinkan. <a href="../register.html">Kembali</a></p>';
    exit;
}

$nama      = trim($_POST['nama'] ?? '');
$nip       = trim($_POST['nip'] ?? '');
$password  = $_POST['password'] ?? '';
$role      = $_POST['role'] ?? '';
$provinsi  = $_POST['provinsi'] ?? '';
$kabupaten = $_POST['kabupaten'] ?? '';
$puskesmas = $_POST['puskesmas'] ?? '';

// Validasi sederhana
$errors = [];
if ($nama === '') $errors[] = 'Nama harus diisi';
if ($nip === '') $errors[] = 'NIP harus diisi';
if ($password === '') $errors[] = 'Password harus diisi';
if (!in_array($role, ['puskesmas', 'kabupaten', 'provinsi'], true)) $errors[] = 'Role tidak valid';

if (count($errors) > 0) {
    echo '<div style="font-family:sans-serif;padding:2rem;max-width:600px;margin:2rem auto;background:#fff;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">';
    echo '<h2>Registrasi Gagal</h2><ul>';
    foreach ($errors as $e) {
        echo '<li>' . htmlspecialchars($e) . '</li>';
    }
    echo '</ul><a href="../register.html">Kembali ke form registrasi</a></div>';
    exit;
}

try {
    // Cek apakah NIP sudah terdaftar
    $stmt = $pdo->prepare("SELECT id FROM users WHERE nip = ?");
    $stmt->execute([$nip]);
    if ($stmt->fetch()) {
        echo '<div style="font-family:sans-serif;padding:2rem;max-width:600px;margin:2rem auto;background:#fff;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">';
        echo '<h2>Registrasi Gagal</h2><p>NIP ' . htmlspecialchars($nip) . ' sudah terdaftar.</p>';
        echo '<a href="../register.html">Kembali ke form registrasi</a></div>';
        exit;
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $pdo->prepare("INSERT INTO users (nama, nip, password, role, provinsi, kabupaten, puskesmas) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nama, $nip, $hashedPassword, $role, $provinsi, $kabupaten, $puskesmas]);

    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Registrasi Berhasil</title>';
    echo '<link rel="stylesheet" href="../styles.css">';
    echo '</head><body><div class="container"><div class="login-box">';
    echo '<h1>Registrasi Berhasil</h1>';
    echo '<p>Akun dengan NIP <strong>' . htmlspecialchars($nip) . '</strong> berhasil dibuat.</p>';
    echo '<p><a href="../index.html" class="login-btn" style="display:inline-block;text-decoration:none;color:white;padding:0.75rem 2rem;">Login Sekarang</a></p>';
    echo '</div></div></body></html>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<div style="font-family:sans-serif;padding:2rem;max-width:600px;margin:2rem auto;background:#fff;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">';
    echo '<h2>Error</h2><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<a href="../register.html">Kembali</a></div>';
}

