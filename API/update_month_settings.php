<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metode request harus POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized. Silakan login terlebih dahulu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SESSION['role'] !== 'provinsi') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Akses hanya tersedia untuk role provinsi.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$activeMonths = $input['bulan_aktif'] ?? null;
$monthNames = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
];

if (!is_array($activeMonths)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Daftar bulan aktif tidak valid.'], JSON_UNESCAPED_UNICODE);
    exit;
}

foreach ($activeMonths as $month) {
    if (!is_string($month) || !array_key_exists($month, $monthNames)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Daftar bulan berisi nilai yang tidak valid.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$activeMonths = array_fill_keys($activeMonths, true);

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'INSERT INTO pengaturan_bulan_kinerja (bulan, nama, aktif)
         VALUES (:bulan, :nama, :aktif)
         ON DUPLICATE KEY UPDATE nama = VALUES(nama), aktif = VALUES(aktif)'
    );
    foreach ($monthNames as $month => $name) {
        $stmt->execute([
            ':bulan' => $month,
            ':nama' => $name,
            ':aktif' => isset($activeMonths[$month]) ? 1 : 0,
        ]);
    }
    $pdo->commit();

    echo json_encode(['ok' => true, 'message' => 'Pengaturan bulan berhasil disimpan.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Gagal menyimpan pengaturan bulan.'], JSON_UNESCAPED_UNICODE);
}