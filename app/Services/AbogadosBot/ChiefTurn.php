<?php
namespace App\Services\AbogadosBot;
use Illuminate\Support\Facades\Crypt;
final class ChiefTurn
{
    public const VERSION='verified-directed-turn-without-release-v1';
    public static function informative(string $text): ?string
    {
        $s=ChiefConversation::body($text);
        if(preg_match('/\b(?:dijo|dice|dijeron|reenviado)\b/u',$s))return null;
        if(preg_match('/^(?:estas (?:funcionando|ahi|activo|disponible)|me escuchas|puedes responder)$/u',$s))return 'Sí, Sandra. Recibí tu mensaje y puedo atenderte por aquí.';
        if(($topic=Policy::capability($text))!==null)return ChiefConversation::reply($topic);
        if(preg_match('/^(?:tu )?(?:ya has entrado a|has consultado|sabes manejar|puedes consultar|puedes revisar) (?:mono ?legal)(?: lo sabes manejar bien)?$/u',$s))return 'Sandra, la revisión de Monolegal disponible es parcial. Todavía no tengo una conexión automática para consultarlo directamente. Puedo buscar el proceso en el programa, Gmail o Drive; no confirmo términos sin revisar la providencia.';
        return null;
    }
    public static function pending(Runtime $bot,array $event,string $text): string
    {
        if($event['chat']!==Policy::SANDRA)throw new \RuntimeException('CHIEF_REQUIRED');
        $bot->query('CREATE TABLE IF NOT EXISTS chief_turn_requests(event TEXT PRIMARY KEY,body TEXT,state TEXT,at INTEGER)');
        $bot->query('INSERT OR IGNORE INTO chief_turn_requests VALUES(?,?,?,?)',[$event['id'],Crypt::encryptString($text),'REVIEW',$event['at']]);
        return 'Sandra, recibí tu solicitud y quedó guardada para revisión. Todavía no he ejecutado esa gestión.';
    }
}
