<?php
declare(strict_types=1);
final class Releases {
    public const REPOSITORY='https://github.com/flattery103/SimplePHPKB';
    private string $directory;
    public function __construct(string $storage,private ?Closure $fetcher=null) {
        $this->directory=$storage.'/updates';
        if(!is_dir($this->directory))@mkdir($this->directory,0700,true);
    }
    public function check(bool $force=false): array {
        $fallback=['status'=>'unavailable','checked_at'=>time(),'version'=>null];
        $file=$this->directory.'/release.json';
        $cached=is_file($file)?json_decode((string)file_get_contents($file),true):null;
        if(is_array($cached)&&time()-(int)($cached['checked_at']??0)<($force?60:21600))return $this->relative($cached);
        $lock=@fopen($this->directory.'/release.lock','c');
        if(!$lock)return $fallback;
        if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return is_array($cached)?$this->relative($cached):$fallback;}
        try {
            $response=$this->fetcher?($this->fetcher)():$this->fetch();
            $result=$fallback;
            if($response['status']===404)$result['status']='none';
            elseif($response['status']===200) {
                $data=json_decode($response['body'],true);
                if(is_array($data)&&empty($data['draft'])&&empty($data['prerelease'])&&is_string($data['tag_name']??null)&&preg_match('/^v?(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D',$data['tag_name'])) {
                    $result['version']=ltrim($data['tag_name'],'v');$result['status']='current';
                    $result['url']=self::REPOSITORY.'/releases/tag/'.rawurlencode($data['tag_name']);
                }
            }
            @file_put_contents($file,json_encode($result,JSON_THROW_ON_ERROR),LOCK_EX);
            return $this->relative($result);
        } catch(Throwable $e){@file_put_contents($file,json_encode($fallback),LOCK_EX);return $fallback;}
        finally {flock($lock,LOCK_UN);fclose($lock);}
    }
    private function relative(array $result): array {
        if(is_string($result['version']??null))$result['status']=version_compare($result['version'],KB_VERSION,'>')?'available':'current';
        return $result;
    }
    private function fetch(): array {
        if(!extension_loaded('curl'))return ['status'=>0,'body'=>''];
        $body='';$curl=curl_init('https://api.github.com/repos/flattery103/SimplePHPKB/releases/latest');
        curl_setopt_array($curl,[CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>4,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'SimplePHPKB/'.KB_VERSION,CURLOPT_HTTPHEADER=>['Accept: application/vnd.github+json','X-GitHub-Api-Version: 2022-11-28'],CURLOPT_WRITEFUNCTION=>function($curl,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>1048576)return 0;$body.=$chunk;return strlen($chunk);}]);
        $ok=curl_exec($curl);$status=$ok===false?0:(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
        return ['status'=>$status,'body'=>$body];
    }
}
