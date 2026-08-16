<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

// Cek session login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['nama'])) {
    http_response_code(401);
    echo json_encode([
        'ok'    => false,
        'error' => 'Unauthorized. Silakan login terlebih dahulu.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->query("SELECT id, nama, nip, role, provinsi, kabupaten, puskesmas FROM users ORDER BY role, nama ASC");
    $data = $stmt->fetchAll();

    echo json_encode([
        'ok'   => true,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Gagal mengambil data pengguna.',
    ], JSON_UNESCAPED_UNICODE);
}
