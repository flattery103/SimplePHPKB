<?php
declare(strict_types=1);
const KB_VERSION = '0.6.0';
if(!defined('KB_APP_ROOT'))define('KB_APP_ROOT',dirname(__DIR__));
spl_autoload_register(function (string $name): void {
    if (preg_match('/^[A-Za-z]+$/', $name) && is_file(__DIR__."/$name.php")) require __DIR__."/$name.php";
});
if (is_file(__DIR__.'/../vendor/autoload.php')) require __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/Support.php';
