<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DomainException;

class CrearcoopSmsCampaignPlanner
{
    private const REASON_PRIORITY = [
        'no_verified_mobile',
        'invalid_contact_data',
        'contacted_today',
        'contacted_same_week',
        'invalid_timezone',
        'holiday',
        'sunday',
        'outside_legal_hours',
        'opt_out',
        'rne_excluded',
        'rne_pending',
    ];

    public function __construct(
        private readonly SmsLegalWindow $legalWindow,
        private readonly SmsSuppressionService $suppressions,
        private readonly RneProofVerifier $rneProofs,
    ) {}

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>|null  $rneProof
     * @return array<string, mixed>
     */
    public function plan(
        array $manifest,
        string $manifestSha256,
        CarbonImmutable $scheduledAt,
        ?array $rneProof = null,
    ): array {
        $recipients = $this->validateFrozenScope($manifest);
        $readyRecipients = array_values(array_filter(
            $recipients,
            static fn (array $recipient): bool => ($recipient['status'] ?? null) === 'ready',
        ));
        $fingerprints = array_map(
            static fn (array $recipient): string => (string) $recipient['phone_fingerprint'],
            $readyRecipients,
        );
        $rneStatuses = $rneProof === null
            ? []
            : $this->rneProofs->verify(
                $rneProof,
                (string) $manifest['campaign_key'],
                $manifestSha256,
                $scheduledAt,
                $fingerprints,
            );

        $decisions = [];
        $included = 0;
        $segments = 0;
        $cost = 0.0;
        $primaryReasons = [];
        $reasonOccurrences = [];

        foreach ($recipients as $recipient) {
            $reasons = [];

            if (($recipient['status'] ?? null) === 'excluded') {
                $reasons[] = trim((string) ($recipient['exclusion_reason'] ?? 'invalid_contact_data'));
            } else {
                $lastContactAt = $this->legalWindow->parseContactAt($recipient['last_contact_at'] ?? null);
                $legal = $this->legalWindow->evaluate($scheduledAt, $lastContactAt);
                $reasons = [...$reasons, ...$legal['reasons']];
                $phone = (string) ($recipient['phone'] ?? '');

                if ($this->suppressions->isSuppressed($phone)) {
                    $reasons[] = 'opt_out';
                }

                $fingerprint = (string) ($recipient['phone_fingerprint'] ?? '');

                if ($rneProof === null) {
                    $reasons[] = 'rne_pending';
                } elseif (($rneStatuses[$fingerprint] ?? null) === 'excluded') {
                    $reasons[] = 'rne_excluded';
                }
            }

            $reasons = array_values(array_unique(array_filter($reasons)));
            $isIncluded = $reasons === [];

            if ($isIncluded) {
                $included++;
                $segments += (int) ($recipient['segments'] ?? 0);
                $cost += (float) ($recipient['estimated_cost'] ?? 0);
            } else {
                $primary = $this->primaryReason($reasons);
                $primaryReasons[$primary] = ($primaryReasons[$primary] ?? 0) + 1;

                foreach ($reasons as $reason) {
                    $reasonOccurrences[$reason] = ($reasonOccurrences[$reason] ?? 0) + 1;
                }
            }

            $decisions[] = [
                'ordinal' => (int) $recipient['ordinal'],
                'source_row' => (int) $recipient['source_row'],
                'role' => (string) $recipient['role'],
                'status' => $isIncluded ? 'included' : 'excluded',
                'reasons' => $reasons,
            ];
        }

        ksort($primaryReasons);
        ksort($reasonOccurrences);

        $plan = [
            'campaign_key' => (string) $manifest['campaign_key'],
            'manifest_sha256' => $manifestSha256,
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'scope_total' => CrearcoopSmsManifestBuilder::EXPECTED_TOTAL,
            'data_ready' => count($readyRecipients),
            'included' => $included,
            'excluded' => CrearcoopSmsManifestBuilder::EXPECTED_TOTAL - $included,
            'excluded_by_primary_reason' => $primaryReasons,
            'reason_occurrences' => $reasonOccurrences,
            'segments' => $segments,
            'estimated_cost_usd' => number_format($cost, 4, '.', ''),
            'rne_proof' => $rneProof === null ? 'pending' : 'verified',
            'rne_proof_sha256' => $rneProof === null
                ? null
                : hash('sha256', $this->canonicalJson($rneProof)),
            'recipient_decisions' => $decisions,
        ];

        $plan['plan_sha256'] = hash('sha256', $this->canonicalJson($plan));

        return $plan;
    }

