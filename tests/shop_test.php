<?php
/**
 * tests/shop_test.php — integration tests for Member 3's shop & inventory endpoints
 * (create-shop.php, purchase-inventory.php, update-shop-name.php, sell-product.php, get-shop-products.php).
 *
 * HOW IT WORKS
 *   - Black-box: every check goes through real HTTP calls (cURL) to the running API.
 *   - Ground truth: balances / inventory / shop rows are read straight from MySQL (db.php), never from the API
 *     under test, so "unchanged" really means unchanged in the database.
 *   - Two EXISTING players are used (user A = the shop owner, user B = the attacker). Their balances are changed
 *     during the run and restored at the end. Test data is created with synthetic property ids (9900001, 9900002)
 *     and shop names starting with "TESTSUITE-", and is deleted again when the run ends (even on failure/abort).
 *
 * RUN
 *     php -S 127.0.0.1:8000 -t public          # terminal 1 (API must be reachable)
 *     php tests/shop_test.php                  # terminal 2
 *
 * OPTIONS (or the env var in brackets)
 *     --base-url=http://127.0.0.1:8000/api/    [BASE_URL]
 *     --user-a=1  --user-b=2                   [TEST_USER_A, TEST_USER_B]  two players that exist in `users`
 *     --login-url=http://.../dev-login.php?user_id={user_id}   [LOGIN_URL]  how to get a session for a player
 *     --force                                  allow a non-localhost base URL (the run WRITES to the database!)
 *     -v                                       print every request/response
 *
 * Exit code: 0 = all passed, 1 = at least one failure, 2 = could not run (setup problem).
 * Requires PHP 7.4+ with the curl and pdo_mysql extensions. Use a development database, never production.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this from the command line: php tests/shop_test.php\n");
}

// ───────────────────────── Configuration (mirrors lib/shop_helpers.php + create-shop.php) ─────────────────────────
const TEST_PREFIX          = 'TESTSUITE-';   // every shop the suite creates starts with this
const PROP_A               = 9900001;        // synthetic property rented by user A (never collides with real 1..48)
const PROP_B               = 9900002;        // synthetic property rented by user B
const PROP_MISSING         = 99999;          // must exist neither as a rental nor as a shop
const RICH_BALANCE         = 200000000.0;
const T_WALLET_TABLE       = 'users';
const T_WALLET_ID_COL      = 'id';
const T_WALLET_BALANCE_COL = 'balance';
const T_RENTALS_TABLE      = 'rentals';      // same 3 names as RENTALS_* in public/api/create-shop.php
const T_RENTALS_PROP_COL   = 'property_id';
const T_RENTALS_USER_COL   = 'user_id';

// ───────────────────────── Tiny test runner ─────────────────────────
final class T
{
    public static $pass = 0;
    public static $fail = 0;
    public static $skip = 0;
    public static $failures = [];
    public static $color = false;
    public static $verbose = false;

    public static function c(string $s, string $code): string
    {
        return self::$color ? "\033[{$code}m{$s}\033[0m" : $s;
    }

    public static function section(string $title): void
    {
        echo "\n" . self::c($title, '1;36') . "\n";
    }

    public static function check(string $name, bool $cond, string $detail = ''): bool
    {
        if ($cond) {
            self::$pass++;
            echo '  ' . self::c('PASS', '32') . "  $name\n";
        } else {
            self::$fail++;
            self::$failures[] = $name;
            echo '  ' . self::c('FAIL', '1;31') . "  $name\n";
            if ($detail !== '') {
                echo "          -> $detail\n";
            }
        }
        return $cond;
    }

    public static function skip(string $name, string $why): void
    {
        self::$skip++;
        echo '  ' . self::c('SKIP', '33') . "  $name ($why)\n";
    }
}

function abort_run(string $msg): void
{
    fwrite(STDERR, "\n" . T::c('ABORT', '1;31') . ": $msg\n");
    exit(2);
}

function cfg(array $opts, string $key, string $env, string $default): string
{
    if (isset($opts[$key]) && is_string($opts[$key]) && $opts[$key] !== '') {
        return $opts[$key];
    }
    $e = getenv($env);
    return ($e !== false && $e !== '') ? $e : $default;
}

function snippet(string $raw, int $len = 200): string
{
    return substr((string)preg_replace('/\s+/', ' ', $raw), 0, $len);
}

// ───────────────────────── HTTP client (one cookie jar per player = one PHP session per player) ─────────────────────────
final class Http
{
    private $jar;

    public function __construct()
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'shoptest_');
    }

    public function __destruct()
    {
        if ($this->jar && is_file($this->jar)) {
            @unlink($this->jar);
        }
    }

    /** @return array{status:int, json:?array, raw:string} */
    public function call(string $method, string $url, ?array $body = null): array
    {
        $headers = ['Accept: application/json'];
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 5,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;

        $ch = curl_init($url);
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("cURL error on $method $url: $err (is the server running?)");
        }
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $json = json_decode((string)$raw, true);
        return ['status' => $status, 'json' => is_array($json) ? $json : null, 'raw' => (string)$raw];
    }
}

