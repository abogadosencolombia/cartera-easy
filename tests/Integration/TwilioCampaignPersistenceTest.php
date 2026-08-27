<?php

use App\Models\TwilioCampaign;
use App\Models\TwilioCampaignRecipient;
use App\Models\TwilioMessageAttempt;
use App\Models\TwilioStatusEvent;
use App\Services\TwilioCampaignService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

function bootTwilioCampaignDatabase(): void
{
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('t', 32)),
        'app.cipher' => 'AES-256-CBC',
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'services.twilio.account_sid' => 'AC'.str_repeat('1', 32),
        'services.twilio.api_key_sid' => 'SK'.str_repeat('2', 32),
        'services.twilio.api_key_secret' => 'test-api-key-secret',
        'services.twilio.auth_token' => 'test-auth-token',
        'services.twilio.from' => '+14709284068',
        'services.twilio.messaging_service_sid' => null,
        'services.twilio.status_callback_url' => 'https://example.test/api/twilio/status-callback',
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

function twilioManifest(array $overrides = []): array
{
    return array_replace_recursive([
        'campaign_key' => 'crearcoop-solo-pagares-2026-08-20-v1',
        'name' => 'CREARCOOP - Solo pagarés',
        'source_reference' => 'drive-sheet:source-id',
        'recipients' => [
            [
                'ordinal' => 1,
                'source_row' => 14,
                'identity_key' => 'debtor-document-001',
                'role' => 'debtor',
                'phone' => '+573001112233',
                'body' => 'Abogados en Colombia S.A.S. invita a comunicarse al 3152819233. STOP para no recibir SMS.',
                'segments' => 1,
                'estimated_cost' => '0.059200',
                'status' => 'ready',
            ],
            [
                'ordinal' => 2,
                'source_row' => 14,
                'identity_key' => 'codebtor-document-002',
                'role' => 'codebtor',
                'phone' => null,
                'body' => 'Abogados en Colombia S.A.S. informa que figura como codeudor. Comuníquese al 3152819233. STOP.',
                'segments' => 1,
                'estimated_cost' => '0.059200',
                'status' => 'excluded',
                'exclusion_reason' => 'RNE',
            ],
        ],
    ], $overrides);
}

beforeEach(function () {
    bootTwilioCampaignDatabase();
    Http::preventStrayRequests();
});

