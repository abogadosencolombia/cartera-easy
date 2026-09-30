<?php
namespace App\Services\AbogadosBot;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Exact, reviewed administrative changes. Never changes parties, money, dates or legal status. */
final class ProgramAdmin
{
    public function __construct(private Runtime $bot, private ?\Closure $store=null) {
        $bot->query('CREATE TABLE IF NOT EXISTS program_requests(event TEXT PRIMARY KEY,body TEXT,state TEXT,created INTEGER)');
    }
    public static function plan(string $text):?array {
        $s=ChiefConversation::body($text);
        if(!preg_match('/^(?:por favor )?(?:agrega|anade|añade|registra|guarda|actualiza|cambia|modifica)\b/u',$s)||!preg_match('/\b(?:nota|observacion|enlace|link)\b/u',$s))return null;
        if(preg_match_all('/(?<!\d)\d{23}(?!\d)/',$text,$m)!==1)return ['ask'=>'¿Cuál es el radicado de 23 dígitos del proceso que quieres actualizar?'];
        $radicado=$m[0][0];
        if(preg_match('/\b(?:nota|observacion)\b/u',$s)) {
            if(!preg_match('/[«“"]([^»”"]{1,2000})[»”"]/u',$text,$v))return ['ask'=>'¿Qué nota exacta quieres agregar? Escríbela entre comillas junto con el radicado.'];
            if(preg_match('/\b(?:borra|elimina|reemplaza|sustituye)\b/u',$s))return ['ask'=>'Puedo agregar una nota conservando las anteriores. ¿Qué texto quieres añadir?'];
            if(preg_match('/[<>\x00-\x08\x0b\x0c\x0e-\x1f]/u',$v[1]))return ['ask'=>'La nota contiene caracteres que no puedo guardar. Envíamela como texto sencillo.'];
            return ['radicado'=>$radicado,'operation'=>'note','value'=>trim($v[1])];
        }
        if(preg_match_all('~https://[^\s<>"»”]+~u',$text,$urls)!==1)return ['ask'=>'¿Cuál es el enlace HTTPS exacto que debo guardar junto con ese radicado?'];
        $url=rtrim($urls[0][0],'.;,');$p=parse_url($url);$host=strtolower($p['host']??'');
        $drive=$host==='drive.google.com'||$host==='docs.google.com';
        $judicial=$host==='ramajudicial.gov.co'||str_ends_with($host,'.ramajudicial.gov.co')||$host==='fiscalia.gov.co'||str_ends_with($host,'.fiscalia.gov.co');
        if(isset($p['user'])||isset($p['pass'])||isset($p['port'])||(!$drive&&!$judicial))return ['ask'=>'Necesito un enlace de Drive o de un sitio oficial de la Rama Judicial o Fiscalía. No guardé el enlace recibido.'];
        return ['radicado'=>$radicado,'operation'=>$drive?'drive':'file','value'=>$url];
    }
    public function pending():?array {
        $r=$this->bot->query("SELECT * FROM program_requests WHERE state='PREVIEW' AND created>? ORDER BY created DESC,rowid DESC LIMIT 1",[time()-1800])->fetch();
        return $r?['event'=>$r['event'],'plan'=>json_decode(Crypt::decryptString($r['body']),true)]:null;
    }
    public static function confirm(string $text):bool {return (bool)preg_match('/^(?:si|si confirmo|confirmo|confirmo el cambio|si guardalo|guardalo|adelante|correcto)$/u',ChiefConversation::body($text));}
    public function handle(array $e,string $text):?string {
        if($e['chat']!==Policy::SANDRA)throw new RuntimeException('ADMIN_IDENTITY');
        $d=json_decode(Crypt::decryptString($e['body']),true);if(Policy::forwarded($d['message']??[]))throw new RuntimeException('ADMIN_FORWARD');
        $pending=$this->pending();$plan=self::plan($text);
        if($pending&&self::confirm($text)) {
            $last=$this->bot->query("SELECT id FROM outbox WHERE chat=? AND internal=0 AND state IN ('ACCEPTED','DELIVERED','READ') AND created<=? ORDER BY created DESC,rowid DESC LIMIT 1",[Policy::SANDRA,$e['at']])->fetchColumn();
            if($last!==$pending['event'].'|reply')return 'Sandra, primero necesito mostrarte el cambio exacto para que lo confirmes. No he modificado el proceso.';
            $result=$this->persist($pending['plan']+['confirmation_event'=>$e['id']],$pending['event']);
            $this->bot->query("UPDATE program_requests SET state='DONE' WHERE event=?",[$pending['event']]);
            return 'Listo, Sandra. '.($pending['plan']['operation']==='note'?'La nota quedó agregada':'El enlace quedó actualizado').' en el proceso '.$pending['plan']['radicado'].'.';
        }
        if($pending&&preg_match('/^(?:no|cancela|cancelalo|no lo cambies)$/u',ChiefConversation::body($text))){$this->bot->query("UPDATE program_requests SET state='CANCELLED' WHERE event=?",[$pending['event']]);return 'Listo, Sandra. Cancelé ese cambio; el proceso conserva sus datos.';}
        if(!$plan)return null;if(isset($plan['ask']))return $plan['ask'];
        $snapshot=$this->persist($plan,null);$plan+=$snapshot;
        $this->bot->query("UPDATE program_requests SET state='SUPERSEDED' WHERE state='PREVIEW' AND event!=?",[$e['id']]);
        $this->bot->query("INSERT OR IGNORE INTO program_requests VALUES(?,?,'PREVIEW',?)",[$e['id'],Crypt::encryptString(json_encode($plan)),time()]);
        return 'Sandra, en el proceso '.$plan['radicado'].' voy a '.($plan['operation']==='note'?'agregar esta nota, conservando las anteriores':'actualizar el enlace').': «'.$plan['value'].'». ¿Confirmas que lo guarde?';
    }
    private function persist(array $plan,?string $event):array {
        if($this->store)return ($this->store)($plan,$event);
        return self::store(DB::connection(),$plan,$event);
    }
    public static function store($db,array $plan,?string $event):array {
        return $db->transaction(function()use($db,$plan,$event){
            $matches=[];
            foreach(['casos','proceso_radicados'] as $table)foreach($db->table($table)->whereNull('deleted_at')->where('radicado',$plan['radicado'])->lockForUpdate()->get() as $row)$matches[]=['table'=>$table,'row'=>$row];
            if(count($matches)!==1)throw new RuntimeException(count($matches)?'ADMIN_AMBIGUOUS':'ADMIN_NOT_FOUND');
            ['table'=>$table,'row'=>$row]=$matches[0];
            $field=match($plan['operation']){'note'=>$table==='casos'?'notas_legales':'observaciones','drive'=>$table==='casos'?'link_drive':'ubicacion_drive','file'=>'link_expediente',default=>throw new RuntimeException('ADMIN_OPERATION')};
            $type=$table==='casos'?'App\\Models\\Caso':'App\\Models\\ProcesoRadicado';
            $old=$row->$field??null;$snapshot=['table'=>$table,'id'=>$row->id,'field'=>$field,'before_hash'=>hash('sha256',json_encode($old))];
            if(!$event)return $snapshot;
            // Lock on the process serializes retries. Audit and field write commit together.
            if($db->table('auditoria_eventos')->where('evento','BOT_ADMIN_CHANGE')->where('descripcion_breve','WhatsApp '.$event)->where('auditable_type',$type)->where('auditable_id',$row->id)->exists())return ['saved'=>true,'duplicate'=>true];
            foreach($snapshot as $k=>$v)if(($plan[$k]??null)!==$v)throw new RuntimeException('ADMIN_CHANGED_SINCE_PREVIEW');
            $value=$plan['operation']==='note'?trim((string)$old)."\n\n[Nota administrativa de Sandra, WhatsApp ".$event."]\n".$plan['value']:$plan['value'];
            $db->table($table)->where('id',$row->id)->update([$field=>$value,'updated_at'=>now()]);
            $db->table('auditoria_eventos')->insert(['user_id'=>null,'evento'=>'BOT_ADMIN_CHANGE','descripcion_breve'=>'WhatsApp '.$event,'auditable_id'=>$row->id,'auditable_type'=>$type,'criticidad'=>'baja','detalle_anterior'=>json_encode([$field=>$old]),'detalle_nuevo'=>json_encode([$field=>$value,'actor_phone'=>Policy::SANDRA,'source_event'=>$event,'confirmation_event'=>$plan['confirmation_event']??null,'legal_result_verified'=>false]),'user_agent'=>'AbogadosBot/Administrative','created_at'=>now(),'updated_at'=>now()]);
            if($db->table($table)->where('id',$row->id)->value($field)!==$value)throw new RuntimeException('ADMIN_WRITE_NOT_VERIFIED');
            return ['saved'=>true,'duplicate'=>false];
        });
    }
    public static function error(string $code):string {return match($code){'ADMIN_NOT_FOUND'=>'No encontré un proceso único con ese radicado. ¿Puedes verificarlo?', 'ADMIN_AMBIGUOUS'=>'Ese radicado aparece en más de un registro. Necesito identificar el correcto antes de modificarlo.', 'ADMIN_CHANGED_SINCE_PREVIEW'=>'El dato cambió después de mostrártelo. Necesito preparar de nuevo el cambio con la información actual.',default=>'Sandra, no pude completar el cambio. Quedó pendiente de revisión; no voy a confirmarlo como guardado.'};}
}
