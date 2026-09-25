<?php
declare(strict_types=1);
final class Updates {
    public function __construct(private PDO $db) {}
    public static function pending(PDO $db): bool {
        global $config;
        return is_file($config['storage'].'/updates/state.json')||Database::pending($db)!==[];
    }
    public function run(): void {
        global $config,$lifecycleLock;
        $auth=new Auth($this->db);$admin=$auth->current();$error=null;
        $packages=new UpdatePackage(KB_APP_ROOT,$config['storage']);
        $directory=$packages->directory;$stateFile=$directory.'/state.json';
        try {
            $method=$_SERVER['REQUEST_METHOD']??'GET';
            if(!in_array($method,['GET','HEAD','POST'],true))throw new UserError('Method not allowed.',405);
            if($method==='POST') {
                $action=input_string($_POST,'action');
                if(!$admin&&$action!=='login')throw new UserError('Sign in to continue.',401);
                verify_csrf($_POST['csrf']??null);
                if($action==='login') {
                    if(!$auth->login(trim(input_string($_POST,'username')),input_string($_POST,'password'),$_SERVER['REMOTE_ADDR']??'unknown'))throw new UserError('Unable to sign in. Check your details or wait 15 minutes after repeated attempts.',401);
                    redirect_to('updates');
                }
                if($action==='check'){(new Releases($config['storage']))->check(true);redirect_to('updates');}
                if(!in_array($action,['backup','upload','apply','migrate','discard'],true))throw new UserError('Unknown update action.',400);
                if(!flock($lifecycleLock,LOCK_EX|LOCK_NB))throw new UserError('The site is busy. Wait a moment and retry.',503);
                $state=is_file($stateFile)?json_decode((string)file_get_contents($stateFile),true):null;
                if(is_file($stateFile)&&!is_array($state))throw new UserError('Update state is damaged. Restore the site backup or ask the server administrator for help.',409);
                if($state&&in_array($action,['backup','upload','discard'],true))throw new UserError('Finish the pending update first. Its original backup is preserved.',409);
                if($action==='backup') {Backup::create($this->db,$directory.'/backup.zip');flash('Backup created. Download it before applying changes.');}
                elseif($action==='upload') {
                    if(Database::pending($this->db))throw new UserError('Apply pending database changes before uploading another version.',409);
                    $file=$_FILES['package']??null;
                    if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']))throw new UserError('Select an update ZIP within your server upload limit.');
                    $packages->discard();$packages->stage($file['tmp_name']);flash('Update checked. Review the version and create a backup before applying.');
                }
                elseif($action==='discard'){$packages->discard();flash('Staged update discarded.');}
                elseif($action==='apply') {
                    $m=$packages->staged();if(!$m)throw new UserError('Upload an update first.');
                    if($state&&($state['phase']??'')!=='files')throw new UserError('Files are already updated. Apply database changes next.',409);
                    if(!$state) {
                        if(!is_file($directory.'/backup.zip')||input_string($_POST,'backup_saved')!=='1')throw new UserError('Create and download a backup, then confirm it is saved.');
                        if(version_compare($m['version'],KB_VERSION,'<='))throw new UserError('The staged update is no longer newer than this installation. Discard it.');
                        $packages->preflight($m);
                        // Refresh the server backup immediately before the first write.
                        Backup::create($this->db,$directory.'/backup.zip');
                        $packages->prepareRecovery();
                        UpdatePackage::writeJson($stateFile,['phase'=>'files','version'=>$m['version'],'started_at'=>gmdate('c')]);
                    }
                    $packages->apply($m);
                    UpdatePackage::writeJson($stateFile,['phase'=>'database','version'=>$m['version'],'started_at'=>$state['started_at']??gmdate('c')]);
                    flash('Application files updated. Apply database changes to finish.');
                }
                elseif($action==='migrate') {
                    if($state&&($state['phase']??'')==='files')throw new UserError('Resume copying application files before running database changes.',409);
                    if($state&&($state['version']??'')!==KB_VERSION)throw new UserError('The running code version does not match the update. Refresh or ask the server administrator to clear the PHP opcode cache.',409);
                    if(!$state) {
                        Backup::create($this->db,$directory.'/backup.zip');
                        UpdatePackage::writeJson($stateFile,['phase'=>'database','version'=>KB_VERSION,'started_at'=>gmdate('c')]);
                    }
                    Database::migrate($this->db);
                    unlink($stateFile);$packages->discard();flash('Database is up to date. The knowledge base is ready.');
                }
                redirect_to('updates');
            }
            if(input_string($_GET,'download')==='backup') {
                if(!$admin)throw new UserError('Sign in to download backups.',401);
                $file=$directory.'/backup.zip';if(!is_file($file))throw new UserError('Create a backup first.',404);
                header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="SimplePHPKB-backup-'.gmdate('Ymd-His',filemtime($file)).'.zip"');header('Content-Length: '.filesize($file));if($method!=='HEAD')readfile($file);return;
            }
        } catch(UserError $e){$error=$e->getMessage();http_response_code($e->status);}
        catch(Throwable $e){error_log('SimplePHPKB update: '.$e);$error='The operation could not finish. Your staged update and backup have been kept. Check disk space, permissions and the server log, then retry.';http_response_code(500);}
        $state=is_file($stateFile)?json_decode((string)file_get_contents($stateFile),true):null;
        $staged=$admin?$packages->staged():null;$pending=$admin?Database::pending($this->db):[];
        $release=$admin?(new Releases($config['storage']))->check():null;
        require __DIR__.'/../templates/updates.php';
    }
}
