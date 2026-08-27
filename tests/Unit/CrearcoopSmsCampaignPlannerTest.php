<?php

use App\Services\CrearcoopSmsCampaignPlanner;
use App\Services\RneProofVerifier;
use App\Services\SmsLegalWindow;
use App\Services\SmsSuppressionService;
use Carbon\CarbonImmutable;

function plannerManifestFixture(): array
{
    $recipients = [];

    for ($ordinal = 1; $ordinal <= 44; $ordinal++) {
        $role = $ordinal <= 42 ? 'debtor' : 'codebtor';
        $phone = $ordinal <= 3 ? null : sprintf('+57300%07d', $ordinal);
        $recipients[] = [
            'ordinal' => $ordinal,
            'source_row' => 100 + $ordinal,
            'identity_key' => hash('sha256', 'identity-'.$ordinal),
            'role' => $role,
            'phone' => $phone,
            'phone_fingerprint' => $phone === null ? null : hash('sha256', $phone),
            'body' => 'CREARCOOP Abogados en Colombia SAS 3152819233 abogadosencolombiasas@gmail.com STOP.',
            'segments' => 1,
            'estimated_cost' => '0.0592',
            'idempotency_key' => hash('sha256', 'attempt-'.$ordinal),
            'status' => $phone === null ? 'excluded' : 'ready',
            'exclusion_reason' => $phone === null ? 'no_verified_mobile' : null,
            'last_contact_at' => '2026-08-20T09:00:00-05:00',
        ];
    }

    return [
        'schema_version' => 1,
        'campaign_key' => 'crearcoop-sms-2026-08-24',
        'name' => 'CREARCOOP SMS 24 agosto 2026',
        'source_reference' => 'https://docs.google.com/spreadsheets/d/'.\App\Services\CrearcoopSmsManifestBuilder::SOURCE_SPREADSHEET_ID.'/edit#gid=0',
        'scope' => ['total' => 44, 'debtors' => 42, 'codebtors' => 2],
        'recipients' => $recipients,
    ];
}

