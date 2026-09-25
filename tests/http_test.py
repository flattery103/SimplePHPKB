import pathlib,urllib.request,urllib.parse,urllib.error,http.cookiejar,subprocess,time,os,re,json
root=pathlib.Path(__file__).resolve().parent.parent
os.environ['KB_CONFIG']=str(root/'tests/artifacts/config.php')
subprocess.run(['php',str(root/'tests/seed.php')],check=True)
log=open(root/'tests/artifacts/http-server.log','w')
prefix=os.environ.get('KB_TEST_BASE_PATH','')
webroot=root/'public'
if prefix:
    webroot=root/'tests/artifacts/subdirectory';webroot.mkdir(parents=True,exist_ok=True)
    link=webroot/prefix.strip('/')
    if not link.exists():link.symlink_to(root/'public',target_is_directory=True)
server=subprocess.Popen(['php','-S','127.0.0.1:8090','-t',str(webroot)],stdout=log,stderr=log)
base='http://127.0.0.1:8090'+prefix+'/index.php'
jar=http.cookiejar.CookieJar(); client=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(jar))
def request(route='',data=None,**args):
    url=base+'?'+urllib.parse.urlencode(dict(route=route,**args))
    payload=None if data is None else urllib.parse.urlencode(data).encode()
    try:
        r=client.open(urllib.request.Request(url,payload),timeout=10)
        return r.status,r.read().decode(errors='replace'),r.headers
    except urllib.error.HTTPError as e:return e.code,e.read().decode(),e.headers
