<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'error' => 'Metode tidak diizinkan.']);
}

if (!isset($_SESSION['user_id'], $_SESSION['nip'])) {
    respond(401, ['ok' => false, 'error' => 'Silakan login terlebih dahulu.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(400, ['ok' => false, 'error' => 'Permintaan tidak valid.']);
}

$nip = trim((string)($input['nip'] ?? ''));
$currentPassword = (string)($input['current_password'] ?? '');
$newPassword = (string)($input['new_password'] ?? '');

if ($nip === '' || $currentPassword === '' || $newPassword === '') {
    respond(400, ['ok' => false, 'error' => 'Semua kolom wajib diisi.']);
}

if (!hash_equals((string)$_SESSION['nip'], $nip)) {
    respond(403, ['ok' => false, 'error' => 'NIP harus sesuai dengan akun yang sedang login.']);
}

if (strlen($newPassword) < 8) {
    respond(400, ['ok' => false, 'error' => 'Password baru minimal 8 karakter.']);
}

try {
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? AND nip = ?');
    $stmt->execute([(int)$_SESSION['user_id'], $nip]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password'])) {
        respond(400, ['ok' => false, 'error' => 'Password saat ini salah.']);
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ? AND nip = ?');
    $stmt->execute([$newHash, (int)$_SESSION['user_id'], $nip]);

    respond(200, ['ok' => true, 'message' => 'Password berhasil diperbarui.']);
} catch (Throwable $e) {
    respond(500, ['ok' => false, 'error' => 'Gagal memperbarui password.']);
}