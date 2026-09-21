<?php

namespace Tests\Feature;

use App\Services\Sms\SplitSmsProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SplitSmsProviderTest extends TestCase
{
    public function test_it_sends_via_splitsms_bearer_api(): void
    {
        config([
            'sms.api_key' => 'sk_test_example',
            'sms.sender_id' => 'AGHVOTE',
            'sms.timeout' => 5,
        ]);

        Http::fake([
            'www.splitsms.com/api/v1/sms/send' => Http::response([
                'success' => true,
            ], 200),
        ]);

        $this->app->make(SplitSmsProvider::class)->send('0241231234', 'Test OTP message');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://www.splitsms.com/api/v1/sms/send'
                && $request->hasHeader('Authorization', 'Bearer sk_test_example')
                && $request['sender'] === 'AGHVOTE'
                && $request['recipients'] === ['233241231234']
                && $request['message'] === 'Test OTP message';
        });
    }
}
