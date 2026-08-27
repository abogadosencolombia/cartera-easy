<?php

namespace App\Services;

use App\Models\TwilioCampaign;
use App\Models\TwilioCampaignRecipient;
use App\Models\TwilioMessageAttempt;
use App\Models\TwilioStatusEvent;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use JsonException;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class TwilioCampaignService
{
    private const STATUS_RANK = [
        'submitting' => 0,
        'accepted' => 10,
        'scheduled' => 10,
        'queued' => 20,
        'sending' => 30,
        'sent' => 40,
        'delivered' => 100,
        'undelivered' => 100,
        'failed' => 100,
        'canceled' => 100,
        'read' => 110,
    ];

    private const TERMINAL_STATUSES = [
        'delivered',
        'undelivered',
        'failed',
        'canceled',
        'read',
    ];

    public function __construct(private readonly TwilioMessagingService $messaging) {}

    /**
     * Seal a complete campaign manifest. Reusing the same key is allowed only
     * when every normalized manifest field has the same SHA-256 digest.
     */
    public function createFromManifest(array $manifest, array $authorization = []): TwilioCampaign
    {
        $normalized = $this->normalizeManifest($manifest);
        $normalizedAuthorization = $this->normalizeAuthorization(
            $authorization,
            count($normalized['recipients']),
        );
        $manifestSha256 = hash('sha256', $this->canonicalJson([
            'manifest' => $normalized,
            'authorization' => $normalizedAuthorization,
        ]));

        $existing = TwilioCampaign::query()
            ->where('campaign_key', $normalized['campaign_key'])
            ->first();

        if ($existing !== null) {
            return $this->assertSameSealedManifest($existing, $manifestSha256);
        }

        try {
            return DB::transaction(function () use ($normalized, $normalizedAuthorization, $manifestSha256): TwilioCampaign {
                $campaign = TwilioCampaign::query()->create([
                    'campaign_key' => $normalized['campaign_key'],
                    'name' => $normalized['name'],
                    'source_reference' => $normalized['source_reference'],
                    'manifest' => $normalized,
                    'manifest_sha256' => $manifestSha256,
                    'raw_manifest_sha256' => $normalizedAuthorization['raw_manifest_sha256'],
                    'rne_proof_sha256' => $normalizedAuthorization['rne_proof_sha256'],
                    'plan_sha256' => $normalizedAuthorization['plan_sha256'],
                    'recipient_count' => count($normalized['recipients']),
                    'included_count' => $normalizedAuthorization['included_count'],
                    'status' => 'sealed',
                    'sealed_at' => now(),
                    'rne_checked_at' => $normalizedAuthorization['rne_checked_at'],
                    'scheduled_at' => $normalizedAuthorization['scheduled_at'],
                ]);

                foreach ($normalized['recipients'] as $recipient) {
                    $campaign->recipients()->create([
                        'ordinal' => $recipient['ordinal'],
                        'source_row' => $recipient['source_row'],
                        'identity_hash' => $recipient['identity_hash'],
                        'role' => $recipient['role'],
                        'phone' => $recipient['phone'],
                        'phone_hash' => $recipient['phone_hash'],
                        'body' => $recipient['body'],
                        'body_hash' => $recipient['body_hash'],
                        'segments' => $recipient['segments'],
                        'estimated_cost' => $recipient['estimated_cost'],
                        'idempotency_key' => $recipient['idempotency_key'],
                        'exclusion_reason' => $recipient['exclusion_reason'],
                        'status' => $recipient['status'],
                    ]);
                }

                return $campaign->load('recipients');
            });
        } catch (QueryException $exception) {
            // A concurrent creator may have sealed the same campaign key.
            $existing = TwilioCampaign::query()
                ->where('campaign_key', $normalized['campaign_key'])
                ->first();

            if ($existing !== null) {
                return $this->assertSameSealedManifest($existing, $manifestSha256);
            }

            throw $exception;
        }
    }

    /**
     * Submit one ready recipient once. The durable attempt is claimed before
     * the network call, so a retry returns the same attempt without resending.
     */
    public function sendRecipient(TwilioCampaignRecipient $recipient): TwilioMessageAttempt
    {
        [$attempt, $claimedRecipient, $shouldSubmit] = DB::transaction(function () use ($recipient): array {
            $lockedRecipient = TwilioCampaignRecipient::query()
                ->lockForUpdate()
                ->findOrFail($recipient->getKey());

            $existingAttempt = $lockedRecipient->attempts()
                ->where('idempotency_key', $lockedRecipient->idempotency_key)
                ->first();

            if ($existingAttempt !== null) {
                return [$existingAttempt, $lockedRecipient, false];
            }

            if ($lockedRecipient->status !== 'ready') {
                throw new DomainException('Only a ready Twilio campaign recipient may be submitted.');
            }

            if (! is_string($lockedRecipient->phone)
                || ! preg_match('/^\+573\d{9}$/', $lockedRecipient->phone)) {
                throw new DomainException('A ready recipient must have a Colombian mobile number in E.164 format.');
            }

            $attempt = $lockedRecipient->attempts()->create([
                'attempt_number' => 1,
                'idempotency_key' => $lockedRecipient->idempotency_key,
                'status' => 'submitting',
                'segments' => $lockedRecipient->segments,
                'submitted_at' => now(),
            ]);

            $lockedRecipient->status = 'submitting';
            $lockedRecipient->save();

            return [$attempt, $lockedRecipient, true];
        });

        if (! $shouldSubmit) {
            return $attempt->fresh();
        }

        try {
            $response = $this->messaging->send($claimedRecipient->phone, $claimedRecipient->body);
        } catch (Throwable $exception) {
            $this->recordSubmissionFailure($attempt, $claimedRecipient, $exception);

            throw $exception;
        }

        $providerResponse = $response->json();
        $providerResponse = is_array($providerResponse) ? $providerResponse : [];
        $messageSid = (string) ($providerResponse['sid'] ?? '');

        if (! preg_match('/^SM[0-9a-fA-F]{32}$/', $messageSid)) {
            $exception = new UnexpectedValueException('Twilio accepted the request without returning a valid MessageSid.');
            $this->recordSubmissionFailure(
                $attempt,
                $claimedRecipient,
                $exception,
                'submission_unknown',
                $providerResponse,
            );

            throw $exception;
        }

        $status = strtolower(trim((string) ($providerResponse['status'] ?? 'accepted')));
        $status = array_key_exists($status, self::STATUS_RANK) ? $status : 'accepted';
        $responseSegments = filter_var($providerResponse['num_segments'] ?? null, FILTER_VALIDATE_INT);
        $segments = is_int($responseSegments) && $responseSegments > 0
            ? $responseSegments
            : $claimedRecipient->segments;
        $price = is_numeric($providerResponse['price'] ?? null)
            ? (string) $providerResponse['price']
            : null;
        $priceUnit = strtoupper(trim((string) ($providerResponse['price_unit'] ?? '')));
        $priceUnit = preg_match('/^[A-Z]{3}$/', $priceUnit) ? $priceUnit : null;
        $acceptedAt = now();

        return DB::transaction(function () use (
            $attempt,
            $claimedRecipient,
            $messageSid,
            $status,
            $segments,
            $price,
            $priceUnit,
            $providerResponse,
            $acceptedAt,
        ): TwilioMessageAttempt {
            $lockedAttempt = TwilioMessageAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->getKey());

            $lockedAttempt->fill([
                'message_sid' => $messageSid,
                'status' => $status,
                'segments' => $segments,
                'price' => $price,
                'price_unit' => $priceUnit,
                'provider_response' => $providerResponse,
                'accepted_at' => $acceptedAt,
                'resolved_at' => in_array($status, self::TERMINAL_STATUSES, true) ? $acceptedAt : null,
            ])->save();

            $lockedRecipient = TwilioCampaignRecipient::query()
                ->lockForUpdate()
                ->findOrFail($claimedRecipient->getKey());
            $lockedRecipient->status = $status;
            $lockedRecipient->save();

            TwilioStatusEvent::query()->create([
                'twilio_message_attempt_id' => $lockedAttempt->id,
                'message_sid' => $messageSid,
                'event_hash' => $this->eventHash('api_response', $providerResponse),
                'source' => 'api_response',
                'status' => $status,
                'previous_status' => 'submitting',
                'applied' => true,
                'payload' => $providerResponse,
                'received_at' => $acceptedAt,
            ]);

            $this->reconcileOrphanEvents($lockedAttempt, $lockedRecipient);

            return $lockedAttempt->fresh();
        });
    }

    /**
     * Persist an authenticated status callback as an immutable event and move
     * the linked attempt only forward through the provider lifecycle.
     */
    public function recordStatusCallback(array $payload): TwilioStatusEvent
    {
        $messageSid = (string) ($payload['MessageSid'] ?? '');
        $status = strtolower(trim((string) ($payload['MessageStatus'] ?? '')));
        $eventHash = $this->eventHash('callback', $payload);

        return DB::transaction(function () use ($payload, $messageSid, $status, $eventHash): TwilioStatusEvent {
            $existingEvent = TwilioStatusEvent::query()
                ->where('event_hash', $eventHash)
                ->first();

            if ($existingEvent !== null) {
                return $existingEvent;
            }

            $attempt = TwilioMessageAttempt::query()
                ->where('message_sid', $messageSid)
                ->lockForUpdate()
                ->first();
            $previousStatus = $attempt?->status;
            $applied = $attempt !== null && $this->statusAdvances($previousStatus, $status);
            $receivedAt = now();
            $errorCode = $this->nullableString($payload['ErrorCode'] ?? null);
            $errorMessage = $this->nullableString($payload['ErrorMessage'] ?? null);

            $event = TwilioStatusEvent::query()->create([
                'twilio_message_attempt_id' => $attempt?->id,
                'message_sid' => $messageSid,
                'event_hash' => $eventHash,
                'source' => 'callback',
                'status' => $status,
                'previous_status' => $previousStatus,
                'applied' => $applied,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'payload' => $payload,
                'received_at' => $receivedAt,
            ]);

            if ($attempt === null) {
                return $event;
            }

            $attempt->last_callback_at = $receivedAt;

            if ($applied) {
                $attempt->status = $status;
                $attempt->provider_error_code = $errorCode;
                $attempt->provider_error_message = $errorMessage;

                if (in_array($status, self::TERMINAL_STATUSES, true)) {
                    $attempt->resolved_at = $receivedAt;
                }
            }

            $attempt->save();

            if ($applied) {
                $recipient = $attempt->recipient()->lockForUpdate()->firstOrFail();
                $recipient->status = $status;
                $recipient->save();
            }

            return $event;
        });
    }

    private function normalizeManifest(array $manifest): array
    {
        $validator = Validator::make($manifest, [
            'campaign_key' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'source_reference' => ['nullable', 'string', 'max:512'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*.ordinal' => ['required', 'integer', 'min:1', 'distinct:strict'],
            'recipients.*.source_row' => ['nullable', 'integer', 'min:1'],
            'recipients.*.identity_key' => ['required', 'string', 'max:500'],
            'recipients.*.role' => ['required', Rule::in(['debtor', 'codebtor'])],
            'recipients.*.phone' => ['nullable', 'string', 'max:20'],
            'recipients.*.body' => ['required', 'string', 'max:5000'],
            'recipients.*.segments' => ['required', 'integer', 'min:1', 'max:100'],
            'recipients.*.estimated_cost' => ['required', 'numeric', 'min:0'],
            'recipients.*.idempotency_key' => ['nullable', 'string', 'max:500'],
            'recipients.*.status' => ['required', Rule::in(['ready', 'excluded'])],
            'recipients.*.exclusion_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $validator->after(function ($validator) use ($manifest): void {
            $readyPhones = [];

            foreach (($manifest['recipients'] ?? []) as $index => $recipient) {
                if (! is_array($recipient)) {
                    continue;
                }

                $status = $recipient['status'] ?? null;
                $phone = $recipient['phone'] ?? null;
                $phone = is_string($phone) ? trim($phone) : $phone;
                $reason = $recipient['exclusion_reason'] ?? null;

                if ($status === 'ready' && (! is_string($phone) || ! preg_match('/^\+573\d{9}$/', $phone))) {
                    $validator->errors()->add(
                        "recipients.{$index}.phone",
                        'A ready recipient requires a Colombian mobile number in E.164 format.'
                    );
                }

                if ($status === 'ready' && is_string($phone) && preg_match('/^\+573\d{9}$/', $phone)) {
                    if (isset($readyPhones[$phone])) {
                        $validator->errors()->add(
                            "recipients.{$index}.phone",
                            'A phone number may appear only once among ready campaign recipients.'
                        );
                    }

                    $readyPhones[$phone] = true;
                }

                if ($phone !== null && $phone !== ''
                    && (! is_string($phone) || ! preg_match('/^\+573\d{9}$/', $phone))) {
                    $validator->errors()->add(
                        "recipients.{$index}.phone",
                        'The phone number must be a Colombian mobile number in E.164 format.'
                    );
                }

                if ($status === 'excluded' && (! is_string($reason) || trim($reason) === '')) {
                    $validator->errors()->add(
                        "recipients.{$index}.exclusion_reason",
                        'An excluded recipient requires an exclusion reason.'
                    );
                }
            }
        });

        $validated = $validator->validate();
        $secret = (string) config('app.key');

        if ($secret === '') {
            throw new RuntimeException('APP_KEY is required to protect Twilio campaign identities.');
        }

        $campaignKey = trim($validated['campaign_key']);
        $recipients = [];

        foreach ($validated['recipients'] as $recipient) {
            $phone = isset($recipient['phone']) && trim((string) $recipient['phone']) !== ''
                ? trim((string) $recipient['phone'])
                : null;
            $body = str_replace(["\r\n", "\r"], "\n", $recipient['body']);
            $identityHash = hash_hmac('sha256', $recipient['identity_key'], $secret);
            $phoneHash = $phone === null ? null : hash_hmac('sha256', $phone, $secret);
            $bodyHash = hash('sha256', $body);
            $providedIdempotencyKey = trim((string) ($recipient['idempotency_key'] ?? ''));
            $idempotencySeed = $providedIdempotencyKey !== ''
                ? $campaignKey.'|provided|'.$providedIdempotencyKey
                : implode('|', [
                    $campaignKey,
                    (string) $recipient['ordinal'],
                    $identityHash,
                    $recipient['role'],
                    $phoneHash ?? 'no-phone',
                    $bodyHash,
                ]);

            $recipients[] = [
                'ordinal' => (int) $recipient['ordinal'],
                'source_row' => isset($recipient['source_row']) ? (int) $recipient['source_row'] : null,
                'identity_hash' => $identityHash,
                'role' => $recipient['role'],
                'phone' => $phone,
                'phone_hash' => $phoneHash,
                'body' => $body,
                'body_hash' => $bodyHash,
                'segments' => (int) $recipient['segments'],
                'estimated_cost' => number_format((float) $recipient['estimated_cost'], 6, '.', ''),
                'idempotency_key' => hash('sha256', $idempotencySeed),
                'status' => $recipient['status'],
                'exclusion_reason' => isset($recipient['exclusion_reason'])
                    ? trim((string) $recipient['exclusion_reason'])
                    : null,
            ];
        }

        usort($recipients, fn (array $left, array $right): int => $left['ordinal'] <=> $right['ordinal']);

        return [
            'version' => 1,
            'campaign_key' => $campaignKey,
            'name' => trim($validated['name']),
            'source_reference' => isset($validated['source_reference'])
                ? trim((string) $validated['source_reference'])
                : null,
            'recipients' => $recipients,
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeAuthorization(array $authorization, int $recipientCount): array
    {
        if ($authorization === []) {
            return [
                'raw_manifest_sha256' => null,
                'rne_proof_sha256' => null,
                'plan_sha256' => null,
                'rne_checked_at' => null,
                'scheduled_at' => null,
                'included_count' => null,
            ];
        }

        $validated = Validator::make($authorization, [
            'raw_manifest_sha256' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/'],
            'rne_proof_sha256' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/'],
            'plan_sha256' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/'],
            'rne_checked_at' => ['required', 'date'],
            'scheduled_at' => ['required', 'date'],
            'included_count' => ['required', 'integer', 'min:1', 'max:'.$recipientCount],
        ])->validate();

        $rneCheckedAt = CarbonImmutable::parse($validated['rne_checked_at'])
            ->setTimezone(SmsLegalWindow::TIMEZONE);
        $scheduledAt = CarbonImmutable::parse($validated['scheduled_at'])
            ->setTimezone(SmsLegalWindow::TIMEZONE);

        if ($rneCheckedAt->greaterThan($scheduledAt)) {
            throw new DomainException('The RNE check cannot occur after the scheduled campaign time.');
        }

        return [
            'raw_manifest_sha256' => $validated['raw_manifest_sha256'],
            'rne_proof_sha256' => $validated['rne_proof_sha256'],
            'plan_sha256' => $validated['plan_sha256'],
            'rne_checked_at' => $rneCheckedAt->toIso8601String(),
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'included_count' => (int) $validated['included_count'],
        ];
    }

    private function assertSameSealedManifest(TwilioCampaign $campaign, string $manifestSha256): TwilioCampaign
    {
        if (! hash_equals($campaign->manifest_sha256, $manifestSha256)) {
            throw new DomainException('The campaign key is already sealed with a different manifest.');
        }

        return $campaign->loadMissing('recipients');
    }

    private function recordSubmissionFailure(
        TwilioMessageAttempt $attempt,
        TwilioCampaignRecipient $recipient,
        Throwable $exception,
        ?string $forcedStatus = null,
        ?array $providerResponse = null,
    ): void {
        $status = $forcedStatus
            ?? ($exception instanceof ConnectionException ? 'submission_unknown' : 'rejected');

        if ($providerResponse === null && $exception instanceof RequestException) {
            $response = $exception->response->json();
            $providerResponse = is_array($response) ? $response : null;
        }

        DB::transaction(function () use (
            $attempt,
            $recipient,
            $exception,
            $status,
            $providerResponse,
        ): void {
            $lockedAttempt = TwilioMessageAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->getKey());
            $lockedAttempt->fill([
                'status' => $status,
                'provider_error_code' => $exception instanceof RequestException
                    ? (string) $exception->response->status()
                    : null,
                'provider_error_message' => mb_substr($exception->getMessage(), 0, 1000),
                'provider_response' => $providerResponse,
                'resolved_at' => $status === 'rejected' ? now() : null,
            ])->save();

            $lockedRecipient = TwilioCampaignRecipient::query()
                ->lockForUpdate()
                ->findOrFail($recipient->getKey());
            $lockedRecipient->status = $status;
            $lockedRecipient->save();
        });
    }

    private function statusAdvances(?string $current, string $incoming): bool
    {
        if ($current === $incoming || ! array_key_exists($incoming, self::STATUS_RANK)) {
            return false;
        }

        if ($current !== null && in_array($current, self::TERMINAL_STATUSES, true)) {
            return false;
        }

        if ($current === null || ! array_key_exists($current, self::STATUS_RANK)) {
            return true;
        }

        return self::STATUS_RANK[$incoming] >= self::STATUS_RANK[$current];
    }

    private function reconcileOrphanEvents(
        TwilioMessageAttempt $attempt,
        TwilioCampaignRecipient $recipient,
    ): void {
        $events = TwilioStatusEvent::query()
            ->whereNull('twilio_message_attempt_id')
            ->where('message_sid', $attempt->message_sid)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($events as $event) {
            $previousStatus = $attempt->status;
            $applied = $this->statusAdvances($previousStatus, $event->status);

            $event->twilio_message_attempt_id = $attempt->id;
            $event->previous_status = $previousStatus;
            $event->applied = $applied;
            $event->save();

            if ($attempt->last_callback_at === null
                || $event->received_at->isAfter($attempt->last_callback_at)) {
                $attempt->last_callback_at = $event->received_at;
            }

            if (! $applied) {
                continue;
            }

            $attempt->status = $event->status;
            $attempt->provider_error_code = $event->error_code;
            $attempt->provider_error_message = $event->error_message;

            if (in_array($event->status, self::TERMINAL_STATUSES, true)) {
                $attempt->resolved_at = $event->received_at;
            }
        }

        $attempt->save();
        $recipient->status = $attempt->status;
        $recipient->save();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function eventHash(string $source, array $payload): string
    {
        return hash('sha256', $source.'|'.$this->canonicalJson($payload));
    }

    /** @throws JsonException */
    private function canonicalJson(array $value): string
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
