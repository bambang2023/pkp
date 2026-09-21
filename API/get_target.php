<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'Unauthorized. Silakan login terlebih dahulu.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($_SESSION['role'], ['provinsi', 'puskesmas'], true)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Akses ditolak. Halaman ini hanya dapat diakses oleh role provinsi atau puskesmas.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->query(
        "SELECT kd_ind, ind, target, Januari, Pebruari, Maret, April, Mei, Juni,
                Juli, Agustus, September, Oktober, Nopember, Desember
         FROM target
         ORDER BY kd_ind"
    );

    echo json_encode([
        'ok' => true,
        'data' => $stmt->fetchAll(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Gagal mengambil data target.',
    ], JSON_UNESCAPED_UNICODE);
}
