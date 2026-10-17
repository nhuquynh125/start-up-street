<?php
// Đường dẫn tới file cấu hình local
$configPath = __DIR__ . '/config/config.local.php';

if (!file_exists($configPath)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => false,
        "message" => "Thiếu file config/config.local.php! Hãy tạo file cấu hình database.",
        "data" => null
    ]);
    exit;
}

$config = require $configPath;

// Kiểm tra file chứng chỉ SSL CA
if (!empty($config['ssl_ca']) && !file_exists($config['ssl_ca'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => false,
        "message" => "Không tìm thấy file chứng chỉ SSL CA tại: " . $config['ssl_ca'],
        "data" => null
    ]);
    exit;
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $config['host'],
    $config['port'],
    $config['database']
);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

if (!empty($config['ssl_ca'])) {
    $options[PDO::MYSQL_ATTR_SSL_CA] = $config['ssl_ca'];
}

try {
    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        $options
    );
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => false,
        "message" => "Kết nối MySQL thất bại: " . $e->getMessage(),
        "data" => null
    ]);
    exit;
}
