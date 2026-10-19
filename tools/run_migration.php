<?php
require __DIR__ . '/db.php';
$sql = file_get_contents(__DIR__ . '/database/migrations/00_users_auth.sql');
$stmts = array_filter(array_map('trim', explode(';', $sql)));
$ok = 0; $err = 0;
foreach ($stmts as $s) {
    if (empty($s) || str_starts_with(ltrim($s), '--')) continue;
    try { $pdo->exec($s); $ok++; } catch(Exception $e) { echo 'ERR: '.$e->getMessage().PHP_EOL; $err++; }
}
echo "Done: $ok stmts OK, $err errors\n";
