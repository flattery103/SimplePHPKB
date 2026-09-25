#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try {
    require __DIR__.'/../src/bootstrap.php';
    $command=$argv[1] ?? 'help';
    if ($command === 'migrate') { Database::migrate($db); echo "Database is up to date.\n"; }
    elseif (in_array($command, ['admin:create','admin:reset'], true)) {
        $username=$argv[2] ?? '';
        if (!preg_match('/^[A-Za-z0-9_.@-]{3,80}$/D', $username)) throw new UserError('Supply a username (3–80 letters, numbers, . _ @ -).');
        $tty=function_exists('stream_isatty') && stream_isatty(STDIN);
        if ($tty) { fwrite(STDOUT, 'Password (at least 8 characters): '); system('stty -echo'); }
        try { $password=rtrim((string)fgets(STDIN), "\r\n"); }
        finally { if ($tty) { system('stty echo'); fwrite(STDOUT,"\n"); } }
        $auth=new Auth($db);
        $q=$db->prepare('SELECT id FROM admins WHERE username=?'); $q->execute([$username]); $id=$q->fetchColumn();
        if ($command === 'admin:create' && $id) throw new UserError('That username already exists.');
        if ($command === 'admin:reset' && !$id) throw new UserError('No such admin.');
        $auth->saveAdmin(['username'=>$username,'display_name'=>$username,'password'=>$password,'active'=>1], $id ? (int)$id : null);
        echo "Admin account saved.\n";
    } else { echo "SimplePHPKB ".KB_VERSION."\nCommands:\n  php bin/kb.php migrate\n  php bin/kb.php admin:create USERNAME\n  php bin/kb.php admin:reset USERNAME\nPasswords are read from the terminal or stdin; never pass them as arguments.\n"; }
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); }
