<?php
namespace App\Services\AbogadosBot;

/** Conversation helpers do not grant source access or permission to change records. */
final class ChiefConversation
{
    public static function body(string $text): string
    {
        $s=Policy::normalize($text);
        $s=preg_replace('/^[¡¿ ]*(?:hola[,! ]*|buen(?:os dias|as tardes|as noches)[,! ]*)?(?:abogado\s+)?jeison\b[, :!¿?-]*/u','',$s);
        return trim($s," \t\n\r\0\x0B¿?¡!.");
    }

    public static function clarification(string $text): bool
    {
        return (bool)preg_match('/^(?:no (?:comprendo|entiendo)(?: (?:que quisiste decir|lo que (?:dices|dijiste)|que (?:dices|dijiste)|tu respuesta|eso))?|no entendi(?: (?:que quisiste decir|tu respuesta|eso))?|(?:me )?explicas(?: (?:mejor|eso|otra vez))?|explicame(?: (?:mejor|eso|otra vez))?|que (?:quieres|quisiste) decir|a que te refieres|no me quedo claro)(?: por favor)?$/u',self::body($text));
    }

    public static function reply(string $topic,bool $clarify=false): string
    {
        $prefix=$clarify?'Perdón, Sandra, me explico mejor. ':'';
        return $prefix.match($topic){
            'audio'=>'Sí, puedes enviarme audios. Si alguna parte no se entiende, te pediré que me la aclares.',
            'judiciary'=>'Aún no puedo consultar directamente la Rama Judicial. Sí puedo buscar el radicado en el programa de Abogados. ¿Cuál necesitas revisar?',
            'changes'=>'Todavía no puedo modificar expedientes. Sí puedo consultar la información registrada. ¿Qué dato necesitas revisar?',
            'cases'=>'Puedo buscar el proceso en el programa de Abogados. Envíame el radicado de 23 dígitos y reviso qué información aparece.',
            'sources'=>'Puedo buscar correos o archivos y mostrarte su contenido disponible. Por ejemplo: «Jeison, muéstrame los correos de hoy». ¿Qué quieres revisar?',
            default=>'Puedo buscar un proceso por su radicado, consultar tus correos y localizar documentos en Drive. También recibo audios y registro solicitudes. ¿Qué quieres revisar primero?',
        };
    }

    public static function programRequest(string $text,bool $awaitingRadicado): ?string
    {
        $s=self::body($text);
        if($awaitingRadicado && preg_match('/^(?:(?:el )?radicado (?:es )?)?(\d{23})$/D',$s,$m))return $m[1];
        if(preg_match('/^(?:por favor )?(?:busca|consulta|revisa|mira)(?: en el programa)? (?:el )?(?:proceso |radicado )?(\d{23})(?: en el programa(?: de abogados)?)?(?: por favor)?$/D',$s,$m))return $m[1];
        return null;
    }

    public static function unsupported(string $text): string
    {
        $s=self::body($text);
        if(preg_match('/\b(?:cambia|cambiar|modifica|modificar|actualiza|actualizar|borra|borrar|elimina|eliminar)\b/u',$s))return 'Sandra, todavía no puedo hacer cambios en los expedientes. No he modificado ningún dato. ¿Qué información necesitas consultar?';
        if(preg_match('/\b(?:rama judicial|monolegal)\b/u',$s))return 'Sandra, todavía no puedo hacer esa consulta directamente. Puedo buscar el proceso en el programa o localizar sus correos y documentos. ¿Qué radicado revisamos?';
        if(preg_match('/\b(?:envia|enviar|reenvia|reenviar|paga|pagar|cobra|cobrar|radica|radicar|firma|firmar|acepta|aceptar|acuerdo)\b/u',$s))return 'Sandra, esa acción necesita revisión antes de realizarse. Dejé tu solicitud registrada y aún no la he ejecutado.';
        return 'Claro, Sandra. ¿Qué necesitas que revise: un proceso, un correo o un archivo?';
    }

    public static function needsReview(string $text): bool
    {
        return (bool)preg_match('/\b(?:cambia|cambiar|modifica|modificar|actualiza|actualizar|borra|borrar|elimina|eliminar|envia|enviar|reenvia|reenviar|paga|pagar|cobra|cobrar|radica|radicar|firma|firmar|acepta|aceptar|acuerdo)\b/u',self::body($text));
    }
}
