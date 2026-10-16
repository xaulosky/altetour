<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$email=strtolower(trim($argv[1]??''));
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190){fwrite(STDERR,"Uso: php bin/make-install-sql.php admin@dominio.cl > instalacion-privada.sql\nSe leerá la contraseña por entrada estándar. Mantén el archivo resultante FUERA del directorio público.\n");exit(1);}
$tty=function_exists('posix_isatty')&&posix_isatty(STDIN);
if($tty){fwrite(STDERR,"Contraseña nueva (mínimo 12 caracteres): ");system('stty -echo');}
try{$password=rtrim(fgets(STDIN)?:'',"\r\n");}finally{if($tty){system('stty echo');fwrite(STDERR,"\n");}}
if(strlen($password)<12){fwrite(STDERR,"Contraseña demasiado corta.\n");exit(1);}
$root=dirname(__DIR__);
$sql=file_get_contents($root.'/config/schema.sql');
$literal=static fn(string $v):string=>'CONVERT(0x'.bin2hex($v).' USING utf8mb4)';
foreach(json_decode(file_get_contents($root.'/config/programs.seed.json'),true,512,JSON_THROW_ON_ERROR) as $p){
    $sql.="\nINSERT INTO programs (slug,data) VALUES (".$literal($p['slug']).','.$literal(json_encode($p,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)).") ON DUPLICATE KEY UPDATE slug=slug;\n";
}
$hash=$literal(password_hash($password,PASSWORD_DEFAULT));
$sql.="\nINSERT INTO admins (email,password_hash) VALUES (".$literal($email).",$hash) ON DUPLICATE KEY UPDATE password_hash=$hash;\n";
echo "-- CONTIENE CONFIGURACION DE ACCESO. IMPORTAR EN PHPMYADMIN Y ELIMINAR ESTE ARCHIVO.\n".$sql;