try:
    for i in range(40):
        try: status,body,h=request('home');break
        except urllib.error.URLError:time.sleep(.1)
    assert status==200 and 'Reset your account password' in body,'reader homepage'
    assert 'noindex' in h.get('X-Robots-Tag',''),'no-index defaults'
    assert request('edit',id=1)[0] in (200,401) and 'Sign in' in request('edit',id=1)[1],'anonymous admin redirect'
    assert request('preview',id=1)[0] in (200,401),'preview auth redirect'
    assert request('edit',{'action':'publish'},id=1)[0] in (401,403),'anonymous mutation denied'
    assert request('browse',q='pasword')[0]==200 and 'Reset your account password' in request('browse',q='pasword')[1],'fuzzy HTTP search'
    status,body,_=request('browse',q="My wireless won’t connect")
    assert status==200 and 'Connect to the staff Wi-Fi' in body,'natural-language search over HTTP'
    status,body,_=request('browse',q='Please help me with my password qzxvunknown')
    assert status==200 and 'Reset your account password' in body,'unmatched wording does not hide relevant HTTP results'
    assert request('article',id='1 OR 1=1')[0]==400,'bad id'
    assert request('article',**{'id[]':'1'})[0]==400,'array id'
    assert request('browse',**{'q[]':'x'})[0]==400,'array query'
    status,body,_=request('login');token=re.search(r'name="csrf" value="([^"]+)"',body).group(1)
    assert request('login',{'username':'admin','password':'Only-Test-Browser-Password-74','csrf':'wrong'})[0]==403,'invalid csrf'
    status,body,_=request('login',{'username':'admin','password':'Only-Test-Browser-Password-74','csrf':token})
    assert status==200 and 'Manage articles' in body,'login'
    token=re.search(r'name="csrf" value="([^"]+)"',body).group(1)
    status,body,_=request('admin',{'csrf':token,'action':'new'})
    assert status==200 and 'New article' in body,'new article'
    assert 'data-unsaved="false"' in body,'new article has no unsaved recovery text'
    new_id=re.search(r'name="article_id" value="(\d+)"',body).group(1)
    token=re.search(r'name="csrf" value="([^"]+)"',body).group(1)
    data={'csrf':token,'version':'1','action':'draft','title':'Private draft phrase','category_id':'1','keywords':'secret','html':'<p>Private body</p>'}
    status,body,_=request('edit',data,id=new_id)
    assert status==200 and 'Draft saved' in body,'save draft'
    assert 'data-unsaved="false"' in body,'successful save clears unsaved recovery marker'
    assert request('article',id=new_id)[0]==404,'draft not on reader route even for admin'
    assert 'Private body' in request('preview',id=new_id)[1],'admin draft preview'
    data['version']='2';data['action']='publish';status,body,_=request('edit',data,id=new_id)
    assert status==200 and 'Article published' in body,'publish'
    assert request('article',id=new_id)[0]==200,'published visible'
    data['title']='Stale text';status,body,_=request('edit',data,id=new_id)
    assert status==409 and 'Stale text' in body and 'value="2"' in body,'stale save retains text and stale token'
    assert 'data-unsaved="true"' in body,'failed save marks preserved text as unsaved'
    assert 'Private draft phrase' in request('article',id=new_id)[1],'conflict preserves publication'
    # Multipart upload passes through the real PHP upload boundary.
    boundary='SimplePHPKBTestBoundary'
    image_bytes=(root/'tests/artifacts/screenshot.png').read_bytes()
    def upload(image,filename='screen.png'):
        payload=(f'--{boundary}\r\nContent-Disposition: form-data; name="csrf"\r\n\r\n{token}\r\n--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{filename}"\r\nContent-Type: image/png\r\n\r\n').encode()+image+f'\r\n--{boundary}--\r\n'.encode()
        req=urllib.request.Request(base+'?route=upload&id='+new_id,payload,{'Content-Type':'multipart/form-data; boundary='+boundary})
        try:
            response=client.open(req);return response.status,json.loads(response.read())
        except urllib.error.HTTPError as e:return e.code,json.loads(e.read())
    assert upload(b'<?php echo "not an image"; ?>')[0]==422,'forged image rejected'
    status,result=upload(image_bytes);assert status==200,'image upload'
    media_id=result['id'];assert request('media',id=media_id)[0]==200,'admin reads own image'
    anonymous=urllib.request.build_opener(urllib.request.ProxyHandler({}))
    def anon_status(url):
        try:return anonymous.open(url).status
        except urllib.error.HTTPError as e:return e.code
    image_url='http://127.0.0.1:8090'+result['location']
    assert anon_status(image_url)==404,'draft image on published article is private'
    data.update(version='3',html='<p>Published image</p><img src="'+result['location']+'" alt="Screenshot">',title='Illustrated instructions')
    assert request('edit',data,id=new_id)[0]==200,'publish uploaded image'
    assert anon_status(image_url)==200,'published image readable anonymously'
    assert request('admin',{'csrf':token,'action':'unpublish','id':new_id,'version':'4'})[0]==200,'unpublish'
    assert anon_status(image_url)==404,'unpublish hides image'
    assert request('admin',{'csrf':token,'action':'trash','id':new_id,'version':'5'})[0]==200,'trash'
    assert request('admin',{'csrf':token,'action':'restore','id':new_id,'version':'6'})[0]==200,'restore from trash'
    assert anon_status(base+'?route=article&id='+new_id)==404,'restored article remains draft'
    assert request('categories',{'csrf':token,'action':'save','name':'HTTP category'})[0]==200,'create category'
    status,body,_=request('admins',{'csrf':token,'username':'http-admin','display_name':'HTTP Admin','password':'pass1234','active':'1'})
    assert status==200 and 'HTTP Admin' in body,'create admin'
    assert 'minlength="8"' in body and 'minlength="12"' not in body,'eight-character password help and validation'
    assert request('branding')[0]==200,'admin branding page'
    assert request('branding',{'csrf':'bad','version':'1','title':'Rejected','subtitle':''})[0]==403,'branding CSRF protection'
    assert request('branding',{'csrf':token,'version':'1','title[]':'Rejected','subtitle':''})[0]==400,'branding rejects array inputs'
    status,body,_=request('branding',{'csrf':token,'version':'1','title':'Nuvera Knowledge Base','subtitle':'Answers for employees'})
    assert status==200 and 'Nuvera Knowledge Base' in body and 'Answers for employees' in body,'branding text saved and rendered'
    assert request('branding',{'csrf':token,'version':'1','title':'Stale branding','subtitle':''})[0]==409,'branding conflict rejected'
    assert 'Nuvera Knowledge Base' in request('home')[1],'branding conflict did not replace settings'
    def brand_upload(image,version,title='Nuvera <script>alert(1)</script>'):
        fields={'csrf':token,'version':str(version),'title':title,'subtitle':'Local employee help'}
        payload=b''.join((f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n').encode() for key,value in fields.items())
        payload+=(f'--{boundary}\r\nContent-Disposition: form-data; name="logo"; filename="logo.png"\r\nContent-Type: image/png\r\n\r\n').encode()+image+f'\r\n--{boundary}--\r\n'.encode()
        req=urllib.request.Request(base+'?route=branding',payload,{'Content-Type':'multipart/form-data; boundary='+boundary})
        try:
            response=client.open(req);return response.status,response.read().decode()
        except urllib.error.HTTPError as e:return e.code,e.read().decode()
    status,body=brand_upload(image_bytes,2)
    assert status==200 and 'class="brand-logo"' in body,'logo upload saved and rendered'
    assert 'Nuvera &lt;script&gt;alert(1)&lt;/script&gt;' in body and '<script>alert(1)</script>' not in body,'branding is escaped as text'
    logo_url=base+'?route=logo'
    response=anonymous.open(logo_url)
    first_logo=response.read()
    assert response.headers['Content-Type']=='image/png' and first_logo.startswith(b'\x89PNG'),'anonymous logo image route'
    assert brand_upload(b'<svg onload="alert(1)"></svg>',3)[0]==422,'forged logo rejected'
    assert brand_upload(image_bytes+b'x'*(2*1024*1024),3)[0]==422,'oversized logo rejected'
    assert anon_status(logo_url)==200,'rejected upload preserves current logo'
    replacement=(root/'tests/artifacts/replacement.png').read_bytes()
    assert brand_upload(replacement,3)[0]==200,'valid replacement logo saved'
    assert anonymous.open(logo_url).read()!=first_logo,'logo route switches to the replacement image'
    status,body,_=request('branding',{'csrf':token,'version':'4','title':'Nuvera KB','subtitle':'','remove_logo':'1'})
    assert status==200 and anon_status(logo_url)==404,'logo removed'
    assert 'class="brand-logo"' not in request('home')[1],'removed logo no longer in shared layout'
    status,body,_=request('admin',{'csrf':token,'action':'feature','id':'2','version':'2','featured':'1','status':'published'})
    assert status==200 and 'Featured' in body,'star an article'
    home=request('home')[1]
    assert home.count('class="article-list"')==1 and 'Featured and popular articles' in home,'one combined homepage box'
    assert home.index('route=article&amp;id=2')<home.index('route=article&amp;id=1'),'featured article first in HTTP homepage'
    from category_layout_http import check_category_layout
    check_category_layout(request,base,client,anonymous,token,image_bytes,replacement)
    from site_notices_http import check_site_notices
    check_site_notices(request,base,anonymous,token)
    assert request('admin',{'csrf':token,'action':'feature','id':'2','version':'2','featured':'0'})[0]==409,'stale feature action rejected'
    assert request('admin',{'csrf':token,'action':'feature','id':'2','version':'3','featured':'0'})[0]==200,'unstar an article'
    assert request('admin',{'csrf':token,'action':'feature','id':'2','version':'4','featured[]':'1'})[0]==400,'feature rejects array input'
    request('logout',{'csrf':token})
    assert request('branding',{'csrf':token,'version':'4','title':'Denied','subtitle':''})[0] in (401,403),'anonymous branding mutation denied'
    assert request('admin',{'csrf':token,'action':'feature','id':'2','version':'4','featured':'1'})[0] in (401,403),'anonymous starring denied'
    status,body,_=request('login');token=re.search(r'name="csrf" value="([^"]+)"',body).group(1)
    assert request('login',{'csrf':token,'username':'http-admin','password':'pass1234'})[0]==200,'eight-character password signs in over HTTP'
    print('PASS HTTP enhancements: branding/escaping/conflicts, logo upload/privacy/limits/removal, featured ordering/authorization, eight-character login')
    print('PASS HTTP: reading/search, login/CSRF, invalid inputs, draft/preview/publish, edit conflict, real image upload/privacy, unpublish/trash/restore, categories/admins')
finally:
    server.terminate();server.wait(timeout=5);log.close()
