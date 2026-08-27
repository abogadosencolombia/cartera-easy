<?php

use App\Services\CrearcoopSmsCopy;
use App\Services\CrearcoopSmsManifestBuilder;
use App\Services\SmsSegmentCalculator;

function crearcoopSourceFixture(): array
{
    $rows = [];

    for ($index = 0; $index < 42; $index++) {
        $row = 100 + $index;
        $phones = [sprintf('+57300%07d', $index)];

        if (in_array($row, [110, 111, 112], true)) {
            $phones = [];
        } elseif ($row === 113) {
            $phones = ['+573009999991', '+573009999992'];
        }

        $rows[] = [
            'row' => $row,
            'role' => 'DEUDOR',
            'document' => 'D-'.$row,
            'name' => 'Persona '.$row,
            'promissory' => 'P-'.$row,
            'validPhones' => $phones,
            'contactedAt' => '2026-08-20 09:00:00',
        ];
    }

    foreach ([200, 201] as $row) {
        $rows[] = [
            'row' => $row,
            'role' => 'CODEUDOR',
            'document' => 'C-'.$row,
            'name' => 'Codeudor '.$row,
            'promissory' => 'P-'.$row,
            'validPhones' => [sprintf('+57301%07d', $row)],
            'relatedDebtorName' => 'Deudor Relacionado '.$row,
            'relatedDebtorDocument' => 'DR-'.$row,
            'contactedAt' => '2026-08-20 09:00:00',
        ];
    }

    return [
        'source' => [
            'spreadsheet_id' => CrearcoopSmsManifestBuilder::SOURCE_SPREADSHEET_ID,
            'sheet' => 'Hoja 1',
            'expected_total' => 44,
            'expected_debtors' => 42,
            'expected_codebtors' => 2,
        ],
        'rows' => $rows,
    ];
}

function crearcoopResolutionFixture(): array
{
    return [
        '110:DEUDOR' => ['status' => 'excluded', 'reason' => 'no_verified_mobile', 'evidence' => ['source' => 'app_drive']],
        '111:DEUDOR' => ['status' => 'excluded', 'reason' => 'no_verified_mobile', 'evidence' => ['source' => 'app_drive']],
        '112:DEUDOR' => ['status' => 'excluded', 'reason' => 'no_verified_mobile', 'evidence' => ['source' => 'app_drive']],
        '113:DEUDOR' => ['status' => 'ready', 'phone' => '+573009999991', 'evidence' => ['source' => 'app_primary']],
    ];
}

function crearcoopManifestBuilder(): CrearcoopSmsManifestBuilder
{
    $segments = new SmsSegmentCalculator;

    return new CrearcoopSmsManifestBuilder(new CrearcoopSmsCopy($segments), $segments);
}

test('builds the frozen 44-recipient scope with debtors before codebtors', function () {
    $manifest = crearcoopManifestBuilder()->build(
        crearcoopSourceFixture(),
        crearcoopResolutionFixture(),
        'crearcoop-sms-2026-08-24',
        'CREARCOOP SMS 24 agosto 2026',
    );

    $ready = array_values(array_filter($manifest['recipients'], fn (array $row): bool => $row['status'] === 'ready'));
    $excluded = array_values(array_filter($manifest['recipients'], fn (array $row): bool => $row['status'] === 'excluded'));

    expect($manifest['scope'])->toMatchArray(['total' => 44, 'debtors' => 42, 'codebtors' => 2])
        ->and($manifest['recipients'])->toHaveCount(44)
        ->and($ready)->toHaveCount(41)
        ->and($excluded)->toHaveCount(3)
        ->and(array_slice(array_column($manifest['recipients'], 'role'), 0, 42))->each->toBe('debtor')
        ->and(array_slice(array_column($manifest['recipients'], 'role'), 42))->each->toBe('codebtor')
        ->and(array_sum(array_column($ready, 'segments')))->toBe(41)
        ->and($manifest['estimated_cost_usd'])->toBe('2.4272')
        ->and(array_unique(array_column($ready, 'phone')))->toHaveCount(41);
});

test('rejects any automatic expansion beyond the exact 44 identities', function () {
    $source = crearcoopSourceFixture();
    $source['rows'][] = $source['rows'][0] + ['row' => 999];

    expect(fn () => crearcoopManifestBuilder()->build($source, crearcoopResolutionFixture(), 'key', 'name'))
        ->toThrow(DomainException::class, '44');
});

test('rejects an unresolved missing or ambiguous phone', function () {
    $resolutions = crearcoopResolutionFixture();
    unset($resolutions['113:DEUDOR']);

    expect(fn () => crearcoopManifestBuilder()->build(crearcoopSourceFixture(), $resolutions, 'key', 'name'))
        ->toThrow(DomainException::class, 'resolución');
});

test('rejects duplicate identities or duplicate ready phone numbers', function () {
    $duplicateIdentity = crearcoopSourceFixture();
    $duplicateIdentity['rows'][1]['document'] = $duplicateIdentity['rows'][0]['document'];
    $duplicateIdentity['rows'][1]['promissory'] = $duplicateIdentity['rows'][0]['promissory'];

    expect(fn () => crearcoopManifestBuilder()->build($duplicateIdentity, crearcoopResolutionFixture(), 'key', 'name'))
        ->toThrow(DomainException::class, 'identidad duplicada');

    $duplicatePhone = crearcoopSourceFixture();
    $duplicatePhone['rows'][1]['validPhones'] = $duplicatePhone['rows'][0]['validPhones'];

    expect(fn () => crearcoopManifestBuilder()->build($duplicatePhone, crearcoopResolutionFixture(), 'key', 'name'))
        ->toThrow(DomainException::class, 'teléfono duplicado');
});

test('rejects a codebtor without a verified related debtor', function () {
    $source = crearcoopSourceFixture();
    unset($source['rows'][42]['relatedDebtorName']);

    expect(fn () => crearcoopManifestBuilder()->build($source, crearcoopResolutionFixture(), 'key', 'name'))
        ->toThrow(DomainException::class, 'deudor relacionado');
});
