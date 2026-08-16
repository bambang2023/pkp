<?php
declare(strict_types=1);

// Selalu mulai session untuk dapat mengakses dan menghancurkannya.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Hapus semua variabel di dalam session.
$_SESSION = [];

// 2. Hancurkan cookie session.
// Ini akan memastikan bahwa pada request selanjutnya, browser tidak akan mengirimkan cookie session yang lama.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Hancurkan session secara permanen.
session_destroy();

// Beri respons JSON untuk konfirmasi (best practice untuk API)
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'message' => 'Anda telah berhasil logout.'], JSON_UNESCAPED_UNICODE);

exit;