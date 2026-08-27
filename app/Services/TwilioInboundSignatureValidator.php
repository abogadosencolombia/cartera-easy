<?php

namespace App\Services;

use Illuminate\Http\Request;

class TwilioInboundSignatureValidator
{
    public function isValid(Request $request): bool
    {
        $authToken = trim((string) config('services.twilio.auth_token'));
        $webhookUrl = trim((string) config('services.twilio.inbound_webhook_url'));
        $providedSignature = trim((string) $request->header('X-Twilio-Signature'));

        if ($authToken === '' || $webhookUrl === '' || $providedSignature === '') {
            return false;
        }

        $params = $request->request->all();
        ksort($params, SORT_STRING);

        $signedPayload = $webhookUrl;

        foreach ($params as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $signedPayload .= $name.(string) $item;
                }

                continue;
            }

            $signedPayload .= $name.(string) $value;
        }

        $expectedSignature = base64_encode(
            hash_hmac('sha1', $signedPayload, $authToken, true)
        );

        return hash_equals($expectedSignature, $providedSignature);
    }
}
