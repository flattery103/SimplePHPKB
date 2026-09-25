<?php
declare(strict_types=1);
final class Categories {
    public function __construct(private PDO $db) {}
    public function all(bool $counts=true): array {
        if (!$counts) return $this->db->query('SELECT id,name,image_id,image_mime,lock_version FROM categories ORDER BY name,id')->fetchAll();
        return $this->db->query('SELECT c.id,c.name,c.image_id,c.image_mime,c.lock_version,COUNT(a.id) AS article_count FROM categories c LEFT JOIN revisions r ON r.category_id=c.id LEFT JOIN articles a ON a.published_revision_id=r.id AND a.trashed_at IS NULL GROUP BY c.id,c.name,c.image_id,c.image_mime,c.lock_version ORDER BY c.name,c.id')->fetchAll();
    }
    public function save(string $name,?int $id,?array $file=null,bool $removeImage=false,?int $version=null): int {
        $name=trim($name);
        if (!mb_check_encoding($name,'UTF-8') || $name==='' || mb_strlen($name)>100) throw new UserError('Category name must be 1–100 characters.');
        $hasUpload=$file!==null && ($file['error'] ?? null)!==UPLOAD_ERR_NO_FILE;
        if($hasUpload && (($file['error'] ?? null)!==UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])))throw new UserError('Category image upload failed. Choose a PNG, JPEG, or WebP image no larger than 2 MB.');
        if($hasUpload && $removeImage)throw new UserError('Choose a new image or remove the current image, not both.');
        $newImage=null;
        try {
            return Database::transaction($this->db,function() use($name,$id,$file,$hasUpload,$removeImage,$version,&$newImage) {
                $row=null;
                if($id) {
                    $q=$this->db->prepare('SELECT * FROM categories WHERE id=? FOR UPDATE');$q->execute([$id]);$row=$q->fetch();
                    if(!$row)throw new UserError('Category not found.',404);
                    if($version!==null && (int)$row['lock_version']!==$version)throw new UserError('Another admin changed this category. Reload the page before saving again.',409);
                }
                if($hasUpload)$newImage=Media::writeImage($file['tmp_name'],2097152,4000000);
                $image=$newImage['id'] ?? ($removeImage ? null : ($row['image_id'] ?? null));
                $mime=$newImage['mime'] ?? ($removeImage ? null : ($row['image_mime'] ?? null));
                if($id)$this->db->prepare('UPDATE categories SET name=?,image_id=?,image_mime=?,lock_version=lock_version+1 WHERE id=?')->execute([$name,$image,$mime,$id]);
                else {$this->db->prepare('INSERT INTO categories (name,image_id,image_mime) VALUES (?,?,?)')->execute([$name,$image,$mime]);$id=(int)$this->db->lastInsertId();}
                return $id;
            });
        } catch(Throwable $e) {
            if($newImage && is_file($newImage['path']))unlink($newImage['path']);
            if($e instanceof PDOException && $e->getCode()==='23000')throw new UserError('That category already exists.');
            throw $e;
        }
    }
    public function image(int $id): array {
        global $config;
        $q=$this->db->prepare('SELECT image_id,image_mime FROM categories WHERE id=?');$q->execute([$id]);$row=$q->fetch();
        if(!$row)throw new UserError('Category not found.',404);
        if(is_string($row['image_id']) && preg_match('/^[a-f0-9]{32}$/D',$row['image_id']) && in_array($row['image_mime'],['image/png','image/jpeg','image/webp'],true)) {
            $path=$config['storage'].'/media/'.$row['image_id'];
            if(is_file($path))return ['path'=>$path,'mime'=>$row['image_mime']];
        }
        return ['path'=>__DIR__.'/../public/assets/category-default.svg','mime'=>'image/svg+xml'];
    }
    public function delete(int $id): void {
        Database::transaction($this->db,function() use ($id) {
            $q=$this->db->prepare('SELECT id FROM categories WHERE id=? FOR UPDATE');$q->execute([$id]); if (!$q->fetch()) throw new UserError('Category not found.',404);
            $q=$this->db->prepare('SELECT a.id FROM articles a LEFT JOIN revisions r ON r.id=a.published_revision_id WHERE a.draft_category_id=? OR r.category_id=? LIMIT 1');$q->execute([$id,$id]);
            if ($q->fetch()) throw new UserError('Move all draft and published articles out of this category first, including articles in Trash.');
            $this->db->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
        });
    }
}
