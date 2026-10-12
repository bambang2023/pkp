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

$clusterFilters = [];
foreach (['kd_0', 'kd_1', 'kd_2', 'kd_3'] as $filter) {
    $value = $_GET[$filter] ?? '';
    if (!is_string($value)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "Filter {$filter} tidak valid."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $value = trim($value);
    if (strlen($value) > 100) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "Filter {$filter} tidak valid."], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($value !== '') {
        $clusterFilters[$filter] = $value;
    }
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

    $sql = 'SELECT dk.nip, dk.kdpusk, dk.bulan, dk.kdindikator,
            i.indikator AS indikator, dk.target, dk.sasaran, dk.jml_sasaran,
            dk.target_sasaran, dk.capaian, dk.hasil_riil, dk.hasil_kinerja
         FROM data_kinerja AS dk
          LEFT JOIN indikator AS i
              ON i.kd_ind COLLATE utf8mb4_general_ci = dk.kdindikator COLLATE utf8mb4_general_ci
          LEFT JOIN kluster_l3 AS l3
              ON l3.kd_3 COLLATE utf8mb4_general_ci = i.kd_3 COLLATE utf8mb4_general_ci
          LEFT JOIN kluster_l2 AS l2
              ON l2.kd_2 COLLATE utf8mb4_general_ci = l3.kd_2 COLLATE utf8mb4_general_ci
          LEFT JOIN kluster_l1 AS l1
              ON l1.kd_1 COLLATE utf8mb4_general_ci = l2.kd_1 COLLATE utf8mb4_general_ci
          LEFT JOIN kluster AS l0
              ON l0.kd COLLATE utf8mb4_general_ci = l1.kd_0 COLLATE utf8mb4_general_ci
         WHERE dk.kdpusk = :kdpusk';
    $params = [':kdpusk' => $kdpusk];
    foreach ($clusterFilters as $filter => $value) {
        $column = [
            'kd_0' => 'l0.kd',
            'kd_1' => 'l1.kd_1',
            'kd_2' => 'l2.kd_2',
            'kd_3' => 'l3.kd_3',
        ][$filter];
        $sql .= " AND {$column} = :{$filter}";
        $params[":{$filter}"] = $value;
    }
    $sql .= ' ORDER BY CAST(dk.bulan AS UNSIGNED) ASC, dk.kdindikator ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

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