<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\IntegracionToken;

class ChatwootService
{
    private $baseUrl = 'https://chatwoot.servilutioncrm.cloud/api/v1';
    private $accountId = '1';
    private $apiToken = 'rVDjuhVPWDUEVRaNzjnoQdhV';

    /**
     * Envía un mensaje. Si no hay conversationId, intenta crear uno para el usuario.
     */
    public function sendMessage($conversationId, $content, $user = null)
    {
        if (!$conversationId && $user) {
            $conversationId = $this->getOrCreateConversation($user);
        }

        if (!$conversationId) return null;

        $url = "{$this->baseUrl}/accounts/{$this->accountId}/conversations/{$conversationId}/messages";
        
        try {
            $response = Http::withHeaders([
                'api_access_token' => $this->apiToken,
            ])->post($url, [
                'content' => $content,
                'message_type' => 'outgoing',
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error("[Chatwoot API] Error enviando mensaje: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Crea un contacto y una conversación en Chatwoot para el usuario actual.
     */
    public function getOrCreateConversation($user)
    {
        try {
            // 1. Crear o buscar contacto
            $contactUrl = "{$this->baseUrl}/accounts/{$this->accountId}/contacts";
            $contactResponse = Http::withHeaders(['api_access_token' => $this->apiToken])->post($contactUrl, [
                'name' => $user->name,
                'email' => $user->email,
                'custom_attributes' => ['user_id' => $user->id]
            ]);

            $contactId = $contactResponse->json()['payload']['contact']['id'] ?? null;

            if (!$contactId) {
                // Si ya existe, lo buscamos
                $searchUrl = "{$this->baseUrl}/accounts/{$this->accountId}/contacts/search?q={$user->email}";
                $searchResponse = Http::withHeaders(['api_access_token' => $this->apiToken])->get($searchUrl);
                $contactId = $searchResponse->json()['payload'][0]['id'] ?? null;
            }

            // 2. Crear conversación (usando el inbox tipo 'Website' o el primero disponible)
            $convUrl = "{$this->baseUrl}/accounts/{$this->accountId}/conversations";
            $convResponse = Http::withHeaders(['api_access_token' => $this->apiToken])->post($convUrl, [
                'source_id' => 'laravel-app-' . $user->id,
                'contact_id' => $contactId,
                'inbox_id' => 1 // Ajusta este ID según tus buzones en Chatwoot
            ]);

            return $convResponse->json()['id'] ?? null;

        } catch (\Exception $e) {
            Log::error("[Chatwoot API] Error creando conversación: " . $e->getMessage());
            return null;
        }
    }
}