    /** @param array<string, mixed> $manifest @return list<array<string, mixed>> */
    private function validateFrozenScope(array $manifest): array
    {
        $recipients = $manifest['recipients'] ?? null;

        if (! is_array($recipients)) {
            throw new DomainException('El manifiesto no contiene destinatarios.');
        }

        $roles = array_count_values(array_map(
            static fn (array $recipient): string => (string) ($recipient['role'] ?? ''),
            $recipients,
        ));
        $ordinals = array_map(static fn (array $recipient): int => (int) ($recipient['ordinal'] ?? 0), $recipients);
        sort($ordinals, SORT_NUMERIC);

        $scope = $manifest['scope'] ?? [];
        $sourceReference = (string) ($manifest['source_reference'] ?? '');

        if (count($recipients) !== CrearcoopSmsManifestBuilder::EXPECTED_TOTAL
            || ($roles['debtor'] ?? 0) !== CrearcoopSmsManifestBuilder::EXPECTED_DEBTORS
            || ($roles['codebtor'] ?? 0) !== CrearcoopSmsManifestBuilder::EXPECTED_CODEBTORS
            || ($scope['total'] ?? null) !== CrearcoopSmsManifestBuilder::EXPECTED_TOTAL
            || ($scope['debtors'] ?? null) !== CrearcoopSmsManifestBuilder::EXPECTED_DEBTORS
            || ($scope['codebtors'] ?? null) !== CrearcoopSmsManifestBuilder::EXPECTED_CODEBTORS
            || $ordinals !== range(1, CrearcoopSmsManifestBuilder::EXPECTED_TOTAL)
            || ! str_contains($sourceReference, CrearcoopSmsManifestBuilder::SOURCE_SPREADSHEET_ID)) {
            throw new DomainException('El comando acepta únicamente el manifiesto exacto de 44: 42 deudores y 2 codeudores.');
        }

        $ordered = $recipients;
        usort($ordered, static fn (array $left, array $right): int => (int) $left['ordinal'] <=> (int) $right['ordinal']);
        $orderedRoles = array_column($ordered, 'role');

        if (array_unique(array_slice($orderedRoles, 0, CrearcoopSmsManifestBuilder::EXPECTED_DEBTORS)) !== ['debtor']
            || array_unique(array_slice($orderedRoles, CrearcoopSmsManifestBuilder::EXPECTED_DEBTORS)) !== ['codebtor']) {
            throw new DomainException('El orden sellado debe conservar deudores primero y codeudores después.');
        }

        foreach ($recipients as $recipient) {
            $status = $recipient['status'] ?? null;

            if (! in_array($status, ['ready', 'excluded'], true)
                || ($status === 'ready' && (
                    preg_match('/^\+573\d{9}$/', (string) ($recipient['phone'] ?? '')) !== 1
                    || ! preg_match('/^[0-9a-f]{64}$/', (string) ($recipient['phone_fingerprint'] ?? ''))
                ))
                || ($status === 'excluded' && trim((string) ($recipient['exclusion_reason'] ?? '')) === '')) {
                throw new DomainException('El manifiesto contiene un destinatario sin decisión verificable.');
            }
        }

        return $ordered;
    }

    /** @param list<string> $reasons */
    private function primaryReason(array $reasons): string
    {
        foreach (self::REASON_PRIORITY as $reason) {
            if (in_array($reason, $reasons, true)) {
                return $reason;
            }
        }

        return $reasons[0] ?? 'unknown';
    }

    private function canonicalJson(mixed $value): string
    {
        $sort = function (mixed $item) use (&$sort): mixed {
            if (! is_array($item)) {
                return $item;
            }

            if (! array_is_list($item)) {
                ksort($item, SORT_STRING);
            }

            foreach ($item as $key => $nested) {
                $item[$key] = $sort($nested);
            }

            return $item;
        };

        return json_encode(
            $sort($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
