<?php
run_group('search',function() use($db) {
    check(method_exists('Search','query'),'search query implemented');
    $uid=(new Auth($db))->saveAdmin(['username'=>'searcher','display_name'=>'Search writer','password'=>'Only-Testing-Search-674','active'=>1],null);
    $a=new Articles($db);$s=new Search($db);$c=new Categories($db);$other=$c->save('Network',null);
    $make=function($title,$body,$keywords='',$cat=1)use($a,$uid) {$id=$a->create($uid);$a->save($id,['title'=>$title,'html'=>'<p>'.$body.'</p>','keywords'=>$keywords,'category_id'=>$cat],1,$uid,true);return $id;};
    $password=$make('Password reset','Use your account to sign in.','passwords');
    $body=$make('Account help','These password instructions are basic.');
    $wifi=$make('Connect to Wi-Fi','Join the staff network.','wireless internet',$other);
    $unicode=$make('Café 日本語','International setup.');
    check($s->query('password',null)['items'][0]['id']===$password,'exact title before body');
    check($s->query('pasword',null)['items'][0]['id']===$password,'fuzzy missing letter');
    check($s->query('xassword',null)['items'][0]['id']===$password,'fuzzy first letter');
    check($s->query('passw',null)['items'][0]['id']===$password,'prefix');
    check($s->query('wireless',null)['items'][0]['id']===$wifi,'keyword alias');
    check($s->query('wireless',1)['total']===0,'category restriction');
    check($s->query('日本語',null)['items'][0]['id']===$unicode,'unicode exact');
    check($s->query('cafe',null)['items'][0]['id']===$unicode,'unicode fuzzy');
    check($s->query("%' OR 1=1 --",null)['total']===0,'punctuation and SQL input safe');
    check($s->query('zzq',null)['total']===0,'short queries not fuzzy');
    $draft=$a->get($password,true);$draft['title']='Classified unpublished phrase';
    $a->save($password,$draft,2,$uid,false);
    check($s->query('classified',null)['total']===0,'draft not indexed');
    $a->recordView($password,'same','Mozilla/5.0',false);$a->recordView($password,'same','Mozilla/5.0',false);
    $a->recordView($password,'admin','Mozilla/5.0',true);$a->recordView($password,'bot','Googlebot',false);
    check((int)$a->get($password)['view_count']===1,'qualified daily views');
    $a->recordView($password,'another','Mozilla/5.0',false);
    check($a->listing(null,1,true)['items'][0]['id']===$password,'popularity order');
    check(count($a->listing(null,1,true)['items'])<=10,'top ten limit');
    $a->trash($password,3);
    check(!in_array($password,array_column($s->query('password',null)['items'],'id'),true),'trashed search result removed');
    check($a->listing($other,1)['total']===1,'category listing');
    for($i=0;$i<25;$i++)$a->create($uid);
    $adminPage=$a->adminList('all','',2);
    check(isset($adminPage['items']) && $adminPage['pages']>1,'admin pagination reaches older articles');
});

