<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $provinsi_kode = $_GET['provinsi_kode'] ?? '';
    if (!$provinsi_kode) {
        echo json_encode([]);
        exit;
    }

    // Jika parameter 'all', ambil semua data tanpa filter
    if ($provinsi_kode === 'all') {
        $stmt = $pdo->query("SELECT kode, nama FROM ref_kabupaten ORDER BY nama ASC");
    } else {
        $stmt = $pdo->prepare("SELECT kode, nama FROM ref_kabupaten WHERE provinsi_kode = ? ORDER BY nama ASC");
        $stmt->execute([$provinsi_kode]);
    }

    $data = $stmt->fetchAll();
    echo json_encode($data);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