function api(Http $http, string $method, string $endpoint, ?array $body = null, array $query = []): array
{
    $url = T_BASE . $endpoint . ($query ? '?' . http_build_query($query) : '');
    $res = $http->call($method, $url, $body);
    if (T::$verbose) {
        echo "        $method $endpoint" . ($body !== null ? ' ' . json_encode($body, JSON_UNESCAPED_UNICODE) : '') . "\n";
        echo '        <- HTTP ' . $res['status'] . ' ' . snippet($res['raw'], 300) . "\n";
    }
    return $res;
}

/** Passes when the HTTP status is one of $statuses AND the JSON envelope says success=false. */
function expect_error(string $name, array $res, array $statuses): bool
{
    $ok = in_array($res['status'], $statuses, true)
        && is_array($res['json'])
        && ($res['json']['success'] ?? null) === false;
    $detail = $ok ? '' : 'expected HTTP ' . implode('/', $statuses) . ' with success=false, got HTTP '
        . $res['status'] . ' ' . snippet($res['raw']);
    return T::check($name . ' -> HTTP ' . implode('/', $statuses), $ok, $detail);
}

/** Compares two state snapshots field by field (money with a 0.005 tolerance). */
function assert_unchanged(string $name, array $before, array $after): bool
{
    $diffs = [];
    foreach ($before as $k => $v) {
        $a = $after[$k] ?? null;
        $same = (is_float($v) && is_float($a)) ? abs($v - $a) < 0.005 : $v === $a;
        if (!$same) {
            $diffs[] = "$k: " . json_encode($v) . ' -> ' . json_encode($a);
        }
    }
    return T::check($name, !$diffs, implode('; ', $diffs));
}