function plannerRneProof(array $manifest, string $manifestSha256): array
{
    return [
        'schema_version' => 1,
        'source' => 'CRC_RNE',
        'campaign_key' => $manifest['campaign_key'],
        'manifest_sha256' => $manifestSha256,
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

function campaignPlanner(?string $suppressedPhone = null): CrearcoopSmsCampaignPlanner
{
    $suppressions = Mockery::mock(SmsSuppressionService::class);
    $suppressions->shouldReceive('isSuppressed')
        ->zeroOrMoreTimes()
        ->andReturnUsing(fn (string $phone): bool => $phone === $suppressedPhone);

    return new CrearcoopSmsCampaignPlanner(
        new SmsLegalWindow,
        $suppressions,
        new RneProofVerifier,
    );
}

test('keeps all 41 ready phones pending when there is no current RNE proof', function () {
    $manifest = plannerManifestFixture();
    $result = campaignPlanner()->plan(
        $manifest,
        str_repeat('a', 64),
        CarbonImmutable::parse('2026-08-24 07:00:00', 'America/Bogota'),
    );

    expect($result['scope_total'])->toBe(44)
        ->and($result['data_ready'])->toBe(41)
        ->and($result['included'])->toBe(0)
        ->and($result['excluded'])->toBe(44)
        ->and($result['excluded_by_primary_reason'])->toMatchArray([
            'no_verified_mobile' => 3,
            'rne_pending' => 41,
        ])
        ->and($result['segments'])->toBe(0)
        ->and($result['estimated_cost_usd'])->toBe('0.0000');
});

test('accepts the exact Colombia timestamp format stored by the source sheet', function () {
    $manifest = plannerManifestFixture();

    foreach ($manifest['recipients'] as &$recipient) {
        $recipient['last_contact_at'] = '2026-08-20 10:34:29 (Colombia)';
    }
    unset($recipient);

    $result = campaignPlanner()->plan(
        $manifest,
        str_repeat('a', 64),
        CarbonImmutable::parse('2026-08-24 07:00:00', 'America/Bogota'),
    );

    expect($result['data_ready'])->toBe(41)
        ->and($result['included'])->toBe(0)
        ->and($result['excluded_by_primary_reason'])->toMatchArray([
            'no_verified_mobile' => 3,
            'rne_pending' => 41,
        ]);
});

test('includes 40 after valid RNE proof when one phone is suppressed', function () {
    $manifest = plannerManifestFixture();
    $manifestSha = str_repeat('a', 64);
    $suppressedPhone = $manifest['recipients'][10]['phone'];

    $result = campaignPlanner($suppressedPhone)->plan(
        $manifest,
        $manifestSha,
        CarbonImmutable::parse('2026-08-24 07:00:00', 'America/Bogota'),
        plannerRneProof($manifest, $manifestSha),
    );

    expect($result['included'])->toBe(40)
        ->and($result['excluded'])->toBe(4)
        ->and($result['excluded_by_primary_reason'])->toMatchArray([
            'no_verified_mobile' => 3,
            'opt_out' => 1,
        ])
        ->and($result['segments'])->toBe(40)
        ->and($result['estimated_cost_usd'])->toBe('2.3680');
});

test('binds the exact RNE and suppression decisions to a stable plan hash', function () {
    $manifest = plannerManifestFixture();
    $manifestSha = str_repeat('a', 64);
    $scheduledAt = CarbonImmutable::parse('2026-08-24 07:00:00', 'America/Bogota');
    $proof = plannerRneProof($manifest, $manifestSha);

    $first = campaignPlanner()->plan($manifest, $manifestSha, $scheduledAt, $proof);
    $same = campaignPlanner()->plan($manifest, $manifestSha, $scheduledAt, $proof);
    $suppressed = campaignPlanner($manifest['recipients'][10]['phone'])
        ->plan($manifest, $manifestSha, $scheduledAt, $proof);

    expect($first['plan_sha256'])->toMatch('/^[0-9a-f]{64}$/')
        ->and($first['rne_proof_sha256'])->toMatch('/^[0-9a-f]{64}$/')
        ->and($same['plan_sha256'])->toBe($first['plan_sha256'])
        ->and($suppressed['plan_sha256'])->not->toBe($first['plan_sha256']);
});

test('excludes this same week even with an otherwise clear RNE proof', function () {
    $manifest = plannerManifestFixture();
    $manifestSha = str_repeat('a', 64);
    $proof = plannerRneProof($manifest, $manifestSha);
    $proof['checked_at'] = '2026-08-21T03:10:00-05:00';

    $result = campaignPlanner()->plan(
        $manifest,
        $manifestSha,
        CarbonImmutable::parse('2026-08-21 07:00:00', 'America/Bogota'),
        $proof,
    );

    expect($result['included'])->toBe(0)
        ->and($result['excluded_by_primary_reason']['contacted_same_week'])->toBe(41);
});

test('rejects a manifest whose exact 42 plus 2 scope has changed', function () {
    $manifest = plannerManifestFixture();
    $manifest['recipients'][] = $manifest['recipients'][0] + ['ordinal' => 45];

    expect(fn () => campaignPlanner()->plan(
        $manifest,
        str_repeat('a', 64),
        CarbonImmutable::parse('2026-08-24 07:00:00', 'America/Bogota'),
    ))->toThrow(DomainException::class, '44');
});

test('rejects a manifest that would place a codebtor before debtors', function () {
    $manifest = plannerManifestFixture();
    [$manifest['recipients'][0]['role'], $manifest['recipients'][42]['role']] = [
        $manifest['recipients'][42]['role'],
        $manifest['recipients'][0]['role'],
    ];

    expect(fn () => campaignPlanner()->plan(
        $manifest,
        str_repeat('a', 64),
        CarbonImmutable::parse('2026-08-24 07:00:00', 'America/Bogota'),
    ))->toThrow(DomainException::class, 'deudores primero');
});
