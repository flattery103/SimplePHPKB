<?php
declare(strict_types=1);
final class Search {
    public function __construct(private PDO $db) {}
    public static function tokens(string $s): array {
        $s=mb_strtolower($s,'UTF-8');
        preg_match_all('/[\p{L}\p{N}]{1,64}/u',$s,$m);
        return array_values(array_unique($m[0]));
    }
    private static function queryTokens(string $q): array {
        // Normalize contractions before tokenizing so "won't" does not become "won" and "t".
        $q=preg_replace('/(?<=\p{L})[\x{0027}\x{2019}](?=\p{L})/u','',$q) ?? $q;
        $tokens=self::tokens($q);
        // Keep literal one-word searches, including short technical terms such as "IT".
        if (count($tokens)<=1) return $tokens;
        // "it" is deliberately retained for department searches such as "IT help".
        $stop=array_fill_keys(explode(' ',
            'a an the and or but if so because as at by for from in into of on to with '.
            'i me my mine we us our you your he she they them their its this that these those '.
            'how what when where which who why please help '.
            'am is are was were be been being do does did have has had '.
            'can could should would will may might must not no '.
            'cant cannot couldnt shouldnt wouldnt wont dont doesnt didnt isnt arent wasnt werent '.
            'im ive youre youve weve theyve thats theres'),true);
        return array_slice(array_values(array_filter($tokens,fn($token)=>!isset($stop[$token]))),0,8);
    }
    public function index(int $articleId,?array $published): void {
        $this->db->prepare('DELETE FROM search_postings WHERE article_id=?')->execute([$articleId]);
        if (!$published) return;
        $words=[];
        foreach (['plain_text'=>1,'keywords'=>7,'title'=>12] as $field=>$weight) foreach (self::tokens($published[$field]) as $term) $words[$term]=max($weight,$words[$term] ?? 0);
        $term=$this->db->prepare('INSERT IGNORE INTO search_terms (term,term_length) VALUES (?,?)');
        $posting=$this->db->prepare('INSERT INTO search_postings (term,article_id,weight) VALUES (?,?,?)');
        foreach ($words as $word=>$weight) { $word=(string)$word; $term->execute([$word,mb_strlen($word)]);$posting->execute([$word,$articleId,$weight]); }
    }

    private static function distance(string $a,string $b): int {
        if (preg_match('/^[\x00-\x7F]*$/D',$a.$b)) return levenshtein($a,$b);
        $aa=mb_str_split($a);$bb=mb_str_split($b);$previous=range(0,count($bb));
        foreach($aa as $i=>$ca) {
            $row=[$i+1];
            foreach($bb as $j=>$cb) $row[]=min($row[$j]+1,$previous[$j+1]+1,$previous[$j]+($ca===$cb ? 0 : 1));
            $previous=$row;
        }
        return $previous[count($bb)];
    }
    private function matches(string $token): array {
        $q=$this->db->prepare('SELECT term FROM search_terms WHERE term LIKE ? ORDER BY term LIMIT 128');
        $q->execute([$token.'%']);$matches=[];
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $term) $matches[$term]=$term===$token ? 6 : 3;
        $len=mb_strlen($token);
        if ($len>3) {
            $max=$len>7 ? 2 : 1;
            $q=$this->db->prepare('SELECT term FROM search_terms WHERE term_length BETWEEN ? AND ? ORDER BY term LIMIT 20000');
            $q->execute([$len-$max,$len+$max]);
            foreach($q->fetchAll(PDO::FETCH_COLUMN) as $term) {
                if (isset($matches[$term])) continue;
                $distance=self::distance($token,$term);
                if ($distance<=$max) $matches[$term]=$distance===0 ? 6 : ($distance===1 ? 2 : 1);
            }
        }
        arsort($matches,SORT_NUMERIC);
        return array_slice($matches,0,128,true);
    }
    public function query(string $q,?int $categoryId,int $page=1): array {
        $page=max(1,$page);
        if (mb_strlen($q)>200) throw new UserError('Search is limited to 200 characters.');
        $tokens=self::queryTokens($q);
        $empty=['items'=>[],'total'=>0,'page'=>1,'pages'=>1];
        if (!$tokens) return $empty;
        $results=[];
        foreach($tokens as $token) {
            $matches=$this->matches($token);
            if (!$matches) continue;
            $case='CASE p.term';$args=[];
            foreach($matches as $term=>$factor) {$case.=' WHEN ? THEN ?';$args[]=(string)$term;$args[]=$factor;}
            $case.=' ELSE 0 END';
            $placeholders=implode(',',array_fill(0,count($matches),'?'));
            $sql="SELECT a.id,r.title,MAX(p.weight*($case)) AS score FROM search_postings p JOIN articles a ON a.id=p.article_id JOIN revisions r ON r.id=a.published_revision_id AND r.article_id=a.id WHERE a.trashed_at IS NULL AND p.term IN ($placeholders)";
            $args=array_merge($args,array_map('strval',array_keys($matches)));
            if($categoryId!==null){$sql.=' AND r.category_id=?';$args[]=$categoryId;}
            $sql.=' GROUP BY a.id,r.title';
            $stmt=$this->db->prepare($sql);$stmt->execute($args);
            foreach($stmt->fetchAll() as $r) {
                $id=(int)$r['id'];
                $results[$id]=['id'=>$id,'matched'=>($results[$id]['matched'] ?? 0)+1,'score'=>(int)$r['score']+($results[$id]['score'] ?? 0),'title'=>$r['title']];
            }
        }
        if (!$results) return $empty;
        foreach($results as &$r) if(mb_strtolower(trim($r['title']))===mb_strtolower(trim($q))) $r['score']+=1000;
        unset($r);
        uasort($results,fn($a,$b)=>($b['matched']<=>$a['matched']) ?: ($b['score']<=>$a['score']) ?: strcasecmp($a['title'],$b['title']) ?: ($a['id']<=>$b['id']));
        $total=count($results);$pages=max(1,(int)ceil($total/20));$page=min($page,$pages);
        $ids=array_keys(array_slice($results,($page-1)*20,20,true));
        if(!$ids)return $empty;
        $ph=implode(',',array_fill(0,count($ids),'?'));
        $stmt=$this->db->prepare("SELECT a.id,a.view_count,r.title,r.category_id,c.name AS category_name,LEFT(r.plain_text,300) AS plain_text,r.created_at AS updated_at FROM articles a JOIN revisions r ON r.id=a.published_revision_id AND r.article_id=a.id LEFT JOIN categories c ON c.id=r.category_id WHERE a.trashed_at IS NULL AND a.id IN ($ph)");
        $stmt->execute($ids);$byId=[];
        foreach($stmt->fetchAll() as $r){$r['id']=(int)$r['id'];$byId[$r['id']]=$r;}
        $items=[];foreach($ids as $id)if(isset($byId[$id]))$items[]=$byId[$id];
        return compact('items','total','page','pages');
    }
}
