<?php
namespace App\Services\AbogadosBot;

use RuntimeException;
use Throwable;

/** Internal, account-bound notifications. No Gmail mutations or legal decisions. */
final class JudicialMail
{
    public function __construct(private Runtime $bot, private object $google)
    {
        $bot->query('CREATE TABLE IF NOT EXISTS judicial_mail_rule(id INTEGER PRIMARY KEY CHECK(id=1), enabled INTEGER, activated INTEGER, source_event TEXT, checked INTEGER DEFAULT 0, watermark INTEGER, until_at INTEGER DEFAULT 0, page TEXT, fault TEXT DEFAULT NULL)');
        $bot->query('CREATE TABLE IF NOT EXISTS judicial_mail_seen(id TEXT PRIMARY KEY, received INTEGER, state TEXT, checked INTEGER)');
    }
    public static function requested(string $text):bool
    {
        $s=Policy::normalize($text);
        if(preg_match('/\b(?:no|nunca|deja|suspende|cancela|borra|elimina)\b/u',$s))return false;
        return (bool)(preg_match('/\b(?:cada|cuando|siempre)\b/u',$s)&&preg_match('/\bcorreo\w*\b/u',$s)&&preg_match('/\b(?:juzgado\w*|fiscalia\w*)\b/u',$s)&&preg_match('/\b(?:compart\w*|envi\w*|avis\w*|notific\w*)\b/u',$s)&&preg_match('/\b(?:grupo|equipo)\b/u',$s)&&preg_match('/\b(?:mi|mio|personal)\b/u',$s));
    }
    public function active():bool
    {return (bool)$this->bot->query('SELECT enabled FROM judicial_mail_rule WHERE id=1')->fetchColumn();}
    /** Deployment invokes this only with the verified original Sandra event and owner authorization. */
    public function activate(string $event,int $now):void
    {
        $e=$this->bot->query('SELECT chat,body FROM events WHERE id=?',[$event])->fetch();
        if(!$e||$e['chat']!==Policy::SANDRA)throw new RuntimeException('MAIL_RULE_AUTHORITY');
        $d=json_decode(\Illuminate\Support\Facades\Crypt::decryptString($e['body']),true);
        $text=$d['transcript']??Policy::text($d['message']??[]);
        if(Policy::forwarded($d['message']??[])||!Policy::directed($text)||!self::requested($text))throw new RuntimeException('MAIL_RULE_NOT_DIRECT');
        if(($this->google->verifyIdentity()['account']??'')!==GoogleSources::ACCOUNT)throw new RuntimeException('MAIL_RULE_ACCOUNT');
        $this->bot->query('INSERT OR IGNORE INTO judicial_mail_rule(id,enabled,activated,source_event,watermark) VALUES(1,1,?,?,?)',[$now,$event,$now]);
    }
    public static function inspect(array $mail):array
    {
        $h=[];foreach($mail['payload']['headers']??[] as $v){$n=strtolower($v['name']??'');$h[$n][]=$v['value']??'';}
        $from=$h['from'][0]??'';
        preg_match_all('/[A-Z0-9.!#$%&\x27*+\/=?^_`{|}~-]+@([A-Z0-9.-]+)/i',$from,$m);
        if(count($m[0])!==1)return ['state'=>'NOT_OFFICIAL'];
        $domain=strtolower($m[1][0]);
        if(!preg_match('/^(?:[a-z0-9-]+\.)*(?:ramajudicial\.gov\.co|fiscalia\.gov\.co)$/D',$domain))return ['state'=>'NOT_OFFICIAL'];
        $subject=$h['subject'][0]??'Sin asunto';
        if(preg_match('/(?:password|contrase[nñ]a|c[oó]digo de (?:acceso|verificaci[oó]n)|api.?key|\bOTP\b)/iu',$subject))return ['state'=>'PROTECTED'];
        // Gmail prepends its own Authentication-Results; lower copies cannot confer trust.
        $auth=$h['authentication-results'][0]??'';
        $verified=preg_match('/^\s*mx\.google\.com\s*;/i',$auth)&&preg_match('/\bdmarc=pass\b[^;]*\bheader\.from='.preg_quote($domain,'/').'(?:\s|;|$)/i',$auth);
        return ['state'=>$verified?'VERIFIED':'AUTH_REVIEW','sender'=>strtolower($m[0][0]),'subject'=>SourceConversation::clean($subject,180),'received'=>(int)floor((int)($mail['internalDate']??0)/1000)];
    }
    public static function message(array $meta,string $id):string
    {
        $date=(new \DateTimeImmutable('@'.$meta['received']))->setTimezone(new \DateTimeZone('America/Bogota'))->format('d/m/Y g:i a');
        $lead=$meta['state']==='VERIFIED'?'Llegó un correo de una autoridad judicial.':'Llegó un correo que usa una dirección judicial; falta comprobar su autenticidad.';
        return $lead."\nDe: ".$meta['sender']."\nAsunto: ".$meta['subject']."\nRecibido: ".$date."\nAbrir correo: https://mail.google.com/mail/u/?authuser=".rawurlencode(GoogleSources::ACCOUNT).'#all/'.$id."\nRevisen el correo y sus adjuntos para identificar qué actuación requiere. Este aviso no calcula vencimientos.";
    }
    public function tick(?int $now=null):array
    {
        $now??=time();$s=$this->bot->query('SELECT * FROM judicial_mail_rule WHERE id=1')->fetch();
        if(!$s||!$s['enabled'])return ['state'=>'DISABLED'];
        if($s['checked']>$now-50)return ['state'=>'RECENT'];
        $this->bot->query('UPDATE judicial_mail_rule SET checked=? WHERE id=1',[$now]);
        try{
            if(($this->google->verifyIdentity()['account']??'')!==GoogleSources::ACCOUNT)throw new RuntimeException('MAIL_ACCOUNT');
            $until=$s['until_at']?:$now;
            $q='-in:spam -in:trash -in:sent {from:ramajudicial.gov.co from:fiscalia.gov.co} after:'.max($s['activated']-1,$s['watermark']-120).' before:'.$until;
            $found=$this->google->listMail($q,$s['page']?:null,10);$count=0;
            foreach($found['messages']??[] as $item){
                $id=$item['id']??'';if(!preg_match('/^[a-f0-9]{8,64}$/D',$id))throw new RuntimeException('MAIL_ID');
                if($this->bot->query('SELECT 1 FROM judicial_mail_seen WHERE id=?',[$id])->fetchColumn())continue;
                $mail=$this->google->mail($id,true);$meta=self::inspect($mail);
                if(($meta['received']??0)<$s['activated'])$meta['state']='BASELINE';
                if(in_array($meta['state'],['VERIFIED','AUTH_REVIEW'],true)){
                    $message=self::message($meta,$id);
                    // Enqueue both before marking seen. INSERT OR IGNORE survives partial retry.
                    foreach([Policy::SANDRA,Policy::GROUP] as $dest)$this->bot->queue('judicial-mail|'.$id.'|'.$dest,$dest,$message,true);
                    $count++;
                }
                $this->bot->query('INSERT OR IGNORE INTO judicial_mail_seen VALUES(?,?,?,?)',[$id,$meta['received']??0,$meta['state'],$now]);
            }
            $next=$found['nextPageToken']??null;
            $this->bot->query('UPDATE judicial_mail_rule SET watermark=?,until_at=?,page=?,fault=NULL WHERE id=1',[$next?$s['watermark']:$until,$next?$until:0,$next]);
            return ['state'=>'OK','queued_mail'=>$count,'has_more'=>(bool)$next,'checked_at'=>$now];
        }catch(Throwable $ex){
            $code=preg_match('/^[A-Z_0-9]{1,60}$/D',$ex->getMessage())?$ex->getMessage():'MAIL_UNAVAILABLE';
            if(!$s['fault'])foreach([Policy::SANDRA,Policy::GROUP] as $dest)$this->bot->queue('judicial-mail-fault|'.$now.'|'.$dest,$dest,'No pude revisar los nuevos correos judiciales de Abogados. Hace falta comprobar la conexión de Google en el panel; mientras tanto, revisen Gmail directamente. https://cobrocartera.abogadosencolombiasas.com/abogados-bot',true);
            $this->bot->query('UPDATE judicial_mail_rule SET fault=? WHERE id=1',[$code]);
            return ['state'=>'ERROR','code'=>$code];
        }
    }
}
