<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatwootService
{
    private string $baseUrl;
    private string $accountId;
    private ?string $apiToken;
    private int $inboxId;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.chatwoot.url'), '/') . '/api/v1';
        $this->accountId = (string) config('services.chatwoot.account_id', '1');
        $this->apiToken = config('services.chatwoot.api_token');
        $this->inboxId = (int) config('services.chatwoot.inbox_id', 1);
    }

    /**
     * Envía un mensaje. Si no hay conversationId, intenta crear uno para el usuario.
     */
    public function sendMessage($conversationId, $content, $user = null)
    {
        if (! $this->apiToken) {
            Log::warning('[Chatwoot API] CHATWOOT_API_TOKEN no está configurado.');
            return null;
        }

        if (! $conversationId && $user) {
            $conversationId = $this->getOrCreateConversation($user);
        }

        if (! $conversationId) {
            return null;
        }

        $url = "{$this->baseUrl}/accounts/{$this->accountId}/conversations/{$conversationId}/messages";

        try {
            $response = Http::withHeaders($this->authHeaders())->post($url, [
                'content' => $content,
                'message_type' => 'outgoing',
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('[Chatwoot API] Error enviando mensaje: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Crea un contacto y una conversación en Chatwoot para el usuario actual.
     */
    public function getOrCreateConversation($user)
    {
        if (! $this->apiToken) {
            Log::warning('[Chatwoot API] CHATWOOT_API_TOKEN no está configurado.');
            return null;
        }

        try {
            $contactUrl = "{$this->baseUrl}/accounts/{$this->accountId}/contacts";
            $contactResponse = Http::withHeaders($this->authHeaders())->post($contactUrl, [
                'name' => $user->name,
                'email' => $user->email,
                'custom_attributes' => ['user_id' => $user->id],
            ]);

            $contactId = $contactResponse->json()['payload']['contact']['id'] ?? null;

            if (! $contactId) {
                $searchUrl = "{$this->baseUrl}/accounts/{$this->accountId}/contacts/search";
                $searchResponse = Http::withHeaders($this->authHeaders())->get($searchUrl, [
                    'q' => $user->email,
                ]);
                $contactId = $searchResponse->json()['payload'][0]['id'] ?? null;
            }

            if (! $contactId) {
                Log::warning('[Chatwoot API] No fue posible resolver contacto para usuario.', [
                    'user_id' => $user->id,
                ]);
                return null;
            }

            $convUrl = "{$this->baseUrl}/accounts/{$this->accountId}/conversations";
            $convResponse = Http::withHeaders($this->authHeaders())->post($convUrl, [
                'source_id' => 'laravel-app-' . $user->id,
                'contact_id' => $contactId,
                'inbox_id' => $this->inboxId,
            ]);

            return $convResponse->json()['id'] ?? null;
        } catch (\Exception $e) {
            Log::error('[Chatwoot API] Error creando conversación: ' . $e->getMessage());
            return null;
        }
    }

    private function authHeaders(): array
    {
        return ['api_access_token' => $this->apiToken];
    }
}
