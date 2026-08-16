<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $kd_ind_filter = $_GET['kd_ind'] ?? null;

    if ($kd_ind_filter !== null) {
        // Mode 1: Ambil satu indikator berdasarkan kd_ind
        $stmt = $pdo->prepare("SELECT kd_3, kd_ind, indikator, kegiatan, DO, formula, sumber_data FROM indikator WHERE kd_ind = :kd_ind");
        $stmt->execute([':kd_ind' => $kd_ind_filter]);
        $row = $stmt->fetch();

        if ($row) {
            $data = [
                'kd_3' => $row['kd_3'] ?? null,
                'kd_ind' => $row['kd_ind'] ?? null,
                'indikator' => $row['indikator'] ?? null,
                'kegiatan' => $row['kegiatan'] ?? null,
                'DO' => $row['DO'] ?? null,
                'formula' => $row['formula'] ?? null,
                'sumber_data' => $row['sumber_data'] ?? null,
            ];
            echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Indikator tidak ditemukan'], JSON_UNESCAPED_UNICODE);
        }
    } else {
        // Mode 2: Ambil semua indikator (perilaku default)
        $stmt = $pdo->query("SELECT kd_3, kd_ind, indikator, kegiatan, DO, formula, sumber_data FROM indikator ORDER BY kd_ind");
        $rows = $stmt->fetchAll();

        echo json_encode([
            'ok' => true,
            'data' => $rows, // Langsung kirim array dari fetchAll
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Gagal mengambil data indikator.',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}