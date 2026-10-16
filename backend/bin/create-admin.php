<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/bootstrap.php';
$email=strtolower(trim($argv[1]??''));
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190){fwrite(STDERR,"Uso: php bin/create-admin.php tu-correo@dominio.cl\n");exit(1);}
// Nunca acepta la contraseña en la línea de comandos ni crea contraseñas por defecto.
$tty=function_exists('posix_isatty')&&posix_isatty(STDIN);
if($tty){fwrite(STDOUT,"Contraseña nueva (mínimo 12 caracteres): ");system('stty -echo');}
try{$password=rtrim(fgets(STDIN)?:'',"\r\n");}finally{if($tty){system('stty echo');echo "\n";}}
if(strlen($password)<12){fwrite(STDERR,"Se requieren al menos 12 caracteres.\n");exit(1);}
\Alte\store()->setAdmin($email,password_hash($password,PASSWORD_DEFAULT));
echo "Administrador creado o contraseña actualizada.\n";
