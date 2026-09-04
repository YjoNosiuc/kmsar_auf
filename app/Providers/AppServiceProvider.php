<?php

namespace App\Providers;

use App\Notifications\Channels\ResilientMailChannel;
use App\Services\SmtpSettingsService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Notification::extend('resilient-mail', function ($app) {
            return $app->make(ResilientMailChannel::class);
        });

        $this->app->make(SmtpSettingsService::class)->applyToConfig();
    }
}
