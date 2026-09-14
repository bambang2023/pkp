<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized. Silakan login terlebih dahulu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $userStmt = $pdo->prepare('SELECT puskesmas FROM users WHERE id = :user_id LIMIT 1');
    $userStmt->execute([':user_id' => (int) $_SESSION['user_id']]);
    $user = $userStmt->fetch();
    $kdpusk = trim((string) ($user['puskesmas'] ?? ''));

    if ($kdpusk === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Kode puskesmas user tidak tersedia.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT nip, kdpusk, bulan, kdindikator, target, sasaran, jml_sasaran,
                target_sasaran, capaian, hasil_riil, hasil_kinerja
         FROM data_kinerja
         WHERE kdpusk = :kdpusk
         ORDER BY bulan DESC, kdindikator ASC'
    );
    $stmt->execute([':kdpusk' => $kdpusk]);

    echo json_encode([
        'ok' => true,
        'data' => $stmt->fetchAll(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Gagal mengambil data kinerja.',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}