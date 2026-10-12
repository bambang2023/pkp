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
    $clusterFilters[$filter] = trim($value);
}

function csvCell(mixed $value): string
{
    $text = $value === null ? '' : (string) $value;
    if (preg_match('/^[\t\r ]*[=+\-@]/', $text)) {
        $text = "'" . $text;
    }
    return '"' . str_replace('"', '""', $text) . '"';
}

function writeCsvRow($stream, array $values): void
{
    fwrite($stream, implode(',', array_map('csvCell', $values)) . "\r\n");
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

    $clusters = $pdo->query('SELECT kd, uraian FROM kluster ORDER BY kd')->fetchAll();
    $level1 = $pdo->query('SELECT kd_0, kd_1, uraian FROM kluster_l1 ORDER BY kd_0, kd_1')->fetchAll();
    $level2 = $pdo->query('SELECT kd_1, kd_2, uraian FROM kluster_l2 ORDER BY kd_1, kd_2')->fetchAll();
    $level3 = $pdo->query('SELECT kd_2, kd_3, uraian FROM kluster_l3 ORDER BY kd_2, kd_3')->fetchAll();
    $indicators = $pdo->query('SELECT kd_3, kd_ind, indikator FROM indikator ORDER BY kd_3, kd_ind')->fetchAll();

    $clusters = array_values(array_filter($clusters, static fn ($row) =>
        $clusterFilters['kd_0'] === '' || (string) $row['kd'] === $clusterFilters['kd_0']
    ));
    $level1 = array_values(array_filter($level1, static fn ($row) =>
        ($clusterFilters['kd_0'] === '' || (string) $row['kd_0'] === $clusterFilters['kd_0']) &&
        ($clusterFilters['kd_1'] === '' || (string) $row['kd_1'] === $clusterFilters['kd_1'])
    ));
    $allowedLevel1 = array_fill_keys(array_map(static fn ($row) => (string) $row['kd_1'], $level1), true);
    $level2 = array_values(array_filter($level2, static fn ($row) =>
        isset($allowedLevel1[(string) $row['kd_1']]) &&
        ($clusterFilters['kd_2'] === '' || (string) $row['kd_2'] === $clusterFilters['kd_2'])
    ));
    $allowedLevel2 = array_fill_keys(array_map(static fn ($row) => (string) $row['kd_2'], $level2), true);
    $level3 = array_values(array_filter($level3, static fn ($row) =>
        isset($allowedLevel2[(string) $row['kd_2']]) &&
        ($clusterFilters['kd_3'] === '' || (string) $row['kd_3'] === $clusterFilters['kd_3'])
    ));
    $allowedLevel3 = array_fill_keys(array_map(static fn ($row) => (string) $row['kd_3'], $level3), true);
    $indicators = array_values(array_filter($indicators, static fn ($row) =>
        isset($allowedLevel3[(string) $row['kd_3']])
    ));

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
    $completed = array_fill_keys(array_map('strval', array_column($completionStmt->fetchAll(), 'kd_ind')), true);

    $level1ByCode = [];
    $level1ByParent = [];
    foreach ($level1 as $row) {
        $code = (string) $row['kd_1'];
        $level1ByCode[$code] = $row;
        $level1ByParent[(string) $row['kd_0']][] = $row;
    }
    $level2ByCode = [];
    $level2ByParent = [];
    foreach ($level2 as $row) {
        $code = (string) $row['kd_2'];
        $level2ByCode[$code] = $row;
        $level2ByParent[(string) $row['kd_1']][] = $row;
    }
    $level3ByCode = [];
    $level3ByParent = [];
    foreach ($level3 as $row) {
        $code = (string) $row['kd_3'];
        $level3ByCode[$code] = $row;
        $level3ByParent[(string) $row['kd_2']][] = $row;
    }
    $indicatorsByParent = [];
    $stats = [];
    foreach ($indicators as $row) {
        $code3 = (string) $row['kd_3'];
        $parent3 = $level3ByCode[$code3] ?? null;
        $parent2 = $parent3 === null ? null : ($level2ByCode[(string) $parent3['kd_2']] ?? null);
        $parent1 = $parent2 === null ? null : ($level1ByCode[(string) $parent2['kd_1']] ?? null);
        if ($parent1 === null) {
            continue;
        }

        $filled = isset($completed[(string) $row['kd_ind']]) ? 1 : 0;
        foreach ([
            'level0:' . $parent1['kd_0'],
            'level1:' . $parent1['kd_1'],
            'level2:' . $parent2['kd_2'],
            'level3:' . $parent3['kd_3'],
        ] as $key) {
            $stats[$key]['total'] = ($stats[$key]['total'] ?? 0) + 1;
            $stats[$key]['filled'] = ($stats[$key]['filled'] ?? 0) + $filled;
        }
        $indicatorsByParent[$code3][] = $row + ['filled' => $filled];
    }

    $monthNames = [
        '' => 'Semua bulan', '01' => 'Januari', '02' => 'Februari',
        '03' => 'Maret', '04' => 'April', '05' => 'Mei',
        '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November',
        '12' => 'Desember',
    ];
    $monthName = $monthNames[$month];
    $filename = 'monitoring-puskesmas-' . ($month !== '' ? $month : 'semua-bulan') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'wb');
    writeCsvRow($output, ['Periode', 'Jenjang', 'Kode', 'Kluster / Indikator', 'Terisi', 'Total', 'Progres (%)', 'Status']);
    $writeNode = static function (string $period, string $levelName, $code, string $name, int $filled, int $total) use ($output): void {
        $progress = $total > 0 ? ($filled / $total) * 100 : 0;
        $status = $filled === 0 ? 'Belum terisi' : ($filled === $total ? 'Terisi' : 'Terisi sebagian');
        writeCsvRow($output, [$period, $levelName, $code, $name, $filled, $total, number_format($progress, 1, '.', ''), $status]);
    };

    foreach ($clusters as $cluster) {
        $code0 = (string) $cluster['kd'];
        $clusterStats = $stats['level0:' . $code0] ?? ['filled' => 0, 'total' => 0];
        $writeNode($monthName, 'Kluster 0', $code0, (string) $cluster['uraian'], $clusterStats['filled'], $clusterStats['total']);
        foreach ($level1ByParent[$code0] ?? [] as $row1) {
            $code1 = (string) $row1['kd_1'];
            $stats1 = $stats['level1:' . $code1] ?? ['filled' => 0, 'total' => 0];
            $writeNode($monthName, 'Kluster 1', $code1, (string) $row1['uraian'], $stats1['filled'], $stats1['total']);
            foreach ($level2ByParent[$code1] ?? [] as $row2) {
                $code2 = (string) $row2['kd_2'];
                $stats2 = $stats['level2:' . $code2] ?? ['filled' => 0, 'total' => 0];
                $writeNode($monthName, 'Kluster 2', $code2, (string) $row2['uraian'], $stats2['filled'], $stats2['total']);
                foreach ($level3ByParent[$code2] ?? [] as $row3) {
                    $code3 = (string) $row3['kd_3'];
                    $stats3 = $stats['level3:' . $code3] ?? ['filled' => 0, 'total' => 0];
                    $writeNode($monthName, 'Kluster 3', $code3, (string) $row3['uraian'], $stats3['filled'], $stats3['total']);
                    foreach ($indicatorsByParent[$code3] ?? [] as $indicator) {
                        $filled = (int) $indicator['filled'];
                        $writeNode($monthName, 'Indikator', $indicator['kd_ind'], (string) $indicator['indikator'], $filled, 1);
                    }
                }
            }
        }
    }
    fclose($output);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'Gagal mengunduh data monitoring indikator.',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
