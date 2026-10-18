<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['ok'=>false,'message'=>'Method không hợp lệ.'], 405);
$userId = require_login();

$stmt = db()->prepare(
    'SELECT u.id, u.username, u.email, u.role, u.created_at,
            p.display_name, p.avatar, p.money, p.level, p.experience, p.reputation
     FROM users u INNER JOIN player_profiles p ON p.user_id = u.id
     WHERE u.id = ? LIMIT 1'
);
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) json_response(['ok'=>false,'message'=>'Không tìm thấy hồ sơ.'], 404);

json_response(['ok'=>true,'user'=>$user,'csrf_token'=>csrf_token()]);
