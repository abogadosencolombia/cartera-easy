<?php
namespace App\Services\AbogadosBot;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Read-only source results. No document text is executed or sent to a chat model. */
final class SourceConversation
{
    public function __construct(private object $google, private string $root) {}

    public static function plan(string $text):?array
    {
        $s=Policy::normalize($text);
        $s=preg_replace('/^(?:hola[,! ]*|buen(?:os dias|as tardes|as noches)[,! ]*)?(?:abogado )?jeison[, :!¿-]*/u','',$s);
        $hasMail=(bool)preg_match('/\b(?:gmail|correos?|emails?)\b/u',$s);
        $hasDrive=(bool)preg_match('/\bdrive\b/u',$s);
        if(preg_match('/^(?:por favor[, ]*)?(?:lee|abre|muestra(?:me)?|consulta)\s+(?:(?:el|la)\s+)?(?:(?:correo|archivo|documento|carpeta|resultado|opcion)\s+)?(?:(?:numero|n[º°])\s*)?([1-5]|primero|primera|segundo|segunda|tercero|tercera|cuarto|cuarta|quinto|quinta)(?:\s+(?:de\s+)?(gmail|drive))?[.!? ]*$/u',$s,$m)){
            $ord=['primero'=>1,'primera'=>1,'segundo'=>2,'segunda'=>2,'tercero'=>3,'tercera'=>3,'cuarto'=>4,'cuarta'=>4,'quinto'=>5,'quinta'=>5];
            return ['action'=>'read','index'=>$ord[$m[1]]??(int)$m[1],'source'=>$m[2]??null];
        }
        if(!$hasMail&&!$hasDrive)return null;
        // Requests to alter, forward or decide are not converted into reads.
        if(preg_match('/\b(?:envia|enviar|reenvia|reenviar|borra|borrar|elimina|eliminar|archiva|archivar|marca|marcar|comparte|compartir|publica|publicar|modifica|modificar|edita|editar|radica|radicar|vence|vencimiento|termino|plazo|concluye|decide)\b/u',$s))return ['action'=>'restricted'];
        if($hasMail&&$hasDrive)return ['action'=>'clarify','reply'=>'Claro, Sandra. ¿Quieres que busque primero en el correo o en Drive?'];
        if(!preg_match('/\b(?:busca|buscar|revisa|revisar|consulta|consultar|mira|muestrame|muestra|lee|leer|abre|abrir|ver|tengo|hay|llego|llegaron)\b/u',$s))return ['action'=>'help'];
        $term='';
        if(preg_match('/["“«]([^"”»]{2,180})["”»]/u',$text,$m))$term=trim($m[1]);
        elseif(preg_match('/(?<!\d)(\d{23})(?!\d)/',$text,$m))$term=$m[1];
        elseif(preg_match('/\b(?:sobre|acerca de|relacionados? con|llamad[oa]s?)\s+(.+?)(?:[?.!]|$)/iu',$text,$m))$term=trim($m[1]);
        elseif(preg_match('/\b(?:drive|correos?|emails?)\s+(?:de|sobre)\s+(.+?)(?:[?.!]|$)/iu',$text,$m)&&!preg_match('/^(?:hoy|ayer|esta semana|los ultimos)/u',Policy::normalize($m[1])))$term=trim($m[1]);
        elseif(preg_match('/\ben drive\s+(?:el |la |los |las )?(.{2,180})[?.!]*$/iu',$text,$m))$term=rtrim(trim($m[1]),'?.!');
        elseif(preg_match('/\b(?:busca|buscar|revisa|revisar|consulta|consultar)\s+(?:el |la |los |las )?(.{2,180}?)\s+en drive\b/iu',$text,$m))$term=trim($m[1]);
        $source=$hasMail?'gmail':'drive';
        if($source==='drive'&&$term==='')return ['action'=>'clarify','reply'=>'Claro, Sandra. ¿Qué nombre o radicado busco en Drive?'];
        if(mb_strlen($term)>180)return ['action'=>'clarify','reply'=>'¿Qué nombre o radicado específico quieres que busque?'];
        if(preg_match('/\b(?:todos|todas|completo|completa|todo)\b/u',$s))return ['action'=>'clarify','reply'=>'Puedo revisar los resultados por partes. ¿Qué nombre, radicado o fecha quieres consultar primero?'];
        return ['action'=>'search','source'=>$source,'term'=>$term,'unread'=>(bool)preg_match('/\b(?:sin leer|no leidos)\b/u',$s),'day'=>preg_match('/\bhoy\b/u',$s)?'today':(preg_match('/\bayer\b/u',$s)?'yesterday':null)];
    }

