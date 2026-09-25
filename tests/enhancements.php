<?php
run_group('password_policy',function() use($db) {
    $auth=new Auth($db);
    if(session_status()!==PHP_SESSION_ACTIVE)session_start();
    $id=$auth->saveAdmin(['username'=>'eight-char','display_name'=>'Eight characters','password'=>'abcd1234','active'=>1],null);
    check($auth->login('eight-char','abcd1234','192.0.2.88'),'eight-character password accepted at creation and login');
    expect_error(fn()=>$auth->saveAdmin(['password'=>'1234567'],$id),'8');
    expect_error(fn()=>$auth->saveAdmin(['password'=>'éééé'],$id),'8');
    $auth->saveAdmin(['password'=>'newpass8'],$id);
    check($auth->current()===null,'eight-character reset still revokes old sessions');
    check($auth->login('eight-char','newpass8','192.0.2.89'),'eight-character reset accepted');
    $auth->logout();
});
run_group('branding',function() use($db) {
    global $config;
    check(class_exists('Branding'),'branding service exists');
    $config['site_name']='Existing configured title';
    $b=new Branding($db);$initial=$b->get();
    check($initial['title']==='Existing configured title','upgrade preserves configured title until admin changes it');
    check($initial['subtitle']==='Employee knowledge base','default subtitle preserved');
    $b->save(['title'=>'Nuvera KB','subtitle'=>'Help for everyone'],1);
    check($b->get()['title']==='Nuvera KB' && $b->get()['subtitle']==='Help for everyone','branding persisted');
    expect_error(fn()=>$b->save(['title'=>'Stale title','subtitle'=>''],1),'changed');
    check($b->get()['title']==='Nuvera KB','stale branding save cannot overwrite new settings');
    expect_error(fn()=>$b->save(['title'=>'','subtitle'=>''],2),'title');
    expect_error(fn()=>$b->save(['title'=>'Title','subtitle'=>str_repeat('x',201)],2),'subtitle');
    $b->save(['title'=>'日本語 <script>title</script>','subtitle'=>''],2);
    check($b->get()['subtitle']==='','subtitle can be cleared');
    check($b->logo()===null,'no logo route without an uploaded logo');
});
run_group('featured',function() use($db) {
    check(method_exists('Articles','setFeatured'),'article starring implemented');
    $uid=(new Auth($db))->saveAdmin(['username'=>'featurer','display_name'=>'Feature admin','password'=>'password','active'=>1],null);
    $a=new Articles($db);
    $make=function($name,$publish=true)use($a,$uid){$id=$a->create($uid);$a->save($id,['title'=>$name,'category_id'=>1,'keywords'=>'','html'=>'<p>Useful help</p>'],1,$uid,$publish);return $id;};
    $popular=$make('Popular feature fixture');$star=$make('Pinned feature fixture');$draft=$make('Private starred draft',false);
    $db->prepare('UPDATE articles SET view_count=1000 WHERE id=?')->execute([$popular]);
    check($a->listing(null,1,true)['items'][0]['id']===$popular,'popular article leads before featuring');
    $a->setFeatured($star,true,2);$a->setFeatured($draft,true,2);
    $home=$a->listing(null,1,true)['items'];
    check($home[0]['id']===$star && $home[1]['id']===$popular,'starred article comes ahead of more visited article');
    check(!in_array($draft,array_column($home,'id'),true),'starred draft remains private');
    check(count(array_unique(array_column($home,'id')))===count($home),'no featured/popular duplicates');
    expect_error(fn()=>$a->setFeatured($star,false,2),'changed');
    $a->setFeatured($star,false,3);
    check($a->listing(null,1,true)['items'][0]['id']===$popular,'unstar restores normal popularity order');
    $a->setFeatured($star,true,4);$a->unpublish($star,5);
    check(!in_array($star,array_column($a->listing(null,1,true)['items'],'id'),true),'unpublishing hides a starred article');
    $a->save($star,$a->get($star,true),6,$uid,true);$a->trash($star,7);
    check(!in_array($star,array_column($a->listing(null,1,true)['items'],'id'),true),'Trash hides a starred article');
    expect_error(fn()=>$a->setFeatured($star,true,8),'Trash');
    $a->restore($star,8);
    check(!in_array($star,array_column($a->listing(null,1,true)['items'],'id'),true),'restored starred article stays a private draft');
    for($i=0;$i<11;$i++)$make('Extra home fixture '.$i);
    check(count($a->listing(null,1,true)['items'])===10,'combined homepage list remains ten articles');
});
run_group('category_layout',function() use($db) {
    global $config;
    $b=new Branding($db);$settings=$b->get();
    check($settings['compact_home']===0,'existing installations keep regular rows until opted into compact mode');
    $b->save(['title'=>$settings['title'],'subtitle'=>$settings['subtitle'],'compact_home'=>1],$settings['lock_version']);
    $settings=$b->get();
    $b->save(['title'=>$settings['title'],'subtitle'=>'Branding-only change'],$settings['lock_version']);
    check($b->get()['compact_home']===1,'branding-only service save retains homepage preference');
    $c=new Categories($db);$id=$c->save('Category image tests',null);
    check($c->image($id)['mime']==='image/svg+xml','new category serves question-mark fallback');
    expect_error(fn()=>$c->save('Stale category name',$id,null,false,9),'changed');
    check(in_array('Category image tests',array_column($c->all(),'name'),true),'stale rename preserves category');
    expect_error(fn()=>$c->save('Forged upload',$id,['error'=>UPLOAD_ERR_OK,'tmp_name'=>__FILE__]),'upload');
    $db->prepare('UPDATE categories SET image_id=?,image_mime=? WHERE id=?')->execute([str_repeat('a',32),'image/png',$id]);
    check($c->image($id)['mime']==='image/svg+xml','missing stored image falls back cleanly');
    $db->exec('DELETE FROM schema_migrations WHERE version=3');Database::migrate($db);
    check($b->get()['compact_home']===1,'migration replay preserves saved homepage preference');
    $row=array_values(array_filter($c->all(false),fn($r)=>(int)$r['id']===$id))[0];
    check($row['image_id']===str_repeat('a',32),'migration replay preserves existing category image reference');
    $c->delete($id);expect_error(fn()=>$c->image($id),'not found');
});

