<?php

use App\Services\RneProofVerifier;
use Carbon\CarbonImmutable;

function validRneProof(array $fingerprints): array
{
    return [
        'schema_version' => 1,
        'source' => 'CRC_RNE',
        'campaign_key' => 'crearcoop-sms-2026-08-24',
        'manifest_sha256' => str_repeat('a', 64),
        'checked_at' => '2026-08-24T03:10:00-05:00',
        'results' => array_map(
            static fn (string $fingerprint): array => ['phone_fingerprint' => $fingerprint, 'status' => 'clear'],
            $fingerprints,
        ),
    ];
}

test('accepts a complete same-day CRC RNE proof', function () {
    $fingerprints = [hash('sha256', '+573001111111'), hash('sha256', '+573002222222')];
    $result = (new RneProofVerifier)->verify(
        validRneProof($fingerprints),
        'crearcoop-sms-2026-08-24',
        str_repeat('a', 64),
        CarbonImmutable::parse('2026-08-24 07:00:00', 'America/Bogota'),
        $fingerprints,
    );

    expect($result)->toHaveCount(2)
        ->and(array_unique(array_values($result)))->toBe(['clear']);
});

test('preserves an excluded RNE result', function () {
    $fingerprints = [hash('sha256', '+573001111111')];
    $proof = validRneProof($fingerprints);
    $proof['results'][0]['status'] = 'excluded';

    expect((new RneProofVerifier)->verify(
        $proof,
        $proof['campaign_key'],
        $proof['manifest_sha256'],
        CarbonImmutable::parse('2026-08-24 07:00:00', 'America/Bogota'),
        $fingerprints,
    )[$fingerprints[0]])->toBe('excluded');
});

test('rejects stale future or pre-update proofs', function (string $checkedAt) {
    $fingerprints = [hash('sha256', '+573001111111')];
    $proof = validRneProof($fingerprints);
    $proof['checked_at'] = $checkedAt;

    expect(fn () => (new RneProofVerifier)->verify(
        $proof,
        $proof['campaign_key'],
        $proof['manifest_sha256'],
        CarbonImmutable::parse('2026-08-24 07:00:00', 'America/Bogota'),
        $fingerprints,
    ))->toThrow(DomainException::class);
})->with([
    'previous day' => '2026-08-23T10:00:00-05:00',
    'before the conservative CRC refresh window' => '2026-08-24T02:59:59-05:00',
    'after planned execution' => '2026-08-24T07:00:01-05:00',
]);

test('rejects a proof for a different manifest or campaign', function () {
    $fingerprints = [hash('sha256', '+573001111111')];
    $proof = validRneProof($fingerprints);

    expect(fn () => (new RneProofVerifier)->verify(
        $proof,
        'different-campaign',
        str_repeat('a', 64),
        CarbonImmutable::parse('2026-08-24 07:00:00', 'America/Bogota'),
        $fingerprints,
    ))->toThrow(DomainException::class)
        ->and(fn () => (new RneProofVerifier)->verify(
            $proof,
            $proof['campaign_key'],
            str_repeat('b', 64),
            CarbonImmutable::parse('2026-08-24 07:00:00', 'America/Bogota'),
            $fingerprints,
        ))->toThrow(DomainException::class);
});

test('rejects missing extra duplicate or invalid RNE results', function (callable $mutate) {
    $fingerprints = [hash('sha256', '+573001111111'), hash('sha256', '+573002222222')];
    $proof = $mutate(validRneProof($fingerprints));

    expect(fn () => (new RneProofVerifier)->verify(
        $proof,
        'crearcoop-sms-2026-08-24',
        str_repeat('a', 64),
        CarbonImmutable::parse('2026-08-24 07:00:00', 'America/Bogota'),
        $fingerprints,
    ))->toThrow(DomainException::class);
})->with([
    'missing' => fn (array $proof): array => [...$proof, 'results' => [$proof['results'][0]]],
    'extra' => fn (array $proof): array => [...$proof, 'results' => [...$proof['results'], ['phone_fingerprint' => str_repeat('f', 64), 'status' => 'clear']]],
    'duplicate' => fn (array $proof): array => [...$proof, 'results' => [...$proof['results'], $proof['results'][0]]],
    'invalid status' => function (array $proof): array {
        $proof['results'][0]['status'] = 'unknown';

        return $proof;
    },
]);
