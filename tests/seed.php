<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
if(!preg_match('/dbname=[A-Za-z0-9_]+_test(?:;|$)/',$config['db']['dsn']))throw new RuntimeException('Seed requires _test database.');
$db->exec('SET FOREIGN_KEY_CHECKS=0');
foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {if(!preg_match('/^[a-z_]+$/D',$table))throw new RuntimeException('Unsafe table');$db->exec('DROP TABLE `'.$table.'`');}
$db->exec('SET FOREIGN_KEY_CHECKS=1');Database::migrate($db);
$uid=(new Auth($db))->saveAdmin(['username'=>'admin','display_name'=>'Test Administrator','password'=>'Only-Test-Browser-Password-74','active'=>1],null);
$c=new Categories($db);$c->save('Getting started',1);
$accounts=$c->save('Accounts & access',null);$hardware=$c->save('Hardware & devices',null);$network=$c->save('Network & connectivity',null);
$a=new Articles($db);
$entries=[
['Reset your account password',$accounts,'password login reset','<p>Keep your account secure with a strong, unique password.</p><h2>Before you begin</h2><p>Have your phone ready to approve a sign-in request.</p><h2>Change your password</h2><ol><li>Open your account settings.</li><li>Select <strong>Change password</strong>.</li><li>Enter your current password and choose a new one.</li></ol><blockquote>Having trouble? Contact the IT help desk for assistance.</blockquote>'],
['Connect to the staff Wi-Fi',$network,'wifi wireless internet','<p>Get your laptop connected to the staff wireless network.</p><h2>Connect your device</h2><ol><li>Open your wireless settings.</li><li>Choose the staff network.</li><li>Sign in using the details provided by IT.</li></ol>'],
['Set up a shared printer',$hardware,'printing paper','<p>Add an office printer and send your first print job.</p><h2>Add a printer</h2><p>Open Settings, then Printers & scanners. Choose the printer for your location.</p>'],
['Getting started with the knowledge base',1,'help guide support','<p>Find the instructions you need, when you need them.</p><h2>Search or browse</h2><p>Type a few words in the search box, or choose a category on the left. Search can also find common misspellings.</p>'],
['Set up multi-factor authentication',$accounts,'mfa authenticator','<p>Add a second sign-in method to protect your account.</p><h2>Set up your phone</h2><p>Follow the enrollment instructions from your IT team.</p>'],
['Troubleshoot a second monitor',$hardware,'display screen','<p>Check the display cable, input source, and display settings when a second screen is not detected.</p>'],
['Connect from home',$network,'vpn remote','<p>Use the approved remote access connection when working away from the office.</p>'],
['Contact the IT help desk',1,'support assistance','<p>Include the device name, the error message, and what you were doing when the issue occurred.</p>']];
foreach($entries as $i=>$entry){$id=$a->create($uid);$a->save($id,['title'=>$entry[0],'category_id'=>$entry[1],'keywords'=>$entry[2],'html'=>$entry[3]],1,$uid,true);$db->prepare('UPDATE articles SET view_count=? WHERE id=?')->execute([120-$i*11,$id]);}
$fixture=imagecreatetruecolor(80,40);$bg=imagecolorallocate($fixture,18,127,119);imagefill($fixture,0,0,$bg);imagepng($fixture,__DIR__.'/artifacts/screenshot.png');imagefill($fixture,0,0,imagecolorallocate($fixture,190,20,40));imagepng($fixture,__DIR__.'/artifacts/replacement.png');unset($fixture);
$large=imagecreatetruecolor(2001,2000);imagepng($large,__DIR__.'/artifacts/too-many-pixels.png');unset($large);
echo "Browser fixtures created.\n";