    public function answer(array $plan,array $context=[],?int $now=null):array
    {
        $now??=time();$contextOut=[];$evidence=['checked_at'=>gmdate('c',$now),'kind'=>$plan['action']];
        $finish=fn($reply,$ctx=[],$e=[])=>['reply'=>$reply,'context'=>$ctx,'evidence'=>array_replace($evidence,$e)];
        if($plan['action']==='restricted')return $finish('Puedo buscar y leer fuentes, Sandra. Enviar o modificar información y definir actuaciones jurídicas requiere una revisión aparte.');
        if($plan['action']==='clarify')return $finish($plan['reply']);
        if($plan['action']==='help')return $finish('Sí, Sandra. Puedes pedirme los correos de hoy o buscar en Drive por nombre o radicado. Luego dime cuál resultado quieres leer.');
        if(!$this->google->status()['connected'])return $finish('Sandra, la conexión con Google necesita atención. No pude consultar la información.');
        $identity=$this->google->verifyIdentity();
        if(($identity['account']??'')!==GoogleSources::ACCOUNT)throw new RuntimeException('GOOGLE_SOURCE_IDENTITY_MISMATCH');
        if($plan['action']==='search'){
            $source=$plan['source'];$term=$plan['term'];$items=[];$more=false;
            if($source==='gmail'){
                $q='-in:spam -in:trash';
                if($term!=='')$q.=' "'.str_replace(['\\','"'],['\\\\','\\"'],$term).'"';
                if($plan['unread'])$q.=' is:unread';
                if($plan['day']){$day=(new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('America/Bogota'))->setTime(0,0);if($plan['day']==='yesterday')$day=$day->modify('-1 day');$q.=' after:'.($day->getTimestamp()-1).' before:'.$day->modify('+1 day')->getTimestamp();}
                $found=$this->google->listMail($q,null,5);$more=!empty($found['nextPageToken']);
                foreach(array_slice($found['messages']??[],0,5) as $row){$mail=$this->google->mail($row['id'],true);$items[]=$this->mailItem($mail);}
                $evidence['query']=$q;
            }else{
                $q="trashed = false and name contains '".str_replace(['\\',"'"],['\\\\',"\\'"],$term)."'";
                $found=$this->google->listFiles($q,null,5);$more=!empty($found['nextPageToken']);
                foreach(array_slice($found['files']??[],0,5) as $row)$items[]=$this->driveItem($row);
                $evidence['query']=$q;
            }
            return $this->listing($source,$items,$more,$now,$evidence);
        }
        if($plan['action']!=='read')throw new RuntimeException('INVALID_SOURCE_ACTION');
        if(empty($context['items'])||($context['expires']??0)<$now||(!empty($plan['source'])&&$plan['source']!==($context['source']??'')))return $finish('Sandra, necesito una búsqueda reciente para saber cuál documento quieres leer. Indícame el nombre o radicado.');
        $item=$context['items'][$plan['index']-1]??null;
        if(!$item)return $finish('Ese número no corresponde a los resultados que te mostré. ¿Cuál de la lista quieres consultar?',$context);
        if($context['source']==='gmail'){
            if(!empty($item['protected'])||self::sensitiveTitle($item['title']))return $finish('Sandra, ese correo parece contener datos de acceso. Revísalo directamente en Gmail; no mostraré claves ni códigos por WhatsApp.',$context,['coverage'=>'CONTENT_NOT_READ']);
            $mail=$this->google->mail($item['id']);$item=$this->mailItem($mail);
            $body=self::mailText($mail['payload']??[]);$coverage='Texto del correo; adjuntos sin leer.';
            $evidence+=['source'=>'gmail','id'=>$item['id'],'received_at'=>$item['received_at'],'coverage'=>$coverage];
        }else{
            $file=$this->google->file($item['id']);$item=$this->driveItem($file);
            if(!empty($item['protected'])||self::sensitiveTitle($item['title']))return $finish('Sandra, ese archivo parece contener datos de acceso. Revísalo directamente en Drive; no mostraré claves por WhatsApp.',$context,['coverage'=>'CONTENT_NOT_READ']);
            if(($file['mimeType']??'')==='application/vnd.google-apps.folder'){
                $found=$this->google->listFiles("trashed = false and '".$file['id']."' in parents",null,5);
                return $this->listing('drive',array_map(fn($f)=>$this->driveItem($f),$found['files']??[]),!empty($found['nextPageToken']),$now,['kind'=>'folder_list','parent'=>$file['id'],'checked_at'=>gmdate('c',$now)]);
            }
            $read=$this->document($file);$body=$read['text'];$coverage=$read['coverage'];
            $evidence+=['source'=>'drive','id'=>$item['id'],'modified_at'=>$file['modifiedTime']??null,'coverage'=>$coverage,'bytes_sha256'=>$read['sha256']??null];
        }
        $excerpt=self::clean($body,900);$evidence['content_sha256']=hash('sha256',$body);$evidence['characters_extracted']=mb_strlen($body);$evidence['characters_shown']=mb_strlen($excerpt);
        $reply=$excerpt!==''?"Sandra, este es un fragmento de «".$item['title']."»:\n\n“".$excerpt."”\n\n".$coverage:"Encontré «".$item['title']."», Sandra, pero no pude extraer texto legible. ".$coverage;
        $reply.="\nFuente: ".$item['url']."\nEs contenido de la fuente; por sí solo no confirma un resultado ni un vencimiento judicial.";
        return $finish($reply,$context,$evidence);
    }

