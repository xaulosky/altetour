<?php
declare(strict_types=1);
// Desarrollo: backend/public. cPanel: contenido de public en public_html,
// y el resto en /home/USUARIO/altetour-app (fuera del webroot).
$root=getenv('ALTE_APP_ROOT')?:dirname(__DIR__);
if(!is_file($root.'/src/bootstrap.php'))$root=dirname(__DIR__).'/altetour-app';
if(!is_file($root.'/src/bootstrap.php')){
    http_response_code(503);header('Content-Type: application/json; charset=utf-8');
    echo '{"message":"El backend no ha sido instalado. Contacta a Alte Tour por WhatsApp."}';exit;
}
require_once $root.'/src/bootstrap.php';
