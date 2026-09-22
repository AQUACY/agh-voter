<?php

namespace App\Providers;

use App\Contracts\SmsProvider;
use App\Services\Sms\ArkeselSmsProvider;
use App\Services\Sms\HubtelSmsProvider;
use App\Services\Sms\LogSmsProvider;
use App\Services\Sms\MnotifySmsProvider;
use App\Services\SmsService;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsProvider::class, function () {
            return match (config('sms.provider')) {
                'arkesel' => new ArkeselSmsProvider,
                'hubtel' => new HubtelSmsProvider,
                'mnotify' => new MnotifySmsProvider,
                'log' => new LogSmsProvider,
                default => throw new InvalidArgumentException('Unsupported SMS_PROVIDER. Use log, arkesel, hubtel, or mnotify.'),
            };
        });

        $this->app->singleton(SmsService::class);
    }

    public function boot(): void
    {
        //
    }
}
