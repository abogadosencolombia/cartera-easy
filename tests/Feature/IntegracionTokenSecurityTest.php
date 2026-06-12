<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\IntegracionTokenController;
use App\Models\IntegracionToken;
use ReflectionClass;
use Tests\TestCase;

class IntegracionTokenSecurityTest extends TestCase
{
    public function test_integration_token_model_hides_secrets_when_serialized(): void
    {
        $token = new IntegracionToken([
            'proveedor' => 'Chatwoot',
            'api_key' => 'secret-api-key',
            'client_id' => 'public-client-id',
            'client_secret' => 'secret-client-value',
            'activo' => true,
        ]);

        $serialized = $token->toArray();

        $this->assertArrayNotHasKey('api_key', $serialized);
        $this->assertArrayNotHasKey('client_secret', $serialized);
        $this->assertSame('public-client-id', $serialized['client_id']);
    }

    public function test_safe_token_payload_does_not_include_secret_values(): void
    {
        $token = new IntegracionToken([
            'proveedor' => 'Chatwoot',
            'api_key' => 'secret-api-key',
            'client_id' => 'public-client-id',
            'client_secret' => 'secret-client-value',
            'activo' => true,
        ]);
        $token->id = 10;

        $payload = $this->invokePrivateMethod(
            new IntegracionTokenController(),
            'safeTokenPayload',
            [$token]
        );

        $this->assertArrayNotHasKey('api_key', $payload);
        $this->assertArrayNotHasKey('client_secret', $payload);
        $this->assertTrue($payload['has_api_key']);
        $this->assertTrue($payload['has_client_secret']);
        $this->assertSame('public-client-id', $payload['client_id']);
    }

    public function test_blank_secret_update_fields_are_removed_before_persistence(): void
    {
        $payload = $this->invokePrivateMethod(
            new IntegracionTokenController(),
            'withoutBlankSecrets',
            [[
                'proveedor' => 'Chatwoot',
                'api_key' => '',
                'client_id' => 'new-client-id',
                'client_secret' => null,
                'activo' => true,
            ]]
        );

        $this->assertArrayNotHasKey('api_key', $payload);
        $this->assertArrayNotHasKey('client_secret', $payload);
        $this->assertSame('new-client-id', $payload['client_id']);
    }

    private function invokePrivateMethod(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $reflectedMethod = $reflection->getMethod($method);
        $reflectedMethod->setAccessible(true);

        return $reflectedMethod->invokeArgs($object, $arguments);
    }
}
