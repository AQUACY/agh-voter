<?php

namespace App\Exceptions;

use RuntimeException;

class OtpThrottleException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $retryAfterSeconds = 60,
    ) {
        parent::__construct($message);
    }
}
