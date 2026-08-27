<?php

use App\Models\TwilioMessageAttempt;
use App\Models\TwilioStatusEvent;
use App\Services\TwilioCampaignService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

function signTwilioStatusCallback(string $url, array $params, string $token): string
{
    ksort($params, SORT_STRING);

    $payload = $url;

    foreach ($params as $key => $value) {
        $payload .= $key.$value;
    }

    return base64_encode(hash_hmac('sha1', $payload, $token, true));
}

function bootTwilioStatusCallbackDatabase(): void
{
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('c', 32)),
        'app.cipher' => 'AES-256-CBC',
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'services.twilio.account_sid' => 'AC'.str_repeat('b', 32),
        'services.twilio.api_key_sid' => 'SK'.str_repeat('e', 32),
        'services.twilio.api_key_secret' => 'test-api-key-secret',
        'services.twilio.auth_token' => 'test-auth-token',
        'services.twilio.from' => '+14709284068',
        'services.twilio.messaging_service_sid' => null,
        'services.twilio.status_callback_url' => 'https://cobrocartera.abogadosencolombiasas.com/api/twilio/status-callback',
    ]);

    DB::purge('sqlite');

    foreach ([
        '2026_08_20_170000_create_twilio_campaigns_table.php',
        '2026_08_20_170100_create_twilio_campaign_recipients_table.php',
        '2026_08_20_170200_create_twilio_message_attempts_table.php',
        '2026_08_20_170300_create_twilio_status_events_table.php',
        '2026_08_20_170600_add_authorization_trace_to_twilio_campaigns_table.php',
    ] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }
}

function acceptedTwilioAttempt(string $messageSid): TwilioMessageAttempt
{
    Http::fake([
        'api.twilio.com/*' => Http::response([
            'sid' => $messageSid,
            'status' => 'queued',
            'num_segments' => '1',
            'price' => null,
            'price_unit' => 'USD',
        ], 201),
    ]);

    $service = app(TwilioCampaignService::class);
    $campaign = $service->createFromManifest([
        'campaign_key' => 'callback-test-'.$messageSid,
        'name' => 'Callback integration test',
        'source_reference' => 'test-suite',
        'recipients' => [[
            'ordinal' => 1,
            'source_row' => 2,
            'identity_key' => 'identity-'.$messageSid,
            'role' => 'debtor',
            'phone' => '+573016803926',
            'body' => 'Abogados en Colombia S.A.S. prueba callback. STOP.',
            'segments' => 1,
            'estimated_cost' => '0.059200',
            'status' => 'ready',
        ]],
    ]);

    return $service->sendRecipient($campaign->recipients()->firstOrFail());
}

function postSignedTwilioCallback($testCase, array $params): void
{
    $signature = signTwilioStatusCallback(
        config('services.twilio.status_callback_url'),
        $params,
        config('services.twilio.auth_token'),
    );

    $testCase->withHeader('X-Twilio-Signature', $signature)
        ->post('/api/twilio/status-callback', $params)
        ->assertNoContent();
}

beforeEach(function () {
    bootTwilioStatusCallbackDatabase();
    Http::preventStrayRequests();
});

test('accepts a valid callback and links its immutable event to the send attempt', function () {
    $messageSid = 'SM'.str_repeat('a', 32);
    $attempt = acceptedTwilioAttempt($messageSid);
    expect($attempt->message_sid)->toBe($messageSid)
        ->and(TwilioMessageAttempt::query()->where('message_sid', $messageSid)->count())->toBe(1);
    $params = [
        'AccountSid' => 'AC'.str_repeat('b', 32),
        'ErrorCode' => '',
        'ErrorMessage' => '',
        'From' => '+14709284068',
        'MessageSid' => $messageSid,
        'MessageStatus' => 'delivered',
        'To' => '+573016803926',
    ];

    postSignedTwilioCallback($this, $params);

    $event = TwilioStatusEvent::query()
        ->where('source', 'callback')
        ->firstOrFail();

    expect($event->twilio_message_attempt_id)->toBe($attempt->id)
        ->and($event->status)->toBe('delivered')
        ->and($event->applied)->toBeTrue()
        ->and($attempt->fresh()->status)->toBe('delivered')
        ->and($attempt->fresh()->last_callback_at)->not->toBeNull();

    $rawEvent = DB::table('twilio_status_events')->where('id', $event->id)->first();
    expect($rawEvent->payload)->not->toContain('+573016803926');
});

test('rejects a callback with an invalid Twilio signature', function () {
    $params = [
        'MessageSid' => 'SM'.str_repeat('c', 32),
        'MessageStatus' => 'failed',
    ];

    $this->withHeader('X-Twilio-Signature', 'invalid-signature')
        ->post('/api/twilio/status-callback', $params)
        ->assertForbidden();

    expect(TwilioStatusEvent::query()->count())->toBe(0);
});

test('preserves callback history without regressing a terminal attempt state', function () {
    $messageSid = 'SM'.str_repeat('d', 32);
    $attempt = acceptedTwilioAttempt($messageSid);

    foreach (['sent', 'delivered', 'sending'] as $status) {
        postSignedTwilioCallback($this, [
            'MessageSid' => $messageSid,
            'MessageStatus' => $status,
            'To' => '+573016803926',
            'From' => '+14709284068',
        ]);
    }

    expect($attempt->fresh()->status)->toBe('delivered')
        ->and(TwilioStatusEvent::query()->where('source', 'callback')->count())->toBe(3)
        ->and(TwilioStatusEvent::query()->where('status', 'sending')->value('applied'))->toBeFalse();

    postSignedTwilioCallback($this, [
        'MessageSid' => $messageSid,
        'MessageStatus' => 'sending',
        'To' => '+573016803926',
        'From' => '+14709284068',
    ]);

    expect(TwilioStatusEvent::query()->where('source', 'callback')->count())->toBe(3);
});
