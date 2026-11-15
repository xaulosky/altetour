<?php
declare(strict_types=1);
namespace Alte;
use Throwable;

// Aviso por correo de cada consulta nueva. Se configura en config.local.php:
// 'notify' => ['to' => 'destino@dominio.cl', 'from' => 'avisos@altetour.cl', 'from_name' => 'Alte Tour web']
// Sin 'notify.to' no se envía nada. La consulta ya quedó guardada antes de llamar aquí.
const LEAD_INTERESTS=['turquia'=>'Turquía 2027','viaje'=>'Viaje a medida','misiones'=>'Misión comercial','receptivo'=>'Descubrir Chile'];
const LEAD_ROOMS=['single'=>'Single','double'=>'Doble','triple'=>'Triple','unsure'=>'Por definir'];

function oneLine(string $v):string{return trim(preg_replace('/[\r\n\t\x00]+/',' ',$v));}

function leadMail(array $record,string $site):array {
    $d=$record['data'];
    $program=$d['program']!==''?(store()->program($d['program'])['title']??$d['program']):'';
    $interest=LEAD_INTERESTS[$d['interest']]??$d['interest'];
    $subject='Nueva consulta '.$record['reference'].': '.oneLine($d['name']).' · '.($program?:$interest);
    $lines=[
        'Llegó una nueva consulta desde el sitio web.','',
        'Referencia: '.$record['reference'],
        'Nombre: '.oneLine($d['name']),
        'Correo: '.$d['email'],
        'Teléfono: '.oneLine($d['phone']),
        'Interés: '.$interest,
    ];
    if($program)$lines[]='Programa: '.oneLine($program);
    $lines[]='Viajeros: '.$d['travelers'];
    $lines[]='Habitación: '.(LEAD_ROOMS[$d['room']]??$d['room']);
    if($d['source']!=='')$lines[]='Página: '.oneLine($d['source']);
    if($d['utm'])$lines[]='Campaña: '.oneLine(implode(' / ',$d['utm']));
    $lines[]='';$lines[]='Mensaje:';$lines[]=$d['message']!==''?str_replace("\r",'',$d['message']):'(sin mensaje)';
    $lines[]='';$lines[]='Responder este correo le escribe directamente a '.oneLine($d['name']).'.';
    $lines[]='Ver y cambiar el estado en el panel: '.rtrim($site,'/').'/admin/?view=lead&id='.$record['id'];
    return ['subject'=>$subject,'body'=>implode("\n",$lines)];
}

function notifyLead(array $record):void {
    try {
        $n=config()['notify']??[];$to=$n['to']??'';$from=$n['from']??'';
        if(!$to||!filter_var($to,FILTER_VALIDATE_EMAIL)||!filter_var($from,FILTER_VALIDATE_EMAIL))return;
        $mail=leadMail($record,$n['site']??'https://altetour.cl');
        $name=mb_encode_mimeheader(oneLine($n['from_name']??'Alte Tour web'),'UTF-8','Q');
        $client=$record['data']['email'];$clientName=mb_encode_mimeheader(oneLine($record['data']['name']),'UTF-8','Q');
        $headers=[
            'From'=>$name.' <'.$from.'>',
            'Reply-To'=>filter_var($client,FILTER_VALIDATE_EMAIL)?$clientName.' <'.$client.'>':$from,
            'MIME-Version'=>'1.0',
            'Content-Type'=>'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding'=>'8bit',
            'X-Alte-Reference'=>$record['reference'],
        ];
        $ok=mail($to,mb_encode_mimeheader($mail['subject'],'UTF-8','Q'),$mail['body'],$headers,'-f'.$from);
        if(!$ok)error_log('Alte aviso: no se pudo entregar el aviso de '.$record['reference'].' a sendmail');
    } catch(Throwable $ex){error_log('Alte aviso: '.$ex->getMessage());}
}