test('creates a sealed encrypted manifest and reuses it idempotently', function () {
    $service = app(TwilioCampaignService::class);
    $manifest = twilioManifest();

    $campaign = $service->createFromManifest($manifest);
    $sameCampaign = $service->createFromManifest([
        ...$manifest,
        'recipients' => array_reverse($manifest['recipients']),
    ]);

    expect($campaign)
        ->toBeInstanceOf(TwilioCampaign::class)
        ->and($sameCampaign->is($campaign))->toBeTrue()
        ->and($campaign->manifest_sha256)->toMatch('/^[0-9a-f]{64}$/')
        ->and($campaign->recipient_count)->toBe(2)
        ->and($campaign->status)->toBe('sealed')
        ->and($campaign->sealed_at)->not->toBeNull()
        ->and(TwilioCampaign::query()->count())->toBe(1)
        ->and(TwilioCampaignRecipient::query()->count())->toBe(2);

    $recipient = $campaign->recipients()->where('ordinal', 1)->firstOrFail();

    expect($recipient->phone)->toBe($manifest['recipients'][0]['phone'])
        ->and($recipient->body)->toBe($manifest['recipients'][0]['body'])
        ->and($recipient->body_hash)->toBe(hash('sha256', $manifest['recipients'][0]['body']))
        ->and($recipient->phone_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($recipient->identity_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($recipient->identity_hash)->not->toBe(hash('sha256', $manifest['recipients'][0]['identity_key']));

    $rawCampaign = DB::table('twilio_campaigns')->where('id', $campaign->id)->first();
    $rawRecipient = DB::table('twilio_campaign_recipients')->where('id', $recipient->id)->first();

    expect($rawCampaign->manifest)
        ->not->toContain($manifest['recipients'][0]['phone'])
        ->not->toContain($manifest['recipients'][0]['body'])
        ->and($rawRecipient->phone)->not->toContain($manifest['recipients'][0]['phone'])
        ->and($rawRecipient->body)->not->toContain($manifest['recipients'][0]['body']);

    $campaign->name = 'Mutated campaign';
    expect(fn () => $campaign->save())->toThrow(LogicException::class);

    $recipient->source_row = 999;
    expect(fn () => $recipient->save())->toThrow(LogicException::class);
});

test('rejects a changed manifest when its campaign key is already sealed', function () {
    $service = app(TwilioCampaignService::class);
    $service->createFromManifest(twilioManifest());

    $changed = twilioManifest();
    $changed['recipients'][0]['body'] .= ' Texto cambiado.';

    expect(fn () => $service->createFromManifest($changed))
        ->toThrow(DomainException::class);
});

test('persists the exact manifest RNE and dynamic plan authorization hashes', function () {
    $service = app(TwilioCampaignService::class);
    $authorization = [
        'raw_manifest_sha256' => str_repeat('a', 64),
        'rne_proof_sha256' => str_repeat('b', 64),
        'plan_sha256' => str_repeat('c', 64),
        'rne_checked_at' => '2026-08-24T03:10:00-05:00',
        'scheduled_at' => '2026-08-24T07:00:00-05:00',
        'included_count' => 1,
    ];

    $campaign = $service->createFromManifest(twilioManifest(), $authorization);

    expect($campaign->raw_manifest_sha256)->toBe($authorization['raw_manifest_sha256'])
        ->and($campaign->rne_proof_sha256)->toBe($authorization['rne_proof_sha256'])
        ->and($campaign->plan_sha256)->toBe($authorization['plan_sha256'])
        ->and($campaign->rne_checked_at)->toBe('2026-08-24T03:10:00-05:00')
        ->and($campaign->scheduled_at)->toBe('2026-08-24T07:00:00-05:00')
        ->and($campaign->included_count)->toBe(1);

    $changedAuthorization = [...$authorization, 'plan_sha256' => str_repeat('d', 64)];

    expect(fn () => $service->createFromManifest(twilioManifest(), $changedAuthorization))
        ->toThrow(DomainException::class);
});

test('keeps excluded identities without a phone and enforces readiness rules', function () {
    $service = app(TwilioCampaignService::class);
    $campaign = $service->createFromManifest(twilioManifest());
    $excluded = $campaign->recipients()->where('status', 'excluded')->firstOrFail();

    expect($campaign->recipient_count)->toBe(2)
        ->and($excluded->phone)->toBeNull()
        ->and($excluded->phone_hash)->toBeNull()
        ->and($excluded->exclusion_reason)->toBe('RNE');

    $readyWithoutPhone = twilioManifest([
        'campaign_key' => 'invalid-ready-phone',
        'recipients' => [[
            'phone' => null,
        ]],
    ]);

    expect(fn () => $service->createFromManifest($readyWithoutPhone))
        ->toThrow(ValidationException::class);

    $excludedWithoutReason = twilioManifest([
        'campaign_key' => 'invalid-exclusion-reason',
        'recipients' => [
            1 => [
                'exclusion_reason' => null,
            ],
        ],
    ]);

    expect(fn () => $service->createFromManifest($excludedWithoutReason))
        ->toThrow(ValidationException::class);

    $duplicateReadyPhone = twilioManifest([
        'campaign_key' => 'invalid-duplicate-ready-phone',
        'recipients' => [
            1 => [
                'phone' => '+573001112233',
                'status' => 'ready',
                'exclusion_reason' => null,
            ],
        ],
    ]);

    expect(fn () => $service->createFromManifest($duplicateReadyPhone))
        ->toThrow(ValidationException::class);
});

test('persists Twilio acceptance immediately and never submits the same recipient twice', function () {
    $sid = 'SM'.str_repeat('a', 32);

    Http::fake([
        'api.twilio.com/*' => Http::response([
            'sid' => $sid,
            'status' => 'queued',
            'num_segments' => '1',
            'price' => null,
            'price_unit' => 'USD',
            'to' => '+573001112233',
            'body' => 'sensitive provider echo',
        ], 201),
    ]);

    $service = app(TwilioCampaignService::class);
    $campaign = $service->createFromManifest(twilioManifest());
    $recipient = $campaign->recipients()->where('status', 'ready')->firstOrFail();

    $attempt = $service->sendRecipient($recipient);
    $sameAttempt = $service->sendRecipient($recipient->fresh());

    Http::assertSentCount(1);

    expect($attempt)
        ->toBeInstanceOf(TwilioMessageAttempt::class)
        ->and($sameAttempt->is($attempt))->toBeTrue()
        ->and($attempt->message_sid)->toBe($sid)
        ->and($attempt->status)->toBe('queued')
        ->and($attempt->accepted_at)->not->toBeNull()
        ->and($attempt->idempotency_key)->toBe($recipient->idempotency_key)
        ->and(TwilioMessageAttempt::query()->count())->toBe(1)
        ->and(TwilioStatusEvent::query()->count())->toBe(1)
        ->and($recipient->fresh()->status)->toBe('queued');

    $rawAttempt = DB::table('twilio_message_attempts')->where('id', $attempt->id)->first();
    $rawEvent = DB::table('twilio_status_events')->first();

    expect($rawAttempt->provider_response)->not->toContain('+573001112233')
        ->and($rawAttempt->provider_response)->not->toContain('sensitive provider echo')
        ->and($rawEvent->payload)->not->toContain('+573001112233');
});

test('does not submit a recipient excluded by the sealed manifest', function () {
    Http::fake();

    $service = app(TwilioCampaignService::class);
    $campaign = $service->createFromManifest(twilioManifest());
    $recipient = $campaign->recipients()->where('status', 'excluded')->firstOrFail();

    expect(fn () => $service->sendRecipient($recipient))
        ->toThrow(DomainException::class);

    Http::assertNothingSent();
    expect(TwilioMessageAttempt::query()->count())->toBe(0);
});

test('reconciles a status callback that arrives before the accepted sid is persisted', function () {
    $sid = 'SM'.str_repeat('f', 32);
    $service = app(TwilioCampaignService::class);

    Http::fake(function () use ($service, $sid) {
        $service->recordStatusCallback([
            'MessageSid' => $sid,
            'MessageStatus' => 'delivered',
            'To' => '+573001112233',
            'From' => '+14709284068',
        ]);

        return Http::response([
            'sid' => $sid,
            'status' => 'queued',
            'num_segments' => '1',
            'price' => null,
            'price_unit' => 'USD',
        ], 201);
    });

    $campaign = $service->createFromManifest(twilioManifest([
        'campaign_key' => 'callback-before-acceptance',
    ]));
    $recipient = $campaign->recipients()->where('status', 'ready')->firstOrFail();
    $attempt = $service->sendRecipient($recipient);
    $callback = TwilioStatusEvent::query()->where('source', 'callback')->firstOrFail();

    expect($attempt->status)->toBe('delivered')
        ->and($callback->fresh()->twilio_message_attempt_id)->toBe($attempt->id)
        ->and($callback->fresh()->applied)->toBeTrue()
        ->and($recipient->fresh()->status)->toBe('delivered')
        ->and(TwilioStatusEvent::query()->count())->toBe(2);
});
