<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function uploadKinerjaError(int $status, string $message, array $errors = []): void
{
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'error' => $message,
        'errors' => $errors,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeUploadMonth(string $value): ?string
{
    $value = strtolower(trim($value));
    $months = [
        'januari' => '01', 'februari' => '02', 'maret' => '03',
        'april' => '04', 'mei' => '05', 'juni' => '06',
        'juli' => '07', 'agustus' => '08', 'september' => '09',
        'oktober' => '10', 'november' => '11', 'desember' => '12',
    ];
    if (isset($months[$value])) {
        return $months[$value];
    }
    if (!preg_match('/\A(?:0?[1-9]|1[0-2])\z/', $value)) {
        return null;
    }
    return str_pad((string) (int) $value, 2, '0', STR_PAD_LEFT);
}

function parseUploadNumber(string $value, bool $semicolonDelimited): ?float
{
    $value = trim($value);
    if ($semicolonDelimited && preg_match('/\A-?\d+,\d+\z/', $value)) {
        $value = str_replace(',', '.', $value);
    }
    if ($value === '' || !is_numeric($value)) {
        return null;
    }
    $number = (float) $value;
    return is_finite($number) && $number >= 0 ? $number : null;
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    uploadKinerjaError(405, 'Metode request harus POST.');
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    uploadKinerjaError(401, 'Unauthorized. Silakan login terlebih dahulu.');
}

if ($_SESSION['role'] !== 'puskesmas') {
    uploadKinerjaError(403, 'Upload CSV hanya tersedia untuk role puskesmas.');
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    uploadKinerjaError(400, 'Pilih file CSV terlebih dahulu.');
}

$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    uploadKinerjaError(400, 'File gagal diunggah. Pastikan ukuran file sesuai batas server.');
}
if (!isset($file['name'], $file['tmp_name'], $file['size']) ||
    strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'csv') {
    uploadKinerjaError(400, 'File harus berformat CSV.');
}
if ((int) $file['size'] <= 0 || (int) $file['size'] > 5 * 1024 * 1024) {
    uploadKinerjaError(400, 'Ukuran file CSV harus lebih dari 0 dan maksimal 5 MB.');
}
if (!is_uploaded_file((string) $file['tmp_name'])) {
    uploadKinerjaError(400, 'File CSV yang diunggah tidak valid.');
}

$handle = fopen((string) $file['tmp_name'], 'rb');
if ($handle === false) {
    uploadKinerjaError(400, 'File CSV tidak dapat dibaca.');
}
$firstLine = fgets($handle);
if ($firstLine === false) {
    fclose($handle);
    uploadKinerjaError(400, 'File CSV kosong.');
}
$delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
rewind($handle);
$header = fgetcsv($handle, null, $delimiter, '"', '');
if (!is_array($header)) {
    fclose($handle);
    uploadKinerjaError(400, 'Header CSV tidak valid.');
}
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
$headerIndexes = [];
foreach ($header as $index => $column) {
    $headerIndexes[strtolower(trim((string) $column))] = $index;
}

$requiredHeaders = [
    'bulan' => 'Bulan',
    'kode indikator' => 'Kode Indikator',
    'total sasaran' => 'Total Sasaran',
    'capaian' => 'Capaian',
];
$missingHeaders = array_values(array_filter(
    $requiredHeaders,
    static fn (string $key): bool => !array_key_exists($key, $headerIndexes),
    ARRAY_FILTER_USE_KEY
));
if ($missingHeaders !== []) {
    fclose($handle);
    uploadKinerjaError(400, 'Kolom wajib tidak ditemukan di CSV: ' . implode(', ', $missingHeaders) . '.');
}

