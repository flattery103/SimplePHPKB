"""Exercise an actual previous full ZIP -> current update in a disposable database."""
import pathlib,zipfile,tempfile,subprocess,os,json,re,hashlib
ROOT=pathlib.Path(__file__).resolve().parent.parent
DSN=os.environ.get('KB_INSTALL_TEST_DSN','')
assert re.search(r'dbname=[A-Za-z0-9_]+_install_test(?:;|$)',DSN),'set KB_INSTALL_TEST_DSN to a disposable _install_test database'
BASELINE=pathlib.Path(os.environ.get('KB_BASELINE_ZIP',str(ROOT/'dist/SimplePHPKB-v0.2.0.zip')))
VERSION=re.search(r"KB_VERSION = '([^']+)'",(ROOT/'src/init.php').read_text()).group(1)
with tempfile.TemporaryDirectory(prefix='simplephpkb-upgrade-') as temp:
    temp=pathlib.Path(temp)
    with zipfile.ZipFile(BASELINE) as z:z.extractall(temp)
    app=temp/'SimplePHPKB'
    config={'site_name':'Existing site title','base_path':'/kb','secure_cookies':False,'timezone':'America/Chicago','storage':str(app/'storage'),'db':{'dsn':DSN,'username':os.environ.get('KB_TEST_USER','root'),'password':os.environ.get('KB_TEST_PASSWORD','')}}
    config_file=app/'config/config.php'
    config_file.write_text("<?php return json_decode(<<<'KBJSON'\n"+json.dumps(config)+"\nKBJSON, true);\n")
    config_before=config_file.read_bytes()
    env=os.environ.copy();env['KB_CONFIG']=str(config_file)
    def php(code):
        run=subprocess.run(['php','-r',code],cwd=app,env=env,capture_output=True,text=True)
        assert run.returncode==0,run.stdout+run.stderr
        return run.stdout
    def cli(*args,input=None):
        result=subprocess.run(['php','bin/kb.php',*args],input=input,cwd=app,env=env,capture_output=True,text=True)
        assert result.returncode==0,result.stdout+result.stderr
    php("require 'src/bootstrap.php';$db->exec('SET FOREIGN_KEY_CHECKS=0');foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t){if(!preg_match('/^[a-z_]+$/D',$t))exit(2);$db->exec('DROP TABLE `'.$t.'`');}$db->exec('SET FOREIGN_KEY_CHECKS=1');")
    cli('migrate');cli('admin:create','existing-admin',input='Existing-Installation-Password-87\n')
    fixture=json.loads(php("""require 'src/bootstrap.php';$a=new Articles($db);$id=$a->create(1);$img=imagecreatetruecolor(20,20);$path=tempnam(sys_get_temp_dir(),'upgrade-image');imagepng($img,$path);$m=(new Media($db))->store($id,$path,1);unlink($path);$a->save($id,['title'=>'Existing published password help','category_id'=>1,'keywords'=>'account','html'=>'<p>Published text</p><img src="'.e($m['location']).'">'],1,1,true);$d=$a->get($id,true);$d['html']='<p>Private working draft</p>';$a->save($id,$d,2,1,false);$db->exec('UPDATE articles SET view_count=36');echo json_encode(['id'=>$id,'media'=>$m['id']]);"""))
    image_file=app/'storage/media'/fixture['media'];image_before=hashlib.sha256(image_file.read_bytes()).hexdigest()
    previous_branding=None
    if (app/'src/Branding.php').exists():
        previous_branding=json.loads(php("""require 'src/bootstrap.php';$b=new Branding($db);$b->save(['title'=>'Existing branded title','subtitle'=>'Existing branded subtitle','compact_home'=>1],1);$m=Media::writeImage($config['storage'].'/media/"""+fixture['media']+"""');$db->prepare('UPDATE site_settings SET logo_id=?,logo_mime=? WHERE id=1')->execute([$m['id'],$m['mime']]);(new Articles($db))->setFeatured(1,true,3);echo json_encode($b->get());"""))
        logo_file=app/'storage/media'/previous_branding['logo_id'];logo_before=hashlib.sha256(logo_file.read_bytes()).hexdigest()
    with zipfile.ZipFile(ROOT/f'dist/SimplePHPKB-v{VERSION}-update.zip') as z:z.extractall(temp)
    assert config_file.read_bytes()==config_before,'existing configuration unchanged by update archive'
    assert hashlib.sha256(image_file.read_bytes()).hexdigest()==image_before,'existing image unchanged'
    cli('migrate');cli('migrate')
    after_settings=json.loads(php("require 'src/bootstrap.php';echo json_encode((new Branding($db))->get());"))
    assert after_settings['compact_home']==(previous_branding or {}).get('compact_home',0),'upgrade preserves the saved homepage layout'
    assert after_settings['header_link_enabled']==0 and after_settings['banner_enabled']==0,'new optional features default to off after upgrade'
    if previous_branding:
        assert all(after_settings[key]==value for key,value in previous_branding.items()),'existing branding and logo reference survive migration'
        assert hashlib.sha256(logo_file.read_bytes()).hexdigest()==logo_before,'existing logo bytes preserved'
        assert php("require 'src/bootstrap.php';echo (new Articles($db))->get(1,true)['featured'];")=='1','existing featured state preserved'
    else:
        assert after_settings['title']=='Existing site title','configuration title remains the default'
    result=php("""require 'src/bootstrap.php';$a=new Articles($db);$p=$a->get(1);if(!$p||!str_contains($p['html'],'Published text')||str_contains($p['html'],'Private working draft')||(int)$p['view_count']!==36)exit(3);if(!str_contains($a->get(1,true)['html'],'Private working draft'))exit(4);if((new Search($db))->query('pasword',null)['total']!==1)exit(5);if(!(new Media($db))->find('"""+fixture['media']+"""',false))exit(6);$b=new Branding($db);$b->save(['title'=>'Updated brand','subtitle'=>'Updated subtitle','compact_home'=>1,'header_link_enabled'=>1,'header_link_text'=>'Open a ticket','header_link_url'=>'https://tickets.example.test/','header_link_new_window'=>1,'banner_enabled'=>1,'banner_text'=>'Known issue test'], $b->get()['lock_version']);$a->setFeatured(1,true,$a->get(1,true)['lock_version']);$m=Media::writeImage($config['storage'].'/media/"""+fixture['media']+"""');$db->prepare('UPDATE categories SET image_id=?,image_mime=? WHERE id=1')->execute([$m['id'],$m['mime']]);$db->exec('DELETE FROM schema_migrations WHERE version IN (2,3,4)');Database::migrate($db);if($b->get()['title']!=='Updated brand'||!$a->get(1,true)['featured']||$b->get()['compact_home']!==1)exit(8);if((new Categories($db))->image(1)['path']!==$m['path'])exit(10);$n=$b->get();if($n['header_link_enabled']!==1||$n['header_link_text']!=='Open a ticket'||$n['header_link_url']!=='https://tickets.example.test/'||$n['header_link_new_window']!==1||$n['banner_enabled']!==1||$n['banner_text']!=='Known issue test')exit(11);echo 'preserved';""")
    assert result=='preserved'
    cli('admin:reset','existing-admin',input='eight123\n')
    assert php("require 'src/bootstrap.php';if(!password_verify('eight123',$db->query('SELECT password_hash FROM admins WHERE id=1')->fetchColumn()))exit(9);echo 'reset';")=='reset'
print('PASS upgrade: '+BASELINE.name+' -> update ZIP; configuration, images, articles, drafts, views, search and prior branding/stars preserved; category images, compact mode, header link/banner and migration retry work')
