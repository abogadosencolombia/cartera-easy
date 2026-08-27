<?php

namespace App\Services;

use InvalidArgumentException;
use LengthException;

class CrearcoopSmsCopy
{
    public const DEBTOR_MESSAGE = 'CREARCOOP lo invita a contactar a Abogados en Colombia SAS para revisar su obligacion. Tel/WhatsApp 3152819233. Correo: abogadosencolombiasas@gmail.com. STOP.';

    public function __construct(private readonly SmsSegmentCalculator $segments) {}

    public function forDebtor(): string
    {
        return self::DEBTOR_MESSAGE;
    }

    public function forCodebtor(string $relatedDebtorName): string
    {
        $name = $this->asciiName($relatedDebtorName);

        if ($name === '') {
            throw new InvalidArgumentException('El codeudor requiere el nombre verificado del deudor relacionado.');
        }

        $message = "CREARCOOP: Usted es codeudor de {$name}. Contacte Abogados en Colombia SAS: Tel/WhatsApp 3152819233, abogadosencolombiasas@gmail.com. STOP.";

        if ($this->segments->calculate($message)['segments'] !== 1) {
            throw new LengthException('El mensaje personalizado del codeudor excede un segmento SMS.');
        }

        return $message;
    }

    private function asciiName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        if ($name === '') {
            return '';
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);

        if ($ascii === false) {
            throw new InvalidArgumentException('No fue posible normalizar el nombre del deudor a GSM-7.');
        }

        $ascii = trim(preg_replace("/[^A-Za-z0-9 .'-]/", '', $ascii) ?? '');

        return trim(preg_replace('/\s+/', ' ', $ascii) ?? '');
    }
}
