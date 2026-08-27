<?php

namespace App\Services;

use App\Models\SmsSuppression;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use RuntimeException;

class SmsSuppressionService
{
    public function __construct(
        private readonly E164PhoneNumberNormalizer $phoneNumberNormalizer,
    ) {}

    public function isSuppressed(string $phoneNumber): bool
    {
        $phoneE164 = $this->normalizeOrFail($phoneNumber);

        return SmsSuppression::query()
            ->where('phone_hash', $this->phoneHash($phoneE164))
            ->exists();
    }

    public function suppress(string $phoneNumber, string $source, string $reason): SmsSuppression
    {
        $phoneE164 = $this->normalizeOrFail($phoneNumber);
        $phoneHash = $this->phoneHash($phoneE164);
        $timestamp = now();

        SmsSuppression::query()->upsert(
            [[
                'phone_hash' => $phoneHash,
                'phone_e164' => Crypt::encryptString($phoneE164),
                'source' => $source,
                'reason' => $reason,
                'first_suppressed_at' => $timestamp,
                'last_suppressed_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]],
            ['phone_hash'],
            ['source', 'reason', 'last_suppressed_at', 'updated_at'],
        );

        return SmsSuppression::query()
            ->where('phone_hash', $phoneHash)
            ->firstOrFail();
    }

    private function normalizeOrFail(string $phoneNumber): string
    {
        $phoneE164 = $this->phoneNumberNormalizer->normalize($phoneNumber);

        if ($phoneE164 === null) {
            throw new InvalidArgumentException('El número no tiene un formato E.164 válido.');
        }

        return $phoneE164;
    }

    private function phoneHash(string $phoneE164): string
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $decodedKey = base64_decode(substr($key, 7), true);

            if ($decodedKey !== false) {
                $key = $decodedKey;
            }
        }

        if ($key === '') {
            throw new RuntimeException('APP_KEY es obligatorio para consultar supresiones SMS.');
        }

        return hash_hmac('sha256', $phoneE164, $key);
    }
}