run_group('site_notices',function() use($db) {
    $b=new Branding($db);$initial=$b->get();
    check(($initial['header_link_enabled'] ?? null)===0 && ($initial['banner_enabled'] ?? null)===0,'header link and banner default to disabled');
    $base=['title'=>'Nuvera KB','subtitle'=>'Employee help'];
    $save=function(array $changes) use($b,$base) { $b->save($changes+$base,$b->get()['lock_version']); };
    $options=['header_link_enabled'=>1,'header_link_text'=>'Submit a ticket','header_link_url'=>'https://tickets.example.test/new?source=kb&team=it','header_link_new_window'=>1,'banner_enabled'=>1,'banner_text'=>"Outlook is experiencing delays.\nIT is investigating."];
    $save($options);$stored=$b->get();
    foreach($options as $key=>$value)check($stored[$key]===$value,'saved option: '.$key);
    $save(['title'=>'Updated title']);$preserved=$b->get();
    foreach($options as $key=>$value)check($preserved[$key]===$value,'branding-only save preserves option: '.$key);
    expect_error(fn()=>$b->save(['banner_text'=>'Stale message']+$base,$stored['lock_version']),'changed');
    check($b->get()['banner_text']===$options['banner_text'],'stale settings cannot overwrite the banner');
    foreach(['javascript:alert(1)','data:text/html,test','ftp://tickets.example.test','//tickets.example.test','https://user:password@tickets.example.test',"https://tickets.example.test/\nnew",'https://tickets.example.test/\\bad'] as $url) {
        expect_error(fn()=>$save(['header_link_url'=>$url]),'URL');
    }
    foreach(['http://helpdesk/new','http://10.0.0.20:8080/tickets','https://tickets.example.test/#new'] as $url) {
        $save(['header_link_url'=>$url]);check($b->get()['header_link_url']===$url,'internal and HTTPS ticket URLs accepted');
    }
    expect_error(fn()=>$save(['header_link_text'=>'']),'link text');
    expect_error(fn()=>$save(['header_link_url'=>'']),'URL');
    expect_error(fn()=>$save(['banner_text'=>'   ']),'banner message');
    expect_error(fn()=>$save(['header_link_text'=>str_repeat('x',81)]),'80');
    expect_error(fn()=>$save(['banner_text'=>str_repeat('x',1001)]),'1000');
    expect_error(fn()=>$save(['header_link_url'=>'https://tickets.example.test/'.str_repeat('x',2048)]),'2048');
    expect_error(fn()=>$save(['header_link_text'=>"\xFF"]),'UTF-8');
    expect_error(fn()=>$save(['banner_text'=>['invalid']]),'Invalid');
    foreach(['header_link_enabled','header_link_new_window','banner_enabled'] as $field)expect_error(fn()=>$save([$field=>'unexpected']),'Invalid');
    $before=$b->get();
    expect_error(fn()=>$save(['title'=>'Should not save','banner_text'=>'']),'banner message');
    check($b->get()===$before,'invalid new settings do not partially save branding');
    $save(['header_link_enabled'=>0,'banner_enabled'=>0,'header_link_new_window'=>0]);
    $disabled=$b->get();
    check($disabled['header_link_enabled']===0 && $disabled['banner_enabled']===0,'both optional features can be disabled');
    check($disabled['header_link_text']===$options['header_link_text'] && $disabled['banner_text']===$options['banner_text'],'disabling preserves link and banner text for reuse');
    $db->exec('DELETE FROM schema_migrations WHERE version=4');Database::migrate($db);
    check($b->get()===$disabled,'migration replay preserves all saved settings');
});
