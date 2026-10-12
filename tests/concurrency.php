<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
if(!preg_match('/dbname=[A-Za-z0-9_]+_test(?:;|$)/',$config['db']['dsn']))throw new RuntimeException('Concurrency tests require _test database.');
if(($argv[1] ?? '')==='worker') {
    $deadline=microtime(true)+5;
    while(!file_exists($argv[3])) { if(microtime(true)>$deadline)throw new RuntimeException('Barrier timeout');usleep(10000); }
    try {
        if($argv[2]==='usage') {
            (new Usage($db))->recordSiteVisit($argv[5],'Mozilla/5.0',false);
            (new Articles($db))->recordView((int)$argv[4],$argv[5],'Mozilla/5.0',false);
        }
        elseif($argv[2]==='admin')(new Auth($db))->saveAdmin(['active'=>0],(int)$argv[4]);
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
$articles=new Articles($db);$articles->save($id,['title'=>'Usage race','category_id'=>1,'keywords'=>'','html'=>'<p>Read me</p>'],2,$active,true);
if(race([['usage',(string)$id,'same-session'],['usage',(string)$id,'same-session']])!==['saved','saved'])throw new RuntimeException('View race failed');
$r=(new Usage($db))->report('today');
if($r['site_visits']!==1||$r['article_views']!==1||(int)$articles->get($id)['view_count']!==1)throw new RuntimeException('Concurrent requests double-counted one visitor');
if(race([['usage',(string)$id,'another-session'],['usage',(string)$id,'third-session']])!==['saved','saved'])throw new RuntimeException('Distinct visitor race failed');
$r=(new Usage($db))->report('today');
if($r['site_visits']!==3||$r['article_views']!==3||(int)$articles->get($id)['view_count']!==3)throw new RuntimeException('Concurrent requests lost views');
echo "PASS usage concurrency: repeated session counted once; distinct sessions all counted\n";
$second=$articles->create($active);$articles->save($second,['title'=>'Second usage race','category_id'=>1,'keywords'=>'','html'=>'<p>Read me too</p>'],1,$active,true);
foreach([$id,$second] as $articleId) {
    $db->prepare("INSERT INTO article_views (article_id,session_hash,view_day) VALUES (?,'expired',UTC_DATE()-INTERVAL 3 DAY)")->execute([$articleId]);
}
$db->exec("INSERT INTO site_visits (session_hash,view_day) VALUES ('expired',UTC_DATE()-INTERVAL 3 DAY)");
if(race([['usage',(string)$id,'fourth-session'],['usage',(string)$second,'fifth-session']])!==['saved','saved'])throw new RuntimeException('Different article race failed');
$r=(new Usage($db))->report('today');
if($r['site_visits']!==5||$r['article_views']!==5)throw new RuntimeException('Concurrent cleanup lost views');
if((int)$db->query("SELECT COUNT(*) FROM article_views WHERE session_hash='expired'")->fetchColumn()!==0)throw new RuntimeException('Expired view cleanup failed');
echo "PASS usage concurrency: different articles and expired deduplication keys\n";
