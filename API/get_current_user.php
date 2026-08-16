<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

// Mulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['nama'])) {
    http_response_code(401);
    echo json_encode([
        'ok'    => false,
        'error' => 'Unauthorized. Silakan login terlebih dahulu.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Kembalikan data user
echo json_encode([
    'ok'        => true,
    'user'      => [
        'user_id'   => (int)$_SESSION['user_id'],
        'nama'      => $_SESSION['nama'],
        'nip'       => $_SESSION['nip'] ?? '',
        'role'      => $_SESSION['role'],
        'provinsi'  => $_SESSION['provinsi'] ?? '',
        'kabupaten' => $_SESSION['kabupaten'] ?? '',
        'puskesmas' => $_SESSION['puskesmas'] ?? '',
    ],
], JSON_UNESCAPED_UNICODE);
