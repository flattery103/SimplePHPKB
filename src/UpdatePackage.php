<?php
declare(strict_types=1);
final class UpdatePackage {
    public readonly string $directory;
    public function __construct(private string $root,string $storage) {
        $this->directory=$storage.'/updates';
        if(!is_dir($this->directory)&&!mkdir($this->directory,0700,true))throw new UserError('The storage folder must be writable.');
    }
    public static function allowed(string $path): bool {
        if(!preg_match('#^[A-Za-z0-9_.@/-]+$#D',$path)||str_starts_with($path,'/')||str_contains($path,'//'))return false;
        foreach(explode('/',$path) as $part)if($part==='.'||$part==='..'||$part==='')return false;
        return (bool)preg_match('#^(src/|templates/|database/[0-9]+_[A-Za-z0-9_-]+\.sql$|public/|vendor/|bin/|docs/|README\.md$|LICENSE$|CHANGELOG\.md$|THIRD_PARTY_NOTICES\.md$|composer\.(json|lock)$)#D',$path);
    }
    public function stage(string $file): array {
        if(!class_exists('ZipArchive'))throw new UserError('Enable the PHP zip extension to upload updates.');
        if(!is_file($file)||filesize($file)>32*1024*1024)throw new UserError('Update ZIP must be 32 MB or smaller.');
        if(is_file($this->directory.'/state.json'))throw new UserError('Finish the pending update before uploading another.',409);
        $zip=new ZipArchive();if($zip->open($file)!==true)throw new UserError('Unable to open the update ZIP.');
        $stage=$this->directory.'/stage-'.bin2hex(random_bytes(12));
        try {
            if($zip->numFiles>6000)throw new UserError('The update ZIP contains too many files.');
            $manifestInfo=$zip->statName('SimplePHPKB/update-manifest.json');
            if(!$manifestInfo||$manifestInfo['size']>1024*1024)throw new UserError('Use a SimplePHPKB update ZIP with an update manifest.');
            $manifest=json_decode((string)$zip->getFromName('SimplePHPKB/update-manifest.json'),true);
            $this->validateManifest($manifest);
            $seen=[];$total=0;
            for($i=0;$i<$zip->numFiles;$i++) {
                $stat=$zip->statIndex($i);$name=$stat['name'];
                if(isset($seen[$name]))throw new UserError('Duplicate paths in update ZIP.');$seen[$name]=true;
                $zip->getExternalAttributesIndex($i,$opsys,$attr);
                if($opsys===ZipArchive::OPSYS_UNIX&&(($attr>>16)&0170000)===0120000)throw new UserError('Links are not allowed in update ZIPs.');
                $total+=$stat['size'];if($total>160*1024*1024||$stat['size']>32*1024*1024)throw new UserError('Expanded update ZIP is too large.');
                if($name==='SimplePHPKB/update-manifest.json')continue;
                if(!str_starts_with($name,'SimplePHPKB/'))throw new UserError('Invalid update ZIP folder.');
                $path=substr($name,12);
                if(!self::allowed($path)||!isset($manifest['files'][$path]))throw new UserError('Unexpected or unsafe file in update ZIP.');
            }
            if(count($seen)!==count($manifest['files'])+1)throw new UserError('The update ZIP is incomplete.');
            if(!mkdir($stage,0700))throw new UserError('Unable to stage the update.');
            foreach($manifest['files'] as $path=>$hash) {
                $contents=$zip->getFromName('SimplePHPKB/'.$path);
                if($contents===false||!hash_equals($hash,hash('sha256',$contents)))throw new UserError('An update file failed its integrity check.');
                $target=$stage.'/'.$path;if(!is_dir(dirname($target)))mkdir(dirname($target),0700,true);
                if(file_put_contents($target,$contents)===false)throw new UserError('Not enough space or permission to stage the update.');
            }
            $init=(string)file_get_contents($stage.'/src/init.php');
            if(!preg_match("/const KB_VERSION = '([^']+)'/",$init,$v)||$v[1]!==$manifest['version'])throw new UserError('Package version does not match its code.');
            $manifest['stage']=basename($stage);
            self::writeJson($this->directory.'/staged.json',$manifest);
            return $manifest;
        } catch(Throwable $e){if(is_dir($stage))self::removeTree($stage);throw $e;}
        finally {$zip->close();}
    }
    private function validateManifest(mixed $m): void {
        if(!is_array($m)||($m['app']??'')!=='SimplePHPKB'||($m['format']??0)!==1||!is_array($m['files']??null)||!$m['files'])throw new UserError('Invalid update manifest.');
        foreach(['version','minimum_version'] as $key)if(!is_string($m[$key]??null)||!preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D',$m[$key]))throw new UserError('Invalid update version.');
        if(!version_compare($m['version'],KB_VERSION,'>'))throw new UserError('Choose a release newer than the installed version.');
        if(version_compare(KB_VERSION,$m['minimum_version'],'<'))throw new UserError('Install version '.$m['minimum_version'].' before this update.');
        foreach($m['files'] as $path=>$hash)if(!is_string($path)||!self::allowed($path)||!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new UserError('Unsafe update manifest.');
        foreach(['src/init.php','src/Updates.php','src/Database.php','public/index.php'] as $required)if(!isset($m['files'][$required]))throw new UserError('Required application files are missing.');
    }
    public function staged(): ?array {
        $file=$this->directory.'/staged.json';
        if(!is_file($file))return null;
        $m=json_decode((string)file_get_contents($file),true);
        if(!is_array($m)||!preg_match('/^stage-[a-f0-9]{24}$/D',$m['stage']??''))throw new UserError('Staged update metadata is damaged. Upload the package again.');
        return $m;
    }
    public function preflight(array $m): void {
        foreach($m['files'] as $path=>$hash) {
            if(!self::allowed($path))throw new UserError('Unsafe update path.');
            $source=$this->directory.'/'.$m['stage'].'/'.$path;
            if(!is_file($source)||is_link($source)||!hash_equals($hash,hash_file('sha256',$source)))throw new UserError('The staged files failed their integrity check.');
            $cursor=$this->root;
            foreach(explode('/',$path) as $part) {
                $cursor.='/'.$part;
                if(is_link($cursor))throw new UserError('Updates cannot replace paths containing symbolic links.');
                if(file_exists($cursor)&&!is_writable($cursor))throw new UserError('The web server cannot write '.$path.'. Change file permissions through your hosting panel.');
            }
            $parent=dirname($this->root.'/'.$path);while(!is_dir($parent))$parent=dirname($parent);
            if(!is_writable($parent))throw new UserError('The web server cannot create '.$path.'. Change folder permissions through your hosting panel.');
        }
    }
    public function apply(array $m): void {
        $this->preflight($m);
        // The front controller is last, leaving the recovery route intact as long as possible.
        $files=$m['files'];$index=$files['public/index.php'];unset($files['public/index.php']);$files['public/index.php']=$index;
        foreach($files as $path=>$hash) {
            $target=$this->root.'/'.$path;$parent=dirname($target);
            if(!is_dir($parent)&&!mkdir($parent,0755,true))throw new UserError('Unable to create update folder. Retry after checking permissions.');
            $temp=tempnam($parent,'.kb-update-');
            try {
                if(!$temp||!copy($this->directory.'/'.$m['stage'].'/'.$path,$temp))throw new UserError('Unable to copy update file. Retry after checking disk space.');
                $mode=is_file($target)?(fileperms($target)&0777):((fileperms($parent)&0020)?0664:0644);
                chmod($temp,$mode);
                if(!rename($temp,$target))throw new UserError('Unable to replace update file. Retry after checking permissions.');
                if(function_exists('opcache_invalidate'))opcache_invalidate($target,true);
            } finally {if($temp&&is_file($temp))unlink($temp);}
        }
    }
    public function prepareRecovery(): void {
        $destination=$this->directory.'/recovery';
        if(is_dir($destination))self::removeTree($destination);
        foreach(['src','templates'] as $folder) {
            if(!mkdir($destination.'/'.$folder,0700,true))throw new RuntimeException('Cannot prepare update recovery.');
            foreach(glob($this->root.'/'.$folder.'/*.php') as $file) {
                $target=$destination.'/'.$folder.'/'.basename($file);
                if(!copy($file,$target)||hash_file('sha256',$target)!==hash_file('sha256',$file))throw new RuntimeException('Cannot verify update recovery.');
            }
        }
        // Recovery requires PDO/auth only; never load a partially updated Composer tree.
        $init="<?php\ndeclare(strict_types=1);\nconst KB_VERSION = ".var_export(KB_VERSION,true).";\n";
        $init.='spl_autoload_register(function(string $name): void { if(preg_match("/^[A-Za-z]+$/",$name)&&is_file(__DIR__."/$name.php")) require __DIR__."/$name.php"; });'."\n";
        $init.='require_once __DIR__."/Support.php";'."\n";
        if(file_put_contents($destination.'/src/init.php',$init)===false)throw new RuntimeException('Cannot write recovery bootstrap.');
    }
    public function discard(): void {
        $m=$this->staged();if($m&&is_dir($this->directory.'/'.$m['stage']))self::removeTree($this->directory.'/'.$m['stage']);
        if(is_file($this->directory.'/staged.json'))unlink($this->directory.'/staged.json');
    }
    public static function writeJson(string $path,array $value): void {
        $temp=$path.'.tmp';
        if(file_put_contents($temp,json_encode($value,JSON_THROW_ON_ERROR),LOCK_EX)===false||!rename($temp,$path))throw new RuntimeException('Unable to write update state.');
        chmod($path,0600);
    }
    private static function removeTree(string $dir): void {
        foreach(new FilesystemIterator($dir) as $f){if($f->isDir()&&!$f->isLink())self::removeTree($f->getPathname());else unlink($f->getPathname());}rmdir($dir);
    }
}
