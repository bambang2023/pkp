<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $kabupaten_kode = $_GET['kabupaten_kode'] ?? null;
    $nama_puskesmas = $_GET['nama'] ?? null;

    $sql = "SELECT p.kode, p.nama, p.kabupaten_kode, k.nama as kabupaten_nama 
            FROM ref_puskesmas p 
            LEFT JOIN ref_kabupaten k ON p.kabupaten_kode = k.kode";
    
    $params = [];

    if ($nama_puskesmas !== null) {
        $sql .= " WHERE p.nama = :nama";
        $params[':nama'] = $nama_puskesmas;
    } elseif ($kabupaten_kode !== null && $kabupaten_kode !== 'all') {
        $sql .= " WHERE p.kabupaten_kode = :kabupaten_kode";
        $params[':kabupaten_kode'] = $kabupaten_kode;
    }

    $sql .= " ORDER BY p.nama";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    // Jika tidak ada data dan user memfilter berdasarkan nama, coba pencarian LIKE
    if (count($data) === 0 && $nama_puskesmas !== null) {
        $sql = "SELECT p.kode, p.nama, p.kabupaten_kode, k.nama as kabupaten_nama 
                FROM ref_puskesmas p 
                LEFT JOIN ref_kabupaten k ON p.kabupaten_kode = k.kode
                WHERE p.nama LIKE :nama
                ORDER BY p.nama";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':nama' => '%' . $nama_puskesmas . '%']);
        $data = $stmt->fetchAll();
    }

    echo json_encode([
        'ok'   => true,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Gagal mengambil data puskesmas.',
        'detail' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
