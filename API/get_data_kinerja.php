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
        'SELECT dk.nip, dk.kdpusk, dk.bulan, dk.kdindikator,
            i.indikator AS indikator, dk.target, dk.sasaran, dk.jml_sasaran,
            dk.target_sasaran, dk.capaian, dk.hasil_riil, dk.hasil_kinerja
         FROM data_kinerja AS dk
          LEFT JOIN indikator AS i
              ON i.kd_ind COLLATE utf8mb4_general_ci = dk.kdindikator COLLATE utf8mb4_general_ci
         WHERE dk.kdpusk = :kdpusk
         ORDER BY CAST(dk.bulan AS UNSIGNED) ASC, dk.kdindikator ASC'
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