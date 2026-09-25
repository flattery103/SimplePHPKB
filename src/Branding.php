<?php
declare(strict_types=1);
final class Branding {
    public function __construct(private PDO $db) {}
    public function get(): array {
        global $config;
        $row=$this->db->query('SELECT * FROM site_settings WHERE id=1')->fetch();
        if(!$row)throw new RuntimeException('Branding settings missing. Run php bin/kb.php migrate.');
        $row['title']=$row['title'] ?? $config['site_name'] ?? 'SimplePHPKB';
        $row['subtitle']=$row['subtitle'] ?? 'Employee knowledge base';
        $row['lock_version']=(int)$row['lock_version'];
        $row['compact_home']=(int)$row['compact_home'];
        foreach(['header_link_enabled','header_link_new_window','banner_enabled'] as $key)$row[$key]=(int)($row[$key] ?? 0);
        foreach(['header_link_text','header_link_url','banner_text'] as $key)$row[$key]=$row[$key] ?? '';
        return $row;
    }
    private static function noticeSettings(array $data,array $current): array {
        $settings=[];
        foreach(['header_link_enabled','header_link_new_window','banner_enabled'] as $key) {
            $value=array_key_exists($key,$data) ? $data[$key] : ($current[$key] ?? 0);
            if(!in_array($value,[0,1,'0','1'],true))throw new UserError('Invalid header link or banner setting.',400);
            $settings[$key]=(int)$value;
        }
        foreach(['header_link_text'=>['Link text',80],'header_link_url'=>['Link URL',2048],'banner_text'=>['Banner message',1000]] as $key=>[$label,$limit]) {
            $value=array_key_exists($key,$data) ? $data[$key] : ($current[$key] ?? '');
            if(!is_string($value))throw new UserError('Invalid '.$label.'.',400);
            if(!mb_check_encoding($value,'UTF-8'))throw new UserError($label.' must be valid UTF-8 text.');
            $value=trim($value);
            if(mb_strlen($value)>$limit)throw new UserError($label.' must be no longer than '.$limit.' characters.');
            $settings[$key]=$value;
        }
        $url=$settings['header_link_url'];
        if($url!=='' && (!filter_var($url,FILTER_VALIDATE_URL) || str_contains($url,'\\') || preg_match('/[\x00-\x20\x7f]/',$url)
            || !in_array(strtolower(parse_url($url,PHP_URL_SCHEME) ?? ''),['http','https'],true)
            || parse_url($url,PHP_URL_USER)!==null || parse_url($url,PHP_URL_PASS)!==null)) {
            throw new UserError('Link URL must be a complete http:// or https:// address without embedded credentials.');
        }
        if($settings['header_link_enabled'] && $settings['header_link_text']==='')throw new UserError('Enter link text before enabling the header link.');
        if($settings['header_link_enabled'] && $url==='')throw new UserError('Enter a link URL before enabling the header link.');
        if($settings['banner_enabled'] && $settings['banner_text']==='')throw new UserError('Enter a banner message before enabling the known-issues banner.');
        return $settings;
    }
    public function save(array $data,int $version,?array $file=null,bool $removeLogo=false): void {
        $title=trim($data['title'] ?? '');$subtitle=trim($data['subtitle'] ?? '');
        if(!mb_check_encoding($title,'UTF-8') || $title==='' || mb_strlen($title)>120)throw new UserError('Site title must be 1–120 characters.');
        if(!mb_check_encoding($subtitle,'UTF-8') || mb_strlen($subtitle)>200)throw new UserError('Subtitle must be no longer than 200 characters.');
        $compact=$data['compact_home'] ?? null;
        if(!in_array($compact,[null,0,1,'0','1'],true))throw new UserError('Invalid homepage layout setting.',400);
        $hasUpload=$file!==null && ($file['error'] ?? null)!==UPLOAD_ERR_NO_FILE;
        if($hasUpload && (($file['error'] ?? null)!==UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])))throw new UserError('Logo upload failed. Choose a PNG, JPEG, or WebP image no larger than 2 MB.');
        if($hasUpload && $removeLogo)throw new UserError('Choose a new logo or remove the current logo, not both.');
        $newImage=null;
        try {
            Database::transaction($this->db,function() use($title,$subtitle,$compact,$data,$version,$file,$hasUpload,$removeLogo,&$newImage) {
                $row=$this->db->query('SELECT * FROM site_settings WHERE id=1 FOR UPDATE')->fetch();
                if(!$row || (int)$row['lock_version']!==$version)throw new UserError('Another admin changed the settings. Reload the page before saving again.',409);
                $notices=self::noticeSettings($data,$row);
                if($hasUpload)$newImage=Media::writeImage($file['tmp_name'],2097152,4000000);
                $logo=$newImage['id'] ?? ($removeLogo ? null : $row['logo_id']);
                $mime=$newImage['mime'] ?? ($removeLogo ? null : $row['logo_mime']);
                $this->db->prepare('UPDATE site_settings SET title=?,subtitle=?,logo_id=?,logo_mime=?,compact_home=?,header_link_enabled=?,header_link_text=?,header_link_url=?,header_link_new_window=?,banner_enabled=?,banner_text=?,lock_version=lock_version+1 WHERE id=1')->execute([$title,$subtitle,$logo,$mime,$compact ?? $row['compact_home'],$notices['header_link_enabled'],$notices['header_link_text'],$notices['header_link_url'],$notices['header_link_new_window'],$notices['banner_enabled'],$notices['banner_text']]);
            });
        } catch(Throwable $e) { if($newImage && is_file($newImage['path']))unlink($newImage['path']);throw $e; }
    }
    public function logo(): ?array {
        global $config;
        $row=$this->get();
        if(!is_string($row['logo_id']) || !preg_match('/^[a-f0-9]{32}$/D',$row['logo_id']) || !in_array($row['logo_mime'],['image/png','image/jpeg','image/webp'],true))return null;
        $path=$config['storage'].'/media/'.$row['logo_id'];
        return is_file($path) ? ['path'=>$path,'mime'=>$row['logo_mime']] : null;
    }
}
