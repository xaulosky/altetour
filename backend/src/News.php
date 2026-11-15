<?php
declare(strict_types=1);
namespace Alte;
use RuntimeException;

/**
 * Noticias del sitio. Se editan en el panel y se publican exportando news.json
 * hacia frontend/src/data, igual que los programas. Español es obligatorio;
 * inglés y portugués son opcionales y, si faltan, el sitio usa el español.
 */
final class News {
    public const CATEGORIES=['viajes'=>'Viajes','actividades'=>'Actividades','eventos'=>'Eventos','agencia'=>'Agencia'];
    public const LANGS=['es'=>'Español','en'=>'Inglés','pt'=>'Portugués'];
    public const STATUSES=['published'=>'Publicada en el próximo build','draft'=>'Borrador'];
    /** Imágenes que ya existen en el sitio (frontend/public/assets). */
    public const LIBRARY=[
        '/assets/patagonia.webp'=>'Patagonia','/assets/biobio.webp'=>'Biobío','/assets/valparaiso.webp'=>'Valparaíso','/assets/atacama.webp'=>'Atacama',
        '/assets/capadocia.webp'=>'Capadocia','/assets/estambul.webp'=>'Estambul','/assets/galata.webp'=>'Torre de Gálata','/assets/pamukkale.webp'=>'Pamukkale','/assets/izmir.webp'=>'Izmir / Egeo',
        '/assets/viajeros.webp'=>'Viajeros','/assets/misiones.webp'=>'Misiones comerciales','/assets/planificacion.webp'=>'Planificación','/assets/teresa.webp'=>'Retrato de Teresa Alarcón',
    ];
    private const UPLOAD_TYPES=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    /** Fotos de galería por noticia. */
    public const GALLERY_MAX=12;

    public static function imageDir():string {
        $dir=storage().'/news-images';
        if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('No se pudo crear la carpeta de imágenes de noticias.');
        return $dir;
    }
    /** Ruta de un archivo subido a partir de su valor público /assets/news/<archivo>. */
    public static function uploadedFile(string $image):?string {
        if(!preg_match('#^/assets/news/([a-f0-9]{32}(?:-sm)?\.(?:jpg|png|webp))$#D',$image,$m))return null;
        $path=self::imageDir().'/'.$m[1];
        return is_file($path)?$path:null;
    }
    public static function maxUploadBytes():int {
        $ini=static function(string $key):int{$v=trim((string)ini_get($key));$n=(int)$v;return match(strtolower(substr($v,-1))){'g'=>$n*1073741824,'m'=>$n*1048576,'k'=>$n*1024,default=>$n};};
        return max(1,min(5*1048576,$ini('upload_max_filesize')?:PHP_INT_MAX,$ini('post_max_size')?:PHP_INT_MAX));
    }
    public static function slugify(string $text):string {
        $text=strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/','-',(string)iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$text)),'-'));
        return substr($text,0,90)?:'noticia';
    }

