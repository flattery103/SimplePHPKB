<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Application updates · SimplePHPKB</title><link rel="stylesheet" href="<?=e(app_url('assets/app.css'))?>?v=<?=e(KB_VERSION)?>"></head><body><main class="setup-page">
<h1>Application updates</h1><p>Installed version: <strong><?=e(KB_VERSION)?></strong></p>
<?php if($error): ?><div class="notice error" role="alert"><?=e($error)?></div><?php endif; ?>
<?php if(isset($_SESSION['flash'])): ?><div class="notice success" role="status"><?=e($_SESSION['flash'])?></div><?php unset($_SESSION['flash']);endif; ?>
<?php if(!$admin): ?>
<h2>Admin sign in</h2><p>Sign in to manage updates, including while the site is under maintenance.</p><form method="post" class="setup-form"><?=csrf_field()?><input type="hidden" name="action" value="login"><label>Username<input name="username" autocomplete="username" required></label><label>Password<input name="password" type="password" autocomplete="current-password" required></label><button class="button primary">Sign in</button></form>
<?php else: ?>
<p><a href="<?=e(route_url('admin'))?>">← Back to administration</a></p>
<section><h2>GitHub releases</h2>
<?php if($release['status']==='available'): ?><div class="notice success">Version <?=e($release['version'])?> is available. <a href="<?=e($release['url'])?>" target="_blank" rel="noopener noreferrer">View release and download update</a></div>
<?php elseif($release['status']==='current'): ?><p>You are running the latest release or a newer development version.</p>
<?php elseif($release['status']==='none'): ?><p>No published release was found. The repository may be empty or unavailable.</p>
<?php else: ?><p>Could not check GitHub. You can still upload an update ZIP.</p><?php endif; ?>
<p><a href="<?=e(Releases::REPOSITORY)?>/releases" target="_blank" rel="noopener noreferrer">All releases</a> · Checked <?=e(gmdate('Y-m-d H:i',$release['checked_at']))?> UTC</p>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="check"><button class="button secondary">Check again</button></form></section>
<?php if($state): ?><div class="notice">An update is in progress. Readers will see a maintenance message until it finishes.</div><?php endif; ?>
<section><h2>Backup</h2><p>Save a copy before updating. The ZIP contains your database, application, settings and uploaded images. Keep it private.</p>
<?php if(!$state): ?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="backup"><button class="button secondary">Create backup</button></form><?php endif; ?>
<?php if(is_file($directory.'/backup.zip')): ?><p><a class="button secondary" href="<?=e(route_url('updates',['download'=>'backup']))?>">Download backup</a> <small>Created <?=e(gmdate('Y-m-d H:i',filemtime($directory.'/backup.zip')))?> UTC</small></p><?php endif; ?></section>
<?php if(($state['phase']??'')==='files'||(!$state&&$staged)): ?>
<section><h2><?=($state['phase']??'')==='files'?'Resume update':'Review update'?></h2><p>Version <strong><?=e($staged['version'])?></strong> · <?=count($staged['files'])?> files</p>
<form method="post" class="setup-form"><?=csrf_field()?><input type="hidden" name="action" value="apply"><?php if(!$state): ?><label class="check-label"><input type="checkbox" name="backup_saved" value="1" required> I downloaded and saved a backup.</label><?php endif; ?><button class="button primary"><?= $state?'Resume file update':'Apply application files'?></button></form>
<?php if(!$state): ?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="discard"><button class="button secondary">Discard update</button></form><?php endif; ?></section>
<?php elseif($pending||($state['phase']??'')==='database'): ?>
<section><h2>Database updates</h2><p><?=count($pending)?> database update(s) pending. A server backup is saved before changes begin.</p><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="migrate"><button class="button primary">Apply database changes and finish</button></form></section>
<?php else: ?><p>Database is up to date.</p><?php endif; ?>
<?php if(!$state&&!$pending): ?><section><h2>Upload an update</h2><p>Choose the <strong>SimplePHPKB-v…-update.zip</strong> asset from a trusted project release. Your settings and images are preserved.</p><form method="post" enctype="multipart/form-data" class="setup-form"><?=csrf_field()?><input type="hidden" name="action" value="upload"><label>Update ZIP<input type="file" name="package" accept=".zip" required></label><small>Server file limit: <?=e(ini_get('upload_max_filesize'))?>; form limit: <?=e(ini_get('post_max_size'))?>. Application ZIP limit: 32 MB.</small><button class="button primary">Upload and review</button></form></section><?php endif; ?>
<?php endif; ?></main></body></html>
