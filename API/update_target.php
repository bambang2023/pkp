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

if ($_SESSION['role'] !== 'provinsi') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Akses ditolak. Hanya role provinsi yang dapat mengubah target.'], JSON_UNESCAPED_UNICODE);
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
    $missingMonths = array_filter($monthColumns, static fn (string $month): bool => !array_key_exists($month, $input));
    if ($missingMonths !== []) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Seluruh nilai Januari sampai Desember harus dikirim.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $target = is_numeric(trim((string) ($input['target'] ?? '')))
        ? (float) $input['target']
        : null;
    if ($target === null) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'target perbulan tidak sesuai'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $updates[] = '`target` = :target';
    $parameters[':target'] = $target;
    $total = 0.0;
    foreach ($monthColumns as $month) {
        $value = trim((string) $input[$month]);
        if ($value !== '' && !is_numeric($value)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => "Nilai $month harus berupa angka."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $amount = $value === '' ? 0.0 : (float) $value;
        $total += $amount;
        $parameter = ':' . strtolower($month);
        $updates[] = "`$month` = $parameter";
        $parameters[$parameter] = $value === '' ? null : $value;
    }

    if (abs($total - $target) > 0.000001) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'target perbulan tidak sesuai',
        ], JSON_UNESCAPED_UNICODE);
        exit;
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
