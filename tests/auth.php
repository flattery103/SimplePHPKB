<?php
run_group('auth', function() use ($db) {
    check(class_exists('Auth'), 'authentication service exists');
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $auth=new Auth($db);
    $password='Test-only-Password-47';
    $id=$auth->saveAdmin(['username'=>'admin','display_name'=>'KB Admin','password'=>$password,'active'=>1], null);
    check(!$auth->login('admin', 'wrong', '192.0.2.1'), 'incorrect password rejected');
    check($auth->login('admin', $password, '192.0.2.1'), 'valid login accepted');
    check($auth->current()['id'] === $id, 'authenticated account loaded');
    check($db->query('SELECT password_hash FROM admins LIMIT 1')->fetchColumn() !== $password, 'password not stored in plaintext');
    verify_csrf(csrf_token());
    expect_error(fn()=>verify_csrf(null), 'expired');
    expect_error(fn()=>verify_csrf(['bad']), 'expired');
    expect_error(fn()=>verify_csrf('wrong'), 'expired');
    expect_error(fn()=> $auth->saveAdmin(['active'=>0], $id), 'last active');
    $auth->saveAdmin(['password'=>'New-Password-For-Tests-87'], $id);
    check($auth->current() === null, 'password reset invalidates session');
    $id2=$auth->saveAdmin(['username'=>'backup','display_name'=>'Backup','password'=>$password,'active'=>1], null);
    $auth->saveAdmin(['active'=>0], $id2);
    check(!$auth->login('backup',$password,'192.0.2.3'), 'disabled admin denied');
    expect_error(fn()=> $auth->saveAdmin(['username'=>'ADMIN','display_name'=>'dup','password'=>$password,'active'=>1], null), 'already');
    for($i=0;$i<10;$i++) $auth->login('admin','wrong','192.0.2.2');
    check(!$auth->login('admin','New-Password-For-Tests-87','192.0.2.2'), 'login attempts limited');
    $auth->logout();
    check($auth->current() === null, 'logout clears admin');
});
