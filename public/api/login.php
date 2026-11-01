<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'message'=>'Method không hợp lệ.'], 405);
require_csrf();
$data = read_json();
$username = trim((string)($data['username'] ?? ''));
$password = (string)($data['password'] ?? '');

if ($username === '' || $password === '') json_response(['ok'=>false,'message'=>'Nhập tên đăng nhập và mật khẩu.'], 422);

try {
    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_response(['ok'=>false,'message'=>'Tên đăng nhập hoặc mật khẩu không đúng.'], 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    json_response(['ok'=>true,'message'=>'Đăng nhập thành công.','csrf_token'=>$_SESSION['csrf_token']]);
} catch (Throwable $e) {
    error_log((string)$e);
    json_response(['ok'=>false,'message'=>'Có lỗi máy chủ.'], 500);
}
