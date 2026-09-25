<?php
declare(strict_types=1);
final class Html {
    private HTMLPurifier $purifier;
    public function __construct(private PDO $db) {
        global $config;
        if (!class_exists('HTMLPurifier')) throw new RuntimeException('HTML Purifier dependency is missing. Use the complete release package.');
        $cache=$config['storage'].'/cache';
        if (!is_dir($cache) && !mkdir($cache,0770,true) && !is_dir($cache)) throw new RuntimeException('Cannot create HTML cache directory.');
        $c=HTMLPurifier_Config::createDefault();
        $c->set('Core.Encoding','UTF-8');
        $c->set('Cache.SerializerPath',$cache);
        $c->set('HTML.Allowed','p[style],br,strong,b,em,i,u,s,span[style],h2,h3,h4,h5,h6,ul,ol[start],li,blockquote,pre,code[class],a[href|title],img[src|alt|width|height],table,thead,tbody,tfoot,tr,th[colspan|rowspan|scope],td[colspan|rowspan],hr');
        $c->set('CSS.AllowedProperties',['text-align','color','background-color']);
        $c->set('URI.AllowedSchemes',['http'=>true,'https'=>true,'mailto'=>true]);
        $c->set('HTML.Nofollow',true);
        $this->purifier=new HTMLPurifier($c);
    }
    public function clean(string $html,int $articleId): string {
        if (strlen($html)>2*1024*1024 || !mb_check_encoding($html,'UTF-8')) throw new UserError('Article content must be valid UTF-8 and no larger than 2 MB.');
        $clean=$this->purifier->purify($html);
        $dom=$this->document($clean);
        foreach (iterator_to_array($dom->getElementsByTagName('img')) as $img) {
            $id=$this->imageId($img->getAttribute('src'));
            if (!$id) { $img->parentNode->removeChild($img); continue; }
            $q=$this->db->prepare('SELECT id FROM media WHERE id=? AND article_id=?'); $q->execute([$id,$articleId]);
            if (!$q->fetch()) throw new UserError('An image does not belong to this article. Upload it into this article first.');
            $img->setAttribute('src',route_url('media',['id'=>$id]));
        }
        $body=$dom->getElementsByTagName('body')->item(0); $out='';
        foreach ($body->childNodes as $child) $out.=$dom->saveHTML($child);
        return trim($out);
    }
    public function mediaIds(string $html): array {
        $ids=[];
        foreach ($this->document($html)->getElementsByTagName('img') as $img) { $id=$this->imageId($img->getAttribute('src')); if ($id) $ids[$id]=true; }
        return array_keys($ids);
    }
    private function imageId(string $src): ?string {
        $parts=parse_url($src);
        if (!$parts || isset($parts['scheme']) || isset($parts['host']) || isset($parts['fragment'])) return null;
        if (!in_array($parts['path'] ?? '', [app_url('index.php'),'index.php'],true)) return null;
        parse_str($parts['query'] ?? '',$q);
        return ($q['route'] ?? '')==='media' && is_string($q['id'] ?? null) && preg_match('/^[a-f0-9]{32}$/D',$q['id']) ? $q['id'] : null;
    }
    private function document(string $html): DOMDocument {
        $d=new DOMDocument('1.0','UTF-8');
        $previous=libxml_use_internal_errors(true);
        try { $d->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        return $d;
    }
    public static function text(string $html): string {
        return trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags(preg_replace('/<\/(?:p|h[1-6]|li|td|tr|pre|div)>|<br\s*\/?\s*>/i',' ',$html)),ENT_QUOTES|ENT_HTML5,'UTF-8')) ?? '');
    }
}
