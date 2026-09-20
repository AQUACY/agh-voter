<?php

namespace App\Services;

use App\Contracts\SmsProvider;

class SmsService
{
    public function __construct(private readonly SmsProvider $provider) {}

    /**
     * Send an OTP SMS. Voting code should call this method, never a provider class.
     */
    public function sendOtp(string $phoneNumber, string $otp): void
    {
        $minutes = (int) config('otp.expiry_minutes', 5);
        $prefix = (string) config('sms.message_prefix');

        $this->provider->send(
            $phoneNumber,
            "{$prefix}: Your verification code is {$otp}. This code expires in {$minutes} minutes. Do not share this code."
        );
    }
}
