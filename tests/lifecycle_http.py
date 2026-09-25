"""Real web install/update tests against a disposable database and extracted app."""
import sys, pathlib, tempfile, shutil, os, subprocess, urllib.request, urllib.parse, urllib.error, http.cookiejar, re, time, json, zipfile, hashlib
ROOT=pathlib.Path(__file__).resolve().parent.parent
DSN=os.environ['KB_TEST_DSN']
assert re.search(r'dbname=[A-Za-z0-9_]+_test(?:;|$)',DSN)
conn=dict(re.findall(r'(host|port|dbname)=([^;]+)',DSN))
with tempfile.TemporaryDirectory(prefix='kb-web-lifecycle-') as tmp:
    app=pathlib.Path(tmp)/'app'
    shutil.copytree(ROOT,app,ignore=shutil.ignore_patterns('.git','.superpowers','dist','tests','storage','config.php','__pycache__'))
    (app/'storage').mkdir()
    env=os.environ.copy();env.pop('KB_CONFIG',None)
    def php(code):
        p=subprocess.run(['php','-r',code],cwd=app,env=env,capture_output=True,text=True)
        assert p.returncode==0,p.stdout+p.stderr
        return p.stdout
    php("require 'src/init.php';$db=Database::connect(['dsn'=>getenv('KB_TEST_DSN'),'username'=>getenv('KB_TEST_USER')?:'root','password'=>getenv('KB_TEST_PASSWORD')?:'']);$db->exec('SET FOREIGN_KEY_CHECKS=0');foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t)$db->exec('DROP TABLE `'.$t.'`');")
    log=open(pathlib.Path(tmp)/'http.log','w')
    server=subprocess.Popen(['php','-S','127.0.0.1:8094','-t',str(app/'public')],env=env,stdout=log,stderr=log)
    base='http://127.0.0.1:8094/index.php'
    def client():return urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    browser=client()
    def request(route='',data=None,who=None):
        try:
            r=(who or browser).open(base+'?'+urllib.parse.urlencode({'route':route}),None if data is None else urllib.parse.urlencode(data).encode(),timeout=30)
            return r.status,r.read().decode(errors='replace')
        except urllib.error.HTTPError as e:return e.code,e.read().decode(errors='replace')
    def csrf(body):return re.search(r'name="csrf" value="([^"]+)"',body).group(1)
    try:
        for _ in range(40):
            try: status,body=request();break
            except OSError:time.sleep(.1)
        assert status==200 and 'Install SimplePHPKB' in body,'unconfigured app must show browser installer'
        token=csrf(body)
        data={'csrf':token,'db_host':conn['host'],'db_port':conn['port'],'db_name':conn['dbname'],'db_user':os.environ.get('KB_TEST_USER','root'),'db_password':os.environ.get('KB_TEST_PASSWORD',''),'title':'Web test KB','base_path':'','timezone':'America/Chicago','username':'installer','display_name':'Installer','password':'test1234','password_confirm':'test1234'}
        assert request('',dict(data,csrf='bad'))[0]==403,'install CSRF'
        assert request('',dict(data,db_name='bad;host=evil'))[0]==422,'DSN injection'
        assert not (app/'config/config.php').exists(),'invalid setup cannot publish config'
        php("require 'src/init.php';$db=Database::connect(['dsn'=>getenv('KB_TEST_DSN'),'username'=>getenv('KB_TEST_USER')?:'root','password'=>getenv('KB_TEST_PASSWORD')?:'']);$db->exec('CREATE TABLE existing_content(id INT)');")
        assert request('',data)[0]==422,'nonempty database refused'
        php("require 'src/init.php';$db=Database::connect(['dsn'=>getenv('KB_TEST_DSN'),'username'=>getenv('KB_TEST_USER')?:'root','password'=>getenv('KB_TEST_PASSWORD')?:'']);$db->exec('DROP TABLE existing_content');")
        broken=app/'database/005_interrupted.sql';broken.write_text('INVALID SQL;\n')
        assert request('',data)[0]==500,'partial setup failure'
        assert not (app/'config/config.php').exists(),'failed install does not publish config'
        stranger=client();_,other=request(who=stranger)
        assert request('',dict(data,csrf=csrf(other)),who=stranger)[0]==409,'another session cannot claim partial installation'
        broken.unlink()
        status,body=request('',data)
        assert status==200 and 'Web test KB' in body,(status,body)
        assert (app/'config/config.php').exists(),'config generated'
        assert 'Install SimplePHPKB' not in request()[1],'installer locked'
        status,body=request('login');token=csrf(body)
        status,body=request('login',{'csrf':token,'username':'installer','password':'test1234'})
        assert status==200 and 'Manage articles' in body,'first admin works'
        print('PASS browser installation: form, validation, CSRF, generated config, lockout and admin login')
        status,body=request('updates')
        assert status==200 and 'Application updates' in body,'standalone update center'
        token=csrf(body)
        assert request('updates',{'csrf':'bad','action':'migrate'})[0]==403,'update CSRF'
        assert request('updates',{'csrf':token,'action':'backup'},who=client())[0] in (401,403),'anonymous backup denied'
        php("require 'src/bootstrap.php';$db->prepare('INSERT INTO categories(name) VALUES(?)')->execute([\"日本語 café \\0 quote' slash\\\\\"]);")
        category_before=php("require 'src/bootstrap.php';echo json_encode($db->query('SELECT * FROM categories ORDER BY id')->fetchAll());")
        status,body=request('updates',{'csrf':token,'action':'backup'})
        assert status==200 and 'Download backup' in body,(status,body)
        backup=app/'storage/updates/backup.zip'
        assert backup.exists(),'browser-created backup'
        with zipfile.ZipFile(backup) as z:
            assert 'database.sql' in z.namelist() and 'SimplePHPKB/config/config.php' in z.namelist(),'backup has database and configuration'
            sql=z.read('database.sql').decode()
        php("require 'src/bootstrap.php';$db->exec("+json.dumps(sql)+");")
        assert 'Web test KB' in request('home')[1],'SQL backup restores site'
        assert php("require 'src/bootstrap.php';echo json_encode($db->query('SELECT * FROM categories ORDER BY id')->fetchAll());")==category_before,'backup preserves ids, Unicode, null bytes and escapes' 
        (app/'database/005_lifecycle_test.sql').write_text("CREATE TABLE IF NOT EXISTS web_upgrade_probe (id INT PRIMARY KEY);\n")
        assert request('home',who=client())[0]==503,'readers see maintenance for pending schema'
        status,body=request('updates',who=client())
        assert status==200 and 'Admin sign in' in body,'admin can sign in during maintenance'
        status,body=request('updates',{'csrf':token,'action':'migrate'})
        assert status==200 and 'Database is up to date' in body,(status,body)
        assert request('home')[0]==200,'site reopens after browser migration'
        print('PASS update auth, CSRF, browser backup/SQL restore, maintenance and migrations')
        config_before=(app/'config/config.php').read_bytes()
        (app/'storage/media').mkdir(exist_ok=True)
        (app/'storage/media/preserve.txt').write_text('uploaded content')
        fields={}
        for folder in ['src','templates','database','public','vendor','bin']:
            for path in (app/folder).rglob('*'):
                if path.is_file():fields[path.relative_to(app).as_posix()]=path.read_bytes()
        fields['src/init.php']=fields['src/init.php'].replace(b"KB_VERSION = '0.6.0'",b"KB_VERSION = '0.7.0'")
        fields['database/006_web_upgrade.sql']=b'CREATE TABLE IF NOT EXISTS web_upgrade_six (id INT PRIMARY KEY);\n'
        manifest={'app':'SimplePHPKB','format':1,'version':'0.7.0','minimum_version':'0.6.0','files':{p:hashlib.sha256(v).hexdigest() for p,v in fields.items()}}
        update=pathlib.Path(tmp)/'update.zip'
        with zipfile.ZipFile(update,'w',zipfile.ZIP_DEFLATED) as z:
            z.writestr('SimplePHPKB/update-manifest.json',json.dumps(manifest))
            for p,v in fields.items():z.writestr('SimplePHPKB/'+p,v)
        token=csrf(request('updates')[1])
        boundary='KBUpdateBoundary'
        payload=(f'--{boundary}\r\nContent-Disposition: form-data; name="csrf"\r\n\r\n{token}\r\n--{boundary}\r\nContent-Disposition: form-data; name="action"\r\n\r\nupload\r\n--{boundary}\r\nContent-Disposition: form-data; name="package"; filename="update.zip"\r\nContent-Type: application/zip\r\n\r\n').encode()+update.read_bytes()+f'\r\n--{boundary}--\r\n'.encode()
        req=urllib.request.Request(base+'?route=updates',payload,{'Content-Type':'multipart/form-data; boundary='+boundary})
        response=browser.open(req);body=response.read().decode()
        assert 'Review update' in body and '0.7.0' in body,'real multipart ZIP upload'
        assert request('home')[0]==200,'staging does not put site in maintenance'
        assert request('updates',{'csrf':token,'action':'apply'})[0]==422,'backup acknowledgement required'
        status,body=request('updates',{'csrf':token,'action':'apply','backup_saved':'1'})
        assert status==200 and 'Apply database changes and finish' in body,'normal file update with automatic backup'
        # Emulate an interruption just before finalizing the durable file-copy journal.
        staged=json.loads((app/'storage/updates/staged.json').read_text())
        (app/'storage/updates/state.json').write_text(json.dumps({'phase':'files','version':'0.7.0'}))
        autoload=app/'vendor/autoload.php'
        autoload.write_bytes(autoload.read_bytes().replace(b'ComposerAutoloaderInit',b'InterruptedComposerAutoloaderInit'))
        assert request('home',who=client())[0]==503,'interrupted Composer copies stay in maintenance'
        assert 'Resume update' in request('updates')[1],'recovery loads independently of mixed Composer files'
        status,body=request('updates',{'csrf':token,'action':'apply'})
        assert status==200 and 'Apply database changes and finish' in body,(status,body)
        assert (app/'config/config.php').read_bytes()==config_before,'update preserves configuration bytes'
        assert (app/'storage/media/preserve.txt').read_text()=='uploaded content','update preserves media'
        assert '0.7.0' in body,'new request loads new application version'
        assert request('home')[0]==503,'files alone do not reopen site'
        migration=app/'database/006_web_upgrade.sql';migration.write_text('INVALID SQL;\n')
        assert request('updates',{'csrf':token,'action':'migrate'})[0]==500,'failed migration remains recoverable'
        assert request('home')[0]==503,'failed SQL keeps maintenance'
        assert (app/'storage/updates/backup.zip').exists(),'original backup retained'
        migration.write_bytes(fields['database/006_web_upgrade.sql'])
        assert request('updates',{'csrf':token,'action':'migrate'})[0]==200,'retry SQL succeeds'
        assert request('home')[0]==200,'upgraded site reopens'
        assert not (app/'storage/updates/state.json').exists(),'completed update clears maintenance'
        print('PASS uploaded release, recovery from interrupted file/SQL updates, config/media preservation')


    finally:
        server.terminate();server.wait(timeout=5);log.close()
        if sys.exc_info()[0]:print((pathlib.Path(tmp)/'http.log').read_text()[-6000:])
