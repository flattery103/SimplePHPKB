<?php
declare(strict_types=1);
// Only a deliberately named test database may be reset by this runner.
ob_start();
$group = $argv[1] ?? 'all';
$checks = 0;
function check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    $checks++;
}
function expect_error(callable $f, string $contains): void {
    try { $f(); } catch (Throwable $e) {
        check(str_contains(strtolower($e->getMessage()), strtolower($contains)), "error contains '$contains': ".$e->getMessage()); return;
    }
    check(false, "Expected error: $contains");
}
function run_group(string $name, callable $f): void {
    global $group;
    if ($group === 'all' || $group === $name) { $f(); echo "PASS $name\n"; }
}
check(is_file(__DIR__.'/../src/init.php'), 'application bootstrap exists');
require __DIR__.'/../src/init.php';
$dsn = getenv('KB_TEST_DSN') ?: '';
check((bool)preg_match('/dbname=[a-zA-Z0-9_]+_test(?:;|$)/', $dsn), 'explicit _test database required');
$config = ['base_path'=>'/kb', 'secure_cookies'=>false, 'storage'=>sys_get_temp_dir().'/simplephpkb-tests', 'db'=>['dsn'=>$dsn,'username'=>getenv('KB_TEST_USER') ?: 'root','password'=>getenv('KB_TEST_PASSWORD') ?: '']];
$db = Database::connect($config['db']);
$db->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    check((bool)preg_match('/^[a-z_]+$/', $table), 'safe fixture table');
    $db->exec('DROP TABLE `'.$table.'`');
}
$db->exec('SET FOREIGN_KEY_CHECKS=1');
Database::migrate($db);
run_group('foundation', function() use ($db) {
    Database::migrate($db);
    check((int)$db->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() === 4, 'migration idempotent');
    $db->prepare('INSERT INTO categories (name) VALUES (?)')->execute(['Réseau 日本語 🔧']);
    check($db->query('SELECT name FROM categories ORDER BY id DESC LIMIT 1')->fetchColumn() === 'Réseau 日本語 🔧', 'utf8mb4 round trip');
    check(app_url('index.php', ['route'=>'article','id'=>7]) === '/kb/index.php?route=article&id=7', 'subdirectory URL');
    check(e('<script>"') === '&lt;script&gt;&quot;', 'HTML output escaping');
});
foreach (['auth','content','search','enhancements','benchmark'] as $part) {
    $file=__DIR__.'/'.$part.'.php';
    if (is_file($file)) require $file;
}
echo "PASS: $checks checks\n";
ob_end_flush();
