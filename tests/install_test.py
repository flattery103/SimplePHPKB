import pathlib,zipfile,tempfile,subprocess,os,json,re,shutil,urllib.request,time
ROOT=pathlib.Path(__file__).resolve().parent.parent
DSN=os.environ.get('KB_INSTALL_TEST_DSN','')
assert re.search(r'dbname=[A-Za-z0-9_]+_install_test(?:;|$)',DSN),'set KB_INSTALL_TEST_DSN to an empty disposable _install_test database'
USER=os.environ.get('KB_TEST_USER','root');PASSWORD=os.environ.get('KB_TEST_PASSWORD','')
with tempfile.TemporaryDirectory(prefix='simplephpkb-install-') as temp:
    temp=pathlib.Path(temp)
    version=re.search(r"KB_VERSION = '([^']+)'",(ROOT/'src/init.php').read_text()).group(1)
    with zipfile.ZipFile(ROOT/f'dist/SimplePHPKB-v{version}.zip') as z:z.extractall(temp)
    app=temp/'SimplePHPKB'
    assert not (app/'config/config.php').exists()
    config={'site_name':'Installed test KB','base_path':'','secure_cookies':False,'timezone':'UTC','storage':str(app/'storage'),'db':{'dsn':DSN,'username':USER,'password':PASSWORD}}
    config_php="<?php return json_decode(<<<'KBJSON'\n"+json.dumps(config)+"\nKBJSON, true);\n"
    (app/'config/config.php').write_text(config_php)
    env=os.environ.copy();env['KB_CONFIG']=str(app/'config/config.php')
    def php(code):
        run=subprocess.run(['php','-r',code],cwd=app,env=env,capture_output=True,text=True)
        assert run.returncode==0,run.stdout+run.stderr
        return run.stdout
    # Reset only the explicitly designated test database.
    reset="require 'src/bootstrap.php'; if(!preg_match('/dbname=[A-Za-z0-9_]+_install_test(?:;|$)/',$config['db']['dsn'])) exit(2); $db->exec('SET FOREIGN_KEY_CHECKS=0'); foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) { if(!preg_match('/^[a-z_]+$/D',$t))exit(2); $db->exec('DROP TABLE `'.$t.'`'); } $db->exec('SET FOREIGN_KEY_CHECKS=1');"
    php(reset)
    for _ in range(2):subprocess.run(['php','bin/kb.php','migrate'],cwd=app,env=env,check=True,capture_output=True)
    result=subprocess.run(['php','bin/kb.php','admin:create','installer'],input='Only-Installation-Test-Password-749\n',cwd=app,env=env,capture_output=True,text=True)
    assert result.returncode==0,result.stdout+result.stderr
    code="""require 'src/bootstrap.php';$a=new Articles($db);$id=$a->create(1);$image=imagecreatetruecolor(20,20);$p=tempnam(sys_get_temp_dir(),'install-image');imagepng($image,$p);$m=(new Media($db))->store($id,$p,1);unlink($p);$a->save($id,['title'=>'Installation password help','category_id'=>1,'keywords'=>'recovery','html'=>'<p>Published instructions</p><img src="'.e($m['location']).'" alt="Test">'],1,1,true);$draft=$a->get($id,true);$draft['html']='<p>Private draft</p>';$a->save($id,$draft,2,1,false);echo json_encode(['id'=>$id,'media'=>$m['id']]);"""
    fixture=json.loads(php(code))
    connection=dict(re.findall(r'(host|port|dbname|unix_socket)=([^;]+)',DSN))
    defaults=temp/'client.cnf';defaults.write_text('[client]\nuser='+USER+'\npassword="'+PASSWORD.replace('\\','\\\\').replace('"','\\"')+'"\n'+('socket='+connection['unix_socket']+'\n' if 'unix_socket' in connection else 'protocol=tcp\nhost='+connection.get('host','127.0.0.1')+'\nport='+connection.get('port','3306')+'\n'));defaults.chmod(0o600)
    dump=temp/'backup.sql'
    with dump.open('wb') as out:subprocess.run(['mariadb-dump','--defaults-file='+str(defaults),'--single-transaction',connection['dbname']],stdout=out,check=True)
    shutil.copytree(app/'storage/media',temp/'media-backup')
    php(reset);shutil.rmtree(app/'storage/media')
    with dump.open('rb') as inp:subprocess.run(['mariadb','--defaults-file='+str(defaults),connection['dbname']],stdin=inp,check=True)
    shutil.copytree(temp/'media-backup',app/'storage/media')
    verification="""require 'src/bootstrap.php';$a=new Articles($db);$p=$a->get(1);if(!$p||!str_contains($p['html'],'Published instructions')||str_contains($p['html'],'Private draft'))exit(3);if(!str_contains($a->get(1,true)['html'],'Private draft'))exit(4);if((new Search($db))->query('pasword',null)['total']!==1)exit(5);echo 'restored';"""
    assert php(verification)=='restored'
    log=open(temp/'server.log','w');server=subprocess.Popen(['php','-S','127.0.0.1:8092','-t',str(app/'public')],cwd=app,env=env,stdout=log,stderr=log)
    client=urllib.request.build_opener(urllib.request.ProxyHandler({}))
    try:
        for _ in range(40):
            try:response=client.open('http://127.0.0.1:8092/index.php?route=article&id=1');break
            except OSError:time.sleep(.1)
        assert b'Published instructions' in response.read()
        image=client.open('http://127.0.0.1:8092/index.php?route=media&id='+fixture['media']);assert image.headers['Content-Type']=='image/png' and len(image.read())>0
        assert client.open('http://127.0.0.1:8092/assets/tinymce/tinymce.min.js').status==200
    finally:server.terminate();server.wait(timeout=5);log.close()
print('PASS extracted install: migrations twice, admin creation, article/image publish + draft, DB/media backup restore, fuzzy search, reader and local editor asset HTTP')
