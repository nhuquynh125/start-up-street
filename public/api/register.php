<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response([
        'ok' => false,
        'message' => 'Phương thức gửi dữ liệu không hợp lệ.'
    ], 405);
}

require_csrf();
$data = read_json();

$username = trim((string)($data['username'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$password = (string)($data['password'] ?? '');
$displayName = trim((string)($data['display_name'] ?? $username));

// 1. Kiểm tra tên đăng nhập
if ($username === '') {
    json_response([
        'ok' => false,
        'field' => 'username',
        'message' => 'Vui lòng nhập tên đăng nhập.'
    ], 422);
}

if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
    json_response([
        'ok' => false,
        'field' => 'username',
        'message' => 'Tên đăng nhập phải có 3–30 ký tự, chỉ gồm chữ không dấu, số và dấu gạch dưới.'
    ], 422);
}

// 2. Kiểm tra email
if ($email === '') {
    json_response([
        'ok' => false,
        'field' => 'email',
        'message' => 'Vui lòng nhập email.'
    ], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)
    || mb_strlen($email) > 190) {
    json_response([
        'ok' => false,
        'field' => 'email',
        'message' => 'Email không hợp lệ. Vui lòng nhập đúng định dạng, ví dụ abc@gmail.com.'
    ], 422);
}

// 3. Kiểm tra mật khẩu
if ($password === '') {
    json_response([
        'ok' => false,
        'field' => 'password',
        'message' => 'Vui lòng nhập mật khẩu.'
    ], 422);
}

if (strlen($password) < 8) {
    json_response([
        'ok' => false,
        'field' => 'password',
        'message' => 'Mật khẩu phải có ít nhất 8 ký tự.'
    ], 422);
}

// 4. Kiểm tra tên hiển thị
if ($displayName === '') {
    json_response([
        'ok' => false,
        'field' => 'display_name',
        'message' => 'Vui lòng nhập tên hiển thị.'
    ], 422);
}

if (mb_strlen($displayName) > 40) {
    json_response([
        'ok' => false,
        'field' => 'display_name',
        'message' => 'Tên hiển thị không được vượt quá 40 ký tự.'
    ], 422);
}

try {
    $pdo = db();

    // 5. Kiểm tra tên đăng nhập đã tồn tại chưa
    $stmt = $pdo->prepare(
        'SELECT id FROM users WHERE username = ? LIMIT 1'
    );
    $stmt->execute([$username]);

    if ($stmt->fetch()) {
        json_response([
            'ok' => false,
            'field' => 'username',
            'message' => 'Tên đăng nhập này đã tồn tại. Vui lòng chọn tên khác.'
        ], 409);
    }

    // 6. Kiểm tra email đã được sử dụng chưa
    $stmt = $pdo->prepare(
        'SELECT id FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        json_response([
            'ok' => false,
            'field' => 'email',
            'message' => 'Email này đã được sử dụng. Vui lòng dùng email khác.'
        ], 409);
    }

    // 7. Tạo tài khoản và hồ sơ trong cùng một transaction
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO users (username, email, password_hash)
         VALUES (?, ?, ?)'
    );

    $stmt->execute([
        $username,
        $email,
        password_hash($password, PASSWORD_DEFAULT)
    ]);

    $userId = (int)$pdo->lastInsertId();

    $profile = $pdo->prepare(
        'INSERT INTO player_profiles
         (user_id, display_name, money, level, experience, reputation)
         VALUES (?, ?, 20000, 1, 0, 0)'
    );

    $profile->execute([$userId, $displayName]);

    $pdo->commit();

    // 8. Đăng nhập tự động sau khi đăng ký thành công
    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    json_response([
        'ok' => true,
        'message' => 'Đăng ký tài khoản thành công!',
        'user_id' => $userId,
        'csrf_token' => $_SESSION['csrf_token']
    ], 201);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Xử lý lỗi trùng dữ liệu phát sinh giữa lúc kiểm tra và INSERT
    if ($e->getCode() === '23000') {
        json_response([
            'ok' => false,
            'field' => 'account',
            'message' => 'Tên đăng nhập hoặc email đã được sử dụng. Vui lòng kiểm tra lại.'
        ], 409);
    }

    // Ghi chi tiết lỗi vào log máy chủ, không trả thông tin nhạy cảm ra client
    error_log('[REGISTER ERROR] ' . $e->getMessage());

    json_response([
        'ok' => false,
        'field' => 'server',
        'message' => 'Đăng ký thất bại do lỗi cơ sở dữ liệu. Vui lòng thử lại sau.'
    ], 500);
}
