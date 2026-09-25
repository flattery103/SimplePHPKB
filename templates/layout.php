<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=e($title)?> · <?=e($branding['title'])?></title>
<link rel="icon" href="<?=e(app_url('assets/favicon.svg'))?>" type="image/svg+xml">
<link rel="stylesheet" href="<?=e(app_url('assets/app.css'))?>?v=<?=e(KB_VERSION)?>">
<script src="<?=e(app_url('assets/app.js'))?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="topbar"><div class="topbar-inner<?=$branding['header_link_enabled'] ? ' has-header-link' : ''?>">
<a class="brand" href="<?=e(route_url('home'))?>"><?php if($branding['logo_id']): ?><img class="brand-logo" src="<?=e(route_url('logo'))?>" alt=""><?php else: ?><span class="brand-symbol" aria-hidden="true">▤</span><?php endif; ?><span class="brand-text"><?=e($branding['title'])?><?php if($branding['subtitle']!==''): ?><small><?=e($branding['subtitle'])?></small><?php endif; ?></span></a>
<nav class="topnav" aria-label="Main navigation"><a href="<?=e(route_url('browse'))?>">Browse articles</a>
<?php if($admin): ?><a class="nav-admin" href="<?=e(route_url('admin'))?>">Manage KB</a><?php endif; ?>
<?php if($branding['header_link_enabled']): ?><a class="header-custom-link" href="<?=e($branding['header_link_url'])?>" target="<?=$branding['header_link_new_window'] ? '_blank' : '_self'?>"<?=$branding['header_link_new_window'] ? ' rel="noopener noreferrer"' : ''?>><?=e($branding['header_link_text'])?><?php if($branding['header_link_new_window']): ?><span class="sr-only"> (opens in a new window or tab)</span><?php endif; ?></a><?php endif; ?>
<?php if($admin): ?><form method="post" action="<?=e(route_url('logout'))?>"><?=csrf_field()?><button class="link-button" type="submit">Sign out</button></form><?php else: ?><a class="nav-admin" href="<?=e(route_url('login'))?>">Admin sign in <span aria-hidden="true">↗</span></a><?php endif; ?></nav>
</div></header>
<?php if($branding['banner_enabled']): ?><div class="site-banner" role="status"><div class="site-banner-inner"><?=e($branding['banner_text'])?></div></div><?php endif; ?>
<div class="shell">
<aside class="sidebar"><button class="category-toggle" type="button" aria-expanded="false" aria-controls="category-menu">Categories <span aria-hidden="true">⌄</span></button>
<div id="category-menu" class="category-menu"><div class="sidebar-label">EXPLORE THE KNOWLEDGE BASE</div>
<nav aria-label="Categories"><a class="category-link <?=!$cat ? 'selected' : ''?>" href="<?=e(route_url('browse'))?>"><span>All articles</span><span class="count"><?=array_sum(array_column($categories,'article_count'))?></span></a>
<?php foreach($categories as $c): ?><a class="category-link <?=(int)$cat===(int)$c['id'] ? 'selected' : ''?>" href="<?=e(route_url('browse',['category'=>$c['id']]))?>"><span><?=e($c['name'])?></span><span class="count">(<?=$c['article_count']?>)</span></a><?php endforeach; ?></nav>
<?php if($admin): ?><div class="sidebar-label admin-label">ADMINISTRATION</div><nav aria-label="Administration"><a class="category-link" href="<?=e(route_url('admin'))?>">Manage articles</a><a class="category-link" href="<?=e(route_url('categories'))?>">Manage categories</a><a class="category-link" href="<?=e(route_url('admins'))?>">Admin accounts</a><a class="category-link" href="<?=e(route_url('branding'))?>">Settings</a><a class="category-link" href="<?=e(route_url('updates'))?>">Application updates</a></nav><?php endif; ?>
<div class="sidebar-note"><span class="note-mark" aria-hidden="true">?</span><strong>A little help, right here.</strong><p>Search for a task or browse a category to find the next step.</p></div>
</div></aside>
<main id="main" class="main">
<?php if(isset($_SESSION['flash'])): ?><div class="notice success" role="status"><?=e($_SESSION['flash'])?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if($admin && ($release['status']??'')==='available'): ?><div class="notice release-notice" role="status">SimplePHPKB <?=e($release['version'])?> is available. <a href="<?=e(route_url('updates'))?>">View update</a></div><?php endif; ?>
<?=$content?>
</main></div>
<footer class="footer"><span><?=e($branding['title'])?></span><span>Knowledge, shared. <span class="version">v<?=e(KB_VERSION)?></span></span></footer>
</body></html>