// ───────────────────────── Database helper (ground truth) ─────────────────────────
final class Db
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function all(string $sql, array $p = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function one(string $sql, array $p = []): ?array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r === false ? null : $r;
    }

    public function val(string $sql, array $p = [])
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchColumn();
    }

    public function run(string $sql, array $p = []): int
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        return $st->rowCount();
    }

    public function balance(int $uid): ?float
    {
        $v = $this->val(sprintf('SELECT %s FROM %s WHERE %s = ?', T_WALLET_BALANCE_COL, T_WALLET_TABLE, T_WALLET_ID_COL), [$uid]);
        return $v === false ? null : (float)$v;
    }

    public function setBalance(int $uid, float $amount): void
    {
        $this->run(sprintf('UPDATE %s SET %s = ? WHERE %s = ?', T_WALLET_TABLE, T_WALLET_BALANCE_COL, T_WALLET_ID_COL), [$amount, $uid]);
    }

    public function stock(int $shopId, int $productId): int
    {
        return (int)$this->val('SELECT quantity FROM inventory WHERE shop_id = ? AND product_id = ?', [$shopId, $productId]);
    }

    /** Whole inventory of a shop as [product_id => "qty @ price"], so any row added/changed shows up. */
    public function inventory(int $shopId): array
    {
        $out = [];
        foreach ($this->all('SELECT product_id, quantity, selling_price FROM inventory WHERE shop_id = ? ORDER BY product_id', [$shopId]) as $r) {
            $out[(int)$r['product_id']] = (int)$r['quantity'] . ' @ ' . number_format((float)$r['selling_price'], 2, '.', '');
        }
        return $out;
    }

    public function shop(int $id): ?array
    {
        return $this->one('SELECT id, owner_user_id, property_id, business_type_id, name FROM shops WHERE id = ?', [$id]);
    }

    /** @return int[] */
    public function shopIdsOnProperty(int $propertyId): array
    {
        return array_map('intval', array_column($this->all('SELECT id FROM shops WHERE property_id = ? ORDER BY id', [$propertyId]), 'id'));
    }

    /** Finance-ledger rows of a shop (null when Member 4's table is not installed). */
    public function ledgerRows(int $shopId): ?int
    {
        try {
            return (int)$this->val('SELECT COUNT(*) FROM transactions WHERE shop_id = ?', [$shopId]);
        } catch (PDOException $e) {
            return null;
        }
    }
}

/** Deletes everything the suite created and restores the two players' balances. Safe to call twice. */
final class Cleanup
{
    private $db;
    private $balances;
    private $done = false;

    /** @param array<int,float> $balances original balance per user id */
    public function __construct(Db $db, array $balances)
    {
        $this->db = $db;
        $this->balances = $balances;
    }

    public function purge(): void
    {
        $owners = implode(',', array_map('intval', array_keys($this->balances)));
        $props  = PROP_A . ',' . PROP_B;
        $ids = array_map('intval', array_column($this->db->all(
            "SELECT id FROM shops WHERE property_id IN ($props) OR (name LIKE ? AND owner_user_id IN ($owners))",
            [TEST_PREFIX . '%']
        ), 'id'));
        if ($ids) {
            $in = implode(',', $ids);
            $this->db->run("DELETE FROM inventory WHERE shop_id IN ($in)");
            try {
                $this->db->run("DELETE FROM transactions WHERE shop_id IN ($in)");
            } catch (PDOException $e) {
                // Member 4's ledger table is optional
            }
            $this->db->run("DELETE FROM shops WHERE id IN ($in)");
        }
        $this->db->run(sprintf('DELETE FROM %s WHERE %s IN (%s)', T_RENTALS_TABLE, T_RENTALS_PROP_COL, $props));
    }

    public function run(): void
    {
        if ($this->done) {
            return;
        }
        $this->done = true;
        try {
            $this->purge();
            foreach ($this->balances as $uid => $bal) {
                $this->db->setBalance((int)$uid, (float)$bal);
            }
            echo "\nCleanup: test shops/rentals removed, balances restored.\n";
        } catch (Throwable $e) {
            fwrite(STDERR, "\nCleanup WARNING: " . $e->getMessage()
                . "\n  Remove rows with shops.name LIKE '" . TEST_PREFIX . "%' and rentals.property_id IN (" . PROP_A . ',' . PROP_B . ") by hand,\n"
                . "  and check users.balance of the two test players.\n");
        }
    }
}

// ───────────────────────── Setup ─────────────────────────
$opts = getopt('v', ['base-url:', 'user-a:', 'user-b:', 'login-url:', 'force', 'help']);
if (isset($opts['help'])) {
    echo "Usage: php tests/shop_test.php [--base-url=URL] [--user-a=ID] [--user-b=ID] [--login-url=URL_WITH_{user_id}] [--force] [-v]\n"
        . "See the header comment of this file for details.\n";
    exit(0);
}

T::$color   = function_exists('stream_isatty') && stream_isatty(STDOUT) && getenv('NO_COLOR') === false;
T::$verbose = isset($opts['v']);

if (!extension_loaded('curl')) {
    abort_run('The PHP curl extension is required (enable extension=curl in php.ini).');
}

