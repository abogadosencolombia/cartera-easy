<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntegracionExternaLog;
use App\Models\Message;
use App\Models\User;
use App\Events\ChatbotResponseReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatwootWebhookController extends Controller
{
    /**
     * Handle the incoming Chatwoot webhook.
     */
    public function handle(Request $request)
    {
        $payload = $request->all();
        $event = $payload['event'] ?? null;

        // Registrar en el log de integraciones externas
        IntegracionExternaLog::create([
            'servicio' => 'Chatwoot Webhook',
            'endpoint' => $request->path(),
            'datos_enviados' => json_encode($payload),
            'fecha_solicitud' => now(),
        ]);

        Log::info('[Chatwoot Webhook] Evento recibido: ' . $event, [
            'account_id' => $payload['account']['id'] ?? null,
            'conversation_id' => $payload['id'] ?? null,
        ]);

        if ($event === 'message_created') {
            return $this->handleMessageCreated($payload);
        }

        return response()->json(['status' => 'ignored'], 200);
    }

    /**
     * Handle the message_created event.
     */
    private function handleMessageCreated(array $payload)
    {
        $content = $payload['content'] ?? '';
        $messageType = $payload['message_type'] ?? null;
        $conversationId = $payload['conversation']['id'] ?? null;
        $sender = $payload['sender'] ?? null;

        // Solo procesamos mensajes entrantes (de clientes/usuarios)
        // message_type 0 es incoming en Chatwoot
        if ($messageType != 0) {
            return response()->json(['status' => 'skipped_outgoing'], 200);
        }

        // Buscamos si hay un userId en los custom_attributes del contacto
        // O intentamos mapear por el identifier (ej: 573167378803@g.us)
        $userId = $this->resolveUserId($sender, $payload['conversation'] ?? []);

        // Crear el mensaje localmente si es necesario para el historial
        $message = Message::create([
            'user_id' => $userId,
            'body' => $content,
        ]);

        // Broadcast a la interfaz de usuario
        if ($userId) {
            try {
                broadcast(new ChatbotResponseReceived($content, $userId));
                Log::info("[Chatwoot Webhook] Broadcast enviado para el usuario: {$userId}");
            } catch (\Exception $e) {
                Log::error("[Chatwoot Webhook] Error en broadcast: " . $e->getMessage());
            }
        }

        return response()->json([
            'status' => 'success',
            'message_id' => $message->id,
            'user_id' => $userId
        ], 200);
    }

    /**
     * Intenta resolver el ID del usuario local basado en la data de Chatwoot.
     */
    private function resolveUserId($sender, $conversation)
    {
        if (!$sender) return null;

        // 1. Intentar por custom_attributes (si lo configuramos en Chatwoot)
        if (isset($sender['custom_attributes']['user_id'])) {
            return $sender['custom_attributes']['user_id'];
        }

        // 2. Intentar por identifier (WhatsApp ID)
        $identifier = $sender['identifier'] ?? null;
        if ($identifier) {
            // Limpiar identifier de WhatsApp (ej: 57316...s@g.us -> 57316...)
            $phone = preg_replace('/[^0-9]/', '', explode('@', $identifier)[0]);
            
            // Buscar usuario por teléfono (asumiendo que hay un campo phone o similar en users)
            // Para este demo, intentaremos buscar un usuario que coincida o devolver un ID por defecto si es admin.
            // En una app real, aquí harías: User::where('phone', $phone)->first()?->id;
            Log::info("[Chatwoot Webhook] Buscando usuario para identifier: {$identifier} (Phone: {$phone})");
        }

        // Por defecto, si no encontramos, lo dejamos como null o buscamos el primer admin para notificar
        return User::where('tipo_usuario', 'admin')->first()?->id;
    }
}
