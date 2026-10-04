<?php
namespace App\Services\AbogadosBot;

use RuntimeException;
use Throwable;

/** Internal, account-bound notifications. No Gmail mutations or legal decisions. */
final class JudicialMail
{
    public const COMMUNICATION_VERSION = 'protected-subject-and-own-recipient-v2';
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
    /** A forwarded foreign mailbox cannot confer Abogados case scope. */
    public static function recipientScope(array $headers):string
    {
        $addresses=function(array $values):array{
            $out=[];foreach($values as $value){preg_match_all('/[A-Z0-9.!#$%&\x27*+\/=?^_`{|}~-]+@[A-Z0-9.-]+/i',$value,$matches);foreach($matches[0] as $address)$out[]=strtolower($address);}return array_values(array_unique($out));
        };
        $account=GoogleSources::ACCOUNT;
        $direct=$addresses(array_merge($headers['to']??[],$headers['cc']??[]));
        $forwarders=$addresses($headers['x-forwarded-for']??[]);
        if(array_diff($forwarders,[$account]))return 'OUT_OF_SCOPE';
        $forwarded=$addresses($headers['x-forwarded-to']??[]);
        $delivered=$addresses($headers['delivered-to']??[]);
        if($forwarded&&!in_array($account,$direct,true)&&(array_diff($direct,[$account])||array_diff($delivered,[$account])))return 'OUT_OF_SCOPE';
        // A directly addressed message or own delivery without a foreign forwarder
        // admits Bcc mail; missing recipient evidence stays for local review.
        if(!in_array($account,$direct,true)&&($delivered[0]??null)!==$account)return 'RECIPIENT_REVIEW';
        return 'OWN_ACCOUNT';
    }
    public static function inspect(array $mail):array
    {
        $h=[];foreach($mail['payload']['headers']??[] as $v){$n=strtolower($v['name']??'');$h[$n][]=$v['value']??'';}
        $from=$h['from'][0]??'';
        preg_match_all('/[A-Z0-9.!#$%&\x27*+\/=?^_`{|}~-]+@([A-Z0-9.-]+)/i',$from,$m);
        if(count($m[0])!==1)return ['state'=>'NOT_OFFICIAL'];
        $domain=strtolower($m[1][0]);
        if(!preg_match('/^(?:[a-z0-9-]+\.)*(?:ramajudicial\.gov\.co|fiscalia\.gov\.co)$/D',$domain))return ['state'=>'NOT_OFFICIAL'];
        $scope=self::recipientScope($h);
        if($scope!=='OWN_ACCOUNT')return ['state'=>$scope,'received'=>(int)floor((int)($mail['internalDate']??0)/1000)];
        $subject=$h['subject'][0]??'Sin asunto';
        $protectedSubject=(bool)preg_match('/(?:password|contrase[nñ]a|c[oó]digo de (?:acceso|verificaci[oó]n)|api.?key|\bOTP\b|\btoken\b)/iu',$subject);
        // Access credentials never leave Gmail. Keep a generic, authenticated notice.
        // Gmail prepends its own Authentication-Results; lower copies cannot confer trust.
        $auth=$h['authentication-results'][0]??'';
        $verified=preg_match('/^\s*mx\.google\.com\s*;/i',$auth)&&preg_match('/\bdmarc=pass\b[^;]*\bheader\.from='.preg_quote($domain,'/').'(?:\s|;|$)/i',$auth);
        // A verified automatic receipt is evidence of delivery, not a new request.
        if($verified&&preg_match('/^\s*(?:respuesta autom[aá]tica|automatic reply|auto(?:matic)? response|acuse (?:de )?recibo)\s*:/iu',$subject)&&preg_match('/^auto-(?:replied|generated)\b/i',$h['auto-submitted'][0]??''))return ['state'=>'INFORMATIONAL_RECEIPT','received'=>(int)floor((int)($mail['internalDate']??0)/1000)];
        if($protectedSubject)$subject='Información de acceso al expediente judicial (datos reservados)';
        return ['state'=>$verified?'VERIFIED':'AUTH_REVIEW','sender'=>strtolower($m[0][0]),'subject'=>SourceConversation::clean($subject,180),'protected_subject'=>$protectedSubject,'received'=>(int)floor((int)($mail['internalDate']??0)/1000)];
    }
    public static function message(array $meta,string $id):string
    {
        $date=(new \DateTimeImmutable('@'.$meta['received']))->setTimezone(new \DateTimeZone('America/Bogota'))->format('d/m/Y g:i a');
        $lead=$meta['state']==='VERIFIED'?'Correo judicial de ':'Correo de autenticidad pendiente: ';
        return $lead.$meta['sender']."\n".SourceConversation::clean($meta['subject'],90).' · '.$date."\nhttps://mail.google.com/mail/u/?authuser=".rawurlencode(GoogleSources::ACCOUNT).'#all/'.$id;
    }
    public static function answered(array $mail,array $thread):bool
    {
        if(empty($mail['threadId'])||($thread['id']??'')!==$mail['threadId'])return false;
        $headers=[];foreach($mail['payload']['headers']??[] as $h)$headers[strtolower($h['name']??'')]=$h['value']??'';
        $messageId=trim($headers['message-id']??'');if(!preg_match('/^<[^<>\s]+>$/D',$messageId))return false;
        foreach($thread['messages']??[] as $reply){
            if(!in_array('SENT',$reply['labelIds']??[],true)||(int)($reply['internalDate']??0)<=(int)($mail['internalDate']??0))continue;
            $rh=[];foreach($reply['payload']['headers']??[] as $h)$rh[strtolower($h['name']??'')]=$h['value']??'';
            preg_match_all('/[A-Z0-9.!#$%&\x27*+\/=?^_`{|}~-]+@[A-Z0-9.-]+/i',$rh['from']??'',$from);
            if(count($from[0])===1&&strtolower($from[0][0])===GoogleSources::ACCOUNT&&trim($rh['in-reply-to']??'')===$messageId)return true;
        }
        return false;
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
                if(isset($meta['received'])&&$meta['received']<$s['activated'])$meta['state']='BASELINE';
                if($meta['state']==='VERIFIED'&&!empty($mail['threadId'])&&self::answered($mail,$this->google->mailThreadMetadata($mail['threadId'])))$meta['state']='REPLIED_IN_THREAD';
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
