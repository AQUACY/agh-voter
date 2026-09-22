<?php

namespace Tests\Feature;

use App\Exceptions\SmsDeliveryException;
use App\Services\Sms\MnotifySmsProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MnotifySmsProviderTest extends TestCase
{
    public function test_it_sends_via_mnotify_quick_sms_api(): void
    {
        config([
            'sms.api_key' => 'mnotify_test_key',
            'sms.sender_id' => 'AGHVOTE',
            'sms.timeout' => 5,
        ]);

        Http::fake([
            'api.mnotify.com/api/sms/quick*' => Http::response([
                'status' => 'success',
                'code' => '2000',
                'summary' => ['message_id' => 'abc123'],
            ], 200),
        ]);

        $this->app->make(MnotifySmsProvider::class)->send('0241231234', 'Test OTP message');

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://api.mnotify.com/api/sms/quick')
                && str_contains($request->url(), 'key=mnotify_test_key')
                && $request['sender'] === 'AGHVOTE'
                && $request['recipient'] === ['233241231234']
                && $request['message'] === 'Test OTP message'
                && $request['is_schedule'] === false;
        });
    }

    public function test_it_rejects_gateway_error_status(): void
    {
        config([
            'sms.api_key' => 'mnotify_test_key',
            'sms.sender_id' => 'AGHVOTE',
            'sms.timeout' => 5,
        ]);

        Http::fake([
            'api.mnotify.com/api/sms/quick*' => Http::response([
                'status' => 'error',
                'message' => 'Insufficient balance',
            ], 200),
        ]);

        $this->expectException(SmsDeliveryException::class);

        $this->app->make(MnotifySmsProvider::class)->send('0241231234', 'Test OTP message');
    }
}
