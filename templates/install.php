<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Install SimplePHPKB</title><link rel="stylesheet" href="<?=e(app_url('assets/app.css'))?>?v=<?=e(KB_VERSION)?>"></head><body><main class="setup-page">
<h1>Install SimplePHPKB</h1><p>Set up your knowledge base and first administrator account.</p>
<?php if($error): ?><div class="notice error" role="alert"><?=e($error)?></div><?php endif; ?>
<details<?=in_array(false,$checks,true)?' open':''?>><summary>Server requirements</summary><ul><?php foreach($checks as $label=>$ok): ?><li><?=$ok?'✓':'✗'?> <?=e($label)?></li><?php endforeach; ?><li><?=extension_loaded('zip')?'✓':'✗'?> PHP zip (browser upgrades and backups)</li><li><?=extension_loaded('curl')?'✓':'✗'?> PHP curl (GitHub release checks)</li></ul></details>
<form method="post" class="setup-form"><?=csrf_field()?>
<h2>Database</h2><p>Use an empty MySQL database and the account provided by your server administrator or hosting control panel.</p>
<?php foreach(['db_host'=>'Host','db_port'=>'Port','db_name'=>'Database name','db_user'=>'Database username'] as $key=>$label): ?><label><?=e($label)?><input name="<?=e($key)?>" value="<?=e($values[$key])?>" required></label><?php endforeach; ?>
<label>Database password<input type="password" name="db_password" autocomplete="new-password"></label>
<label class="check-label"><input type="checkbox" name="create_database" value="1" <?=input_string($_POST,'create_database')==='1'?'checked':''?>> Create the database if it does not exist (requires permission)</label>
<h2>Site</h2>
<label>Site title<input name="title" maxlength="120" value="<?=e($values['title'])?>" required></label>
<label>Base path<input name="base_path" value="<?=e($values['base_path'])?>" placeholder="Leave blank for the domain root"></label>
<label>Timezone<input name="timezone" value="<?=e($values['timezone'])?>" required></label>
<label class="check-label"><input type="checkbox" name="secure_cookies" value="1" <?=$values['secure_cookies']==='1'?'checked':''?>> This site uses HTTPS (including through a proxy)</label>
<h2>First administrator</h2>
<label>Username<input name="username" value="<?=e($values['username'])?>" required autocomplete="username"></label>
<label>Display name<input name="display_name" value="<?=e($values['display_name'])?>" required></label>
<label>Password (at least 8 characters)<input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
<label>Confirm password<input type="password" name="password_confirm" minlength="8" required autocomplete="new-password"></label>
<button class="button primary" type="submit"<?=in_array(false,$checks,true)?' disabled':''?>>Install knowledge base</button>
</form></main></body></html>
