<?php

use App\Services\SmsSegmentCalculator;

test('counts the approved debtor copy as one GSM-7 segment', function () {
    $text = 'CREARCOOP lo invita a contactar a Abogados en Colombia SAS para revisar su obligacion. Tel/WhatsApp 3152819233. Correo: abogadosencolombiasas@gmail.com. STOP.';

    $result = (new SmsSegmentCalculator)->calculate($text);

    expect($result)->toMatchArray([
        'encoding' => 'GSM-7',
        'units' => 158,
        'segments' => 1,
        'per_segment_limit' => 160,
    ]);
});

test('uses concatenated GSM-7 limits and counts extension characters as two septets', function () {
    $calculator = new SmsSegmentCalculator;

    expect($calculator->calculate(str_repeat('a', 161))['segments'])->toBe(2)
        ->and($calculator->calculate(str_repeat('a', 158).'{}')['units'])->toBe(162)
        ->and($calculator->calculate(str_repeat('a', 158).'{}')['segments'])->toBe(2);
});

test('switches to UCS-2 when a non GSM character is present', function () {
    $result = (new SmsSegmentCalculator)->calculate(str_repeat('a', 69).'ó');

    expect($result)->toMatchArray([
        'encoding' => 'UCS-2',
        'units' => 70,
        'segments' => 1,
        'per_segment_limit' => 70,
    ]);

    expect((new SmsSegmentCalculator)->calculate(str_repeat('a', 70).'ó')['segments'])->toBe(2);
});

test('rejects an empty message', function () {
    expect(fn () => (new SmsSegmentCalculator)->calculate(''))
        ->toThrow(InvalidArgumentException::class);
});
