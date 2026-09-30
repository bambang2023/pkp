<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function showActivationResult(string $title, string $message, bool $success = false): never
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $link = $success
        ? '<a href="../index.html" class="login-btn" style="display:inline-block;text-decoration:none;color:white;padding:0.75rem 2rem;">Login</a>'
        : '<a href="../register.html">Kembali ke registrasi</a>';

    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . $safeTitle . '</title><link rel="stylesheet" href="../styles.css"></head><body><main class="container"><section class="login-box"><h1>' . $safeTitle . '</h1><p>' . $safeMessage . '</p><p>' . $link . '</p></section></main></body></html>';
    exit;
}

$token = trim((string)($_GET['token'] ?? ''));
if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
    showActivationResult('Tautan aktivasi tidak valid', 'Periksa kembali tautan dari email Anda.');
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id, nama, nip, email, password, role, provinsi, kabupaten, puskesmas FROM pending_users WHERE token_hash = ? AND expires_at > NOW() LIMIT 1 FOR UPDATE');
    $stmt->execute([hash('sha256', $token)]);
    $pendingUser = $stmt->fetch();

    if (!$pendingUser) {
        $pdo->rollBack();
        showActivationResult('Tautan aktivasi kedaluwarsa atau tidak valid', 'Silakan lakukan registrasi kembali atau hubungi administrator.');
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE nip = ? LIMIT 1');
    $stmt->execute([$pendingUser['nip']]);
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare('DELETE FROM pending_users WHERE id = ?');
        $stmt->execute([(int)$pendingUser['id']]);
        $pdo->commit();
        showActivationResult('NIP sudah digunakan', 'Hubungi administrator untuk membantu mengaktifkan akun Anda.');
    }

    $stmt = $pdo->prepare('INSERT INTO users (nama, nip, email, password, role, provinsi, kabupaten, puskesmas) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $pendingUser['nama'],
        $pendingUser['nip'],
        $pendingUser['email'],
        $pendingUser['password'],
        $pendingUser['role'],
        $pendingUser['provinsi'],
        $pendingUser['kabupaten'],
        $pendingUser['puskesmas'],
    ]);

    $stmt = $pdo->prepare('DELETE FROM pending_users WHERE id = ?');
    $stmt->execute([(int)$pendingUser['id']]);
    $pdo->commit();

    showActivationResult('Akun berhasil diaktifkan', 'Email Anda sudah terverifikasi. Sekarang Anda dapat login.', true);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    showActivationResult('Aktivasi gagal', 'Terjadi kendala saat mengaktifkan akun. Silakan coba lagi nanti.');
}