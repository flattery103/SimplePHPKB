<?php
declare(strict_types=1);
$dsn=getenv('KB_WEB_TEST_DSN') ?: getenv('KB_TEST_DSN');
if(!is_string($dsn) || !preg_match('/dbname=[A-Za-z0-9_]+_test(?:;|$)/',$dsn))throw new RuntimeException('Set KB_WEB_TEST_DSN or KB_TEST_DSN to a disposable _test database.');
$config=require __DIR__.'/../config/config.example.php';
$config['secure_cookies']=false;
$config['storage']=__DIR__.'/artifacts/storage';
$config['db']=['dsn'=>$dsn,'username'=>getenv('KB_TEST_USER') ?: 'root','password'=>getenv('KB_TEST_PASSWORD') ?: ''];
if(!is_dir(__DIR__.'/artifacts'))mkdir(__DIR__.'/artifacts',0700,true);
file_put_contents(__DIR__.'/artifacts/config.php',"<?php\n\$c=".var_export($config,true).";\n\$c['base_path']=getenv('KB_TEST_BASE_PATH') ?: '';\nreturn \$c;\n");
chmod(__DIR__.'/artifacts/config.php',0600);
echo "Test configuration prepared. The named database will be reset by tests.\n";
