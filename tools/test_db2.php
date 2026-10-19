<?php
$config = require __DIR__ . '/../config/config.local.php';
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $config['host'], $config['port'], $config['database']);

$sslCa = $config['ssl_ca'] ?? '';

// Test 1: SSL với ATTR_SSL_VERIFY_SERVER_CERT = false
echo "=== Test 1: SSL CA + no verify ===" . PHP_EOL;
try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        \Pdo\Mysql::ATTR_SSL_CA => $sslCa,
        \Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];
    $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
    echo "SUCCESS" . PHP_EOL;
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 2: Không SSL
echo PHP_EOL . "=== Test 2: No SSL ===" . PHP_EOL;
try {
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
    echo "SUCCESS" . PHP_EOL;
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 3: SSL CA cũ + verify false
echo PHP_EOL . "=== Test 3: Old MYSQL_ATTR_SSL_CA + no verify ===" . PHP_EOL;
try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => $sslCa,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];
    $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
    echo "SUCCESS" . PHP_EOL;
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}

// Test 4: Kiểm tra có thể ping host không
echo PHP_EOL . "=== Network check ===" . PHP_EOL;
$fp = @fsockopen($config['host'], $config['port'], $errno, $errstr, 5);
if ($fp) {
    echo "TCP port open: YES" . PHP_EOL;
    fclose($fp);
} else {
    echo "TCP port open: NO - $errno: $errstr" . PHP_EOL;
}
