<?php

use App\Models\TwilioCampaign;
use App\Models\TwilioMessageAttempt;
use App\Services\CrearcoopSmsCopy;
use App\Services\CrearcoopSmsManifestBuilder;
use App\Services\SmsSegmentCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

function bootTwilioCommandDatabase(): string
{
    $root = sys_get_temp_dir().'/twilio-command-'.bin2hex(random_bytes(6));
    mkdir($root, 0700, true);

    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('m', 32)),
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
        'services.twilio.campaign_manifest_root' => $root,
        'services.twilio.rne_proof_root' => $root,
    ]);

    DB::purge('sqlite');

    foreach ([
        '2026_08_20_170000_create_twilio_campaigns_table.php',
        '2026_08_20_170100_create_twilio_campaign_recipients_table.php',
        '2026_08_20_170200_create_twilio_message_attempts_table.php',
        '2026_08_20_170300_create_twilio_status_events_table.php',
        '2026_08_20_170500_create_sms_suppressions_table.php',
        '2026_08_20_170600_add_authorization_trace_to_twilio_campaigns_table.php',
    ] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }

    return $root;
}

function twilioCommandManifest(): array
{
    $segments = new SmsSegmentCalculator;
    $copy = new CrearcoopSmsCopy($segments);
    $recipients = [];

    for ($ordinal = 1; $ordinal <= 44; $ordinal++) {
        $role = $ordinal <= 42 ? 'debtor' : 'codebtor';
        $phone = $ordinal <= 3 ? null : sprintf('+57300%07d', $ordinal);
        $body = $role === 'debtor' ? $copy->forDebtor() : $copy->forCodebtor('Deudor Relacionado '.$ordinal);
        $segmentData = $segments->calculate($body);

        $recipients[] = [
            'ordinal' => $ordinal,
            'source_row' => 100 + $ordinal,
            'identity_key' => hash('sha256', 'identity-'.$ordinal),
            'role' => $role,
            'phone' => $phone,
            'phone_fingerprint' => $phone === null ? null : hash('sha256', $phone),
            'body' => $body,
            'segments' => $segmentData['segments'],
            'estimated_cost' => number_format($segmentData['segments'] * 0.0592, 4, '.', ''),
            'idempotency_key' => hash('sha256', 'attempt-'.$ordinal),
            'status' => $phone === null ? 'excluded' : 'ready',
            'exclusion_reason' => $phone === null ? 'no_verified_mobile' : null,
            'last_contact_at' => '2026-08-20 10:34:29 (Colombia)',
        ];
    }

    return [
        'schema_version' => 1,
        'campaign_key' => 'crearcoop-sms-2026-08-24-command-test',
        'name' => 'CREARCOOP SMS command test',
        'source_reference' => 'https://docs.google.com/spreadsheets/d/'.CrearcoopSmsManifestBuilder::SOURCE_SPREADSHEET_ID.'/edit#gid=0',
        'scope' => ['total' => 44, 'debtors' => 42, 'codebtors' => 2],
        'recipients' => $recipients,
    ];
}

function writeTwilioCommandJson(string $root, string $name, array $payload): array
{
    $path = $root.'/'.$name;
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    chmod($path, 0440);

    return [$path, hash_file('sha256', $path)];
}

function twilioCommandRneProof(array $manifest, string $manifestSha): array
{
    return [
        'schema_version' => 1,
        'source' => 'CRC_RNE',
        'campaign_key' => $manifest['campaign_key'],
        'manifest_sha256' => $manifestSha,
        'checked_at' => '2026-08-24T03:10:00-05:00',
        'results' => array_map(
            static fn (array $recipient): array => [
                'phone_fingerprint' => $recipient['phone_fingerprint'],
                'status' => 'clear',
            ],
            array_values(array_filter($manifest['recipients'], fn (array $recipient): bool => $recipient['status'] === 'ready')),
        ),
    ];
}

