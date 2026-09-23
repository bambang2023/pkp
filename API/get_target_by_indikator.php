<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'Unauthorized. Silakan login terlebih dahulu.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$kdInd = trim((string) ($_GET['kd_ind'] ?? ''));
if ($kdInd === '') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Kode indikator wajib diisi.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT target
         FROM target
         WHERE kd_ind = :kd_ind
         LIMIT 1'
    );
    $stmt->execute([':kd_ind' => $kdInd]);
    $target = $stmt->fetchColumn();

    if ($target === false) {
        http_response_code(404);
        echo json_encode([
            'ok' => false,
            'error' => 'Target indikator tidak ditemukan.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'target' => $target,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Gagal mengambil target indikator.',
    ], JSON_UNESCAPED_UNICODE);
}
