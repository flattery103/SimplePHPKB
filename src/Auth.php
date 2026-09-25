<?php
declare(strict_types=1);
final class Auth {
    public function __construct(private PDO $db) {}
    public function login(string $username, string $password, string $ip): bool {
        if (strlen($username)>80 || strlen($password)>1024) return false;
        $buckets=[hash('sha256', 'user:'.mb_strtolower($username)), hash('sha256', 'ip:'.$ip)];
        foreach ($buckets as $i=>$key) {
            $s=$this->db->prepare('INSERT INTO login_attempts (bucket,attempts,window_start) VALUES (?,1,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE attempts=IF(window_start < UTC_TIMESTAMP()-INTERVAL 15 MINUTE,1,attempts+1), window_start=IF(window_start < UTC_TIMESTAMP()-INTERVAL 15 MINUTE,UTC_TIMESTAMP(),window_start)');
            $s->execute([$key]);
            $q=$this->db->prepare('SELECT attempts FROM login_attempts WHERE bucket=?'); $q->execute([$key]);
            if ((int)$q->fetchColumn() > ($i===0 ? 10 : 50)) return false;
        }
        $q=$this->db->prepare('SELECT * FROM admins WHERE username=?'); $q->execute([$username]); $admin=$q->fetch();
        // Constant valid fallback hash avoids a visibly cheap path for unknown users.
        $valid=password_verify($password, $admin['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$admin || !$valid || !(int)$admin['active']) return false;
        session_regenerate_id(true);
        $_SESSION=['admin_id'=>(int)$admin['id'],'session_version'=>(int)$admin['session_version'],'last_activity'=>time()];
        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) $this->db->prepare('UPDATE admins SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$admin['id']]);
        $this->db->prepare('DELETE FROM login_attempts WHERE bucket=?')->execute([$buckets[0]]);
        $this->db->exec('DELETE FROM login_attempts WHERE window_start < UTC_TIMESTAMP()-INTERVAL 1 DAY');
        return true;
    }
    public function current(): ?array {
        if (!isset($_SESSION['admin_id'])) return null;
        $q=$this->db->prepare('SELECT id,username,display_name,active,session_version FROM admins WHERE id=?'); $q->execute([$_SESSION['admin_id']]); $a=$q->fetch();
        if (!$a || !(int)$a['active'] || (int)$a['session_version']!==($_SESSION['session_version'] ?? 0) || time()-($_SESSION['last_activity'] ?? 0)>7200) {
            $this->logout(); return null;
        }
        $_SESSION['last_activity']=time(); $a['id']=(int)$a['id']; return $a;
    }
    public function logout(): void { $_SESSION=[]; if (session_status()===PHP_SESSION_ACTIVE) session_regenerate_id(true); }
    public function saveAdmin(array $data, ?int $id): int {
        return Database::transaction($this->db, function() use ($data,$id): int {
            // Lock in the same order for every update; protects the last account under concurrency.
            $admins=$this->db->query('SELECT * FROM admins ORDER BY id FOR UPDATE')->fetchAll();
            $old=null;
            foreach ($admins as $a) if ((int)$a['id']===$id) $old=$a;
            if ($id!==null && !$old) throw new UserError('Admin not found.',404);
            $username=trim($data['username'] ?? $old['username'] ?? '');
            $name=trim($data['display_name'] ?? $old['display_name'] ?? '');
            $active=(int)($data['active'] ?? $old['active'] ?? 1)===1 ? 1 : 0;
            $password=$data['password'] ?? '';
            if (!preg_match('/^[A-Za-z0-9_.@-]{3,80}$/D',$username)) throw new UserError('Username must be 3–80 letters, numbers, or . _ @ -');
            if ($name==='' || mb_strlen($name)>120) throw new UserError('Display name must be 1–120 characters.');
            if ((!$id || $password!=='') && (mb_strlen($password)<8 || strlen($password)>72 || str_contains($password,"\0"))) throw new UserError('Password must contain at least 8 characters and no more than 72 bytes, with no null characters.');
            $activeCount=count(array_filter($admins,fn($a)=>(int)$a['active']===1));
            if ($old && (int)$old['active']===1 && !$active && $activeCount<=1) throw new UserError('You cannot disable the last active admin.');
            foreach ($admins as $a) if ((int)$a['id']!==$id && strcasecmp($a['username'],$username)===0) throw new UserError('That username already exists.');
            $hash=$password!=='' ? password_hash($password,PASSWORD_DEFAULT) : ($old['password_hash'] ?? '');
            if ($id) {
                $revoke=($password!=='' || $active!==(int)$old['active']) ? 1 : 0;
                $this->db->prepare('UPDATE admins SET username=?,display_name=?,password_hash=?,active=?,session_version=session_version+? WHERE id=?')->execute([$username,$name,$hash,$active,$revoke,$id]);
            } else {
                $this->db->prepare('INSERT INTO admins (username,display_name,password_hash,active) VALUES (?,?,?,?)')->execute([$username,$name,$hash,$active]);
                $id=(int)$this->db->lastInsertId();
            }
            return $id;
        });
    }
    public function all(): array { return $this->db->query('SELECT id,username,display_name,active FROM admins ORDER BY username')->fetchAll(); }
}
