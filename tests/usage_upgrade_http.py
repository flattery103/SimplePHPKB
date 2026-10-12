"""Upload the real usage update to a v0.6.0 installation through its web updater.

Requires KB_BASELINE_ZIP, a built current update ZIP, and a disposable KB_TEST_DSN.
"""
import pathlib, tempfile, zipfile, subprocess, os, re, json, urllib.request, urllib.parse, urllib.error, http.cookiejar, time

root=pathlib.Path(__file__).resolve().parent.parent
baseline=pathlib.Path(os.environ['KB_BASELINE_ZIP'])
dsn=os.environ['KB_TEST_DSN']
assert re.search(r'dbname=[A-Za-z0-9_]+_test(?:;|$)',dsn),'disposable _test database required'
version=re.search(r"KB_VERSION = '([^']+)'",(root/'src/init.php').read_text()).group(1)
with tempfile.TemporaryDirectory(prefix='kb-usage-upgrade-') as tmp:
    tmp=pathlib.Path(tmp)
    with zipfile.ZipFile(baseline) as z:z.extractall(tmp)
    app=tmp/'SimplePHPKB'
    assert "KB_VERSION = '0.6.0'" in (app/'src/init.php').read_text(),'baseline must be 0.6.0'
    config={'site_name':'Preserved KB','base_path':'','secure_cookies':False,'timezone':'America/Chicago','storage':str(app/'storage'),'db':{'dsn':dsn,'username':os.environ.get('KB_TEST_USER','root'),'password':os.environ.get('KB_TEST_PASSWORD','')}}
    config_path=app/'config/config.php'
    config_path.write_text("<?php return json_decode(<<<'KBJSON'\n"+json.dumps(config)+"\nKBJSON, true);\n")
    before=config_path.read_bytes()
    env=os.environ.copy();env['KB_CONFIG']=str(config_path)
    def php(code):
        return subprocess.check_output(['php','-r',"require 'src/bootstrap.php';"+code],cwd=app,env=env,text=True)
    php("$db->exec('SET FOREIGN_KEY_CHECKS=0');foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t){if(!preg_match('/^[a-z_]+$/D',$t))exit(2);$db->exec('DROP TABLE `'.$t.'`');}$db->exec('SET FOREIGN_KEY_CHECKS=1');Database::migrate($db);$id=(new Auth($db))->saveAdmin(['username'=>'upgrade-admin','display_name'=>'Upgrade admin','password'=>'test1234','active'=>1],null);$a=new Articles($db);$article=$a->create($id);$a->save($article,['title'=>'Outlook help','category_id'=>1,'keywords'=>'outlook','html'=>'<p>Original help</p>'],1,$id,true);$db->exec('UPDATE articles SET view_count=12');")
    (app/'storage/media').mkdir(exist_ok=True);(app/'storage/media/preserve.txt').write_text('preserved upload')
    def client():return urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    admin=client();reader=client();base='http://127.0.0.1:8097/index.php'
    def request(who,route='home',data=None,**args):
        req=urllib.request.Request(base+'?'+urllib.parse.urlencode(dict(route=route,**args)),None if data is None else urllib.parse.urlencode(data).encode(),{'User-Agent':'Mozilla/5.0'})
        try:
            r=who.open(req,timeout=30);return r.status,r.read().decode()
        except urllib.error.HTTPError as e:return e.code,e.read().decode()
    def csrf(body):return re.search(r'name="csrf" value="([^"]+)"',body).group(1)
    log=open(tmp/'server.log','w');server=subprocess.Popen(['php','-S','127.0.0.1:8097','-t',str(app/'public')],cwd=app,env=env,stdout=log,stderr=log)
    try:
        for _ in range(40):
            try: status,body=request(admin,'login');break
            except OSError:time.sleep(.1)
        assert request(reader,'article',id=1)[0]==200
        assert php("echo $db->query('SELECT view_count FROM articles WHERE id=1')->fetchColumn();")=='13'
        status,body=request(admin,'login',{'csrf':csrf(body),'username':'upgrade-admin','password':'test1234'})
        assert status==200
        token=csrf(request(admin,'updates')[1])
        boundary='UsageReleaseUpgrade'
        payload=(f'--{boundary}\r\nContent-Disposition: form-data; name="csrf"\r\n\r\n{token}\r\n--{boundary}\r\nContent-Disposition: form-data; name="action"\r\n\r\nupload\r\n--{boundary}\r\nContent-Disposition: form-data; name="package"; filename="update.zip"\r\nContent-Type: application/zip\r\n\r\n').encode()+(root/f'dist/SimplePHPKB-v{version}-update.zip').read_bytes()+f'\r\n--{boundary}--\r\n'.encode()
        r=admin.open(urllib.request.Request(base+'?route=updates',payload,{'Content-Type':'multipart/form-data; boundary='+boundary}),timeout=30)
        assert 'Review update' in r.read().decode()
        assert request(admin,'updates',{'csrf':token,'action':'backup'})[0]==200
        r=admin.open(base+'?route=updates&download=backup')
        (tmp/'downloaded-backup.zip').write_bytes(r.read())
        with zipfile.ZipFile(tmp/'downloaded-backup.zip') as z:
            assert 'database.sql' in z.namelist()
            assert z.read('SimplePHPKB/config/config.php')==before
        status,body=request(admin,'updates',{'csrf':token,'action':'apply','backup_saved':'1'})
        assert status==200 and 'Apply database changes and finish' in body
        assert request(reader)[0]==503,'migration required before readers return'
        assert request(admin,'updates',{'csrf':token,'action':'migrate'})[0]==200
        assert config_path.read_bytes()==before
        assert (app/'storage/media/preserve.txt').read_text()=='preserved upload'
        assert php("echo KB_VERSION;")==version
        assert php("echo $db->query('SELECT MAX(version) FROM schema_migrations')->fetchColumn();")=='5'
        status,body=request(admin,'usage',period='all')
        assert status==200 and '<h1>Usage</h1>' in body
        assert php("echo (new Usage($db))->report('all')['article_views'];")=='13'
        assert php("echo (new Usage($db))->report('today')['article_views'];")=='0','historical totals not invented as daily data'
        assert request(reader,'article',id=1)[0]==200
        assert php("echo (new Usage($db))->report('today')['article_views'];")=='0','same pre-upgrade session is not counted twice'
        request(client(),'article',id=1)
        assert php("$r=(new Usage($db))->report('today');echo json_encode([$r['site_visits'],$r['article_views']]);")=='[2,1]'
        assert php("echo (new Usage($db))->report('all')['article_views'];")=='14'
        print('PASS actual 0.6.0 -> '+version+' browser ZIP upload, downloaded backup, file apply, migration, preserved config/media/history and first visitor counts')
    finally:
        server.terminate();server.wait(timeout=5);log.close()
