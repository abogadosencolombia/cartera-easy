<?php
namespace App\Services\AbogadosBot;

use Illuminate\Support\Facades\Crypt;

/** Capture bounded operational intent after Runtime verifies sender, context and staff hold.
 * A request is not an activated job, a legal rule, or authority to contact a new recipient.
 */
final class ChiefOperations
{
    public function __construct(private Runtime $bot)
    {
        $bot->query('CREATE TABLE IF NOT EXISTS chief_operational_requests(event TEXT PRIMARY KEY,kind TEXT,body TEXT,state TEXT,at INTEGER)');
        $bot->query('CREATE TABLE IF NOT EXISTS chief_operational_rules(kind TEXT PRIMARY KEY,source TEXT,body TEXT,at INTEGER)');
    }
    public static function kind(string $text): ?string
    {
        $s=ChiefConversation::body($text);
        if(preg_match('/^(?:ella|ellos|dile)\b|\b(?:dijeron|dijo|dice|reenviado)\b/u',$s)||preg_match('/[«»“”]/u',$s))return null;
        if(preg_match('/\b(?:todos los dias|cada dia|diariamente)\b/u',$s)&&preg_match('/\bcorreos?\b/u',$s)&&preg_match('/\b(?:revisar|revisa|revision)\b/u',$s))return 'daily_mail';
        if(preg_match('/^(?:muchisimas |muchas )?gracias\b/u',$s)&&preg_match('/\b(?:compartir|comparte)\b.*\b(?:grupo|equipo)\b/u',$s))return 'routing_reminder';
        if(preg_match('/^(?:lo que necesito es que tu|quiero que|necesito que) (?:aprendas|estudies)\b/u',$s))return 'learning_request';
        return null;
    }
    public static function exactDailyMail(string $text): bool
    {
        $s=ChiefConversation::body($text);
        return self::kind($text)==='daily_mail'
            && (bool)preg_match('/\ba las (?:siete|7|19:00)(?: de la noche|\s*p\.?\s*m\.?)\b/u',$s)
            && (bool)preg_match('/\bgrupo\b/u',$s)
            && (bool)preg_match('/\bpendiente(?:s)?\b.*\brespuest[ao]\b/u',$s)
            && !preg_match('/\b(?:otro|otra|nuevo|nueva|todos los grupos|clientes|adjuntos|completo|cuerpos|contraseñas|claves)\b/u',$s);
    }
    public function handle(array $event,string $text,string $kind): string
    {
        if($event['chat']!==Policy::SANDRA)throw new \RuntimeException('CHIEF_REQUIRED');
        if(self::kind($text)!==$kind)throw new \RuntimeException('OPERATION_MISMATCH');
        $this->bot->query('INSERT OR IGNORE INTO chief_operational_requests VALUES(?,?,?,?,?)',[$event['id'],$kind,Crypt::encryptString($text),'REVIEW',$event['at']]);
        if($kind==='routing_reminder')return 'Gracias, Sandra. Las novedades de trabajo de Abogados se comparten contigo y con Equipo Abogados en Colombia, cuidando la información privada.';
        if($kind==='learning_request')return 'Claro, Sandra. Continuaré estudiando los documentos por partes y conservando sus fuentes. Las aclaraciones verificadas de un caso quedan ligadas a ese caso; no las tomaré como reglas para todos los demás.';
        $saved=$this->bot->query('SELECT body FROM chief_operational_rules WHERE kind=?',['daily_mail'])->fetchColumn();
        $rule=$saved?json_decode(Crypt::decryptString($saved),true):[];
        if(self::exactDailyMail($text)&&($rule['active']??false)===true&&($rule['time']??'')==='19:00'&&($rule['timezone']??'')==='America/Bogota'&&($rule['automationId']??'')==='revisi-n-nocturna-del-correo-de-abogados'){
            $this->bot->query('UPDATE chief_operational_requests SET state=? WHERE event=?',['CONFIGURED',$event['id']]);
            return 'Sí, Sandra. Está programada la revisión diaria del correo a las 7 p. m., para compartir contigo y con el equipo los asuntos pendientes de respuesta. Esta revisión necesita que el equipo y Codex estén disponibles.';
        }
        return 'Sandra, guardé tu solicitud de revisión periódica del correo. Todavía no he activado ese horario ni cambiado los destinatarios.';
    }
}
