<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $table = 'kluster_l2';
    $stmt = $pdo->query("SELECT kd_1, kd_2, uraian FROM {$table} ORDER BY kd_1, kd_2");
    $rows = $stmt->fetchAll();

    echo json_encode([
        'ok' => true,
        'data' => array_map(static function ($r) {
            return [
                'kd_1' => $r['kd_1'] ?? null,
                'kd_2' => $r['kd_2'] ?? null,
                'uraian' => $r['uraian'] ?? null,
            ];
        }, $rows),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Gagal mengambil data kluster_l2',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
