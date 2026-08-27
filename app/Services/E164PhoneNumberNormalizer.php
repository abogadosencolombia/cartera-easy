<?php

namespace App\Services;

class E164PhoneNumberNormalizer
{
    public function normalize(string $phoneNumber): ?string
    {
        $candidate = trim($phoneNumber);

        if (
            $candidate === ''
            || substr_count($candidate, '+') !== 1
            || ! str_starts_with($candidate, '+')
            || ! preg_match('/^\+[0-9\s().-]+$/uD', $candidate)
        ) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $candidate);

        if ($digits === null || ! preg_match('/^[1-9][0-9]{7,14}$/D', $digits)) {
            return null;
        }

        return '+'.$digits;
    }
}
