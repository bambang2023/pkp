<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>Metode tidak diizinkan. <a href="../index.html">Kembali</a></p>';
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Login Gagal</title>';
    echo '<link rel="stylesheet" href="../styles.css">';
    echo '</head><body><div class="container"><div class="login-box">';
    echo '<h1>Login Gagal</h1><p>Username dan password harus diisi.</p>';
    echo '<p><a href="../index.html" class="login-btn" style="display:inline-block;text-decoration:none;color:white;padding:0.75rem 2rem;">Kembali</a></p>';
    echo '</div></body></html>';
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nama, nip, password, role, provinsi, kabupaten, puskesmas FROM users WHERE nip = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Login Gagal</title>';
        echo '<link rel="stylesheet" href="../styles.css">';
        echo '</head><body><div class="container"><div class="login-box">';
        echo '<h1>Login Gagal</h1><p>NIP atau password salah.</p>';
        echo '<p><a href="../index.html" class="login-btn" style="display:inline-block;text-decoration:none;color:white;padding:0.75rem 2rem;">Kembali</a></p>';
        echo '</div></body></html>';
        exit;
    }

    // Start session
    session_start();
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['nama']       = $user['nama'];
    $_SESSION['nip']        = $user['nip'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['provinsi']   = $user['provinsi'];
    $_SESSION['kabupaten']  = $user['kabupaten'];
    $_SESSION['puskesmas']  = $user['puskesmas'];

    // Redirect ke dashboard berdasarkan role
    $redirectMap = [
        'provinsi'  => '../provinsi.html',
        'kabupaten' => '../index.html',
        'puskesmas' => '../puskesmas.html',
    ];
    $redirect = $redirectMap[$user['role']] ?? '../index.html';
    header('Location: ' . $redirect);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo '<div style="font-family:sans-serif;padding:2rem;max-width:600px;margin:2rem auto;background:#fff;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">';
    echo '<h2>Error</h2><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<a href="../index.html">Kembali</a></div>';
}
