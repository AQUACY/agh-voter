<?php

namespace App\Contracts;

interface SmsProvider
{
    public function send(string $phoneNumber, string $message): void;
}