run_group('natural_search',function() use($db) {
    $uid=(new Auth($db))->saveAdmin(['username'=>'natural-search','display_name'=>'Search tests','password'=>'testpass8','active'=>1],null);
    $a=new Articles($db);$s=new Search($db);$office=(new Categories($db))->save('Search Office',null);
    $make=function($title,$text,$keywords='',$category=null,$publish=true) use($a,$uid,$office) {
        $id=$a->create($uid);
        $a->save($id,['title'=>$title,'html'=>'<p>'.$text.'</p>','keywords'=>$keywords,'category_id'=>$category ?? $office],1,$uid,$publish);
        return $id;
    };
    $general=$make('Outlook issues','Basic troubleshooting for email. Check your connection and account settings.','mail email');
    $alias=$make('Mailbox help','Check account settings.','outlook');
    $body=$make('Account troubleshooting','Use Outlook to read messages.');
    $printer=$make('Start a printer','Check paper and toner.','',1);
    foreach (["My Outlook won't start",'My Outlook won’t start','Why is my Outlok not opening?','Outlook qzxvunknown','qzxvunknown Outlook','Can you please help me with my Outlook because it is not opening'] as $question) {
        $items=$s->query($question,$office)['items'];
        check(($items[0]['id'] ?? null)===$general,'broad Outlook article found for: '.$question);
    }
    check(array_column($s->query('my outlook',$office)['items'],'id')===[$general,$alias,$body],'title then keywords then body ranking');
    check(!in_array($printer,array_column($s->query("My Outlook won't start",$office)['items'],'id'),true),'partial matches respect category filter');
    check($s->query('my the please can you',null)['total']===0,'filler-only query does not return everything');
    check($s->query('qzxvunknown',null)['total']===0,'no meaningful match still yields no results');
    check($s->query('!!!',null)['total']===0,'punctuation-only query stays empty');
    expect_error(fn()=>$s->query(str_repeat('x',201),null),'200');

    $specific=$make('Outlook will not start','Follow these startup steps.');
    $ranked=array_column($s->query("My Outlook won't start",$office)['items'],'id');
    check($ranked[0]===$specific && in_array($general,$ranked,true),'more matched meaningful words rank first while broad guidance remains available');
    check(array_column($s->query('outlook outlook start',$office)['items'],'id')===$ranked,'repeated query words do not inflate relevance');
    check($s->query('Outlook issues',$office)['items'][0]['id']===$general,'exact article title ranks ahead of partial titles');
    $h=$make('H drive missing','Open File Explorer.','home drive');
    $q=$make('Q drive missing','Open File Explorer.','department drive');
    check($s->query('My Q drive is missing',$office)['items'][0]['id']===$q,'single-letter drive names remain meaningful');

    $private=$make('Outlook start secretglass','Unpublished instructions.','',null,false);
    check(!in_array($private,array_column($s->query('Outlook secretglass',$office)['items'],'id'),true),'partial search cannot return private drafts');
    $edit=$a->get($general,true);$edit['title']='Outlook secretglass draft';$edit['html']='<p>Never published instructions.</p>';
    $a->save($general,$edit,2,$uid,false);
    check($s->query('secretglass',$office)['total']===0,'draft-only wording is not searchable');
    check($s->query('Outlook issues',$office)['items'][0]['title']==='Outlook issues','search continues to show the published title');
    $a->trash($specific,2);$a->unpublish($alias,2);
    $visible=array_column($s->query("My Outlook won't start",$office)['items'],'id');
    check(!in_array($specific,$visible,true) && !in_array($alias,$visible,true),'Trash and unpublished articles stay out of partial results');

    for($i=1;$i<=25;$i++)$make('Outlook reference '.$i,'Reference instructions.');
    $p1=$s->query('Outlook qzxvunknown',$office,1);$p2=$s->query('Outlook qzxvunknown',$office,2);
    check($p1['total']===27 && $p1['pages']===2 && count($p1['items'])===20 && count($p2['items'])===7,'partial results have accurate counts and pagination');
    check(!array_intersect(array_column($p1['items'],'id'),array_column($p2['items'],'id')),'partial result pages contain no duplicates');
    check(array_column($s->query('Outlook qzxvunknown',$office,2)['items'],'id')===array_column($p2['items'],'id'),'partial ranking is stable between requests');
    $it=$make('Contact IT','Support contact details.');
    check($s->query('IT',$office)['items'][0]['id']===$it,'literal single-word technical terms remain searchable');
    foreach (['IT help','Can IT help?','How do I get IT help?'] as $question) {
        check(($s->query($question,$office)['items'][0]['id'] ?? null)===$it,'technical IT term survives a full question: '.$question);
    }
    check(($s->query("\xFFOutlook",$office)['items'][0]['id'] ?? null)===$general,'invalid UTF-8 byte does not crash contraction normalization');
});
