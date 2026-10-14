<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized. Silakan login terlebih dahulu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SESSION['role'] !== 'puskesmas') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Template CSV hanya tersedia untuk role puskesmas.'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template-data-kinerja-puskesmas.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate');

echo "\xEF\xBB\xBF";
$output = fopen('php://output', 'wb');
$headers = [
    'Bulan',
    'Kode Indikator',
    'Indikator',
    'Target',
    'Satuan',
    'Total Sasaran',
    'Target Sasaran',
    'Capaian',
    'Hasil Riil',
    'Hasil Kinerja',
];
fputcsv($output, $headers);
fclose($output);
