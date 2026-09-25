<?php
declare(strict_types=1);
final class Installer {
    public static function requirements(string $configFile): array {
        $checks=['PHP 8.3 or later'=>PHP_VERSION_ID>=80300];
        foreach(['pdo_mysql','mbstring','dom','fileinfo','gd','ctype','iconv'] as $ext)$checks['PHP '.$ext]=extension_loaded($ext);
        $checks['Bundled HTML editor']=is_file(__DIR__.'/../public/assets/tinymce/tinymce.min.js');
        $checks['Bundled HTML sanitizer']=class_exists('HTMLPurifier');
        $checks['Writable configuration folder']=is_writable(dirname($configFile));
        $checks['Writable storage folder']=is_writable(__DIR__.'/../storage');
        return $checks;
    }
    public static function run(string $configFile): void {
        global $config;
        $base=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')),'/.');
        if(!preg_match('#^(?:/[A-Za-z0-9_-]+)*$#D',$base))$base='';
        $https=isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
        $config=['base_path'=>$base];
        ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
        session_name('simplephpkb_setup');
        session_set_cookie_params(['path'=>$base.'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);session_start();
        $_SESSION['install_claim'] ??= bin2hex(random_bytes(32));
        $error=null;$checks=self::requirements($configFile);
        $values=['db_host'=>'127.0.0.1','db_port'=>'3306','db_name'=>'kb','db_user'=>'kb','title'=>'SimplePHPKB','base_path'=>$base,'timezone'=>'America/Chicago','username'=>'admin','display_name'=>'Administrator','secure_cookies'=>$https?'1':'0'];
        try {
            if(is_file(dirname($configFile).'/installed.lock'))throw new UserError('Installation is already complete. Restore the missing configuration file from your backup.',409);
            if(($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST') {
                verify_csrf($_POST['csrf'] ?? null);
                foreach($values as $key=>$default)$values[$key]=input_string($_POST,$key,$key==='secure_cookies'?'0':$default);
                if(in_array(false,$checks,true))throw new UserError('Resolve the failed requirements before installing.');
                self::install($configFile,$_POST);
                header('Location: '.rtrim($values['base_path'],'/').'/index.php?route=login',true,303);return;
            }
        } catch(UserError $e){$error=$e->getMessage();http_response_code($e->status);}
        catch(Throwable $e){error_log('SimplePHPKB setup: '.$e->getMessage());$error='Setup could not finish. Check the database details and permissions, then retry in this browser. See the server error log for details.';http_response_code(500);}
        require __DIR__.'/../templates/install.php';
    }
    private static function install(string $configFile,array $data): void {
        $value=fn(string $k)=>trim(input_string($data,$k));
        $host=$value('db_host');$port=$value('db_port');$name=$value('db_name');$base=$value('base_path');
        if(!preg_match('/^[A-Za-z0-9.:-]{1,253}$/D',$host))throw new UserError('Enter a database hostname or IP address.');
        if(!ctype_digit($port)||(int)$port<1||(int)$port>65535)throw new UserError('Database port must be 1–65535.');
        if(!preg_match('/^[A-Za-z0-9_]{1,64}$/D',$name))throw new UserError('Database name must contain only letters, numbers and underscores.');
        if(!preg_match('#^(?:/[A-Za-z0-9_-]+)*$#D',$base))throw new UserError('Base path must be empty or a path such as /kb, without a trailing slash.');
        if(!in_array($value('timezone'),DateTimeZone::listIdentifiers(),true))throw new UserError('Enter a valid timezone, such as America/Chicago or UTC.');
        $title=$value('title');$user=$value('username');$display=$value('display_name');$password=input_string($data,'password');
        if($title===''||mb_strlen($title)>120)throw new UserError('Site title must be 1–120 characters.');
        if(!preg_match('/^[A-Za-z0-9_.@-]{3,80}$/D',$user))throw new UserError('Admin username must be 3–80 letters, numbers, or . _ @ -');
        if($display===''||mb_strlen($display)>120)throw new UserError('Admin display name must be 1–120 characters.');
        if(mb_strlen($password)<8||strlen($password)>72||str_contains($password,"\0"))throw new UserError('Password must be at least 8 characters and at most 72 bytes.');
        if($password!==input_string($data,'password_confirm'))throw new UserError('The admin passwords do not match.');
        $settings=['site_name'=>$title,'base_path'=>$base,'secure_cookies'=>input_string($data,'secure_cookies')==='1','timezone'=>$value('timezone'),'storage'=>dirname(__DIR__).'/storage','db'=>['dsn'=>"mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",'username'=>$value('db_user'),'password'=>input_string($data,'db_password')]];
        $dir=dirname($configFile);$lock=fopen($dir.'/setup.lock','c');
        if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new UserError('Another setup is running. Try again shortly.',409);
        try {
            clearstatcache();
            if(is_file($configFile)||is_file($dir.'/installed.lock'))throw new UserError('This site is already installed.',409);
            $pendingFile=$dir.'/setup.pending.php';$claim=hash('sha256',$_SESSION['install_claim']);
            if(is_file($pendingFile)) {
                $pending=require $pendingFile;
                if(!hash_equals($pending['claim'],$claim))throw new UserError('Setup was started in another browser. Continue there, or ask the server administrator to recover the pending installation.',409);
                // An interrupted install must resume the original target and identity.
                if($pending['settings']!==$settings||$pending['admin']['username']!==$user||$pending['admin']['display_name']!==$display||!password_verify($password,$pending['admin']['hash']))throw new UserError('Resume with the original setup details. The pending installation has been kept safe.',409);
                $db=Database::connect($settings['db']);
            } else {
                if(input_string($data,'create_database')==='1') {
                    $server=$settings['db'];$server['dsn']="mysql:host=$host;port=$port;charset=utf8mb4";
                    Database::connect($server)->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                }
                $db=Database::connect($settings['db']);
                if($db->query('SHOW TABLES')->fetch())throw new UserError('Choose an empty database. Existing tables will not be changed.');
                $pending=['claim'=>$claim,'settings'=>$settings,'admin'=>['username'=>$user,'display_name'=>$display,'hash'=>password_hash($password,PASSWORD_DEFAULT)]];
                self::writeConfig($pendingFile,$pending);
            }
            Database::migrate($db);
            Database::transaction($db,function()use($db,$pending,$title){
                $admin=$pending['admin'];$existing=$db->query('SELECT * FROM admins')->fetchAll();
                if(!$existing)$db->prepare('INSERT INTO admins(username,display_name,password_hash,active) VALUES(?,?,?,1)')->execute([$admin['username'],$admin['display_name'],$admin['hash']]);
                elseif(count($existing)!==1||$existing[0]['username']!==$admin['username']||$existing[0]['password_hash']!==$admin['hash'])throw new UserError('An admin already exists. Restore configuration instead of reinstalling.',409);
                $db->prepare('UPDATE site_settings SET title=? WHERE id=1')->execute([$title]);
            });
            self::writeConfig($configFile,$settings);
            file_put_contents($dir.'/installed.lock',gmdate('c'),LOCK_EX);
            unlink($pendingFile);$_SESSION=[];
        } finally {flock($lock,LOCK_UN);fclose($lock);}
    }
    private static function writeConfig(string $file,array $data): void {
        $temp=$file.'.tmp';
        if(file_put_contents($temp,"<?php\nreturn ".var_export($data,true).";\n",LOCK_EX)===false)throw new RuntimeException('Cannot write configuration.');
        chmod($temp,0600);
        if(!rename($temp,$file))throw new RuntimeException('Cannot publish configuration.');
    }
}
