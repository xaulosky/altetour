<?php
declare(strict_types=1);
require dirname(__DIR__).'/_bootstrap.php';
use Alte\Problem;
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
    }else{throw new Problem(404,'Vista no disponible.');}
    ?><!doctype html><html lang="es-CL"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=e($title)?> · Alte Tour Admin</title><link rel="stylesheet" href="/admin/admin.css"></head><body class="<?=$view==='login'?'login-page':''?>"><?php if($view!=='login'):?><aside class="sidebar"><a class="admin-brand" href="/admin/">ALTE <span>TOUR</span><small>PANEL DE ADMINISTRACIÓN</small></a><nav aria-label="Panel"><a class="<?=$view==='dashboard'?'active':''?>" href="/admin/">Resumen</a><a class="<?=in_array($view,['leads','lead'])?'active':''?>" href="/admin/?view=leads">Consultas</a><a class="<?=$view==='programs'?'active':''?>" href="/admin/?view=programs">Programa y publicación</a><a href="/">Ver sitio web ↗</a></nav><div class="session-user"><small><?=e($_SESSION['admin'])?></small><form method="post"><?=csrf()?><input type="hidden" name="action" value="logout"><button type="submit">Cerrar sesión</button></form></div></aside><main class="admin-main"><header class="admin-header"><span class="eyebrow">ALTE TOUR · OPERACIÓN</span><h1><?=e($title)?></h1><p>Consultas y contenido comercial, en un solo lugar.</p></header><?php if($flash):?><div class="alert success"><?=e($flash)?></div><?php endif;?><?=$body?></main><?php else:?><main><?=$body?></main><?php endif;?></body></html><?php
} catch(Problem $ex){http_response_code($ex->status);echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Alte Tour · Aviso</title><link rel="stylesheet" href="/admin/admin.css"><body class="login-page"><main class="login-card"><h1>No se completó la acción.</h1><p>'.e($ex->getMessage()).'</p><a class="button" href="/admin/">Volver al panel</a></main></body></html>';}
catch(\Throwable $ex){error_log('Alte Admin: '.$ex->getMessage());http_response_code(503);echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Alte Tour</title><body><h1>Panel temporalmente no disponible</h1><p>Revisa la instalación y la conexión de almacenamiento en la configuración privada.</p></body></html>';}