define('T_BASE', rtrim(cfg($opts, 'base-url', 'BASE_URL', 'http://127.0.0.1:8000/api'), '/') . '/');
$host = strtolower((string)parse_url(T_BASE, PHP_URL_HOST));
if (!in_array($host, ['127.0.0.1', 'localhost', '::1', '[::1]'], true) && !isset($opts['force'])) {
    abort_run('Refusing to run against "' . $host . '": this suite writes to the database. Use --force if you are sure it is a test environment.');
}

$uidA = (int)cfg($opts, 'user-a', 'TEST_USER_A', '1');
$uidB = (int)cfg($opts, 'user-b', 'TEST_USER_B', '2');
if ($uidA <= 0 || $uidB <= 0 || $uidA === $uidB) {
    abort_run('--user-a and --user-b must be two different positive user ids.');
}

$dbFile = dirname(__DIR__) . '/db.php';
if (!is_file($dbFile)) {
    abort_run("db.php not found at $dbFile (expected in the project root, next to tests/).");
}
require $dbFile; // defines $pdo
if (!isset($pdo) || !($pdo instanceof PDO)) {
    abort_run('db.php did not provide a PDO connection in $pdo.');
}
$db = new Db($pdo);

try {
    $origA = $db->balance($uidA);
    $origB = $db->balance($uidB);
    if ($origA === null || $origB === null) {
        abort_run('User #' . ($origA === null ? $uidA : $uidB) . ' does not exist in `users`. Create two players or pass --user-a / --user-b.');
    }

    $cleanup = new Cleanup($db, [$uidA => $origA, $uidB => $origB]);
    $cleanup->purge();                                   // leftovers from a previous crashed run
    register_shutdown_function([$cleanup, 'run']);       // restores balances + deletes test data, even on abort/fatal

    // Business types / products used by the tests
    $type = $db->one('SELECT id, name, required_capital FROM business_types ORDER BY required_capital ASC, id ASC LIMIT 1');
    if (!$type) {
        abort_run('No rows in business_types. Run database/migrations/02_shops_inventory.sql first.');
    }
    $own = $db->one('SELECT id, base_cost FROM products WHERE business_type_id = ? ORDER BY id LIMIT 1', [(int)$type['id']]);
    $foreign = $db->one('SELECT id FROM products WHERE business_type_id <> ? ORDER BY id LIMIT 1', [(int)$type['id']]);
    $type2 = $db->one('SELECT id FROM business_types WHERE id <> ? ORDER BY id LIMIT 1', [(int)$type['id']]);
    if (!$own || !$foreign) {
        abort_run('Need products in at least two different business types (migration 02 seeds 6 types x 4 products).');
    }
    $ownId = (int)$own['id'];
    $foreignId = (int)$foreign['id'];
    $baseCost = (float)$own['base_cost'];
    if ($baseCost <= 0) {
        abort_run("products.id=$ownId has base_cost <= 0; the insufficient-funds test needs a positive cost.");
    }

    // Both players are rich enough to open a shop; each one rents one synthetic property (rows removed by Cleanup)
    $db->setBalance($uidA, RICH_BALANCE);
    $db->setBalance($uidB, RICH_BALANCE);
    $rentSql = sprintf('INSERT INTO %s (%s, %s, price_paid) VALUES (?, ?, 0)', T_RENTALS_TABLE, T_RENTALS_PROP_COL, T_RENTALS_USER_COL);
    $db->run($rentSql, [PROP_A, $uidA]);
    $db->run($rentSql, [PROP_B, $uidB]);
} catch (PDOException $e) {
    abort_run('Database setup failed: ' . $e->getMessage()
        . "\n  (If the rentals table has other column names, edit the T_RENTALS_* constants at the top of this file.)");
}

