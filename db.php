<?php
// db.php — kết nối MySQL dùng chung (gộp từ db-quynh.php và db.php)

function dbFail(string $message, int $status = 500): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code($status);
    echo json_encode([
        "success" => false,
        "message" => $message,
        "data" => null
    ]);
    exit;
}

// Đường dẫn tới file cấu hình local
$configPath = __DIR__ . '/config/config.local.php';

if (!file_exists($configPath)) {
    dbFail("Thiếu file config/config.local.php! Hãy sao chép từ config/config.example.php và điền thông tin kết nối.");
}

$config = require $configPath;

// Chứng chỉ SSL CA là tùy chọn; nếu có khai báo thì file phải tồn tại
$sslCa = $config['ssl_ca'] ?? '';
if (!empty($sslCa) && !file_exists($sslCa)) {
    dbFail("Không tìm thấy file chứng chỉ SSL CA tại: " . $sslCa);
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

if (!empty($sslCa)) {
    $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
}

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
} catch (PDOException $e) {
    error_log('MySQL connection failed: ' . $e->getMessage());
    dbFail("Không thể kết nối database. Hãy kiểm tra cấu hình.");
}