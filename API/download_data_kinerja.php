<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized. Silakan login terlebih dahulu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SESSION['role'] !== 'puskesmas') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Halaman ini hanya tersedia untuk role puskesmas.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$month = $_GET['bulan'] ?? '';
if (!is_string($month) || ($month !== '' && !preg_match('/^(0[1-9]|1[0-2])$/', $month))) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Filter bulan tidak valid.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$clusterFilters = [];
foreach (['kd_0', 'kd_1', 'kd_2', 'kd_3'] as $filter) {
    $value = $_GET[$filter] ?? '';
    if (!is_string($value) || strlen($value) > 100) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => "Filter {$filter} tidak valid."], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $value = trim($value);
    if ($value !== '') {
        $clusterFilters[$filter] = $value;
    }
}

function dataKinerjaCsvCell(mixed $value): string
{
    $text = $value === null ? '' : (string) $value;
    if (preg_match('/^[\t\r ]*[=+\-@]/', $text)) {
        $text = "'" . $text;
    }
    return '"' . str_replace('"', '""', $text) . '"';
}

try {
    $userStmt = $pdo->prepare('SELECT puskesmas FROM users WHERE id = :user_id LIMIT 1');
    $userStmt->execute([':user_id' => (int) $_SESSION['user_id']]);
    $puskesmas = trim((string) ($userStmt->fetchColumn() ?: ''));
    if ($puskesmas === '') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Kode puskesmas user tidak tersedia.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = 'SELECT dk.bulan, dk.kdindikator, i.indikator,
                   dk.target, dk.sasaran, dk.jml_sasaran, dk.target_sasaran,
                   dk.capaian, dk.hasil_riil, dk.hasil_kinerja
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
    $params = [':kdpusk' => $puskesmas];
    if ($month !== '') {
        $sql .= ' AND dk.bulan = :bulan';
        $params[':bulan'] = $month;
    }
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

    $monthNames = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
        '04' => 'April', '05' => 'Mei', '06' => 'Juni',
        '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
        '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    ];
    $filename = 'data-kinerja-puskesmas-' . ($month !== '' ? $month : 'semua-bulan') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'wb');
    $headers = ['Bulan', 'Kode Indikator', 'Indikator', 'Target', 'Satuan', 'Total Sasaran', 'Target Sasaran', 'Capaian', 'Hasil Riil', 'Hasil Kinerja'];
    fwrite($output, implode(',', array_map('dataKinerjaCsvCell', $headers)) . "\r\n");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $monthCode = str_pad((string) $row['bulan'], 2, '0', STR_PAD_LEFT);
        $values = [
            $monthNames[$monthCode] ?? $row['bulan'],
            $row['kdindikator'],
            $row['indikator'],
            $row['target'],
            $row['sasaran'],
            $row['jml_sasaran'],
            $row['target_sasaran'],
            $row['capaian'],
            $row['hasil_riil'],
            $row['hasil_kinerja'],
        ];
        fwrite($output, implode(',', array_map('dataKinerjaCsvCell', $values)) . "\r\n");
    }
    fclose($output);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'Gagal mengunduh data kinerja.',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