// Sessions: one per player + one anonymous client
$loginTpl = cfg($opts, 'login-url', 'LOGIN_URL', T_BASE . 'dev-login.php?user_id={user_id}');
$login = function (int $uid) use ($loginTpl): Http {
    $h = new Http();
    $url = str_replace('{user_id}', (string)$uid, $loginTpl);
    try {
        $r = $h->call('GET', $url);
    } catch (RuntimeException $e) {
        abort_run($e->getMessage());
    }
    if ($r['status'] !== 200 || !is_array($r['json']) || empty($r['json']['success'])) {
        abort_run("Could not log in as user #$uid via $url (HTTP {$r['status']}: " . snippet($r['raw'], 120) . ").\n"
            . '  dev-login.php only works from 127.0.0.1 and only while it still exists; otherwise pass --login-url.');
    }
    return $h;
};
$httpA = $login($uidA);
$httpB = $login($uidB);
$anon  = new Http();

echo T::c('Shop & inventory API tests', '1') . '  base=' . T_BASE . "  owner=#$uidA  attacker=#$uidB\n";
echo "Fixtures: business type #{$type['id']} ({$type['name']}), own product #$ownId (base_cost $baseCost), foreign product #$foreignId\n";

$shopId = 0;
$snap = function () use ($db, $uidA, $uidB, &$shopId): array {
    $shop = $db->shop($shopId);
    return [
        'owner balance'    => $db->balance($uidA),
        'attacker balance' => $db->balance($uidB),
        'inventory'        => $db->inventory($shopId),
        'ledger rows'      => $db->ledgerRows($shopId),
        'shop row'         => $shop ? [$shop['name'], (int)$shop['business_type_id'], (int)$shop['owner_user_id']] : null,
    ];
};

