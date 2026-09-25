<?php
declare(strict_types=1);
final class Web {
    private Auth $auth;
    private Articles $articles;
    private Categories $categories;
    private ?array $admin;
    public function __construct(private PDO $db) {
        $this->auth=new Auth($db);$this->admin=$this->auth->current();
        $this->articles=new Articles($db);$this->categories=new Categories($db);
    }
    public function run(): void {
        $route='home';
        try {
            $route=input_string($_GET,'route','home');
            $method=$_SERVER['REQUEST_METHOD'];
            if(!in_array($method,['GET','POST','HEAD'],true))throw new UserError('Method not allowed.',405);
            $post=$method==='POST';
            if($post && !in_array($route,['login','logout','admin','edit','history','categories','admins','upload','branding'],true))throw new UserError('Method not allowed.',405);
            if(!in_array($route,['home','browse','article','media','logo','category-image','login'],true) && !$this->admin) {
                if($post)throw new UserError('Sign in to continue.',401);
                redirect_to('login');
            }
            if($post)verify_csrf($_POST['csrf'] ?? null);
            switch($route) {
                case 'home': case 'browse':
                    $q=trim(input_string($_GET,'q'));$cat=input_id($_GET,'category',true);$page=input_id($_GET,'page',true) ?? 1;
                    if(mb_strlen($q)>200)throw new UserError('Search is limited to 200 characters.');
                    $catName=null;
                    if($cat!==null) {foreach($this->categories->all(false) as $c)if((int)$c['id']===$cat)$catName=$c['name'];if(!$catName)throw new UserError('Category not found.',404);}
                    $isHome=$route==='home' && $q==='' && !$cat;
                    $results=$q!=='' ? (new Search($this->db))->query($q,$cat,$page) : $this->articles->listing($cat,$page,$isHome);
                    $title=$isHome ? 'Featured and popular articles' : ($q!=='' ? 'Search results' : ($catName ?? 'All articles'));
                    $this->render('browse',compact('q','cat','catName','results','isHome','title'));break;
                case 'article': case 'preview':
                    $id=input_id($_GET,'id');$preview=$route==='preview';$a=$this->articles->get($id,$preview);
                    if(!$a || ($preview && $a['trashed_at']))throw new UserError('Article not found.',404);
                    if(!$preview && $method==='GET')$this->articles->recordView($id,session_id(),$_SERVER['HTTP_USER_AGENT'] ?? '',$this->admin!==null);
                    $this->render('article',['a'=>$a,'title'=>$a['title'],'preview'=>$preview,'cat'=>$a['category_id']]);break;
                case 'media': case 'logo': case 'category-image':
                    $m=match($route){'logo'=>(new Branding($this->db))->logo(),'category-image'=>$this->categories->image(input_id($_GET,'id')),default=>(new Media($this->db))->find(input_string($_GET,'id'),$this->admin!==null)};
                    if(!$m)throw new UserError('Image not found.',404);
                    header('Content-Type: '.$m['mime']);header('Content-Length: '.filesize($m['path']));
                    if($method!=='HEAD')readfile($m['path']);break;
                case 'login':
                    if($this->admin)redirect_to('admin');
                    $error=null;
                    if($post) {
                        if($this->auth->login(trim(input_string($_POST,'username')),input_string($_POST,'password'),$_SERVER['REMOTE_ADDR'] ?? 'unknown'))redirect_to('admin');
                        $error='Unable to sign in. Check your details, or wait 15 minutes if you have tried several times.';http_response_code(401);
                    }
                    $this->render('login',['title'=>'Admin sign in','error'=>$error]);break;
                case 'logout':
                    if(!$post)throw new UserError('Use the Sign out button.',405);
                    $this->auth->logout();redirect_to('home');
                case 'admin':
                    if($post) {
                        $action=input_string($_POST,'action');
                        if($action==='new')redirect_to('edit',['id'=>$this->articles->create($this->admin['id'])]);
                        $id=input_id($_POST,'id');$version=input_id($_POST,'version');
                        if($action==='feature') {
                            $value=input_string($_POST,'featured');
                            if(!in_array($value,['0','1'],true))throw new UserError('Invalid featured setting.',400);
                            $status=input_string($_POST,'status','all');
                            if(!in_array($status,['all','published','draft'],true))$status='all';
                            $q=input_string($_POST,'q');if(mb_strlen($q)>200)throw new UserError('Search is limited to 200 characters.');
                            $this->articles->setFeatured($id,$value==='1',$version);
                            flash($value==='1' ? 'Article starred. It appears ahead of popular articles when published.' : 'Article is no longer featured.');
                            redirect_to('admin',['status'=>$status,'q'=>$q]);
                        }
                        match($action){'trash'=>$this->articles->trash($id,$version),'restore'=>$this->articles->restore($id,$version),'unpublish'=>$this->articles->unpublish($id,$version),default=>throw new UserError('Unknown action.',400)};
                        flash(match($action){'restore'=>'Article restored as a draft.','unpublish'=>'Article unpublished.','trash'=>'Article moved to Trash.'});redirect_to('admin',['status'=>$action==='restore' ? 'draft' : 'all']);
                    }
                    $status=input_string($_GET,'status','all');if(!in_array($status,['all','published','draft','trash'],true))$status='all';
                    $q=trim(input_string($_GET,'q'));if(mb_strlen($q)>200)throw new UserError('Search is limited to 200 characters.');
                    $this->render('admin',['title'=>'Manage articles','status'=>$status,'q'=>$q,'results'=>$this->articles->adminList($status,$q,input_id($_GET,'page',true) ?? 1)]);break;
                case 'edit':$this->edit($post);break;
                case 'branding':$this->branding($post);break;
                case 'history':
                    $id=input_id($_GET,'id');$a=$this->articles->get($id,true);if(!$a)throw new UserError('Article not found.',404);
                    if($post){$this->articles->restoreRevision($id,input_id($_POST,'revision'),input_id($_POST,'version'),$this->admin['id']);flash('Revision restored to the draft. Review it before publishing.');redirect_to('edit',['id'=>$id]);}
                    $this->render('history',['title'=>'Revision history','a'=>$a,'history'=>$this->articles->history($id)]);break;
                case 'categories':
                    $error=null;
                    if($post)try{
                        $action=input_string($_POST,'action');$id=input_id($_POST,'id',true);
                        if($action==='delete'){$this->categories->delete($id ?? 0);flash('Category deleted.');}
                        elseif($action==='save'){
                            $file=$_FILES['image'] ?? null;if($file!==null && !is_array($file))throw new UserError('Invalid category image.',400);
                            $remove=input_string($_POST,'remove_image');if(!in_array($remove,['','1'],true))throw new UserError('Invalid image removal setting.',400);
                            $this->categories->save(input_string($_POST,'name'),$id,$file,$remove==='1',$id ? input_id($_POST,'version') : null);flash('Category saved.');
                        }
                        else throw new UserError('Unknown action.',400);
                        redirect_to('categories');
                    }catch(UserError $e){$error=$e->getMessage();http_response_code($e->status);}
                    $this->render('categories',['title'=>'Categories','error'=>$error]);break;
                case 'admins':
                    $error=null;
                    if($post)try{
                        $id=input_id($_POST,'id',true);
                        $this->auth->saveAdmin(['username'=>input_string($_POST,'username'),'display_name'=>input_string($_POST,'display_name'),'password'=>input_string($_POST,'password'),'active'=>input_string($_POST,'active')==='1' ? 1 : 0],$id);
                        flash('Admin account saved. Password changes require signing in again.');redirect_to('admins');
                    }catch(UserError $e){$error=$e->getMessage();http_response_code($e->status);}
                    $this->render('admins',['title'=>'Admin accounts','error'=>$error,'admins'=>$this->auth->all()]);break;
                case 'upload':
                    if(!$post)throw new UserError('Method not allowed.',405);
                    $id=input_id($_GET,'id');$file=$_FILES['file'] ?? [];
                    if(!is_array($file))throw new UserError('Invalid upload.',400);
                    $result=(new Media($this->db))->upload($id,$file,$this->admin['id']);
                    header('Content-Type: application/json');echo json_encode($result,JSON_THROW_ON_ERROR);break;
                default:throw new UserError('Page not found.',404);
            }
        }catch(UserError $e){
            http_response_code($e->status);
            if($route==='upload'){header('Content-Type: application/json');echo json_encode(['error'=>$e->getMessage()]);}
            else $this->render('error',['title'=>$e->status===404 ? 'Not found' : 'Unable to continue','error'=>$e->getMessage()]);
        }
    }
    private function edit(bool $post): void {
        $id=input_id($_GET,'id');$a=$this->articles->get($id,true);
        if(!$a || $a['trashed_at'])throw new UserError('Article not found. Check Trash if it was removed.',404);
        $error=null;
        if($post){
            $data=['title'=>input_string($_POST,'title'),'category_id'=>input_id($_POST,'category_id',true),'keywords'=>input_string($_POST,'keywords'),'html'=>input_string($_POST,'html')];
            $version=input_id($_POST,'version');$action=input_string($_POST,'action');
            if(!in_array($action,['draft','publish','preview'],true))throw new UserError('Unknown save action.',400);
            try {
                $this->articles->save($id,$data,$version,$this->admin['id'],$action==='publish');
                flash($action==='publish' ? 'Article published. Supported formatting was checked and saved.' : 'Draft saved. Supported formatting was checked and saved.');
                redirect_to($action==='preview' ? 'preview' : 'edit',['id'=>$id]);
            }catch(Throwable $e){
                // Never drop a user's submitted text after validation, storage, or concurrency errors.
                $a=array_merge($a,$data,['lock_version'=>$version]);
                if($e instanceof UserError){$error=$e->getMessage();http_response_code($e->status);}
                else{error_log('SimplePHPKB save: '.$e);$error='The article could not be saved. Your text is preserved below. Try again shortly.';http_response_code(500);}
            }
        }
        $this->render('edit',['title'=>$a['title'] ?: 'New article','a'=>$a,'error'=>$error]);
    }
    private function branding(bool $post): void {
        $service=new Branding($this->db);$settings=$service->get();$error=null;
        if($post) {
            $data=['title'=>input_string($_POST,'title'),'subtitle'=>input_string($_POST,'subtitle'),'compact_home'=>input_string($_POST,'compact_home','0')];
            foreach(['header_link_enabled','header_link_new_window','banner_enabled'] as $key)$data[$key]=input_string($_POST,$key,'0');
            foreach(['header_link_text','header_link_url','banner_text'] as $key)$data[$key]=input_string($_POST,$key);
            $version=input_id($_POST,'version');$remove=input_string($_POST,'remove_logo')==='1';
            $file=$_FILES['logo'] ?? null;
            if($file!==null && !is_array($file))throw new UserError('Invalid logo upload.',400);
            try {
                $service->save($data,$version,$file,$remove);
                flash('Settings updated.');redirect_to('branding');
            } catch(Throwable $e) {
                $settings=array_merge($settings,$data,['lock_version'=>$version]);
                if($e instanceof UserError){$error=$e->getMessage();http_response_code($e->status);}
                else{error_log('SimplePHPKB settings: '.$e);$error='Settings could not be saved. Your text is preserved below. Try again shortly.';http_response_code(500);}
            }
        }
        $this->render('branding',['title'=>'Settings','settings'=>$settings,'error'=>$error]);
    }
    private function render(string $template,array $data=[]): void {
        global $config;
        $admin=$this->admin;$categories=$this->categories->all();$title=$data['title'] ?? 'SimplePHPKB';$branding=(new Branding($this->db))->get();
        $q=$data['q'] ?? '';$cat=$data['cat'] ?? null;extract($data,EXTR_SKIP);
        $release=$admin?(new Releases($config['storage']))->check():null;
        ob_start();require __DIR__.'/../templates/'.$template.'.php';$content=ob_get_clean();
        require __DIR__.'/../templates/layout.php';
    }
}
