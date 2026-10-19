<?php
// POST api/auth/login.php  { username_or_email, password }
require_once dirname(__DIR__, 3) . '/lib/shop_helpers.php';

requireMethod('POST');

try {
    $input = readJsonBody();
    $login = trim($input['username_or_email'] ?? '');
    $pass  = $input['password'] ?? '';

    if ($login === '' || $pass === '') {
        throw new ApiException("Vui lòng nhập tên đăng nhập và mật khẩu.", 400);
    }

    // Tìm user qua username hoặc email
    $stmt = $pdo->prepare('SELECT id, username, password_hash, balance FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        throw new ApiException("Tên đăng nhập hoặc mật khẩu không đúng.", 401);
    }

    // Cập nhật last_login
    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

    if (session_status() === PHP_SESSION_NONE) {
        session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];

    apiRespond(true, "Đăng nhập thành công!", [
        "user_id"  => (int)$user['id'],
        "username" => $user['username'],
        "balance"  => (float)$user['balance'],
    ]);

} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), null, $e->getCode());
} catch (Throwable $e) {
    error_log('login: ' . $e->getMessage());
    apiRespond(false, "Lỗi server khi đăng nhập.", null, 500);
}