    private function listing(string $source,array $items,bool $more,int $now,array $evidence):array
    {
        $reply=$items?"Sandra, encontré estos resultados en ".($source==='gmail'?'el correo':'Drive').":":"Sandra, no encontré coincidencias en esta búsqueda. Podemos intentar con otro nombre o radicado.";
        foreach($items as $i=>$item)$reply.="\n".($i+1).'. '.$item['title'].' · '.$item['date_label'].' '.$item['date'];
        if($items)$reply.="\n".($more?'Hay más resultados; esta es solo una primera parte. ':'')."¿Cuál quieres que lea? Puedes decirme: «Jeison, lee el primero».";
        return ['reply'=>$reply,'context'=>['source'=>$source,'items'=>$items,'expires'=>$now+900],'evidence'=>array_replace($evidence,['source'=>$source,'coverage'=>'LISTED_ONLY','ids'=>array_column($items,'id'),'has_more'=>$more])];
    }
    private function mailItem(array $mail):array
    {
        $headers=[];foreach($mail['payload']['headers']??[] as $h)$headers[strtolower($h['name']??'')]=$h['value']??'';
        $at=(int)floor((int)($mail['internalDate']??0)/1000);$date=$at?(new DateTimeImmutable('@'.$at))->setTimezone(new DateTimeZone('America/Bogota'))->format('d/m/Y H:i'):'fecha no disponible';
        $protected=self::sensitiveTitle($headers['subject']??'');
        return ['id'=>$mail['id'],'protected'=>$protected,'title'=>$protected?'Correo de acceso: revisar directamente en Gmail':self::clean($headers['subject']??'Sin asunto',100),'date'=>$date,'date_label'=>'recibido','received_at'=>$at?gmdate('c',$at):null,'url'=>'https://mail.google.com/mail/u/?authuser='.rawurlencode(GoogleSources::ACCOUNT).'#all/'.($mail['threadId']??$mail['id'])];
    }
    private function driveItem(array $file):array
    {
        $date='fecha no disponible';if(!empty($file['modifiedTime']))$date=(new DateTimeImmutable($file['modifiedTime']))->setTimezone(new DateTimeZone('America/Bogota'))->format('d/m/Y H:i');
        $protected=self::sensitiveTitle($file['name']??'');
        return ['id'=>$file['id'],'protected'=>$protected,'title'=>$protected?'Archivo de acceso: revisar directamente en Drive':self::clean($file['name']??'Sin nombre',100),'date'=>$date,'date_label'=>'modificado','url'=>($file['mimeType']??'')==='application/vnd.google-apps.folder'?'https://drive.google.com/drive/folders/'.$file['id']:'https://drive.google.com/file/d/'.$file['id'].'/view','mime'=>$file['mimeType']??''];
    }
    private static function sensitiveTitle(string $title):bool
    {return (bool)preg_match('/(?:contrase[nñ]a|password|credencial|secret|api.?key|clave de acceso|c[oó]digo de (?:acceso|verificaci[oó]n)|verification code|sign.in|login|one.time|otp\b|\b[0-9]{6}\b)/iu',$title);}
    public static function clean(string $text,int $limit=900):string
    {
        $text=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{202A}-\x{202E}\x{2066}-\x{2069}]/u','',mb_convert_encoding($text,'UTF-8','UTF-8'));
        $text=preg_replace('~https?://\S+|www\.\S+~iu','[enlace del documento]',$text);
        $text=preg_replace('/\b(?:sk-[a-zA-Z0-9_-]{12,}|AIza[a-zA-Z0-9_-]{20,})\b/u','[dato reservado]',$text);
        $text=preg_replace('/\b(contrase[nñ]a|password|api.?key|token|c[oó]digo de (?:acceso|verificaci[oó]n))\s*[:=]?\s*\S+/iu','$1: [dato reservado]',$text);
        $text=trim(preg_replace('/\s+/u',' ',$text));
        return mb_strlen($text)>$limit?mb_substr($text,0,$limit).'…':$text;
    }
    public static function mailText(array $part,int $depth=0):string
    {
        if($depth>12||!empty($part['filename']))return '';
        $plain=[];$html=[];
        foreach($part['parts']??[] as $child){$t=self::mailText($child,$depth+1);if($t!==''){if(($child['mimeType']??'')==='text/html')$html[]=$t;else $plain[]=$t;}}
        if($plain||$html)return mb_substr(implode("\n",$plain?:$html),0,100000);
        if(!in_array($part['mimeType']??'',['text/plain','text/html'],true)||empty($part['body']['data']))return '';
        $raw=base64_decode(strtr($part['body']['data'],'-_','+/'),true);if($raw===false)return '';
        if(($part['mimeType']??'')==='text/html'){$raw=preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is','',$raw);$raw=html_entity_decode(strip_tags($raw),ENT_QUOTES|ENT_HTML5,'UTF-8');}
        return mb_substr(mb_convert_encoding($raw,'UTF-8','UTF-8'),0,100000);
    }
    private function document(array $file):array
    {
        $mime=$file['mimeType']??'';
        if(!in_array($mime,['application/vnd.google-apps.document','text/plain','application/pdf','application/vnd.openxmlformats-officedocument.wordprocessingml.document'],true))return ['text'=>'','coverage'=>'Este formato necesita lectura manual; solo se consultaron sus datos.'];
        $bytes=$this->google->fileBytes($file);$sha=hash('sha256',$bytes);
        if(in_array($mime,['application/vnd.google-apps.document','text/plain'],true))return ['text'=>mb_substr(mb_convert_encoding($bytes,'UTF-8','UTF-8'),0,100000),'coverage'=>'Fragmento de texto extraído; no es revisión integral del documento.','sha256'=>$sha];
        $path=tempnam($this->root,'source-');chmod($path,0600);file_put_contents($path,$bytes);
        try{
            if($mime==='application/vnd.openxmlformats-officedocument.wordprocessingml.document'){
                $z=new \ZipArchive();if($z->open($path)!==true)throw new RuntimeException('DOCUMENT_UNREADABLE');
                try{$stat=$z->statName('word/document.xml');if(!$stat||$stat['size']>2*1024*1024)throw new RuntimeException('DOCUMENT_TOO_LARGE');$xml=$z->getFromName('word/document.xml');}finally{$z->close();}
                if(stripos($xml,'<!DOCTYPE')!==false||stripos($xml,'<!ENTITY')!==false)throw new RuntimeException('DOCUMENT_UNSAFE_XML');
                $doc=new \DOMDocument();if(!@$doc->loadXML($xml,LIBXML_NONET))throw new RuntimeException('DOCUMENT_UNREADABLE');
                $xp=new \DOMXPath($doc);$xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');$chunks=[];
                foreach($xp->query('//w:p') as $p)$chunks[]=$p->textContent;
                return ['text'=>mb_substr(implode("\n",$chunks),0,100000),'coverage'=>'Texto principal de Word, sin paginación verificada; tablas, imágenes, encabezados y notas requieren revisión del original.','sha256'=>$sha];
            }
            if(!is_executable('/usr/bin/pdftotext') && is_file(storage_path('app/private/abogados-bot/pdf-reader/vendor/autoload.php'))){
                $p=proc_open(['/usr/bin/timeout','15',PHP_BINARY,'-d','memory_limit=128M',dirname(__DIR__,3).'/scripts/abogados-pdf-text.php',$path],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['file','/dev/null','w']],$pipes);
                if(!is_resource($p))throw new RuntimeException('PDF_READER_UNAVAILABLE');
                $json=stream_get_contents($pipes[1],120000);fclose($pipes[1]);$rc=proc_close($p);$result=$rc===0?json_decode($json,true):null;
                if(!is_array($result))return ['text'=>'','coverage'=>'PDF no legible en la consulta limitada; requiere revisión visual.','sha256'=>$sha];
                return ['text'=>$result['text'],'coverage'=>'Texto extraído de '.$result['pages_extracted'].' de '.$result['pages_total'].' páginas. '.($result['pages_total']>$result['pages_extracted']?'Páginas restantes e imágenes sin revisar.':'Imágenes y disposición visual sin revisar.'),'sha256'=>$sha];
            }
            if(!is_executable('/usr/bin/pdftotext')||!is_executable('/usr/bin/timeout'))return ['text'=>'','coverage'=>'PDF localizado; falta un lector de texto disponible.','sha256'=>$sha];
            $out=$path.'.txt';
            $p=proc_open(['/usr/bin/timeout','12','/usr/bin/pdftotext','-f','1','-l','3','-enc','UTF-8','-layout',$path,$out],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
            $rc=is_resource($p)?proc_close($p):1;
            try{$txt=$rc===0&&is_file($out)?file_get_contents($out,false,null,0,100000):'';}finally{if(is_file($out))unlink($out);}
            return ['text'=>$txt,'coverage'=>$txt!==''?'Texto extraído de hasta las primeras 3 páginas del PDF; páginas restantes e imágenes sin revisar.':'PDF sin texto extraíble; requiere revisión visual u OCR. No se ha leído su contenido.','sha256'=>$sha];
        }finally{if(is_file($path))unlink($path);}
    }
}
