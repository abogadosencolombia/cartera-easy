<?php

declare(strict_types=1);

use App\Models\Persona;
use App\Services\CrearcoopSmsManifestBuilder;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$sourcePath = $argv[1] ?? storage_path('app/private/twilio/.crearcoop_44_live_source.json');
$outputPath = $argv[2] ?? storage_path('app/private/twilio/campaigns/crearcoop-sms-2026-08-24-v1.json');
$hashPath = $outputPath.'.sha256';

$source = json_decode((string) file_get_contents($sourcePath), true, 512, JSON_THROW_ON_ERROR);
$rows = $source['rows'] ?? [];

$ambiguous = collect($rows)->first(
    static fn (array $row): bool => (int) ($row['row'] ?? 0) === 178
        && strtoupper((string) ($row['role'] ?? '')) === 'DEUDOR'
);

if (! is_array($ambiguous)) {
    throw new RuntimeException('No se encontró la excepción verificada de la fila 178.');
}

$normalizeIdentifier = static function (mixed $value): string {
    $value = mb_strtoupper(trim((string) $value), 'UTF-8');
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

    return preg_replace('/[^A-Z0-9]/', '', $ascii === false ? $value : $ascii) ?? '';
};

$normalizeColombianMobile = static function (mixed $value): ?string {
    $digits = preg_replace('/\D/', '', (string) $value) ?? '';

    if (strlen($digits) === 10 && str_starts_with($digits, '3')) {
        return '+57'.$digits;
    }

    if (strlen($digits) === 12 && str_starts_with($digits, '573')) {
        return '+'.$digits;
    }

    return null;
};

$document = $normalizeIdentifier($ambiguous['document'] ?? '');
$matches = Persona::query()
    ->get(['id', 'numero_documento', 'celular_1', 'celular_2'])
    ->filter(static fn (Persona $persona): bool => $normalizeIdentifier($persona->numero_documento) === $document)
    ->values();

if ($matches->count() !== 1) {
    throw new RuntimeException('La fila 178 no tiene una única ficha de persona verificable.');
}

$person = $matches->firstOrFail();
$primaryPhone = $normalizeColombianMobile($person->celular_1);
$sheetPhones = array_values(array_unique($ambiguous['validPhones'] ?? []));

if ($primaryPhone === null || ! in_array($primaryPhone, $sheetPhones, true)) {
    throw new RuntimeException('El móvil principal de la ficha no coincide con los valores de la hoja.');
}

$resolutions = [
    '140:DEUDOR' => [
        'status' => 'excluded',
        'reason' => 'no_verified_mobile',
        'evidence' => [
            'app_source_sha256' => 'a2fa4a44ed4e8156',
            'drive_folder_sha256' => 'a83a515d9691ff71',
            'drive_result' => 'empty',
        ],
    ],
    '146:DEUDOR' => [
        'status' => 'excluded',
        'reason' => 'no_verified_mobile',
        'evidence' => [
            'app_source_sha256' => '2dc0ce10f7e7c68a',
            'drive_folder_sha256' => '260a1d1401d3534b',
            'drive_result' => 'empty',
        ],
    ],
    '148:DEUDOR' => [
        'status' => 'excluded',
        'reason' => 'no_verified_mobile',
        'evidence' => [
            'app_source_sha256' => '7c555ef73605ba6b',
            'drive_folder_sha256' => '6406e458269e35dd',
            'drive_result' => 'empty',
        ],
    ],
    '178:DEUDOR' => [
        'status' => 'ready',
        'phone' => $primaryPhone,
        'evidence' => [
            'app_source_sha256' => 'fdba96200e8fbd0a',
            'field' => 'celular_1',
            'secondary_excluded' => true,
        ],
    ],
];

$manifest = app(CrearcoopSmsManifestBuilder::class)->build(
    $source,
    $resolutions,
    'crearcoop-sms-2026-08-24-v1',
    'CREARCOOP SMS - primera ventana 24 agosto 2026',
);

$json = json_encode(
    $manifest,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
).PHP_EOL;

file_put_contents($outputPath, $json, LOCK_EX);
$sha256 = hash_file('sha256', $outputPath);
file_put_contents($hashPath, $sha256.'  '.basename($outputPath).PHP_EOL, LOCK_EX);
chmod($outputPath, 0440);
chmod($hashPath, 0440);

fwrite(STDOUT, json_encode([
    'manifest' => basename($outputPath),
    'sha256' => $sha256,
    'scope' => $manifest['scope'],
    'segments_before_compliance_filters' => $manifest['estimated_segments'],
    'estimated_cost_before_compliance_filters_usd' => $manifest['estimated_cost_usd'],
], JSON_UNESCAPED_SLASHES).PHP_EOL);
