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

$kd_3 = $input['kd_3'] ?? null;
$kd_ind = $input['kd_ind'] ?? null;
$indikator = $input['indikator'] ?? null;
$original_kd_ind = $input['original_kd_ind'] ?? null; // Untuk mode edit

if (empty($kd_3) || empty($kd_ind) || empty($indikator)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'KD_3, KD Indikator, dan Indikator tidak boleh kosong.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validasi hanya dilakukan jika kd_3 tidak dinonaktifkan (mode tambah baru)
if ($original_kd_ind === null) {
    try {
        $checkStmt = $pdo->prepare("SELECT 1 FROM kluster_l3 WHERE kd_3 = :kd_3 LIMIT 1");
        $checkStmt->execute([':kd_3' => $kd_3]);
        if ($checkStmt->fetchColumn() === false) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'KD_3 tidak valid atau tidak ditemukan.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Gagal memvalidasi KD_3.', 'detail' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Simpan data ke tabel indikator
try {
    $params = [
        ':kd_ind' => $kd_ind,
        ':indikator' => $indikator,
        ':kegiatan' => $input['kegiatan'] ?? null,
        ':DO' => $input['DO'] ?? null,
        ':formula' => $input['formula'] ?? null,
        ':sumber_data' => $input['sumber_data'] ?? null,
    ];

    if ($original_kd_ind !== null) {
        // Mode UPDATE
        $sql = "UPDATE indikator SET 
                    kd_ind = :kd_ind, 
                    indikator = :indikator, 
                    kegiatan = :kegiatan, 
                    DO = :DO, 
                    formula = :formula, 
                    sumber_data = :sumber_data
                WHERE kd_ind = :original_kd_ind";
        $params[':original_kd_ind'] = $original_kd_ind;
        $message = 'Data indikator berhasil diperbarui.';
    } else {
        // Mode INSERT
        $sql = "INSERT INTO indikator (kd_3, kd_ind, indikator, kegiatan, DO, formula, sumber_data) 
                VALUES (:kd_3, :kd_ind, :indikator, :kegiatan, :DO, :formula, :sumber_data)";
        $params[':kd_3'] = $kd_3;
        $message = 'Data indikator berhasil disimpan.';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['ok' => true, 'message' => $message], JSON_UNESCAPED_UNICODE);
    } else {
        // Jika tidak ada baris yang terpengaruh, bisa jadi karena data yang di-update sama persis.
        // Ini bukan error, jadi kita anggap sukses.
        echo json_encode(['ok' => true, 'message' => 'Tidak ada perubahan data.'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    // Cek jika ada error duplikat primary key (kd_ind)
    if ($e instanceof PDOException && $e->errorInfo[1] == 1062) {
        http_response_code(409); // 409 Conflict
        echo json_encode([
            'ok' => false,
            'error' => 'KD Indikator ' . htmlspecialchars($kd_ind) . ' sudah ada atau duplikat.'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => 'Terjadi kesalahan pada server saat menyimpan data.',
            'detail' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
    }
}