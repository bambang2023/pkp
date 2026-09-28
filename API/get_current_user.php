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

// Ambil nama wilayah dari tabel referensi berdasarkan kode yang tersimpan di users.
$stmt = $pdo->prepare("SELECT u.puskesmas AS puskesmas_kode, p.nama AS puskesmas_nama,
                              u.kabupaten AS kabupaten_kode, k.nama AS kabupaten_nama
                       FROM users u
                       LEFT JOIN ref_puskesmas p ON p.kode = u.puskesmas
                       LEFT JOIN ref_kabupaten k ON k.kode = u.kabupaten
                       WHERE u.id = ?");
$stmt->execute([(int)$_SESSION['user_id']]);
$puskesmas = $stmt->fetch();

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
        'kabupaten_kode' => $puskesmas['kabupaten_kode'] ?? $_SESSION['kabupaten'] ?? '',
        'kabupaten_nama' => $puskesmas['kabupaten_nama'] ?? '',
        'puskesmas_kode' => $puskesmas['puskesmas_kode'] ?? $_SESSION['puskesmas_kode'] ?? '',
        'puskesmas' => $puskesmas['puskesmas_nama'] ?? $_SESSION['puskesmas'] ?? '',
    ],
], JSON_UNESCAPED_UNICODE);