beforeEach(function () {
    $this->twilioCommandRoot = bootTwilioCommandDatabase();
    Http::preventStrayRequests();
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('performs a real dry run without RNE proof and never calls Twilio', function () {
    [$manifestPath] = writeTwilioCommandJson($this->twilioCommandRoot, 'manifest.json', twilioCommandManifest());

    $exitCode = \Illuminate\Support\Facades\Artisan::call('app:send-twilio-campaign', [
        'manifest' => $manifestPath,
        '--dry-run' => true,
        '--at' => '2026-08-24 07:00:00',
        '--timezone' => 'America/Bogota',
    ]);
    $output = \Illuminate\Support\Facades\Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('"scope_total": 44')
        ->toContain('"data_ready": 41')
        ->toContain('"included": 0')
        ->toContain('"rne_pending": 41');

    Http::assertNothingSent();
    expect(TwilioCampaign::query()->count())->toBe(0);
});

test('sends only after explicit hash confirmation and a complete same-day RNE proof', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 07:00:00', 'America/Bogota'));
    $manifest = twilioCommandManifest();
    [$manifestPath, $manifestSha] = writeTwilioCommandJson($this->twilioCommandRoot, 'manifest.json', $manifest);
    $proof = twilioCommandRneProof($manifest, $manifestSha);
    [$proofPath] = writeTwilioCommandJson($this->twilioCommandRoot, 'rne.json', $proof);
    $plan = app(\App\Services\CrearcoopSmsCampaignPlanner::class)->plan(
        $manifest,
        $manifestSha,
        CarbonImmutable::parse('2026-08-24 07:00:00', 'America/Bogota'),
        $proof,
    );
    $sentBodies = [];
    $sequence = 0;

    Http::fake(function ($request) use (&$sentBodies, &$sequence) {
        $sequence++;
        $sentBodies[] = (string) $request['Body'];

        return Http::response([
            'sid' => 'SM'.str_pad(dechex($sequence), 32, 'a', STR_PAD_LEFT),
            'status' => 'queued',
            'num_segments' => '1',
            'price' => null,
            'price_unit' => 'USD',
        ], 201);
    });

    $this->artisan('app:send-twilio-campaign', [
        'manifest' => $manifestPath,
        '--send' => true,
        '--at' => '2026-08-24 07:00:00',
        '--timezone' => 'America/Bogota',
        '--rne-proof' => $proofPath,
        '--confirm-manifest' => $manifestSha,
        '--confirm-plan' => $plan['plan_sha256'],
        '--delay-seconds' => 0,
    ])
        ->expectsOutputToContain('"included": 41')
        ->expectsOutputToContain('"submitted": 41')
        ->assertSuccessful();

    Http::assertSentCount(41);
    $campaign = TwilioCampaign::query()->firstOrFail();

    expect(TwilioCampaign::query()->count())->toBe(1)
        ->and($campaign->recipient_count)->toBe(44)
        ->and($campaign->included_count)->toBe(41)
        ->and($campaign->raw_manifest_sha256)->toBe($manifestSha)
        ->and($campaign->rne_proof_sha256)->toBe($plan['rne_proof_sha256'])
        ->and($campaign->plan_sha256)->toBe($plan['plan_sha256'])
        ->and(TwilioMessageAttempt::query()->count())->toBe(41)
        ->and($sentBodies)->toHaveCount(41)
        ->and(array_filter(array_slice($sentBodies, 0, 39), fn (string $body): bool => str_contains($body, 'codeudor')))->toBe([])
        ->and(array_filter(array_slice($sentBodies, 39), fn (string $body): bool => str_contains($body, 'codeudor')))->toHaveCount(2);
});

test('refuses send mode until the exact dynamic plan hash is confirmed', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 07:00:00', 'America/Bogota'));
    $manifest = twilioCommandManifest();
    [$manifestPath, $manifestSha] = writeTwilioCommandJson($this->twilioCommandRoot, 'manifest.json', $manifest);
    [$proofPath] = writeTwilioCommandJson($this->twilioCommandRoot, 'rne.json', twilioCommandRneProof($manifest, $manifestSha));

    Http::fake(fn () => Http::response([
        'sid' => 'SM'.str_repeat('a', 32),
        'status' => 'queued',
        'num_segments' => '1',
    ], 201));

    $this->artisan('app:send-twilio-campaign', [
        'manifest' => $manifestPath,
        '--send' => true,
        '--at' => '2026-08-24 07:00:00',
        '--timezone' => 'America/Bogota',
        '--rne-proof' => $proofPath,
        '--confirm-manifest' => $manifestSha,
        '--delay-seconds' => 0,
    ])->assertFailed();

    Http::assertNothingSent();
    expect(TwilioCampaign::query()->count())->toBe(0);
});

test('refuses send mode without the exact manifest confirmation hash', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 07:00:00', 'America/Bogota'));
    $manifest = twilioCommandManifest();
    [$manifestPath, $manifestSha] = writeTwilioCommandJson($this->twilioCommandRoot, 'manifest.json', $manifest);
    [$proofPath] = writeTwilioCommandJson($this->twilioCommandRoot, 'rne.json', twilioCommandRneProof($manifest, $manifestSha));

    $this->artisan('app:send-twilio-campaign', [
        'manifest' => $manifestPath,
        '--send' => true,
        '--at' => '2026-08-24 07:00:00',
        '--timezone' => 'America/Bogota',
        '--rne-proof' => $proofPath,
        '--confirm-manifest' => 'wrong',
        '--delay-seconds' => 0,
    ])->assertFailed();

    Http::assertNothingSent();
    expect(TwilioCampaign::query()->count())->toBe(0);
});

test('refuses a manifest expanded beyond the exact 44 identities', function () {
    $manifest = twilioCommandManifest();
    $manifest['recipients'][] = [...$manifest['recipients'][0], 'ordinal' => 45];
    [$manifestPath] = writeTwilioCommandJson($this->twilioCommandRoot, 'expanded.json', $manifest);

    $this->artisan('app:send-twilio-campaign', [
        'manifest' => $manifestPath,
        '--dry-run' => true,
        '--at' => '2026-08-24 07:00:00',
        '--timezone' => 'America/Bogota',
    ])->assertFailed();

    Http::assertNothingSent();
});
