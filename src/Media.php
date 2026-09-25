<?php
declare(strict_types=1);
final class Media {
    public function __construct(private PDO $db) {}
    public function upload(int $articleId,array $file,int $adminId): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) throw new UserError('Image upload failed. Choose a PNG, JPEG, or WebP image under 10 MB.');
        return $this->store($articleId,$file['tmp_name'],$adminId);
    }
    public function store(int $articleId,string $path,int $adminId): array {
        $q=$this->db->prepare('SELECT id FROM articles WHERE id=? AND trashed_at IS NULL'); $q->execute([$articleId]);
        if (!$q->fetch()) throw new UserError('Article not found.',404);
        $m=self::writeImage($path);
        try {
            $this->db->prepare('INSERT INTO media (id,article_id,mime,size_bytes,width,height,created_by) VALUES (?,?,?,?,?,?,?)')->execute([$m['id'],$articleId,$m['mime'],$m['size_bytes'],$m['width'],$m['height'],$adminId]);
        } catch(Throwable $e) { unlink($m['path']);throw $e; }
        return ['id'=>$m['id'],'location'=>route_url('media',['id'=>$m['id']])];
    }
    // Shared image decoding for article attachments, branding, and category images.
    public static function writeImage(string $path,int $maxBytes=10485760,int $maxPixels=20000000): array {
        global $config;
        $bytes=filesize($path);
        if (!$bytes || $bytes>$maxBytes) throw new UserError('Image must be no larger than '.($maxBytes/1048576).' MB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
        $info=@getimagesize($path);
        if (!in_array($mime,['image/png','image/jpeg','image/webp'],true) || !$info || $info['mime']!==$mime || $info[0]*$info[1]>$maxPixels) throw new UserError('Use a valid PNG, JPEG, or WebP image under '.($maxPixels/1000000).' megapixels.');
        $image=@imagecreatefromstring(file_get_contents($path));
        if (!$image) throw new UserError('This image could not be decoded.');
        $id=bin2hex(random_bytes(16)); $dir=$config['storage'].'/media';
        if (!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir)) throw new RuntimeException('Cannot create image directory.');
        $destination=$dir.'/'.$id;
        try {
            imagesavealpha($image,true);
            $written=match($mime) {'image/png'=>imagepng($image,$destination), 'image/jpeg'=>imagejpeg($image,$destination,92), 'image/webp'=>imagewebp($image,$destination,90)};
            if (!$written || !is_file($destination)) throw new RuntimeException('Image could not be stored.');
            $size=filesize($destination);
            if ($size>$maxBytes) throw new UserError('Processed image is too large. Resize it and try again.');
            chmod($destination,0660);
        } catch (Throwable $e) { if (is_file($destination)) unlink($destination); throw $e; }
        finally { unset($image); }
        return ['id'=>$id,'path'=>$destination,'mime'=>$mime,'size_bytes'=>$size,'width'=>$info[0],'height'=>$info[1]];
    }
    public function find(string $id,bool $admin): ?array {
        global $config;
        if (!preg_match('/^[a-f0-9]{32}$/D',$id)) return null;
        $sql='SELECT m.* FROM media m JOIN articles a ON a.id=m.article_id WHERE m.id=?';
        if (!$admin) $sql.=' AND a.trashed_at IS NULL AND EXISTS (SELECT 1 FROM revision_media rm WHERE rm.media_id=m.id AND rm.revision_id=a.published_revision_id)';
        $q=$this->db->prepare($sql);$q->execute([$id]);$m=$q->fetch();
        if (!$m) return null;
        $m['path']=$config['storage'].'/media/'.$m['id'];
        return is_file($m['path']) ? $m : null;
    }
}
