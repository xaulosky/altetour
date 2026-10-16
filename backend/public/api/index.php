<?php
declare(strict_types=1);
require dirname(__DIR__).'/_bootstrap.php';
use Alte\Problem;
use function Alte\{beginSession,headersPrivate,csrfCheck,rateLimit,jsonResponse,validLead,store};
try {
    headersPrivate();beginSession();$route=$_GET['route']??'';$method=$_SERVER['REQUEST_METHOD'];
    if($route==='csrf'&&$method==='GET'){rateLimit('csrf',120,300);jsonResponse(['token'=>$_SESSION['csrf']]);}
    if($route==='leads'&&$method==='POST'){
        csrfCheck();
        if((int)($_SERVER['CONTENT_LENGTH']??0)>14000)throw new Problem(413,'El formulario supera el tamaño permitido.');
        if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')!==0)throw new Problem(415,'Formato de solicitud inválido.');
        $raw=file_get_contents('php://input',false,null,0,14001);
        if(strlen($raw)>14000)throw new Problem(413,'El formulario supera el tamaño permitido.');
        try{$input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new Problem(400,'No se pudo leer la solicitud.');}
        if(!is_array($input))throw new Problem(400,'Formulario inválido.');
        rateLimit('leads',12,3600);
        if(!empty($input['website']))jsonResponse(['reference'=>'AT-'.strtoupper(bin2hex(random_bytes(5)))],202);
        $key=$input['idempotency_key']??'';
        if(!is_string($key)||!preg_match('/^[a-zA-Z0-9-]{20,80}$/D',$key))throw new Problem(422,'Recarga el formulario para comenzar una nueva consulta.');
        $record=store()->createLead($key,validLead($input));
        jsonResponse(['reference'=>$record['reference']],201);
    }
    if($route==='programs'&&$method==='GET'){
        $programs=array_values(array_filter(store()->programs(),fn($p)=>$p['status']==='published'));
        // El material editorial interno nunca se expone a visitantes de la API.
        foreach($programs as &$p)unset($p['editorialWarnings']);unset($p);
        jsonResponse(['programs'=>$programs]);
    }
    throw new Problem(404,'Ruta no disponible.');
} catch(Problem $ex){jsonResponse(['message'=>$ex->getMessage()],$ex->status);}
catch(\Throwable $ex){error_log('Alte API: '.$ex->getMessage());jsonResponse(['message'=>'El formulario no está disponible en este momento. Tus datos no se han borrado; reintenta o contacta por WhatsApp.'],503);}