$rows = [];
$rowErrors = [];
$csvRowNumber = 1;
$semicolonDelimited = $delimiter === ';';
while (($values = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
    $csvRowNumber++;
    if ($values === [null] || (count($values) === 1 && trim((string) $values[0]) === '')) {
        continue;
    }

    if (count($values) !== count($header)) {
        $rowErrors[] = "Baris {$csvRowNumber}: jumlah kolom tidak sesuai header.";
        continue;
    }

    $month = normalizeUploadMonth((string) $values[$headerIndexes['bulan']]);
    $indicatorCode = trim((string) $values[$headerIndexes['kode indikator']]);
    $totalSasaran = parseUploadNumber((string) $values[$headerIndexes['total sasaran']], $semicolonDelimited);
    $capaian = parseUploadNumber((string) $values[$headerIndexes['capaian']], $semicolonDelimited);

    if ($month === null) {
        $rowErrors[] = "Baris {$csvRowNumber}: Bulan harus berupa 01-12 atau nama bulan Indonesia.";
    }
    if ($indicatorCode === '') {
        $rowErrors[] = "Baris {$csvRowNumber}: Kode Indikator wajib diisi.";
    }
    if ($totalSasaran === null) {
        $rowErrors[] = "Baris {$csvRowNumber}: Total Sasaran harus angka nol atau lebih.";
    }
    if ($capaian === null) {
        $rowErrors[] = "Baris {$csvRowNumber}: Capaian harus angka nol atau lebih.";
    }
    if ($month !== null && $indicatorCode !== '' && $totalSasaran !== null && $capaian !== null) {
        $rows[] = [
            'row' => $csvRowNumber,
            'bulan' => $month,
            'kdindikator' => $indicatorCode,
            'jml_sasaran' => $totalSasaran,
            'capaian' => $capaian,
        ];
    }
}
fclose($handle);

if ($rows === [] && $rowErrors === []) {
    uploadKinerjaError(400, 'CSV tidak berisi baris data.');
}
if ($rowErrors !== []) {
    uploadKinerjaError(400, 'CSV tidak valid. Tidak ada data yang disimpan.', array_slice($rowErrors, 0, 20));
}
if (count($rows) > 1000) {
    uploadKinerjaError(400, 'Maksimal 1000 baris data dapat diunggah sekaligus.');
}

try {
    $userStmt = $pdo->prepare(
        'SELECT nip, puskesmas AS kdpusk
         FROM users
         WHERE id = :user_id AND role = :role
         LIMIT 1'
    );
    $userStmt->execute([
        ':user_id' => (int) $_SESSION['user_id'],
        ':role' => 'puskesmas',
    ]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || trim((string) $user['nip']) === '' || trim((string) $user['kdpusk']) === '') {
        uploadKinerjaError(400, 'NIP atau kode puskesmas user tidak tersedia.');
    }

    $pdo->beginTransaction();
    $monthStmt = $pdo->prepare(
        'SELECT aktif FROM pengaturan_bulan_kinerja WHERE bulan = :bulan LIMIT 1'
    );
    $indicatorStmt = $pdo->prepare(
        'SELECT i.kd_ind, t.target,
                (SELECT s.satuan FROM satuan AS s WHERE s.kd_ind = i.kd_ind LIMIT 1) AS satuan
         FROM indikator AS i
         LEFT JOIN target AS t ON t.kd_ind = i.kd_ind
         WHERE i.kd_ind = :kd_ind
         LIMIT 1'
    );
    $duplicateStmt = $pdo->prepare(
        'SELECT 1 FROM data_kinerja
         WHERE kdpusk = :kdpusk AND bulan = :bulan AND kdindikator = :kdindikator
         LIMIT 1'
    );
    $insertStmt = $pdo->prepare(
        'INSERT INTO data_kinerja (
            nip, kdpusk, bulan, kdindikator, target, sasaran, jml_sasaran,
            target_sasaran, capaian, hasil_riil, hasil_kinerja
         ) VALUES (
            :nip, :kdpusk, :bulan, :kdindikator, :target, :satuan, :jml_sasaran,
            :target_sasaran, :capaian, :hasil_riil, :hasil_kinerja
         )'
    );

    $seen = [];
    foreach ($rows as $index => $row) {
        $monthStmt->execute([':bulan' => $row['bulan']]);
        if ((int) $monthStmt->fetchColumn() !== 1) {
            $rowErrors[] = "Baris {$row['row']}: bulan tersebut sedang tidak dibuka untuk input.";
            continue;
        }

        $indicatorStmt->execute([':kd_ind' => $row['kdindikator']]);
        $indicator = $indicatorStmt->fetch(PDO::FETCH_ASSOC);
        if (!$indicator || !is_numeric($indicator['target']) ||
            !is_finite((float) $indicator['target']) || (float) $indicator['target'] < 0) {
            $rowErrors[] = "Baris {$row['row']}: kode indikator tidak ditemukan atau target belum tersedia.";
            continue;
        }
        if (trim((string) ($indicator['satuan'] ?? '')) === '') {
            $rowErrors[] = "Baris {$row['row']}: satuan indikator belum tersedia.";
            continue;
        }
        $rows[$index]['target'] = (float) $indicator['target'];
        $rows[$index]['satuan'] = trim((string) $indicator['satuan']);

        $duplicateKey = $row['bulan'] . '|' . $row['kdindikator'];
        if (isset($seen[$duplicateKey])) {
            $rowErrors[] = "Baris {$row['row']}: bulan dan indikator duplikat pada baris {$seen[$duplicateKey]}.";
            continue;
        }
        $seen[$duplicateKey] = $row['row'];

        $duplicateStmt->execute([
            ':kdpusk' => trim((string) $user['kdpusk']),
            ':bulan' => $row['bulan'],
            ':kdindikator' => $row['kdindikator'],
        ]);
        if ($duplicateStmt->fetchColumn() !== false) {
            $rowErrors[] = "Baris {$row['row']}: data untuk bulan dan indikator tersebut sudah ada.";
        }
    }

    if ($rowErrors !== []) {
        $pdo->rollBack();
        uploadKinerjaError(409, 'Sebagian data tidak dapat disimpan. Tidak ada data yang disimpan.', array_slice($rowErrors, 0, 20));
    }

    foreach ($rows as $row) {
        $target = $row['target'];
        $jmlSasaran = $row['jml_sasaran'];
        $capaian = $row['capaian'];
        $targetSasaran = round($target * $jmlSasaran / 100, 2);
        $hasilRiil = $jmlSasaran > 0 ? round($capaian / $jmlSasaran * 100, 2) : 0;
        $hasilKinerja = $targetSasaran > 0 ? round(min($capaian / $targetSasaran * 100, 100), 2) : 0;

        $insertStmt->execute([
            ':nip' => trim((string) $user['nip']),
            ':kdpusk' => trim((string) $user['kdpusk']),
            ':bulan' => $row['bulan'],
            ':kdindikator' => $row['kdindikator'],
            ':target' => $target,
            ':satuan' => $row['satuan'],
            ':jml_sasaran' => $jmlSasaran,
            ':target_sasaran' => $targetSasaran,
            ':capaian' => $capaian,
            ':hasil_riil' => $hasilRiil,
            ':hasil_kinerja' => $hasilKinerja,
        ]);
    }

    $pdo->commit();
    echo json_encode([
        'ok' => true,
        'inserted' => count($rows),
        'message' => 'Data kinerja berhasil diunggah.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    uploadKinerjaError(500, 'Terjadi kesalahan pada server saat mengunggah data kinerja.', [$e->getMessage()]);
}
