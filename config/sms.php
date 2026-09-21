<?php

return [
    /*
    | SMS_PROVIDER: log | arkesel | hubtel | splitsms
    | Switch providers without changing voting logic.
    */
    'provider' => env('SMS_PROVIDER', 'log'),
    'api_key' => env('SMS_API_KEY'),
    'api_secret' => env('SMS_API_SECRET'),
    'sender_id' => env('SMS_SENDER_ID', 'AGHVOTE'),
    'timeout' => (int) env('SMS_TIMEOUT', 15),
    'message_prefix' => env(
        'SMS_MESSAGE_PREFIX',
        'Asesewa Government Hospital Welfare Election'
    ),
];
