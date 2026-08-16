<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $table = 'kluster';
    $stmt = $pdo->query("SELECT kd, uraian FROM {$table} ORDER BY kd");
    $rows = $stmt->fetchAll();

    echo json_encode([
        'ok' => true,
        'data' => array_map(static function ($r) {
            return [
                'kd' => $r['kd'] ?? null,
                'uraian' => $r['uraian'] ?? null,
            ];
        }, $rows),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Gagal mengambil data kluster',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

