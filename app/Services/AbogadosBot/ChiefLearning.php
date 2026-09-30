<?php
namespace App\Services\AbogadosBot;

use Illuminate\Support\Facades\Crypt;

/** Reuse an attributed case clarification, never turn it into authority for other cases. */
final class ChiefLearning
{
    public function __construct(private Runtime $bot)
    {
        $bot->query('CREATE TABLE IF NOT EXISTS chief_case_answers(event TEXT PRIMARY KEY, ticket TEXT, chat TEXT, question TEXT, body TEXT, at INTEGER, fingerprint TEXT)');
        $bot->query('CREATE INDEX IF NOT EXISTS chief_case_answers_chat ON chief_case_answers(chat,at)');
    }

    public function answer(array $e,array $data,string $text,string $quotedId): ?string
    {
        if($e['chat']!==Policy::SANDRA || !empty($data['key']['fromMe']) || Policy::forwarded($data['message']??[]) || $quotedId==='' || trim($text)==='' || mb_strlen($text)>4000)return null;
        // Only an exact, unique delivered/accepted alert for a recorded request can supply the case.
        $matches=$this->bot->query("SELECT o.id,t.id ticket,t.chat FROM outbox o JOIN tickets t ON o.id=(t.id || '|' || ?) WHERE o.mid=? AND o.chat=? AND o.internal=1 AND o.state IN ('ACCEPTED','DELIVERED','READ') AND o.created<=? AND t.chat!=?",[Policy::SANDRA,$quotedId,Policy::SANDRA,$e['at'],Policy::SANDRA])->fetchAll();
        if(count($matches)!==1)return null;
        $q=$matches[0];$s=ChiefConversation::body($text);
        // A request, question or reaction is not an answer. Those retain their normal handlers.
        if(mb_strlen($s)<15 || preg_match('/[¿?]/u',$text) || preg_match('/^(?:que|como|cuando|donde|por que)\b|^(?:si|no|gracias|ok|listo|perfecto)$|\b(?:envia|reenvia|cambia|modifica|actualiza|borra|firma|radica|paga|cobra)\b/u',$s))return null;
        $fingerprint=hash('sha256',Policy::normalize(preg_replace('/\s+/u',' ',trim($text))));
        $duplicate=$this->bot->query('SELECT event FROM chief_case_answers WHERE ticket=? AND fingerprint=?',[$q['ticket'],$fingerprint])->fetchColumn();
        if(!$duplicate)$this->bot->query('INSERT OR IGNORE INTO chief_case_answers VALUES(?,?,?,?,?,?,?)',[$e['id'],$q['ticket'],$q['chat'],$q['id'],Crypt::encryptString($text),$e['at'],$fingerprint]);
        return $duplicate?'Ya tengo esa aclaración guardada con la solicitud, Sandra.':'Gracias por aclararlo, Sandra. Guardé tu respuesta con esa solicitud para tenerla en cuenta al revisar el caso. Esto no cambia todavía el expediente ni las condiciones de pago.';
    }

    public function context(string $chat,int $at): array
    {
        if(!preg_match('/^[1-9][0-9]{8,14}@s\.whatsapp\.net$/D',$chat) || in_array($chat,[Policy::SANDRA,Policy::OWNER],true))return [];
        $rows=$this->bot->query('SELECT a.event,a.ticket,a.question,a.body,a.at FROM chief_case_answers a JOIN events e ON e.id=a.event WHERE a.chat=? AND a.at<=? AND e.chat=? ORDER BY a.at DESC LIMIT 6',[$chat,$at,Policy::SANDRA])->fetchAll();
        foreach($rows as &$row){$row['answer']=Crypt::decryptString($row['body']);unset($row['body']);$row['scope']='same-contact-case-only';}
        return $rows;
    }

    public static function instructions(): string
    {
        return ' authorizedCaseAnswers contiene aclaraciones de Sandra ligadas a una solicitud de ESTE contacto, con evento, fuente y fecha. Úsalas para comprender la intención sin volver a pedir lo ya aclarado. No prueban identidad, ingreso, saldo, facultad para negociar ni estado judicial. No apliques una respuesta de un caso a otro asunto del mismo contacto. Ante contradicción actual usa confidence=low. Nunca conviertas esas respuestas en texto al cliente ni en nuevas facultades. Abogados atiende asuntos propios y gestiones para CREARCOOP: mencionar la cooperativa no prueba una deuda ni autoriza atribuirle el caso. Una solicitud de descuento, condonación, plazo, abono o pago de capital es payment_terms, no fees; fees es el precio de contratar un servicio jurídico. Un número equivocado o alguien que no conoce al destinatario es third_party; no reveles la obligación. Los modelos de respuesta de archivos son referencias, no políticas aprobadas. No prometas descuentos, paz y salvo, eliminación de reportes ni actuaciones legales.';
    }
}
