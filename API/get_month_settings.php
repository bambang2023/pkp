<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized. Silakan login terlebih dahulu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SESSION['role'] !== 'provinsi') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Akses hanya tersedia untuk role provinsi.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->query('SELECT bulan, nama, aktif FROM pengaturan_bulan_kinerja ORDER BY bulan');
    echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Gagal memuat pengaturan bulan.'], JSON_UNESCAPED_UNICODE);
}