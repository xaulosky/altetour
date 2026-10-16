<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);$config=$root.'/config/config.local.php';
if(!is_file($config)){
    if(!in_array('--demo',$_SERVER['argv'],true)){fwrite(STDERR,"Copia config/config.example.php a config/config.local.php y configura MySQL, o ejecuta --demo para desarrollo privado.\n");exit(1);}
    $settings=['environment'=>'development','storage_driver'=>'json','app_key'=>bin2hex(random_bytes(32)),'mysql'=>[]];
    file_put_contents($config,"<?php\nreturn ".var_export($settings,true).";\n");chmod($config,0600);
}
require $root.'/src/bootstrap.php';
try {
    \Alte\store()->install();
    $seed=json_decode(file_get_contents($root.'/config/programs.seed.json'),true,512,JSON_THROW_ON_ERROR);
    foreach($seed as $p)if(!\Alte\store()->program($p['slug']))\Alte\store()->putProgram($p);
    echo "Almacenamiento instalado. No se reemplazaron programas existentes. Crea un administrador con bin/create-admin.php.\n";
}catch(Throwable $e){fwrite(STDERR,"Instalación fallida: ".$e->getMessage()."\n");exit(1);}
