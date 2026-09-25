<div class="page-heading"><div><div class="eyebrow">ADMINISTRATION</div><h1>Categories</h1><p>A simple way to keep related answers together.</p></div></div>
<?php if($error): ?><div class="notice error" role="alert"><?=e($error)?></div><?php endif; ?>
<div class="settings-card"><h2>Add a category</h2>
<form class="category-create-form" method="post" enctype="multipart/form-data" action="<?=e(route_url('categories'))?>">
<?=csrf_field()?><input type="hidden" name="action" value="save">
<label for="new-category">Category name</label><input id="new-category" name="name" required maxlength="100" value="<?=e(!isset($_POST['id']) && is_string($_POST['name'] ?? null) ? $_POST['name'] : '')?>">
<label for="new-category-image">Category image <span class="optional">optional</span></label><input id="new-category-image" name="image" type="file" accept="image/png,image/jpeg,image/webp" aria-describedby="category-image-help">
<p class="field-help" id="category-image-help">PNG, JPEG, or WebP, up to 2 MB and 4 megapixels. A square image works well. Categories without an image show a question mark.</p>
<button class="button primary" type="submit">Add category</button>
</form></div>
<div class="settings-card"><h2>Manage categories</h2>
<?php foreach($categories as $c):
    $failedSave=$error && ($_POST['action'] ?? null)==='save' && ($_POST['id'] ?? null)===(string)$c['id'];
    $editName=$failedSave && is_string($_POST['name'] ?? null) ? $_POST['name'] : $c['name'];
    $editVersion=$failedSave && is_string($_POST['version'] ?? null) && ctype_digit($_POST['version']) ? (int)$_POST['version'] : $c['lock_version'];
?>
<div class="category-admin-row">
<form class="category-edit-form" method="post" enctype="multipart/form-data" action="<?=e(route_url('categories'))?>">
<?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$c['id']?>"><input type="hidden" name="version" value="<?=$editVersion?>">
<img class="category-icon category-preview" src="<?=e($c['image_id'] ? route_url('category-image',['id'=>$c['id']]) : app_url('assets/category-default.svg'))?>" alt="<?=e('Image for '.$c['name'])?>">
<div class="category-fields">
<label for="cat-<?=$c['id']?>">Category name</label><input id="cat-<?=$c['id']?>" name="name" value="<?=e($editName)?>" required maxlength="100">
<label for="cat-image-<?=$c['id']?>">Category image</label><input id="cat-image-<?=$c['id']?>" name="image" type="file" accept="image/png,image/jpeg,image/webp">
<p class="field-help">Leave empty to keep the current image. PNG, JPEG, or WebP, up to 2 MB and 4 megapixels.</p>
<?php if($c['image_id']): ?><label class="checkbox-label"><input type="checkbox" name="remove_image" value="1"> Remove image and use the question mark</label><?php endif; ?>
<div class="category-save"><button class="button secondary small" type="submit">Save category</button><span class="subtle"><?=$c['article_count']?> published</span></div>
</div></form>
<form method="post" action="<?=e(route_url('categories'))?>" data-confirm="Delete this empty category?"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="link-button danger-text">Delete</button></form>
</div>
<?php endforeach; ?><p class="field-help">Move draft and published articles to another category before deleting a category.</p></div>
