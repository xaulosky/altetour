<?php
declare(strict_types=1);
// Carga noticias desde un news.json al almacenamiento del panel (MySQL o JSON).
// Sirve para llevar a producción noticias preparadas fuera del panel.
// Uso:
//   php backend/bin/import-news.php ruta/news.json             agrega solo las que no existen
//   php backend/bin/import-news.php ruta/news.json --replace   también reemplaza las existentes
// Nunca borra noticias. Las imágenes deben estar ya publicadas en /assets/.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/bootstrap.php';
use function Alte\store;
use Alte\News;

$file=$argv[1]??'';
if($file===''||!is_file($file)){fwrite(STDERR,"Uso: php bin/import-news.php ruta/news.json [--replace]\n");exit(1);}
$replace=in_array('--replace',$argv,true);
$items=json_decode((string)file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
if(!is_array($items)||!array_is_list($items))throw new RuntimeException('El archivo debe ser una lista de noticias.');
$added=$replaced=$skipped=0;
foreach($items as $n){
    $slug=$n['slug']??'';
    if(!is_string($slug)||!preg_match('/^[a-z0-9-]{1,90}$/D',$slug))throw new RuntimeException('Noticia con dirección inválida.');
    if(!isset(News::CATEGORIES[$n['category']??''],News::STATUSES[$n['status']??''])||empty($n['es']['title']))throw new RuntimeException("La noticia $slug no tiene categoría, estado o título válidos.");
    $exists=store()->newsItem($slug)!==null;
    if($exists&&!$replace){$skipped++;continue;}
    store()->putNews($n);
    $exists?$replaced++:$added++;
}
fwrite(STDOUT,"$added agregadas, $replaced reemplazadas, $skipped ya existían (sin cambios).\n");
