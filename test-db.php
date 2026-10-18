<?php

require_once __DIR__ . '/db.php';

try {
    $stmt = $pdo->query('SELECT DATABASE() AS db_name, VERSION() AS version');
    $result = $stmt->fetch();
    
    echo "<h2>Kết nối MySQL thành công!</h2>";
    echo "<p>Database: " . htmlspecialchars($result['db_name']) . "</p>";
    echo "<p>MySQL version: " . htmlspecialchars($result['version']) . "</p>";
} catch (Exception $e) {
    echo "<h2>Lỗi kết nối: " . htmlspecialchars($e->getMessage()) . "</h2>";
}
