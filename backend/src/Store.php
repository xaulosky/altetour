<?php
declare(strict_types=1);
namespace Alte;
use PDO;
use RuntimeException;
use Throwable;

/** Dos adaptadores: MySQL para producción y JSON bloqueado para pruebas locales. */
final class Store {
    private ?PDO $pdo = null;
    private string $file;
    public function __construct(private array $config, private string $storage) {
        $this->file = $storage.'/data.json';
        if ($config['storage_driver'] === 'mysql') {
            $c=$config['mysql'];
            $this->pdo=new PDO('mysql:host='.$c['host'].';port='.(int)$c['port'].';dbname='.$c['database'].';charset=utf8mb4',$c['username'],$c['password'],[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES=>false,
            ]);
        } elseif ($config['storage_driver'] !== 'json') { throw new RuntimeException('Adaptador de almacenamiento inválido.'); }
        if ($config['environment']==='production' && $config['storage_driver']!=='mysql') {
            throw new RuntimeException('En producción debe configurarse MySQL.');
        }
    }
    public function isMysql():bool { return $this->pdo!==null; }
    private function json(callable $callback, bool $write=false):mixed {
        $lock=fopen($this->file.'.lock','c');
        if(!$lock || !flock($lock,$write?LOCK_EX:LOCK_SH))throw new RuntimeException('No se pudo bloquear almacenamiento.');
        try {
            $data=is_file($this->file)?json_decode(file_get_contents($this->file),true,512,JSON_THROW_ON_ERROR):['admins'=>[],'programs'=>[],'leads'=>[]];
            $result=$callback($data);
            if($write){
                $tmp=tempnam($this->storage,'alte-');
                if($tmp===false)throw new RuntimeException('No se pudo crear archivo temporal.');
                try {
                    chmod($tmp,0600);
                    if(file_put_contents($tmp,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))===false || !rename($tmp,$this->file))throw new RuntimeException('No se pudo guardar almacenamiento.');
                } finally { if(is_file($tmp))unlink($tmp); }
            }
            return $result;
        } finally {flock($lock,LOCK_UN);fclose($lock);}
    }
    public function install():void {
        if(!$this->pdo)return;
        $schema=file_get_contents(dirname(__DIR__).'/config/schema.sql');
        foreach(array_filter(array_map('trim',explode(';',$schema))) as $sql)$this->pdo->exec($sql);
    }
    public function admin(string $email):?array {
        if($this->pdo){$q=$this->pdo->prepare('SELECT * FROM admins WHERE email = ?');$q->execute([$email]);return $q->fetch()?:null;}
        return $this->json(fn($db)=>$db['admins'][$email]??null);
    }
    public function setAdmin(string $email,string $hash):void {
        if($this->pdo){$q=$this->pdo->prepare('INSERT INTO admins (email,password_hash) VALUES (?,?) ON DUPLICATE KEY UPDATE password_hash = ?');$q->execute([$email,$hash,$hash]);return;}
        $this->json(function(&$db)use($email,$hash){$db['admins'][$email]=['email'=>$email,'password_hash'=>$hash];},true);
    }
    public function programs():array {
        if($this->pdo){return array_map(fn($r)=>json_decode($r['data'],true,512,JSON_THROW_ON_ERROR),$this->pdo->query('SELECT data FROM programs ORDER BY slug')->fetchAll());}
        return $this->json(fn($db)=>array_values($db['programs']));
    }
    public function program(string $slug):?array {
        if($this->pdo){$q=$this->pdo->prepare('SELECT data FROM programs WHERE slug=?');$q->execute([$slug]);$r=$q->fetch();return $r?json_decode($r['data'],true,512,JSON_THROW_ON_ERROR):null;}
        return $this->json(fn($db)=>$db['programs'][$slug]??null);
    }
    public function putProgram(array $program):void {
        if($this->pdo){$json=json_encode($program,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$q=$this->pdo->prepare('INSERT INTO programs (slug,data) VALUES (?,?) ON DUPLICATE KEY UPDATE data=?,updated_at=UTC_TIMESTAMP()');$q->execute([$program['slug'],$json,$json]);return;}
        $this->json(function(&$db)use($program){$db['programs'][$program['slug']]=$program;},true);
    }
    public function lead(string $id):?array {
        if($this->pdo){$q=$this->pdo->prepare('SELECT * FROM leads WHERE id=?');$q->execute([$id]);$r=$q->fetch();if($r)$r['data']=json_decode($r['data'],true,512,JSON_THROW_ON_ERROR);return $r?:null;}
        return $this->json(fn($db)=>$db['leads'][$id]??null);
    }
    public function leads():array {
        if($this->pdo){$rows=$this->pdo->query('SELECT * FROM leads ORDER BY created_at DESC LIMIT 5000')->fetchAll();return array_map(function($r){$r['data']=json_decode($r['data'],true,512,JSON_THROW_ON_ERROR);return $r;},$rows);}
        return $this->json(function($db){$rows=array_values($db['leads']);usort($rows,fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));return array_slice($rows,0,5000);});
    }
    public function createLead(string $key,array $data):array {
        $fingerprint=hash('sha256',json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $id=bin2hex(random_bytes(16));$reference='AT-'.strtoupper(bin2hex(random_bytes(5)));$now=gmdate('Y-m-d H:i:s');
        $record=['id'=>$id,'reference'=>$reference,'idempotency_key'=>$key,'fingerprint'=>$fingerprint,'data'=>$data,'status'=>'nuevo','created_at'=>$now,'updated_at'=>$now];
        if($this->pdo){
            // La clave única resuelve envíos concurrentes sin crear duplicados.
            $q=$this->pdo->prepare('INSERT INTO leads (id,reference,idempotency_key,fingerprint,data,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id');
            $q->execute([$id,$reference,$key,$fingerprint,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'nuevo',$now,$now]);
            $q=$this->pdo->prepare('SELECT * FROM leads WHERE idempotency_key=?');$q->execute([$key]);$r=$q->fetch();
            if(!$r)throw new RuntimeException('No se pudo recuperar la consulta.');
            if(!hash_equals($r['fingerprint'],$fingerprint))throw new Problem(409,'La solicitud ya fue registrada con otros datos. Recarga el formulario para enviar una consulta distinta.');
            $r['data']=json_decode($r['data'],true,512,JSON_THROW_ON_ERROR);return $r;
        }
        return $this->json(function(&$db)use($key,$fingerprint,$record){
            foreach($db['leads'] as $old){if($old['idempotency_key']===$key){if(!hash_equals($old['fingerprint'],$fingerprint))throw new Problem(409,'La solicitud ya fue registrada con otros datos. Recarga el formulario.');return $old;}}
            $db['leads'][$record['id']]=$record;return $record;
        },true);
    }
    public function setStatus(string $id,string $status):void {
        if($this->pdo){$q=$this->pdo->prepare('UPDATE leads SET status=?,updated_at=UTC_TIMESTAMP() WHERE id=?');$q->execute([$status,$id]);return;}
        $this->json(function(&$db)use($id,$status){if(isset($db['leads'][$id])){$db['leads'][$id]['status']=$status;$db['leads'][$id]['updated_at']=gmdate('Y-m-d H:i:s');}},true);
    }
    public function deleteLead(string $id):void {
        if($this->pdo){$q=$this->pdo->prepare('DELETE FROM leads WHERE id=?');$q->execute([$id]);return;}
        $this->json(function(&$db)use($id){unset($db['leads'][$id]);},true);
    }
}
