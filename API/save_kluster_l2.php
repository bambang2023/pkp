<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metode request harus POST'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$kd_1 = $input['kd_1'] ?? null;
$kd_2 = $input['kd_2'] ?? null;
$uraian = $input['uraian'] ?? null;

if ($kd_1 === null || $kd_1 === '' || $kd_2 === null || $kd_2 === '' || $uraian === null || $uraian === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'KD_1, KD_2, dan Uraian tidak boleh kosong.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $checkStmt = $pdo->prepare("SELECT 1 FROM kluster_l1 WHERE kd_1 = :kd_1 LIMIT 1");
    $checkStmt->execute([':kd_1' => $kd_1]);
    if ($checkStmt->fetchColumn() === false) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'KD_1 tidak valid.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(
        [
            'ok' => false,
            'error' => 'Terjadi kesalahan pada server saat memvalidasi KD_1.',
            'detail' => $e->getMessage(),
        ],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

try {
    $table = 'kluster_l2';
    $sql = "INSERT INTO {$table} (kd_1, kd_2, uraian) VALUES (:kd_1, :kd_2, :uraian)";
    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':kd_1' => $kd_1,
        ':kd_2' => $kd_2,
        ':uraian' => $uraian,
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['ok' => true, 'message' => 'Data kluster L2 berhasil disimpan.'], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('Gagal menyimpan data ke database.');
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(
        [
            'ok' => false,
            'error' => 'Terjadi kesalahan pada server saat menyimpan data.',
            'detail' => $e->getMessage(),
        ],
        JSON_UNESCAPED_UNICODE
    );
}

