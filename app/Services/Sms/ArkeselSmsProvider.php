<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use App\Exceptions\SmsDeliveryException;
use App\Support\GhanaPhone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArkeselSmsProvider implements SmsProvider
{
    public function send(string $phoneNumber, string $message): void
    {
        $key = (string) config('sms.api_key');

        if ($key === '') {
            throw new SmsDeliveryException('SMS_API_KEY is not configured for Arkesel.');
        }

        $recipient = $this->toE164($phoneNumber);

        $response = Http::timeout((int) config('sms.timeout'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'api-key' => $key,
            ])
            ->post('https://sms.arkesel.com/api/v2/sms/send', [
                'sender' => (string) config('sms.sender_id'),
                'message' => $message,
                'recipients' => [$recipient],
            ]);

        $payload = $response->json() ?? [];
        $status = strtolower((string) data_get($payload, 'status', ''));

        Log::info('sms.arkesel', [
            'http' => $response->status(),
            'status' => $status,
            'to_masked' => GhanaPhone::mask($phoneNumber),
            'sender' => (string) config('sms.sender_id'),
            'gateway' => data_get($payload, 'data') ?? data_get($payload, 'message'),
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

    /**
     * Arkesel expects E.164 with a leading +, e.g. +233241234567.
     */
    private function toE164(string $phoneNumber): string
    {
        $normalized = GhanaPhone::normalize($phoneNumber);

        return str_starts_with($normalized, '+') ? $normalized : '+'.$normalized;
    }
}
