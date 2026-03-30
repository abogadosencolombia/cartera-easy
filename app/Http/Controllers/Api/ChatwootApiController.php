<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatwootService;
use Illuminate\Http\Request;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;

class ChatwootApiController extends Controller
{
    protected $chatwoot;

    public function __construct(ChatwootService $chatwoot)
    {
        $this->chatwoot = $chatwoot;
    }

    /**
     * Envía un mensaje desde nuestra interfaz hacia Chatwoot
     */
    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string',
            'conversation_id' => 'nullable|integer'
        ]);

        $user = Auth::user();
        $conversationId = $validated['conversation_id'] ?? 16; // De prueba

        // 1. Guardar localmente
        $localMessage = Message::create([
            'user_id' => $user->id,
            'body' => $validated['message'],
        ]);

        // 2. Enviar a Chatwoot vía API
        $response = $this->chatwoot->sendMessage($conversationId, $validated['message']);

        return response()->json([
            'status' => 'success',
            'message' => $localMessage,
            'chatwoot_response' => $response
        ]);
    }

    /**
     * Obtener historial de mensajes (opcional)
     */
    public function getHistory()
    {
        return Message::orderBy('created_at', 'asc')->take(50)->get();
    }
}
