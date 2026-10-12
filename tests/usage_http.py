"""Usage route authorization, real session counting, and read failures against a disposable DB."""
import pathlib, urllib.request, urllib.parse, urllib.error, http.cookiejar, subprocess, time, os, re, json

root=pathlib.Path(__file__).resolve().parent.parent
os.environ['KB_CONFIG']=str(root/'tests/artifacts/config.php')
subprocess.run(['php',str(root/'tests/seed.php')],check=True)
prefix=os.environ.get('KB_TEST_BASE_PATH','')
webroot=root/'public'
if prefix:
    webroot=root/'tests/artifacts/usage-subdirectory';webroot.mkdir(parents=True,exist_ok=True)
    link=webroot/prefix.strip('/')
    if not link.exists():link.symlink_to(root/'public',target_is_directory=True)
base='http://127.0.0.1:8096'+prefix+'/index.php'
log=open(root/'tests/artifacts/usage-http.log','w')
server=subprocess.Popen(['php','-S','127.0.0.1:8096','-t',str(webroot)],stdout=log,stderr=log)
def client():
    return urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(who,route='home',data=None,method=None,agent='Mozilla/5.0',**args):
    req=urllib.request.Request(base+'?'+urllib.parse.urlencode(dict(route=route,**args)),None if data is None else urllib.parse.urlencode(data).encode(),{'User-Agent':agent},method=method)
    try:
        r=who.open(req,timeout=10);return r.status,r.read().decode(errors='replace'),r.geturl()
    except urllib.error.HTTPError as e:return e.code,e.read().decode(errors='replace'),e.geturl()
def php(code):
    return subprocess.check_output(['php','-r',"require 'src/bootstrap.php';"+code],cwd=root,text=True)
def counts():
    return json.loads(php("$r=(new Usage($db))->report('today');echo json_encode([$r['site_visits'],$r['article_views']]);"))
try:
    admin=client()
    for _ in range(40):
        try: status,body,_=request(admin,'login');break
        except urllib.error.URLError:time.sleep(.1)
    assert status==200
    token=re.search(r'name="csrf" value="([^"]+)"',body).group(1)
    status,body,_=request(admin,'login',{'csrf':token,'username':'admin','password':'Only-Test-Browser-Password-74'})
    assert status==200 and 'route=usage' in body,'admin navigation links to Usage'
    assert '<th>All-time views</th>' in body,'manage articles exposes historical view counts'
    status,body,_=request(admin,'usage')
    assert status==200 and '<h1>Usage</h1>' in body and 'Site visits' in body,'admin usage page'
    assert 'UTC' in body and 'browser sessions' in body,'honest counting definitions shown'
    status,body,url=request(client(),'usage')
    assert 'route=login' in url and '<h1>Usage</h1>' not in body,'anonymous report access redirects to sign in'
    assert request(admin,'usage',period='nonsense')[0]==400
    assert request(admin,'usage',**{'period[]':'30'})[0]==400
    assert request(admin,'usage',search='x'*201)[0]==422
    assert request(admin,'usage',data={})[0]==405,'report is read-only'
    assert counts()==[0,0],'admin and login pages do not count'

    reader=client()
    status,body,_=request(reader)
    assert status==200 and 'route=usage' not in body,'reader navigation hides usage'
    assert counts()==[1,0]
    request(reader);request(reader,'browse',q='password')
    request(reader,'article',id=1);request(reader,'article',id=1);request(reader,'article',id=2)
    assert counts()==[1,2],'home, search and repeated article reads share one site visit'
    request(client(),'article',id=1)
    assert counts()==[2,3],'direct article entry counts a site visit and an article view'
    for route,args in [('home',{}),('browse',{}),('article',{'id':1})]:
        request(admin,route,**args)
        request(client(),route,agent='Googlebot',**args)
        request(client(),route,method='HEAD',**args)
    for route,args in [('article',{'id':999999}),('category-image',{'id':1}),('logo',{}),('browse',{'category':999999}),('preview',{'id':1}),('unknown',{})]:
        request(client(),route,**args)
    assert counts()==[2,3],'admins, bots, HEAD, errors, assets and previews do not count'
    status,body,_=request(admin,'usage',period='today',search='Reset your account password')
    assert status==200 and 'Reset your account password' in body and 'Connect to the staff Wi-Fi' not in body,'report title search works'
    assert re.search(r'class="usage-number">2</td>',body),'per-article count displayed'
    assert re.search(r'class="usage-number">122</td>',body),'historical total plus new reads displayed'
    assert request(admin,'usage',period='all')[0]==200

    # Usage write failure must not make a published article unreadable.
    php("$db->exec('RENAME TABLE article_daily TO article_daily_unavailable');")
    try:
        status,body,_=request(client(),'article',id=1)
        assert status==200 and 'Reset your account password' in body,'tracking failure leaves reader access working'
        assert php("echo $db->query('SELECT view_count FROM articles WHERE id=1')->fetchColumn();")=='122','failed tracking rolls back lifetime count'
    finally:php("$db->exec('RENAME TABLE article_daily_unavailable TO article_daily');")
    assert counts()==[3,3],'failed article transaction does not inflate daily views'
    print('PASS usage HTTP: admin-only reporting, per-session counting, direct-entry visits, exclusions, search, legacy totals and fail-open reads at '+(prefix or '/'))
finally:
    server.terminate();server.wait(timeout=5);log.close()
