<?php
declare(strict_types=1);
final class Database {
    public static function pending(PDO $db): array {
        $exists=$db->query("SHOW TABLES LIKE 'schema_migrations'")->fetchColumn();
        $done=$exists?array_map('intval',$db->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN)):[];
        return array_values(array_filter(glob(__DIR__.'/../database/*.sql'),fn($f)=>!in_array((int)basename($f),$done,true)));
    }
    public static function connect(array $config): PDO {
        $db = new PDO($config['dsn'], $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
        ]);
        $db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $db->exec("SET time_zone = '+00:00'");
        return $db;
    }
    public static function migrate(PDO $db): void {
        if ((int)$db->query("SELECT GET_LOCK('simplephpkb_migrate', 10)")->fetchColumn() !== 1) throw new RuntimeException('Another migration is running.');
        try {
            $db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version INT PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
            foreach (glob(__DIR__.'/../database/*.sql') as $file) {
                $version = (int)basename($file);
                $q=$db->prepare('SELECT version FROM schema_migrations WHERE version=?'); $q->execute([$version]);
                if ($q->fetch()) continue;
                foreach (preg_split('/;\s*(?:\n|$)/', file_get_contents($file)) as $sql) if (trim($sql) !== '') $db->exec($sql);
                $db->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
            }
        } finally { $db->query("SELECT RELEASE_LOCK('simplephpkb_migrate')"); }
    }
    public static function transaction(PDO $db, callable $callback): mixed {
        $db->beginTransaction();
        try { $result=$callback(); $db->commit(); return $result; }
        catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
}
