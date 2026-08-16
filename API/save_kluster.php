<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metode request harus POST']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$kd = $input['kd'] ?? null;
$uraian = $input['uraian'] ?? null;

if (empty($kd) || empty($uraian)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Kode kluster dan uraian tidak boleh kosong.']);
    exit;
}

try {
    $table = 'kluster';
    $sql = "INSERT INTO {$table} (kd, uraian) VALUES (:kd, :uraian)";
    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':kd' => $kd,
        ':uraian' => $uraian,
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'ok' => true,
            'message' => 'Data kluster berhasil disimpan.',
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('Gagal menyimpan data ke database, tidak ada baris yang terpengaruh.');
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Terjadi kesalahan pada server saat menyimpan data.',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
