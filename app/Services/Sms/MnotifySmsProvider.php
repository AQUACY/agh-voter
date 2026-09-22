<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use App\Exceptions\SmsDeliveryException;
use App\Support\GhanaPhone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MnotifySmsProvider implements SmsProvider
{
    public function send(string $phoneNumber, string $message): void
    {
        $key = (string) config('sms.api_key');

        if ($key === '') {
            throw new SmsDeliveryException('SMS_API_KEY is not configured for mNotify.');
        }

        $recipient = GhanaPhone::normalize($phoneNumber);

        $response = Http::timeout((int) config('sms.timeout'))
            ->acceptJson()
            ->asJson()
            ->post('https://api.mnotify.com/api/sms/quick?key='.urlencode($key), [
                'recipient' => [$recipient],
                'sender' => (string) config('sms.sender_id'),
                'message' => $message,
                'is_schedule' => false,
            ]);

        $payload = $response->json() ?? [];
        $status = strtolower((string) data_get($payload, 'status', ''));

        Log::info('sms.mnotify', [
            'http' => $response->status(),
            'status' => $status,
            'code' => data_get($payload, 'code'),
            'to_masked' => GhanaPhone::mask($phoneNumber),
            'sender' => (string) config('sms.sender_id'),
            'message_id' => data_get($payload, 'summary.message_id') ?? data_get($payload, 'summary._id'),
            'gateway' => data_get($payload, 'message'),
        ]);

        if ($response->failed()) {
            throw new SmsDeliveryException(
                'The SMS gateway rejected the message: '.($response->json('message') ?? $response->body())
            );
        }

        if ($status !== '' && ! in_array($status, ['success', 'ok'], true)) {
            throw new SmsDeliveryException(
                'The SMS gateway could not deliver the message: '.(string) data_get($payload, 'message', $status)
            );
        }
    }
}
