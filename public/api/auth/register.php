<?php
// POST api/auth/register.php  { username, email, password }
// Đăng ký tài khoản mới, cấp 1 tỉ vốn ban đầu, tạo profile mặc định.
require_once dirname(__DIR__, 3) . '/lib/shop_helpers.php';

requireMethod('POST');

try {
    $input    = readJsonBody();
    $username = trim($input['username'] ?? '');
    $email    = trim($input['email']    ?? '');
    $password = $input['password']       ?? '';

    // Validation
    if (mb_strlen($username) < 3 || mb_strlen($username) > 32) {
        throw new ApiException("Tên người dùng phải từ 3 đến 32 ký tự.", 400);
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        throw new ApiException("Tên người dùng chỉ được chứa chữ cái, số và dấu gạch dưới.", 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new ApiException("Email không hợp lệ.", 400);
    }
    if (strlen($password) < 6) {
        throw new ApiException("Mật khẩu phải có ít nhất 6 ký tự.", 400);
    }

    // Kiểm tra trùng username / email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        throw new ApiException("Tên người dùng hoặc email đã được sử dụng.", 409);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $pdo->beginTransaction();

    // Tạo user (balance = 1 tỉ mặc định trong schema)
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$username, $email, $hash]);
    $userId = (int)$pdo->lastInsertId();

    // Tạo profile mặc định
    $pdo->prepare('INSERT INTO player_profiles (user_id) VALUES (?)')->execute([$userId]);

    $pdo->commit();

    // Bắt đầu session ngay sau đăng ký
    if (session_status() === PHP_SESSION_NONE) {
        session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;

    apiRespond(true, "Đăng ký thành công! Chào mừng " . $username . " đến Startup Street.", [
        "user_id"  => $userId,
        "username" => $username,
        "balance"  => 1000000000,
    ], 201);

} catch (ApiException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    apiRespond(false, $e->getMessage(), null, $e->getCode());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('register: ' . $e->getMessage());
    apiRespond(false, "Lỗi server khi tạo tài khoản.", null, 500);
}
