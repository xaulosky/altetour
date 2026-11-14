<?php
declare(strict_types=1);
// Uso:
//   php backend/bin/export-news.php            imprime news.json (solo publicadas)
//   php backend/bin/export-news.php --write    escribe frontend/src/data/news.json y copia
//                                              las imágenes subidas a frontend/public/assets/news/
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/bootstrap.php';
use Alte\News;
$news=News::export();
$json=json_encode($news,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
if(!in_array('--write',$argv,true)){echo $json;exit;}
$frontend=dirname(__DIR__,2).'/frontend';
if(!is_dir($frontend.'/src/data'))throw new RuntimeException('No se encontró frontend/src/data junto al backend.');
$copied=0;$assets=$frontend.'/public/assets/news';
foreach($news as $n){
    $file=News::uploadedFile($n['image']);
    if(!$file)continue;
    if(!is_dir($assets)&&!mkdir($assets,0755,true))throw new RuntimeException('No se pudo crear '.$assets);
    if(!copy($file,$assets.'/'.basename($file)))throw new RuntimeException('No se pudo copiar '.basename($file));
    $copied++;
}
file_put_contents($frontend.'/src/data/news.json',$json);
fwrite(STDOUT,count($news)." noticias publicadas exportadas a frontend/src/data/news.json; $copied imágenes copiadas.\n");
