<div class="page-heading"><div><div class="eyebrow">ADMINISTRATION</div><h1>Settings</h1><p>Manage branding, the homepage, your header link, and notices.</p></div></div>
<?php if($error): ?><div class="notice error" role="alert"><?=e($error)?></div><?php endif; ?>
<section class="settings-card branding-card">
<form method="post" enctype="multipart/form-data" action="<?=e(route_url('branding'))?>">
<?=csrf_field()?><input type="hidden" name="version" value="<?=$settings['lock_version']?>">
<h2>Branding</h2>
<label for="site-title">Site title</label><input id="site-title" name="title" value="<?=e($settings['title'])?>" maxlength="120" required>
<p class="field-help">Shown in the header, browser tab, and footer.</p>
<label for="site-subtitle">Subtitle <span class="optional">optional</span></label><input id="site-subtitle" name="subtitle" value="<?=e($settings['subtitle'])?>" maxlength="200">
<p class="field-help">Shown underneath the title in the header. Leave blank to hide it.</p>
<label for="site-logo">Logo <span class="optional">optional</span></label>
<?php if($settings['logo_id']): ?><div class="logo-preview"><img src="<?=e(route_url('logo'))?>" alt="Current site logo"></div><?php endif; ?>
<input id="site-logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" aria-describedby="logo-help">
<p class="field-help" id="logo-help">PNG, JPEG, or WebP. Maximum 2 MB and 4 megapixels. Transparent PNG works well. Leave empty to keep the current logo.</p>
<?php if($settings['logo_id']): ?><label class="checkbox-label"><input type="checkbox" name="remove_logo" value="1"> Remove the current logo</label><?php endif; ?>
<h2 class="layout-setting-heading">Homepage list</h2>
<label class="checkbox-label"><input type="checkbox" name="compact_home" value="1" <?=(string)$settings['compact_home']==='1' ? 'checked' : ''?>> Use compact rows on the homepage</label>
<p class="field-help">Keep article titles, summaries, category images, and category names at their regular size with less padding. Hide only featured labels and update dates. Starred articles still appear first.</p>
<h2 class="layout-setting-heading">Header link <span class="optional">optional</span></h2>
<label class="checkbox-label"><input type="checkbox" name="header_link_enabled" value="1" <?=(string)$settings['header_link_enabled']==='1' ? 'checked' : ''?>> Show a link in the header</label>
<p class="field-help">Visible to everyone, next to Sign out or Admin sign in. Turn it off to hide the link while keeping its details.</p>
<label for="header-link-text">Link text</label><input id="header-link-text" name="header_link_text" value="<?=e($settings['header_link_text'])?>" maxlength="80" placeholder="Submit a ticket">
<label for="header-link-url">Link URL</label><input id="header-link-url" name="header_link_url" type="url" value="<?=e($settings['header_link_url'])?>" maxlength="2048" placeholder="https://your-ticket-system/" aria-describedby="header-link-help">
<p class="field-help" id="header-link-help">Enter the full address, beginning with http:// or https://.</p>
<label for="header-link-window">Open link in</label><select id="header-link-window" name="header_link_new_window"><option value="0" <?=(string)$settings['header_link_new_window']==='0' ? 'selected' : ''?>>Current window/tab</option><option value="1" <?=(string)$settings['header_link_new_window']==='1' ? 'selected' : ''?>>New window/tab</option></select>
<h2 class="layout-setting-heading">Known-issues banner <span class="optional">optional</span></h2>
<label class="checkbox-label"><input type="checkbox" name="banner_enabled" value="1" <?=(string)$settings['banner_enabled']==='1' ? 'checked' : ''?>> Show the known-issues banner</label>
<label for="banner-text">Banner message</label><textarea id="banner-text" name="banner_text" rows="4" maxlength="1000" aria-describedby="banner-help" placeholder="Outlook is experiencing delays. IT is investigating."><?=e($settings['banner_text'])?></textarea>
<p class="field-help" id="banner-help">Shown below the header on every page until you turn it off. Plain text, up to 1,000 characters; line breaks are kept. Turning it off keeps the message for reuse.</p>
<div class="branding-save"><button class="button primary" type="submit">Save settings</button></div>
</form></section>