    /** Valida el formulario del panel y devuelve la noticia lista para guardar. */
    public static function fromForm(array $post,array $files,?array $existing):array {
        $str=static function(string $key,int $max,int $min=0)use($post):string{
            $v=$post[$key]??'';if(!is_string($v))throw new Problem(422,'Revisa el campo '.$key.'.');
            $v=str_replace("\r\n","\n",trim($v));
            if(mb_strlen($v)>$max||($min>0&&mb_strlen($v)<$min))throw new Problem(422,'Revisa el largo del campo '.$key.'.');
            return $v;
        };
        $category=$str('category',30);if(!isset(self::CATEGORIES[$category]))throw new Problem(422,'Elige una categoría válida.');
        $status=$str('status',20);if(!isset(self::STATUSES[$status]))throw new Problem(422,'Elige un estado válido.');
        $date=$str('date',10);$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$d||$d->format('Y-m-d')!==$date)throw new Problem(422,'Ingresa una fecha válida.');
        $item=['slug'=>$existing['slug']??'','category'=>$category,'status'=>$status,'date'=>$date,'image'=>$existing['image']??'','linkUrl'=>''];
        $link=$str('linkUrl',500);
        if($link!==''){
            $internal=preg_match('#^/[a-z0-9/_-]*$#D',$link)===1;
            $externalOk=filter_var($link,FILTER_VALIDATE_URL)&&preg_match('#^https?://#i',$link);
            if(!$internal&&!$externalOk)throw new Problem(422,'El enlace debe ser una dirección https:// o una ruta del sitio como /viajes/.');
            $item['linkUrl']=$link;
        }
        foreach(array_keys(self::LANGS) as $lang){
            $required=$lang==='es';
            $block=[
                'title'=>$str($lang.'_title',160,$required?3:0),
                'summary'=>$str($lang.'_summary',400,$required?10:0),
                'body'=>$str($lang.'_body',20000,$required?20:0),
                'imageAlt'=>$str($lang.'_imageAlt',200),
                'linkLabel'=>$str($lang.'_linkLabel',80),
            ];
            if(!$required&&$block['title']===''&&$block['summary']===''&&$block['body']==='')continue;
            if(!$required&&($block['title']===''||$block['summary']===''||$block['body']===''))throw new Problem(422,'Para '.self::LANGS[$lang].' completa título, resumen y texto, o deja los tres vacíos.');
            $item[$lang]=$block;
        }
        if($item['linkUrl']!==''&&$item['es']['linkLabel']==='')$item['es']['linkLabel']='Más información';
        if($item['slug']===''){
            $base=self::slugify($str('slug',90)?:$item['es']['title']);$slug=$base;$n=2;
            while(store()->newsItem($slug))$slug=$base.'-'.$n++;
            $item['slug']=$slug;
        }
        // Imagen: archivo subido, imagen de la biblioteca o la que ya tenía.
        $upload=$files['image_upload']??null;
        if(is_array($upload)&&($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
            $item['image']=self::storeUpload($upload);
        }else{
            $choice=$str('image',200);
            if($choice!==''&&$choice!=='__keep'){
                if(!isset(self::LIBRARY[$choice]))throw new Problem(422,'Elige una imagen válida.');
                $item['image']=$choice;
            }
        }
        if($item['image']==='')$item['image']='/assets/biobio.webp';
        // Galería: conserva las fotos que no se marcaron para quitar y agrega las subidas.
        $remove=is_array($post['gallery_remove']??null)?array_filter($post['gallery_remove'],'is_string'):[];
        $gallery=array_values(array_filter(self::gallery($existing),fn($g)=>!in_array($g['src'],$remove,true)));
        foreach(self::multiUpload($files['gallery_upload']??null) as $upload){
            if(count($gallery)>=self::GALLERY_MAX)throw new Problem(422,'La galería admite hasta '.self::GALLERY_MAX.' fotos. Quita alguna antes de subir más.');
            $gallery[]=self::storeUpload($upload,true);
        }
        $item['gallery']=$gallery;
        return $item;
    }
    /** Galería normalizada de una noticia: lista de ['src'=>…, 'thumb'=>…]. */
    public static function gallery(?array $item):array {
        $out=[];
        foreach(($item['gallery']??[]) as $g){
            if(is_string($g))$g=['src'=>$g];
            if(!is_array($g)||!is_string($g['src']??null)||$g['src']==='')continue;
            $out[]=['src'=>$g['src'],'thumb'=>is_string($g['thumb']??null)&&$g['thumb']!==''?$g['thumb']:$g['src']];
        }
        return $out;
    }
    /** Todas las rutas de imagen de una noticia (portada y galería). */
    public static function imagePaths(array $item):array {
        $paths=[$item['image']??''];
        foreach(self::gallery($item) as $g){$paths[]=$g['src'];$paths[]=$g['thumb'];}
        return array_values(array_unique(array_filter($paths)));
    }
    /** Normaliza un <input type=file multiple> de PHP a una lista de archivos. */
    private static function multiUpload(mixed $field):array {
        if(!is_array($field)||!is_array($field['name']??null))return [];
        $list=[];
        foreach(array_keys($field['name']) as $i){
            if(($field['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;
            $list[]=['name'=>$field['name'][$i],'type'=>$field['type'][$i]??'','tmp_name'=>$field['tmp_name'][$i]??'','error'=>$field['error'][$i],'size'=>$field['size'][$i]??0];
        }
        return $list;
    }
    /**
     * Guarda una imagen subida. Si GD está disponible la reduce a 1600 px en WebP
     * (las fotos de celular pesan varios MB); en galería también crea una miniatura.
     */
    private static function storeUpload(array $upload,bool $gallery=false):string|array {
        $max=self::maxUploadBytes();
        if(($upload['error']??0)===UPLOAD_ERR_INI_SIZE||($upload['error']??0)===UPLOAD_ERR_FORM_SIZE||($upload['size']??0)>$max)throw new Problem(422,'La imagen supera el máximo de '.round($max/1048576,1).' MB.');
        if(($upload['error']??1)!==UPLOAD_ERR_OK||!is_uploaded_file($upload['tmp_name']??''))throw new Problem(422,'No se pudo recibir la imagen. Vuelve a intentarlo.');
        $type=(new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        if(!isset(self::UPLOAD_TYPES[$type])||!getimagesize($upload['tmp_name']))throw new Problem(422,'La imagen debe ser JPG, PNG o WebP.');
        $id=bin2hex(random_bytes(16));$dir=self::imageDir();
        $src=self::resize($upload['tmp_name'],$type,1600,$dir.'/'.$id.'.webp');
        if($src===null){
            $name=$id.'.'.self::UPLOAD_TYPES[$type];
            if(!move_uploaded_file($upload['tmp_name'],$dir.'/'.$name))throw new RuntimeException('No se pudo guardar la imagen.');
            chmod($dir.'/'.$name,0600);$src='/assets/news/'.$name;
        }
        if(!$gallery)return $src;
        $thumb=self::resize($dir.'/'.basename($src),str_ends_with($src,'.webp')?'image/webp':$type,640,$dir.'/'.$id.'-sm.webp');
        return ['src'=>$src,'thumb'=>$thumb??$src];
    }
    /** Reduce una imagen a $max px de lado mayor y la guarda en WebP. Devuelve null si no hay GD. */
    private static function resize(string $from,string $type,int $max,string $to):?string {
        if(!function_exists('imagewebp'))return null;
        $img=match($type){'image/jpeg'=>@imagecreatefromjpeg($from),'image/png'=>@imagecreatefrompng($from),'image/webp'=>@imagecreatefromwebp($from),default=>false};
        if(!$img)return null;
        if($type==='image/jpeg'&&function_exists('exif_read_data')){
            $o=@exif_read_data($from)['Orientation']??1;
            $img=match($o){3=>imagerotate($img,180,0),6=>imagerotate($img,-90,0),8=>imagerotate($img,90,0),default=>$img};
        }
        $w=imagesx($img);$h=imagesy($img);$scale=min(1,$max/max($w,$h));
        if($scale<1)$img=imagescale($img,(int)round($w*$scale),(int)round($h*$scale),IMG_BICUBIC);
        $ok=imagewebp($img,$to,82);imagedestroy($img);
        if(!$ok)return null;
        chmod($to,0600);
        return '/assets/news/'.basename($to);
    }

    /** Noticias publicadas, en el formato que lee el sitio. */
    public static function export():array {
        return array_values(array_filter(store()->news(),fn($n)=>$n['status']==='published'));
    }
}
