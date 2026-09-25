<?php
declare(strict_types=1);
// Keep this entry point independent of vendor and replaceable application classes.
ini_set('display_errors','0');
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
try {
    $configFile=getenv('KB_CONFIG') ?: __DIR__.'/../config/config.php';
    if(!is_file($configFile)){http_response_code(404);exit;}
    $config=require $configFile;
    if(!isset($lifecycleLock)) {
        $lifecycleLock=fopen($config['storage'].'/lifecycle.lock','c');
        if(!$lifecycleLock||!flock($lifecycleLock,LOCK_SH|LOCK_NB)){http_response_code(503);header('Retry-After: 30');echo 'An update is running. Please retry shortly.';exit;}
    }
    $file=$config['storage'].'/updates/state.json';
    $state=is_file($file)?json_decode((string)file_get_contents($file),true):null;
    $url=($config['base_path']?:'').'/index.php?route=updates';
    if(($state['phase']??'')!=='files'){header('Location: '.$url,true,303);exit;}
    if(basename($_SERVER['SCRIPT_NAME']??'')!=='recover.php'&&($_GET['route']??'')!=='updates') {
        http_response_code(503);header('Retry-After: 60');echo '<h1>The knowledge base is being updated.</h1><p><a href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">Admin updates</a></p>';exit;
    }
    define('KB_APP_ROOT',dirname(__DIR__));
    // This snapshot was made and verified before the first live file was replaced.
    require $config['storage'].'/updates/recovery/src/bootstrap.php';
    (new Updates($db))->run();
} catch(Throwable $e) {
    error_log('SimplePHPKB recovery: '.$e);http_response_code(503);
    echo 'Recovery could not start. Ask the server administrator to restore the saved backup.';
}
