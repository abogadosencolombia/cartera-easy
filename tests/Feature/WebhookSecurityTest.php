<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebhookSecurityTest extends TestCase
{
    public function test_chatbot_notify_rejects_requests_when_token_is_not_configured(): void
    {
        config(['services.chatbot.webhook_token' => null]);

        $this->postJson('/api/chatbot/notify', [
            'response' => 'Mensaje de prueba',
            'userId' => 1,
        ])->assertStatus(503);
    }

    public function test_chatbot_notify_rejects_requests_with_invalid_token(): void
    {
        config(['services.chatbot.webhook_token' => 'expected-token']);

        $this->withHeader('X-Chatbot-Webhook-Token', 'wrong-token')
            ->postJson('/api/chatbot/notify', [
                'response' => 'Mensaje de prueba',
                'userId' => 1,
            ])->assertUnauthorized();
    }

    public function test_chatwoot_webhook_rejects_requests_when_token_is_not_configured(): void
    {
        config(['services.chatwoot.webhook_token' => null]);

        $this->postJson('/api/webhook/chatwoot', [
            'event' => 'message_created',
        ])->assertStatus(503);
    }

    public function test_chatwoot_webhook_rejects_requests_with_invalid_token(): void
    {
        config(['services.chatwoot.webhook_token' => 'expected-token']);

        $this->withHeader('X-Chatwoot-Webhook-Token', 'wrong-token')
            ->postJson('/api/webhook/chatwoot', [
                'event' => 'message_created',
            ])->assertUnauthorized();
    }
}
