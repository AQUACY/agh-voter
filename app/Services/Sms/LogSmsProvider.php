<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use Illuminate\Support\Facades\Log;

class LogSmsProvider implements SmsProvider
{
    public function send(string $phoneNumber, string $message): void
    {
        Log::channel(config('logging.default'))->info('sms.send', [
            'provider' => 'log',
            'to' => $phoneNumber,
            'message' => $message,
        ]);
    }
}
