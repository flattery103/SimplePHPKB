<?php
require __DIR__.'/../src/init.php';
function ok(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function rejects(callable $f,string $m):void{try{$f();}catch(UserError $e){return;}throw new RuntimeException($m);}
ok(class_exists('Releases'),'GitHub release service is available');
$tmp=sys_get_temp_dir().'/kb-release-'.bin2hex(random_bytes(5));mkdir($tmp);
$calls=0;
$fetch=function()use(&$calls){$calls++;return ['status'=>200,'body'=>json_encode(['tag_name'=>'v0.7.0','draft'=>false,'prerelease'=>false])];};
$r=new Releases($tmp,$fetch);
$a=$r->check();ok($a['status']==='available'&&$a['version']==='0.7.0','newer stable release');
$r->check();$r->check(true);ok($calls===1,'automatic cache and manual cooldown');
foreach([[404,'none'],[403,'unavailable'],[500,'unavailable']] as [$code,$status]){unlink($tmp.'/updates/release.json');$r=new Releases($tmp,fn()=>['status'=>$code,'body'=>'{}']);ok($r->check()['status']===$status,'HTTP status handling');}
foreach([['tag_name'=>'garbage'],['tag_name'=>'v9.0.0','prerelease'=>true],['tag_name'=>'v9.0.0','draft'=>true]] as $release){unlink($tmp.'/updates/release.json');$r=new Releases($tmp,fn()=>['status'=>200,'body'=>json_encode($release)]);ok($r->check()['status']==='unavailable','ignore unsafe/unpublished versions');}
unlink($tmp.'/updates/release.json');unlink($tmp.'/updates/release.lock');rmdir($tmp.'/updates');rmdir($tmp);
echo "PASS GitHub release status and cache\n";
$tmp=sys_get_temp_dir().'/kb-package-'.bin2hex(random_bytes(5));mkdir($tmp);mkdir($tmp.'/storage');
ok(class_exists('UpdatePackage'),'ZIP update service is available');
function packageFile(string $target,array $files,array $extra=[]):void{
    $files+=['src/init.php'=>"<?php const KB_VERSION = '0.7.0';",'public/index.php'=>'<?php','src/Updates.php'=>'<?php','src/Database.php'=>'<?php'];
    $manifest=['app'=>'SimplePHPKB','format'=>1,'version'=>'0.7.0','minimum_version'=>'0.6.0','files'=>array_map(fn($v)=>hash('sha256',$v),$files)];
    $z=new ZipArchive();$z->open($target,ZipArchive::CREATE|ZipArchive::OVERWRITE);
    $z->addFromString('SimplePHPKB/update-manifest.json',json_encode($manifest));
    foreach($files+$extra as $p=>$text)$z->addFromString('SimplePHPKB/'.$p,$text);
    $z->close();
}
$p=new UpdatePackage($tmp,$tmp.'/storage');
packageFile($tmp.'/ok.zip',['public/assets/app.css'=>'body{}']);
$m=$p->stage($tmp.'/ok.zip');ok($m['version']==='0.7.0','valid package is staged');ok(!is_file($tmp.'/public/index.php'),'staging never overwrites code');
foreach(['../escape.php','config/config.php','storage/media/x','public/../config/config.php','/tmp/escape','public\\evil.php'] as $bad){packageFile($tmp.'/bad.zip',[$bad=>'x']);rejects(fn()=>$p->stage($tmp.'/bad.zip'),'unsafe ZIP path rejected: '.$bad);}
packageFile($tmp.'/bad.zip',[],['public/extra.php'=>'x']);rejects(fn()=>$p->stage($tmp.'/bad.zip'),'unlisted ZIP files rejected');
packageFile($tmp.'/bad.zip',[]);$z=new ZipArchive();$z->open($tmp.'/bad.zip');$z->addFromString('SimplePHPKB/src/Updates.php','tamper');$z->close();rejects(fn()=>$p->stage($tmp.'/bad.zip'),'tampered hash rejected');
packageFile($tmp.'/bad.zip',[]);$z=new ZipArchive();$z->open($tmp.'/bad.zip');$z->setExternalAttributesName('SimplePHPKB/src/Updates.php',ZipArchive::OPSYS_UNIX,0120777<<16);$z->close();rejects(fn()=>$p->stage($tmp.'/bad.zip'),'symlink ZIP rejected');
function rmtest(string $dir):void{foreach(new FilesystemIterator($dir) as $f){if($f->isDir()&&!$f->isLink())rmtest($f->getPathname());else unlink($f->getPathname());}rmdir($dir);}rmtest($tmp);
echo "PASS update package paths, manifest, hashes, symlinks, staging isolation\n";