try {
    // ═════════════ 1. Duplicate property creation ═════════════
    T::section('1. Duplicate property creation (two shops on one property_id) -> 409');
    $create = ['property_id' => PROP_A, 'business_type_id' => (int)$type['id'], 'name' => TEST_PREFIX . 'shop A'];
    $r = api($httpA, 'POST', 'create-shop.php', $create);
    $created = T::check(
        'first create-shop on a rented property -> HTTP 201',
        $r['status'] === 201 && (int)($r['json']['data']['shop_id'] ?? 0) > 0,
        'got HTTP ' . $r['status'] . ' ' . snippet($r['raw'])
    );
    if (!$created) {
        abort_run('The fixture shop could not be created, so the remaining tests cannot run. '
            . 'Check that rentals(' . PROP_A . ", user #$uidA) is accepted by create-shop.php and the player has enough capital.");
    }
    $shopId = (int)$r['json']['data']['shop_id'];

    $dup = api($httpA, 'POST', 'create-shop.php', $create);
    expect_error('second create-shop on the same property_id', $dup, [409]);
    T::check(
        '409 body points at the existing shop (data.shop_id)',
        (int)($dup['json']['data']['shop_id'] ?? 0) === $shopId,
        'data.shop_id = ' . json_encode($dup['json']['data']['shop_id'] ?? null) . ", expected $shopId"
    );
    if ($type2) {
        $dup2 = api($httpA, 'POST', 'create-shop.php', [
            'property_id' => PROP_A, 'business_type_id' => (int)$type2['id'], 'name' => TEST_PREFIX . 'shop A (other type)',
        ]);
        expect_error('same property, different business type and name', $dup2, [409]);
    } else {
        T::skip('same property, different business type', 'only one business type in the database');
    }
    T::check('still exactly one shop row on the property', $db->shopIdsOnProperty(PROP_A) === [$shopId],
        'shop ids on property: ' . json_encode($db->shopIdsOnProperty(PROP_A)));
    $s = $db->shop($shopId);
    T::check('original shop was not overwritten (name + business type)',
        $s !== null && $s['name'] === TEST_PREFIX . 'shop A' && (int)$s['business_type_id'] === (int)$type['id'],
        'row: ' . json_encode($s));

    // ═════════════ Control: the happy path works ═════════════
    T::section('Control: a valid purchase succeeds (proves the failures below fail for the right reason)');
    $qty = 2;
    $cost = $baseCost * $qty;
    $balBefore = $db->balance($uidA);
    $stockBefore = $db->stock($shopId, $ownId);
    $r = api($httpA, 'POST', 'purchase-inventory.php', ['shop_id' => $shopId, 'product_id' => $ownId, 'quantity' => $qty]);
    T::check('valid purchase -> HTTP 200, success=true',
        $r['status'] === 200 && ($r['json']['success'] ?? null) === true, 'got HTTP ' . $r['status'] . ' ' . snippet($r['raw']));
    T::check('wallet debited by base_cost x quantity',
        abs((float)$db->balance($uidA) - ($balBefore - $cost)) < 0.005,
        'balance ' . $balBefore . ' -> ' . $db->balance($uidA) . ", expected -$cost");
    T::check('stock increased by quantity', $db->stock($shopId, $ownId) === $stockBefore + $qty,
        "stock $stockBefore -> " . $db->stock($shopId, $ownId));

    // ═════════════ 2. Insufficient funds ═════════════
    T::section('2. Insufficient funds on purchase-inventory -> 400/422, balance & inventory unchanged');
    $qty = 10;
    $cost = $baseCost * $qty;
    $cases = ['balance 1 VND below the total cost' => max(0.0, $cost - 1), 'balance = 0' => 0.0];
    foreach ($cases as $label => $bal) {
        $db->setBalance($uidA, $bal);
        $before = $snap();
        $r = api($httpA, 'POST', 'purchase-inventory.php', ['shop_id' => $shopId, 'product_id' => $ownId, 'quantity' => $qty]);
        expect_error("buy $qty x product #$ownId (cost " . number_format($cost, 0, '.', ',') . ") with $label", $r, [400, 422]);
        assert_unchanged("wallet, inventory and ledger untouched ($label)", $before, $snap());
    }
    $db->setBalance($uidA, RICH_BALANCE);

    // ═════════════ 3. Cross-business product ═════════════
    T::section('3. Product of a different business type -> 404 (invalid product)');
    $before = $snap();
    $r = api($httpA, 'POST', 'purchase-inventory.php', ['shop_id' => $shopId, 'product_id' => $foreignId, 'quantity' => 1]);
    expect_error("product #$foreignId belongs to another business type", $r, [404]);
    assert_unchanged('no money taken and no inventory row created', $before, $snap());
    $r = api($httpA, 'POST', 'purchase-inventory.php', ['shop_id' => $shopId, 'product_id' => 99999999, 'quantity' => 1]);
    expect_error('product id that does not exist at all (extra)', $r, [404]);
    assert_unchanged('state still unchanged', $before, $snap());

    // ═════════════ 4. Unauthorized access ═════════════
    T::section("4. Another user's shop (unowned shop_id) -> 403");
    $db->setBalance($uidB, RICH_BALANCE); // B can afford everything, so a 403 can only come from the ownership check
    $before = $snap();
    $attacks = [
        'purchase-inventory.php (restock)' => ['POST', 'purchase-inventory.php', ['shop_id' => $shopId, 'product_id' => $ownId, 'quantity' => 1], []],
        'update-shop-name.php (rename)'    => ['POST', 'update-shop-name.php', ['shop_id' => $shopId, 'name' => TEST_PREFIX . 'HACKED'], []],
        'sell-product.php (sell stock)'    => ['POST', 'sell-product.php', ['shop_id' => $shopId, 'product_id' => $ownId, 'quantity' => 1], []],
        'get-shop-products.php (read)'     => ['GET', 'get-shop-products.php', null, ['shop_id' => $shopId]],
    ];
    foreach ($attacks as $label => [$method, $endpoint, $body, $query]) {
        $r = api($httpB, $method, $endpoint, $body, $query);
        expect_error("user #$uidB calls $label on user #$uidA's shop", $r, [403]);
    }
    assert_unchanged("owner's shop name, stock and wallet, and attacker's wallet, are untouched", $before, $snap());
    $r = api($httpB, 'POST', 'purchase-inventory.php', ['shop_id' => 99999999, 'product_id' => $ownId, 'quantity' => 1]);
    expect_error('shop_id that does not exist (extra: 404, not 403)', $r, [404]);
    $r = api($anon, 'POST', 'purchase-inventory.php', ['shop_id' => $shopId, 'product_id' => $ownId, 'quantity' => 1]);
    expect_error('no session at all (extra)', $r, [401]);
    assert_unchanged('state still unchanged', $before, $snap());

    // ═════════════ 5. Invalid quantities ═════════════
    T::section('5. Invalid quantities on purchase-inventory -> 400');
    $before = $snap();
    $qtyCases = [
        // [label, send the key?, value]
        ['quantity = 0',                 true,  0],
        ['quantity = -1',                true,  -1],
        ['quantity = "abc"',             true,  'abc'],
        ['quantity = 1.5 (extra)',       true,  1.5],
        ['quantity = "" (extra)',        true,  ''],
        ['quantity = null (extra)',      true,  null],
        ['quantity omitted (extra)',     false, null],
        ['quantity = 10000, over the 9999 cap (extra)', true, 10000],
    ];
    foreach ($qtyCases as [$label, $send, $value]) {
        $payload = ['shop_id' => $shopId, 'product_id' => $ownId];
        if ($send) {
            $payload['quantity'] = $value;
        }
        $r = api($httpA, 'POST', 'purchase-inventory.php', $payload);
        expect_error($label, $r, [400]);
    }
    assert_unchanged('wallet and inventory untouched by all rejected quantities', $before, $snap());

    // ═════════════ 6. Non-existent property ═════════════
    T::section('6. create-shop with a non-existent property_id -> 403/404');
    $taken = (int)$db->val(sprintf('SELECT COUNT(*) FROM %s WHERE %s = ?', T_RENTALS_TABLE, T_RENTALS_PROP_COL), [PROP_MISSING])
        + count($db->shopIdsOnProperty(PROP_MISSING));
    if ($taken > 0) {
        T::skip('property_id ' . PROP_MISSING, 'a rental/shop with this id already exists in your data; change PROP_MISSING at the top of the file');
    } else {
        $before = $snap();
        $r = api($httpA, 'POST', 'create-shop.php', ['property_id' => PROP_MISSING, 'business_type_id' => (int)$type['id'], 'name' => TEST_PREFIX . 'ghost']);
        expect_error('property_id ' . PROP_MISSING . ' (does not exist)', $r, [403, 404]);
        T::check('no shop row was created for that property', $db->shopIdsOnProperty(PROP_MISSING) === [],
            'shop ids: ' . json_encode($db->shopIdsOnProperty(PROP_MISSING)));

        $r = api($httpA, 'POST', 'create-shop.php', ['property_id' => PROP_B, 'business_type_id' => (int)$type['id'], 'name' => TEST_PREFIX . 'stolen']);
        expect_error("property rented by user #$uidB, not by the caller (extra)", $r, [403, 404]);
        T::check('no shop row was created on the other player\'s property', $db->shopIdsOnProperty(PROP_B) === [],
            'shop ids: ' . json_encode($db->shopIdsOnProperty(PROP_B)));
        assert_unchanged('no capital was charged and nothing else changed', $before, $snap());
    }
} catch (Throwable $e) {
    T::check('suite ran to completion without an unexpected exception', false, get_class($e) . ': ' . $e->getMessage());
}

// ───────────────────────── Summary ─────────────────────────
$cleanup->run();
echo "\n" . str_repeat('-', 64) . "\n";
echo T::c(T::$pass . ' passed', '32') . ', '
    . (T::$fail > 0 ? T::c(T::$fail . ' failed', '1;31') : '0 failed') . ', '
    . T::$skip . " skipped\n";
if (T::$failures) {
    echo "Failed checks:\n";
    foreach (T::$failures as $f) {
        echo "  - $f\n";
    }
}
exit(T::$fail > 0 ? 1 : 0);
