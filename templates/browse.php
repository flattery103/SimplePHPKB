<?php $compact=$isHome && !empty($branding['compact_home']);$categoryImages=array_column($categories,'image_id','id'); ?>
<div class="compact-search"><?php require __DIR__.'/search-form.php'; ?></div>
<section class="article-section">
<div class="section-heading"><div><?php if(!$isHome): ?><div class="eyebrow">KNOWLEDGE BASE</div><?php endif; ?><h2><?=$isHome ? 'Featured and popular articles' : ($results['total'].' '.($results['total']===1 ? 'article' : 'articles'))?></h2></div><?php if($isHome): ?><a class="text-link" href="<?=e(route_url('browse'))?>">View all articles <span aria-hidden="true">→</span></a><?php endif; ?></div>
<?php if(!$results['items']): ?>
<div class="empty-state"><span class="empty-icon" aria-hidden="true">▤</span><h3><?=$q!=='' ? 'No articles found' : 'No published articles yet'?></h3><p><?=$q!=='' ? 'Try a shorter phrase or a different keyword.' : 'Published articles will appear here as your knowledge base grows.'?></p><a class="button secondary" href="<?=e(route_url('browse'))?>">Browse all articles</a></div>
<?php else: ?><div class="article-list<?=$compact ? ' compact-list' : ''?>">
<?php foreach($results['items'] as $item): ?>
<article class="article-row">
<figure class="article-category-icon">
<img class="category-icon" src="<?=e(!empty($categoryImages[$item['category_id'] ?? 0]) ? route_url('category-image',['id'=>$item['category_id']]) : app_url('assets/category-default.svg'))?>" alt="" loading="lazy" width="48" height="48">
<figcaption><?=e($item['category_name'] ?? 'Uncategorized')?></figcaption>
</figure>
<div class="article-summary">
<?php if(!$compact && $isHome && !empty($item['featured'])): ?><span class="featured-badge"><span aria-hidden="true">★</span> Featured</span><?php endif; ?>
<h3><a href="<?=e(route_url('article',['id'=>$item['id']]))?>"><?=e($item['title'])?></a></h3>
<p><?=e(excerpt($item['plain_text']))?></p>
<?php if(!$compact): ?><div class="article-meta">Updated <?=e(formatted_date($item['updated_at']))?></div><?php endif; ?>
</div>
<a class="row-arrow" href="<?=e(route_url('article',['id'=>$item['id']]))?>" aria-label="<?=e('Read '.$item['title'])?>">→</a>
</article>
<?php endforeach; ?></div>
<?php endif; ?>
<?php if($results['pages']>1): ?><nav class="pagination" aria-label="Article pages"><?php if($results['page']>1): ?><a class="button secondary" href="<?=e(route_url('browse',array_filter(['q'=>$q,'category'=>$cat,'page'=>$results['page']-1])))?>">← Previous</a><?php endif; ?><span>Page <?=$results['page']?> of <?=$results['pages']?></span><?php if($results['page']<$results['pages']): ?><a class="button secondary" href="<?=e(route_url('browse',array_filter(['q'=>$q,'category'=>$cat,'page'=>$results['page']+1])))?>">Next →</a><?php endif; ?></nav><?php endif; ?>
</section>
