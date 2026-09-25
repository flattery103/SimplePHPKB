<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
if(!preg_match('/dbname=[A-Za-z0-9_]+_test(?:;|$)/',$config['db']['dsn']))throw new RuntimeException('Concurrency tests require _test database.');
if(($argv[1] ?? '')==='worker') {
    $deadline=microtime(true)+5;
    while(!file_exists($argv[3])) { if(microtime(true)>$deadline)throw new RuntimeException('Barrier timeout');usleep(10000); }
    try {
        if($argv[2]==='admin')(new Auth($db))->saveAdmin(['active'=>0],(int)$argv[4]);
        else (new Articles($db))->save((int)$argv[4],['title'=>$argv[6],'category_id'=>1,'keywords'=>'','html'=>'<p>Concurrent save</p>'],1,(int)$argv[5],false);
        echo 'saved';
    } catch(UserError $e) {echo 'rejected';}
    exit;
}
$db->exec('SET FOREIGN_KEY_CHECKS=0');foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t){if(!preg_match('/^[a-z_]+$/D',$t))throw new RuntimeException('Unsafe table');$db->exec('DROP TABLE `'.$t.'`');}$db->exec('SET FOREIGN_KEY_CHECKS=1');Database::migrate($db);
$auth=new Auth($db);$one=$auth->saveAdmin(['username'=>'first','display_name'=>'First','password'=>'Only-Testing-Password-345','active'=>1],null);$two=$auth->saveAdmin(['username'=>'second','display_name'=>'Second','password'=>'Only-Testing-Password-346','active'=>1],null);
function race(array $commands): array {
    $barrier=tempnam(sys_get_temp_dir(),'kbbarrier');unlink($barrier);$jobs=[];
    foreach($commands as $args){$pipes=[];$p=proc_open(array_merge([PHP_BINARY,'-c',php_ini_loaded_file(),__FILE__,'worker',$args[0],$barrier],array_slice($args,1)),[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);$jobs[]=[$p,$pipes];}
    touch($barrier);$results=[];
    foreach($jobs as [$p,$pipes]){fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException($err);$results[]=$out;}
    unlink($barrier);sort($results);return $results;
}
if(race([['admin',(string)$one],['admin',(string)$two]])!==['rejected','saved'])throw new RuntimeException('Last-admin race failed');
if((int)$db->query('SELECT COUNT(*) FROM admins WHERE active=1')->fetchColumn()!==1)throw new RuntimeException('No active admin survived');
$active=(int)$db->query('SELECT id FROM admins WHERE active=1')->fetchColumn();$id=(new Articles($db))->create($active);
if(race([['article',(string)$id,(string)$active,'First edit'],['article',(string)$id,(string)$active,'Second edit']])!==['rejected','saved'])throw new RuntimeException('Concurrent edit race failed');
echo "PASS concurrency: one last-admin disable rejected; one simultaneous stale article save rejected\n";
