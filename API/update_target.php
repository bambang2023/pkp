<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metode request harus POST'], JSON_UNESCAPED_UNICODE);
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

if (!in_array($_SESSION['role'], ['provinsi', 'puskesmas'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Akses ditolak. Hanya role provinsi atau puskesmas yang dapat mengubah target.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$kdInd = trim((string) ($input['kd_ind'] ?? ''));

if ($kdInd === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'KD indikator tidak boleh kosong.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$monthColumns = [
    'Januari', 'Pebruari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'Nopember', 'Desember',
];
$updates = [];
$parameters = [':kd_ind' => $kdInd];

if ($_SESSION['role'] === 'provinsi') {
    if (!array_key_exists('target', $input)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Nilai target harus dikirim.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $updates[] = '`target` = :target';
    $target = trim((string) $input['target']);
    $parameters[':target'] = $target === '' ? null : $target;
} else {
    foreach ($monthColumns as $month) {
        if (array_key_exists($month, $input)) {
            $parameter = ':' . strtolower($month);
            $updates[] = "`$month` = $parameter";
            $value = trim((string) $input[$month]);
            $parameters[$parameter] = $value === '' ? null : $value;
        }
    }
}

if ($updates === []) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Minimal satu nilai yang dapat diubah harus dikirim.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE target SET ' . implode(', ', $updates) . ' WHERE kd_ind = :kd_ind');
    $stmt->execute($parameters);

    echo json_encode([
        'ok' => true,
        'message' => $stmt->rowCount() > 0
            ? 'Data target berhasil diperbarui.'
            : 'Tidak ada perubahan data.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Gagal memperbarui data target.',
    ], JSON_UNESCAPED_UNICODE);
}
