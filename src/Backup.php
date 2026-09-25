<?php
declare(strict_types=1);
final class Backup {
    public static function create(PDO $db,string $target): void {
        global $config,$configFile;
        if(!class_exists('ZipArchive'))throw new UserError('Enable the PHP zip extension to create a backup.');
        $sql=$target.'.sql.tmp';$zipPath=$target.'.tmp';$out=fopen($sql,'wb');
        if(!$out)throw new UserError('Cannot write backup. Check storage permissions and free space.');
        $write=function(string $s)use($out){if(fwrite($out,$s)!==strlen($s))throw new RuntimeException('Backup disk is full.');};
        try {
            $tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            $write("-- SimplePHPKB ".KB_VERSION." backup ".gmdate('c')."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
            $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->beginTransaction();
            foreach($tables as $table) {
                if(!preg_match('/^[A-Za-z0-9_]+$/D',$table))throw new UserError('Unsupported database table name for backup.');
                $create=$db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
                $write("DROP TABLE IF EXISTS `$table`;\n$create;\n");
                $rows=$db->prepare("SELECT * FROM `$table`",[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>false]);$rows->execute();
                while($row=$rows->fetch(PDO::FETCH_ASSOC)) {
                    $columns=implode(',',array_map(fn($c)=>'`'.str_replace('`','``',$c).'`',array_keys($row)));
                    // Convert the hex literal to a byte string before MySQL coerces numeric columns.
                    $values=implode(',',array_map(fn($v)=>$v===null?'NULL':($v===''?"''":'CONVERT(0x'.bin2hex((string)$v).' USING binary)'),array_values($row)));
                    $write("INSERT INTO `$table` ($columns) VALUES ($values);\n");
                }
                $rows->closeCursor();
            }
            $db->commit();$write("SET FOREIGN_KEY_CHECKS=1;\n");fclose($out);$out=null;
            $zip=new ZipArchive();if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Cannot create backup ZIP.');
            try {
                $zip->addFile($sql,'database.sql');
                $root=dirname(__DIR__);
                foreach(['src','templates','database','public','vendor','bin'] as $dir)self::addTree($zip,$root.'/'.$dir,'SimplePHPKB/'.$dir);
                foreach(['README.md','LICENSE','composer.json','composer.lock'] as $file)if(is_file($root.'/'.$file))$zip->addFile($root.'/'.$file,'SimplePHPKB/'.$file);
                $zip->addFile($configFile ?: $root.'/config/config.php','SimplePHPKB/config/config.php');
                foreach(new FilesystemIterator($config['storage']) as $file) {
                    if($file->getFilename()==='updates'||$file->getFilename()==='lifecycle.lock'||$file->isLink())continue;
                    if($file->isDir())self::addTree($zip,$file->getPathname(),'SimplePHPKB/storage/'.$file->getFilename());
                    else $zip->addFile($file->getPathname(),'SimplePHPKB/storage/'.$file->getFilename());
                }
            } finally {if(!$zip->close())throw new RuntimeException('Backup ZIP could not be completed.');}
            chmod($zipPath,0600);if(!rename($zipPath,$target))throw new RuntimeException('Cannot finish backup.');
        } finally {
            if($db->inTransaction())$db->rollBack();if(is_resource($out))fclose($out);
            if(is_file($sql))unlink($sql);if(is_file($zipPath))unlink($zipPath);
        }
    }
    private static function addTree(ZipArchive $zip,string $directory,string $prefix): void {
        if(!is_dir($directory))return;
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,FilesystemIterator::SKIP_DOTS)) as $file) {
            if($file->isLink()||!$file->isFile())continue;
            if(!$zip->addFile($file->getPathname(),$prefix.'/'.substr($file->getPathname(),strlen($directory)+1)))throw new RuntimeException('Cannot add a file to backup.');
        }
    }
}
