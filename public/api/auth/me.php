<?php
// GET api/auth/me.php — trả thông tin người dùng hiện tại (kiểm tra đã đăng nhập chưa)
require_once dirname(__DIR__, 3) . '/lib/shop_helpers.php';

try {
    $userId = getCurrentUserId();

    $stmt = $pdo->prepare('
        SELECT u.id, u.username, u.email, u.balance, u.created_at,
               p.avatar_age_group, p.avatar_skin_tone, p.avatar_hair_style, p.avatar_hair_color,
               p.avatar_eye_style, p.avatar_outfit, p.avatar_outfit_color, p.avatar_hat, p.avatar_glasses,
               p.theme, p.display_name
        FROM users u
        LEFT JOIN player_profiles p ON p.user_id = u.id
        WHERE u.id = ?
    ');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new ApiException("Không tìm thấy người dùng.", 404);
    }

    apiRespond(true, "OK", [
        "user_id"  => (int)$user['id'],
        "username" => $user['username'],
        "email"    => $user['email'],
        "balance"  => (float)$user['balance'],
        "created_at" => $user['created_at'],
        "profile"  => [
            "display_name"        => $user['display_name'] ?? $user['username'],
            "theme"               => $user['theme'] ?? 'warm',
            "avatar_age_group"    => $user['avatar_age_group'] ?? 'adult',
            "avatar_skin_tone"    => (int)($user['avatar_skin_tone'] ?? 2),
            "avatar_hair_style"   => (int)($user['avatar_hair_style'] ?? 0),
            "avatar_hair_color"   => (int)($user['avatar_hair_color'] ?? 0),
            "avatar_eye_style"    => (int)($user['avatar_eye_style'] ?? 0),
            "avatar_outfit"       => (int)($user['avatar_outfit'] ?? 0),
            "avatar_outfit_color" => $user['avatar_outfit_color'] ?? '#5b8dd9',
            "avatar_hat"          => (int)($user['avatar_hat'] ?? 0),
            "avatar_glasses"      => (int)($user['avatar_glasses'] ?? 0),
        ],
    ]);

} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), null, $e->getCode());
} catch (Throwable $e) {
    error_log('me: ' . $e->getMessage());
    apiRespond(false, "Lỗi server.", null, 500);
}
