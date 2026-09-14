<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metode request harus POST'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized. Silakan login terlebih dahulu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Payload JSON tidak valid.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$requiredFields = [
    'puskesmas', 'bulan', 'kd_kluster', 'kd', 'target', 'satuan',
    'sasaran', 'target_sasaran', 'capaian', 'hasil_riil', 'hasil_kinerja',
];

foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || trim((string) $input[$field]) === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "Field {$field} wajib diisi."], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$numericFields = ['target', 'sasaran', 'target_sasaran', 'capaian', 'hasil_riil', 'hasil_kinerja'];
foreach ($numericFields as $field) {
    if (!is_numeric($input[$field])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "Field {$field} harus berupa angka."], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $userStmt = $pdo->prepare(
        'SELECT u.nip, u.puskesmas AS kdpusk
         FROM users u
         WHERE u.id = :user_id
         LIMIT 1'
    );
    $userStmt->execute([':user_id' => (int) $_SESSION['user_id']]);
    $user = $userStmt->fetch();

    if (!$user || trim((string) ($user['nip'] ?? '')) === '' || trim((string) ($user['kdpusk'] ?? '')) === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'NIP atau kode puskesmas user tidak tersedia.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $bulan = trim((string) $input['bulan']);
    $kdIndikator = trim((string) $input['kd']);

    $duplicateStmt = $pdo->prepare(
        'SELECT 1
         FROM data_kinerja
         WHERE bulan = :bulan AND kdindikator = :kdindikator
         LIMIT 1'
    );
    $duplicateStmt->execute([
        ':bulan' => $bulan,
        ':kdindikator' => $kdIndikator,
    ]);

    if ($duplicateStmt->fetchColumn() !== false) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'data sudah terinput'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = 'INSERT INTO data_kinerja (
                nip, kdpusk, bulan, kdindikator, target, sasaran, jml_sasaran, target_sasaran, capaian, hasil_riil, hasil_kinerja
            ) VALUES (
                :nip, :kdpusk, :bulan, :kd, :target, :satuan,
                :sasaran, :target_sasaran, :capaian, :hasil_riil, :hasil_kinerja
            )';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nip' => trim((string) $user['nip']),
        ':kdpusk' => trim((string) $user['kdpusk']),
        ':bulan' => $bulan,
        ':kd' => $kdIndikator,
        ':target' => (float) $input['target'],
        ':satuan' => trim((string) $input['satuan']),
        ':sasaran' => (float) $input['sasaran'],
        ':target_sasaran' => (float) $input['target_sasaran'],
        ':capaian' => (float) $input['capaian'],
        ':hasil_riil' => (float) $input['hasil_riil'],
        ':hasil_kinerja' => (float) $input['hasil_kinerja'],
    ]);

    echo json_encode([
        'ok' => true,
        'message' => 'Data kinerja berhasil disimpan.',
        'id' => $pdo->lastInsertId(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Terjadi kesalahan pada server saat menyimpan data kinerja.',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}