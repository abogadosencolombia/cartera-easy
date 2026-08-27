<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Throwable;

class RneProofVerifier
{
    /**
     * @param  array<string, mixed>  $proof
     * @param  list<string>  $expectedPhoneFingerprints
     * @return array<string, 'clear'|'excluded'>
     */
    public function verify(
        array $proof,
        string $campaignKey,
        string $manifestSha256,
        CarbonImmutable $scheduledAt,
        array $expectedPhoneFingerprints,
    ): array {
        if (($proof['schema_version'] ?? null) !== 1
            || ($proof['source'] ?? null) !== 'CRC_RNE'
            || ($proof['campaign_key'] ?? null) !== $campaignKey
            || ! is_string($proof['manifest_sha256'] ?? null)
            || ! hash_equals($manifestSha256, $proof['manifest_sha256'])
            || ! is_array($proof['results'] ?? null)) {
            throw new DomainException('La constancia RNE no corresponde a esta campaña y manifiesto.');
        }

        try {
            $checkedAt = CarbonImmutable::parse((string) ($proof['checked_at'] ?? ''))
                ->setTimezone(SmsLegalWindow::TIMEZONE);
        } catch (Throwable) {
            throw new DomainException('La constancia RNE no tiene una fecha válida.');
        }

        $plannedLocal = $scheduledAt->setTimezone(SmsLegalWindow::TIMEZONE);

        if ($checkedAt->format('Y-m-d') !== $plannedLocal->format('Y-m-d')
            || $checkedAt->format('H:i:s') < '03:00:00'
            || $checkedAt->greaterThan($plannedLocal)) {
            throw new DomainException('La constancia RNE debe verificarse el mismo día, después de las 03:00 y antes del envío.');
        }

        $expected = array_values(array_unique($expectedPhoneFingerprints));
        sort($expected, SORT_STRING);
        $statuses = [];

        foreach ($proof['results'] as $result) {
            $fingerprint = is_array($result) ? (string) ($result['phone_fingerprint'] ?? '') : '';
            $status = is_array($result) ? (string) ($result['status'] ?? '') : '';

            if (! preg_match('/^[0-9a-f]{64}$/', $fingerprint)
                || ! in_array($status, ['clear', 'excluded'], true)
                || isset($statuses[$fingerprint])) {
                throw new DomainException('La constancia RNE contiene resultados inválidos o duplicados.');
            }

            $statuses[$fingerprint] = $status;
        }

        $actual = array_keys($statuses);
        sort($actual, SORT_STRING);

        if ($actual !== $expected) {
            throw new DomainException('La constancia RNE no cubre exactamente todos los teléfonos del manifiesto.');
        }

        return $statuses;
    }
}
