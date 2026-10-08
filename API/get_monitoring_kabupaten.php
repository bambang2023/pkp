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

if ($_SESSION['role'] !== 'kabupaten') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Halaman ini hanya tersedia untuk role kabupaten.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$month = trim((string) ($_GET['bulan'] ?? ''));
if ($month !== '' && !preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Filter bulan tidak valid.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $userStmt = $pdo->prepare(
        'SELECT kabupaten
         FROM users AS u
         WHERE u.id = :user_id
         LIMIT 1'
    );
    $userStmt->execute([':user_id' => (int) $_SESSION['user_id']]);
    $userKabupaten = trim((string) ($userStmt->fetchColumn() ?: ''));

    if ($userKabupaten === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Kolom kabupaten pada akun tidak tersedia.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $districtStmt = $pdo->prepare(
        "SELECT kode AS kabupaten_kode, nama AS kabupaten_nama
         FROM ref_kabupaten
               WHERE kode COLLATE utf8mb4_general_ci = CONVERT(:kabupaten_kode USING utf8mb4) COLLATE utf8mb4_general_ci
                OR LOWER(TRIM(REPLACE(REPLACE(REPLACE(nama, 'kabupaten', ''), 'kab.', ''), 'kab ', ''))) COLLATE utf8mb4_general_ci =
                   LOWER(TRIM(REPLACE(REPLACE(REPLACE(CONVERT(:kabupaten_nama USING utf8mb4), 'kabupaten', ''), 'kab.', ''), 'kab ', ''))) COLLATE utf8mb4_general_ci
         LIMIT 1"
    );
    $districtStmt->execute([
        ':kabupaten_kode' => $userKabupaten,
        ':kabupaten_nama' => $userKabupaten,
    ]);
    $user = $districtStmt->fetch();
    $kabupatenCode = trim((string) ($user['kabupaten_kode'] ?? ''));

    if ($kabupatenCode === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Nilai users.kabupaten tidak ditemukan pada kode atau nama ref_kabupaten.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $totalIndicators = (int) $pdo->query('SELECT COUNT(*) FROM indikator')->fetchColumn();
    $performanceJoin = '';
    $performanceParams = [':kabupaten' => $kabupatenCode];
    if ($month !== '') {
        $performanceJoin = ' AND dk.bulan = :bulan';
        $performanceParams[':bulan'] = $month;
    }

    $stmt = $pdo->prepare(
        'SELECT p.kode, p.nama,
            COUNT(DISTINCT i.kd_ind) AS jumlah_terisi,
            SUM(dk.hasil_kinerja) AS jumlah_hasil_kinerja,
            COUNT(CASE WHEN dk.jml_sasaran = 0 THEN 1 END) AS sasaran_nol
         FROM ref_puskesmas AS p
         LEFT JOIN data_kinerja AS dk
                ON dk.kdpusk COLLATE utf8mb4_general_ci = p.kode COLLATE utf8mb4_general_ci' . $performanceJoin . '
         LEFT JOIN indikator AS i
            ON i.kd_ind COLLATE utf8mb4_general_ci = dk.kdindikator COLLATE utf8mb4_general_ci
             WHERE p.kabupaten_kode COLLATE utf8mb4_general_ci = CONVERT(:kabupaten USING utf8mb4) COLLATE utf8mb4_general_ci
         GROUP BY p.kode, p.nama
         ORDER BY p.nama'
    );
    $stmt->execute($performanceParams);
    $puskesmas = $stmt->fetchAll();

    $totalPuskesmas = count($puskesmas);
    $totalFilled = 0;
    $puskesmasSubmitting = 0;
    $performanceSum = 0.0;
    $performanceCount = 0;

    foreach ($puskesmas as &$row) {
        $row['jumlah_terisi'] = (int) $row['jumlah_terisi'];
        $row['total_indikator'] = $totalIndicators;
        $row['persentase_pengisian'] = $totalIndicators > 0
            ? round(($row['jumlah_terisi'] / $totalIndicators) * 100, 1)
            : 0;
        $performanceDenominator = 257 - (int) $row['sasaran_nol'];
        $rawPerformanceAverage = $row['jumlah_hasil_kinerja'] !== null && $performanceDenominator > 0
            ? (float) $row['jumlah_hasil_kinerja'] / $performanceDenominator
            : null;
        $row['rata_hasil_kinerja'] = $rawPerformanceAverage !== null
            ? round($rawPerformanceAverage, 2)
            : null;
        unset($row['jumlah_hasil_kinerja'], $row['sasaran_nol']);
        $totalFilled += $row['jumlah_terisi'];
        if ($row['jumlah_terisi'] > 0) {
            $puskesmasSubmitting++;
        }
        if ($rawPerformanceAverage !== null) {
            $performanceSum += $rawPerformanceAverage;
            $performanceCount++;
        }
    }
    unset($row);

    $totalPossible = $totalPuskesmas * $totalIndicators;

    echo json_encode([
        'ok' => true,
        'bulan' => $month,
        'kabupaten' => [
            'kode' => $kabupatenCode,
            'nama' => $user['kabupaten_nama'],
        ],
        'ringkasan' => [
            'total_puskesmas' => $totalPuskesmas,
            'puskesmas_mengisi' => $puskesmasSubmitting,
            'total_indikator' => $totalIndicators,
            'total_kemungkinan' => $totalPossible,
            'jumlah_terisi' => $totalFilled,
            'belum_terisi' => max($totalPossible - $totalFilled, 0),
            'persentase_pengisian' => $totalPossible > 0
                ? round(($totalFilled / $totalPossible) * 100, 1)
                : 0,
            'rata_hasil_kinerja' => $performanceCount > 0
                ? round($performanceSum / $performanceCount, 2)
                : null,
        ],
        'data' => $puskesmas,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Gagal mengambil monitoring kabupaten.',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}