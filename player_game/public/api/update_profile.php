<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'message'=>'Method không hợp lệ.'], 405);
$userId = require_login();
require_csrf();
$data = read_json();

$name = trim((string)($data['display_name'] ?? ''));
$avatar = (string)($data['avatar'] ?? 'barista');
$allowedAvatars = ['barista', 'cat', 'fox', 'bear'];

if ($name === '' || mb_strlen($name) > 40) json_response(['ok'=>false,'message'=>'Tên hiển thị cần từ 1 đến 40 ký tự.'], 422);
if (!in_array($avatar, $allowedAvatars, true)) json_response(['ok'=>false,'message'=>'Avatar không hợp lệ.'], 422);

$stmt = db()->prepare('UPDATE player_profiles SET display_name = ?, avatar = ? WHERE user_id = ?');
$stmt->execute([$name, $avatar, $userId]);
json_response(['ok'=>true,'message'=>'Đã cập nhật hồ sơ.']);
