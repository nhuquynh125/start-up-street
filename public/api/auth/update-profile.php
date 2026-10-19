<?php
// POST api/auth/update-profile.php
// { display_name?, theme?, avatar_age_group?, avatar_skin_tone?, avatar_hair_style?, avatar_hair_color?,
//   avatar_eye_style?, avatar_outfit?, avatar_outfit_color?, avatar_hat?, avatar_glasses? }
require_once dirname(__DIR__, 3) . '/lib/shop_helpers.php';

requireMethod('POST');

try {
    $userId = getCurrentUserId();
    $input  = readJsonBody();

    $allowed = ['display_name','theme','avatar_age_group','avatar_skin_tone','avatar_hair_style',
                 'avatar_hair_color','avatar_eye_style','avatar_outfit','avatar_outfit_color',
                 'avatar_hat','avatar_glasses'];

    $sets = []; $params = [];
    foreach ($allowed as $col) {
        if (!array_key_exists($col, $input)) continue;
        $v = $input[$col];
        // Validate per column
        if ($col === 'display_name') {
            $v = mb_substr(trim((string)$v), 0, 50);
        } elseif ($col === 'theme') {
            if (!in_array($v, ['warm','cool','forest','night'], true)) continue;
        } elseif ($col === 'avatar_age_group') {
            if (!in_array($v, ['teen','young','adult','middle','senior','elder'], true)) continue;
        } elseif ($col === 'avatar_outfit_color') {
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string)$v)) continue;
        } else {
            // numeric tinyint 0-9
            $v = max(0, min(9, (int)$v));
        }
        $sets[]   = "`$col` = ?";
        $params[] = $v;
    }

    if (empty($sets)) {
        throw new ApiException("Không có trường hợp lệ để cập nhật.", 400);
    }

    $params[] = $userId;

    // Upsert: nếu chưa có profile thì tạo, rồi update
    $pdo->prepare('INSERT IGNORE INTO player_profiles (user_id) VALUES (?)')->execute([$userId]);
    $pdo->prepare('UPDATE player_profiles SET ' . implode(', ', $sets) . ' WHERE user_id = ?')
        ->execute($params);

    apiRespond(true, "Đã cập nhật hồ sơ.", ["user_id" => $userId]);

} catch (ApiException $e) {
    apiRespond(false, $e->getMessage(), null, $e->getCode());
} catch (Throwable $e) {
    error_log('update-profile: ' . $e->getMessage());
    apiRespond(false, "Lỗi server khi cập nhật hồ sơ.", null, 500);
}
