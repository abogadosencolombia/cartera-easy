<?php

namespace App\Services\AbogadosBot;

final class Policy
{
    public const OWNER = '573152819233@s.whatsapp.net';
    public const SANDRA = '573016803926@s.whatsapp.net';
    public const GROUP = '120363408832976272@g.us';
    public const INTENTS = ['greeting','identity','thanks','new_service','professional_offer','appointment','case_status','payment','payment_terms','fees','legal','complaint','third_party','stop','unclear','other'];

    public static function normalize(string $text): string
    {
        return mb_strtolower(trim(strtr($text, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u'])));
    }

    public const PRIVATE_CHAT_GUARD = 'own-native-private-before-intake-and-send-v1';

    /** Alternate PN/LID identifiers cannot turn a status, group or channel into a private chat. */
    public static function nonDirectKey(array $key): bool
    {
        foreach (['remoteJid','remoteJidAlt'] as $field) {
            $jid=strtolower(trim((string)($key[$field]??'')));
            foreach (['@broadcast','@g.us','@newsletter'] as $suffix)
                if (str_ends_with($jid,$suffix)) return true;
        }
        return false;
    }

    public static function jid(array $key): ?string
    {
        $primary = $key['remoteJid'] ?? '';
        if (str_ends_with($primary, '@g.us')) return $primary;
        if (self::nonDirectKey($key)) return null;
        foreach ([$primary, $key['remoteJidAlt'] ?? ''] as $jid) {
            if (preg_match('/^[1-9][0-9]{8,14}@s\.whatsapp\.net$/D', $jid)) return $jid;
        }
        // A LID is never interpreted as a phone number or an authority.
        return $primary === '231670619889791@lid' ? self::SANDRA : null;
    }

    public static function text(array $message): string
    {
        return trim((string) ($message['conversation'] ?? $message['extendedTextMessage']['text'] ?? ''));
    }

    public static function forwarded(array $message): bool
    {
        foreach ($message as $part) {
            if (is_array($part) && (!empty($part['contextInfo']['isForwarded']) || ($part['contextInfo']['forwardingScore'] ?? 0) > 0)) return true;
        }
        return false;
    }

    public static function directed(string $text): bool
    {
        return (bool) preg_match('/^[¡¿ ]*(?:hola[,! ]*|buen(?:os dias|as tardes|as noches)[,! ]*)?(?:abogado\s+)?jeison\b(?:$|[, :!¿?-])/u', self::normalize($text));
    }

    /** Only questions about current capabilities may inherit an explicit conversation. */
    public static function capability(string $text): ?string
    {
        $s=self::normalize($text);
        $s=preg_replace('/^(?:(?:abogado )?jeison[, :]*[¿? ]*)?[¿ ]*(?:y )?/u','',$s);
        $s=rtrim($s,'?.! ');
        return match(true){
            (bool)preg_match('/^(?:que puedes hacer|con que puedes ayudarme|como puedes ayudarme)$/u',$s)=>'general',
            (bool)preg_match('/^(?:puedes (?:escuchar|recibir|entender) audios|recibes audios|me escuchas)$/u',$s)=>'audio',
            (bool)preg_match('/^(?:puedes )?(?:revisar|consultar) (?:la )?rama judicial(?: por mi)?$/u',$s)=>'judiciary',
            (bool)preg_match('/^(?:puedes )?hacer cambios en el programa(?: de abogados)?$/u',$s)=>'changes',
            (bool)preg_match('/^(?:puedes )?(?:revisar|consultar) (?:los )?procesos(?: por mi)?$/u',$s)=>'cases',
            default=>null,
        };
    }

    public static function release(string $text): ?string
    {
        $s = self::normalize($text);
        if (preg_match('/^(?:abogado )?jeison[, ]+retoma (?:este|el) chat[.! ]*$/u', $s)) return self::SANDRA;
        if (preg_match('/^(?:abogado )?jeison[, ]+retoma (?:el )?chat (\+?57[0-9]{10})[.! ]*$/u', $s, $m)) return ltrim($m[1], '+').'@s.whatsapp.net';
        return null;
    }

    /** Templates are the only external text; AI can classify, never send arbitrary prose. */
    public static function plan(string $intent, string $phase, bool $hasGreeting, array $known=[], string $message=''): array
    {
        $prefix = $hasGreeting && $intent !== 'greeting' ? '¡Hola! ' : '';
        // An office-hours question can mention a payment agreement as context.
        // Preserve review and do not infer an institutional schedule from that message.
        $officeHours = preg_match('/(?:^|[.!?\n])\s*[¿ ]*(?:(?:quisiera|quiero|necesito|me gustaria) (?:saber|conocer|consultar) el|cual es el|que) horario de atencion de (?:la|su) oficina\b/u', self::normalize($message));
        if ($officeHours && in_array($intent, ['appointment','payment_terms'], true)) {
            return ['reply'=>$prefix.'Con gusto te ayudo. Aún no tengo confirmado el horario de atención de la oficina.', 'phase'=>'review', 'ticket'=>'Horario de oficina: confirmar información vigente'];
        }
        $result = match ($intent) {
            'greeting' => ['reply'=>'¡Hola! Con gusto te ayudo. Cuéntame, ¿qué necesitas?', 'phase'=>'purpose', 'ticket'=>null],
            'identity' => ['reply'=>'Soy Abogado Jeison, el asistente virtual de Abogados en Colombia. ¿En qué puedo ayudarte?', 'phase'=>'purpose', 'ticket'=>null],
            'thanks' => ['reply'=>'¡Con mucho gusto!', 'phase'=>$phase, 'ticket'=>null],
            'stop' => ['reply'=>'Entendido. No continuaré enviándote mensajes automáticos.', 'phase'=>'stop', 'ticket'=>null],
            'new_service' => $phase === 'service_detail'
                ? ['reply'=>'Gracias. Tu solicitud quedó registrada.', 'phase'=>'review', 'ticket'=>'Nueva solicitud de servicio']
                : ['reply'=>'Con gusto. ¿Qué necesitas resolver y en qué ciudad?', 'phase'=>'service_detail', 'ticket'=>null],
            'professional_offer' => ['reply'=>'Gracias por ofrecer tus servicios. Tu propuesta quedó registrada para revisión.', 'phase'=>'review', 'ticket'=>'Propuesta de colaboración: revisión humana'],
            'appointment' => $phase === 'appointment_detail'
                ? ['reply'=>'Gracias. Tu solicitud quedó registrada; la cita aún no está confirmada.', 'phase'=>'review', 'ticket'=>'Solicitud de asesoría: verificar agenda y tarifa']
                : ['reply'=>'Con gusto. ¿Qué día y horario te sirven, y prefieres atención virtual o presencial?', 'phase'=>'appointment_detail', 'ticket'=>null],
            'case_status' => ['reply'=>'Tu consulta sobre el proceso quedó registrada y sigue pendiente de confirmación.', 'phase'=>'review', 'ticket'=>'Consulta de proceso: verificar identidad y fuentes'],
            'payment' => ['reply'=>'Gracias por avisarnos. El pago sigue pendiente de confirmación.', 'phase'=>'review', 'ticket'=>'Pago o acuerdo: requiere verificación y autorización'],
            'payment_terms' => ['reply'=>'Entiendo tu consulta. Las condiciones del pago siguen pendientes de confirmación.', 'phase'=>'review', 'ticket'=>'Condiciones o acuerdo de pago: requiere autorización'],
            'fees' => ['reply'=>'Con gusto. La tarifa depende del servicio que necesitas. ¿Sobre qué asunto buscas asesoría?', 'phase'=>'service_detail', 'ticket'=>null],
            'third_party' => ['reply'=>'Para compartir esa información, necesito que acredites tu autorización para recibirla.', 'phase'=>'review', 'ticket'=>'Solicitud de tercero: verificar autorización'],
            'complaint' => ['reply'=>'Lamento lo que nos cuentas. Gracias por explicarlo; dejé registrada tu inconformidad.', 'phase'=>'review', 'ticket'=>'Queja o situación que requiere atención humana'],
            'legal' => ['reply'=>'Tu solicitud quedó registrada y está pendiente de orientación profesional.', 'phase'=>'review', 'ticket'=>'Consulta jurídica: revisión profesional'],
            default => $phase === 'purpose' || $phase === 'clarify'
                ? ['reply'=>'Tu mensaje quedó registrado.', 'phase'=>'review', 'ticket'=>'Solicitud sin regla verificada']
                : ['reply'=>'¿En qué puedo ayudarte?', 'phase'=>'clarify', 'ticket'=>null],
        };
        $result['reply'] = $prefix.$result['reply'];
        if($intent==='fees' && !empty($known['service']))$result=['reply'=>$prefix.'Tu solicitud quedó registrada; la tarifa aún está pendiente de confirmación.','phase'=>'review','ticket'=>'Cotización: verificar tarifa vigente del servicio'];
        if($intent==='new_service' && $known){
            if(empty($known['service']))$result=['reply'=>$prefix.'Con gusto. Cuéntame brevemente qué necesitas resolver.','phase'=>'service_detail','ticket'=>null];
            elseif(empty($known['city']))$result=['reply'=>$prefix.'¿Desde qué ciudad nos escribes?','phase'=>'service_detail','ticket'=>null];
            else $result=['reply'=>$prefix.'Gracias. Tu solicitud quedó registrada.','phase'=>'review','ticket'=>'Nueva solicitud de servicio'];
        }
        if($intent==='appointment' && $known){
            if(empty($known['date_time']))$result=['reply'=>$prefix.'Con gusto. ¿Qué día y horario te sirven?','phase'=>'appointment_detail','ticket'=>null];
            elseif(empty($known['modality']))$result=['reply'=>$prefix.'¿Prefieres atención virtual o presencial?','phase'=>'appointment_detail','ticket'=>null];
            else $result=['reply'=>$prefix.'Gracias. Tu solicitud quedó registrada; la cita aún no está confirmada.','phase'=>'review','ticket'=>'Solicitud de asesoría: verificar agenda y tarifa'];
        }
        return $result;
    }
}
