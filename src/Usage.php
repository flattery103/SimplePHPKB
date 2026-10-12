<?php
declare(strict_types=1);
final class Usage {
    public const PERIODS=['today'=>'Today','7'=>'Last 7 days','30'=>'Last 30 days','all'=>'All time'];

    public function __construct(private PDO $db) {}

    private static function eligible(string $sessionKey,string $agent,bool $admin): bool {
        return !$admin && $sessionKey!=='' && $agent!=='' && !preg_match('/bot|crawler|spider|slurp|preview|headless/i',$agent);
    }

    public function recordSiteVisit(string $sessionKey,string $agent,bool $admin): void {
        if(!self::eligible($sessionKey,$agent,$admin))return;
        Database::transaction($this->db,function()use($sessionKey) {
            // Use one date for the whole transaction, including requests crossing midnight.
            $day=$this->db->query('SELECT UTC_DATE()')->fetchColumn();
            $q=$this->db->prepare('INSERT IGNORE INTO site_visits (session_hash,view_day) VALUES (?,?)');
            $q->execute([hash('sha256',$sessionKey),$day]);
            if($q->rowCount()===1) {
                $this->db->prepare('INSERT INTO site_daily (view_day,visit_count) VALUES (?,1) ON DUPLICATE KEY UPDATE visit_count=visit_count+1')->execute([$day]);
            }
            $this->db->prepare('DELETE FROM site_visits WHERE view_day < DATE_SUB(?,INTERVAL 1 DAY) LIMIT 100')->execute([$day]);
        });
    }

    public function recordArticleView(int $id,string $sessionKey,string $agent,bool $admin): void {
        if(!self::eligible($sessionKey,$agent,$admin))return;
        Database::transaction($this->db,function()use($id,$sessionKey) {
            $q=$this->db->prepare('SELECT id FROM articles WHERE id=? AND published_revision_id IS NOT NULL AND trashed_at IS NULL FOR UPDATE');
            $q->execute([$id]);if(!$q->fetch())return;
            $day=$this->db->query('SELECT UTC_DATE()')->fetchColumn();
            $q=$this->db->prepare('INSERT IGNORE INTO article_views (article_id,session_hash,view_day) VALUES (?,?,?)');
            $q->execute([$id,hash('sha256',$sessionKey),$day]);
            if($q->rowCount()===1) {
                $this->db->prepare('UPDATE articles SET view_count=view_count+1 WHERE id=?')->execute([$id]);
                $this->db->prepare('INSERT INTO article_daily (article_id,view_day,view_count) VALUES (?,?,1) ON DUPLICATE KEY UPDATE view_count=view_count+1')->execute([$id,$day]);
            }
            // Short-lived deduplication keys; aggregate daily counts are retained.
            $this->db->prepare('DELETE FROM article_views WHERE view_day < DATE_SUB(?,INTERVAL 1 DAY) LIMIT 100')->execute([$day]);
        });
    }

    public function report(string $period='30',string $search='',int $page=1): array {
        if(!array_key_exists($period,self::PERIODS))throw new UserError('Choose a valid reporting period.',400);
        if(mb_strlen($search)>200)throw new UserError('Search is limited to 200 characters.');
        $end=(string)$this->db->query('SELECT UTC_DATE()')->fetchColumn();
        $start=$period==='all' ? null : (new DateTimeImmutable($end,new DateTimeZone('UTC')))->modify('-'.($period==='today' ? 0 : (int)$period-1).' days')->format('Y-m-d');
        $dateSql='view_day <= ?';$dateArgs=[$end];
        if($start!==null){$dateSql.=' AND view_day >= ?';$dateArgs[]=$start;}
        $q=$this->db->prepare('SELECT COALESCE(SUM(visit_count),0) FROM site_daily WHERE '.$dateSql);
        $q->execute($dateArgs);$visits=(int)$q->fetchColumn();

        $join='';$args=[];$views='a.view_count';
        if($period!=='all') {
            $join=' LEFT JOIN (SELECT article_id,SUM(view_count) AS views FROM article_daily WHERE '.$dateSql.' GROUP BY article_id) d ON d.article_id=a.id';
            $args=$dateArgs;$views='COALESCE(d.views,0)';
        }
        $q=$this->db->prepare('SELECT COALESCE(SUM('.$views.'),0) AS article_views,COALESCE(SUM('.$views.'>0),0) AS articles_read FROM articles a'.$join);
        $q->execute($args);$totals=$q->fetch();

        $title="COALESCE(r.title,NULLIF(a.draft_title,''),'Untitled article')";
        $from=' FROM articles a LEFT JOIN revisions r ON r.id=a.published_revision_id AND r.article_id=a.id LEFT JOIN categories c ON c.id=COALESCE(r.category_id,a.draft_category_id)';
        $where='';$filter=[];
        if($search!==''){$where=' WHERE '.$title.' LIKE ?';$filter[]='%'.$search.'%';}
        $q=$this->db->prepare('SELECT COUNT(*)'.$from.$where);$q->execute($filter);$total=(int)$q->fetchColumn();
        $pages=max(1,(int)ceil($total/20));$page=min(max(1,$page),$pages);$offset=($page-1)*20;
        $q=$this->db->prepare('SELECT a.id,'.$title.' AS title,c.name AS category_name,a.published_revision_id,a.trashed_at,a.view_count AS lifetime_views,'.$views.' AS views'.$from.$join.$where.' ORDER BY views DESC,a.view_count DESC,title,a.id LIMIT 20 OFFSET '.$offset);
        $q->execute(array_merge($args,$filter));$items=$q->fetchAll();
        foreach($items as &$item)foreach(['id','views','lifetime_views'] as $key)$item[$key]=(int)$item[$key];
        unset($item);
        return ['period'=>$period,'start'=>$start,'end'=>$end,'started_at'=>(string)$this->db->query('SELECT started_at FROM usage_settings WHERE id=1')->fetchColumn(),
            'site_visits'=>$visits,'article_views'=>(int)$totals['article_views'],'articles_read'=>(int)$totals['articles_read'],
            'items'=>$items,'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
}
