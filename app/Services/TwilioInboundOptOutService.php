<?php

namespace App\Services;

use App\Models\SmsSuppression;
use Illuminate\Support\Str;

class TwilioInboundOptOutService
{
    public function __construct(
        private readonly SmsSuppressionService $smsSuppressionService,
    ) {}

    public function handle(string $from, ?string $body, ?string $optOutType): ?SmsSuppression
    {
        [$source, $reason] = $this->suppressionReason($body, $optOutType);

        if ($source === null || $reason === null) {
            return null;
        }

        return $this->smsSuppressionService->suppress($from, $source, $reason);
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function suppressionReason(?string $body, ?string $optOutType): array
    {
        if ($this->normalizeKeyword($optOutType) === 'STOP') {
            return ['twilio_advanced_opt_out', 'opt_out_type_stop'];
        }

        $keyword = $this->normalizeKeyword($body);

        if (in_array($keyword, ['STOP', 'SALIR'], true)) {
            return ['twilio_inbound_keyword', 'keyword_'.strtolower($keyword)];
        }

        return [null, null];
    }

    private function normalizeKeyword(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $withoutWhitespace = preg_replace('/\s+/u', '', trim($value));

        if ($withoutWhitespace === null) {
            return '';
        }

        return strtoupper(Str::ascii($withoutWhitespace));
    }
}
