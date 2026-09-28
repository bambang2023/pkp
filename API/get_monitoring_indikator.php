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

if ($_SESSION['role'] !== 'puskesmas') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Halaman ini hanya tersedia untuk role puskesmas.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$month = trim((string) ($_GET['bulan'] ?? ''));
if ($month !== '' && !preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Filter bulan tidak valid.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $userStmt = $pdo->prepare('SELECT puskesmas FROM users WHERE id = :user_id LIMIT 1');
    $userStmt->execute([':user_id' => (int) $_SESSION['user_id']]);
    $puskesmas = trim((string) ($userStmt->fetchColumn() ?: ''));

    if ($puskesmas === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Kode puskesmas user tidak tersedia.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $clusters = $pdo->query('SELECT kd, uraian FROM kluster ORDER BY kd')->fetchAll();
    $level1 = $pdo->query('SELECT kd_0, kd_1, uraian FROM kluster_l1 ORDER BY kd_0, kd_1')->fetchAll();
    $level2 = $pdo->query('SELECT kd_1, kd_2, uraian FROM kluster_l2 ORDER BY kd_1, kd_2')->fetchAll();
    $level3 = $pdo->query('SELECT kd_2, kd_3, uraian FROM kluster_l3 ORDER BY kd_2, kd_3')->fetchAll();
    $indicators = $pdo->query('SELECT kd_3, kd_ind, indikator FROM indikator ORDER BY kd_3, kd_ind')->fetchAll();

    $completionSql = 'SELECT DISTINCT i.kd_ind
                      FROM indikator AS i
                      INNER JOIN data_kinerja AS dk
                          ON i.kd_ind COLLATE utf8mb4_general_ci = dk.kdindikator COLLATE utf8mb4_general_ci
                      WHERE dk.kdpusk = :kdpusk';
    $completionParams = [':kdpusk' => $puskesmas];
    if ($month !== '') {
        $completionSql .= ' AND dk.bulan = :bulan';
        $completionParams[':bulan'] = $month;
    }
    $completionStmt = $pdo->prepare($completionSql);
    $completionStmt->execute($completionParams);
    $completedIndicators = array_column($completionStmt->fetchAll(), 'kd_ind');
    $totalIndicators = count($indicators);
    $filledIndicators = count($completedIndicators);

    echo json_encode([
        'ok' => true,
        'bulan' => $month,
        'data' => [
            'kluster' => $clusters,
            'kluster_l1' => $level1,
            'kluster_l2' => $level2,
            'kluster_l3' => $level3,
            'indikator' => $indicators,
            'terisi' => $completedIndicators,
            'total_indikator' => $totalIndicators,
            'jumlah_terisi' => $filledIndicators,
            'belum_terisi' => max($totalIndicators - $filledIndicators, 0),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Gagal mengambil data monitoring indikator.',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}