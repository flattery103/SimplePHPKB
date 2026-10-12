<?php
declare(strict_types=1);
run_group('usage', function() use ($db) {
    check(class_exists('Usage'), 'admin usage reporting is available');
    $uid=(new Auth($db))->saveAdmin(['username'=>'usage-admin','display_name'=>'Usage admin','password'=>'test1234','active'=>1],null);
    $a=new Articles($db);$usage=new Usage($db);
    $make=function(string $title,bool $publish=true)use($a,$uid):int {
        $id=$a->create($uid);
        $a->save($id,['title'=>$title,'category_id'=>1,'keywords'=>'','html'=>'<p>Help</p>'],1,$uid,$publish);
        return $id;
    };
    $one=$make('Usage Outlook help');$two=$make('Usage printer help');$zero=$make('Usage unused help');$draft=$make('Usage private draft',false);
    // Other groups share this disposable database; give this group an isolated count baseline.
    foreach(['site_daily','site_visits','article_daily','article_views'] as $table)$db->exec('DELETE FROM '.$table);
    $db->exec('UPDATE articles SET view_count=0');
    $db->prepare('UPDATE articles SET view_count=40 WHERE id=?')->execute([$one]);
    foreach([['','Mozilla/5.0',false],['empty-agent','',false],['admin','Mozilla/5.0',true],['bot','Googlebot',false],['preview','Slackbot-LinkExpanding',false],['headless','HeadlessChrome',false]] as [$key,$agent,$admin]) {
        $usage->recordSiteVisit($key,$agent,$admin);$a->recordView($one,$key,$agent,$admin);
    }
    check($usage->report('today')['site_visits']===0,'admins, bots and missing identifiers do not count');
    $usage->recordSiteVisit('reader-one','Mozilla/5.0',false);
    $usage->recordSiteVisit('reader-one','Mozilla/5.0',false);
    $usage->recordSiteVisit('reader-two','Mozilla/5.0',false);
    $a->recordView($one,'reader-one','Mozilla/5.0',false);
    $a->recordView($one,'reader-one','Mozilla/5.0',false);
    $a->recordView($one,'reader-two','Mozilla/5.0',false);
    $a->recordView($two,'reader-one','Mozilla/5.0',false);
    $a->recordView($draft,'reader-one','Mozilla/5.0',false);
    $a->recordView(2147483647,'reader-one','Mozilla/5.0',false);
    $today=$usage->report('today');
    check($today['site_visits']===2,'site visit counted once per session each day');
    check($today['article_views']===3 && $today['articles_read']===2,'each article counted independently, excluding drafts and missing articles');
    check((int)$a->get($one)['view_count']===42,'historical all-time article count preserved and incremented');
    check($today['items'][0]['id']===$one && $today['items'][0]['views']===2,'usage list ranks articles by selected period');
    $zeroRows=$usage->report('today','Usage unused')['items'];
    check(count($zeroRows)===1 && $zeroRows[0]['views']===0,'unused articles remain visible with zero views');

    // Dates on both sides of the inclusive 7/30-day boundaries, plus a future row.
    foreach([1,6,7,29,30] as $days) {
        $db->exec("INSERT INTO site_daily (view_day,visit_count) VALUES (UTC_DATE()-INTERVAL $days DAY,10)");
        $db->prepare("INSERT INTO article_daily (article_id,view_day,view_count) VALUES (?,UTC_DATE()-INTERVAL $days DAY,10)")->execute([$two]);
    }
    $db->exec('INSERT INTO site_daily (view_day,visit_count) VALUES (UTC_DATE()+INTERVAL 1 DAY,999)');
    $db->prepare('INSERT INTO article_daily (article_id,view_day,view_count) VALUES (?,UTC_DATE()+INTERVAL 1 DAY,999)')->execute([$two]);
    $db->prepare('UPDATE articles SET view_count=view_count+50 WHERE id=?')->execute([$two]);
    $week=$usage->report('7');$month=$usage->report('30');$all=$usage->report('all');
    check($week['site_visits']===22 && $week['article_views']===23,'7 days includes today and six preceding UTC dates');
    check($month['site_visits']===42 && $month['article_views']===43,'30 days includes today and 29 preceding UTC dates');
    check($all['site_visits']===52 && $all['article_views']===93,'all-time uses legacy article totals and retained site aggregates');
    check($month['items'][0]['id']===$two && $month['items'][0]['views']===41,'selected period changes ranking');
    $a->trash($two,$a->get($two,true)['lock_version']);
    check($usage->report('all')['article_views']===93,'trashing an article preserves historical totals');
    $a->recordView($two,'new-reader','Mozilla/5.0',false);
    check($usage->report('all')['article_views']===93,'trashed articles cannot gain new views');

    // Deduplication rows expire, but aggregated history remains.
    $db->exec("INSERT INTO site_visits (session_hash,view_day) VALUES ('old',UTC_DATE()-INTERVAL 3 DAY)");
    $db->prepare("INSERT INTO article_views (article_id,session_hash,view_day) VALUES (?,'old',UTC_DATE()-INTERVAL 3 DAY)")->execute([$one]);
    $usage->recordSiteVisit('reader-three','Mozilla/5.0',false);$a->recordView($one,'reader-three','Mozilla/5.0',false);
    check((int)$db->query("SELECT COUNT(*) FROM site_visits WHERE session_hash='old'")->fetchColumn()===0,'old site session hashes expire');
    check((int)$db->query("SELECT COUNT(*) FROM article_views WHERE session_hash='old'")->fetchColumn()===0,'old article session hashes expire');
    check($usage->report('30')['article_views']===44,'cleanup retains daily history');
    $hashes=$db->query('SELECT session_hash FROM site_visits')->fetchAll(PDO::FETCH_COLUMN);
    check(in_array(hash('sha256','reader-one'),$hashes,true) && !in_array('reader-one',$hashes,true),'only hashed session identifiers stored');
    $before=$usage->report('all');$db->exec('DELETE FROM schema_migrations WHERE version=5');Database::migrate($db);
    check($usage->report('all')===$before,'replaying usage migration preserves totals and tracking start');
    expect_error(fn()=>$usage->report('invalid'),'period');
    for($i=0;$i<22;$i++)$make('Usage pagination '.$i,false);
    $page=$usage->report('today','Usage pagination',2);
    check($page['total']===22 && $page['page']===2 && count($page['items'])===2,'article report paginates and keeps unused drafts');
    check($usage->report('today','Usage pagination',999)['page']===2,'out-of-range report page clamps to last page');
});
