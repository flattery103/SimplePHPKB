<?php
if ($group==='benchmark') run_group('benchmark',function()use($db) {
    $uid=(new Auth($db))->saveAdmin(['username'=>'bench','display_name'=>'Benchmark','password'=>'Only-Test-Benchmark-67','active'=>1],null);
    $articles=new Articles($db);$search=new Search($db);
    $start=microtime(true);
    for($i=1;$i<=5000;$i++) {
        $id=$articles->create($uid);
        $title=$i===5000 ? 'Password recovery procedure' : 'Employee procedure '.$i;
        $articles->save($id,['title'=>$title,'category_id'=>1,'keywords'=>'employee helpdesk','html'=>'<p>Step one: follow the instructions for the employee device. Step two: contact the help desk. Reference number '.$i.'.</p>'],1,$uid,true);
    }
    echo 'Seeded 5000 published articles in '.round(microtime(true)-$start,2)."s\n";
    foreach(['password','pasword','employee',"My password won't work",'Please help me with an employee device qzxvunknown'] as $query) {
        $start=microtime(true);$r=$search->query($query,1);$ms=(microtime(true)-$start)*1000;
        check($r['total']>0,'benchmark '.$query.' returns results');
        check($ms<2000,'benchmark '.$query.' under 2 seconds');
        echo $query.': '.round($ms,1).' ms, '.$r['total']." results\n";
    }
    $p1=$search->query('employee',1,1);$p2=$search->query('employee',1,2);
    check(!array_intersect(array_column($p1['items'],'id'),array_column($p2['items'],'id')),'stable distinct pages');
    check(count($articles->listing(null,1,true)['items'])===10,'exactly top 10 for large collection');
});
