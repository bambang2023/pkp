<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $kd_ind_filter = $_GET['kd_ind'] ?? null;
    $kd_kluster_filter = $_GET['kd_kluster'] ?? null;

    if ($kd_ind_filter !== null) {
        // Mode 1: Ambil satu indikator berdasarkan kd_ind
        $stmt = $pdo->prepare(
            "SELECT i.kd_3, i.kd_ind, i.indikator, i.kegiatan, i.DO, i.formula, i.sumber_data,
                (SELECT s.satuan FROM satuan AS s WHERE s.kd_ind = i.kd_ind LIMIT 1) AS satuan
             FROM indikator AS i
             WHERE i.kd_ind = :kd_ind"
        );
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
                'satuan' => $row['satuan'] ?? null,
            ];
            echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Indikator tidak ditemukan'], JSON_UNESCAPED_UNICODE);
        }
    } elseif ($kd_kluster_filter !== null && $kd_kluster_filter !== '') {
        $stmt = $pdo->prepare(
                "SELECT i.kd_3, i.kd_ind, i.indikator, i.kegiatan, i.DO, i.formula, i.sumber_data,
                    (SELECT s.satuan FROM satuan AS s WHERE s.kd_ind = i.kd_ind LIMIT 1) AS satuan
                 FROM indikator AS i
                 WHERE i.kd_ind LIKE :kd_prefix
                 ORDER BY i.kd_ind"
        );
        $stmt->execute([':kd_prefix' => $kd_kluster_filter . '.%']);
        $rows = $stmt->fetchAll();

        echo json_encode([
            'ok' => true,
            'data' => $rows,
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Mode 2: Ambil semua indikator (perilaku default)
        $stmt = $pdo->query(
            "SELECT i.kd_3, i.kd_ind, i.indikator, i.kegiatan, i.DO, i.formula, i.sumber_data,
                (SELECT s.satuan FROM satuan AS s WHERE s.kd_ind = i.kd_ind LIMIT 1) AS satuan
             FROM indikator AS i
             ORDER BY i.kd_ind"
        );
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