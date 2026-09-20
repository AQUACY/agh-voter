<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use App\Exceptions\SmsDeliveryException;
use Illuminate\Support\Facades\Http;

class HubtelSmsProvider implements SmsProvider
{
    public function send(string $phoneNumber, string $message): void
    {
        $clientId = (string) config('sms.api_key');
        $clientSecret = (string) config('sms.api_secret');

        if ($clientId === '' || $clientSecret === '') {
            throw new SmsDeliveryException('SMS_API_KEY and SMS_API_SECRET are required for Hubtel.');
        }

        $response = Http::timeout((int) config('sms.timeout'))
            ->acceptJson()
            ->withBasicAuth($clientId, $clientSecret)
            ->post('https://smsc.hubtel.com/v1/messages/send', [
                'From' => (string) config('sms.sender_id'),
                'To' => $phoneNumber,
                'Content' => $message,
            ]);

        if ($response->failed()) {
            throw new SmsDeliveryException('The SMS gateway rejected the message.');
        }
    }
}
