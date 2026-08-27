<?php

namespace App\Services;

use Illuminate\Http\Request;

class TwilioRequestValidator
{
    public function isValid(Request $request): bool
    {
        $authToken = trim((string) config('services.twilio.auth_token'));
        $callbackUrl = trim((string) config('services.twilio.status_callback_url'));
        $providedSignature = trim((string) $request->header('X-Twilio-Signature'));

        if ($authToken === '' || $callbackUrl === '' || $providedSignature === '') {
            return false;
        }

        $params = $request->request->all();
        ksort($params, SORT_STRING);

        $signedPayload = $callbackUrl;

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
