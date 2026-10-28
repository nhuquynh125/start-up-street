<?php
// ============================================================
// tools/run_migration.php
// Chạy tất cả 4 file migration theo đúng thứ tự vào defaultdb
// Cách dùng: php tools/run_migration.php
// ============================================================

$root = dirname(__DIR__);

// Kết nối trực tiếp (không qua db.php vì db.php cần web context)
$configPath = $root . '/config/config.local.php';
if (!file_exists($configPath)) {
    die("❌ Thiếu file config/config.local.php\n");
}
$config = require $configPath;

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $config['host'],
    $config['port'],
    $config['database']
);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => true,  // cần true để chạy nhiều statements
];

// SSL (bỏ qua nếu ssl_ca rỗng)
$sslCa = $config['ssl_ca'] ?? '';
if (!empty($sslCa) && file_exists($sslCa)) {
    $attr = PHP_VERSION_ID >= 80400 ? \Pdo\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA;
    $options[$attr] = $sslCa;
}

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
    echo "✅ Kết nối database thành công: {$config['host']}:{$config['port']}/{$config['database']}\n\n";
} catch (PDOException $e) {
    die("❌ Không thể kết nối: " . $e->getMessage() . "\n");
}

// Danh sách 4 file migration theo thứ tự
$migrations = [
    '00_users_auth.sql',
    '01_simulation_finance.sql',
    '02_shops_inventory.sql',
    '03_properties_rentals.sql',
    '04_shop_runtime.sql',
    '05_game_days_reports.sql',
    '05_custom_products.sql',
    '06_settlement.sql',
    '07_product_emoji.sql',
    '08_game_days_reports.sql',
    '09_inventory_max_stock.sql',
];

$migrationDir = $root . '/database/migrations/';

foreach ($migrations as $file) {
    $path = $migrationDir . $file;
    if (!file_exists($path)) {
        echo "⚠️  Bỏ qua (không tìm thấy file): $file\n";
        continue;
    }

    echo "─────────────────────────────────────────\n";
    echo "📄 Đang chạy: $file\n";

    $sql = file_get_contents($path);

    // Tách từng câu lệnh SQL theo dấu chấm phẩy
    // Xử lý DELIMITER (PREPARE/EXECUTE) - dùng PDO::EMULATE_PREPARES=true
    $statements = splitSqlStatements($sql);

    $ok  = 0;
    $err = 0;
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt) || str_starts_with(ltrim($stmt), '--')) {
            continue;
        }
        try {
            $pdo->exec($stmt);
            $ok++;
        } catch (PDOException $e) {
            $short = substr($stmt, 0, 80);
            echo "   ⚠️  LỖI: " . $e->getMessage() . "\n";
            echo "      SQL: $short...\n";
            $err++;
        }
    }

    echo "   ✅ Xong: $ok câu OK, $err lỗi\n";
}

echo "\n🎉 Migration hoàn tất!\n";

// ─── Helper: tách SQL thành từng statement ───────────────────
function splitSqlStatements(string $sql): array
{
    // Chuẩn hóa line endings
    $sql = str_replace("\r\n", "\n", $sql);

    $statements = [];
    $current    = '';
    $lines      = explode("\n", $sql);

    foreach ($lines as $line) {
        $trimmed = ltrim($line);

        // Bỏ qua comment thuần
        if (str_starts_with($trimmed, '--')) {
            continue;
        }

        $current .= $line . "\n";

        // Kết thúc statement khi dòng kết thúc bằng ;
        if (str_ends_with(rtrim($line), ';')) {
            $stmt = trim($current);
            if (!empty($stmt)) {
                $statements[] = $stmt;
            }
            $current = '';
        }
    }

    // Phần còn thừa (không có ; cuối)
    $stmt = trim($current);
    if (!empty($stmt)) {
        $statements[] = $stmt;
    }

    return $statements;
}
