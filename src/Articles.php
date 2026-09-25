<?php
declare(strict_types=1);
final class Articles {
    private Html $html;
    private Search $search;
    public function __construct(private PDO $db) { $this->html=new Html($db);$this->search=new Search($db); }
    public function create(int $adminId): int {
        $category=$this->db->query('SELECT id FROM categories ORDER BY id LIMIT 1')->fetchColumn() ?: null;
        $this->db->prepare("INSERT INTO articles (draft_category_id,draft_html,created_by,updated_by) VALUES (?,'',?,?)")->execute([$category,$adminId,$adminId]);
        return (int)$this->db->lastInsertId();
    }
    public function get(int $id,bool $admin=false): ?array {
        if ($admin) {
            $q=$this->db->prepare('SELECT a.*,a.draft_title AS title,a.draft_category_id AS category_id,a.draft_keywords AS keywords,a.draft_html AS html,c.name AS category_name,u.display_name AS editor FROM articles a LEFT JOIN categories c ON c.id=a.draft_category_id JOIN admins u ON u.id=a.updated_by WHERE a.id=?');
        } else {
            $q=$this->db->prepare('SELECT a.id,a.view_count,a.lock_version,r.id AS revision_id,r.title,r.category_id,COALESCE(c.name,r.category_name) AS category_name,r.keywords,r.html,r.plain_text,r.created_at AS updated_at,u.display_name AS editor FROM articles a JOIN revisions r ON r.id=a.published_revision_id AND r.article_id=a.id LEFT JOIN categories c ON c.id=r.category_id JOIN admins u ON u.id=r.created_by WHERE a.id=? AND a.trashed_at IS NULL');
        }
        $q->execute([$id]);$a=$q->fetch();
        if ($a) { $a['id']=(int)$a['id'];$a['lock_version']=(int)$a['lock_version']; }
        return $a ?: null;
    }
    private function locked(int $id,int $version): array {
        $q=$this->db->prepare('SELECT * FROM articles WHERE id=? FOR UPDATE');$q->execute([$id]);$a=$q->fetch();
        if (!$a) throw new UserError('Article not found.',404);
        if ((int)$a['lock_version']!==$version) throw new UserError('Another admin changed this article. Your text is preserved below. Copy your changes, reload the latest version, and reapply them.',409);
        return $a;
    }
    public function save(int $id,array $data,int $expectedVersion,int $adminId,bool $publish): void {
        $title=trim($data['title'] ?? '');$keywords=trim($data['keywords'] ?? '');$category=(int)($data['category_id'] ?? 0);
        if ($title==='' || mb_strlen($title)>200) throw new UserError('Title must be 1–200 characters.');
        if (mb_strlen($keywords)>500) throw new UserError('Search keywords must be no longer than 500 characters.');
        $clean=$this->html->clean($data['html'] ?? '',$id);
        if ($publish && Html::text($clean)==='' && !$this->html->mediaIds($clean)) throw new UserError('Add some article content before publishing.');
        Database::transaction($this->db,function() use($id,$expectedVersion,$adminId,$publish,$title,$keywords,$category,$clean) {
            $a=$this->locked($id,$expectedVersion);
            if ($a['trashed_at']) throw new UserError('Restore this article from Trash before editing it.');
            $q=$this->db->prepare('SELECT name FROM categories WHERE id=? LOCK IN SHARE MODE');$q->execute([$category]);$categoryName=$q->fetchColumn();
            if (!$categoryName) throw new UserError('Choose an existing category.');
            $this->db->prepare('UPDATE articles SET draft_title=?,draft_category_id=?,draft_keywords=?,draft_html=?,lock_version=lock_version+1,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$title,$category,$keywords,$clean,$adminId,$id]);
            if ($publish) {
                $text=Html::text($clean);
                $this->db->prepare('INSERT INTO revisions (article_id,title,category_id,category_name,keywords,html,plain_text,created_by) VALUES (?,?,?,?,?,?,?,?)')->execute([$id,$title,$category,$categoryName,$keywords,$clean,$text,$adminId]);
                $rev=(int)$this->db->lastInsertId();
                foreach ($this->html->mediaIds($clean) as $mediaId) $this->db->prepare('INSERT INTO revision_media (revision_id,media_id) VALUES (?,?)')->execute([$rev,$mediaId]);
                $this->db->prepare('UPDATE articles SET published_revision_id=? WHERE id=?')->execute([$rev,$id]);
                $this->search->index($id,['title'=>$title,'keywords'=>$keywords,'plain_text'=>$text]);
            }
        });
    }
    public function trash(int $id,int $version): void {
        Database::transaction($this->db,function() use($id,$version) {
            $this->locked($id,$version);
            $this->db->prepare('UPDATE articles SET trashed_at=UTC_TIMESTAMP(),published_revision_id=NULL,lock_version=lock_version+1 WHERE id=?')->execute([$id]);
            $this->search->index($id,null);
        });
    }
    public function setFeatured(int $id,bool $featured,int $version): void {
        Database::transaction($this->db,function() use($id,$featured,$version) {
            $a=$this->locked($id,$version);
            if($a['trashed_at'])throw new UserError('Restore this article from Trash before featuring it.');
            $this->db->prepare('UPDATE articles SET featured=?,lock_version=lock_version+1 WHERE id=?')->execute([$featured ? 1 : 0,$id]);
        });
    }
    public function unpublish(int $id,int $version): void {
        Database::transaction($this->db,function() use($id,$version) {
            $this->locked($id,$version);
            $this->db->prepare('UPDATE articles SET published_revision_id=NULL,lock_version=lock_version+1 WHERE id=?')->execute([$id]);
            $this->search->index($id,null);
        });
    }
    public function restore(int $id,int $version): void {
        Database::transaction($this->db,function() use($id,$version) {
            $a=$this->locked($id,$version);
            if (!$a['trashed_at']) throw new UserError('This article is not in Trash.');
            $this->db->prepare('UPDATE articles SET trashed_at=NULL,published_revision_id=NULL,lock_version=lock_version+1 WHERE id=?')->execute([$id]);
            $this->search->index($id,null);
        });
    }
    public function restoreRevision(int $id,int $revisionId,int $version,int $adminId): void {
        Database::transaction($this->db,function() use($id,$revisionId,$version,$adminId) {
            $a=$this->locked($id,$version);
            if ($a['trashed_at']) throw new UserError('Restore this article from Trash first.');
            $q=$this->db->prepare('SELECT * FROM revisions WHERE id=? AND article_id=?');$q->execute([$revisionId,$id]);$r=$q->fetch();
            if (!$r) throw new UserError('Revision not found.',404);
            $this->db->prepare('UPDATE articles SET draft_title=?,draft_category_id=?,draft_keywords=?,draft_html=?,updated_by=?,updated_at=UTC_TIMESTAMP(),lock_version=lock_version+1 WHERE id=?')->execute([$r['title'],$r['category_id'],$r['keywords'],$this->html->clean($r['html'],$id),$adminId,$id]);
        });
    }
    public function history(int $id): array {
        $q=$this->db->prepare('SELECT r.*,u.display_name AS editor FROM revisions r JOIN admins u ON u.id=r.created_by WHERE r.article_id=? ORDER BY r.id DESC');$q->execute([$id]);return $q->fetchAll();
    }
    public function adminList(string $status,string $q='',int $page=1): array {
        $sql='SELECT a.id,a.featured,a.draft_title AS title,a.published_revision_id,a.lock_version,a.trashed_at,a.updated_at,c.name AS category_name,u.display_name AS editor FROM articles a LEFT JOIN categories c ON c.id=a.draft_category_id JOIN admins u ON u.id=a.updated_by WHERE ';
        $sql.=$status==='trash' ? 'a.trashed_at IS NOT NULL' : 'a.trashed_at IS NULL';
        if ($status==='draft') $sql.=' AND a.published_revision_id IS NULL';
        if ($status==='published') $sql.=' AND a.published_revision_id IS NOT NULL';
        if ($q!=='') $sql.=' AND a.draft_title LIKE ?';
        $args=$q!=='' ? ['%'.$q.'%'] : [];
        $countSql='SELECT COUNT(*) FROM ('.$sql.') counted';$s=$this->db->prepare($countSql);$s->execute($args);$total=(int)$s->fetchColumn();
        $pages=max(1,(int)ceil($total/20));$page=min(max(1,$page),$pages);$offset=($page-1)*20;
        $sql.=' ORDER BY a.updated_at DESC,a.id DESC LIMIT 20 OFFSET '.$offset;
        $s=$this->db->prepare($sql);$s->execute($args);$items=$s->fetchAll();return compact('items','total','page','pages');
    }

    public function listing(?int $categoryId,int $page,bool $popular=false): array {
        $where='a.trashed_at IS NULL';$args=[];
        if($categoryId!==null){$where.=' AND r.category_id=?';$args[]=$categoryId;}
        $from=' FROM articles a JOIN revisions r ON r.id=a.published_revision_id AND r.article_id=a.id LEFT JOIN categories c ON c.id=r.category_id WHERE '.$where;
        $q=$this->db->prepare('SELECT COUNT(*)'.$from);$q->execute($args);$total=(int)$q->fetchColumn();
        $limit=$popular ? 10 : 20;$pages=$popular ? 1 : max(1,(int)ceil($total/$limit));$page=min(max(1,$page),$pages);$offset=($page-1)*$limit;
        $order=$popular ? 'a.featured DESC,a.view_count DESC,r.created_at DESC,a.id DESC' : 'r.title,a.id';
        $q=$this->db->prepare('SELECT a.id,a.featured,a.view_count,r.title,r.category_id,c.name AS category_name,LEFT(r.plain_text,300) AS plain_text,r.created_at AS updated_at'.$from." ORDER BY $order LIMIT $limit OFFSET $offset");
        $q->execute($args);$items=$q->fetchAll();foreach($items as &$r)$r['id']=(int)$r['id'];unset($r);
        return compact('items','total','page','pages');
    }
    public function recordView(int $id,string $sessionKey,string $agent,bool $admin): void {
        if($admin || $sessionKey==='' || $agent==='' || preg_match('/bot|crawler|spider|slurp|preview|headless/i',$agent))return;
        Database::transaction($this->db,function()use($id,$sessionKey) {
            $q=$this->db->prepare('SELECT id FROM articles WHERE id=? AND published_revision_id IS NOT NULL AND trashed_at IS NULL FOR UPDATE');$q->execute([$id]);if(!$q->fetch())return;
            $q=$this->db->prepare('INSERT IGNORE INTO article_views (article_id,session_hash,view_day) VALUES (?,?,UTC_DATE())');$q->execute([$id,hash('sha256',$sessionKey)]);
            if($q->rowCount()===1)$this->db->prepare('UPDATE articles SET view_count=view_count+1 WHERE id=?')->execute([$id]);
            // Only today's records are needed for deduplication; keep yesterday for slow requests.
            $this->db->exec('DELETE FROM article_views WHERE view_day < UTC_DATE()-INTERVAL 1 DAY LIMIT 100');
        });
    }
}
