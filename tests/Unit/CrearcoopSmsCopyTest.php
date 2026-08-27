<?php

use App\Services\CrearcoopSmsCopy;
use App\Services\SmsSegmentCalculator;

test('returns the approved debtor message verbatim', function () {
    $copy = new CrearcoopSmsCopy(new SmsSegmentCalculator);

    expect($copy->forDebtor())->toBe(
        'CREARCOOP lo invita a contactar a Abogados en Colombia SAS para revisar su obligacion. Tel/WhatsApp 3152819233. Correo: abogadosencolombiasas@gmail.com. STOP.'
    );
});

test('identifies the related debtor in the codebtor message and keeps GSM-7', function () {
    $copy = new CrearcoopSmsCopy(new SmsSegmentCalculator);
    $message = $copy->forCodebtor('María José Álvarez de Ocampo');
    $segments = (new SmsSegmentCalculator)->calculate($message);

    expect($message)->toContain('codeudor de Maria Jose Alvarez de Ocampo')
        ->toContain('Abogados en Colombia SAS')
        ->toContain('3152819233')
        ->toContain('abogadosencolombiasas@gmail.com')
        ->toEndWith('STOP.')
        ->and($segments['encoding'])->toBe('GSM-7')
        ->and($segments['segments'])->toBe(1);
});

test('rejects a blank related debtor name', function () {
    expect(fn () => (new CrearcoopSmsCopy(new SmsSegmentCalculator))->forCodebtor('   '))
        ->toThrow(InvalidArgumentException::class);
});

test('rejects personalized copy that would exceed one segment', function () {
    expect(fn () => (new CrearcoopSmsCopy(new SmsSegmentCalculator))->forCodebtor(str_repeat('NombreMuyLargo', 10)))
        ->toThrow(LengthException::class);
});
