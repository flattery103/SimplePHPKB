<form class="search-form" role="search" method="get" action="<?=e(app_url('index.php'))?>">
<input type="hidden" name="route" value="browse"><?php if($cat): ?><input type="hidden" name="category" value="<?=(int)$cat?>"><?php endif; ?>
<label class="sr-only" for="kb-search">Search the knowledge base</label><span class="search-icon" aria-hidden="true">⌕</span><input type="search" id="kb-search" name="q" value="<?=e($q)?>" placeholder="Search for answers, instructions, and how-tos…" maxlength="200"><button type="submit" class="button primary">Search</button>
</form>
