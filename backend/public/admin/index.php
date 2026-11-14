<?php
declare(strict_types=1);
require dirname(__DIR__).'/_bootstrap.php';
use Alte\Problem;
use Alte\News;
use function Alte\{escape as e,store,beginSession,headersPrivate,csrfCheck,rateLimit,requireAdmin};
const STATUSES=['nuevo'=>'Nuevo','contactado'=>'En contacto','cotizado'=>'Cotizado','cerrado'=>'Cerrado'];
function csrf():string{return '<input type="hidden" name="_csrf" value="'.e($_SESSION['csrf']).'">';}
function url(string $view,string $id=''):string{return '/admin/?view='.$view.($id!==''?'&id='.rawurlencode($id):'');}
function redirect(string $view,string $message='',string $id=''):never{if($message!=='')$_SESSION['flash']=$message;header('Location: '.url($view,$id));exit;}
function cleanCsv(mixed $v):string{$v=(string)$v;return preg_match('/^[\x00-\x20]*[=+\-@]/u',$v)?"'".$v:$v;}
function table(array $rows):string {
    $html='<div class="table-wrap"><table><thead><tr><th>Referencia / fecha UTC</th><th>Persona</th><th>Interés</th><th>Estado</th><th></th></tr></thead><tbody>';
    foreach($rows as $r){$d=$r['data'];$html.='<tr><td><strong>'.e($r['reference']).'</strong><small>'.e($r['created_at']).'</small></td><td>'.e($d['name']).'<small>'.e($d['email']).'</small></td><td>'.e($d['program']?:$d['interest']).'<small>'.e($d['travelers']).' viajeros</small></td><td><span class="status status-'.e($r['status']).'">'.e(STATUSES[$r['status']]??$r['status']).'</span></td><td><a class="text-link" href="'.url('lead',$r['id']).'">Ver consulta →</a></td></tr>';}
    if(!$rows)$html.='<tr><td colspan="5" class="empty">Todavía no hay consultas para esta vista.</td></tr>';
    return $html.'</tbody></table></div>';
}
try {
    headersPrivate(true);beginSession();
    $view=is_string($_GET['view']??null)?$_GET['view']:'dashboard';
    $error='';
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        csrfCheck();$action=is_string($_POST['action']??null)?$_POST['action']:'';
        if($action==='login'){
            rateLimit('admin-login',8,900);
            $email=is_string($_POST['email']??null)?strtolower(trim($_POST['email'])):'';
            $password=is_string($_POST['password']??null)?$_POST['password']:'';
            $admin=store()->admin($email);
            if(!$admin || !password_verify($password,$admin['password_hash'])){$error='Correo o contraseña incorrectos.';$view='login';}
            else{session_regenerate_id(true);$_SESSION['admin']=$admin['email'];$_SESSION['csrf']=bin2hex(random_bytes(32));$_SESSION['last_activity']=time();$_SESSION['login_at']=time();redirect('dashboard');}
        }else{
            requireAdmin();
            if($action==='logout'){$_SESSION=[];session_regenerate_id(true);redirect('login');}
            if($action==='status'){
                $id=is_string($_POST['id']??null)?$_POST['id']:'';$status=is_string($_POST['status']??null)?$_POST['status']:'';
                if(!isset(STATUSES[$status])||!store()->lead($id))throw new Problem(422,'Consulta o estado inválido.');
                store()->setStatus($id,$status);redirect('lead','Estado actualizado.',$id);
            }
            if($action==='delete'){
                $id=is_string($_POST['id']??null)?$_POST['id']:'';
                if(($_POST['confirmation']??'')!=='ELIMINAR'||!store()->lead($id))throw new Problem(422,'Para eliminar, escribe ELIMINAR y verifica la consulta.');
                store()->deleteLead($id);redirect('leads','Consulta eliminada del almacenamiento activo. Revisa también la política de retención de copias de respaldo.');
            }
            if($action==='program'){
                $slug=is_string($_POST['slug']??null)?$_POST['slug']:'';$p=store()->program($slug);
                if(!$p)throw new Problem(404,'Programa no encontrado.');
                foreach(['title'=>150,'subtitle'=>400] as $field=>$max){$v=$_POST[$field]??'';if(!is_string($v)||strlen(trim($v))<3||strlen($v)>$max)throw new Problem(422,'Revisa título y descripción.');$p[$field]=trim($v);}
                foreach(['double','single','triple'] as $room){$v=filter_var($_POST['price_'.$room]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>999999]]);if($v===false)throw new Problem(422,'Los precios deben ser enteros positivos en USD.');$p['prices'][$room]=$v;}
                $status=$_POST['status']??'';if(!in_array($status,['published','draft','archived'],true))throw new Problem(422,'Estado de publicación inválido.');$p['status']=$status;
                store()->putProgram($p);redirect('programs','Cambios guardados en el backend. Exporta programs.json, recompila Astro y publica el nuevo dist para actualizar el sitio visible.');
            }
            if($action==='news-save'){
                $slug=is_string($_POST['original']??null)?$_POST['original']:'';
                $existing=$slug!==''?store()->newsItem($slug):null;
                if($slug!==''&&!$existing)throw new Problem(404,'Noticia no encontrada.');
                $item=News::fromForm($_POST,$_FILES,$existing);
                store()->putNews($item);
                redirect('news-edit','Noticia guardada. Para verla en el sitio, exporta news.json y vuelve a publicar.',$item['slug']);
            }
            if($action==='news-delete'){
                $slug=is_string($_POST['slug']??null)?$_POST['slug']:'';
                if(($_POST['confirmation']??'')!=='ELIMINAR'||!store()->newsItem($slug))throw new Problem(422,'Para eliminar, escribe ELIMINAR y verifica la noticia.');
                $image=store()->newsItem($slug)['image'];store()->deleteNews($slug);
                // Borra la imagen subida si ninguna otra noticia la usa.
                if(($file=News::uploadedFile($image))&&!array_filter(store()->news(),fn($n)=>$n['image']===$image))unlink($file);
                redirect('news','Noticia eliminada. Exporta news.json y vuelve a publicar para retirarla del sitio.');
            }
            throw new Problem(404,'Acción no disponible.');
        }
    }
    if($view!=='login')requireAdmin();
    if($view==='export'){
        header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="consultas-altetour-'.gmdate('Ymd').'.csv"');
        $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['Referencia','Fecha UTC','Nombre','Email','Teléfono','Interés','Programa','Viajeros','Habitación','Mensaje','Estado','Origen','UTM campaña'],',','"','');
        foreach(store()->leads() as $r){$d=$r['data'];fputcsv($out,array_map('cleanCsv',[$r['reference'],$r['created_at'],$d['name'],$d['email'],$d['phone'],$d['interest'],$d['program'],$d['travelers'],$d['room'],$d['message'],$r['status'],$d['source'],$d['utm']['utm_campaign']??'']),',','"','');}
        fclose($out);exit;
    }
    if($view==='export-programs'){
        header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="programs.json"');
        echo json_encode(store()->programs(),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);exit;
    }
    if($view==='export-news'){
        header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="news.json"');
        echo json_encode(News::export(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);exit;
    }
    if($view==='news-image'){
        // Vista previa privada de imágenes subidas que aún no se han exportado al sitio.
        $file=News::uploadedFile('/assets/news/'.(is_string($_GET['file']??null)?$_GET['file']:''));
        if(!$file)throw new Problem(404,'Imagen no encontrada.');
        header('Content-Type: '.(new finfo(FILEINFO_MIME_TYPE))->file($file));header('Content-Length: '.filesize($file));readfile($file);exit;
    }
    $body='';$title='Resumen';$flash=$_SESSION['flash']??'';unset($_SESSION['flash']);
    if($view==='login'){
        $title='Acceso al panel';
        $body='<section class="login-card"><span class="eyebrow">ALTE TOUR · ADMINISTRACIÓN</span><h1>Bienvenido de vuelta.</h1><p>Ingresa para revisar consultas y administrar el programa.</p>'.($error?'<div class="alert danger">'.e($error).'</div>':'').'<form method="post" action="/admin/?view=login">'.csrf().'<input type="hidden" name="action" value="login"><label>Correo electrónico<input type="email" name="email" autocomplete="username" maxlength="190" required></label><label>Contraseña<input type="password" name="password" autocomplete="current-password" required></label><button class="button" type="submit">Entrar al panel →</button></form><small>No existe una cuenta por defecto. El administrador se crea desde la consola del servidor.</small><a href="/" class="text-link">← Volver al sitio</a></section>';
    }elseif($view==='dashboard'||$view==='leads'){
        $rows=store()->leads();$all=$rows;$title=$view==='dashboard'?'Tu agencia, de un vistazo.':'Consultas recibidas';
        $q=is_string($_GET['q']??null)?trim($_GET['q']):'';$status=is_string($_GET['status']??null)?$_GET['status']:'';
        if($view==='dashboard'){
            $body.='<div class="stats"><article><span>Consultas registradas</span><strong>'.count($all).'</strong></article><article><span>Nuevas por revisar</span><strong>'.count(array_filter($all,fn($r)=>$r['status']==='nuevo')).'</strong></article><article><span>En contacto</span><strong>'.count(array_filter($all,fn($r)=>$r['status']==='contactado')).'</strong></article><article><span>Cotizadas</span><strong>'.count(array_filter($all,fn($r)=>$r['status']==='cotizado')).'</strong></article></div><div class="section-heading"><h2>Últimas consultas</h2><a class="text-link" href="/admin/?view=leads">Ver consultas →</a></div>'; $rows=array_slice($all,0,10);
        }else{
            $rows=array_values(array_filter($rows,fn($r)=>($status===''||$r['status']===$status)&&($q===''||stripos($r['data']['name'].' '.$r['data']['email'].' '.$r['reference'],$q)!==false)));
            $body.='<form class="filters" method="get"><input type="hidden" name="view" value="leads"><input type="search" name="q" value="'.e($q).'" placeholder="Nombre, correo o referencia" aria-label="Buscar consultas"><select name="status" aria-label="Filtrar por estado"><option value="">Todos los estados</option>';
            foreach(STATUSES as $k=>$label)$body.='<option value="'.$k.'"'.($status===$k?' selected':'').'>'.$label.'</option>';
            $body.='</select><button class="button" type="submit">Filtrar</button><a class="button secondary" href="/admin/?view=export">Exportar CSV</a></form>';
        }
        $body.=table($rows).'<p class="footnote">Se muestran como máximo las 5.000 consultas más recientes. Las fechas del panel están en UTC. Las consultas no representan reservas ni ventas confirmadas.</p>';
        if($view==='dashboard')$body.='<div class="alert"><strong>Antes de publicar</strong><p>Revisar términos de contratación, paseo en globo, hoteles, datos del responsable de privacidad y material visual de mayor resolución. El panel no procesa pagos ni envía correos automáticos.</p></div>';
    }elseif($view==='lead'){
        $r=store()->lead(is_string($_GET['id']??null)?$_GET['id']:'');if(!$r)throw new Problem(404,'Consulta no encontrada.');$d=$r['data'];$title=$r['reference'];
        $body='<a class="text-link" href="/admin/?view=leads">← Volver a consultas</a><div class="detail-grid"><section class="card"><h2>'.e($d['name']).'</h2><dl>';
        foreach(['email'=>'Correo','phone'=>'Teléfono','interest'=>'Interés','program'=>'Programa','travelers'=>'Viajeros','room'=>'Habitación','source'=>'Origen','consent_version'=>'Versión de consentimiento'] as $k=>$label)$body.='<dt>'.$label.'</dt><dd>'.e($d[$k]).'</dd>';
        $body.='<dt>Fecha de solicitud UTC</dt><dd>'.e($r['created_at']).'</dd><dt>Consentimiento</dt><dd>Autorizado para responder la consulta</dd></dl><h3>Mensaje</h3><p class="message">'.nl2br(e($d['message']?:'Sin mensaje adicional.')).'</p><h3>Origen de campaña</h3><p class="message">'.e(json_encode($d['utm'],JSON_UNESCAPED_UNICODE)).'</p></section><aside><section class="card"><h2>Seguimiento</h2><form method="post">'.csrf().'<input type="hidden" name="action" value="status"><input type="hidden" name="id" value="'.e($r['id']).'"><label>Estado<select name="status">';
        foreach(STATUSES as $k=>$label)$body.='<option value="'.$k.'"'.($r['status']===$k?' selected':'').'>'.$label.'</option>';
        $body.='</select></label><button type="submit" class="button">Guardar estado</button></form></section><details class="card delete-card"><summary>Eliminar consulta</summary><p>Elimina el registro activo. Esta acción no se puede deshacer desde el panel.</p><form method="post">'.csrf().'<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="'.e($r['id']).'"><label>Escribe ELIMINAR<input name="confirmation" pattern="ELIMINAR" required autocomplete="off"></label><button type="submit" class="button danger-button">Eliminar permanentemente</button></form></details></aside></div>';
    }elseif($view==='programs'){
        $title='Programa y publicación';$body='<div class="alert"><strong>El contenido de Astro se publica por compilación.</strong><p>Guardar aquí no modifica las páginas estáticas ya subidas. Descarga programs.json, reemplaza frontend/src/data/programs.json, ejecuta npm run build y vuelve a publicar dist. Las consultas nuevas sí se guardan inmediatamente.</p><a class="button secondary" href="/admin/?view=export-programs">Exportar programs.json</a></div>';
        foreach(store()->programs() as $p){
            $body.='<section class="card program-card"><span class="eyebrow">'.e($p['slug']).' · FECHAS E ITINERARIO EN EL ARCHIVO DE CONTENIDO</span><h2>'.e($p['title']).'</h2><form method="post">'.csrf().'<input type="hidden" name="action" value="program"><input type="hidden" name="slug" value="'.e($p['slug']).'"><div class="form-columns"><label>Título<input name="title" value="'.e($p['title']).'" maxlength="150" required></label><label>Estado<select name="status">';
            foreach(['published'=>'Publicado en el próximo build','draft'=>'Borrador','archived'=>'Archivado'] as $key=>$label)$body.='<option value="'.$key.'"'.($p['status']===$key?' selected':'').'>'.$label.'</option>';
            $body.='</select></label><label class="wide">Descripción breve<textarea name="subtitle" maxlength="400" rows="2" required>'.e($p['subtitle']).'</textarea></label>';
            foreach(['double'=>'Doble','single'=>'Single','triple'=>'Triple'] as $room=>$label)$body.='<label>'.$label.' · USD por persona<input type="number" name="price_'.$room.'" min="1" max="999999" step="1" value="'.e($p['prices'][$room]).'" required></label>';
            $body.='</div><button class="button" type="submit">Guardar cambios</button></form><details class="editorial"><summary>Pendientes de validación del material</summary><ul>';
            foreach($p['editorialWarnings']??[] as $w)$body.='<li>'.e($w).'</li>';
            $body.='</ul></details></section>';
        }
        $body.='<p class="footnote">Esta base permite editar el programa cargado. Alta de nuevos programas, medios e itinerarios desde un editor visual y publicación automática quedan para la siguiente etapa.</p>';
    }elseif($view==='news'){
        $title='Noticias';$items=store()->news();
        $body='<div class="alert"><strong>Las noticias se publican por compilación, igual que el programa.</strong><p>Guardar aquí no cambia el sitio ya publicado. Después de editar, exporta news.json: desde la consola con <code>php backend/bin/export-news.php --write</code> (también copia las imágenes subidas), o descargándolo aquí y reemplazando frontend/src/data/news.json. Luego compila y publica. Solo se exportan las noticias publicadas.</p><div class="button-row"><a class="button" href="'.url('news-edit').'">+ Nueva noticia</a><a class="button secondary" href="/admin/?view=export-news">Descargar news.json</a></div></div>';
        $body.='<div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Noticia</th><th>Categoría</th><th>Idiomas</th><th>Estado</th><th></th></tr></thead><tbody>';
        foreach($items as $n){
            $langs=implode(' · ',array_map('strtoupper',array_values(array_filter(array_keys(News::LANGS),fn($l)=>isset($n[$l])))));
            $body.='<tr><td>'.e($n['date']).'</td><td><strong>'.e($n['es']['title']).'</strong><small>'.e($n['slug']).'</small></td><td>'.e(News::CATEGORIES[$n['category']]??$n['category']).'</td><td>'.e($langs).'</td><td><span class="status status-'.($n['status']==='published'?'cotizado':'nuevo').'">'.($n['status']==='published'?'Publicada':'Borrador').'</span></td><td><a class="text-link" href="'.url('news-edit',$n['slug']).'">Editar →</a></td></tr>';
        }
        if(!$items)$body.='<tr><td colspan="6" class="empty">Todavía no hay noticias. Crea la primera con «Nueva noticia».</td></tr>';
        $body.='</tbody></table></div><p class="footnote">Categorías disponibles: '.e(implode(', ',News::CATEGORIES)).'. El español es obligatorio; si falta inglés o portugués, esas versiones del sitio muestran el texto en español.</p>';
    }elseif($view==='news-edit'){
        $slug=is_string($_GET['id']??null)?$_GET['id']:'';$n=$slug!==''?store()->newsItem($slug):null;
        if($slug!==''&&!$n)throw new Problem(404,'Noticia no encontrada.');
        $title=$n?'Editar noticia':'Nueva noticia';
        $v=static fn(string $lang,string $field):string=>e($n[$lang][$field]??'');
        $body='<a class="text-link" href="'.url('news').'">← Volver a noticias</a><form class="card news-form" method="post" enctype="multipart/form-data" action="'.url('news-edit',$slug).'">'.csrf().'<input type="hidden" name="action" value="news-save"><input type="hidden" name="original" value="'.e($slug).'">';
        $body.='<div class="form-columns"><label>Categoría<select name="category">';
        foreach(News::CATEGORIES as $k=>$label)$body.='<option value="'.$k.'"'.(($n['category']??'viajes')===$k?' selected':'').'>'.e($label).'</option>';
        $body.='</select></label><label>Estado<select name="status">';
        foreach(News::STATUSES as $k=>$label)$body.='<option value="'.$k.'"'.(($n['status']??'draft')===$k?' selected':'').'>'.e($label).'</option>';
        $body.='</select></label><label>Fecha<input type="date" name="date" value="'.e($n['date']??gmdate('Y-m-d')).'" required></label>';
        $body.=$n?'<label>Dirección<input value="/noticias/'.e($n['slug']).'/" readonly></label>':'<label>Dirección (opcional)<input name="slug" maxlength="90" placeholder="Se genera desde el título" pattern="[a-z0-9-]*"></label>';
        $body.='<label class="wide">Enlace relacionado (opcional)<input name="linkUrl" maxlength="500" value="'.e($n['linkUrl']??'').'" placeholder="https://… o una ruta del sitio, como /viajes/turquia-2027/"></label></div>';
        $body.='<h3>Imagen</h3><div class="news-image-row">';
        if($n&&$n['image']!==''){$preview=News::uploadedFile($n['image'])?'/admin/?view=news-image&file='.rawurlencode(basename($n['image'])):$n['image'];$body.='<img class="news-thumb" src="'.e($preview).'" alt="">';}
        $body.='<div class="form-columns"><label>Elegir de la biblioteca<select name="image"><option value="__keep">'.($n?'Mantener la imagen actual':'Sin elegir (se usa Biobío)').'</option>';
        foreach(News::LIBRARY as $path=>$label)$body.='<option value="'.e($path).'">'.e($label).'</option>';
        $body.='</select></label><label>O subir una imagen nueva (JPG, PNG o WebP, máx. '.e(round(News::maxUploadBytes()/1048576,1)).' MB)<input type="file" name="image_upload" accept="image/jpeg,image/png,image/webp"></label></div></div>';
        foreach(News::LANGS as $lang=>$label){
            $req=$lang==='es';
            $body.='<fieldset class="news-lang"><legend>'.e($label).($req?' · obligatorio':' · opcional').'</legend><div class="form-columns"><label class="wide">Título<input name="'.$lang.'_title" maxlength="160" value="'.$v($lang,'title').'"'.($req?' required':'').'></label><label class="wide">Resumen<textarea name="'.$lang.'_summary" maxlength="400" rows="2"'.($req?' required':'').'>'.$v($lang,'summary').'</textarea></label><label class="wide">Texto (separa los párrafos con una línea en blanco)<textarea name="'.$lang.'_body" maxlength="20000" rows="8"'.($req?' required':'').'>'.$v($lang,'body').'</textarea></label><label>Descripción de la imagen<input name="'.$lang.'_imageAlt" maxlength="200" value="'.$v($lang,'imageAlt').'"></label><label>Texto del enlace<input name="'.$lang.'_linkLabel" maxlength="80" value="'.$v($lang,'linkLabel').'"></label></div></fieldset>';
        }
        $body.='<button class="button" type="submit">Guardar noticia</button></form>';
        if($n)$body.='<details class="card delete-card"><summary>Eliminar noticia</summary><p>Elimina la noticia del panel. Seguirá visible en el sitio hasta que exportes news.json y vuelvas a publicar.</p><form method="post">'.csrf().'<input type="hidden" name="action" value="news-delete"><input type="hidden" name="slug" value="'.e($n['slug']).'"><label>Escribe ELIMINAR<input name="confirmation" pattern="ELIMINAR" required autocomplete="off"></label><button type="submit" class="button danger-button">Eliminar noticia</button></form></details>';
    }else{throw new Problem(404,'Vista no disponible.');}
    ?><!doctype html><html lang="es-CL"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=e($title)?> · Alte Tour Admin</title><link rel="stylesheet" href="/admin/admin.css"></head><body class="<?=$view==='login'?'login-page':''?>"><?php if($view!=='login'):?><aside class="sidebar"><a class="admin-brand" href="/admin/">ALTE <span>TOUR</span><small>PANEL DE ADMINISTRACIÓN</small></a><nav aria-label="Panel"><a class="<?=$view==='dashboard'?'active':''?>" href="/admin/">Resumen</a><a class="<?=in_array($view,['leads','lead'])?'active':''?>" href="/admin/?view=leads">Consultas</a><a class="<?=$view==='programs'?'active':''?>" href="/admin/?view=programs">Programa y publicación</a><a class="<?=in_array($view,['news','news-edit'])?'active':''?>" href="/admin/?view=news">Noticias</a><a href="/">Ver sitio web ↗</a></nav><div class="session-user"><small><?=e($_SESSION['admin'])?></small><form method="post"><?=csrf()?><input type="hidden" name="action" value="logout"><button type="submit">Cerrar sesión</button></form></div></aside><main class="admin-main"><header class="admin-header"><span class="eyebrow">ALTE TOUR · OPERACIÓN</span><h1><?=e($title)?></h1><p>Consultas y contenido comercial, en un solo lugar.</p></header><?php if($flash):?><div class="alert success"><?=e($flash)?></div><?php endif;?><?=$body?></main><?php else:?><main><?=$body?></main><?php endif;?></body></html><?php
} catch(Problem $ex){http_response_code($ex->status);echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Alte Tour · Aviso</title><link rel="stylesheet" href="/admin/admin.css"><body class="login-page"><main class="login-card"><h1>No se completó la acción.</h1><p>'.e($ex->getMessage()).'</p><a class="button" href="/admin/">Volver al panel</a></main></body></html>';}
catch(\Throwable $ex){error_log('Alte Admin: '.$ex->getMessage());http_response_code(503);echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Alte Tour</title><body><h1>Panel temporalmente no disponible</h1><p>Revisa la instalación y la conexión de almacenamiento en la configuración privada.</p></body></html>';}
