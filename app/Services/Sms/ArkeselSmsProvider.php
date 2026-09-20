<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use App\Exceptions\SmsDeliveryException;
use Illuminate\Support\Facades\Http;

class ArkeselSmsProvider implements SmsProvider
{
    public function send(string $phoneNumber, string $message): void
    {
        $key = (string) config('sms.api_key');

        if ($key === '') {
            throw new SmsDeliveryException('SMS_API_KEY is not configured for Arkesel.');
        }

        $response = Http::timeout((int) config('sms.timeout'))
            ->acceptJson()
            ->withHeaders([
                'api-key' => $key,
            ])
            ->post('https://sms.arkesel.com/api/v2/sms/send', [
                'sender' => (string) config('sms.sender_id'),
                'message' => $message,
                'recipients' => [$phoneNumber],
            ]);

        if ($response->failed()) {
            throw new SmsDeliveryException('The SMS gateway rejected the message.');
        }

        $status = strtolower((string) $response->json('status', ''));

        if ($status !== '' && ! in_array($status, ['success', 'ok'], true)) {
            throw new SmsDeliveryException('The SMS gateway could not deliver the message.');
        }
    }
}
