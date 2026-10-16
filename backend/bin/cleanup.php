<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/bootstrap.php';
// Limpia únicamente archivos técnicos. NO elimina consultas de clientes.
foreach(['rates'=>86400,'sessions'=>172800] as $sub=>$age){foreach(glob(\Alte\storage().'/'.$sub.'/*')?:[] as $f){if(is_file($f)&&filemtime($f)<time()-$age)@unlink($f);}}
echo "Archivos técnicos antiguos limpiados.\n";
