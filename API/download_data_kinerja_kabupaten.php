<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function respondWithError(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function csvCell(mixed $value): string
{
    $text = $value === null ? '' : (string) $value;
    if (preg_match('/^[\t\r ]*[=+\-@]/', $text)) {
        $text = "'" . $text;
    }
    return '"' . str_replace('"', '""', $text) . '"';
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    respondWithError(401, 'Unauthorized. Silakan login terlebih dahulu.');
}

if ($_SESSION['role'] !== 'kabupaten') {
    respondWithError(403, 'Unduhan ini hanya tersedia untuk role kabupaten.');
}

$month = $_GET['bulan'] ?? '';
if (!is_string($month) || ($month !== '' && !preg_match('/^(0[1-9]|1[0-2])$/', $month))) {
    respondWithError(400, 'Filter bulan tidak valid.');
}

$cluster = $_GET['kd_kluster'] ?? '';
if (!is_string($cluster) || strlen($cluster) > 100) {
    respondWithError(400, 'Filter kluster tidak valid.');
}
$cluster = trim($cluster);

try {
    $userStmt = $pdo->prepare(
        'SELECT kabupaten
         FROM users
         WHERE id = :user_id
         LIMIT 1'
    );
    $userStmt->execute([':user_id' => (int) $_SESSION['user_id']]);
    $userKabupaten = trim((string) ($userStmt->fetchColumn() ?: ''));
    if ($userKabupaten === '') {
        respondWithError(400, 'Kolom kabupaten pada akun tidak tersedia.');
    }

    $districtStmt = $pdo->prepare(
        "SELECT kode AS kabupaten_kode, nama AS kabupaten_nama
         FROM ref_kabupaten
         WHERE kode COLLATE utf8mb4_general_ci =
                   CONVERT(:kabupaten_kode USING utf8mb4) COLLATE utf8mb4_general_ci
            OR LOWER(TRIM(REPLACE(REPLACE(REPLACE(nama, 'kabupaten', ''), 'kab.', ''), 'kab ', ''))) COLLATE utf8mb4_general_ci =
                   LOWER(TRIM(REPLACE(REPLACE(REPLACE(CONVERT(:kabupaten_nama USING utf8mb4), 'kabupaten', ''), 'kab.', ''), 'kab ', ''))) COLLATE utf8mb4_general_ci
         LIMIT 1"
    );
    $districtStmt->execute([
        ':kabupaten_kode' => $userKabupaten,
        ':kabupaten_nama' => $userKabupaten,
    ]);
    $district = $districtStmt->fetch(PDO::FETCH_ASSOC);
    $kabupatenCode = trim((string) ($district['kabupaten_kode'] ?? ''));
    if ($kabupatenCode === '') {
        respondWithError(400, 'Nilai users.kabupaten tidak ditemukan pada kode atau nama ref_kabupaten.');
    }

    $sql = 'SELECT p.kode AS kode_puskesmas, p.nama AS nama_puskesmas,
                   dk.bulan, dk.kdindikator AS kode_indikator, i.indikator,
                   dk.target, dk.sasaran AS satuan, dk.jml_sasaran AS total_sasaran,
                   dk.target_sasaran, dk.capaian, dk.hasil_riil, dk.hasil_kinerja
            FROM ref_puskesmas AS p
            INNER JOIN data_kinerja AS dk
                ON dk.kdpusk COLLATE utf8mb4_general_ci = p.kode COLLATE utf8mb4_general_ci
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
            WHERE p.kabupaten_kode COLLATE utf8mb4_general_ci =
                  CONVERT(:kabupaten USING utf8mb4) COLLATE utf8mb4_general_ci';
    $params = [':kabupaten' => $kabupatenCode];

    if ($month !== '') {
        $sql .= ' AND dk.bulan = :bulan';
        $params[':bulan'] = $month;
    }
    if ($cluster !== '') {
        $sql .= ' AND l0.kd = :kd_kluster';
        $params[':kd_kluster'] = $cluster;
    }
    $sql .= ' ORDER BY p.nama, CAST(dk.bulan AS UNSIGNED), dk.kdindikator';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $monthNames = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
        '04' => 'April', '05' => 'Mei', '06' => 'Juni',
        '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
        '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    ];
    $filename = 'data-kinerja-kabupaten-' . ($month !== '' ? $month : 'semua-bulan') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'wb');
    $headers = [
        'Kabupaten', 'Kode Puskesmas', 'Nama Puskesmas', 'Bulan',
        'Kode Indikator', 'Indikator', 'Target', 'Satuan', 'Total Sasaran',
        'Target Sasaran', 'Capaian', 'Hasil Riil', 'Hasil Kinerja',
    ];
    fwrite($output, implode(',', array_map('csvCell', $headers)) . "\r\n");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $monthCode = str_pad((string) $row['bulan'], 2, '0', STR_PAD_LEFT);
        $values = [
            $district['kabupaten_nama'],
            $row['kode_puskesmas'],
            $row['nama_puskesmas'],
            $monthNames[$monthCode] ?? $row['bulan'],
            $row['kode_indikator'],
            $row['indikator'],
            $row['target'],
            $row['satuan'],
            $row['total_sasaran'],
            $row['target_sasaran'],
            $row['capaian'],
            $row['hasil_riil'],
            $row['hasil_kinerja'],
        ];
        fwrite($output, implode(',', array_map('csvCell', $values)) . "\r\n");
    }
    fclose($output);
} catch (Throwable $e) {
    error_log('Gagal mengunduh data kinerja kabupaten: ' . $e->getMessage());
    respondWithError(500, 'Gagal mengunduh data kinerja kabupaten.');
}