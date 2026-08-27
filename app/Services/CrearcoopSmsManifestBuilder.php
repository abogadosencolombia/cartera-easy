<?php

namespace App\Services;

use DomainException;

class CrearcoopSmsManifestBuilder
{
    public const SOURCE_SPREADSHEET_ID = '1l1_C4YbTVVgGl59fBktW6h7wS-lLZaBl40MKh6s3uvQ';

    public const SOURCE_SHEET = 'Hoja 1';

    public const EXPECTED_TOTAL = 44;

    public const EXPECTED_DEBTORS = 42;

    public const EXPECTED_CODEBTORS = 2;

    public const PRICE_PER_SEGMENT_USD = 0.0592;

    public function __construct(
        private readonly CrearcoopSmsCopy $copy,
        private readonly SmsSegmentCalculator $segments,
    ) {}

    /**
     * @param  array<string, mixed>  $sourcePayload
     * @param  array<string, array<string, mixed>>  $resolutions
     * @return array<string, mixed>
     */
    public function build(array $sourcePayload, array $resolutions, string $campaignKey, string $name): array
    {
        $source = $sourcePayload['source'] ?? [];
        $rows = $sourcePayload['rows'] ?? null;

        if (! is_array($rows)) {
            throw new DomainException('El origen del manifiesto no contiene filas.');
        }

        $this->assertFrozenScope($source, $rows);

        usort($rows, static function (array $left, array $right): int {
            $leftPriority = strtoupper((string) ($left['role'] ?? '')) === 'DEUDOR' ? 0 : 1;
            $rightPriority = strtoupper((string) ($right['role'] ?? '')) === 'DEUDOR' ? 0 : 1;

            return [$leftPriority, (int) ($left['row'] ?? 0)] <=> [$rightPriority, (int) ($right['row'] ?? 0)];
        });

        $recipients = [];
        $identityFingerprints = [];
        $readyPhones = [];

        foreach ($rows as $index => $row) {
            $sourceRole = strtoupper(trim((string) ($row['role'] ?? '')));
            $role = $sourceRole === 'DEUDOR' ? 'debtor' : 'codebtor';
            $sourceRow = (int) ($row['row'] ?? 0);
            $document = $this->normalizeIdentifier($row['document'] ?? '');
            $promissory = $this->normalizeIdentifier($row['promissory'] ?? '');
            $fingerprint = implode('|', [$sourceRole, $document, $promissory]);

            if ($document === '' || $promissory === '' || isset($identityFingerprints[$fingerprint])) {
                throw new DomainException("El lote contiene una identidad duplicada o incompleta en la fila {$sourceRow}.");
            }

            $identityFingerprints[$fingerprint] = true;
            $identityKey = hash('sha256', implode('|', [
                self::SOURCE_SPREADSHEET_ID,
                (string) $sourceRow,
                $sourceRole,
                $document,
                $promissory,
            ]));

            $validPhones = array_values(array_unique(array_filter(
                is_array($row['validPhones'] ?? null) ? $row['validPhones'] : [],
                static fn (mixed $phone): bool => is_string($phone) && preg_match('/^\+573\d{9}$/', $phone) === 1,
            )));
            $resolutionKey = $sourceRow.':'.$sourceRole;
            $resolution = $resolutions[$resolutionKey] ?? null;

            if (count($validPhones) === 1 && $resolution === null) {
                $status = 'ready';
                $phone = $validPhones[0];
                $reason = null;
                $evidence = ['source' => 'sheet_verified_contact'];
            } else {
                if (! is_array($resolution) || ! isset($resolution['status'], $resolution['evidence'])) {
                    throw new DomainException("La fila {$sourceRow} requiere una resolución documentada del teléfono.");
                }

                $status = (string) $resolution['status'];
                $phone = $status === 'ready' ? (string) ($resolution['phone'] ?? '') : null;
                $reason = $status === 'excluded' ? trim((string) ($resolution['reason'] ?? '')) : null;
                $evidence = $resolution['evidence'];

                if (! in_array($status, ['ready', 'excluded'], true)
                    || ($status === 'ready' && preg_match('/^\+573\d{9}$/', (string) $phone) !== 1)
                    || ($status === 'excluded' && $reason === '')) {
                    throw new DomainException("La resolución telefónica de la fila {$sourceRow} no es válida.");
                }
            }

            if ($status === 'ready') {
                if (isset($readyPhones[$phone])) {
                    throw new DomainException("El lote contiene un teléfono duplicado entre identidades (fila {$sourceRow}).");
                }

                $readyPhones[$phone] = true;
            }

            if ($role === 'codebtor') {
                $relatedDebtorName = trim((string) ($row['relatedDebtorName'] ?? ''));

                if ($relatedDebtorName === '') {
                    throw new DomainException("El codeudor de la fila {$sourceRow} no tiene deudor relacionado verificado.");
                }

                $body = $this->copy->forCodebtor($relatedDebtorName);
            } else {
                $body = $this->copy->forDebtor();
            }

            $segmentData = $this->segments->calculate($body);
            $bodyHash = hash('sha256', $body);
            $phoneComponent = $phone === null ? 'excluded' : hash('sha256', $phone);
            $idempotencyKey = hash('sha256', implode('|', [$campaignKey, $identityKey, $phoneComponent, $bodyHash]));

            $recipients[] = [
                'ordinal' => $index + 1,
                'source_row' => $sourceRow,
                'identity_key' => $identityKey,
                'role' => $role,
                'phone' => $phone,
                'phone_fingerprint' => $phone === null ? null : hash('sha256', $phone),
                'body' => $body,
                'segments' => $segmentData['segments'],
                'estimated_cost' => number_format($segmentData['segments'] * self::PRICE_PER_SEGMENT_USD, 4, '.', ''),
                'idempotency_key' => $idempotencyKey,
                'status' => $status,
                'exclusion_reason' => $reason,
                'last_contact_at' => $row['contactedAt'] ?? null,
                'rne_status' => 'pending',
                'evidence' => $evidence,
            ];
        }

        $ready = array_values(array_filter($recipients, static fn (array $row): bool => $row['status'] === 'ready'));
        $estimatedSegments = array_sum(array_column($ready, 'segments'));

        return [
            'schema_version' => 1,
            'campaign_key' => trim($campaignKey),
            'name' => trim($name),
            'source_reference' => 'https://docs.google.com/spreadsheets/d/'.self::SOURCE_SPREADSHEET_ID.'/edit#gid=0',
            'generated_at_utc' => now('UTC')->toIso8601String(),
            'scope' => [
                'total' => self::EXPECTED_TOTAL,
                'debtors' => self::EXPECTED_DEBTORS,
                'codebtors' => self::EXPECTED_CODEBTORS,
                'ready_before_compliance_filters' => count($ready),
                'excluded_for_contact_data' => self::EXPECTED_TOTAL - count($ready),
            ],
            'policy' => [
                'timezone' => SmsLegalWindow::TIMEZONE,
                'not_before' => '2026-08-24T07:00:00-05:00',
                'price_per_segment_usd' => number_format(self::PRICE_PER_SEGMENT_USD, 4, '.', ''),
                'rne_required' => true,
                'opt_out_suppression_required' => true,
                'no_monetary_values' => true,
            ],
            'estimated_segments' => $estimatedSegments,
            'estimated_cost_usd' => number_format($estimatedSegments * self::PRICE_PER_SEGMENT_USD, 4, '.', ''),
            'recipients' => $recipients,
        ];
    }

    /** @param array<string, mixed> $source @param list<array<string, mixed>> $rows */
    private function assertFrozenScope(array $source, array $rows): void
    {
        $roles = array_count_values(array_map(
            static fn (array $row): string => strtoupper(trim((string) ($row['role'] ?? ''))),
            $rows,
        ));

        if (($source['spreadsheet_id'] ?? null) !== self::SOURCE_SPREADSHEET_ID
            || ($source['sheet'] ?? null) !== self::SOURCE_SHEET
            || count($rows) !== self::EXPECTED_TOTAL
            || ($roles['DEUDOR'] ?? 0) !== self::EXPECTED_DEBTORS
            || ($roles['CODEUDOR'] ?? 0) !== self::EXPECTED_CODEBTORS) {
            throw new DomainException('El manifiesto debe conservar exactamente 44 identidades: 42 deudores y 2 codeudores.');
        }
    }

    private function normalizeIdentifier(mixed $value): string
    {
        $value = mb_strtoupper(trim((string) $value), 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return preg_replace('/[^A-Z0-9]/', '', $ascii === false ? $value : $ascii) ?? '';
    }
}
