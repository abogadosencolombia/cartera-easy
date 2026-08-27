<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class TwilioMessagingService
{
    public function send(string $to, string $message, ?string $mediaUrl = null): Response
    {
        $accountSid = (string) config('services.twilio.account_sid');
        $apiKeySid = (string) config('services.twilio.api_key_sid');
        $apiKeySecret = (string) config('services.twilio.api_key_secret');
        $authToken = (string) config('services.twilio.auth_token');
        $from = (string) config('services.twilio.from');
        $messagingServiceSid = (string) config('services.twilio.messaging_service_sid');
        $statusCallbackUrl = (string) config('services.twilio.status_callback_url');

        if ($accountSid === '') {
            throw new RuntimeException('TWILIO_ACCOUNT_SID no está configurado.');
        }

        if ($apiKeySid !== '' && $apiKeySecret !== '') {
            $username = $apiKeySid;
            $password = $apiKeySecret;
        } elseif ($authToken !== '') {
            $username = $accountSid;
            $password = $authToken;
        } else {
            throw new RuntimeException(
                'Configura TWILIO_API_KEY_SID y TWILIO_API_KEY_SECRET, o TWILIO_AUTH_TOKEN para pruebas.'
            );
        }

        if ($messagingServiceSid === '' && $from === '') {
            throw new RuntimeException(
                'Configura TWILIO_MESSAGING_SERVICE_SID o TWILIO_FROM como remitente.'
            );
        }

        if (! preg_match('/^\+[1-9]\d{7,14}$/', $to)) {
            throw new InvalidArgumentException('El destinatario debe estar en formato E.164, por ejemplo +573001234567.');
        }

        $payload = [
            'To' => $to,
            'Body' => $message,
        ];

        if ($messagingServiceSid !== '') {
            $payload['MessagingServiceSid'] = $messagingServiceSid;
        } else {
            $payload['From'] = $from;
        }

        if ($mediaUrl !== null) {
            if (! filter_var($mediaUrl, FILTER_VALIDATE_URL) || ! str_starts_with($mediaUrl, 'https://')) {
                throw new InvalidArgumentException('La URL del archivo multimedia debe ser una URL HTTPS pública.');
            }

            $payload['MediaUrl'] = $mediaUrl;
        }

        if ($statusCallbackUrl !== '') {
            $payload['StatusCallback'] = $statusCallbackUrl;
        }

        return Http::asForm()
            ->withBasicAuth($username, $password)
            ->timeout(20)
            ->post(
                'https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode($accountSid).'/Messages.json',
                $payload
            )
            ->throw();
    }
}
