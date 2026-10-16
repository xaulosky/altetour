<?php
declare(strict_types=1);
namespace Alte;
use RuntimeException;
use Throwable;
final class Problem extends RuntimeException {
    public function __construct(public readonly int $status,string $message){parent::__construct($message);}
}
function escape(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function config():array {
    static $config;
    if($config!==null)return $config;
    $path=dirname(__DIR__).'/config/config.local.php';
    if(!is_file($path))throw new Problem(503,'El formulario todavía no está configurado. Puedes contactar a Alte Tour por WhatsApp.');
    $config=require $path;
    if(!is_array($config)||strlen($config['app_key']??'')<40||str_starts_with($config['app_key']??'','REEMPLAZAR'))throw new Problem(503,'El sistema requiere configuración.');
    return $config;
}
function storage():string {
    $p=dirname(__DIR__).'/storage';
    if(!is_dir($p)&&!mkdir($p,0700,true))throw new RuntimeException('No se pudo crear almacenamiento privado.');
    $real=realpath($p);$doc=realpath($_SERVER['DOCUMENT_ROOT']??'/does-not-exist');
    if(PHP_SAPI!=='cli' && $doc && ($real===$doc||str_starts_with($real,$doc.DIRECTORY_SEPARATOR)))throw new Problem(503,'Debe configurarse almacenamiento fuera del directorio público.');
    return $p;
}
require_once __DIR__.'/Store.php';
function store():Store {static $store;return $store??=new Store(config(),storage());}
function beginSession():void {
    if(session_status()===PHP_SESSION_ACTIVE)return;
    $dir=storage().'/sessions';if(!is_dir($dir))mkdir($dir,0700,true);
    session_save_path($dir);
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
    session_name('alte_session');
    $secure=config()['environment']==='production'||($_SERVER['HTTPS']??'')==='on';
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
    if(!isset($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));
}
function headersPrivate(bool $html=false):void {
    header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: same-origin');header('X-Robots-Tag: noindex, nofollow');
    if($html)header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
}
function csrfCheck():void {
    $token=$_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['_csrf']??'';
    if(!is_string($token)||!hash_equals($_SESSION['csrf']??'',$token))throw new Problem(419,'Tu sesión de formulario expiró. Recarga la página y vuelve a intentar.');
}
function rateLimit(string $scope,int $max,int $seconds):void {
    // No se guardan direcciones IP en texto claro. No confiar en cabeceras proxy sin configurar.
    $bucket=hash_hmac('sha256',$scope.'|'.($_SERVER['REMOTE_ADDR']??'local'),config()['app_key']);
    $dir=storage().'/rates';if(!is_dir($dir))mkdir($dir,0700,true);
    $path=$dir.'/'.$bucket.'.json';$f=fopen($path,'c+');
    if(!$f||!flock($f,LOCK_EX))throw new RuntimeException('No se pudo limitar solicitudes.');
    try {
        $raw=stream_get_contents($f);$v=$raw?json_decode($raw,true):null;$now=time();
        if(!is_array($v)||$now-($v['start']??0)>=$seconds)$v=['start'=>$now,'count'=>0];
        if($v['count']>=$max){header('Retry-After: '.max(1,$seconds-($now-$v['start'])));throw new Problem(429,'Has realizado varios intentos. Espera unos minutos o contacta a Alte Tour por WhatsApp.');}
        $v['count']++;rewind($f);ftruncate($f,0);fwrite($f,json_encode($v));fflush($f);
    } finally {flock($f,LOCK_UN);fclose($f);}
}
function jsonResponse(array $body,int $status=200):never {
    http_response_code($status);header('Content-Type: application/json; charset=utf-8');headersPrivate();echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
}
function validLead(array $input):array {
    $str=static function(string $key,int $max,bool $required=false)use($input):string{
        $v=$input[$key]??'';if(!is_string($v))throw new Problem(422,'Revisa el campo '.$key.'.');
        $v=trim($v);if(($required&&strlen($v)<2)||strlen($v)>$max)throw new Problem(422,'Revisa el campo '.$key.'.');return $v;
    };
    $name=$str('name',240,true);$email=strtolower($str('email',190,true));$phone=$str('phone',30,true);
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new Problem(422,'Ingresa un correo electrónico válido.');
    if(!preg_match('/^\+?[0-9().\s-]{8,30}$/D',$phone)||!preg_match('/^[0-9]{8,15}$/D',preg_replace('/\D/','',$phone)))throw new Problem(422,'Ingresa un teléfono válido, incluyendo el código de país.');
    if(($input['consent']??null)!==true)throw new Problem(422,'Debes autorizar el uso de tus datos para responder la consulta.');
    $travelers=filter_var($input['travelers']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>50]]);
    if($travelers===false)throw new Problem(422,'Selecciona una cantidad válida de viajeros.');
    $interest=$str('interest',30,true);if(!in_array($interest,['turquia','viaje','misiones','receptivo'],true))throw new Problem(422,'Selecciona un tipo de consulta válido.');
    $room=$str('room',20,true);if(!in_array($room,['single','double','triple','unsure'],true))throw new Problem(422,'Selecciona una habitación válida.');
    $program=$str('program',100);if($program!==''&&(!preg_match('/^[a-z0-9-]+$/D',$program)||!($p=store()->program($program))||$p['status']!=='published'))throw new Problem(422,'Este programa no está disponible para consultas desde el formulario. Contacta a Alte Tour.');
    $utm=[];
    if(isset($input['utm'])&&is_array($input['utm']))foreach(['utm_source','utm_medium','utm_campaign'] as $k){$value=$input['utm'][$k]??'';if(is_string($value)&&$value!=='')$utm[$k]=substr($value,0,150);}
    return ['name'=>$name,'email'=>$email,'phone'=>$phone,'interest'=>$interest,'travelers'=>$travelers,'room'=>$room,'program'=>$program,'message'=>$str('message',5000),'source'=>$str('source',250),'utm'=>$utm,'consent'=>true,'consent_version'=>'contacto-v1'];
}
function requireAdmin():void {
    if(empty($_SESSION['admin'])){header('Location: /admin/?view=login');exit;}
    if((time()-($_SESSION['last_activity']??0))>1800 || (time()-($_SESSION['login_at']??0))>28800){
        $_SESSION=[];session_regenerate_id(true);header('Location: /admin/?view=login');exit;
    }
    $_SESSION['last_activity']=time();
}
