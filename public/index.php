<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-src 'self' blob:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
try {
    // Acquire the stable request lock before loading any replaceable application code.
    $configFile=getenv('KB_CONFIG') ?: __DIR__.'/../config/config.php';
    if(is_file($configFile)) {
        $preconfig=require $configFile;
        $lifecycleLock=fopen($preconfig['storage'].'/lifecycle.lock','c');
        if(!$lifecycleLock||!flock($lifecycleLock,LOCK_SH|LOCK_NB)) {
            http_response_code(503);header('Retry-After: 30');echo 'The knowledge base is being updated. Please try again shortly.';exit;
        }
        $journal=$preconfig['storage'].'/updates/state.json';
        $updateState=is_file($journal)?json_decode((string)file_get_contents($journal),true):null;
        if(($updateState['phase']??'')==='files') {require __DIR__.'/recover.php';exit;}
    }
    require_once __DIR__.'/../src/init.php';
    if(!is_file($configFile)) { Installer::run($configFile); exit; }
    require __DIR__.'/../src/bootstrap.php';
    if(($_GET['route']??'')==='updates') {(new Updates($db))->run();exit;}
    if(Updates::pending($db)) {
        http_response_code(503);header('Retry-After: 60');
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Maintenance</title><h1>The knowledge base is being updated.</h1><p>Please try again shortly.</p><p><a href="'.e(route_url('updates')).'">Admin updates</a></p></html>';exit;
    }
    (new Web($db))->run();
} catch (Throwable $e) {
    error_log('SimplePHPKB: '.$e);
    http_response_code(500);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>SimplePHPKB</title><h1>We could not load the knowledge base.</h1><p>Please try again shortly. If the problem continues, contact your administrator.</p></html>';
}
