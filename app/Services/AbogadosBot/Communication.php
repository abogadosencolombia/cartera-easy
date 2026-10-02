<?php

namespace App\Services\AbogadosBot;

final class Communication
{
    public const VERSION = 'private-routing-and-media-work-v2';

    public static function issue(string $jid, string $content, bool $internal = false): ?string
    {
        // Only the existing, exact chief/team destinations may receive internal context.
        if ($jid === Policy::SANDRA || ($internal && $jid === Policy::GROUP)) return null;
        $text = Policy::normalize($content);
        if (preg_match('/\b(?:voy|vamos|debo|debemos|estoy|estamos|necesito|necesitamos|lo|le|te|ya|hemos)\b.{0,65}\b(?:consult\w*|pregunt\w*|avis\w*|inform\w*|escal\w*|notific\w*|pedir apoyo|verific\w*|revis\w*)\b.{0,65}\b(?:diego|sandra|coordinador\w*|equipo|supervisor|personal|profesional)\b/us', $text)
            || preg_match('/\b(?:consultare|consultaremos|preguntare|avisare|informare|verificare|verificaremos|revisare|revisaremos)\b.{0,65}\b(?:diego|sandra|coordinador\w*|equipo|supervisor|personal|profesional)\b/us', $text)) return 'INTERNAL_ROUTING_DETAIL';
        if (preg_match('/\b(?:estoy|estamos|voy a|vamos a|procedere a|procederemos a)\s+(?:transcrib\w*|proces\w*|convert\w*|analiz\w*|escuch\w*)\b.{0,65}\b(?:audio|mensaje de voz|archivo|adjunto|documento)\b/us', $text)
            || preg_match('/\b(?:transcribo|transcribimos|transcribire|transcribiremos|transcripcion|transcribiendo)\b.{0,65}\b(?:audio|mensaje de voz)\b/us', $text)) return 'INTERNAL_MEDIA_PROCESS';
        if (preg_match('/\b(?:webhook|payload|api[_ -]?key|token de acceso|n8n|chatwoot|evolution api|outbox|sql)\b/u', $text)) return 'INTERNAL_TECHNICAL_DETAIL';
        if (preg_match('/(?:sk-[a-z0-9_-]{20,}|-----begin .*private key-----|bearer\s+[a-z0-9_.-]{16,})/i', $content)) return 'SECRET_PATTERN';
        return null;
    }
}
