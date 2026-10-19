<?php
$config = require __DIR__ . '/../config/config.local.php';
echo 'Host: ' . $config['host'] . PHP_EOL;
echo 'Port: ' . $config['port'] . PHP_EOL;
echo 'DB: ' . $config['database'] . PHP_EOL;
echo 'PHP Version: ' . PHP_VERSION . PHP_EOL;
echo 'pdo_mysql loaded: ' . (extension_loaded('pdo_mysql') ? 'YES' : 'NO') . PHP_EOL;

$sslCa = $config['ssl_ca'] ?? '';
echo 'SSL CA path: ' . $sslCa . PHP_EOL;
echo 'SSL CA file exists: ' . (file_exists($sslCa) ? 'YES' : 'NO') . PHP_EOL;

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $config['host'], $config['port'], $config['database']);

try {
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    if (!empty($sslCa)) {
        $sslCaAttr = PHP_VERSION_ID >= 80400
            ? \Pdo\Mysql::ATTR_SSL_CA
            : PDO::MYSQL_ATTR_SSL_CA;
        $options[$sslCaAttr] = $sslCa;
    }
    $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
    echo 'Connection: SUCCESS' . PHP_EOL;
} catch (PDOException $e) {
    echo 'Connection FAILED: ' . $e->getMessage() . PHP_EOL;
}
