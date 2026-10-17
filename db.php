<?php

$configPath = __DIR__ . '/config/config.local.php';

if (!file_exists($configPath)) {
    throw new RuntimeException(
        'Chưa có file config/config.local.php. Hãy sao chép từ config.example.php và điền thông tin kết nối.'
    );
}

$config = require $configPath;

if (!file_exists($config['ssl_ca'])) {
    throw new RuntimeException(
        'Không tìm thấy file chứng chỉ SSL CA tại: ' . $config['ssl_ca']
    );
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $config['host'],
    $config['port'],
    $config['database']
);

try {
    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_SSL_CA       => $config['ssl_ca'],
        ]
    );
} catch (PDOException $e) {
    error_log('MySQL connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Không thể kết nối database. Hãy kiểm tra cấu hình.');
}
