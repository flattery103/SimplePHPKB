<?php
declare(strict_types=1);
require_once __DIR__.'/init.php';
$configFile=$configFile ?? (getenv('KB_CONFIG') ?: __DIR__.'/../config/config.php');
if (!is_file($configFile)) throw new RuntimeException('Configuration is missing. Follow docs/INSTALL.md.');
$config=require $configFile;
if (!is_array($config) || !preg_match('#^(?:/[A-Za-z0-9_-]+)*$#D', $config['base_path'] ?? '')) throw new RuntimeException('Invalid base_path configuration.');
if (PHP_VERSION_ID < 80300) throw new RuntimeException('PHP 8.3 or later is required.');
foreach (['pdo_mysql','mbstring','dom','fileinfo','gd'] as $extension) if (!extension_loaded($extension)) throw new RuntimeException("Missing PHP extension: $extension");
$db=Database::connect($config['db']);
if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('simplephpkb');
    session_set_cookie_params(['lifetime'=>0,'path'=>($config['base_path'] ?: '').'/','secure'=>(bool)$config['secure_cookies'],'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
