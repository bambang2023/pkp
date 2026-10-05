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

$bulan = trim((string) ($input['bulan'] ?? ''));
$kdIndikator = trim((string) ($input['kdindikator'] ?? ''));
$jmlSasaran = $input['jml_sasaran'] ?? null;
$capaian = $input['capaian'] ?? null;

if (!preg_match('/\A(?:0?[1-9]|1[0-2])\z/', $bulan) || $kdIndikator === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Bulan dan kode indikator tidak valid.'], JSON_UNESCAPED_UNICODE);
    exit;
}

foreach (['jml_sasaran' => $jmlSasaran, 'capaian' => $capaian] as $field => $value) {
    if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "Field {$field} harus berupa angka nol atau lebih."], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $userStmt = $pdo->prepare('SELECT puskesmas FROM users WHERE id = :user_id LIMIT 1');
    $userStmt->execute([':user_id' => (int) $_SESSION['user_id']]);
    $kdpusk = trim((string) ($userStmt->fetchColumn() ?: ''));

    if ($kdpusk === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Kode puskesmas user tidak tersedia.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $bulan = str_pad((string) (int) $bulan, 2, '0', STR_PAD_LEFT);
    $recordStmt = $pdo->prepare(
        'SELECT target
         FROM data_kinerja
         WHERE kdpusk = :kdpusk AND bulan = :bulan AND kdindikator = :kdindikator
         LIMIT 1'
    );
    $recordStmt->execute([
        ':kdpusk' => $kdpusk,
        ':bulan' => $bulan,
        ':kdindikator' => $kdIndikator,
    ]);
    $target = $recordStmt->fetchColumn();

    if ($target === false) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Data kinerja tidak ditemukan.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $target = (float) $target;
    $jmlSasaran = (float) $jmlSasaran;
    $capaian = (float) $capaian;
    $targetSasaran = round($target * $jmlSasaran / 100, 2);
    $hasilRiil = $jmlSasaran > 0 ? round($capaian / $jmlSasaran * 100, 2) : 0;
    $hasilKinerja = $targetSasaran > 0 ? round(min($capaian / $targetSasaran * 100, 100), 2) : 0;

    $updateStmt = $pdo->prepare(
        'UPDATE data_kinerja
         SET jml_sasaran = :jml_sasaran,
             capaian = :capaian,
             target_sasaran = :target_sasaran,
             hasil_riil = :hasil_riil,
             hasil_kinerja = :hasil_kinerja
         WHERE kdpusk = :kdpusk AND bulan = :bulan AND kdindikator = :kdindikator'
    );
    $updateStmt->execute([
        ':jml_sasaran' => $jmlSasaran,
        ':capaian' => $capaian,
        ':target_sasaran' => $targetSasaran,
        ':hasil_riil' => $hasilRiil,
        ':hasil_kinerja' => $hasilKinerja,
        ':kdpusk' => $kdpusk,
        ':bulan' => $bulan,
        ':kdindikator' => $kdIndikator,
    ]);

    echo json_encode(['ok' => true, 'message' => 'Data kinerja berhasil diperbarui.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Terjadi kesalahan pada server saat memperbarui data kinerja.',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
