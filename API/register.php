<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/db.php';

function showResult(string $title, string $message, bool $success = false): never
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $action = $success
        ? '<a href="../index.html" class="login-btn" style="display:inline-block;text-decoration:none;color:white;padding:0.75rem 2rem;">Ke halaman login</a>'
        : '<a href="../register.html">Kembali ke formulir registrasi</a>';

    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . $safeTitle . '</title><link rel="stylesheet" href="../styles.css"></head><body><main class="container"><section class="login-box"><h1>' . $safeTitle . '</h1><p>' . $safeMessage . '</p><p>' . $action . '</p></section></main></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    showResult('Metode tidak diizinkan', 'Gunakan formulir registrasi untuk membuat akun.');
}

$nama = trim((string)($_POST['nama'] ?? ''));
$nip = trim((string)($_POST['nip'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$role = (string)($_POST['role'] ?? '');
$provinsi = trim((string)($_POST['provinsi'] ?? ''));
$kabupaten = trim((string)($_POST['kabupaten'] ?? ''));
$puskesmas = trim((string)($_POST['puskesmas'] ?? ''));

if ($nama === '' || $nip === '' || $email === '' || $password === '') {
    showResult('Registrasi belum lengkap', 'Nama, NIP, email, dan password wajib diisi.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    showResult('Email tidak valid', 'Masukkan alamat email yang benar agar tautan aktivasi dapat diterima.');
}

if (strlen($password) < 8) {
    showResult('Password terlalu pendek', 'Password harus memiliki minimal 8 karakter.');
}

if (!in_array($role, ['puskesmas', 'kabupaten', 'provinsi'], true)) {
    showResult('Role tidak valid', 'Pilih role yang tersedia pada formulir.');
}

if ($provinsi === '' || ($role !== 'provinsi' && $kabupaten === '') || ($role === 'puskesmas' && $puskesmas === '')) {
    showResult('Wilayah belum lengkap', 'Pilih wilayah sesuai dengan role akun.');
}

$baseUrl = rtrim((string)(getenv('PKP_BASE_URL') ?: ''), '/');
$fromEmail = (string)(getenv('PKP_MAIL_FROM') ?: '');
$smtpHost = trim((string)(getenv('SMTP_HOST') ?: ''));
$smtpPort = filter_var(getenv('SMTP_PORT'), FILTER_VALIDATE_INT);
$smtpUsername = (string)(getenv('SMTP_USERNAME') ?: '');
$smtpPassword = (string)(getenv('SMTP_PASSWORD') ?: '');
$smtpEncryption = strtolower((string)(getenv('SMTP_ENCRYPTION') ?: 'tls'));

if (
    filter_var($baseUrl, FILTER_VALIDATE_URL) === false
    || filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false
    || $smtpHost === ''
    || $smtpPort === false
    || $smtpPort < 1
    || $smtpPort > 65535
    || $smtpUsername === ''
    || $smtpPassword === ''
    || !in_array($smtpEncryption, ['tls', 'ssl'], true)
) {
    showResult('Konfigurasi email belum lengkap', 'Periksa PKP_BASE_URL, PKP_MAIL_FROM, dan konfigurasi SMTP di file .env.');
}

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    showResult('Dependensi email belum tersedia', 'Jalankan composer install pada folder aplikasi, lalu coba kembali.');
}
require_once $autoloadPath;
$smtpSecure = $smtpEncryption === 'ssl'
    ? PHPMailer::ENCRYPTION_SMTPS
    : PHPMailer::ENCRYPTION_STARTTLS;

try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE nip = ? LIMIT 1');
    $stmt->execute([$nip]);
    if ($stmt->fetch()) {
        showResult('NIP sudah terdaftar', 'Gunakan NIP yang belum terdaftar atau hubungi administrator.');
    }

    $stmt = $pdo->prepare('DELETE FROM pending_users WHERE nip = ? AND expires_at <= NOW()');
    $stmt->execute([$nip]);

    $stmt = $pdo->prepare('SELECT id FROM pending_users WHERE nip = ? LIMIT 1');
    $stmt->execute([$nip]);
    if ($stmt->fetch()) {
        showResult('Registrasi sedang menunggu aktivasi', 'Periksa email yang digunakan saat registrasi untuk membuka tautan aktivasi.');
    }

    $token = bin2hex(random_bytes(32));
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('INSERT INTO pending_users (nama, nip, email, password, role, provinsi, kabupaten, puskesmas, token, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))');
    $stmt->execute([
        $nama,
        $nip,
        $email,
        $hashedPassword,
        $role,
        $provinsi,
        $kabupaten !== '' ? $kabupaten : null,
        $puskesmas !== '' ? $puskesmas : null,
        $token,
    ]);

    $activationUrl = $baseUrl . '/API/activate_account.php?token=' . rawurlencode($token);
    try {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $smtpHost;
        $mailer->SMTPAuth = true;
        $mailer->Username = $smtpUsername;
        $mailer->Password = $smtpPassword;
        $mailer->SMTPSecure = $smtpSecure;
        $mailer->Port = $smtpPort;
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($fromEmail, 'PKP Jawa Timur');
        $mailer->addAddress($email, $nama);
        $mailer->Subject = 'Aktivasi akun PKP Jawa Timur';
        $mailer->Body = "Halo {$nama},\n\n"
            . "Untuk mengaktifkan akun PKP Anda, buka tautan berikut dalam 24 jam:\n"
            . $activationUrl . "\n\n"
            . "Jika Anda tidak membuat akun ini, abaikan email ini.\n";
        $mailer->send();
    } catch (Throwable $e) {
        $stmt = $pdo->prepare('DELETE FROM pending_users WHERE token = ?');
        $stmt->execute([$token]);
        error_log('Email aktivasi gagal dikirim melalui SMTP.');
        showResult('Email aktivasi gagal dikirim', 'Akun belum dibuat. Periksa konfigurasi SMTP di file .env.');
    }

    showResult('Periksa email Anda', 'Tautan aktivasi telah dikirim ke ' . $email . '. Tautan berlaku selama 24 jam.', true);
} catch (Throwable $e) {
    http_response_code(500);
    showResult('Registrasi gagal', 'Terjadi kendala saat memproses registrasi. Pastikan skema aktivasi sudah dipasang dan coba kembali.');
}